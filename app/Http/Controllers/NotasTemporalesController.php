<?php

namespace App\Http\Controllers;

use App\Models\NotaTemporal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Notas: portapapeles temporal del NOC. Se pega texto (comandos, IPs, configuraciones) en una PC y se copia en otra
 * con un botón. Cada nota caduca y se borra sola; las privadas solo las ve quien las creó.
 */
class NotasTemporalesController extends Controller
{
    public function index(Request $request)
    {
        // Lo caducado se borra al entrar (no hace falta un temporizador aparte)
        NotaTemporal::where('expira_en', '<=', now())->delete();

        $esAdmin = self::esAdmin($request);
        return view('notas.index', [
            // Las fijas (comandos de uso frecuente) arriba; luego lo más reciente
            'notas' => NotaTemporal::with('autor:id,name,usuario')->visiblesPara($request->user())
                ->orderByRaw('expira_en IS NULL DESC')->latest()->get(),
            'duraciones' => $esAdmin ? NotaTemporal::DURACIONES : array_diff_key(NotaTemporal::DURACIONES, ['fija' => 1]),
            'esAdmin' => $esAdmin,
        ]);
    }

    private static function esAdmin(Request $request): bool
    {
        return strtolower((string) $request->user()?->role) === 'admin';
    }

    public function guardar(Request $request)
    {
        $datos = $request->validate([
            'titulo' => 'nullable|string|max:120',
            'contenido' => 'required|string|max:20000',
            'duracion' => ['required', Rule::in(array_keys(self::esAdmin($request) ? NotaTemporal::DURACIONES : array_diff_key(NotaTemporal::DURACIONES, ['fija' => 1])))],
            'privada' => 'boolean',
        ], ['contenido.required' => 'Escribe o pega el texto de la nota.', 'contenido.max' => 'La nota es muy larga (máximo 20 000 caracteres).',
            'duracion.in' => 'Solo un Admin puede crear notas fijas.']);

        $minutos = NotaTemporal::DURACIONES[$datos['duracion']][1];
        $nota = NotaTemporal::create([
            'user_id' => $request->user()->id,
            'titulo' => trim((string) ($datos['titulo'] ?? '')) ?: null,
            'contenido' => rtrim($datos['contenido']),
            'privada' => $request->boolean('privada'),
            'expira_en' => $minutos === null ? null : now()->addMinutes($minutos),
        ]);
        Log::info('Nota temporal creada', ['id' => $nota->id, 'privada' => $nota->privada, 'fija' => $nota->esFija(), 'usuario' => $request->user()->usuario]);

        return redirect()->route('notas.index')->with('success', $nota->esFija()
            ? 'Nota fija guardada: no se borra sola.'
            : 'Nota guardada: se borra sola ' . $nota->expira_en->locale('es')->diffForHumans() . '.');
    }

    /** La borra quien la creó o un Admin; las fijas, solo un Admin. */
    public function borrar(Request $request, NotaTemporal $nota)
    {
        $u = $request->user();
        abort_unless(self::esAdmin($request) || ($nota->user_id === $u->id && !$nota->esFija()), 403);
        abort_if($nota->privada && $nota->user_id !== $u->id, 404);
        $nota->delete();

        return redirect()->route('notas.index')->with('success', 'Nota borrada.');
    }
}
