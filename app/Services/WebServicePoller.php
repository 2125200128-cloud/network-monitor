<?php

namespace App\Services;

use App\Models\ServicioWeb;
use App\Models\HistoricoServicioWeb;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WebServicePoller
{
    /** Latencia (ms) a partir de la cual un 2xx/3xx se considera "warning". */
    public const LATENCIA_WARNING_MS = 3500;

    /** Timeout total por petición (s) y timeout de conexión TCP/TLS (s). */
    private const TIMEOUT_S = 8;
    private const CONNECT_TIMEOUT_S = 5;

    /**
     * Revisa el estado de un servicio web específico.
     */
    public function checkService(ServicioWeb $servicio): array
    {
        $inicio = microtime(true);

        try {
            $res = $this->request()->send(strtoupper($servicio->metodo ?: 'GET'), $servicio->url);
        } catch (\Throwable $e) {
            $res = $e;
        }

        return $this->registrar($servicio, $res, $inicio);
    }

    /**
     * Revisa todos los servicios web activos en paralelo.
     */
    public function checkAllServices(): array
    {
        $servicios = ServicioWeb::where('es_activo', true)->get();
        if ($servicios->isEmpty()) {
            return [];
        }

        $inicio = microtime(true);

        $responses = Http::pool(function (Pool $pool) use ($servicios) {
            $requests = [];
            foreach ($servicios as $servicio) {
                $requests[] = $this->request($pool->as((string) $servicio->id))
                    ->send(strtoupper($servicio->metodo ?: 'GET'), $servicio->url);
            }
            return $requests;
        });

        $resultados = [];
        foreach ($servicios as $servicio) {
            $resultados[] = $this->registrar($servicio, $responses[(string) $servicio->id] ?? null, $inicio);
        }

        $this->purgarHistorico();

        return $resultados;
    }

    /**
     * Elimina el histórico más antiguo que los días configurados en Configuración → Retención
     * (la tabla crece ~1 fila por servicio cada 15 s).
     */
    public function purgarHistorico(): int
    {
        $dias = \App\Models\ConfiguracionGeneral::retencionDias()['historico_servicio_web'];
        return HistoricoServicioWeb::where('created_at', '<', now()->subDays($dias))->delete();
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
                // Certificados internos / IPs sin SSL válido
                'verify' => false,
                'allow_redirects' => [
                    'max' => 5,
                    'strict' => false,
                    'referer' => true,
                    'protocols' => ['http', 'https'],
                ],
            ]);
    }

    /**
     * Clasifica la respuesta, guarda el estado y el histórico, y devuelve el resultado.
     *
     * @param Response|\Throwable|null $res
     */
    private function registrar(ServicioWeb $servicio, $res, float $inicio): array
    {
        $httpCode = 0;
        $latency = null;
        $errorMsg = null;

        if ($res instanceof Response) {
            $httpCode = $res->status();
            // Latencia real de ESTA petición (incluye redirecciones), no la del lote completo.
            $transfer = $res->transferStats?->getTransferTime();
            $latency = round(($transfer !== null ? $transfer : (microtime(true) - $inicio)) * 1000, 2);

            if ($httpCode >= 200 && $httpCode < 400) {
                $status = $latency > self::LATENCIA_WARNING_MS ? 'warning' : 'online';
                if ($status === 'warning') {
                    $errorMsg = "Respuesta lenta: {$latency} ms (umbral " . self::LATENCIA_WARNING_MS . " ms).";
                }
            } elseif ($httpCode >= 400 && $httpCode < 500) {
                $status = 'warning';
                $errorMsg = "Respuesta HTTP {$httpCode} - Cliente/Recurso no encontrado o restringido.";
            } else {
                $status = 'offline';
                $errorMsg = "Respuesta HTTP {$httpCode} - Error de servidor.";
            }
        } else {
            $status = 'offline';
            $errorMsg = 'Sin respuesta / Timeout / Error de conexión'
                . ($res instanceof \Throwable ? ': ' . $res->getMessage() : '');
        }

        $datos = [
            'estado' => $status,
            'codigo_http' => $httpCode,
            'tiempo_respuesta_ms' => $latency,
            'detalles_error' => $errorMsg,
        ];

        // Escritura incondicional (no solo columnas "dirty"): si dos sondeos se solapan
        // (bucle de 15 s + botón "Verificar"), la fila nunca queda con valores mezclados.
        $actualizacion = $datos + ['ultimo_chequeo' => now()];
        ServicioWeb::whereKey($servicio->getKey())->update($actualizacion);
        $servicio->forceFill($actualizacion)->syncOriginal();

        HistoricoServicioWeb::create($datos + [
            'servicio_web_id' => $servicio->id,
            'created_at' => now(),
        ]);

        return $datos + [
            'id' => $servicio->id,
            'nombre' => $servicio->nombre,
            'url' => $servicio->url,
        ];
    }
}
