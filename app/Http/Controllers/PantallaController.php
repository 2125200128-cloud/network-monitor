<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Latido de sesión: lo llaman todas las páginas (y el modo TV) para que la sesión nunca se cierre.
 */
class PantallaController extends Controller
{
    /**
     * La primera vez deja la sesión "recordada" (cookie persistente de Laravel, 400 días) para el mismo usuario
     * ya autenticado: si la sesión se pierde (servidor reiniciado, sesiones borradas), el siguiente acceso la
     * restaura sin pedir contraseña. Cada latido además renueva la sesión. Devuelve el token CSRF vigente para que
     * la página se recargue si cambió (una página con el token viejo recibiría 419 al guardar).
     */
    public function mantener(Request $request)
    {
        $guard = Auth::guard('web');
        if (!$guard->viaRemember() && !$request->cookies->has($guard->getRecallerName())) {
            $guard->login($guard->user(), true);
        }

        return response()->json([
            'ok' => true,
            'usuario' => $guard->user()->name,
            'hora' => now()->format('H:i:s'),
            'csrf' => csrf_token(),
        ]);
    }
}
