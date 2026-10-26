<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Comprime con gzip el HTML y el JSON. El dashboard pesa ~3 MB sin comprimir (sangrías y clases repetidas) y
 * ~110 KB comprimido; el refresco automático lo vuelve a pedir cada pocos segundos.
 * `php artisan serve` (Windows) no comprime nada; detrás de nginx la respuesta ya llega comprimida y nginx
 * no la vuelve a comprimir.
 */
class ComprimirRespuesta
{
    private const MINIMO_BYTES = 2048;
    private const NIVEL = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        if ($respuesta instanceof StreamedResponse || $respuesta instanceof BinaryFileResponse
            || $respuesta->headers->has('Content-Encoding')
            || !str_contains((string) $request->header('Accept-Encoding'), 'gzip')
            || !preg_match('#^(text/html|application/json)#', (string) $respuesta->headers->get('Content-Type', 'text/html'))) {
            return $respuesta;
        }

        $contenido = $respuesta->getContent();
        if ($contenido === false || strlen($contenido) < self::MINIMO_BYTES) {
            return $respuesta;
        }

        $comprimido = gzencode($contenido, self::NIVEL);
        if ($comprimido === false) {
            return $respuesta;
        }
        $respuesta->setContent($comprimido);
        $respuesta->headers->set('Content-Encoding', 'gzip');
        $respuesta->headers->set('Content-Length', (string) strlen($comprimido));
        $respuesta->headers->set('Vary', trim($respuesta->headers->get('Vary', '') . ', Accept-Encoding', ', '));

        return $respuesta;
    }
}
