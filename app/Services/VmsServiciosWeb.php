<?php

namespace App\Services;

use App\Models\ServicioWeb;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Relaciona cada sitio web monitoreado con la(s) VM(s) que lo atienden, con lo leído de los ESXi
 * (worker/esxi_collector.py → esxi_estado):
 * - Automático: la IP a la que se conectó el monitor (ip_servidor) es una IP de la VM.
 * - Manual: VMs elegidas en "Editar sitio" (sitios detrás de un balanceador/VIP).
 * Solo datos leídos: estado de la VM, CPU y memoria activa que reporta el host, y el estado del host.
 */
class VmsServiciosWeb
{
    /** Lecturas de ESXi más viejas que esto no se usan (el colector lee cada minuto). */
    private const MINUTOS_VIGENTE = 5;

    private static ?array $memo = null;

    /** @return array{por_ip: array<string, array>, por_nombre: array<string, array>} */
    public static function mapa(): array
    {
        return self::$memo ??= Cache::remember('vms_servicios_web_mapa', 30, function () {
            $porIp = $porNombre = [];
            // Se usa la última lectura de cada host aunque sea vieja, marcada como tal: así el sitio sigue
            // mostrando su VM y se ve que el dato no está al día (en vez de desaparecer).
            $hosts = DB::table('esxi_estado as e')->join('dispositivos as d', 'd.id', '=', 'e.dispositivo_id')
                ->whereNotNull('e.leido_en')
                ->get(['d.id', 'd.nombre', 'd.estado as ping', 'e.conexion', 'e.vms', 'e.cpu_mhz_total', 'e.nucleos', 'e.leido_en']);
            foreach ($hosts as $h) {
                $mhzNucleo = $h->nucleos ? $h->cpu_mhz_total / $h->nucleos : null;
                $minutos = (int) floor(\Carbon\Carbon::parse($h->leido_en)->diffInMinutes(now(), true));
                foreach (json_decode($h->vms ?? '[]', true) ?: [] as $vm) {
                    $encendida = ($vm['estado'] ?? null) === 'poweredOn';
                    $info = [
                        'nombre' => $vm['nombre'],
                        'host' => strtok($h->nombre, '.'),
                        'host_id' => $h->id,
                        'host_ping' => $h->ping,
                        'host_conexion' => $h->conexion,
                        'lectura_min' => $minutos,
                        'desactualizada' => $minutos >= self::MINUTOS_VIGENTE,
                        'estado' => $vm['estado'] ?? null,
                        'salud' => $vm['salud'] ?? null,
                        // % de la capacidad de sus vCPU (MHz en uso / (vCPU × MHz por núcleo del host))
                        'cpu_pct' => $encendida && isset($vm['cpu_mhz'], $vm['vcpu']) && $vm['vcpu'] && $mhzNucleo
                            ? round(min(100, $vm['cpu_mhz'] / ($vm['vcpu'] * $mhzNucleo) * 100), 1) : null,
                        // Memoria activa del invitado respecto a la asignada
                        'ram_pct' => $encendida && isset($vm['ram_mb_uso']) && !empty($vm['ram_mb'])
                            ? round(min(100, $vm['ram_mb_uso'] / $vm['ram_mb'] * 100), 1) : null,
                        'ips' => array_values(array_unique(array_filter(array_merge([$vm['ip'] ?? null], $vm['ips'] ?? [])))),
                        'latido' => $vm['latido'] ?? null,
                        'uptime_seg' => $encendida ? ($vm['uptime_seg'] ?? null) : null,
                        'balloon_mb' => $vm['balloon_mb'] ?? null,
                        'swap_mb' => $vm['swap_mb'] ?? null,
                        // Particiones vistas desde el SO invitado, con % usado
                        'discos' => array_map(fn ($d) => $d + [
                            'usado_pct' => $d['libre'] !== null ? round(($d['capacidad'] - $d['libre']) / $d['capacidad'] * 100, 1) : null,
                        ], $vm['discos'] ?? []),
                    ];
                    $porNombre[$vm['nombre']] = $info;
                    foreach ($info['ips'] as $ip) {
                        $porIp[$ip] = $info;
                    }
                }
            }
            return ['por_ip' => $porIp, 'por_nombre' => $porNombre];
        });
    }

    /** VMs del sitio, cada una con 'origen' (ip|manual) y 'aviso' (lo que está mal en ella o su host, o null). */
    public static function para(ServicioWeb $s): array
    {
        $mapa = self::mapa();
        $vms = [];
        foreach ($s->vms_manual ?? [] as $nombre) {
            $vms[$nombre] = ($mapa['por_nombre'][$nombre] ?? ['nombre' => $nombre, 'sin_lectura' => true]) + ['origen' => 'manual'];
        }
        $ip = $s->ip_servidor ?: self::ipPorDns($s->url);
        if ($ip && isset($mapa['por_ip'][$ip]) && !isset($vms[$mapa['por_ip'][$ip]['nombre']])) {
            $vms[$mapa['por_ip'][$ip]['nombre']] = $mapa['por_ip'][$ip] + ['origen' => 'ip', 'ip' => $ip];
        }
        return array_values(array_map(function ($vm) {
            $avisos = self::avisos($vm);
            return $vm + ['avisos' => $avisos, 'aviso' => $avisos[0]['texto'] ?? null];
        }, $vms));
    }

