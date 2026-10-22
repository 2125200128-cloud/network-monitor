<?php

namespace App\Services;

use App\Models\ServicioWeb;
use Illuminate\Support\Facades\Cache;

/**
 * Lee el certificado que presenta cada servicio HTTPS y guarda los días que faltan para su expiración.
 * Se ejecuta desde noc:sync; cada servicio se revisa como máximo cada INTERVALO_HORAS.
 *
 * ssl_valido indica solo vigencia por fechas (notBefore <= hoy <= notAfter). Aparte, ssl_problema dice si el
 * certificado no corresponde al dominio o si su cadena no es de confianza; esto último es normal en
 * servicios internos con CA privada, por eso solo se informa y no impide la revisión del sitio.
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
                ? ['ssl_valido' => null, 'ssl_dias_expiracion' => null, 'ssl_problema' => null]
                : [
                    'ssl_valido' => $cert['desde'] <= time() && time() <= $cert['hasta'],
                    'ssl_dias_expiracion' => (int) floor(($cert['hasta'] - time()) / 86400),
                    'ssl_problema' => $cert['problema'],
                ]);
            Cache::put($this->claveCache($servicio), true, now()->addHours(self::INTERVALO_HORAS));
        }

        return $pendientes->count();
    }

    /** Prefijos de ssl_problema: el de dominio sí genera alarma; el de cadena solo se informa. */
    public const PROBLEMA_DOMINIO = 'No corresponde al dominio';
    public const PROBLEMA_CADENA = 'Cadena no reconocida';

    /** @return array{desde:int, hasta:int, problema:?string}|null */
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

        if (!$info || !isset($info['validTo_time_t'])) {
            return null;
        }

        return [
            'desde' => (int) $info['validFrom_time_t'],
            'hasta' => (int) $info['validTo_time_t'],
            'problema' => $this->problemaDominio($host, $info) ?? $this->problemaCadena($host, $port),
        ];
    }

    /** El certificado debe nombrar al host de la URL (SAN o, si no hay SAN, el CN); acepta comodines "*.dominio". */
    private function problemaDominio(string $host, array $info): ?string
    {
        $host = strtolower($host);
        $esIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $nombres = [];
        $ips = [];
        foreach (array_map('trim', explode(',', (string) ($info['extensions']['subjectAltName'] ?? ''))) as $entrada) {
            if (str_starts_with($entrada, 'DNS:')) {
                $nombres[] = strtolower(substr($entrada, 4));
            } elseif (str_starts_with($entrada, 'IP Address:')) {
                $ips[] = strtolower(substr($entrada, 11));
            }
        }
        if (!$nombres && !$ips && !empty($info['subject']['CN'])) {
            $nombres[] = strtolower((string) $info['subject']['CN']);
        }

        $coincide = $esIp
            ? in_array($host, $ips, true)
            : collect($nombres)->contains(fn ($n) => $n === $host
                || (str_starts_with($n, '*.') && substr_count($host, '.') === substr_count($n, '.')
                    && str_ends_with($host, substr($n, 1))));
        if ($coincide) {
            return null;
        }

        $emitido = $nombres[0] ?? ($ips[0] ?? 'otro nombre');
        return self::PROBLEMA_DOMINIO . ": el certificado es para {$emitido}"
            . (count($nombres) + count($ips) > 1 ? ' y otros' : '') . ($esIp ? ' (se entra por IP)' : '') . '.';
    }

    /** Segunda conexión validando la cadena con el almacén de CA de PHP (openssl.cafile). */
    private function problemaCadena(string $host, int $port): ?string
    {
        $ctx = stream_context_create(['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => false,   // el dominio ya se revisó aparte
            'SNI_enabled' => true,
            'peer_name' => $host,
        ]]);
        // PHP emite dos avisos (el motivo de OpenSSL y luego "Unable to connect"): se guardan todos
        $avisos = [];
        set_error_handler(function ($nivel, $mensaje) use (&$avisos) {
            $avisos[] = $mensaje;
            return true;
        });
        try {
            $socket = stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, self::TIMEOUT_S, STREAM_CLIENT_CONNECT, $ctx);
        } finally {
            restore_error_handler();
        }
        if ($socket) {
            fclose($socket);
            return null;
        }
        // Solo se informa si el fallo fue por el certificado (no por un corte de red en ese momento)
        $error = implode(' ', $avisos) . ' ' . $errstr;
        return str_contains($error, 'certificate verify failed')
            ? self::PROBLEMA_CADENA . ': emitido por una CA que este equipo no reconoce (CA interna o falta el certificado intermedio).'
            : null;
    }

    private function claveCache(ServicioWeb $servicio): string
    {
        return 'ssl_revisado_' . $servicio->id . '_' . md5($servicio->url);
    }
}
