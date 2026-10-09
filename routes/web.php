<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ConsolaSeguraController;
use App\Http\Controllers\VlanController;

Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    
    Route::get('/setup-2fa', function () {
        return view('auth.setup-2fa');
    })->name('setup-2fa');

    Route::middleware(['password.confirm:password.confirm,900', 'check.admin'])->group(function () {
        // ==========================================
        // RUTAS ADMINISTRATIVAS
        // ==========================================
        Route::get('/settings', [App\Http\Controllers\SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [App\Http\Controllers\SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/role/{user}', [App\Http\Controllers\SettingController::class, 'updateRole'])->name('settings.update_role');
        Route::post('/settings/register', [App\Http\Controllers\SettingController::class, 'register'])->name('settings.register');
        Route::put('/settings/user/{user}', [App\Http\Controllers\SettingController::class, 'updateUser'])->name('settings.update_user');
        Route::delete('/settings/user/{user}', [App\Http\Controllers\SettingController::class, 'deleteUser'])->name('settings.delete_user');
    });

    // ==========================================
    // RUTAS ESTÁNDAR (Solo requieren Auth normal)
    // ==========================================
    Route::get('/consola', [ConsolaSeguraController::class, 'index'])->name('consola.index');
    Route::post('/consola/ejecutar', [ConsolaSeguraController::class, 'ejecutar'])->name('consola.ejecutar');
    Route::post('/consola/guardar-credenciales', [ConsolaSeguraController::class, 'guardarCredenciales'])->name('consola.guardar_credenciales');
    Route::post('/consola/probar-conexion', [ConsolaSeguraController::class, 'probarConexion'])->name('consola.probar_conexion');
    Route::get('/dispositivos/create', [App\Http\Controllers\DispositivoController::class, 'create'])->name('dispositivos.create');
    Route::post('/dispositivos', [App\Http\Controllers\DispositivoController::class, 'store'])->name('dispositivos.store');
    Route::get('/dispositivos/{id}', [App\Http\Controllers\DispositivoShowController::class, 'show'])->name('dispositivos.show');
    Route::get('/dispositivos/{id}/pdf', [App\Http\Controllers\DispositivoShowController::class, 'descargarPdf'])->name('dispositivos.pdf');
    
    Route::get('/topologia', [App\Http\Controllers\TopologiaController::class, 'index'])->name('topologia.index');
    Route::get('/api/topologia/nodos-enlaces', [App\Http\Controllers\TopologiaController::class, 'datosGrafos'])->name('topologia.datos');
    Route::post('/topologia/guardar-posiciones', [App\Http\Controllers\TopologiaController::class, 'guardarPosiciones'])->name('topologia.guardar_posiciones');
    Route::post('/topologia/descubrir', [App\Http\Controllers\DiscoveryController::class, 'run'])->name('topologia.descubrir');
    Route::post('/topologia/escanear-local', [App\Http\Controllers\DiscoveryController::class, 'scanLocalInterface'])->name('topologia.escanear_local');
    
    Route::get('/reportes/inventario-pdf', [App\Http\Controllers\DashboardController::class, 'descargarInventarioPdf'])->name('reportes.inventario_pdf');
    Route::get('/api/snmp/live-traffic', [App\Http\Controllers\DashboardController::class, 'snmpLiveTraffic'])->name('snmp.live_traffic');
    Route::get('/api/cdp/live', [App\Http\Controllers\DashboardController::class, 'cdpLive'])->name('cdp.live');
    Route::get('/api/kpi/live', [App\Http\Controllers\DashboardController::class, 'kpiLive'])->name('kpi.live');
    Route::post('/api/notificaciones/marcar-leida', [App\Http\Controllers\DashboardController::class, 'marcarNotificacionLeida'])->name('notificaciones.marcar_leida');
    Route::post('/api/notificaciones/descartar', [App\Http\Controllers\DashboardController::class, 'descartarNotificacion'])->name('notificaciones.descartar');

    // ==========================================
    // MONITOREO DE SERVICIOS Y PÁGINAS WEB
    // ==========================================
    Route::get('/servicios-web', [App\Http\Controllers\ServicioWebController::class, 'index'])->name('servicios_web.index');
    Route::post('/servicios-web', [App\Http\Controllers\ServicioWebController::class, 'store'])->name('servicios_web.store');
    Route::put('/servicios-web/{id}', [App\Http\Controllers\ServicioWebController::class, 'update'])->name('servicios_web.update');
    Route::delete('/servicios-web/{id}', [App\Http\Controllers\ServicioWebController::class, 'destroy'])->name('servicios_web.destroy');
    Route::post('/servicios-web/reprobar-todos', [App\Http\Controllers\ServicioWebController::class, 'reprobarTodos'])->name('servicios_web.reprobar_todos');
    Route::post('/servicios-web/{id}/reprobar', [App\Http\Controllers\ServicioWebController::class, 'reprobar'])->name('servicios_web.reprobar');
    Route::get('/api/servicios-web/live', [App\Http\Controllers\ServicioWebController::class, 'apiLive'])->name('servicios_web.api_live');
});

