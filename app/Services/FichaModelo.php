<?php

namespace App\Services;

use App\Models\Dispositivo;
use App\Models\TelemetriaChasis;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Datos de la ficha técnica de un equipo, en tres grupos:
 *   - Identificación: lo que el equipo reporta (SNMP) o sus vecinos (CDP/LLDP).
 *   - Hardware del modelo: lo que se deduce del número de parte (formato, puertos, uplinks, PoE, ranuras).
 *     No es una medición; la vista lo rotula "según número de parte".
 *   - Estado: última respuesta, uptime e inventario medido.
 * Solo se incluyen valores conocidos: nada de "Sin dato" de relleno ni especificaciones de catálogo inventadas.
 */
class FichaModelo
{
    public static function para(Dispositivo $d, ?TelemetriaChasis $chasis, Collection $interfaces, array $tipo): array
    {
        $spec = DeviceArtwork::especificacionDispositivo($d);
        $parte = strtoupper(trim(($d->modelo_detectado ?? '') . ' ' . ($chasis?->model_name ?? '')));
        $uplink = self::tipoUplink($parte);

        $formato = match ($spec['dibujo'] ?? null) {
            'rack' => 'Rack ' . $spec['u'] . 'U',
            'modular' => 'Chasis modular de ' . $spec['ranuras'] . ' ranuras',
            'escritorio' => 'Escritorio',
            'ap' => 'Montaje en techo',
            'radio' => 'Unidad exterior (radioenlace)',
            default => null,
        };

        // Los puertos solo se publican cuando salen de la nomenclatura del número de parte; en FortiGate, ISR,
        // WLC 9800 o Huawei sin modelo la cantidad del dibujo es aproximada y aquí se omite.
        $exacto = $spec && ($spec['puertos_exactos'] ?? true);
        $tipoRanura = preg_match('/^Cisco (18|28|29)\d{2}/', $spec['serie'] ?? '') ? 'HWIC' : 'NIM';

        // Resumen de puertos del número de parte, p.ej. "48 RJ45 PoE+ · 4 SFP+"
        $resumen = $exacto ? implode(' · ', array_filter([
            $spec['rj45'] ? $spec['rj45'] . ' RJ45' . ($spec['poe'] ? ' PoE+' : '') : null,
            $spec['sfp'] ? $spec['sfp'] . ' ' . ($uplink['sfp'] ?? 'SFP') : null,
            $spec['qsfp'] ? $spec['qsfp'] . ' ' . ($uplink['qsfp'] ?? 'QSFP') : null,
            $spec['nim'] ? $spec['nim'] . ' ranuras ' . $tipoRanura : null,
        ])) : null;

        $modelo = $tipo['modelo'] ?: ($spec['modelo'] ?? null);
        $sysDescr = trim((string) $chasis?->model_name);
        // sysDescr solo si aporta algo distinto al modelo (en FortiGate es la misma cadena)
        if ($sysDescr !== '' && $modelo && str_contains(strtoupper($sysDescr), strtoupper(trim(preg_replace('/^cisco\s+/i', '', $modelo))))
            && mb_strlen($sysDescr) < mb_strlen($modelo) + 20) {
            $sysDescr = '';
        }

        $identificacion = array_filter([
            'Fabricante' => $tipo['fabricante'] ?: ($spec ? self::marca($spec) : null),
            'Modelo' => $modelo,
            'Serie' => $spec['serie'] ?? null,
            'Número de serie' => $chasis?->serial_number,
            'Versión de software' => $chasis?->os_version,
            'Descripción SNMP' => $sysDescr !== '' ? \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $sysDescr), 140) : null,
            'sysObjectID' => $d->sys_object_id,
        ]);

        $hardware = $spec ? array_filter([
            'Formato' => $formato,
            'Puertos de cobre' => $exacto && $spec['rj45'] ? $spec['rj45'] . ' × RJ45' . ($spec['poe'] ? ' con PoE+' : '') : null,
            'Puertos ópticos' => $exacto && $spec['sfp'] ? $spec['sfp'] . ' × ' . ($uplink['sfp'] ?? 'SFP') : null,
            'Puertos de alta velocidad' => $exacto && $spec['qsfp'] ? $spec['qsfp'] . ' × ' . ($uplink['qsfp'] ?? 'QSFP') : null,
            'Ranuras' => $spec['ranuras'] ? $spec['ranuras'] . ' (' . count($spec['sups']) . ' para supervisora)'
                : ($exacto && $spec['nim'] ? $spec['nim'] . ' ' . $tipoRanura : null),
        ]) : [];

        $medidas = $interfaces->filter(fn ($i) => in_array($i->port_type, ['copper_rj45', 'sfp_fiber'], true));
        $estado = array_filter([
            'Tipo de equipo' => $tipo['label'] . ' · confianza ' . $tipo['confianza'],
            'Uptime' => $chasis?->uptime_str && !in_array($chasis->uptime_str, ['Sin respuesta SNMP', 'Inaccesible'], true) ? $chasis->uptime_str : null,
            'Última respuesta' => $d->ultima_vez_visto ? Carbon::parse($d->ultima_vez_visto)->format('d/m/Y H:i') : null,
            'Interfaces físicas (SNMP)' => $medidas->count() ? (string) $medidas->count() : null,
        ]);

        return [
            'spec' => $spec,
            'serie' => $spec['serie'] ?? null,
            'formato' => $formato,
            'resumen' => $resumen,
            'etiqueta' => trim(implode(' · ', array_filter([$formato, $resumen]))) ?: null,
            'uplink' => $uplink['sfp'] ?? 'SFP',
            'tiene_poe' => (bool) ($spec['poe'] ?? false) || ($chasis?->poe_total_budget_w ?? 0) > 0,
            'grupos' => array_filter([
                'Identificación' => $identificacion,
                'Hardware según número de parte' => $hardware,
                'Estado' => $estado,
            ]),
        ];
    }

    /** Tipo de uplink según la nomenclatura del número de parte (sufijos estándar de Cisco/Huawei). */
    private static function tipoUplink(string $parte): array
    {
        return match (true) {
            (bool) preg_match('/\d+Y\d*C|YC-|YC\b/', $parte) => ['sfp' => 'SFP28 (25G)', 'qsfp' => 'QSFP28 (100G)'],
            (bool) preg_match('/-\d+X\b|\dX-|\d+X$|PQ\b|PQ-|31108|S\d{2}S\d+X|\d[A-Z]*4X/', $parte) => ['sfp' => 'SFP+ (10G)', 'qsfp' => 'QSFP+ (40G)'],
            (bool) preg_match('/-\d+G\b|\d+G-/', $parte) => ['sfp' => 'SFP (1G)'],
            default => [],
        };
    }

    private static function marca(array $spec): ?string
    {
        return match ($spec['marca'] ?? '') {
            'cisco' => 'Cisco',
            'HUAWEI' => 'Huawei',
            'FORTINET' => 'Fortinet',
            'ARISTA' => 'Arista',
            '' => null,
            default => $spec['marca'],
        };
    }
}
