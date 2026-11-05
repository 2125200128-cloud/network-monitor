<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Video en vivo de las cámaras con go2rtc: arma la dirección RTSP de cada marca con su usuario y contraseña
 * (descifrada solo aquí) y se la registra a go2rtc con un nombre neutro (cam{id}_{calidad}). El navegador pide
 * el video por ese nombre: nunca ve la dirección ni las credenciales.
 */
class VideoCamaras
{
    /**
     * Ruta RTSP por marca. "sub" es el flujo secundario (menor resolución, casi siempre H.264: ligero para el mosaico)
     * y "main" el principal (resolución completa, para verla en grande).
     */
    private const RUTAS = [
        'hikvision' => ['sub' => '/Streaming/Channels/102', 'main' => '/Streaming/Channels/101'],
        'dahua' => ['sub' => '/cam/realmonitor?channel=1&subtype=1', 'main' => '/cam/realmonitor?channel=1&subtype=0'],
        'axis' => ['sub' => '/axis-media/media.amp?resolution=640x360', 'main' => '/axis-media/media.amp'],
        'uniview' => ['sub' => '/unicast/c1/s1/live', 'main' => '/unicast/c1/s0/live'],
    ];

    /** Marca para elegir la ruta, por el fabricante registrado de su MAC (Ezviz es de Hikvision). */
    public static function marca(string $fabricante): ?string
    {
        return match (true) {
            (bool) preg_match('/hikvision|ezviz/i', $fabricante) => 'hikvision',
            (bool) preg_match('/dahua|imou/i', $fabricante) => 'dahua',
            (bool) preg_match('/axis/i', $fabricante) => 'axis',
            (bool) preg_match('/uniview/i', $fabricante) => 'uniview',
            default => null,
        };
    }

    /** ¿Tiene lo necesario para pedir su video? (credenciales y una ruta conocida o capturada). */
    public static function lista(object $cam): bool
    {
        return $cam->usuario && $cam->password && ($cam->ruta_rtsp || self::marca($cam->fabricante));
    }

    public static function nombreStream(int $id, string $calidad): string
    {
        return "cam{$id}_{$calidad}";
    }

    /**
     * Registra (o actualiza) el flujo de la cámara en go2rtc y devuelve su nombre.
     * @throws \RuntimeException si falta configurar la cámara o go2rtc no responde
     */
    public function registrar(object $cam, string $calidad): string
    {
        if (!self::lista($cam)) {
            throw new \RuntimeException('La cámara no tiene usuario, contraseña o ruta de video configurados.');
        }
        $ruta = $cam->ruta_rtsp ?: self::RUTAS[self::marca($cam->fabricante)][$calidad];
        $ruta = '/' . ltrim($ruta, '/');
        $src = sprintf('rtsp://%s:%s@%s:554%s', rawurlencode($cam->usuario), rawurlencode(Crypt::decryptString($cam->password)), $cam->ip, $ruta);
        $nombre = self::nombreStream($cam->id, $calidad);

        $api = rtrim(config('noc.go2rtc_api'), '/') . '/api/streams';
        try {
            // go2rtc recibe nombre y fuente en la query string. Después intenta guardarlo en su archivo de configuración;
            // a propósito no puede (archivo de solo lectura en el servidor, sin archivo en desarrollo) para que las
            // credenciales no queden en disco, y por eso responde 400 aunque el flujo sí quedó creado en memoria.
            $put = Http::timeout(5)->withQueryParameters(['name' => $nombre, 'src' => $src])->put($api);
            $existe = Http::timeout(5)->get($api, ['src' => $nombre])->successful();
        } catch (\Throwable) {
            throw new \RuntimeException('El servicio de video (go2rtc) no responde en el servidor.');
        }
        if (!$existe) {
            throw new \RuntimeException("El servicio de video no aceptó la cámara (HTTP {$put->status()}).");
        }
        return $nombre;
    }
}
