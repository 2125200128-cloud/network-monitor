<?php

namespace App\Support;

use App\Models\ConfiguracionGeneral;
use Illuminate\Support\Facades\Cache;

/**
 * Redes que requieren atención especial (Configuración → Notificaciones; por omisión 10.4.0.0/16): sus fallas se
 * marcan como «Red prioritaria» en Alertas, en el dashboard y en Telegram, y se recuerdan mientras sigan activas.
 */
class RedesPrioritarias
{
    public const POR_OMISION = '10.4.0.0/16';

    /** @var array<int, array{0: int, 1: int}>|null [red, máscara] ya interpretadas en esta petición */
    private static ?array $redes = null;

    /** Texto guardado, normalizado: «10.4.0.0/16, 10.13.0.0/16». */
    public static function texto(): string
    {
        return Cache::remember('redes_prioritarias_v1', 60, function () {
            $valor = ConfiguracionGeneral::query()->value('redes_prioritarias');
            return $valor === null ? self::POR_OMISION : trim($valor);
        });
    }

    /** ¿La IP está en alguna red prioritaria? Acepta texto con la IP dentro («Equipo (10.4.2.1)»). */
    public static function contiene(?string $ip): bool
    {
        if (!$ip || !preg_match('/(?<![\d.])(\d{1,3}(?:\.\d{1,3}){3})(?![\d.])/', $ip, $m) || ($n = ip2long($m[1])) === false) {
            return false;
        }
        foreach (self::redes() as [$red, $mascara]) {
            if (($n & $mascara) === $red) {
                return true;
            }
        }
        return false;
    }

    /** Valida y normaliza lo escrito en Configuración. @return array{0: ?string, 1: ?string} [texto normalizado, error] */
    public static function normalizar(?string $texto): array
    {
        $partes = preg_split('/[\s,;]+/', trim((string) $texto), -1, PREG_SPLIT_NO_EMPTY);
        $ok = [];
        foreach ($partes as $p) {
            [$ip, $bits] = str_contains($p, '/') ? explode('/', $p, 2) : [$p, '32'];
            if (ip2long($ip) === false || !ctype_digit($bits) || (int) $bits < 8 || (int) $bits > 32) {
                return [null, "«{$p}» no es una red válida (ejemplo: 10.4.0.0/16)."];
            }
            $mascara = (-1 << (32 - (int) $bits)) & 0xFFFFFFFF;
            $ok[] = long2ip(ip2long($ip) & $mascara) . '/' . (int) $bits;
        }
        return [implode(', ', array_unique($ok)), null];
    }

    /**
     * Patrones LIKE que cubren las redes prioritarias, para filtrar en SQL sin funciones propias de una base:
     * 10.4.0.0/16 → «10.4.%». Una red que no termina en octeto completo se reparte en las de octeto siguiente
     * (10.4.16.0/20 → 10.4.16.% … 10.4.31.%). Sin redes definidas devuelve [].
     * @return list<string>
     */
    public static function patronesLike(): array
    {
        $patrones = [];
        foreach (self::redes() as [$red, $mascara]) {
            $bits = 32 - (int) round(log((~$mascara & 0xFFFFFFFF) + 1, 2));
            $octetos = (int) ceil($bits / 8);
            $paso = 1 << (32 - $octetos * 8);
            for ($n = $red, $fin = $red + ((~$mascara) & 0xFFFFFFFF); $n <= $fin; $n += $paso) {
                $partes = array_slice(explode('.', long2ip($n)), 0, $octetos);
                $patrones[] = implode('.', $partes) . ($octetos < 4 ? '.%' : '');
                if ($octetos === 0) {
                    break;
                }
            }
        }
        return array_values(array_unique($patrones));
    }

    public static function olvidar(): void
    {
        Cache::forget('redes_prioritarias_v1');
        self::$redes = null;
    }

    private static function redes(): array
    {
        if (self::$redes === null) {
            self::$redes = [];
            foreach (preg_split('/[\s,;]+/', self::texto(), -1, PREG_SPLIT_NO_EMPTY) as $p) {
                [$ip, $bits] = str_contains($p, '/') ? explode('/', $p, 2) : [$p, '32'];
                if (($n = ip2long($ip)) !== false && ctype_digit($bits) && (int) $bits >= 8 && (int) $bits <= 32) {
                    $mascara = (-1 << (32 - (int) $bits)) & 0xFFFFFFFF;
                    self::$redes[] = [$n & $mascara, $mascara];
                }
            }
        }
        return self::$redes;
    }
}
