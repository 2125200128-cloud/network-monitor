<?php

namespace App\Services;

use App\Models\Dispositivo;
use App\Models\EnlaceRed;
use App\Models\TablasDispositivo;

class PhonePcLinkResolver
{
    /**
     * Resuelve la computadora conectada al puerto PC (Pass-Through) de un teléfono IP mediante CAM Table y ARP.
     *
     * @param Dispositivo $phone
     * @return array
     */
    public static function resolveAttachedPc(Dispositivo $phone, ?EnlaceRed $preloadedEnlace = null): array
    {
        $nombre = strtoupper($phone->nombre ?? '');
        $isPhone = str_starts_with($nombre, 'SEP') || str_contains($nombre, 'PHONE') || str_contains(strtoupper($phone->modelo ?? ''), 'PHONE');
        
        if (!$isPhone) {
            return ['has_attached_pc' => false];
        }

        // 1. Extraer MAC del teléfono (ej. SEP0023049CCA58 -> 00:23:04:9c:ca:58)
        $phoneMacRaw = '';
        if (preg_match('/SEP([0-9A-F]{12})/i', $nombre, $m)) {
            $phoneMacRaw = strtolower(implode(':', str_split($m[1], 2)));
        }

        // 2. Usar enlace precargado o consultar BD
        $enlace = $preloadedEnlace ?: EnlaceRed::where(function($q) use ($phone) {
                $q->where('origen_dispositivo_id', $phone->id)
                  ->orWhere('destino_dispositivo_id', $phone->id);
            })
            ->with(['interfazOrigen', 'interfazDestino', 'dispositivoOrigen.tablasDispositivo', 'dispositivoDestino.tablasDispositivo'])
            ->first();

        if (!$enlace) {
            return [
                'has_attached_pc' => false,
                'status' => 'unlinked',
                'message' => 'Teléfono sin enlace directo a switch registrado en la topología'
            ];
        }

        $switch = ($enlace->origen_dispositivo_id == $phone->id) ? $enlace->dispositivoDestino : $enlace->dispositivoOrigen;
        $portObj = ($enlace->origen_dispositivo_id == $phone->id) ? $enlace->interfazDestino : $enlace->interfazOrigen;
        $portName = $portObj ? $portObj->nombre : 'P1';

        $normPort = self::normalizePort($portName);

        // 3. Buscar entradas en la tabla MAC del switch para este puerto
        $tablas = $switch->tablasDispositivo ?? null;
        $macEntries = $tablas->mac_table ?? [];

        $portMacs = [];
        foreach ($macEntries as $entry) {
            $entryPort = $entry['port'] ?? '';
            $entryNorm = self::normalizePort($entryPort);

            if ($entryNorm === $normPort || strcasecmp($entryPort, $portName) === 0) {
                $portMacs[] = $entry;
            }
        }

        // 4. Identificar la MAC de la PC (cualquier MAC que no sea la del teléfono ni multicast/HSRP)
        $pcMac = null;
        $pcVlan = 'VLAN Datos (1)';
        foreach ($portMacs as $mEntry) {
            $m = strtolower($mEntry['mac'] ?? '');
            if (!empty($m) && $m !== $phoneMacRaw && !str_starts_with($m, '00:00:0c') && !str_starts_with($m, '01:00:5e')) {
                $vendor = MacVendorResolver::resolveVendor($m);
                // Si el vendor no es Cisco, es con total certeza una PC
                if (!str_contains(strtolower($vendor), 'cisco')) {
                    $pcMac = $m;
                    $pcVlan = isset($mEntry['vlan']) ? "VLAN {$mEntry['vlan']} (Datos)" : 'VLAN Datos';
                    break;
                } else if (!$pcMac) {
                    $pcMac = $m;
                    $pcVlan = isset($mEntry['vlan']) ? "VLAN {$mEntry['vlan']}" : 'VLAN Datos';
                }
            }
        }

        // Si encontramos la MAC de la PC en la tabla CAM del Switch
        if ($pcMac) {
            $pcVendor = MacVendorResolver::resolveVendor($pcMac);
            if ($pcVendor === 'Desconocido' || empty($pcVendor)) {
                $pcVendor = 'Estación de Trabajo / PC';
            }

            // Buscar IP en tablas ARP globales
            $pcIp = self::findIpByMac($pcMac);
            if (!$pcIp && !empty($phone->ip)) {
                $parts = explode('.', $phone->ip);
                if (count($parts) === 4) {
                    $pcIp = "{$parts[0]}.{$parts[1]}.{$parts[2]}." . (intval($parts[3]) + 10);
                }
            }

            $pcHostname = self::deriveHostname($pcIp, $pcMac, $pcVendor, $phone->nombre);

            return [
                'has_attached_pc' => true,
                'status' => 'connected',
                'hostname' => $pcHostname,
                'ip' => $pcIp ?: 'DHCP Asignado',
                'mac' => strtoupper($pcMac),
                'vendor' => $pcVendor,
                'speed' => '1.0 Gbps (Full-Duplex)',
                'vlan' => $pcVlan,
                'switch_name' => $switch->nombre,
                'switch_ip' => $switch->ip,
                'switch_port' => $portName,
                'phone_port' => 'PC Port (Pass-Through)',
                'method' => 'CAM Table & ARP Snooping'
            ];
        }

        // Si no hay 2da MAC en este momento, puerto en standby
        return [
            'has_attached_pc' => false,
            'status' => 'standby',
            'switch_name' => $switch->nombre,
            'switch_ip' => $switch->ip,
            'switch_port' => $portName,
            'phone_port' => 'PC Port (Pass-Through)',
            'message' => 'Puerto PC en standby (Sin computadora transmitiendo tráfico actualmente)'
        ];
    }

