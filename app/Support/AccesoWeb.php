<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Dirección de la administración web de un equipo, para los botones "Abrir consola".
 * No todos atienden en 443 (p. ej. un FortiGate en 8443): se prueba qué puerto responde y se recuerda una semana.
 * Solo arma la liga; quien hace clic entra con su propio usuario.
 */
class AccesoWeb
{
    private const PUERTOS_HTTPS = [443, 8443, 4443, 10443];
    private const ESPERA_S = 0.8;
    /** Una semana, más hasta un día al azar: así no vencen todos juntos y la página no tarda en probarlos a la vez. */
    private const CACHE_S = 7 * 86400;

    public static function url(string $ip, string $ruta = '/'): string
    {
        $base = Cache::remember('acceso_web_' . $ip, self::CACHE_S + random_int(0, 86400), function () use ($ip) {
            foreach (self::PUERTOS_HTTPS as $p) {
                if (self::abierto($ip, $p)) {
                    return 'https://' . $ip . ($p === 443 ? '' : ":{$p}");
                }
            }
            // Sin HTTPS: solo HTTP; si nada responde se deja 443 (puede ser que el monitor no llegue pero el usuario sí)
            return self::abierto($ip, 80) ? "http://{$ip}" : "https://{$ip}";
        });
        return $base . $ruta;
    }

    private static function abierto(string $ip, int $puerto): bool
    {
        $s = @fsockopen($ip, $puerto, $errno, $errstr, self::ESPERA_S);
        if ($s) {
            fclose($s);
        }
        return (bool) $s;
    }
}
