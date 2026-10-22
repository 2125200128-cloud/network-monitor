<?php

namespace App\Services;

use App\Models\ServicioWeb;
use App\Models\HistoricoServicioWeb;
use App\Support\Sql;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Monitoreo de sitios web con solo su URL.
 *
 * Para no alarmar por un parpadeo, una falla se confirma: el sitio que falla se vuelve a probar al momento y
 * solo se marca caído si falla FALLOS_PARA_CAIDO rondas seguidas (la lentitud y los errores de contenido,
 * LENTOS_PARA_AVISO rondas). Si fallan casi todos a la vez, el problema es del monitor o de su red: se avisa
 * una sola vez y esa ronda no cuenta contra la disponibilidad de cada sitio.
 */
class WebServicePoller
{
    /** Umbral de lentitud (ms) mientras un sitio no tiene historial para calcular el suyo. */
    public const LATENCIA_WARNING_MS = 3500;
    /** Límites del umbral automático (el doble del p95 de la última semana). */
    public const UMBRAL_AUTO_MIN_MS = 1500;
    public const UMBRAL_AUTO_MAX_MS = 7000;   // por debajo del tiempo de espera, si no nunca avisaría

    public const FALLOS_PARA_CAIDO = 2;
    public const LENTOS_PARA_AVISO = 2;

    /** Si a la vez falla al menos esta fracción de los sitios (y 5 o más), el problema es del monitor o de su red. */
    private const FRACCION_FALLA_MASIVA = 0.8;
    private const MIN_SITIOS_FALLA_MASIVA = 5;
    public const CACHE_SIN_CONECTIVIDAD = 'web_monitor_sin_conectividad';

    /** Timeout total por petición (s) y timeout de conexión TCP/TLS (s). */
    private const TIMEOUT_S = 8;
    private const CONNECT_TIMEOUT_S = 5;
    /** Se revisa el contenido solo en los primeros bytes de la página (ahí están el título y los errores). */
    private const MAX_BYTES_CONTENIDO = 262144;

    /**
     * Firmas de páginas de error que algunos servidores entregan con HTTP 200. Se buscan en el título y en el
     * cuerpo; deben ser lo bastante específicas para no confundirse con texto normal de un trámite.
     */
    private const FIRMAS_ERROR = [
        '/<title>[^<]*(service unavailable|bad gateway|gateway time-?out|internal server error|runtime error|server error)[^<]*<\/title>/i' => 'página de error del servidor',
        '/<title>[^<]*(mantenimiento|maintenance)[^<]*<\/title>/i' => 'página de mantenimiento',
        "/Server Error in '\\/[^']*' Application/i" => 'error de la aplicación ASP.NET',
        '/<b>(Fatal error|Parse error)<\/b>:/i' => 'error fatal de PHP',
        '/Error establishing a database connection/i' => 'la aplicación no se conecta a su base de datos',
        '/SQLSTATE\[[0-9A-Z]{5}\]/' => 'error de base de datos (SQLSTATE)',
        '/\bORA-\d{5}:/' => 'error de base de datos Oracle',
        '/(Microsoft OLE DB Provider|\[ODBC [^\]]*Driver\])[^<]{0,80}error/i' => 'error de base de datos (ODBC/OLE DB)',
        '/HTTP Status 5\d\d|Apache Tomcat\/[\d.]+ - Error report/i' => 'error de Tomcat',
        '/java\.lang\.[A-Za-z]+Exception/' => 'excepción de Java',
    ];

    /**
     * Revisa un solo servicio (alta, edición o botón "Probar"). El resultado se aplica de inmediato,
     * sin esperar la segunda ronda: quien pulsa "Probar" quiere ver el estado real en ese momento.
     */
    public function checkService(ServicioWeb $servicio): array
    {
        $eval = $this->evaluar($servicio, $this->sondear($servicio));
        if ($eval['tipo'] === 'caido') {
            usleep(500_000);
            $eval = $this->evaluar($servicio, $this->sondear($servicio));
        }

        return $this->registrar($servicio, $eval, true);
    }