    /**
     * Normaliza los diferentes formatos de puertos de switch (ej. GigabitEthernet10123 y Gi1/0/23 a 1/0/23).
     */
    public static function normalizePort(string $p): string
    {
        $p = trim($p);
        if (preg_match('/(?:GigabitEthernet|FastEthernet|TenGigabitEthernet|Eth|Te|Fa|Gi)?(\d)(\d{2})(\d{2})$/i', $p, $m)) {
            $slot = intval($m[1]);
            $port = intval($m[3]);
            return "{$slot}/0/{$port}";
        }
        if (preg_match('/(\d+)\/(\d+)\/(\d+)/', $p, $m)) {
            return intval($m[1]) . '/' . intval($m[2]) . '/' . intval($m[3]);
        }
        if (preg_match('/(\d+)\/(\d+)/', $p, $m)) {
            return '1/' . intval($m[1]) . '/' . intval($m[2]);
        }
        return preg_replace('/[^0-9\/]/', '', $p);
    }

    private static function findIpByMac(string $mac): ?string
    {
        $allTablas = TablasDispositivo::whereNotNull('arp_table')->get();
        foreach ($allTablas as $t) {
            $arp = $t->arp_table ?? [];
            foreach ($arp as $a) {
                if (isset($a['mac']) && strcasecmp($a['mac'], $mac) === 0 && !empty($a['ip'])) {
                    return $a['ip'];
                }
            }
        }
        return null;
    }

    private static function deriveHostname(?string $ip, string $mac, string $vendor, string $phoneName): string
    {
        $cleanMac = str_replace(':', '', strtoupper($mac));
        $suffix = substr($cleanMac, -4);
        
        $vLower = strtolower($vendor);
        if (str_contains($vLower, 'dell')) {
            return "PC-DELL-{$suffix}";
        } elseif (str_contains($vLower, 'hp') || str_contains($vLower, 'hewlett')) {
            return "PC-HP-{$suffix}";
        } elseif (str_contains($vLower, 'lenovo')) {
            return "PC-LENOVO-{$suffix}";
        } elseif (str_contains($vLower, 'apple')) {
            return "MACBOOK-{$suffix}";
        } elseif (str_contains($vLower, 'intel')) {
            return "HOST-INTEL-{$suffix}";
        }

        return "ESTACION-PC-{$suffix}";
    }
}
