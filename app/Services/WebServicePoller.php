<?php

namespace App\Services;

use App\Models\ServicioWeb;
use App\Models\HistoricoServicioWeb;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebServicePoller
{
    /**
     * Revisa el estado de un servicio web específico por su ID.
     */
    public function checkService(ServicioWeb $servicio): array
    {
        $startTime = microtime(true);
        $url = $servicio->url;
        $httpCode = null;
        $errorMsg = null;
        $status = 'offline';

        try {
            // Permitir redirecciones y desactivar verificación SSL estricta para IPs/certificados internos
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) NOC-NetworkMonitor/2.0'
                ])
                ->withOptions([
                    'verify' => false,
                    'allow_redirects' => [
                        'max' => 5,
                        'strict' => false,
                        'referer' => true,
                        'protocols' => ['http', 'https']
                    ]
                ])
                ->send($servicio->metodo ?: 'GET', $url);

            $latency = round((microtime(true) - $startTime) * 1000, 2);
            $httpCode = $response->status();

            if ($httpCode >= 200 && $httpCode < 400) {
                $status = ($latency > 3500) ? 'warning' : 'online';
            } elseif ($httpCode >= 400 && $httpCode < 500) {
                $status = 'warning';
                $errorMsg = "Respuesta HTTP {$httpCode} - Cliente/Recurso no encontrado o restringido.";
            } else {
                $status = 'offline';
                $errorMsg = "Respuesta HTTP {$httpCode} - Error de servidor.";
            }
        } catch (\Throwable $e) {
            $latency = round((microtime(true) - $startTime) * 1000, 2);
            $status = 'offline';
            $errorMsg = "Sin respuesta / Timeout / Error de conexión: " . $e->getMessage();
            $httpCode = 0;
        }

        // Actualizar Servicio Web en BD
        $servicio->update([
            'estado' => $status,
            'codigo_http' => $httpCode,
            'tiempo_respuesta_ms' => $latency,
            'detalles_error' => $errorMsg,
            'ultimo_chequeo' => now(),
        ]);

        // Registrar Histórico
        HistoricoServicioWeb::create([
            'servicio_web_id' => $servicio->id,
            'estado' => $status,
            'codigo_http' => $httpCode,
            'tiempo_respuesta_ms' => $latency,
            'detalles_error' => $errorMsg,
            'created_at' => now(),
        ]);

        return [
            'id' => $servicio->id,
            'nombre' => $servicio->nombre,
            'url' => $servicio->url,
            'estado' => $status,
            'codigo_http' => $httpCode,
            'tiempo_respuesta_ms' => $latency,
            'detalles_error' => $errorMsg,
        ];
    }

    /**
     * Revisa la totalidad de servicios web activos en paralelo (concurrente no bloqueante).
     */
    public function checkAllServices(): array
    {
        $servicios = ServicioWeb::where('es_activo', true)->get();
        if ($servicios->isEmpty()) {
            return [];
        }

        $startTimes = [];
        foreach ($servicios as $s) {
            $startTimes[$s->id] = microtime(true);
        }

        // Ejecutar todas las peticiones HTTP concurrentemente con timeout de 4s
        $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($servicios) {
            $requests = [];
            foreach ($servicios as $servicio) {
                $method = strtolower($servicio->metodo ?: 'get');
                $requests[$servicio->id] = $pool->as((string)$servicio->id)
                    ->timeout(4)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) NOC-NetworkMonitor/2.0'
                    ])
                    ->withOptions([
                        'verify' => false,
                        'allow_redirects' => [
                            'max' => 5,
                            'strict' => false,
                            'referer' => true,
                            'protocols' => ['http', 'https']
                        ]
                    ])
                    ->$method($servicio->url);
            }
            return $requests;
        });

        $resultados = [];
        foreach ($servicios as $servicio) {
            $idStr = (string)$servicio->id;
            $res = $responses[$idStr] ?? null;
            $latency = isset($startTimes[$servicio->id])
                ? round((microtime(true) - $startTimes[$servicio->id]) * 1000, 2)
                : 0.0;

            $status = 'offline';
            $httpCode = 0;
            $errorMsg = null;

            if ($res instanceof \Illuminate\Http\Client\Response) {
                $httpCode = $res->status();
                if ($httpCode >= 200 && $httpCode < 400) {
                    $status = ($latency > 3500) ? 'warning' : 'online';
                } elseif ($httpCode >= 400 && $httpCode < 500) {
                    $status = 'warning';
                    $errorMsg = "Respuesta HTTP {$httpCode} - Cliente/Recurso no encontrado o restringido.";
                } else {
                    $status = 'offline';
                    $errorMsg = "Respuesta HTTP {$httpCode} - Error de servidor.";
                }
            } else {
                $status = 'offline';
                $errorMsg = ($res instanceof \Throwable) ? $res->getMessage() : 'Sin respuesta / Timeout / Error de conexión';
            }

            // Actualizar Servicio Web en BD
            $servicio->update([
                'estado' => $status,
                'codigo_http' => $httpCode,
                'tiempo_respuesta_ms' => $latency,
                'detalles_error' => $errorMsg,
                'ultimo_chequeo' => now(),
            ]);

            // Registrar Histórico
            HistoricoServicioWeb::create([
                'servicio_web_id' => $servicio->id,
                'estado' => $status,
                'codigo_http' => $httpCode,
                'tiempo_respuesta_ms' => $latency,
                'detalles_error' => $errorMsg,
                'created_at' => now(),
            ]);

            $resultados[] = [
                'id' => $servicio->id,
                'nombre' => $servicio->nombre,
                'url' => $servicio->url,
                'estado' => $status,
                'codigo_http' => $httpCode,
                'tiempo_respuesta_ms' => $latency,
                'detalles_error' => $errorMsg,
            ];
        }

        return $resultados;
    }
}
