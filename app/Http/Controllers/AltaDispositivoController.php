<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionGeneral;
use App\Models\Dispositivo;
use App\Models\EnlaceRed;
use App\Models\InterfazRed;
use App\Services\TipoDispositivoResolver;
use App\Support\Python;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\Process\Process;

/**
 * Alta de un equipo por IP (página Equipos).
 *  1. descubrir: el crawler de la topología consulta solo ese equipo (profundidad 0) por SNMP: nombre, modelo,
 *     interfaces y vecinos CDP/LLDP; lo registra con sus enlaces.
 *  2. Si no responde SNMP: alta manual (nombre y tipo) y se monitorea por ping.
 *  3. En los dos casos se pueden agregar enlaces a mano a equipos ya registrados.
 */
class AltaDispositivoController extends Controller
{
    private const VELOCIDADES = [100, 1000, 10000, 25000, 40000, 100000];

    public function descubrir(Request $request)
    {
        $datos = $request->validate([
            'ip' => 'required|ipv4',
            'comunidad' => 'nullable|string|max:64',
        ], ['ip.ipv4' => 'No es una dirección IPv4 válida.']);

        if ($existente = Dispositivo::where('ip', $datos['ip'])->first()) {
            return response()->json(['ok' => false, 'existente' => $this->resumen($existente),
                'mensaje' => "Esa IP ya está registrada como {$existente->nombre}."], 409);
        }

        $comunidad = trim((string) ($datos['comunidad'] ?? ''))
            ?: (ConfiguracionGeneral::query()->value('comunidad_snmp_default') ?: config('noc.snmp_community'));
        // Mismo formato de job que DiscoveryController, en la misma carpeta
        $dir = storage_path('app/discovery');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $archivo = $dir . DIRECTORY_SEPARATOR . now()->format('Ymd_His') . '_' . Str::lower(Str::random(6)) . '.json';

        $proceso = new Process([Python::binario(), base_path('worker/network_discovery.py'),
            '--method', 'cdp_lldp', '--seed', $datos['ip'], '--community', $comunidad,
            '--max-depth', '0', '--timeout', '40', '--job-file', $archivo], base_path(), Python::entorno());
        $proceso->setTimeout(60);
        try {
            $proceso->run();
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => 'El descubrimiento tardó demasiado: ' . $e->getMessage()]);
        }
        $estado = json_decode((string) @file_get_contents($archivo), true) ?: [];
        @unlink($archivo);

        $semilla = collect($estado['devices'] ?? [])->firstWhere('role', 'semilla');
        if (!$semilla) {
            // Sin SNMP: el formulario pasa al alta manual con el motivo
            return response()->json(['ok' => false, 'sin_snmp' => true,
                'mensaje' => $estado['error'] ?? Str::limit(trim($proceso->getErrorOutput()) ?: 'El equipo no respondió SNMP.', 200)]);
        }

        $dispositivo = Dispositivo::findOrFail($semilla['id']);
        TopologiaController::olvidarCacheGrafo();
        $vecinosNuevos = collect($estado['devices'])->where('role', 'vecino')->where('new', true)
            ->map(fn ($d) => "{$d['name']} ({$d['ip']})")->values()->all();
        Log::info('Dispositivo agregado por descubrimiento', ['dispositivo' => "{$dispositivo->id} {$dispositivo->nombre} ({$dispositivo->ip})",
            'vecinos_nuevos' => count($vecinosNuevos), 'usuario' => auth()->user()?->email]);

        return response()->json(['ok' => true, 'dispositivo' => $this->resumen($dispositivo), 'modelo' => $semilla['model'] ?? null,
            'vecinos_nuevos' => $vecinosNuevos, 'enlaces' => $this->listaEnlaces($dispositivo->id)]);
    }

    /** Alta manual (no respondió SNMP): queda por ping; el sondeo le pone el estado real en su siguiente vuelta. */
    public function guardar(Request $request)
    {
        $datos = $request->validate([
            'ip' => 'required|ipv4|unique:dispositivos,ip',
            'nombre' => 'required|string|max:255',
            'tipo' => ['required', Rule::in(TipoDispositivoResolver::tipos())],
            'ubicacion' => 'nullable|string|max:255',
            'comunidad' => 'nullable|string|max:64',
        ], ['ip.unique' => 'Esa IP ya está registrada.']);

        $dispositivo = Dispositivo::create([
            'nombre' => trim($datos['nombre']),
            'ip' => $datos['ip'],
            'ubicacion' => trim((string) ($datos['ubicacion'] ?? '')) ?: 'Alta manual',
            'estado' => 'offline',
            'tipo_manual' => $datos['tipo'],
            'comunidad_snmp_manual' => trim((string) ($datos['comunidad'] ?? '')) ?: null,
            'ssh_port' => 22,
        ]);
        TopologiaController::olvidarCacheGrafo();
        Cache::forget('dashboard_ids_firewalls_v1');
        Log::info('Dispositivo agregado a mano', ['dispositivo' => "{$dispositivo->id} {$dispositivo->nombre} ({$dispositivo->ip})",
            'usuario' => auth()->user()?->email]);

        return response()->json(['ok' => true, 'dispositivo' => $this->resumen($dispositivo), 'enlaces' => []]);
    }

    public function enlaces(int $id)
    {
        Dispositivo::findOrFail($id);
        return response()->json(['enlaces' => $this->listaEnlaces($id)]);
    }

    public function agregarEnlace(Request $request, int $id)
    {
        $dispositivo = Dispositivo::findOrFail($id);
        $datos = $request->validate([
            'vecino_id' => ['required', 'integer', 'exists:dispositivos,id', Rule::notIn([$id])],
            'puerto_local' => 'nullable|string|max:64',
            'puerto_vecino' => 'nullable|string|max:64',
            'velocidad' => ['required', 'integer', Rule::in(self::VELOCIDADES)],
        ], ['vecino_id.not_in' => 'Un equipo no se puede enlazar consigo mismo.', 'vecino_id.exists' => 'Elige un equipo de la lista.']);

        $vecino = Dispositivo::findOrFail($datos['vecino_id']);
        $local = $this->interfaz($dispositivo, $datos['puerto_local'] ?? null, $datos['velocidad']);
        $remota = $this->interfaz($vecino, $datos['puerto_vecino'] ?? null, $datos['velocidad']);

        $repetido = EnlaceRed::where(fn ($q) => $q->where(['origen_dispositivo_id' => $id, 'destino_dispositivo_id' => $vecino->id,
                'origen_interfaz_id' => $local, 'destino_interfaz_id' => $remota]))
            ->orWhere(fn ($q) => $q->where(['origen_dispositivo_id' => $vecino->id, 'destino_dispositivo_id' => $id,
                'origen_interfaz_id' => $remota, 'destino_interfaz_id' => $local]))->exists();
        if ($repetido) {
            return response()->json(['message' => 'Ese enlace ya existe.'], 422);
        }

        EnlaceRed::create([
            'origen_dispositivo_id' => $id, 'origen_interfaz_id' => $local,
            'destino_dispositivo_id' => $vecino->id, 'destino_interfaz_id' => $remota,
            'switch_origen' => $dispositivo->nombre, 'switch_destino' => $vecino->nombre,
            'tipo_medio' => $datos['velocidad'] >= 10000 ? 'fibra_10g' : 'cobre_1g',
            'estado' => 'up', 'velocidad_mbps' => $datos['velocidad'],
            'trafico_in_mbps' => 0, 'trafico_out_mbps' => 0, 'manual' => true,
        ]);
        TopologiaController::olvidarCacheGrafo();

        return response()->json(['enlaces' => $this->listaEnlaces($id)]);
    }

    /** Solo se quitan los enlaces puestos a mano; los descubiertos los mantiene la red. */
    public function quitarEnlace(int $id, int $enlace)
    {
        EnlaceRed::where('manual', true)->whereKey($enlace)
            ->where(fn ($q) => $q->where('origen_dispositivo_id', $id)->orWhere('destino_dispositivo_id', $id))
            ->firstOrFail()->delete();
        TopologiaController::olvidarCacheGrafo();

        return response()->json(['enlaces' => $this->listaEnlaces($id)]);
    }

    private function resumen(Dispositivo $d): array
    {
        return ['id' => $d->id, 'nombre' => $d->nombre, 'ip' => $d->ip, 'url' => route('dispositivos.show', $d->id)];
    }

    /** Enlaces del equipo vistos desde él: puerto propio, vecino y puerto del vecino. */
    private function listaEnlaces(int $id): array
    {
        return EnlaceRed::with(['dispositivoOrigen:id,nombre,ip', 'dispositivoDestino:id,nombre,ip', 'interfazOrigen:id,nombre', 'interfazDestino:id,nombre'])
            ->where('origen_dispositivo_id', $id)->orWhere('destino_dispositivo_id', $id)->orderBy('id')->get()
            ->map(function ($e) use ($id) {
                $desdeAqui = (int) $e->origen_dispositivo_id === $id;
                $vecino = $desdeAqui ? $e->dispositivoDestino : $e->dispositivoOrigen;
                return [
                    'id' => $e->id,
                    'puerto' => ($desdeAqui ? $e->interfazOrigen : $e->interfazDestino)?->nombre,
                    'vecino' => $vecino?->nombre, 'vecino_ip' => $vecino?->ip,
                    'puerto_vecino' => ($desdeAqui ? $e->interfazDestino : $e->interfazOrigen)?->nombre,
                    'velocidad' => $e->velocidad_mbps, 'manual' => (bool) $e->manual,
                ];
            })->all();
    }

    /** Interfaz por nombre (con las abreviaturas de Cisco expandidas, como el crawler); se crea si no existe. */
    private function interfaz(Dispositivo $d, ?string $nombre, int $velocidad): ?int
    {
        $nombre = self::normalizarPuerto($nombre);
        if ($nombre === '') {
            return null;
        }
        $existente = InterfazRed::where('dispositivo_id', $d->id)->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->value('id');
        if ($existente) {
            return $existente;
        }
        return InterfazRed::create([
            'dispositivo_id' => $d->id,
            'if_index' => (InterfazRed::where('dispositivo_id', $d->id)->max('if_index') ?? 0) + 1,
            'nombre' => $nombre,
            'velocidad_mbps' => $velocidad,
            'port_type' => $velocidad >= 10000 ? 'sfp_fiber' : 'copper_rj45',
            'is_poe' => false,
        ])->id;
    }

    /** Gi1/0/1 → GigabitEthernet1/0/1 (misma tabla que normalize_iface de worker/network_discovery.py). */
    public static function normalizarPuerto(?string $nombre): string
    {
        $s = str_replace(' ', '', trim((string) $nombre));
        $abreviaturas = [
            '/^(hundredgige?|hu(?=\d))/i' => 'HundredGigE',
            '/^(fortygigabitethernet|fo(?=\d))/i' => 'FortyGigabitEthernet',
            '/^(twentyfivegige?|twe(?=\d))/i' => 'TwentyFiveGigE',
            '/^(tengigabitethernet|te(?=\d))/i' => 'TenGigabitEthernet',
            '/^(gigabitethernet|gi(?=\d))/i' => 'GigabitEthernet',
            '/^(fastethernet|fa(?=\d))/i' => 'FastEthernet',
            '/^(ethernet|eth?(?=\d))/i' => 'Ethernet',
            '/^(port-channel|po(?=\d))/i' => 'Port-channel',
        ];
        foreach ($abreviaturas as $patron => $completo) {
            if (preg_match($patron, $s, $m)) {
                return $completo . substr($s, strlen($m[0]));
            }
        }
        return $s;
    }
}
