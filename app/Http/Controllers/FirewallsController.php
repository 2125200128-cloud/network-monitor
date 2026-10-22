<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionGeneral;
use App\Services\AlarmDiagnosisService;
use App\Services\FirewallsService;

/** Sección Firewalls: todo lo de los FortiGate en un solo lugar. */
class FirewallsController extends Controller
{
    public function index(FirewallsService $firewalls)
    {
        return view('firewalls.index', $firewalls->resumen() + [
            'umbralCpu' => (int) (ConfiguracionGeneral::query()->value('umbral_cpu_warning') ?: 85),
            'memAviso' => AlarmDiagnosisService::FORTI_MEM_AVISO_PCT,
            'memConserva' => AlarmDiagnosisService::FORTI_MEM_CONSERVA_PCT,
            'usuarioSnmp' => ConfiguracionGeneral::query()->value('snmpv3_usuario'),
        ]);
    }
}
