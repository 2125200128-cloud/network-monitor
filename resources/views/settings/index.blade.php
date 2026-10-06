@extends('layouts.app')

@section('styles')
        /* ======================== WINDOWS SETTINGS LAYOUT ======================== */
        .settings-container {
            display: flex;
            gap: 0;
            height: calc(100vh - 7rem);
            background: white;
            border-radius: 1.5rem;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
        }

        /* -- Left Panel (Category List) -- */
        .settings-nav {
            width: 280px;
            min-width: 280px;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            border-right: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        .settings-nav-header {
            padding: 1.75rem 1.5rem 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .settings-nav-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            gap: 0.85rem;
            cursor: pointer;
            color: #475569;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.15s ease;
            border-left: 3px solid transparent;
            text-decoration: none;
        }
        .settings-nav-item:hover {
            background: rgba(59, 89, 152, 0.06);
            color: #3b5998;
        }
        .settings-nav-item.active {
            background: rgba(59, 89, 152, 0.08);
            border-left-color: #3b5998;
            color: #3b5998;
            font-weight: 700;
        }
        .settings-nav-item .nav-icon {
            width: 1.35rem;
            height: 1.35rem;
            flex-shrink: 0;
        }
        .settings-nav-item .nav-badge {
            margin-left: auto;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
            background: #3b5998;
            color: white;
        }
        .nav-separator {
            height: 1px;
            background: #e2e8f0;
            margin: 0.5rem 1.5rem;
        }

        /* -- Right Panel (Content) -- */
        .settings-content {
            flex: 1;
            overflow-y: auto;
            padding: 2rem 2.5rem;
        }
        .settings-section {
            display: none;
            animation: settingsFadeIn 0.25s ease;
        }
        .settings-section.active {
            display: block;
        }
        @keyframes settingsFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .settings-section-title {
            font-size: 1.6rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.35rem;
        }
        .settings-section-subtitle {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }
        .settings-group {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.25rem;
        }
        .settings-group-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 700;
            color: #94a3b8;
            margin-bottom: 1rem;
        }
        .settings-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.85rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .settings-row:last-child {
            border-bottom: none;
        }
        .settings-row-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #334155;
        }
        .settings-row-desc {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 0.15rem;
        }
        .settings-input {
            width: 200px;
            padding: 0.5rem 0.85rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.65rem;
            font-size: 0.875rem;
            background: white;
            color: #1e293b;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .settings-input:focus {
            outline: none;
            border-color: #3b5998;
            box-shadow: 0 0 0 3px rgba(59, 89, 152, 0.1);
        }

        /* User Table */
        .user-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .user-table thead th {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            color: #94a3b8;
            padding: 0.75rem 1rem;
            text-align: left;
            background: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
        }
        .user-table thead th:first-child { border-radius: 0.75rem 0 0 0; }
        .user-table thead th:last-child { border-radius: 0 0.75rem 0 0; }
        .user-table tbody td {
            padding: 0.85rem 1rem;
            font-size: 0.875rem;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .user-table tbody tr:hover { background: #fafbfd; }
        .user-table tbody tr:last-child td { border-bottom: none; }

        /* Register Form Card */
        .register-card {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 1.75rem;
        }
        .register-card .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.4rem;
            display: block;
        }
        .register-card .form-input {
            width: 100%;
            padding: 0.6rem 0.85rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.65rem;
            font-size: 0.875rem;
            background: white;
            color: #1e293b;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .register-card .form-input:focus {
            outline: none;
            border-color: #3b5998;
            box-shadow: 0 0 0 3px rgba(59, 89, 152, 0.1);
        }

        /* Role badges */
        .role-admin { background: #dbeafe; color: #1e40af; }
        .role-operador { background: #dcfce7; color: #166534; }
        .role-auditor { background: #fef3c7; color: #92400e; }

        /* Buttons */
        .btn-primary {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 0.65rem 1.5rem; border-radius: 0.75rem;
            font-size: 0.875rem; font-weight: 700;
            background: #3b5998; color: white;
            transition: all 0.2s; border: none; cursor: pointer;
        }
        .btn-primary:hover { background: #2d4373; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(59, 89, 152, 0.3); }
        .btn-danger {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 0.45rem 0.75rem; border-radius: 0.55rem;
            font-size: 0.75rem; font-weight: 600;
            background: #fee2e2; color: #991b1b;
            transition: all 0.2s; border: 1px solid #fecaca; cursor: pointer;
        }
        .btn-danger:hover { background: #fecaca; }

        @media (max-width: 768px) {
            .settings-container { flex-direction: column; height: auto; }
            .settings-nav { width: 100%; min-width: 100%; flex-direction: row; overflow-x: auto; border-right: none; border-bottom: 1px solid #e2e8f0; }
            .settings-nav-header { display: none; }
            .settings-nav-item { padding: 0.75rem 1rem; border-left: none; border-bottom: 3px solid transparent; white-space: nowrap; }
            .settings-nav-item.active { border-left-color: transparent; border-bottom-color: #3b5998; }
            .nav-separator { display: none; }
            .settings-content { padding: 1.25rem; }
        }

        /* ======================== DARK MODE NOC OLED OVERRIDES ======================== */
        .dark .settings-container {
            background: #090b10 !important;
            border: 1px solid rgba(51, 65, 85, 0.4) !important;
            box-shadow: none !important;
        }
        .dark .settings-nav {
            background: #090b10 !important;
            border-right: 1px solid rgba(51, 65, 85, 0.4) !important;
        }
        .dark .settings-nav-header {
            border-bottom: 1px solid rgba(51, 65, 85, 0.4) !important;
        }
        .dark .settings-nav-header h2 {
            color: #f1f5f9 !important;
        }
        .dark .settings-nav-header p {
            color: #64748b !important;
        }
        .dark .settings-nav-header input {
            background: #0d1017 !important;
            border-color: rgba(51, 65, 85, 0.5) !important;
            color: #f1f5f9 !important;
        }
        .dark .settings-nav-header input::placeholder {
            color: #475569 !important;
        }
        .dark .settings-nav-item {
            color: #94a3b8 !important;
        }
        .dark .settings-nav-item:hover {
            background: rgba(30, 41, 59, 0.5) !important;
            color: #60a5fa !important;
        }
        .dark .settings-nav-item.active {
            background: rgba(59, 130, 246, 0.12) !important;
            border-left-color: #3b82f6 !important;
            color: #60a5fa !important;
        }
        .dark .nav-separator {
            background: rgba(51, 65, 85, 0.4) !important;
        }
        .dark .settings-content {
            background: #07090e !important;
            color: #f1f5f9 !important;
        }
        .dark .settings-section-title {
            color: #f8fafc !important;
        }
        .dark .settings-section-subtitle {
            color: #94a3b8 !important;
        }
        .dark .settings-group {
            background: #0d1017 !important;
            border: 1px solid rgba(51, 65, 85, 0.4) !important;
        }
        .dark .settings-group-title {
            color: #64748b !important;
        }
        .dark .settings-row {
            border-bottom: 1px solid rgba(51, 65, 85, 0.3) !important;
        }
        .dark .settings-row-label {
            color: #e2e8f0 !important;
        }
        .dark .settings-row-desc {
            color: #94a3b8 !important;
        }
        .dark .settings-input {
            background: #090b10 !important;
            border-color: rgba(51, 65, 85, 0.5) !important;
            color: #f1f5f9 !important;
        }
        .dark .settings-input:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2) !important;
        }
        .dark .user-table thead th {
            background: #090b10 !important;
            border-bottom: 1px solid rgba(51, 65, 85, 0.4) !important;
            color: #94a3b8 !important;
        }
        .dark .user-table tbody td {
            color: #cbd5e1 !important;
            border-bottom: 1px solid rgba(51, 65, 85, 0.3) !important;
        }
        .dark .user-table tbody tr:hover {
            background: rgba(30, 41, 59, 0.3) !important;
        }
        .dark .register-card {
            background: #0d1017 !important;
            border: 1px solid rgba(51, 65, 85, 0.4) !important;
        }
        .dark .register-card .form-label {
            color: #cbd5e1 !important;
        }
        .dark .register-card .form-input {
            background: #090b10 !important;
            border-color: rgba(51, 65, 85, 0.5) !important;
            color: #f1f5f9 !important;
        }
    </style>
@endsection

@section('content')
    <div class="settings-container">
        {{-- ==================== LEFT NAV PANEL ==================== --}}
        <nav class="settings-nav custom-scrollbar">
            <div class="settings-nav-header">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#3b5998] to-[#5c8096] flex items-center justify-center shadow-sm">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-extrabold text-gray-900 leading-tight">Configuración</h2>
                        <p class="text-xs text-gray-400 font-medium">Ajustes del sistema</p>
                    </div>
                </div>
                {{-- Search (visual only) --}}
                <div class="relative">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="text" placeholder="Buscar ajuste..." class="w-full pl-9 pr-3 py-2 bg-white border border-gray-200 rounded-lg text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#3b5998]/20 focus:border-[#3b5998] transition" id="settingsSearch">
                </div>
            </div>

            {{-- Navigation Items --}}
            <div class="py-2">
                <a href="#" class="settings-nav-item {{ $section === 'general' ? 'active' : '' }}" data-section="general">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    Motor SNMP
                </a>
                <a href="#" class="settings-nav-item {{ $section === 'cuentas' ? 'active' : '' }}" data-section="cuentas">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    Cuentas
                    <span class="nav-badge">{{ $users->count() }}</span>
                </a>

                <div class="nav-separator"></div>

                <a href="#" class="settings-nav-item {{ $section === 'seguridad' ? 'active' : '' }}" data-section="seguridad">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    Seguridad
                </a>
                <a href="#" class="settings-nav-item {{ $section === 'notificaciones' ? 'active' : '' }}" data-section="notificaciones">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    Notificaciones
                </a>

                <div class="nav-separator"></div>

                <a href="#" class="settings-nav-item {{ $section === 'apariencia' ? 'active' : '' }}" data-section="apariencia">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path></svg>
                    Apariencia
                </a>
                <a href="#" class="settings-nav-item {{ $section === 'acerca' ? 'active' : '' }}" data-section="acerca">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Acerca de
                </a>
            </div>

            {{-- Footer --}}
            <div class="mt-auto p-4 border-t border-gray-200">
                <div class="flex items-center gap-3 p-1.5 mb-2.5">
                    @if(auth()->user()->avatar)
                        <img src="{{ asset('storage/' . auth()->user()->avatar) }}" alt="{{ auth()->user()->name }}" class="w-9 h-9 rounded-full object-cover border-2 border-[#3b5998]/20 flex-shrink-0">
                    @else
                        <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'Admin') }}&background=3b5998&color=fff&size=64" alt="{{ auth()->user()->name }}" class="w-9 h-9 rounded-full border-2 border-[#3b5998]/20 flex-shrink-0">
                    @endif
                    <div class="overflow-hidden">
                        <p class="text-xs font-bold text-gray-800 leading-tight truncate">
                            @if(auth()->user()->prefijo)<span class="text-[#3b5998]">{{ auth()->user()->prefijo }}</span> @endif{{ auth()->user()->name ?? 'Administrador' }}
                        </p>
                        <p class="text-[10px] text-gray-400 font-medium">{{ auth()->user()->role ?? 'Admin' }}</p>
                    </div>
                </div>

                {{-- Botón Cerrar Sesión --}}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-xl bg-red-50 hover:bg-red-100 text-red-600 text-xs font-bold transition border border-red-200 shadow-sm" title="Cerrar Sesión">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        Cerrar Sesión
                    </button>
                </form>
            </div>
        </nav>

        {{-- ==================== RIGHT CONTENT PANEL ==================== --}}
        <div class="settings-content custom-scrollbar">
            
            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="mb-6 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold flex items-center gap-2 animate-pulse">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-6 p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold flex items-center gap-2">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    {{ session('error') }}
                </div>
            @endif
            @if($errors->any())
                <div class="mb-6 p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ========== SECTION: General / Motor SNMP ========== --}}
            <div class="settings-section {{ $section === 'general' ? 'active' : '' }}" id="section-general">
                <h1 class="settings-section-title">Motor de Sondeo SNMP</h1>
                <p class="settings-section-subtitle">Configura los parámetros del motor de sondeo, comunidad SNMP y umbrales de alerta.</p>

                <form action="{{ route('settings.update') }}" method="POST">
                    @csrf

                    <div class="settings-group">
                        <h4 class="settings-group-title">Parámetros del Worker</h4>
                        <div class="settings-row">
                            <div>
                                <div class="settings-row-label">Intervalo de Sondeo</div>
                                <div class="settings-row-desc">Tiempo entre ciclos de escaneo del worker Python</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="number" name="intervalo_sondeo_segundos" value="{{ $settings->intervalo_sondeo_segundos }}" min="10" class="settings-input text-right" style="width: 120px;">
                                <span class="text-xs font-bold text-gray-400">seg</span>
                            </div>
                        </div>
                        <div class="settings-row">
                            <div>
                                <div class="settings-row-label">Comunidad SNMP</div>
                                <div class="settings-row-desc">Cadena de lectura por defecto (ro community)</div>
                            </div>
                            <input type="text" name="comunidad_snmp_default" value="{{ $settings->comunidad_snmp_default }}" class="settings-input font-mono" style="width: 180px;">
                        </div>
                        <div class="settings-row">
                            <div>
                                <div class="settings-row-label">IP del Switch Core</div>
                                <div class="settings-row-desc">IP del equipo centralizado para el rastreo de CDP</div>
                            </div>
                            <input type="text" name="ip_switch_core" value="{{ $settings->ip_switch_core }}" class="settings-input font-mono" placeholder="10.4.254.3" style="width: 180px;">
                        </div>
                    </div>

                    <div class="settings-group">
                        <h4 class="settings-group-title">Umbrales de Alerta</h4>
                        <div class="settings-row">
                            <div>
                                <div class="settings-row-label">Warning CPU</div>
                                <div class="settings-row-desc">Marca el dispositivo en estado "warning" si la CPU supera este valor</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="number" name="umbral_cpu_warning" value="{{ $settings->umbral_cpu_warning }}" min="1" max="100" class="settings-input text-right" style="width: 100px;">
                                <span class="text-xs font-bold text-gray-400">%</span>
                            </div>
                        </div>
                        <div class="settings-row">
                            <div>
                                <div class="settings-row-label">Warning Packet Loss</div>
                                <div class="settings-row-desc">Marca como advertencia si la pérdida de paquetes supera este umbral</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="number" name="umbral_loss_warning" value="{{ $settings->umbral_loss_warning }}" min="1" max="100" class="settings-input text-right" style="width: 100px;">
                                <span class="text-xs font-bold text-gray-400">%</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end mt-4">
                        <button type="submit" class="btn-primary">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                            Guardar Parámetros
                        </button>
                    </div>
                </form>
            </div>

            {{-- ========== SECTION: Cuentas ========== --}}
            <div class="settings-section {{ $section === 'cuentas' ? 'active' : '' }}" id="section-cuentas">
                <h1 class="settings-section-title">Cuentas de Usuario</h1>
                <p class="settings-section-subtitle">Administra los operadores del sistema. Registra nuevas cuentas y gestiona roles de acceso.</p>

                {{-- Current User Session & Logout Card --}}
                <div class="bg-gradient-to-r from-blue-50/70 to-indigo-50/30 dark:from-[#090b10] dark:to-[#0d1017] rounded-2xl p-4 mb-6 border border-blue-100 dark:border-slate-800/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm dark:shadow-none">
                    <div class="flex items-center gap-3.5">
                        <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" class="w-12 h-12 rounded-full object-cover border-2 border-[#3b5998]/30 shadow-sm flex-shrink-0" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'Admin') }}&background=3b5998&color=fff&size=80';">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-extrabold text-gray-900 dark:text-white">
                                    @if(auth()->user()->prefijo)<span class="text-[#3b5998] dark:text-blue-400">{{ auth()->user()->prefijo }}</span> @endif{{ auth()->user()->name }}
                                </h3>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-950/60 text-[#3b5998] dark:text-blue-400 uppercase tracking-wide">{{ auth()->user()->role }}</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-slate-400 font-mono mt-0.5">{{ auth()->user()->email }} · <span class="text-emerald-600 dark:text-emerald-400 font-bold">Sesión Activa</span></p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-red-50 hover:bg-red-100 text-red-600 text-xs font-bold transition border border-red-200 shadow-sm hover:shadow">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                            Cerrar Sesión
                        </button>
                    </form>
                </div>

                {{-- Register New Account --}}
                <div class="register-card mb-6">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-8 h-8 rounded-lg bg-[#f26419] flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-800">Registrar Nueva Cuenta</h3>
                            <p class="text-xs text-gray-400">Completa los datos para crear un nuevo operador</p>
                        </div>
                    </div>

                    <form action="{{ route('settings.register') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        {{-- Row 1: Avatar + Name + Prefijo + Email --}}
                        <div class="flex flex-col md:flex-row gap-4 mb-4">
                            {{-- Avatar Upload --}}
                            <div class="flex flex-col items-center justify-center" style="min-width: 110px;">
                                <label class="form-label text-center mb-1.5">Foto <span class="text-gray-300 font-normal">(opc.)</span></label>
                                <label for="avatarUpload" class="cursor-pointer group relative">
                                    <div class="w-16 h-16 rounded-full bg-gray-100 border-2 border-dashed border-gray-300 group-hover:border-[#3b5998] flex items-center justify-center overflow-hidden transition" id="avatarPreviewContainer">
                                        <svg id="avatarPlaceholderIcon" class="w-6 h-6 text-gray-300 group-hover:text-[#3b5998] transition" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"></path></svg>
                                        <img id="avatarPreviewImg" src="" alt="Preview" class="w-full h-full object-cover hidden">
                                    </div>
                                    <input type="file" name="avatar" id="avatarUpload" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="previewAvatar(this)">
                                </label>
                                <p class="text-[9px] text-gray-400 mt-1 text-center">JPG/PNG · 2MB</p>
                            </div>

                            {{-- Name + Prefijo + Email --}}
                            <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="form-label">Nombre completo</label>
                                    <input type="text" name="name" required placeholder="Ej. Juan Pérez" class="form-input" value="{{ old('name') }}">
                                </div>
                                <div>
                                    <label class="form-label">Prefijo / Título <span class="text-gray-300 font-normal">(opc.)</span></label>
                                    <select name="prefijo" class="form-input">
                                        <option value="">— Sin prefijo —</option>
                                        <option value="Ing." {{ old('prefijo') == 'Ing.' ? 'selected' : '' }}>Ing.</option>
                                        <option value="Lic." {{ old('prefijo') == 'Lic.' ? 'selected' : '' }}>Lic.</option>
                                        <option value="Tec." {{ old('prefijo') == 'Tec.' ? 'selected' : '' }}>Tec.</option>
                                        <option value="TSU." {{ old('prefijo') == 'TSU.' ? 'selected' : '' }}>TSU.</option>
                                        <option value="Dr." {{ old('prefijo') == 'Dr.' ? 'selected' : '' }}>Dr.</option>
                                        <option value="Mtro." {{ old('prefijo') == 'Mtro.' ? 'selected' : '' }}>Mtro.</option>
                                        <option value="Mtra." {{ old('prefijo') == 'Mtra.' ? 'selected' : '' }}>Mtra.</option>
                                        <option value="C.P." {{ old('prefijo') == 'C.P.' ? 'selected' : '' }}>C.P.</option>
                                        <option value="Arq." {{ old('prefijo') == 'Arq.' ? 'selected' : '' }}>Arq.</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Correo electrónico</label>
                                    <input type="email" name="email" required placeholder="usuario@dominio.com" class="form-input" value="{{ old('email') }}">
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
                            <div>
                                <label class="form-label">Contraseña</label>
                                <input type="password" name="password" required placeholder="Mín. 8 caracteres" class="form-input">
                            </div>
                            <div>
                                <label class="form-label">Confirmar Contraseña</label>
                                <input type="password" name="password_confirmation" required placeholder="Repetir contraseña" class="form-input">
                            </div>
                            <div>
                                <label class="form-label">Rol</label>
                                <select name="role" class="form-input">
                                    <option value="Operador">Operador</option>
                                    <option value="Admin">Administrador</option>
                                    <option value="Auditor">Auditor</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="btn-primary">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                                Crear Cuenta
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Existing Users Table --}}
                <div class="settings-group" style="padding: 0; overflow: hidden;">
                    <div class="px-5 py-3.5 flex items-center justify-between border-b border-gray-200 dark:border-slate-800/50 bg-[#f8fafc] dark:bg-[#090b10]">
                        <h4 class="text-xs uppercase tracking-wider font-bold text-gray-400 dark:text-slate-500">Usuarios Registrados</h4>
                        <span class="text-xs font-bold text-[#3b5998] dark:text-blue-400 bg-blue-50 dark:bg-blue-950/60 px-2.5 py-0.5 rounded-full border border-transparent dark:border-blue-800/40">{{ $users->count() }} total</span>
                    </div>

                    <table class="user-table">
                        <thead>
                            <tr>
                                <th style="border-radius:0;">Usuario</th>
                                <th>Correo</th>
                                <th>Rol</th>
                                <th class="text-center" style="border-radius:0;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $user)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="w-8 h-8 rounded-full flex-shrink-0 object-cover border border-gray-200 dark:border-slate-700" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background={{ strtolower($user->role) === 'admin' ? '3b5998' : (strtolower($user->role) === 'operador' ? '16a34a' : 'f26419') }}&color=fff&size=64';">
                                        <div>
                                            <p class="font-bold text-gray-800 dark:text-white text-sm">
                                                @if($user->prefijo)<span class="text-[#3b5998] dark:text-blue-400">{{ $user->prefijo }}</span> @endif{{ $user->name }}
                                            </p>
                                            @if($user->id === auth()->id())
                                                <p class="text-[10px] text-blue-500 font-semibold">Tú</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="font-mono text-gray-500 dark:text-slate-400 text-xs">{{ $user->email }}</td>
                                <td>
                                    <span class="text-xs font-bold rounded-lg py-1.5 px-2.5 inline-block {{ strtolower($user->role) === 'admin' ? 'role-admin' : (strtolower($user->role) === 'operador' ? 'role-operador' : 'role-auditor') }}">
                                        {{ $user->role }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center justify-center gap-1.5">
                                        {{-- Ver --}}
                                        <button type="button" onclick="openViewModal({{ $user->id }})" class="inline-flex items-center px-2 py-1 rounded-md text-[11px] font-semibold bg-blue-50 dark:bg-blue-950/50 text-[#3b5998] dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/50 transition border border-transparent dark:border-blue-800/40" title="Ver detalles">
                                            <svg class="w-3.5 h-3.5 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                            Ver
                                        </button>
                                        {{-- Editar --}}
                                        <button type="button" onclick="openEditModal({{ $user->id }})" class="inline-flex items-center px-2 py-1 rounded-md text-[11px] font-semibold bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-900/50 transition border border-transparent dark:border-amber-800/40" title="Editar usuario">
                                            <svg class="w-3.5 h-3.5 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                            Editar
                                        </button>
                                        {{-- Eliminar --}}
                                        @if($user->id !== auth()->id())
                                            <form action="{{ route('settings.delete_user', $user->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Estás seguro de eliminar a {{ $user->name }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center px-2 py-1 rounded-md text-[11px] font-semibold bg-red-50 dark:bg-red-950/50 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/50 transition border border-transparent dark:border-red-800/40" title="Eliminar cuenta">
                                                    <svg class="w-3.5 h-3.5 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    Eliminar
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-[10px] text-gray-300 italic px-2">—</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>

                            {{-- Hidden data for JS modals --}}
                            <script>
                                if (!window.__users) window.__users = {};
                                window.__users[{{ $user->id }}] = {
                                    id: {{ $user->id }},
                                    name: @json($user->name),
                                    prefijo: @json($user->prefijo ?? ''),
                                    email: @json($user->email),
                                    role: @json($user->role),
                                    avatar: @json($user->avatar_url),
                                    avatarRaw: @json($user->avatar ?? ''),
                                    isSelf: {{ $user->id === auth()->id() ? 'true' : 'false' }},
                                    created: @json($user->created_at ? $user->created_at->format('d/m/Y H:i') : 'N/A'),
                                };
                            </script>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- ==================== VIEW MODAL (Detail Card) ==================== --}}
                <div id="viewModal" class="fixed inset-0 z-50 hidden">
                    <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" onclick="closeViewModal()"></div>
                    <div class="absolute right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl transform transition-transform duration-300 translate-x-full flex flex-col" id="viewModalPanel">
                        {{-- Header --}}
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                            <h3 class="text-base font-extrabold text-gray-900">Detalle de Usuario</h3>
                            <button onclick="closeViewModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center transition">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        {{-- Body --}}
                        <div class="flex-1 overflow-y-auto px-6 py-6 custom-scrollbar">
                            <div class="flex flex-col items-center mb-6">
                                <img id="viewAvatar" src="" alt="Avatar" class="w-20 h-20 rounded-full object-cover border-4 border-gray-100 shadow-md mb-3">
                                <h4 id="viewFullName" class="text-lg font-extrabold text-gray-900"></h4>
                                <span id="viewRoleBadge" class="text-xs font-bold px-3 py-1 rounded-full mt-1"></span>
                            </div>
                            <div class="space-y-4">
                                <div class="settings-group" style="margin-bottom: 0;">
                                    <div class="settings-row">
                                        <div class="settings-row-label">Correo</div>
                                        <span id="viewEmail" class="text-sm font-mono text-gray-600"></span>
                                    </div>
                                    <div class="settings-row">
                                        <div class="settings-row-label">Prefijo</div>
                                        <span id="viewPrefijo" class="text-sm font-bold text-[#3b5998]"></span>
                                    </div>
                                    <div class="settings-row">
                                        <div class="settings-row-label">Rol</div>
                                        <span id="viewRole" class="text-sm font-bold"></span>
                                    </div>
                                    <div class="settings-row">
                                        <div class="settings-row-label">Registrado</div>
                                        <span id="viewCreated" class="text-sm font-mono text-gray-500"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- Footer --}}
                        <div class="px-6 py-4 border-t border-gray-100 flex items-center gap-3">
                            <button onclick="closeViewModal()" class="flex-1 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-sm font-bold text-gray-600 transition">Cerrar</button>
                            <button id="viewModalEditBtn" onclick="editFromViewModal()" class="flex-1 py-2.5 rounded-xl bg-[#3b5998] hover:bg-[#2d4373] text-sm font-bold text-white transition flex items-center justify-center gap-1.5 shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                Editar
                            </button>
                        </div>
                    </div>
                </div>

                {{-- ==================== EDIT MODAL (Slide-over) ==================== --}}
                <div id="editModal" class="fixed inset-0 z-50 hidden">
                    <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" onclick="closeEditModal()"></div>
                    <div class="absolute right-0 top-0 h-full w-full max-w-lg bg-white shadow-2xl transform transition-transform duration-300 translate-x-full flex flex-col" id="editModalPanel">
                        {{-- Header --}}
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-[#3b5998]/5 to-transparent">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </div>
                                <h3 class="text-base font-extrabold text-gray-900">Editar Usuario</h3>
                            </div>
                            <button onclick="closeEditModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center transition">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        {{-- Body (Form) --}}
                        <form id="editUserForm" method="POST" enctype="multipart/form-data" class="flex-1 flex flex-col overflow-hidden">
                            @csrf
                            @method('PUT')
                            <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5 custom-scrollbar">

                                {{-- Avatar --}}
                                <div class="flex items-center gap-4">
                                    <label for="editAvatarUpload" class="cursor-pointer group relative flex-shrink-0">
                                        <div class="w-16 h-16 rounded-full bg-gray-100 border-2 border-dashed border-gray-300 group-hover:border-[#3b5998] flex items-center justify-center overflow-hidden transition" id="editAvatarContainer">
                                            <img id="editAvatarPreview" src="" alt="Avatar" class="w-full h-full object-cover">
                                        </div>
                                        <div class="absolute -bottom-0.5 -right-0.5 w-6 h-6 rounded-full bg-[#3b5998] flex items-center justify-center shadow-md">
                                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                        </div>
                                        <input type="file" name="avatar" id="editAvatarUpload" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="previewEditAvatar(this)">
                                    </label>
                                    <div class="flex-1">
                                        <p class="text-sm font-bold text-gray-700">Foto de perfil</p>
                                        <p class="text-xs text-gray-400">JPG, PNG o WebP · Máx 2MB</p>
                                        <label class="inline-flex items-center gap-1.5 mt-1.5 cursor-pointer">
                                            <input type="checkbox" name="remove_avatar" id="editRemoveAvatar" class="rounded border-gray-300 text-red-500 focus:ring-red-400 w-3.5 h-3.5">
                                            <span class="text-[11px] text-red-400 font-semibold">Quitar foto actual</span>
                                        </label>
                                    </div>
                                </div>

                                {{-- Name + Prefijo --}}
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="form-label">Nombre completo</label>
                                        <input type="text" name="name" id="editName" required class="form-input">
                                    </div>
                                    <div>
                                        <label class="form-label">Prefijo <span class="text-gray-300 font-normal">(opc.)</span></label>
                                        <select name="prefijo" id="editPrefijo" class="form-input">
                                            <option value="">— Sin prefijo —</option>
                                            <option value="Ing.">Ing.</option>
                                            <option value="Lic.">Lic.</option>
                                            <option value="Tec.">Tec.</option>
                                            <option value="TSU.">TSU.</option>
                                            <option value="Dr.">Dr.</option>
                                            <option value="Mtro.">Mtro.</option>
                                            <option value="Mtra.">Mtra.</option>
                                            <option value="C.P.">C.P.</option>
                                            <option value="Arq.">Arq.</option>
                                        </select>
                                    </div>
                                </div>

                                {{-- Email --}}
                                <div>
                                    <label class="form-label">Correo electrónico</label>
                                    <input type="email" name="email" id="editEmail" required class="form-input">
                                </div>

                                {{-- Role --}}
                                <div>
                                    <label class="form-label">Rol de acceso</label>
                                    <select name="role" id="editRole" class="form-input">
                                        <option value="Admin">Administrador</option>
                                        <option value="Operador">Operador</option>
                                        <option value="Auditor">Auditor</option>
                                    </select>
                                </div>

                                {{-- Password (optional) --}}
                                <div class="border-t border-gray-100 pt-4">
                                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Cambiar Contraseña <span class="font-normal normal-case text-gray-300">(dejar vacío para mantener)</span></p>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="form-label">Nueva contraseña</label>
                                            <input type="password" name="password" placeholder="Mín. 8 caracteres" class="form-input">
                                        </div>
                                        <div>
                                            <label class="form-label">Confirmar contraseña</label>
                                            <input type="password" name="password_confirmation" placeholder="Repetir" class="form-input">
                                        </div>
                                    </div>
                                </div>

                            </div>
                            {{-- Footer --}}
                            <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between gap-3">
                                <button type="button" onclick="closeEditModal()" class="px-5 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-sm font-bold text-gray-600 transition">Cancelar</button>
                                <button type="submit" class="btn-primary">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                    Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ========== SECTION: Seguridad ========== --}}
            <div class="settings-section {{ $section === 'seguridad' ? 'active' : '' }}" id="section-seguridad">
                <h1 class="settings-section-title">Seguridad</h1>
                <p class="settings-section-subtitle">Configuraciones de autenticación y control de acceso.</p>

                <div class="settings-group">
                    <h4 class="settings-group-title">Autenticación</h4>
                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Autenticación de Dos Factores (2FA)</div>
                            <div class="settings-row-desc">Requiere un código adicional al iniciar sesión</div>
                        </div>
                        <a href="{{ route('setup-2fa') }}" class="text-sm font-bold text-[#3b5998] hover:underline">Configurar →</a>
                    </div>
                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Tiempo de Sesión</div>
                            <div class="settings-row-desc">Duración máxima de la sesión de usuario activa</div>
                        </div>
                        <span class="text-sm font-bold text-gray-600 font-mono">120 min</span>
                    </div>
                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Confirmación de Contraseña</div>
                            <div class="settings-row-desc">Requerida antes de acceder a áreas sensibles (Consola, VLANs)</div>
                        </div>
                        <span class="text-xs font-bold px-3 py-1 rounded-full bg-emerald-100 text-emerald-700">Activo</span>
                    </div>
                </div>

                <div class="settings-group">
                    <h4 class="settings-group-title">Control de Acceso (RBAC)</h4>
                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Admin</div>
                            <div class="settings-row-desc">Acceso total al sistema, incluyendo ajustes y consola</div>
                        </div>
                        <span class="text-xs font-bold px-2.5 py-1 rounded-full role-admin">Full Access</span>
                    </div>
                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Operador</div>
                            <div class="settings-row-desc">Dashboard y monitoreo, sin acceso a ajustes</div>
                        </div>
                        <span class="text-xs font-bold px-2.5 py-1 rounded-full role-operador">Read Only</span>
                    </div>
                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Auditor</div>
                            <div class="settings-row-desc">Acceso de solo lectura para auditorías y reportes</div>
                        </div>
                        <span class="text-xs font-bold px-2.5 py-1 rounded-full role-auditor">Audit Only</span>
                    </div>
                </div>
            </div>

            {{-- ========== SECTION: Notificaciones ========== --}}
            <div class="settings-section {{ $section === 'notificaciones' ? 'active' : '' }}" id="section-notificaciones">
                <h1 class="settings-section-title">Notificaciones</h1>
                <p class="settings-section-subtitle">Gestiona cómo recibes alertas y notificaciones del sistema.</p>

                <div class="settings-group">
                    <h4 class="settings-group-title">Canales de Alerta</h4>
                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Alertas por correo electrónico</div>
                            <div class="settings-row-desc">Envía un email al admin cuando un dispositivo pasa a estado "offline"</div>
                        </div>
                        <span class="text-xs font-bold px-3 py-1 rounded-full bg-gray-100 text-gray-500">Próximamente</span>
                    </div>
                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Webhook / Slack</div>
                            <div class="settings-row-desc">Integración con Slack o Microsoft Teams para alertas en tiempo real</div>
                        </div>
                        <span class="text-xs font-bold px-3 py-1 rounded-full bg-gray-100 text-gray-500">Próximamente</span>
                    </div>
                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Notificación en Dashboard</div>
                            <div class="settings-row-desc">Campana de alertas en la barra superior</div>
                        </div>
                        <span class="text-xs font-bold px-3 py-1 rounded-full bg-emerald-100 text-emerald-700">Activo</span>
                    </div>
                </div>
            </div>

            {{-- ========== SECTION: Apariencia ========== --}}
            <div class="settings-section {{ $section === 'apariencia' ? 'active' : '' }}" id="section-apariencia" x-data="appearanceManager()" x-init="init()">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <h1 class="settings-section-title">Apariencia del Sistema</h1>
                        <p class="settings-section-subtitle mb-0">Gestiona el tema visual y la ergonomía de la consola NOC.</p>
                    </div>

                    {{-- Dynamic Status Pill --}}
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold border transition"
                         :class="isDarkActive 
                            ? 'bg-slate-800/80 border-slate-700/60 text-blue-400' 
                            : 'bg-blue-50 border-blue-200 text-[#3b5998]'">
                        <span class="w-2 h-2 rounded-full" :class="isDarkActive ? 'bg-blue-400 shadow-[0_0_8px_rgba(96,165,250,0.8)]' : 'bg-[#3b5998]'"></span>
                        <span x-text="'Modo Activo: ' + (currentTheme === 'light' ? 'Claro (Diurno)' : (currentTheme === 'dark' ? 'Oscuro (NOC OLED)' : 'Automático (' + (isDarkActive ? 'Oscuro' : 'Claro') + ')'))"></span>
                    </div>
                </div>

                {{-- Tarjeta Principal: Selector de Tema de Visualización --}}
                <div class="settings-group mb-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                        <div>
                            <h4 class="settings-group-title mb-1">Tema de Visualización</h4>
                            <p class="text-xs text-gray-500 dark:text-slate-400">Selecciona el modo cromático preferido para la estación de trabajo.</p>
                        </div>

                        {{-- Segmented Quick Switcher --}}
                        <div class="inline-flex p-1 rounded-xl bg-gray-200/80 dark:bg-[#090b10] border border-gray-300/60 dark:border-slate-800/60 shadow-inner self-start sm:self-auto">
                            <button type="button" @click="selectTheme('light')"
                                    :class="currentTheme === 'light' ? 'bg-white dark:bg-slate-800 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white'"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                Claro
                            </button>
                            <button type="button" @click="selectTheme('dark')"
                                    :class="currentTheme === 'dark' ? 'bg-white dark:bg-slate-800 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white'"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                                Oscuro
                            </button>
                            <button type="button" @click="selectTheme('system')"
                                    :class="currentTheme === 'system' ? 'bg-white dark:bg-slate-800 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white'"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                Sistema
                            </button>
                        </div>
                    </div>

                    {{-- 3-Cards Grid Preview --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                        {{-- Card 1: Modo Claro --}}
                        <div @click="selectTheme('light')"
                             :class="currentTheme === 'light' 
                                ? 'border-[#3b5998] ring-2 ring-[#3b5998]/20 bg-blue-50/20 dark:bg-blue-950/20' 
                                : 'border-gray-200 dark:border-slate-800/60 bg-white dark:bg-[#090b10] hover:border-gray-300 dark:hover:border-slate-700'"
                             class="rounded-2xl border p-4 cursor-pointer transition relative flex flex-col justify-between group">
                            
                            {{-- Checkmark indicator --}}
                            <div class="absolute top-3 right-3">
                                <div class="w-5 h-5 rounded-full flex items-center justify-center transition"
                                     :class="currentTheme === 'light' ? 'bg-[#3b5998] text-white' : 'border border-gray-300 dark:border-slate-700 text-transparent'">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                            </div>

                            <div>
                                {{-- Mini UI Preview Mockup (Light) --}}
                                <div class="rounded-xl bg-[#f1f5f9] p-2.5 border border-gray-200 mb-3 shadow-sm">
                                    <div class="flex items-center gap-1.5 pb-2 mb-2 border-b border-gray-200">
                                        <div class="w-2.5 h-2.5 rounded-full bg-red-400"></div>
                                        <div class="w-2.5 h-2.5 rounded-full bg-amber-400"></div>
                                        <div class="w-2.5 h-2.5 rounded-full bg-emerald-400"></div>
                                        <div class="w-16 h-2 rounded-full bg-gray-300 ml-auto"></div>
                                    </div>
                                    <div class="space-y-1.5">
                                        <div class="flex gap-1.5">
                                            <div class="w-6 h-10 rounded bg-[#3b5998] flex-shrink-0"></div>
                                            <div class="flex-1 space-y-1">
                                                <div class="h-4 bg-white rounded border border-gray-200"></div>
                                                <div class="grid grid-cols-2 gap-1">
                                                    <div class="h-5 bg-white rounded border border-gray-200"></div>
                                                    <div class="h-5 bg-white rounded border border-gray-200"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 mb-1">
                                    <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                    <h5 class="font-extrabold text-sm text-gray-900 dark:text-white">Claro / Diurno</h5>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-slate-400 leading-relaxed">
                                    Fondos blancos limpios de alto contraste, ideal para oficinas iluminadas y turnos diurnos.
                                </p>
                            </div>

                            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-slate-800/40 flex items-center justify-between">
                                <span class="text-[10px] font-mono font-bold text-gray-400">#FFFFFF / #F1F5F9</span>
                                <span class="text-[11px] font-bold text-[#3b5998]" x-show="currentTheme === 'light'">Seleccionado</span>
                            </div>
                        </div>

                        {{-- Card 2: Modo Oscuro (NOC OLED) --}}
                        <div @click="selectTheme('dark')"
                             :class="currentTheme === 'dark' 
                                ? 'border-blue-500 ring-2 ring-blue-500/20 bg-blue-950/20' 
                                : 'border-gray-200 dark:border-slate-800/60 bg-white dark:bg-[#090b10] hover:border-gray-300 dark:hover:border-slate-700'"
                             class="rounded-2xl border p-4 cursor-pointer transition relative flex flex-col justify-between group">
                            
                            {{-- Checkmark indicator --}}
                            <div class="absolute top-3 right-3">
                                <div class="w-5 h-5 rounded-full flex items-center justify-center transition"
                                     :class="currentTheme === 'dark' ? 'bg-blue-600 text-white' : 'border border-gray-300 dark:border-slate-700 text-transparent'">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                            </div>

                            <div>
                                {{-- Mini UI Preview Mockup (Dark OLED) --}}
                                <div class="rounded-xl bg-[#07090e] p-2.5 border border-slate-800/80 mb-3 shadow-sm">
                                    <div class="flex items-center gap-1.5 pb-2 mb-2 border-b border-slate-800/60">
                                        <div class="w-2.5 h-2.5 rounded-full bg-red-500/80"></div>
                                        <div class="w-2.5 h-2.5 rounded-full bg-amber-500/80"></div>
                                        <div class="w-2.5 h-2.5 rounded-full bg-emerald-500/80"></div>
                                        <div class="w-16 h-2 rounded-full bg-slate-800 ml-auto"></div>
                                    </div>
                                    <div class="space-y-1.5">
                                        <div class="flex gap-1.5">
                                            <div class="w-6 h-10 rounded bg-[#090b10] border border-slate-800 flex-shrink-0"></div>
                                            <div class="flex-1 space-y-1">
                                                <div class="h-4 bg-[#0d1017] rounded border border-slate-800/60"></div>
                                                <div class="grid grid-cols-2 gap-1">
                                                    <div class="h-5 bg-[#0d1017] rounded border border-slate-800/60"></div>
                                                    <div class="h-5 bg-[#0d1017] rounded border border-slate-800/60"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 mb-1">
                                    <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                                    <h5 class="font-extrabold text-sm text-gray-900 dark:text-white">Oscuro / NOC OLED</h5>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-slate-400 leading-relaxed">
                                    Consola de operaciones nocturnas con negros profundos, baja fatiga ocular y estética mate.
                                </p>
                            </div>

                            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-slate-800/40 flex items-center justify-between">
                                <span class="text-[10px] font-mono font-bold text-gray-400">#07090E / #0D1017</span>
                                <span class="text-[11px] font-bold text-blue-400" x-show="currentTheme === 'dark'">Seleccionado</span>
                            </div>
                        </div>

                        {{-- Card 3: Modo Automático / Sistema --}}
                        <div @click="selectTheme('system')"
                             :class="currentTheme === 'system' 
                                ? 'border-indigo-500 ring-2 ring-indigo-500/20 bg-indigo-50/20 dark:bg-indigo-950/20' 
                                : 'border-gray-200 dark:border-slate-800/60 bg-white dark:bg-[#090b10] hover:border-gray-300 dark:hover:border-slate-700'"
                             class="rounded-2xl border p-4 cursor-pointer transition relative flex flex-col justify-between group">
                            
                            {{-- Checkmark indicator --}}
                            <div class="absolute top-3 right-3">
                                <div class="w-5 h-5 rounded-full flex items-center justify-center transition"
                                     :class="currentTheme === 'system' ? 'bg-indigo-600 text-white' : 'border border-gray-300 dark:border-slate-700 text-transparent'">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                            </div>

                            <div>
                                {{-- Mini UI Preview Mockup (Split Light/Dark) --}}
                                <div class="rounded-xl overflow-hidden border border-gray-200 dark:border-slate-800/80 mb-3 shadow-sm flex h-[62px]">
                                    {{-- Light half --}}
                                    <div class="w-1/2 bg-[#f1f5f9] p-2 border-r border-gray-300 dark:border-slate-700 flex flex-col justify-between">
                                        <div class="w-10 h-2 rounded bg-gray-300"></div>
                                        <div class="h-4 bg-white rounded border border-gray-200"></div>
                                    </div>
                                    {{-- Dark half --}}
                                    <div class="w-1/2 bg-[#07090e] p-2 flex flex-col justify-between">
                                        <div class="w-10 h-2 rounded bg-slate-800 ml-auto"></div>
                                        <div class="h-4 bg-[#0d1017] rounded border border-slate-800/60"></div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 mb-1">
                                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                    <h5 class="font-extrabold text-sm text-gray-900 dark:text-white">Automático (Sistema)</h5>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-slate-400 leading-relaxed">
                                    Sincroniza en tiempo real con las preferencias de color del sistema operativo del usuario.
                                </p>
                            </div>

                            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-slate-800/40 flex items-center justify-between">
                                <span class="text-[10px] font-mono font-bold text-gray-400">matchMedia (OS)</span>
                                <span class="text-[11px] font-bold text-indigo-400" x-show="currentTheme === 'system'">Seleccionado</span>
                            </div>
                        </div>
                    </div>

                    {{-- Live Reactivity Notice --}}
                    <div class="mt-5 p-3.5 rounded-xl bg-gray-50 dark:bg-[#07090e] border border-gray-200/80 dark:border-slate-800/60 flex items-center gap-3">
                        <div class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-[#3b5998] dark:text-blue-400 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <p class="text-xs text-gray-600 dark:text-slate-400 leading-tight">
                            La selección se almacena en tu navegador (<code class="font-mono text-[11px] text-blue-600 dark:text-blue-400">localStorage.theme</code>) y sincroniza inmediatamente todas las pantallas, gráficos y consolas activas sin necesidad de recargar.
                        </p>
                    </div>
                </div>

                {{-- Paleta de Colores Institucional --}}
                <div class="settings-group">
                    <h4 class="settings-group-title">Paleta de Colores Institucional NOC</h4>
                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Color Primario (Azul Institucional)</div>
                            <div class="settings-row-desc">Utilizado en barra lateral, botones primarios y encabezados clave</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg shadow-inner border border-gray-200 dark:border-slate-700" style="background: #3b5998;"></div>
                            <span class="text-xs font-mono font-bold text-gray-500 dark:text-slate-400">#3b5998</span>
                        </div>
                    </div>
                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Color Secundario (Slate Blue)</div>
                            <div class="settings-row-desc">Utilizado en elementos de soporte, insignias y subtítulos</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg shadow-inner border border-gray-200 dark:border-slate-700" style="background: #5c8096;"></div>
                            <span class="text-xs font-mono font-bold text-gray-500 dark:text-slate-400">#5c8096</span>
                        </div>
                    </div>
                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Color de Acento (Naranja NOC)</div>
                            <div class="settings-row-desc">Utilizado en alertas críticas, botones de acción y destacados de tráfico</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg shadow-inner border border-gray-200 dark:border-slate-700" style="background: #f26419;"></div>
                            <span class="text-xs font-mono font-bold text-gray-500 dark:text-slate-400">#f26419</span>
                        </div>
                    </div>
                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Fondo Consola OLED (Dark Canvas)</div>
                            <div class="settings-row-desc">Fondo profundo anti-reflejos para paneles de alta disponibilidad</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg shadow-inner border border-gray-200 dark:border-slate-700" style="background: #07090e;"></div>
                            <span class="text-xs font-mono font-bold text-gray-500 dark:text-slate-400">#07090e</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ========== SECTION: Acerca de ========== --}}
            <div class="settings-section {{ $section === 'acerca' ? 'active' : '' }}" id="section-acerca">
                <h1 class="settings-section-title">Acerca de</h1>
                <p class="settings-section-subtitle">Información del sistema de monitoreo de red.</p>

                <div class="settings-group">
                    <h4 class="settings-group-title">Información del Sistema</h4>
                    <div class="settings-row">
                        <div class="settings-row-label">Nombre del Proyecto</div>
                        <span class="text-sm font-bold text-gray-700">Network Monitor NOC</span>
                    </div>
                    <div class="settings-row">
                        <div class="settings-row-label">Versión</div>
                        <span class="text-sm font-bold font-mono text-gray-700">v2.4.0</span>
                    </div>
                    <div class="settings-row">
                        <div class="settings-row-label">Framework</div>
                        <span class="text-sm font-bold text-gray-700">Laravel {{ app()->version() }}</span>
                    </div>
                    <div class="settings-row">
                        <div class="settings-row-label">PHP</div>
                        <span class="text-sm font-bold font-mono text-gray-700">{{ phpversion() }}</span>
                    </div>
                    <div class="settings-row">
                        <div class="settings-row-label">Workers Activos</div>
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-sm font-bold text-gray-700">snmp_poller.py, snmp_detail_collector.py</span>
                        </div>
                    </div>
                </div>

                <div class="settings-group">
                    <h4 class="settings-group-title">Stack Tecnológico</h4>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach(['Laravel', 'Tailwind CSS', 'Chart.js', 'PySNMP', 'Paramiko', 'MySQL', 'Vite'] as $tech)
                            <span class="px-3 py-1.5 rounded-lg bg-white border border-gray-200 text-xs font-bold text-gray-600 shadow-sm">{{ $tech }}</span>
                        @endforeach
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection

@section('scripts')
    <script>
        function appearanceManager() {
            return {
                currentTheme: window.getCurrentThemePreference ? window.getCurrentThemePreference() : (localStorage.getItem('theme') || 'system'),
                isDarkActive: document.documentElement.classList.contains('dark'),
                init() {
                    window.addEventListener('theme-changed', (e) => {
                        this.currentTheme = e.detail.theme;
                        this.isDarkActive = e.detail.isDark;
                    });
                },
                selectTheme(theme) {
                    this.currentTheme = theme;
                    if (window.setTheme) {
                        this.isDarkActive = window.setTheme(theme);
                    } else {
                        localStorage.setItem('theme', theme);
                        let isDark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                        if (isDark) {
                            document.documentElement.classList.add('dark');
                        } else {
                            document.documentElement.classList.remove('dark');
                        }
                        this.isDarkActive = isDark;
                    }
                }
            };
        }

        document.addEventListener('DOMContentLoaded', function() {
            const navItems = document.querySelectorAll('.settings-nav-item[data-section]');
            const sections = document.querySelectorAll('.settings-section');

            navItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = this.dataset.section;

                    // Update nav active state
                    navItems.forEach(n => n.classList.remove('active'));
                    this.classList.add('active');

                    // Show target section
                    sections.forEach(s => s.classList.remove('active'));
                    const targetSection = document.getElementById('section-' + target);
                    if (targetSection) {
                        targetSection.classList.add('active');
                    }

                    // Update URL without reload (for bookmarking)
                    const url = new URL(window.location);
                    url.searchParams.set('section', target);
                    history.replaceState(null, '', url);
                });
            });

            // Search filter (nav items)
            const searchInput = document.getElementById('settingsSearch');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const query = this.value.toLowerCase().trim();
                    navItems.forEach(item => {
                        const text = item.textContent.toLowerCase();
                        item.style.display = text.includes(query) ? '' : 'none';
                    });
                });
            }
        });

        // Avatar preview on file select (registration)
        function previewAvatar(input) {
            const preview = document.getElementById('avatarPreviewImg');
            const icon = document.getElementById('avatarPlaceholderIcon');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    icon.classList.add('hidden');
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // ==================== USER CRUD MODALS ====================
        let currentViewingUserId = null;

        function openViewModal(id) {
            const user = window.__users && window.__users[id];
            if (!user) return;
            currentViewingUserId = id;

            const modal = document.getElementById('viewModal');
            const panel = document.getElementById('viewModalPanel');
            const avatarImg = document.getElementById('viewAvatar');
            const fullName = document.getElementById('viewFullName');
            const roleBadge = document.getElementById('viewRoleBadge');
            const email = document.getElementById('viewEmail');
            const prefijo = document.getElementById('viewPrefijo');
            const role = document.getElementById('viewRole');
            const created = document.getElementById('viewCreated');

            // Avatar
            if (user.avatar) {
                avatarImg.src = user.avatar;
            } else {
                const bg = (user.role.toLowerCase() === 'admin') ? '3b5998' : ((user.role.toLowerCase() === 'operador') ? '16a34a' : 'f26419');
                avatarImg.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=${bg}&color=fff&size=160`;
            }

            // Name with prefix
            fullName.textContent = (user.prefijo ? user.prefijo + ' ' : '') + user.name;

            // Role badge class & label
            roleBadge.textContent = user.role;
            roleBadge.className = 'text-xs font-bold px-3 py-1 rounded-full mt-1 ' + (
                user.role.toLowerCase() === 'admin' ? 'role-admin' :
                user.role.toLowerCase() === 'operador' ? 'role-operador' : 'role-auditor'
            );

            email.textContent = user.email;
            prefijo.textContent = user.prefijo ? user.prefijo : '— Sin prefijo —';
            role.textContent = user.role;
            created.textContent = user.created;

            modal.classList.remove('hidden');
            requestAnimationFrame(() => {
                panel.classList.remove('translate-x-full');
            });
        }

        function closeViewModal() {
            const modal = document.getElementById('viewModal');
            const panel = document.getElementById('viewModalPanel');
            if (!modal || !panel) return;

            panel.classList.add('translate-x-full');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        function editFromViewModal() {
            if (currentViewingUserId) {
                const idToEdit = currentViewingUserId;
                closeViewModal();
                setTimeout(() => {
                    openEditModal(idToEdit);
                }, 310);
            }
        }

        function openEditModal(id) {
            const user = window.__users && window.__users[id];
            if (!user) return;

            const modal = document.getElementById('editModal');
            const panel = document.getElementById('editModalPanel');
            const form = document.getElementById('editUserForm');
            const nameInput = document.getElementById('editName');
            const prefijoSelect = document.getElementById('editPrefijo');
            const emailInput = document.getElementById('editEmail');
            const roleSelect = document.getElementById('editRole');
            const avatarPreview = document.getElementById('editAvatarPreview');
            const removeAvatarCheckbox = document.getElementById('editRemoveAvatar');
            const fileInput = document.getElementById('editAvatarUpload');

            // Reset file upload & checkbox
            if (fileInput) fileInput.value = '';
            if (removeAvatarCheckbox) removeAvatarCheckbox.checked = false;

            // Reset password fields
            const pwdInput = form.querySelector('input[name="password"]');
            const pwdConfirmInput = form.querySelector('input[name="password_confirmation"]');
            if (pwdInput) pwdInput.value = '';
            if (pwdConfirmInput) pwdConfirmInput.value = '';

            // Set dynamic action URL
            form.action = `/settings/user/${user.id}`;

            // Populate form values
            nameInput.value = user.name;
            prefijoSelect.value = user.prefijo || '';
            emailInput.value = user.email;
            roleSelect.value = user.role;

            // Avatar Preview
            if (user.avatar) {
                avatarPreview.src = user.avatar;
            } else {
                const bg = (user.role.toLowerCase() === 'admin') ? '3b5998' : ((user.role.toLowerCase() === 'operador') ? '16a34a' : 'f26419');
                avatarPreview.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=${bg}&color=fff&size=128`;
            }

            modal.classList.remove('hidden');
            requestAnimationFrame(() => {
                panel.classList.remove('translate-x-full');
            });
        }

        function closeEditModal() {
            const modal = document.getElementById('editModal');
            const panel = document.getElementById('editModalPanel');
            if (!modal || !panel) return;

            panel.classList.add('translate-x-full');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        function previewEditAvatar(input) {
            const preview = document.getElementById('editAvatarPreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    // Uncheck remove avatar if new image is picked
                    const removeCb = document.getElementById('editRemoveAvatar');
                    if (removeCb) removeCb.checked = false;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Close modals on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeViewModal();
                closeEditModal();
            }
        });
    </script>
@endsection
