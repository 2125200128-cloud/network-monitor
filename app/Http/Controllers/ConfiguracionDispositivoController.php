<?php

namespace App\Http\Controllers;

use App\Models\Dispositivo;
use App\Models\ConfiguracionDispositivo;
use App\Models\AuditoriaComando;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;

class ConfiguracionDispositivoController extends Controller
{
    public function index(Request $request)
    {
        $dispositivos = Dispositivo::all();
        $selectedDispositivoId = $request->get('dispositivo_id', $dispositivos->first()->id ?? null);
        
        $historial = collect();
        $ultimaConfig = null;

        if ($selectedDispositivoId) {
            $historial = ConfiguracionDispositivo::with('user')
                ->where('dispositivo_id', $selectedDispositivoId)
                ->orderBy('created_at', 'desc')
                ->get();
            
            $ultimaConfig = $historial->first();
        }

        return view('configuraciones.index', compact('dispositivos', 'selectedDispositivoId', 'historial', 'ultimaConfig'));
    }

    public function respaldar(Request $request)
    {
        $request->validate([
            'dispositivo_id' => 'required|exists:dispositivos,id',
            'tipo' => 'nullable|in:running,startup'
        ]);

        $dispositivo = Dispositivo::findOrFail($request->dispositivo_id);
        $tipo = $request->tipo ?? 'running';
        $comandoSsh = ($tipo === 'startup') ? 'show startup-config' : 'show running-config';
        
        $password = '';
        if ($dispositivo->ssh_password_encrypted) {
            $password = Crypt::decryptString($dispositivo->ssh_password_encrypted);
        } else {
            $password = $dispositivo->comunidad_snmp ?: 'admin';
        }

        $process = new Process([
            'py', 
            base_path('worker/ssh_executor.py'),
            $dispositivo->ip,
            $dispositivo->ssh_port,
            $dispositivo->ssh_user,
            $password,
            $comandoSsh
        ]);

        $process->setTimeout(60);

        try {
            $process->mustRun();
            $salida = $process->getOutput();

            // Guardar auditoría
            AuditoriaComando::create([
                'user_id' => Auth::id(),
                'dispositivo_id' => $dispositivo->id,
                'comando_solicitado' => 'NCM Backup: ' . $comandoSsh,
                'ip_origen' => $request->ip(),
                'estado_ejecucion' => 'autorizado',
                'salida_terminal' => 'Backup exitoso'
            ]);

            // Validar Hash
            $hash = hash('sha256', $salida);
            $ultimoRespaldo = ConfiguracionDispositivo::where('dispositivo_id', $dispositivo->id)
                ->where('tipo', $tipo)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($ultimoRespaldo && $ultimoRespaldo->checksum_sha256 === $hash) {
                return back()->with('success', 'El respaldo se completó, pero no hubo cambios en la configuración (Hash idéntico).');
            }

            ConfiguracionDispositivo::create([
                'dispositivo_id' => $dispositivo->id,
                'user_id' => Auth::id(),
                'tipo' => $tipo,
                'contenido' => $salida,
                'checksum_sha256' => $hash
            ]);

            return back()->with('success', 'Respaldo nuevo guardado exitosamente.');

        } catch (ProcessFailedException $exception) {
            
            AuditoriaComando::create([
                'user_id' => Auth::id(),
                'dispositivo_id' => $dispositivo->id,
                'comando_solicitado' => 'NCM Backup: ' . $comandoSsh,
                'ip_origen' => $request->ip(),
                'estado_ejecucion' => 'fallo_conexion',
                'salida_terminal' => $exception->getMessage()
            ]);

            return back()->with('error', 'Fallo al conectar con el dispositivo o recuperar la configuración.');
        }
    }

    public function descargar($id)
    {
        $config = ConfiguracionDispositivo::findOrFail($id);
        $nombreBase = $config->dispositivo->nombre ?? 'dispositivo';
        $fecha = $config->created_at->format('Ymd_His');
        
        $filename = "{$nombreBase}_{$config->tipo}_{$fecha}.cfg";

        return response($config->contenido)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    public function descargarPdf($id, \App\Services\PdfReportService $pdfService)
    {
        $config = ConfiguracionDispositivo::with(['dispositivo', 'user'])->findOrFail($id);
        return $pdfService->descargarReporteConfiguracion($config);
    }
}
