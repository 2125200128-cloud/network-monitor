@extends('layouts.app')

@section('styles')
    <style>
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
            width: 290px;
            min-width: 290px;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            border-right: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        .settings-nav-header {
            padding: 1.75rem 1.25rem 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .settings-nav-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.25rem;
            gap: 0.85rem;
            cursor: pointer;
            color: #475569;
            font-size: 0.875rem;
            font-weight: 600;
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
            margin: 0.5rem 1.25rem;
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
            letter-spacing: -0.02em;
        }
        .settings-section-subtitle {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }
        .settings-group {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 1.25rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.2s ease;
        }
        .settings-group-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 800;
            color: #94a3b8;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .settings-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.95rem 0;
            border-bottom: 1px solid #f1f5f9;
            transition: background-color 0.15s;
        }
        .settings-row:last-child {
            border-bottom: none;
        }
        .settings-row-label {
            font-size: 0.9rem;
            font-weight: 700;
            color: #334155;
        }
        .settings-row-desc {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 0.2rem;
        }
        .settings-input {
            width: 200px;
            padding: 0.55rem 0.85rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            background: white;
            color: #1e293b;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .settings-input:focus {
            outline: none;
            border-color: #3b5998;
            box-shadow: 0 0 0 3px rgba(59, 89, 152, 0.15);
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
            padding: 0.85rem 1rem;
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
            border-radius: 1.25rem;
            padding: 1.75rem;
        }
        .register-card .form-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #475569;
            margin-bottom: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        .register-card .form-input {
            width: 100%;
            padding: 0.6rem 0.85rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            background: white;
            color: #1e293b;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .register-card .form-input:focus {
            outline: none;
            border-color: #3b5998;
            box-shadow: 0 0 0 3px rgba(59, 89, 152, 0.15);
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
            border-bottom-color: rgba(51, 65, 85, 0.3) !important;
        }
        .dark .settings-row-label {
            color: #e2e8f0 !important;
        }
        .dark .settings-row-desc {
            color: #64748b !important;
        }
        .dark .settings-input {
            background: #090b10 !important;
            border-color: rgba(51, 65, 85, 0.6) !important;
            color: #f1f5f9 !important;
        }
        .dark .settings-input:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2) !important;
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
            border-color: rgba(51, 65, 85, 0.6) !important;
            color: #f1f5f9 !important;
        }
        .dark .user-table thead th {
            background: #0d1017 !important;
            border-bottom-color: rgba(51, 65, 85, 0.4) !important;
            color: #64748b !important;
        }
        .dark .user-table tbody td {
            color: #cbd5e1 !important;
            border-bottom-color: rgba(51, 65, 85, 0.3) !important;
        }
        .dark .user-table tbody tr:hover {
            background: rgba(30, 41, 59, 0.3) !important;
        }
        .dark .role-admin { background: rgba(30, 64, 175, 0.3) !important; color: #93c5fd !important; }
        .dark .role-operador { background: rgba(22, 101, 52, 0.3) !important; color: #86efac !important; }
        .dark .role-auditor { background: rgba(146, 64, 14, 0.3) !important; color: #fde68a !important; }

        .search-highlight {
            background-color: rgba(251, 191, 36, 0.35);
            padding: 1px 3px;
            border-radius: 4px;
        }
    </style>
@endsection

@section('content')
<div class="h-full flex flex-col max-w-7xl mx-auto">

    <div class="settings-container flex-1">

        {{-- ==================== LEFT NAV PANEL ==================== --}}
        <nav class="settings-nav custom-scrollbar">
            <div class="settings-nav-header">
                <div class="flex items-center gap-3 mb-3.5">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-[#3b5998] to-[#5c8096] flex items-center justify-center text-white shadow-md shadow-[#3b5998]/20">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </div>
                    <div>
                        <h2 class="text-base font-black text-slate-900 leading-tight">Configuración</h2>
                        <p class="text-xs text-slate-400 font-medium">Ajustes del sistema NOC</p>
                    </div>
                </div>

                {{-- Live Search Filter with Real Filtering --}}
                <div class="relative">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="text" placeholder="Buscar en todos los ajustes..." class="w-full pl-9 pr-7 py-2 bg-white dark:bg-[#0d1017] border border-slate-200 dark:border-slate-700/80 rounded-xl text-xs text-slate-800 dark:text-white font-medium focus:outline-none focus:ring-2 focus:ring-[#3b5998] focus:border-[#3b5998] transition shadow-xs" id="settingsSearch">
                    <button type="button" id="clearSettingsSearch" class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            </div>

            {{-- Navigation Items with Specific SVG Icons --}}
            <div class="py-2.5 space-y-0.5" id="navItemsList">
                <a href="#" class="settings-nav-item {{ $section === 'general' ? 'active' : '' }}" data-section="general">
                    <div class="w-7 h-7 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <span>Motor SNMP</span>
                    <span class="nav-match-count hidden ml-auto text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-100 text-[#3b5998] dark:bg-blue-900 dark:text-blue-300"></span>
                </a>

                <a href="#" class="settings-nav-item {{ $section === 'cuentas' ? 'active' : '' }}" data-section="cuentas">
                    <div class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </div>
                    <span>Cuentas</span>
                    <span class="nav-badge">{{ $users->count() }}</span>
                </a>

                <div class="nav-separator"></div>

                <a href="#" class="settings-nav-item {{ $section === 'seguridad' ? 'active' : '' }}" data-section="seguridad">
                    <div class="w-7 h-7 rounded-lg bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <span>Seguridad</span>
                    <span class="nav-match-count hidden ml-auto text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-100 text-[#3b5998] dark:bg-blue-900 dark:text-blue-300"></span>
                </a>

                <a href="#" class="settings-nav-item {{ $section === 'notificaciones' ? 'active' : '' }}" data-section="notificaciones">
                    <div class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    </div>
                    <span>Notificaciones</span>
                    <span class="nav-match-count hidden ml-auto text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-100 text-[#3b5998] dark:bg-blue-900 dark:text-blue-300"></span>
                </a>

                <div class="nav-separator"></div>

                <a href="#" class="settings-nav-item {{ $section === 'apariencia' ? 'active' : '' }}" data-section="apariencia">
                    <div class="w-7 h-7 rounded-lg bg-pink-500/10 text-pink-600 dark:text-pink-400 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path></svg>
                    </div>
                    <span>Apariencia</span>
                    <span class="nav-match-count hidden ml-auto text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-100 text-[#3b5998] dark:bg-blue-900 dark:text-blue-300"></span>
                </a>

                <a href="#" class="settings-nav-item {{ $section === 'acerca' ? 'active' : '' }}" data-section="acerca">
                    <div class="w-7 h-7 rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <span>Acerca de</span>
                    <span class="nav-match-count hidden ml-auto text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-100 text-[#3b5998] dark:bg-blue-900 dark:text-blue-300"></span>
                </a>
            </div>

            {{-- User Session Footer --}}
            <div class="mt-auto p-4 border-t border-slate-200 dark:border-slate-800">
                <div class="flex items-center gap-3 p-1.5 mb-2.5">
                    @if(auth()->user()->avatar)
                        <img src="{{ asset('storage/' . auth()->user()->avatar) }}" alt="{{ auth()->user()->name }}" class="w-9 h-9 rounded-full object-cover border-2 border-[#3b5998]/30 flex-shrink-0">
                    @else
                        <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'Admin') }}&background=3b5998&color=fff&size=64" alt="{{ auth()->user()->name }}" class="w-9 h-9 rounded-full border-2 border-[#3b5998]/30 flex-shrink-0">
                    @endif
                    <div class="overflow-hidden">
                        <p class="text-xs font-bold text-slate-800 dark:text-slate-100 leading-tight truncate">
                            @if(auth()->user()->prefijo)<span class="text-[#3b5998] dark:text-blue-400">{{ auth()->user()->prefijo }}</span> @endif{{ auth()->user()->name ?? 'Administrador' }}
                        </p>
                        <p class="text-[10px] text-slate-400 font-medium uppercase">{{ auth()->user()->role ?? 'Admin' }}</p>
                    </div>
                </div>

                {{-- Botón Cerrar Sesión --}}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-xl bg-red-50 dark:bg-red-950/40 hover:bg-red-100 dark:hover:bg-red-900/50 text-red-600 dark:text-red-400 text-xs font-bold transition border border-red-200/80 dark:border-red-800/40 shadow-xs" title="Cerrar Sesión">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        <span>Cerrar Sesión</span>
                    </button>
                </form>
            </div>
        </nav>

        {{-- ==================== RIGHT CONTENT PANEL ==================== --}}
        <div class="settings-content custom-scrollbar" id="settingsContentArea">
            
            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="mb-6 p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-300 text-sm font-semibold flex items-center gap-3 shadow-xs">
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <span>{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="mb-6 p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-700 dark:text-rose-300 text-sm font-semibold flex items-center gap-3 shadow-xs">
                    <div class="w-8 h-8 rounded-xl bg-rose-500/20 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <span>{{ session('error') }}</span>
                </div>
            @endif
            @if($errors->any())
                <div class="mb-6 p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-700 dark:text-rose-300 text-sm font-semibold shadow-xs">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- No Results Banner (when search active) --}}
            <div id="noSearchResults" class="hidden p-8 rounded-2xl bg-slate-50 dark:bg-[#0d1017] border border-slate-200 dark:border-slate-800 text-center">
                <div class="w-12 h-12 rounded-2xl bg-slate-200 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <h3 class="text-sm font-bold text-slate-800 dark:text-white">No se encontraron ajustes</h3>
                <p class="text-xs text-slate-400 mt-1">Intenta con otros términos como "SNMP", "CPU", "2FA", "Tema", o "Contraseña".</p>
            </div>

            {{-- ========== SECTION: General / Motor SNMP ========== --}}
            <div class="settings-section {{ $section === 'general' ? 'active' : '' }}" id="section-general" data-section-name="Motor SNMP">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-9 h-9 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <h1 class="settings-section-title mb-0">Motor de Sondeo SNMP</h1>
                </div>
                <p class="settings-section-subtitle">Configura los parámetros del motor de sondeo, comunidad SNMP y umbrales de alerta.</p>

                <form action="{{ route('settings.update') }}" method="POST">
                    @csrf

                    <div class="settings-group">
                        <h4 class="settings-group-title">
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                            Parámetros del Worker Python
                        </h4>
                        
                        {{-- Row: Intervalo --}}
                        <div class="settings-row">
                            <div class="flex items-center gap-3.5">
                                <div class="w-9 h-9 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <div>
                                    <div class="settings-row-label">Intervalo de Sondeo</div>
                                    <div class="settings-row-desc">Tiempo entre ciclos de escaneo del worker Python</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="number" name="intervalo_sondeo_segundos" value="{{ $settings->intervalo_sondeo_segundos }}" min="10" class="settings-input text-right font-mono font-bold" style="width: 120px;">
                                <span class="text-xs font-bold text-slate-400">seg</span>
                            </div>
                        </div>

                        {{-- Row: Comunidad SNMP --}}
                        <div class="settings-row">
                            <div class="flex items-center gap-3.5">
                                <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                                </div>
                                <div>
                                    <div class="settings-row-label">Comunidad SNMP</div>
                                    <div class="settings-row-desc">Cadena de lectura por defecto (ro community)</div>
                                </div>
                            </div>
                            <input type="text" name="comunidad_snmp_default" value="{{ $settings->comunidad_snmp_default }}" class="settings-input font-mono font-bold" style="width: 180px;">
                        </div>

                        {{-- Row: IP Switch Core --}}
                        <div class="settings-row">
                            <div class="flex items-center gap-3.5">
                                <div class="w-9 h-9 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                </div>
                                <div>
                                    <div class="settings-row-label">IP del Switch Core</div>
                                    <div class="settings-row-desc">IP del equipo centralizado para el rastreo de CDP / Topología</div>
                                </div>
                            </div>
                            <input type="text" name="ip_switch_core" value="{{ $settings->ip_switch_core }}" class="settings-input font-mono font-bold" placeholder="10.4.254.3" style="width: 180px;">
                        </div>
                    </div>

                    <div class="settings-group">
                        <h4 class="settings-group-title">
                            <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            Umbrales de Alerta de Rendimiento
                        </h4>
                        
                        {{-- Row: CPU Warning --}}
                        <div class="settings-row">
                            <div class="flex items-center gap-3.5">
                                <div class="w-9 h-9 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path></svg>
                                </div>
                                <div>
                                    <div class="settings-row-label">Warning CPU</div>
                                    <div class="settings-row-desc">Marca el dispositivo en estado de advertencia si el uso supera este valor</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="number" name="umbral_cpu_warning" value="{{ $settings->umbral_cpu_warning }}" min="1" max="100" class="settings-input text-right font-mono font-bold" style="width: 100px;">
                                <span class="text-xs font-bold text-slate-400">%</span>
                            </div>
                        </div>

                        {{-- Row: Packet Loss Warning --}}
                        <div class="settings-row">
                            <div class="flex items-center gap-3.5">
                                <div class="w-9 h-9 rounded-xl bg-orange-500/10 text-orange-600 dark:text-orange-400 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                                </div>
                                <div>
                                    <div class="settings-row-label">Warning Packet Loss</div>
                                    <div class="settings-row-desc">Marca como advertencia si la pérdida de paquetes ICMP supera este umbral</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="number" name="umbral_loss_warning" value="{{ $settings->umbral_loss_warning }}" min="1" max="100" class="settings-input text-right font-mono font-bold" style="width: 100px;">
                                <span class="text-xs font-bold text-slate-400">%</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end mt-5">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#3b5998] to-[#5c8096] hover:from-[#2e4679] hover:to-[#4a6b80] text-white text-xs font-bold shadow-md shadow-[#3b5998]/20 transition flex items-center gap-2 active:scale-95">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                            <span>Guardar Parámetros</span>
                        </button>
                    </div>
                </form>
            </div>

            {{-- ========== SECTION: Cuentas ========== --}}
            <div class="settings-section {{ $section === 'cuentas' ? 'active' : '' }}" id="section-cuentas" data-section-name="Cuentas">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </div>
                    <h1 class="settings-section-title mb-0">Cuentas de Usuario</h1>
                </div>
                <p class="settings-section-subtitle">Administra los operadores del sistema. Registra nuevas cuentas y gestiona roles de acceso.</p>

                {{-- Current User Session & Logout Card --}}
                <div class="bg-gradient-to-r from-blue-50/70 to-indigo-50/30 dark:from-[#090b10] dark:to-[#0d1017] rounded-2xl p-4 sm:p-5 mb-6 border border-blue-100 dark:border-slate-800/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm dark:shadow-none">
                    <div class="flex items-center gap-3.5">
                        <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" class="w-12 h-12 rounded-full object-cover border-2 border-[#3b5998]/30 shadow-sm flex-shrink-0" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'Admin') }}&background=3b5998&color=fff&size=80';">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">
                                    @if(auth()->user()->prefijo)<span class="text-[#3b5998] dark:text-blue-400">{{ auth()->user()->prefijo }}</span> @endif{{ auth()->user()->name }}
                                </h3>
                                <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-blue-100 dark:bg-blue-950/60 text-[#3b5998] dark:text-blue-400 uppercase tracking-wide">{{ auth()->user()->role }}</span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5">{{ auth()->user()->email }} · <span class="text-emerald-600 dark:text-emerald-400 font-bold">● Sesión Activa</span></p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-red-50 dark:bg-red-950/40 hover:bg-red-100 dark:hover:bg-red-900/50 text-red-600 dark:text-red-400 text-xs font-bold transition border border-red-200 dark:border-red-800/40 shadow-xs">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                            Cerrar Sesión
                        </button>
                    </form>
                </div>

                {{-- Register New Account Card --}}
                <div class="register-card mb-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-9 h-9 rounded-xl bg-[#f26419] flex items-center justify-center text-white shadow-md shadow-orange-500/20">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-extrabold text-slate-800 dark:text-white">Registrar Nueva Cuenta</h3>
                            <p class="text-xs text-slate-400">Completa los datos para crear un nuevo operador</p>
                        </div>
                    </div>

                    <form action="{{ route('settings.register') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        {{-- Row 1: Avatar + Name + Prefijo + Email --}}
                        <div class="flex flex-col md:flex-row gap-4 mb-4">
                            {{-- Avatar Upload --}}
                            <div class="flex flex-col items-center justify-center" style="min-width: 110px;">
                                <label class="form-label text-center mb-1.5">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path></svg>
                                    Foto <span class="text-slate-400 font-normal">(opc.)</span>
                                </label>
                                <label for="avatarUpload" class="cursor-pointer group relative">
                                    <div class="w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 border-2 border-dashed border-slate-300 dark:border-slate-700 group-hover:border-[#3b5998] flex items-center justify-center overflow-hidden transition" id="avatarPreviewContainer">
                                        <svg id="avatarPlaceholderIcon" class="w-6 h-6 text-slate-300 dark:text-slate-600 group-hover:text-[#3b5998] transition" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"></path></svg>
                                        <img id="avatarPreviewImg" src="" alt="Preview" class="w-full h-full object-cover hidden">
                                    </div>
                                    <input type="file" name="avatar" id="avatarUpload" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="previewAvatar(this)">
                                </label>
                                <p class="text-[9px] text-slate-400 mt-1 text-center">JPG/PNG · 2MB</p>
                            </div>

                            {{-- Name + Prefijo + Email --}}
                            <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="form-label">
                                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                        Nombre completo
                                    </label>
                                    <input type="text" name="name" required placeholder="Ej. Juan Pérez" class="form-input" value="{{ old('name') }}">
                                </div>
                                <div>
                                    <label class="form-label">
                                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                                        Prefijo / Título
                                    </label>
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
                                    <label class="form-label">
                                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                        Correo electrónico
                                    </label>
                                    <input type="email" name="email" required placeholder="usuario@dominio.com" class="form-input" value="{{ old('email') }}">
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
                            <div>
                                <label class="form-label">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                    Contraseña
                                </label>
                                <input type="password" name="password" required placeholder="Mín. 8 caracteres" class="form-input">
                            </div>
                            <div>
                                <label class="form-label">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                    Confirmar Contraseña
                                </label>
                                <input type="password" name="password_confirmation" required placeholder="Repetir contraseña" class="form-input">
                            </div>
                            <div>
                                <label class="form-label">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                    Rol de acceso
                                </label>
                                <select name="role" class="form-input">
                                    <option value="Operador">Operador</option>
                                    <option value="Admin">Administrador</option>
                                    <option value="Auditor">Auditor</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#f26419] to-amber-600 hover:from-orange-600 hover:to-amber-700 text-white text-xs font-bold shadow-md shadow-orange-500/20 transition flex items-center gap-2 active:scale-95">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                                <span>Crear Cuenta</span>
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Existing Users Table --}}
                <div class="settings-group" style="padding: 0; overflow: hidden;">
                    <div class="px-5 py-3.5 flex items-center justify-between border-b border-slate-200 dark:border-slate-800/50 bg-[#f8fafc] dark:bg-[#090b10]">
                        <h4 class="text-xs uppercase tracking-wider font-extrabold text-slate-400 dark:text-slate-500 flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            Usuarios Registrados
                        </h4>
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
                                        <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="w-8 h-8 rounded-full flex-shrink-0 object-cover border border-slate-200 dark:border-slate-700" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background={{ strtolower($user->role) === 'admin' ? '3b5998' : (strtolower($user->role) === 'operador' ? '16a34a' : 'f26419') }}&color=fff&size=64';">
                                        <div>
                                            <p class="font-bold text-slate-800 dark:text-white text-sm">
                                                @if($user->prefijo)<span class="text-[#3b5998] dark:text-blue-400">{{ $user->prefijo }}</span> @endif{{ $user->name }}
                                            </p>
                                            @if($user->id === auth()->id())
                                                <p class="text-[10px] text-blue-500 font-bold">Tú (Sesión actual)</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="font-mono text-slate-500 dark:text-slate-400 text-xs">{{ $user->email }}</td>
                                <td>
                                    <span class="text-xs font-bold rounded-lg py-1 px-2.5 inline-block {{ strtolower($user->role) === 'admin' ? 'role-admin' : (strtolower($user->role) === 'operador' ? 'role-operador' : 'role-auditor') }}">
                                        {{ $user->role }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center justify-center gap-1.5">
                                        {{-- Ver --}}
                                        <button type="button" onclick="openViewModal({{ $user->id }})" class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-blue-50 dark:bg-blue-950/50 text-[#3b5998] dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/50 transition border border-transparent dark:border-blue-800/40" title="Ver detalles">
                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                            Ver
                                        </button>
                                        {{-- Editar --}}
                                        <button type="button" onclick="openEditModal({{ $user->id }})" class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-900/50 transition border border-transparent dark:border-amber-800/40" title="Editar usuario">
                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                            Editar
                                        </button>
                                        {{-- Eliminar --}}
                                        @if($user->id !== auth()->id())
                                            <form action="{{ route('settings.delete_user', $user->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Estás seguro de eliminar a {{ $user->name }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-900/50 transition border border-transparent dark:border-rose-800/40" title="Eliminar cuenta">
                                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    Eliminar
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-[10px] text-slate-400 italic px-2">—</span>
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
                    <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm" onclick="closeViewModal()"></div>
                    <div class="absolute right-0 top-0 h-full w-full max-w-md bg-white dark:bg-[#12161f] shadow-2xl transform transition-transform duration-300 translate-x-full flex flex-col border-l border-slate-200 dark:border-slate-800" id="viewModalPanel">
                        {{-- Header --}}
                        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-800">
                            <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Detalle de Usuario</h3>
                            <button onclick="closeViewModal()" class="w-8 h-8 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center transition text-slate-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        {{-- Body --}}
                        <div class="flex-1 overflow-y-auto px-6 py-6 custom-scrollbar">
                            <div class="flex flex-col items-center mb-6">
                                <img id="viewAvatar" src="" alt="Avatar" class="w-20 h-20 rounded-full object-cover border-4 border-slate-100 dark:border-slate-800 shadow-md mb-3">
                                <h4 id="viewFullName" class="text-lg font-extrabold text-slate-900 dark:text-white"></h4>
                                <span id="viewRoleBadge" class="text-xs font-bold px-3 py-1 rounded-full mt-1"></span>
                            </div>
                            <div class="space-y-4">
                                <div class="settings-group" style="margin-bottom: 0;">
                                    <div class="settings-row">
                                        <div class="settings-row-label">Correo</div>
                                        <span id="viewEmail" class="text-sm font-mono text-slate-600 dark:text-slate-300"></span>
                                    </div>
                                    <div class="settings-row">
                                        <div class="settings-row-label">Prefijo</div>
                                        <span id="viewPrefijo" class="text-sm font-bold text-[#3b5998] dark:text-blue-400"></span>
                                    </div>
                                    <div class="settings-row">
                                        <div class="settings-row-label">Rol</div>
                                        <span id="viewRole" class="text-sm font-bold"></span>
                                    </div>
                                    <div class="settings-row">
                                        <div class="settings-row-label">Registrado</div>
                                        <span id="viewCreated" class="text-sm font-mono text-slate-500 dark:text-slate-400"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- Footer --}}
                        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 flex items-center gap-3">
                            <button onclick="closeViewModal()" class="flex-1 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-sm font-bold text-slate-600 dark:text-slate-300 transition">Cerrar</button>
                            <button id="viewModalEditBtn" onclick="editFromViewModal()" class="flex-1 py-2.5 rounded-xl bg-[#3b5998] hover:bg-[#2d4373] text-sm font-bold text-white transition flex items-center justify-center gap-1.5 shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                Editar
                            </button>
                        </div>
                    </div>
                </div>

                {{-- ==================== EDIT MODAL (Slide-over) ==================== --}}
                <div id="editModal" class="fixed inset-0 z-50 hidden">
                    <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm" onclick="closeEditModal()"></div>
                    <div class="absolute right-0 top-0 h-full w-full max-w-lg bg-white dark:bg-[#12161f] shadow-2xl transform transition-transform duration-300 translate-x-full flex flex-col border-l border-slate-200 dark:border-slate-800" id="editModalPanel">
                        {{-- Header --}}
                        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-800 bg-gradient-to-r from-[#3b5998]/5 to-transparent">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-950/50 flex items-center justify-center text-amber-600 dark:text-amber-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </div>
                                <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Editar Usuario</h3>
                            </div>
                            <button onclick="closeEditModal()" class="w-8 h-8 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center transition text-slate-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
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
                                        <div class="w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 border-2 border-dashed border-slate-300 dark:border-slate-700 group-hover:border-[#3b5998] flex items-center justify-center overflow-hidden transition" id="editAvatarContainer">
                                            <img id="editAvatarPreview" src="" alt="Avatar" class="w-full h-full object-cover">
                                        </div>
                                        <div class="absolute -bottom-0.5 -right-0.5 w-6 h-6 rounded-full bg-[#3b5998] flex items-center justify-center shadow-md">
                                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                        </div>
                                        <input type="file" name="avatar" id="editAvatarUpload" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="previewEditAvatar(this)">
                                    </label>
                                    <div class="flex-1">
                                        <p class="text-sm font-bold text-slate-800 dark:text-slate-200">Foto de perfil</p>
                                        <p class="text-xs text-slate-400">JPG, PNG o WebP · Máx 2MB</p>
                                        <label class="inline-flex items-center gap-1.5 mt-1.5 cursor-pointer">
                                            <input type="checkbox" name="remove_avatar" id="editRemoveAvatar" class="rounded border-slate-300 text-red-500 focus:ring-red-400 w-3.5 h-3.5">
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
                                        <label class="form-label">Prefijo <span class="text-slate-400 font-normal">(opc.)</span></label>
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
                                <div class="border-t border-slate-100 dark:border-slate-800 pt-4">
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Cambiar Contraseña <span class="font-normal normal-case text-slate-400">(dejar vacío para mantener)</span></p>
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
                            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
                                <button type="button" onclick="closeEditModal()" class="px-5 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-sm font-bold text-slate-600 dark:text-slate-300 transition">Cancelar</button>
                                <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#3b5998] hover:bg-[#2d4373] text-sm font-bold text-white transition flex items-center gap-2 shadow-md">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                    <span>Guardar Cambios</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ========== SECTION: Seguridad ========== --}}
            <div class="settings-section {{ $section === 'seguridad' ? 'active' : '' }}" id="section-seguridad" data-section-name="Seguridad">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-9 h-9 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <h1 class="settings-section-title mb-0">Seguridad y Control de Acceso</h1>
                </div>
                <p class="settings-section-subtitle">Configuraciones de autenticación de dos factores, sesiones y roles de usuario.</p>

                <div class="settings-group">
                    <h4 class="settings-group-title">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        Autenticación y Sesión
                    </h4>
                    
                    {{-- Row: 2FA --}}
                    <div class="settings-row">
                        <div class="flex items-center gap-3.5">
                            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                            </div>
                            <div>
                                <div class="settings-row-label">Autenticación de Dos Factores (2FA)</div>
                                <div class="settings-row-desc">Protege tu cuenta requiriendo un código TOTP (Google Authenticator)</div>
                            </div>
                        </div>
                        <a href="{{ route('setup-2fa') }}" class="px-3.5 py-1.5 rounded-xl bg-[#3b5998] hover:bg-[#2d4373] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs">
                            <span>Configurar</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </a>
                    </div>

                    {{-- Row: Session Timeout --}}
                    <div class="settings-row">
                        <div class="flex items-center gap-3.5">
                            <div class="w-9 h-9 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <div>
                                <div class="settings-row-label">Tiempo de Expiración de Sesión</div>
                                <div class="settings-row-desc">Duración máxima de inactividad antes de requerir reautenticación</div>
                            </div>
                        </div>
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300 font-mono bg-slate-100 dark:bg-slate-800 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700">120 min</span>
                    </div>

                    {{-- Row: Password Confirmation --}}
                    <div class="settings-row">
                        <div class="flex items-center gap-3.5">
                            <div class="w-9 h-9 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            </div>
                            <div>
                                <div class="settings-row-label">Confirmación de Contraseña</div>
                                <div class="settings-row-desc">Protección reforzada antes de acceder a áreas administrativas críticas</div>
                            </div>
                        </div>
                        <span class="text-xs font-bold px-3 py-1 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            Activo
                        </span>
                    </div>
                </div>

                <div class="settings-group">
                    <h4 class="settings-group-title">
                        <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        Matriz de Control de Acceso (RBAC)
                    </h4>
                    
                    <div class="settings-row">
                        <div class="flex items-center gap-3.5">
                            <div class="w-9 h-9 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                            </div>
                            <div>
                                <div class="settings-row-label">Administrador (Admin)</div>
                                <div class="settings-row-desc">Control total del NOC, gestión de usuarios, SSH Terminal y ajustes</div>
                            </div>
                        </div>
                        <span class="text-xs font-bold px-2.5 py-1 rounded-full role-admin">Full Access</span>
                    </div>

                    <div class="settings-row">
                        <div class="flex items-center gap-3.5">
                            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            </div>
                            <div>
                                <div class="settings-row-label">Operador (Operator)</div>
                                <div class="settings-row-desc">Monitoreo activo del Dashboard, Topología y Estado de Enlaces</div>
                            </div>
                        </div>
                        <span class="text-xs font-bold px-2.5 py-1 rounded-full role-operador">Operaciones</span>
                    </div>

                    <div class="settings-row">
                        <div class="flex items-center gap-3.5">
                            <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                            </div>
                            <div>
                                <div class="settings-row-label">Auditor (Compliance)</div>
                                <div class="settings-row-desc">Acceso a logs, reportes de cumplimiento e histórico de comandos ejecutados</div>
                            </div>
                        </div>
                        <span class="text-xs font-bold px-2.5 py-1 rounded-full role-auditor">Solo Lectura</span>
                    </div>
                </div>
            </div>

            {{-- ========== SECTION: Notificaciones ========== --}}
            <div class="settings-section {{ $section === 'notificaciones' ? 'active' : '' }}" id="section-notificaciones" data-section-name="Notificaciones">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    </div>
                    <h1 class="settings-section-title mb-0">Canales de Alerta y Notificaciones</h1>
                </div>
                <p class="settings-section-subtitle">Gestiona cómo recibes alertas críticas y notificaciones del estado de la red.</p>

                <div class="settings-group">
                    <h4 class="settings-group-title">
                        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        Canales de Transmisión
                    </h4>
                    
                    <div class="settings-row">
                        <div class="flex items-center gap-3.5">
                            <div class="w-9 h-9 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            </div>
                            <div>
                                <div class="settings-row-label">Alertas por Correo Electrónico (SMTP)</div>
                                <div class="settings-row-desc">Envío de resumen y alertas cuando un equipo cae a estado OFFLINE</div>
                            </div>
                        </div>
                        <span class="text-xs font-bold px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">Próximamente</span>
                    </div>

                    <div class="settings-row">
                        <div class="flex items-center gap-3.5">
                            <div class="w-9 h-9 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            </div>
                            <div>
                                <div class="settings-row-label">Webhook / Slack / Teams</div>
                                <div class="settings-row-desc">Publicación automática de incidentes de red en canal de chat NOC</div>
                            </div>
                        </div>
                        <span class="text-xs font-bold px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">Próximamente</span>
                    </div>

                    <div class="settings-row">
                        <div class="flex items-center gap-3.5">
                            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                            </div>
                            <div>
                                <div class="settings-row-label">Notificaciones en Dashboard en Tiempo Real</div>
                                <div class="settings-row-desc">Indicador sonoro y marquesina NOC en la barra superior</div>
                            </div>
                        </div>
                        <span class="text-xs font-bold px-3 py-1 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            Activo
                        </span>
                    </div>
                </div>
            </div>

            {{-- ========== SECTION: Apariencia ========== --}}
            <div class="settings-section {{ $section === 'apariencia' ? 'active' : '' }}" id="section-apariencia" data-section-name="Apariencia" x-data="appearanceManager()" x-init="init()">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-pink-500/10 text-pink-600 dark:text-pink-400 flex items-center justify-center font-bold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path></svg>
                        </div>
                        <div>
                            <h1 class="settings-section-title mb-0">Apariencia del Sistema</h1>
                            <p class="settings-section-subtitle mb-0">Gestiona el tema visual y la ergonomía cromática del NOC.</p>
                        </div>
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
                            <h4 class="settings-group-title mb-1">
                                <svg class="w-4 h-4 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                Tema de Visualización
                            </h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Selecciona el modo cromático preferido para la estación de trabajo.</p>
                        </div>

                        {{-- Segmented Quick Switcher --}}
                        <div class="inline-flex p-1 rounded-xl bg-slate-200/80 dark:bg-[#090b10] border border-slate-300/60 dark:border-slate-800/60 shadow-inner self-start sm:self-auto">
                            <button type="button" @click="selectTheme('light')"
                                    :class="currentTheme === 'light' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                Claro
                            </button>
                            <button type="button" @click="selectTheme('dark')"
                                    :class="currentTheme === 'dark' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                                Oscuro
                            </button>
                            <button type="button" @click="selectTheme('system')"
                                    :class="currentTheme === 'system' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
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
                                : 'border-slate-200 dark:border-slate-800/60 bg-white dark:bg-[#090b10] hover:border-slate-300 dark:hover:border-slate-700'"
                             class="rounded-2xl border p-4 cursor-pointer transition relative flex flex-col justify-between group">
                            
                            {{-- Checkmark indicator --}}
                            <div class="absolute top-3 right-3">
                                <div class="w-5 h-5 rounded-full flex items-center justify-center transition"
                                     :class="currentTheme === 'light' ? 'bg-[#3b5998] text-white' : 'border border-slate-300 dark:border-slate-700 text-transparent'">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                            </div>

                            <div>
                                {{-- Mini UI Preview Mockup (Light) --}}
                                <div class="rounded-xl bg-[#f1f5f9] p-2.5 border border-slate-200 mb-3 shadow-sm">
                                    <div class="flex items-center gap-1.5 pb-2 mb-2 border-b border-slate-200">
                                        <div class="w-2.5 h-2.5 rounded-full bg-rose-400"></div>
                                        <div class="w-2.5 h-2.5 rounded-full bg-amber-400"></div>
                                        <div class="w-2.5 h-2.5 rounded-full bg-emerald-400"></div>
                                        <div class="w-16 h-2 rounded-full bg-slate-300 ml-auto"></div>
                                    </div>
                                    <div class="space-y-1.5">
                                        <div class="flex gap-1.5">
                                            <div class="w-6 h-10 rounded bg-[#3b5998] flex-shrink-0"></div>
                                            <div class="flex-1 space-y-1">
                                                <div class="h-4 bg-white rounded border border-slate-200"></div>
                                                <div class="grid grid-cols-2 gap-1">
                                                    <div class="h-5 bg-white rounded border border-slate-200"></div>
                                                    <div class="h-5 bg-white rounded border border-slate-200"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 mb-1">
                                    <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                    <h5 class="font-extrabold text-sm text-slate-900 dark:text-white">Claro / Diurno</h5>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                    Fondos blancos limpios de alto contraste, ideal para oficinas iluminadas.
                                </p>
                            </div>

                            <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/40 flex items-center justify-between">
                                <span class="text-[10px] font-mono font-bold text-slate-400">#FFFFFF / #F1F5F9</span>
                                <span class="text-[11px] font-bold text-[#3b5998]" x-show="currentTheme === 'light'">Seleccionado</span>
                            </div>
                        </div>

                        {{-- Card 2: Modo Oscuro (NOC OLED) --}}
                        <div @click="selectTheme('dark')"
                             :class="currentTheme === 'dark' 
                                ? 'border-blue-500 ring-2 ring-blue-500/20 bg-blue-950/20' 
                                : 'border-slate-200 dark:border-slate-800/60 bg-white dark:bg-[#090b10] hover:border-slate-300 dark:hover:border-slate-700'"
                             class="rounded-2xl border p-4 cursor-pointer transition relative flex flex-col justify-between group">
                            
                            {{-- Checkmark indicator --}}
                            <div class="absolute top-3 right-3">
                                <div class="w-5 h-5 rounded-full flex items-center justify-center transition"
                                     :class="currentTheme === 'dark' ? 'bg-blue-600 text-white' : 'border border-slate-300 dark:border-slate-700 text-transparent'">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                            </div>

                            <div>
                                {{-- Mini UI Preview Mockup (Dark OLED) --}}
                                <div class="rounded-xl bg-[#07090e] p-2.5 border border-slate-800/80 mb-3 shadow-sm">
                                    <div class="flex items-center gap-1.5 pb-2 mb-2 border-b border-slate-800/60">
                                        <div class="w-2.5 h-2.5 rounded-full bg-rose-500/80"></div>
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
                                    <h5 class="font-extrabold text-sm text-slate-900 dark:text-white">Oscuro / NOC OLED</h5>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                    Consola con negros profundos anti-fatiga para operaciones 24/7.
                                </p>
                            </div>

                            <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/40 flex items-center justify-between">
                                <span class="text-[10px] font-mono font-bold text-slate-400">#07090E / #0D1017</span>
                                <span class="text-[11px] font-bold text-blue-400" x-show="currentTheme === 'dark'">Seleccionado</span>
                            </div>
                        </div>

                        {{-- Card 3: Modo Automático / Sistema --}}
                        <div @click="selectTheme('system')"
                             :class="currentTheme === 'system' 
                                ? 'border-indigo-500 ring-2 ring-indigo-500/20 bg-indigo-50/20 dark:bg-indigo-950/20' 
                                : 'border-slate-200 dark:border-slate-800/60 bg-white dark:bg-[#090b10] hover:border-slate-300 dark:hover:border-slate-700'"
                             class="rounded-2xl border p-4 cursor-pointer transition relative flex flex-col justify-between group">
                            
                            {{-- Checkmark indicator --}}
                            <div class="absolute top-3 right-3">
                                <div class="w-5 h-5 rounded-full flex items-center justify-center transition"
                                     :class="currentTheme === 'system' ? 'bg-indigo-600 text-white' : 'border border-slate-300 dark:border-slate-700 text-transparent'">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                            </div>

                            <div>
                                {{-- Mini UI Preview Mockup (Split Light/Dark) --}}
                                <div class="rounded-xl overflow-hidden border border-slate-200 dark:border-slate-800/80 mb-3 shadow-sm flex h-[62px]">
                                    {{-- Light half --}}
                                    <div class="w-1/2 bg-[#f1f5f9] p-2 border-r border-slate-300 dark:border-slate-700 flex flex-col justify-between">
                                        <div class="w-10 h-2 rounded bg-slate-300"></div>
                                        <div class="h-4 bg-white rounded border border-slate-200"></div>
                                    </div>
                                    {{-- Dark half --}}
                                    <div class="w-1/2 bg-[#07090e] p-2 flex flex-col justify-between">
                                        <div class="w-10 h-2 rounded bg-slate-800 ml-auto"></div>
                                        <div class="h-4 bg-[#0d1017] rounded border border-slate-800/60"></div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 mb-1">
                                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                    <h5 class="font-extrabold text-sm text-slate-900 dark:text-white">Automático (Sistema)</h5>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                    Sincroniza en tiempo real con la preferencia de color de tu sistema operativo.
                                </p>
                            </div>

                            <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/40 flex items-center justify-between">
                                <span class="text-[10px] font-mono font-bold text-slate-400">matchMedia (OS)</span>
                                <span class="text-[11px] font-bold text-indigo-400" x-show="currentTheme === 'system'">Seleccionado</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Paleta de Colores Institucional --}}
                <div class="settings-group">
                    <h4 class="settings-group-title">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path></svg>
                        Paleta de Colores Institucional NOC
                    </h4>
                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Color Primario (Azul Institucional)</div>
                            <div class="settings-row-desc">Utilizado en barra lateral, botones primarios y encabezados clave</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg shadow-inner border border-slate-200 dark:border-slate-700" style="background: #3b5998;"></div>
                            <span class="text-xs font-mono font-bold text-slate-500 dark:text-slate-400">#3b5998</span>
                        </div>
                    </div>
                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Color Secundario (Slate Blue)</div>
                            <div class="settings-row-desc">Utilizado en elementos de soporte, insignias y subtítulos</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg shadow-inner border border-slate-200 dark:border-slate-700" style="background: #5c8096;"></div>
                            <span class="text-xs font-mono font-bold text-slate-500 dark:text-slate-400">#5c8096</span>
                        </div>
                    </div>
                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Color de Acento (Naranja NOC)</div>
                            <div class="settings-row-desc">Utilizado en alertas críticas, botones de acción y destacados de tráfico</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg shadow-inner border border-slate-200 dark:border-slate-700" style="background: #f26419;"></div>
                            <span class="text-xs font-mono font-bold text-slate-500 dark:text-slate-400">#f26419</span>
                        </div>
                    </div>
                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Fondo Consola OLED (Dark Canvas)</div>
                            <div class="settings-row-desc">Fondo profundo anti-reflejos para paneles de alta disponibilidad</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg shadow-inner border border-slate-200 dark:border-slate-700" style="background: #07090e;"></div>
                            <span class="text-xs font-mono font-bold text-slate-500 dark:text-slate-400">#07090e</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ========== SECTION: Acerca de ========== --}}
            <div class="settings-section {{ $section === 'acerca' ? 'active' : '' }}" id="section-acerca" data-section-name="Acerca de">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-9 h-9 rounded-xl bg-teal-500/10 text-teal-600 dark:text-teal-400 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h1 class="settings-section-title mb-0">Acerca de la Plataforma</h1>
                </div>
                <p class="settings-section-subtitle">Información del sistema de monitoreo de infraestructura de red.</p>

                <div class="settings-group">
                    <h4 class="settings-group-title">
                        <svg class="w-4 h-4 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path></svg>
                        Información del Entorno
                    </h4>
                    
                    <div class="settings-row">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-500 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                            </div>
                            <div class="settings-row-label">Nombre del Sistema</div>
                        </div>
                        <span class="text-sm font-bold text-slate-800 dark:text-slate-200">Network Monitor NOC</span>
                    </div>

                    <div class="settings-row">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-500 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </div>
                            <div class="settings-row-label">Versión de Producción</div>
                        </div>
                        <span class="text-xs font-bold font-mono px-2.5 py-1 rounded-lg bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400">v2.4.0</span>
                    </div>

                    <div class="settings-row">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-rose-500/10 text-rose-500 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                            </div>
                            <div class="settings-row-label">Framework Backend</div>
                        </div>
                        <span class="text-sm font-bold text-slate-700 dark:text-slate-300 font-mono">Laravel {{ app()->version() }}</span>
                    </div>

                    <div class="settings-row">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-indigo-500/10 text-indigo-500 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                            </div>
                            <div class="settings-row-label">PHP Engine</div>
                        </div>
                        <span class="text-sm font-bold font-mono text-slate-700 dark:text-slate-300">{{ phpversion() }}</span>
                    </div>

                    <div class="settings-row">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-teal-500/10 text-teal-500 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            </div>
                            <div class="settings-row-label">Workers Python en Background</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-xs font-bold font-mono text-slate-700 dark:text-slate-300">snmp_poller.py, ssh_executor.py</span>
                        </div>
                    </div>
                </div>

                <div class="settings-group">
                    <h4 class="settings-group-title">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        Tecnologías Integradas
                    </h4>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach(['Laravel 12', 'Tailwind CSS', 'Alpine.js', 'xterm.js', 'PySNMP', 'Paramiko SSH', 'MySQL', 'Vite'] as $tech)
                            <span class="px-3 py-1.5 rounded-xl bg-white dark:bg-[#090b10] border border-slate-200 dark:border-slate-800 text-xs font-bold text-slate-700 dark:text-slate-300 shadow-xs flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-[#3b5998]"></span>
                                {{ $tech }}
                            </span>
                        @endforeach
                    </div>
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
            const searchInput = document.getElementById('settingsSearch');
            const clearSearchBtn = document.getElementById('clearSettingsSearch');
            const noResults = document.getElementById('noSearchResults');

            // Navigation tab switching
            navItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = this.dataset.section;

                    // Update nav active state
                    navItems.forEach(n => n.classList.remove('active'));
                    this.classList.add('active');

                    // If search is empty, show only target section
                    if (!searchInput || !searchInput.value.trim()) {
                        sections.forEach(s => s.classList.remove('active'));
                        const targetSection = document.getElementById('section-' + target);
                        if (targetSection) {
                            targetSection.classList.add('active');
                        }
                    } else {
                        // Scroll to target section in search mode
                        const targetSection = document.getElementById('section-' + target);
                        if (targetSection) {
                            targetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    }

                    // Update URL without reload
                    const url = new URL(window.location);
                    url.searchParams.set('section', target);
                    history.replaceState(null, '', url);
                });
            });

            // ==================== LIVE SEARCH ENGINE ====================
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const query = this.value.toLowerCase().trim();
                    
                    if (clearSearchBtn) {
                        if (query) {
                            clearSearchBtn.classList.remove('hidden');
                        } else {
                            clearSearchBtn.classList.add('hidden');
                        }
                    }

                    if (!query) {
                        // Reset view: activate only current nav item
                        if (noResults) noResults.classList.add('hidden');
                        
                        // Clear match badges
                        document.querySelectorAll('.nav-match-count').forEach(b => {
                            b.classList.add('hidden');
                            b.textContent = '';
                        });

                        // Show all rows
                        document.querySelectorAll('.settings-row, .settings-group').forEach(el => {
                            el.style.display = '';
                        });

                        // Restore active section
                        const activeNav = document.querySelector('.settings-nav-item.active');
                        const target = activeNav ? activeNav.dataset.section : 'general';
                        sections.forEach(s => s.classList.remove('active'));
                        const targetSection = document.getElementById('section-' + target);
                        if (targetSection) targetSection.classList.add('active');
                        return;
                    }

                    // When searching: reveal all matching sections & rows
                    let totalMatches = 0;

                    sections.forEach(section => {
                        const secName = (section.dataset.sectionName || '').toLowerCase();
                        const secId = section.id.replace('section-', '');
                        const navItem = document.querySelector(`.settings-nav-item[data-section="${secId}"]`);
                        const badge = navItem ? navItem.querySelector('.nav-match-count') : null;
                        
                        let secMatches = 0;

                        // Search within groups & rows
                        const groups = section.querySelectorAll('.settings-group');
                        groups.forEach(group => {
                            const groupTitle = group.querySelector('.settings-group-title')?.textContent.toLowerCase() || '';
                            const rows = group.querySelectorAll('.settings-row');
                            let groupHasMatch = false;

                            if (groupTitle.includes(query) || secName.includes(query)) {
                                groupHasMatch = true;
                                rows.forEach(r => r.style.display = '');
                                secMatches += (rows.length || 1);
                            } else {
                                rows.forEach(row => {
                                    const text = row.textContent.toLowerCase();
                                    if (text.includes(query)) {
                                        row.style.display = '';
                                        groupHasMatch = true;
                                        secMatches++;
                                    } else {
                                        row.style.display = 'none';
                                    }
                                });
                            }

                            group.style.display = groupHasMatch ? '' : 'none';
                        });

                        // Check user table if in cuentas section
                        const userRows = section.querySelectorAll('.user-table tbody tr');
                        if (userRows.length > 0) {
                            userRows.forEach(tr => {
                                const text = tr.textContent.toLowerCase();
                                if (text.includes(query)) {
                                    tr.style.display = '';
                                    secMatches++;
                                } else {
                                    tr.style.display = 'none';
                                }
                            });
                        }

                        // Show/hide section
                        if (secMatches > 0) {
                            section.classList.add('active');
                            totalMatches += secMatches;
                            if (badge) {
                                badge.textContent = secMatches;
                                badge.classList.remove('hidden');
                            }
                        } else {
                            section.classList.remove('active');
                            if (badge) {
                                badge.classList.add('hidden');
                            }
                        }
                    });

                    if (noResults) {
                        if (totalMatches === 0) {
                            noResults.classList.remove('hidden');
                        } else {
                            noResults.classList.add('hidden');
                        }
                    }
                });

                if (clearSearchBtn) {
                    clearSearchBtn.addEventListener('click', function() {
                        searchInput.value = '';
                        searchInput.dispatchEvent(new Event('input'));
                        searchInput.focus();
                    });
                }
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