    /** Nombres de todas las VMs leídas (para elegirlas en el formulario), con su host. */
    public static function opciones(): array
    {
        return collect(self::mapa()['por_nombre'])->map(fn ($v) => ['nombre' => $v['nombre'], 'host' => $v['host']])
            ->sortBy(fn ($v) => mb_strtolower($v['nombre']))->values()->all();
    }

    /** Disco del SO invitado: aviso desde este % usado y crítico desde el otro (igual que los datastores). */
    public const DISCO_AVISO_PCT = 80;
    public const DISCO_CRITICO_PCT = 90;
    /** Encendida hace menos de esto = arrancó o se reinició hace poco. */
    private const MINUTOS_REINICIO = 30;

    /**
     * Lo que está mal en la VM o su host, de lo leído; lo crítico primero.
     * @return list<array{nivel: string, texto: string}>
     */
    private static function avisos(array $vm): array
    {
        if (!empty($vm['sin_lectura'])) {
            return [['nivel' => 'warn', 'texto' => 'Sin lectura reciente de esta VM']];
        }
        if (!empty($vm['desactualizada'])) {
            // Con datos viejos los problemas se siguen informando con la última lectura y aclarándolo; si se dejaran
            // de informar, las alarmas se darían por resueltas sin que nada haya cambiado.
            $nota = " (última lectura hace {$vm['lectura_min']} min)";
            return array_merge(
                [['nivel' => 'warn', 'texto' => "Sin lectura del ESXi desde hace {$vm['lectura_min']} min (los datos de la VM no están al día)"]],
                array_map(fn ($x) => ['nivel' => $x['nivel'], 'texto' => $x['texto'] . $nota], self::avisos(['desactualizada' => false] + $vm))
            );
        }
        $a = [];
        $add = function ($nivel, $texto) use (&$a) { $a[] = compact('nivel', 'texto'); };

        if ($vm['host_ping'] === 'offline') {
            $add('crit', "Su host {$vm['host']} no responde");
        } elseif ($vm['host_conexion'] && $vm['host_conexion'] !== 'connected') {
            $add('crit', "Su host {$vm['host']} está desconectado");
        }
        if ($vm['estado'] === 'poweredOff') {
            $add('crit', 'La VM está apagada');
        } elseif ($vm['estado'] === 'suspended') {
            $add('crit', 'La VM está suspendida');
        }
        if ($vm['estado'] === 'poweredOn') {
            if ($vm['latido'] === 'red') {
                $add('crit', 'El sistema operativo no responde (sin latido de VMware Tools)');
            } elseif ($vm['latido'] === 'yellow') {
                $add('warn', 'Latido intermitente del sistema operativo');
            }
            foreach ($vm['discos'] as $d) {
                if ($d['usado_pct'] !== null && $d['usado_pct'] >= self::DISCO_AVISO_PCT) {
                    $add($d['usado_pct'] >= self::DISCO_CRITICO_PCT ? 'crit' : 'warn',
                        "Disco {$d['ruta']} al {$d['usado_pct']}% (libres " . self::gb($d['libre']) . ')');
                }
            }
            if ($vm['uptime_seg'] !== null && $vm['uptime_seg'] < self::MINUTOS_REINICIO * 60) {
                $add('warn', 'Se encendió o reinició hace ' . max(1, intdiv($vm['uptime_seg'], 60)) . ' min');
            }
            if ($vm['swap_mb']) {
                $add('warn', "El host pasó {$vm['swap_mb']} MB de su memoria a disco (swap)");
            } elseif ($vm['balloon_mb']) {
                $add('warn', "El host le está reclamando {$vm['balloon_mb']} MB de memoria (balloon)");
            }
            if ($vm['cpu_pct'] !== null && $vm['cpu_pct'] >= 90) {
                $add('warn', "CPU de la VM al {$vm['cpu_pct']}%");
            }
        }
        if ($vm['salud'] === 'red') {
            $add('crit', 'VMware reporta la VM en rojo');
        }
        usort($a, fn ($x, $y) => ($x['nivel'] === 'crit' ? 0 : 1) <=> ($y['nivel'] === 'crit' ? 0 : 1));
        return $a;
    }

    private static function gb(?int $bytes): string
    {
        return $bytes === null ? '—' : number_format($bytes / 1024 ** 3, 1) . ' GB';
    }

    /** Si el monitor aún no se ha conectado al sitio (sin ip_servidor), se usa lo que resuelve el DNS. */
    private static function ipPorDns(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return null;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }
        // '' = no resolvió (null no se guarda en caché y se volvería a consultar el DNS en cada petición)
        return Cache::remember('dns_sitio_' . md5($host), 600, fn () => (gethostbynamel($host) ?: [''])[0]) ?: null;
    }
}
