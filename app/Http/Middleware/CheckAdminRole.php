<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || strtolower(auth()->user()->role) !== 'admin') {
            abort(403, 'Acceso denegado. Se requieren permisos de Administrador.');
        }
        
        return $next($request);
    }
}
