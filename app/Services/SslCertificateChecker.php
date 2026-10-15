<?php

namespace App\Services;

use App\Models\ServicioWeb;
use Illuminate\Support\Facades\Cache;

/**
 * Lee el certificado que presenta cada servicio HTTPS y guarda los días que faltan para su expiración.
 * Se ejecuta desde noc:sync; cada servicio se revisa como máximo cada INTERVALO_HORAS.
 *
 * ssl_valido indica solo vigencia por fechas (notBefore <= hoy <= notAfter): la cadena de confianza
 * no se valida porque varios servicios internos usan certificados de CA privada.
 */
class SslCertificateChecker
{
    public const INTERVALO_HORAS = 6;
    private const MAX_POR_CORRIDA = 5;
    private const TIMEOUT_S = 5;

    public function revisarPendientes(): int
    {
        if (!extension_loaded('openssl')) {
            return 0;
        }

        $pendientes = ServicioWeb::where('es_activo', true)
            ->where('url', 'like', 'https://%')
            ->get()
            ->reject(fn ($s) => Cache::has($this->claveCache($s)))
            ->take(self::MAX_POR_CORRIDA);

        foreach ($pendientes as $servicio) {
            $cert = $this->leerCertificado($servicio->url);
            ServicioWeb::whereKey($servicio->id)->update($cert === null
                ? ['ssl_valido' => null, 'ssl_dias_expiracion' => null]
                : [
                    'ssl_valido' => $cert['desde'] <= time() && time() <= $cert['hasta'],
                    'ssl_dias_expiracion' => (int) floor(($cert['hasta'] - time()) / 86400),
                ]);
            Cache::put($this->claveCache($servicio), true, now()->addHours(self::INTERVALO_HORAS));
        }

        return $pendientes->count();
    }

    /** @return array{desde:int, hasta:int}|null */
    private function leerCertificado(string $url): ?array
    {
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT) ?: 443;
        if (!$host) {
            return null;
        }

        $ctx = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ]]);
        $socket = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, self::TIMEOUT_S, STREAM_CLIENT_CONNECT, $ctx);
        if (!$socket) {
            return null;
        }
        $params = stream_context_get_params($socket);
        fclose($socket);

        $info = isset($params['options']['ssl']['peer_certificate'])
            ? openssl_x509_parse($params['options']['ssl']['peer_certificate'])
            : false;

        return $info && isset($info['validTo_time_t'])
            ? ['desde' => (int) $info['validFrom_time_t'], 'hasta' => (int) $info['validTo_time_t']]
            : null;
    }

    private function claveCache(ServicioWeb $servicio): string
    {
        return 'ssl_revisado_' . $servicio->id . '_' . md5($servicio->url);
    }
}
