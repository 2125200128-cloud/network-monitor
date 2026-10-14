<?php

namespace App\Http\Controllers;

use App\Models\Dispositivo;
use App\Models\NotaDispositivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Notas por dispositivo. El contenido se guarda cifrado; las notas marcadas como secreto
 * (contraseñas, PIN, etc.) nunca se envían en el listado: solo Admin/Operador las revelan
 * una por una y cada revelación queda registrada en el log.
 */
class NotaDispositivoController extends Controller
{
    private function puedeEditar(): bool
    {
        return in_array(auth()->user()?->role, ['Admin', 'Operador'], true);
    }

    private function serializar(NotaDispositivo $nota): array
    {
        return [
            'id' => $nota->id,
            'titulo' => $nota->titulo,
            'contenido' => $nota->es_secreto ? null : $nota->contenido,
            'es_secreto' => $nota->es_secreto,
            'autor' => $nota->autor?->name,
            'actualizado' => $nota->updated_at?->format('d/m/Y H:i'),
        ];
    }

    public function index(int $id)
    {
        $dispositivo = Dispositivo::findOrFail($id);
        $notas = $dispositivo->notas()->with('autor:id,name')->latest('updated_at')->get();

        return response()->json([
            'puede_editar' => $this->puedeEditar(),
            'notas' => $notas->map(fn ($n) => $this->serializar($n))->values(),
        ]);
    }

    public function revelar(int $id, int $nota)
    {
        $n = NotaDispositivo::where('dispositivo_id', $id)->findOrFail($nota);

        if ($n->es_secreto) {
            Log::notice('Nota secreta revelada', [
                'usuario' => auth()->user()?->email,
                'dispositivo_id' => $id,
                'nota_id' => $n->id,
                'titulo' => $n->titulo,
                'ip' => request()->ip(),
            ]);
        }

        return response()->json(['contenido' => $n->contenido]);
    }

    public function store(Request $request, int $id)
    {
        Dispositivo::findOrFail($id);
        $data = $this->validar($request);

        $nota = NotaDispositivo::create($data + ['dispositivo_id' => $id, 'user_id' => auth()->id()]);
        TopologiaController::olvidarCacheGrafo();

        return response()->json(['success' => true, 'nota' => $this->serializar($nota->load('autor:id,name'))]);
    }

    public function update(Request $request, int $id, int $nota)
    {
        $n = NotaDispositivo::where('dispositivo_id', $id)->findOrFail($nota);
        $data = $this->validar($request, $n->es_secreto);

        // En una nota secreta, contenido vacío significa "no cambiar"
        if (($data['contenido'] ?? '') === '' && $n->es_secreto) {
            unset($data['contenido']);
        }
        $n->update($data);

        return response()->json(['success' => true, 'nota' => $this->serializar($n->fresh('autor'))]);
    }

    public function destroy(int $id, int $nota)
    {
        NotaDispositivo::where('dispositivo_id', $id)->findOrFail($nota)->delete();
        TopologiaController::olvidarCacheGrafo();

        return response()->json(['success' => true]);
    }

    private function validar(Request $request, bool $contenidoOpcional = false): array
    {
        return $request->validate([
            'titulo' => 'required|string|max:120',
            'contenido' => ($contenidoOpcional ? 'nullable' : 'required') . '|string|max:5000',
            'es_secreto' => 'boolean',
        ]);
    }
}
