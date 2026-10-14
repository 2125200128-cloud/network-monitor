<?php

namespace App\Services;

use App\Models\Dispositivo;

/**
 * Fuente única del tipo de equipo para toda la app (mapa, inventario, ficha, PDF).
 *
 * Prioridad:
 *   1. tipo_manual       corrección hecha por un operador.
 *   2. tipo_detectado    clasificación por evidencia SNMP/CDP/LLDP (worker/device_classifier.py).
 *   3. nombre / sysDescr heurística antigua, solo mientras el clasificador no ha corrido;
 *                        se marca con confianza "baja".
 */
class TipoDispositivoResolver
{
    public const CATALOGO = [
        'switch' => ['label' => 'Switch L2/L3', 'rol' => 'ACCESS / SWITCH', 'image' => 'images/topology/switch-access.svg', 'size' => 145,
            'badge' => 'bg-blue-50 dark:bg-blue-950/60 text-hacienda-blue dark:text-blue-300 border-blue-200 dark:border-blue-800/40',
            'icon' => 'bg-blue-50 dark:bg-blue-950/60 text-hacienda-blue dark:text-blue-400'],
        'router' => ['label' => 'Router', 'rol' => 'ROUTER / EDGE', 'image' => 'images/topology/router.svg', 'size' => 140,
            'badge' => 'bg-orange-50 dark:bg-orange-950/60 text-orange-700 dark:text-orange-300 border-orange-200 dark:border-orange-800/40',
            'icon' => 'bg-orange-50 dark:bg-orange-950/60 text-orange-600 dark:text-orange-400'],
        'firewall' => ['label' => 'Firewall', 'rol' => 'FIREWALL', 'image' => 'images/topology/firewall-fortinet.svg', 'size' => 155,
            'badge' => 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800/40',
            'icon' => 'bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400'],
        'wlc' => ['label' => 'Controlador inalámbrico', 'rol' => 'ACCESS POINT / WLC', 'image' => 'images/topology/access-point.svg', 'size' => 130,
            'badge' => 'bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800/40',
            'icon' => 'bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400'],
        'access_point' => ['label' => 'Access Point Wi-Fi', 'rol' => 'ACCESS POINT / WLC', 'image' => 'images/topology/access-point.svg', 'size' => 125,
            'badge' => 'bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800/40',
            'icon' => 'bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400'],
        'servidor' => ['label' => 'Servidor', 'rol' => 'SERVIDOR', 'image' => 'images/topology/server.png', 'size' => 200,
            'badge' => 'bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800/40',
            'icon' => 'bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400'],
        'telefono' => ['label' => 'Teléfono IP (VoIP)', 'rol' => 'TELÉFONO IP (VOIP)', 'image' => 'images/topology/ip-phone.svg', 'size' => 110,
            'badge' => 'bg-pink-50 dark:bg-pink-950/60 text-pink-700 dark:text-pink-300 border-pink-200 dark:border-pink-800/40',
            'icon' => 'bg-pink-50 dark:bg-pink-950/60 text-pink-600 dark:text-pink-400'],
        'pc' => ['label' => 'PC / Estación', 'rol' => 'PC / WORKSTATION', 'image' => 'images/topology/pc.svg', 'size' => 120,
            'badge' => 'bg-cyan-50 dark:bg-cyan-950/60 text-cyan-700 dark:text-cyan-300 border-cyan-200 dark:border-cyan-800/40',
            'icon' => 'bg-cyan-50 dark:bg-cyan-950/60 text-cyan-600 dark:text-cyan-400'],
        'impresora' => ['label' => 'Impresora', 'rol' => 'IMPRESORA', 'image' => 'images/topology/pc.svg', 'size' => 110,
            'badge' => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700',
            'icon' => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'],
        'desconocido' => ['label' => 'Tipo no identificado', 'rol' => 'SIN IDENTIFICAR', 'image' => 'images/topology/switch-standard-1u.svg', 'size' => 130,
            'badge' => 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-400 border-gray-200 dark:border-slate-700',
            'icon' => 'bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400'],
    ];

    public static function tipos(): array
    {
        return array_keys(self::CATALOGO);
    }

    public static function resolver(Dispositivo $d): array
    {
        $evidencia = is_array($d->tipo_evidencia) ? $d->tipo_evidencia : [];

        if ($d->tipo_manual && isset(self::CATALOGO[$d->tipo_manual])) {
            $tipo = $d->tipo_manual;
            $confianza = 'manual';
            $fuente = 'manual';
            array_unshift($evidencia, 'Tipo corregido manualmente por un operador');
        } elseif ($d->tipo_detectado && isset(self::CATALOGO[$d->tipo_detectado])) {
            $tipo = $d->tipo_detectado;
            $confianza = $d->tipo_confianza ?: 'baja';
            $fuente = $d->tipo_fuente ?: 'nombre';
        } else {
            $tipo = $d->resolveTypeInfoPorNombre()['tipo'];
            $confianza = 'baja';
            $fuente = 'nombre';
            $evidencia = ['Aún sin clasificar por evidencia: deducido del nombre/sysDescr'];
        }

        $c = self::CATALOGO[$tipo];
        $sysDescr = strtoupper(($d->telemetriaChasis->model_name ?? '') . ' ' . ($d->modelo_detectado ?? ''));
        $image = $c['image'];
        $rol = $c['rol'];
        $size = $c['size'];
        // Variante gráfica de switch según la plataforma real reportada
        if ($tipo === 'switch') {
            if (preg_match('/NX-OS|NEXUS|\bN[3579]K-|\bN77-/', $sysDescr)) {
                [$image, $rol, $size] = ['images/topology/switch-nexus.svg', 'NEXUS / DATACENTER', 170];
            } elseif (preg_match('/C9[456]\d{2}|C6[58]\d{2}|C9500|CAT9K.*9[456]|S2T54/', $sysDescr)) {
                [$image, $rol, $size] = ['images/topology/switch-core.svg', 'DISTRIBUTION / CORE', 155];
            }
        }

        $modelo = $d->modelo_detectado ?: null;
        // El nombre "limpio" heurístico solo se usa si coincide con el tipo real
        $porNombre = $d->resolveTypeInfoPorNombre();
        $cleanModel = $modelo ?: ($porNombre['tipo'] === $tipo ? $porNombre['clean_model'] : $c['label'] . ' (modelo no reportado)');

        return [
            'tipo' => $tipo,
            'label' => $c['label'],
            'rol' => $rol,
            'image' => $image,
            'size' => $size,
            'badge_classes' => $c['badge'],
            'icon_container' => $c['icon'],
            'clean_model' => $cleanModel,
            'modelo' => $modelo,
            'fabricante' => $d->fabricante ?: null,
            'confianza' => $confianza,
            'fuente' => $fuente,
            'evidencia' => $evidencia,
        ];
    }
}