    /**
     * Revisa todos los servicios web activos en paralelo.
     */
    public function checkAllServices(): array
    {
        $this->actualizarLatenciaBase();
        $servicios = ServicioWeb::where('es_activo', true)->get()->keyBy('id');
        if ($servicios->isEmpty()) {
            return [];
        }

        $evals = [];
        foreach ($this->sondearLote($servicios) as $id => $res) {
            $evals[$id] = $this->evaluar($servicios[$id], $res);
        }

        // Confirmación inmediata: los que fallaron se prueban otra vez antes de contarles la falla
        $fallidos = $servicios->filter(fn ($s) => $evals[$s->id]['tipo'] === 'caido');
        if ($fallidos->isNotEmpty()) {
            usleep(500_000);
            foreach ($this->sondearLote($fallidos) as $id => $res) {
                $evals[$id] = $this->evaluar($servicios[$id], $res);
            }
        }

        // ¿Falla casi todo a la vez, sin respuesta HTTP? Entonces el que no llega es el monitor
        $sinRespuesta = collect($evals)->filter(fn ($e) => $e['tipo'] === 'caido' && $e['codigo'] === 0);
        if ($servicios->count() >= self::MIN_SITIOS_FALLA_MASIVA
            && $sinRespuesta->count() >= ceil($servicios->count() * self::FRACCION_FALLA_MASIVA)) {
            $previo = Cache::get(self::CACHE_SIN_CONECTIVIDAD);
            Cache::put(self::CACHE_SIN_CONECTIVIDAD, [
                'desde' => $previo['desde'] ?? now()->toIso8601String(),
                'ultimo' => now()->toIso8601String(),
                'fallidos' => $sinRespuesta->count(),
                'total' => $servicios->count(),
                'error' => $sinRespuesta->countBy('error_corto')->sortDesc()->keys()->first(),
            ], now()->addMinutes(2));

            return [];   // no se cambia el estado de ningún sitio ni se guarda histórico de esta ronda
        }
        Cache::forget(self::CACHE_SIN_CONECTIVIDAD);

        $resultados = [];
        foreach ($servicios as $servicio) {
            $resultados[] = $this->registrar($servicio, $evals[$servicio->id], false);
        }

        $this->purgarHistorico();

        return $resultados;
    }

    /** Estado del monitor cuando la última ronda falló casi completa (null si hay conectividad). */
    public static function sinConectividad(): ?array
    {
        return Cache::get(self::CACHE_SIN_CONECTIVIDAD);
    }

    /**
     * Elimina el histórico más antiguo que los días configurados en Configuración → Retención.
     */
    public function purgarHistorico(): int
    {
        $dias = \App\Models\ConfiguracionGeneral::retencionDias()['historico_servicio_web'];
        return HistoricoServicioWeb::where('created_at', '<', now()->subDays($dias))->delete();
    }

    // ===================== Petición =====================

    /** @return Response|\Throwable */
    private function sondear(ServicioWeb $servicio)
    {
        try {
            return $this->request()->send(strtoupper($servicio->metodo ?: 'GET'), $servicio->url);
        } catch (\Throwable $e) {
            return $e;
        }
    }

    /** @return array<int, Response|\Throwable|null> por id de servicio */
    private function sondearLote($servicios): array
    {
        $responses = Http::pool(function (Pool $pool) use ($servicios) {
            $requests = [];
            foreach ($servicios as $servicio) {
                $requests[] = $this->request($pool->as((string) $servicio->id))
                    ->send(strtoupper($servicio->metodo ?: 'GET'), $servicio->url);
            }
            return $requests;
        });

        $salida = [];
        foreach ($servicios as $servicio) {
            $salida[$servicio->id] = $responses[(string) $servicio->id] ?? null;
        }
        return $salida;
    }

