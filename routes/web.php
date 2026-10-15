<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ConsolaSeguraController;
use App\Http\Controllers\DiscoveryController;
use App\Http\Controllers\DispositivoController;
use App\Http\Controllers\DispositivoShowController;
use App\Http\Controllers\NotaDispositivoController;
use App\Http\Controllers\ServicioWebController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TopologiaController;

Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/setup-2fa', function () {
        return view('auth.setup-2fa');
    })->name('setup-2fa');

    // ==========================================
    // RUTAS ADMINISTRATIVAS
    // ==========================================
    Route::middleware(['password.confirm:password.confirm,900', 'check.admin'])->group(function () {
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/role/{user}', [SettingController::class, 'updateRole'])->name('settings.update_role');
        Route::post('/settings/register', [SettingController::class, 'register'])->name('settings.register');
        Route::put('/settings/user/{user}', [SettingController::class, 'updateUser'])->name('settings.update_user');
        Route::delete('/settings/user/{user}', [SettingController::class, 'deleteUser'])->name('settings.delete_user');
    });

    // ==========================================
    // CONSULTA (todos los roles, incluido Auditor)
    // ==========================================
    Route::get('/consola', [ConsolaSeguraController::class, 'index'])->name('consola.index');
    // Solo comandos de lectura (lista blanca en el controlador)
    Route::post('/consola/ejecutar', [ConsolaSeguraController::class, 'ejecutar'])->name('consola.ejecutar');
    Route::get('/dispositivos/{id}', [DispositivoShowController::class, 'show'])->whereNumber('id')->name('dispositivos.show');
    Route::get('/dispositivos/{id}/pdf', [DispositivoShowController::class, 'descargarPdf'])->whereNumber('id')->name('dispositivos.pdf');

    Route::get('/topologia', [TopologiaController::class, 'index'])->name('topologia.index');
    Route::get('/api/topologia/nodos-enlaces', [TopologiaController::class, 'datosGrafos'])->name('topologia.datos');
    Route::get('/api/dispositivos/{id}/notas', [NotaDispositivoController::class, 'index'])->whereNumber('id')->name('notas.index');

    Route::get('/reportes/inventario-pdf', [DashboardController::class, 'descargarInventarioPdf'])->name('reportes.inventario_pdf');
    Route::get('/api/snmp/live-traffic', [DashboardController::class, 'snmpLiveTraffic'])->name('snmp.live_traffic');
    Route::get('/api/cdp/live', [DashboardController::class, 'cdpLive'])->name('cdp.live');
    Route::get('/api/kpi/live', [DashboardController::class, 'kpiLive'])->name('kpi.live');
    Route::get('/api/dashboard/series', [DashboardController::class, 'series'])->name('dashboard.series');
    Route::post('/api/notificaciones/marcar-leida', [DashboardController::class, 'marcarNotificacionLeida'])->name('notificaciones.marcar_leida');
    Route::post('/api/notificaciones/descartar', [DashboardController::class, 'descartarNotificacion'])->name('notificaciones.descartar');

    Route::get('/servicios-web', [ServicioWebController::class, 'index'])->name('servicios_web.index');
    Route::get('/api/servicios-web/live', [ServicioWebController::class, 'apiLive'])->name('servicios_web.api_live');

    // ==========================================
    // OPERACIÓN (Admin y Operador)
    // ==========================================
    Route::middleware(['check.role:Admin,Operador'])->group(function () {
        Route::post('/consola/guardar-credenciales', [ConsolaSeguraController::class, 'guardarCredenciales'])->name('consola.guardar_credenciales');
        Route::post('/consola/probar-conexion', [ConsolaSeguraController::class, 'probarConexion'])->name('consola.probar_conexion');

        Route::get('/dispositivos/create', [DispositivoController::class, 'create'])->name('dispositivos.create');
        Route::post('/dispositivos', [DispositivoController::class, 'store'])->name('dispositivos.store');
        Route::post('/api/dispositivos/{id}/tipo', [DispositivoController::class, 'corregirTipo'])->whereNumber('id')->name('dispositivos.corregir_tipo');
        Route::post('/api/dispositivos/{id}/snmp', [DispositivoController::class, 'guardarSnmp'])->whereNumber('id')->name('dispositivos.snmp.guardar');
        Route::post('/api/dispositivos/{id}/snmp/probar', [DispositivoController::class, 'probarSnmp'])->whereNumber('id')->middleware('throttle:20,1')->name('dispositivos.snmp.probar');

        // Plano compartido: acomodo y zonas
        Route::post('/topologia/guardar-posiciones', [TopologiaController::class, 'guardarPosiciones'])->name('topologia.guardar_posiciones');
        Route::post('/api/topologia/zonas', [TopologiaController::class, 'guardarZona'])->name('topologia.zonas.store');
        Route::put('/api/topologia/zonas/{id}', [TopologiaController::class, 'actualizarZona'])->whereNumber('id')->name('topologia.zonas.update');
        Route::delete('/api/topologia/zonas', [TopologiaController::class, 'eliminarTodasLasZonas'])->name('topologia.zonas.destroy_all');
        Route::delete('/api/topologia/zonas/{id}',[TopologiaController::class, 'eliminarZona'])->whereNumber('id')->name('topologia.zonas.destroy');

        // Notas de dispositivo (las secretas solo se revelan aquí, con registro en log)
        Route::post('/api/dispositivos/{id}/notas', [NotaDispositivoController::class, 'store'])->whereNumber('id')->name('notas.store');
        Route::put('/api/dispositivos/{id}/notas/{nota}', [NotaDispositivoController::class, 'update'])->whereNumber(['id', 'nota'])->name('notas.update');
        Route::delete('/api/dispositivos/{id}/notas/{nota}', [NotaDispositivoController::class, 'destroy'])->whereNumber(['id', 'nota'])->name('notas.destroy');
        Route::post('/api/dispositivos/{id}/notas/{nota}/revelar', [NotaDispositivoController::class, 'revelar'])->whereNumber(['id', 'nota'])->name('notas.revelar');

        Route::post('/topologia/descubrir', [DiscoveryController::class, 'run'])->name('topologia.descubrir');
        Route::post('/topologia/escanear-local', [DiscoveryController::class, 'scanLocalInterface'])->name('topologia.escanear_local');

        Route::post('/servicios-web', [ServicioWebController::class, 'store'])->name('servicios_web.store');
        Route::put('/servicios-web/{id}', [ServicioWebController::class, 'update'])->name('servicios_web.update');
        Route::delete('/servicios-web/{id}', [ServicioWebController::class, 'destroy'])->name('servicios_web.destroy');
        Route::post('/servicios-web/reprobar-todos', [ServicioWebController::class, 'reprobarTodos'])->name('servicios_web.reprobar_todos');
        Route::post('/servicios-web/{id}/reprobar', [ServicioWebController::class, 'reprobar'])->name('servicios_web.reprobar');
    });
});
