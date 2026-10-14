<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso: ->middleware('check.role:Admin,Operador')
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = strtolower((string) auth()->user()?->role);
        $permitidos = array_map('strtolower', $roles);

        if (!auth()->check() || !in_array($role, $permitidos, true)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Su rol no tiene permiso para esta operación.'], 403);
            }
            abort(403, 'Su rol no tiene permiso para esta operación.');
        }

        return $next($request);
    }
}