    /**
     * Configuración común de la petición HTTP (sobre el cliente normal o sobre el pool).
     */
    private function request($client = null)
    {
        return ($client ?? Http::getFacadeRoot())
            ->timeout(self::TIMEOUT_S)
            ->connectTimeout(self::CONNECT_TIMEOUT_S)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) NOC-NetworkMonitor/2.0',
            ])
            ->withOptions([
                // Certificados internos / IPs sin SSL válido (la validez del certificado la revisa SslCertificateChecker)
                'verify' => false,
                'allow_redirects' => [
                    'max' => 5,
                    'strict' => false,
                    'referer' => true,
                    'protocols' => ['http', 'https'],
                ],
            ]);
    }

    // ===================== Evaluación =====================

    /**
     * Clasifica una respuesta: tipo ok | degradado (lento, 4xx o contenido de error) | caido.
     *
     * @param Response|\Throwable|null $res
     */
    private function evaluar(ServicioWeb $servicio, $res): array
    {
        $eval = ['tipo' => 'caido', 'codigo' => 0, 'latencia' => null, 'mensaje' => null, 'error_corto' => null,
                 'dns' => null, 'conexion' => null, 'tls' => null, 'servidor' => null];

        if (!$res instanceof Response) {
            [$eval['error_corto'], $eval['mensaje']] = $this->describirErrorConexion($res);
            return $eval;
        }

        $eval['codigo'] = $res->status();
        $eval = array_merge($eval, $this->tiempos($res));
        $latencia = $eval['latencia'];

        if ($eval['codigo'] >= 500) {
            $eval['mensaje'] = "El servidor respondió HTTP {$eval['codigo']} (error del servidor).";
            return $eval;
        }
        if ($eval['codigo'] >= 400) {
            $eval['tipo'] = 'degradado';
            $eval['mensaje'] = "Respuesta HTTP {$eval['codigo']}: recurso no encontrado o acceso restringido.";
            return $eval;
        }

        // 2xx/3xx: la página contesta; falta ver que no sea una página de error y que no esté lenta
        if (strtoupper($servicio->metodo ?: 'GET') === 'GET') {
            $problema = $this->revisarContenido($servicio, $res);
            if ($problema) {
                [$eval['tipo'], $eval['mensaje']] = $problema;
                return $eval;
            }
        }

        $umbral = $servicio->umbralLatenciaMs();
        if ($latencia !== null && $latencia > $umbral) {
            $eval['tipo'] = 'degradado';
            $origen = $servicio->latencia_umbral_ms ? '' : ($servicio->latencia_base_ms ? ', automático' : ', por defecto');
            $eval['mensaje'] = "Respuesta lenta: {$latencia} ms (umbral {$umbral} ms{$origen})" . $this->parteMasLenta($eval) . '.';
            return $eval;
        }

        $eval['tipo'] = 'ok';
        return $eval;
    }

    /** @return array{0:string,1:string}|null [tipo, mensaje] si la página tiene un problema de contenido */
    private function revisarContenido(ServicioWeb $servicio, Response $res): ?array
    {
        $cuerpo = substr((string) $res->body(), 0, self::MAX_BYTES_CONTENIDO);
        $tipo = strtolower((string) $res->header('Content-Type'));

        if ($servicio->texto_esperado) {
            $legible = html_entity_decode(strip_tags($cuerpo), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (mb_stripos($cuerpo, $servicio->texto_esperado) === false && mb_stripos($legible, $servicio->texto_esperado) === false) {
                return ['caido', "La página respondió HTTP {$res->status()} pero no contiene el texto esperado «{$servicio->texto_esperado}»."];
            }
        }

        if (trim($cuerpo) === '') {
            return ['degradado', "La página respondió HTTP {$res->status()} pero sin contenido."];
        }

        // Las firmas de error solo aplican a páginas de texto (no a descargas, PDF o imágenes)
        if ($tipo === '' || str_contains($tipo, 'text') || str_contains($tipo, 'html') || str_contains($tipo, 'json') || str_contains($tipo, 'xml')) {
            foreach (self::FIRMAS_ERROR as $patron => $descripcion) {
                if (preg_match($patron, $cuerpo)) {
                    return ['degradado', "La página respondió HTTP {$res->status()} pero muestra un error: {$descripcion}."];
                }
            }
        }

        return null;
    }

    /** Latencia total y su desglose (ms) a partir de las estadísticas de cURL de la última petición. */
    private function tiempos(Response $res): array
    {
        $stats = $res->transferStats;
        $info = $stats?->getHandlerStats() ?? [];
        $ms = fn ($v) => $v !== null ? round(max(0, (float) $v) * 1000, 2) : null;

        $total = $stats?->getTransferTime();
        $dns = $info['namelookup_time'] ?? null;
        $conn = $info['connect_time'] ?? null;
        $tlsFin = ($info['appconnect_time'] ?? 0) > 0 ? $info['appconnect_time'] : null;
        $pre = $info['pretransfer_time'] ?? null;
        $primerByte = $info['starttransfer_time'] ?? null;

        return [
            'latencia' => $total !== null ? $ms($total) : null,
            'dns' => $ms($dns),
            'conexion' => $conn !== null && $dns !== null ? $ms($conn - $dns) : null,
            'tls' => $tlsFin !== null && $conn !== null ? $ms($tlsFin - $conn) : null,
            'servidor' => $primerByte !== null && $pre !== null ? $ms($primerByte - $pre) : null,
        ];
    }

    /** Dice en qué parte se fue el tiempo cuando un sitio está lento. */
    private function parteMasLenta(array $eval): string
    {
        $partes = array_filter(['resolución DNS' => $eval['dns'], 'conexión' => $eval['conexion'],
                                'negociación TLS' => $eval['tls'], 'respuesta del servidor' => $eval['servidor']],
                               fn ($v) => $v !== null);
        if (!$partes || !$eval['latencia']) {
            return '';
        }
        arsort($partes);
        $parte = array_key_first($partes);
        return sprintf('; la mayor parte en %s: %s ms', $parte, number_format($partes[$parte], 0));
    }

    /** @return array{0:string,1:string} [error corto para agrupar, mensaje legible] */
    private function describirErrorConexion($e): array
    {
        $raw = $e instanceof \Throwable ? $e->getMessage() : 'sin respuesta';
        preg_match('/cURL error (\d+)/', $raw, $m);
        $corto = match ((int) ($m[1] ?? 0)) {
            6 => 'No se pudo resolver el nombre (DNS)',
            7 => 'Conexión rechazada o sin ruta al servidor',
            28 => 'Tiempo de espera agotado (' . self::TIMEOUT_S . ' s)',
            35, 60 => 'Falló la negociación TLS/SSL',
            52 => 'El servidor cerró la conexión sin responder',
            56 => 'Se cortó la conexión al recibir la respuesta',
            default => 'Sin respuesta / error de conexión',
        };
        return [$corto, $corto . ($raw !== 'sin respuesta' ? ': ' . mb_strimwidth($raw, 0, 220, '…') : '.')];
    }

    // ===================== Registro =====================

    /**
     * Aplica la evaluación con la regla de confirmación, guarda estado e histórico y devuelve el resultado.
     */
    private function registrar(ServicioWeb $servicio, array $eval, bool $inmediato): array
    {
        $fallos = $eval['tipo'] === 'caido' ? $servicio->fallos_consecutivos + 1 : 0;
        $lentos = $eval['tipo'] === 'degradado' ? $servicio->lentos_consecutivos + 1 : 0;
        $medido = ['ok' => 'online', 'degradado' => 'warning', 'caido' => 'offline'][$eval['tipo']];

        $confirmado = $inmediato || match ($eval['tipo']) {
            'caido' => $fallos >= self::FALLOS_PARA_CAIDO,
            'degradado' => $lentos >= self::LENTOS_PARA_AVISO,
            default => true,
        };

        if ($confirmado) {
            $estado = $medido;
            $detalle = $eval['mensaje'];
        } else {
            // Primera vez que se ve el problema: se conserva el estado anterior hasta confirmarlo.
            // Si estaba caído y ya contesta, deja de estar caído aunque siga lento.
            $previo = $servicio->estado ?: 'unknown';
            $estado = match (true) {
                $previo === 'offline' && $eval['tipo'] === 'degradado' => 'warning',
                $previo === 'unknown' => 'unknown',
                default => $previo,
            };
            $limite = $eval['tipo'] === 'caido' ? self::FALLOS_PARA_CAIDO : self::LENTOS_PARA_AVISO;
            $detalle = "Sin confirmar (1 de {$limite}): " . $eval['mensaje'];
        }

        $datos = [
            'estado' => $estado,
            'codigo_http' => $eval['codigo'],
            'tiempo_respuesta_ms' => $eval['latencia'],
            'detalles_error' => $detalle,
        ];
        $tiempos = [
            'tiempo_dns_ms' => $eval['dns'],
            'tiempo_conexion_ms' => $eval['conexion'],
            'tiempo_tls_ms' => $eval['tls'],
            'tiempo_servidor_ms' => $eval['servidor'],
        ];

        // Escritura incondicional (no solo columnas "dirty"): si dos sondeos se solapan
        // (bucle de 15 s + botón "Probar"), la fila nunca queda con valores mezclados.
        $actualizacion = $datos + $tiempos + [
            'ultimo_chequeo' => now(),
            'fallos_consecutivos' => min($fallos, 1000),
            'lentos_consecutivos' => min($lentos, 1000),
        ];
        ServicioWeb::whereKey($servicio->getKey())->update($actualizacion);
        $servicio->forceFill($actualizacion)->syncOriginal();

        HistoricoServicioWeb::create($datos + $tiempos + [
            'servicio_web_id' => $servicio->id,
            'created_at' => now(),
        ]);

        return $datos + [
            'id' => $servicio->id,
            'nombre' => $servicio->nombre,
            'url' => $servicio->url,
        ];
    }

    /**
     * Tiempo normal de cada sitio: p95 de sus respuestas correctas (2xx/3xx) de los últimos 7 días.
     * Se recalcula cada hora; con menos de 100 muestras el sitio usa el umbral por defecto.
     */
    private function actualizarLatenciaBase(): void
    {
        if (Cache::has('web_latencia_base_calculada')) {
            return;
        }

        $p95 = Sql::esPgsql()
            ? 'percentile_cont(0.95) WITHIN GROUP (ORDER BY tiempo_respuesta_ms)'
            : 'AVG(tiempo_respuesta_ms) * 1.5';   // MySQL no tiene percentiles: aproximación
        $filas = DB::table('historico_servicio_web')
            ->where('created_at', '>=', now()->subDays(7))
            ->whereBetween('codigo_http', [200, 399])
            ->whereNotNull('tiempo_respuesta_ms')
            ->groupBy('servicio_web_id')
            ->havingRaw('COUNT(*) >= 100')
            ->get(['servicio_web_id', DB::raw("{$p95} AS base")]);

        foreach ($filas as $f) {
            ServicioWeb::whereKey($f->servicio_web_id)->update(['latencia_base_ms' => round((float) $f->base, 1)]);
        }
        // Se marca al final: si algo falla, la siguiente ronda lo vuelve a intentar
        Cache::put('web_latencia_base_calculada', true, now()->addHour());
    }
}
