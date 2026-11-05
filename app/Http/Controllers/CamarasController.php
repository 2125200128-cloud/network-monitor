<?php

namespace App\Http\Controllers;

use App\Services\VideoCamaras;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Página Cámaras: las cámaras de seguridad encontradas en las tablas ARP (camaras:buscar), su acceso y el video
 * en vivo (go2rtc). Ver video y configurar el acceso: Administrador y Operador.
 */
class CamarasController extends Controller
{
    private const ROLES_VIDEO = ['admin', 'operador'];

    public function index()
    {
        $camaras = DB::table('camaras_detectadas')->orderBy('visto_en')->orderByRaw('inet(ip)')
            ->get(['id', 'mac', 'ip', 'fabricante', 'visto_en', 'visto_en_ip', 'rtsp', 'http', 'https', 'primera_vez', 'ultima_vez',
                'nombre', 'usuario', 'ruta_rtsp', DB::raw('password IS NOT NULL AS tiene_password')]);

        return view('camaras.index', [
            'camaras' => $camaras,
            'ultimaBusqueda' => $camaras->max('ultima_vez'),
            'puedeVer' => self::puedeVer(),
            'go2rtc' => rtrim(config('noc.go2rtc_publico'), '/'),
        ]);
    }

    /** Botón "Buscar cámaras": la misma búsqueda que corre cada noche (camaras:buscar), en el momento. */
    public function buscar()
    {
        set_time_limit(300);
        $codigo = Artisan::call('camaras:buscar');
        return response()->json(['message' => trim(Artisan::output())], $codigo === 0 ? 200 : 500);
    }

    /** Nombre, usuario, contraseña (cifrada; vacía = se conserva la guardada) y ruta RTSP opcional de una cámara. */
    public function guardarAcceso(Request $request, int $id)
    {
        $datos = $request->validate([
            'nombre' => 'nullable|string|max:120',
            'usuario' => 'nullable|string|max:120',
            'password' => 'nullable|string|max:200',
            'borrar_password' => 'nullable|boolean',
            'ruta_rtsp' => ['nullable', 'string', 'max:250', 'regex:/^\/\S*$/'],
        ], ['ruta_rtsp.regex' => 'La ruta RTSP empieza con / y no lleva espacios (p. ej. /Streaming/Channels/102).']);
        abort_unless(DB::table('camaras_detectadas')->where('id', $id)->exists(), 404);

        $cambios = [
            'nombre' => trim((string) ($datos['nombre'] ?? '')) ?: null,
            'usuario' => trim((string) ($datos['usuario'] ?? '')) ?: null,
            'ruta_rtsp' => trim((string) ($datos['ruta_rtsp'] ?? '')) ?: null,
        ];
        if ($request->boolean('borrar_password')) {
            $cambios['password'] = null;
        } elseif (($datos['password'] ?? '') !== '') {
            $cambios['password'] = Crypt::encryptString($datos['password']);
        }
        DB::table('camaras_detectadas')->where('id', $id)->update($cambios);
        Log::info('Acceso de cámara actualizado', ['camara' => $id, 'usuario' => auth()->user()?->email]);

        return response()->json(['ok' => true]);
    }

    /** Registra la cámara en go2rtc y devuelve el nombre del flujo que pide el reproductor. */
    public function ver(Request $request, int $id, VideoCamaras $video)
    {
        $cam = DB::table('camaras_detectadas')->find($id);
        abort_unless($cam, 404);
        $calidad = $request->query('calidad') === 'main' ? 'main' : 'sub';
        try {
            $stream = $video->registrar($cam, $calidad);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        Log::info('Video de cámara abierto', ['camara' => "{$cam->id} {$cam->ip}", 'calidad' => $calidad, 'usuario' => auth()->user()?->email]);

        return response()->json(['stream' => $stream]);
    }

    /**
     * Para Nginx (auth_request de /go2rtc/): 204 si quien pide el video tiene sesión y rol para verlo.
     * Va fuera del middleware auth: un usuario sin sesión debe recibir 401, no una redirección al login.
     */
    public function autorizar()
    {
        return response()->noContent(self::puedeVer() ? 204 : 401);
    }

    private static function puedeVer(): bool
    {
        return in_array(strtolower((string) auth()->user()?->role), self::ROLES_VIDEO, true);
    }
}
