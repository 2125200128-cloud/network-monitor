<?php

return [

    // Intérprete de Python de los workers (vacío = .venv del proyecto o el del sistema; ver App\Support\Python)
    'python_path' => env('PYTHON_PATH'),

    // Valores iniciales mientras Configuración no tenga los suyos
    'snmp_community' => env('SNMP_COMMUNITY', 'public'),
    'ip_switch_core' => env('IP_SWITCH_CORE', '10.4.254.3'),

    /*
    | Días que se conservan los registros históricos. La limpieza la hace `php artisan noc:sync`
    | (como máximo una vez por hora). Las pantallas usan:
    |  - metricas_red: solo la última hora (lo histórico sale de metricas_red_hora).
    |  - global_metrics: gráfica de tendencias de hasta 7 días.
    |  - metricas_red_hora: disponibilidad de 24 h / 7 d / 30 d.
    |  - alarmas resueltas: MTTR/MTBF de 7 días.
    */
    'retencion_dias' => [
        'metricas_red' => (int) env('NOC_RETENCION_METRICAS_DIAS', 7),
        'global_metrics' => (int) env('NOC_RETENCION_TENDENCIAS_DIAS', 30),
        'metricas_red_hora' => (int) env('NOC_RETENCION_CONSOLIDADO_DIAS', 180),
        'alarmas_resueltas' => (int) env('NOC_RETENCION_ALARMAS_DIAS', 180),
        'historico_servicio_web' => (int) env('NOC_RETENCION_SERVICIOS_WEB_DIAS', 7),
    ],

];
