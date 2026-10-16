<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Pantallas NOC (TV): mantiene la sesión abierta en los navegadores que muestran el monitoreo.
 */
class PantallaController extends Controller
{
    /**
     * Latido del modo TV. La primera vez deja la sesión "recordada" (cookie persistente de Laravel) para el
     * mismo usuario ya autenticado: si la sesión vence (PC suspendida, corte de red), el siguiente acceso la
     * restaura sin pedir contraseña. Cada latido además renueva la sesión.
     */
    public function mantener(Request $request)
    {
        $guard = Auth::guard('web');
        if (!$guard->viaRemember() && !$request->cookies->has($guard->getRecallerName())) {
            $guard->login($guard->user(), true);
        }

        return response()->json(['ok' => true, 'usuario' => $guard->user()->name, 'hora' => now()->format('H:i:s')]);
    }
}
