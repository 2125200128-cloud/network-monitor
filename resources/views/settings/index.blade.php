@extends('layouts.app')

@section('styles')
    <style>
        /* Ajustes: estilos sobre los tokens del sistema de diseño (partials/design-tokens);
           el modo oscuro sale de los mismos tokens, sin reglas .dark aparte. */
        .settings-container {
            display: flex; width: 100%; height: calc(100vh - 4.5rem); min-height: 750px; overflow: hidden;
            background: var(--nm-surface); border: 1px solid var(--nm-border);
            border-radius: var(--nm-card-radius); box-shadow: var(--nm-card-shadow);
        }

        /* Panel izquierdo (categorías) */
        .settings-nav {
            width: 300px; min-width: 300px; display: flex; flex-direction: column; overflow-y: auto;
            background: var(--nm-sunken); border-right: 1px solid var(--nm-border);
        }
        .settings-nav-header { padding: 1.5rem 1.25rem 1.1rem; border-bottom: 1px solid var(--nm-border); }
        .settings-nav-header h2 { color: var(--nm-text); }
        .settings-nav-header p { color: var(--nm-text-muted); }
        .settings-nav-header input { background: var(--nm-surface); border-color: var(--nm-border-strong); color: var(--nm-text); }
        .settings-nav-item {
            display: flex; align-items: center; gap: 0.85rem; padding: 0.7rem 1.1rem; margin: 0.1rem 0.6rem;
            border-radius: var(--nm-radius-sm); color: var(--nm-text-muted); font-size: 0.875rem; font-weight: 600;
            text-decoration: none; cursor: pointer; transition: background-color var(--nm-dur-fast) ease, color var(--nm-dur-fast) ease;
        }
        .settings-nav-item:hover { background: rgb(var(--nm-primary-rgb) / 0.07); color: var(--nm-text); }
        .settings-nav-item.active { background: rgb(var(--nm-primary-rgb) / 0.12); color: var(--nm-primary); font-weight: 700; }
        .settings-nav-item .nav-badge {
            margin-left: auto; font-size: 0.7rem; font-weight: 700; padding: 0.15rem 0.55rem; border-radius: 9999px;
            background: var(--nm-primary); color: #fff;
        }
        .nav-separator { height: 1px; background: var(--nm-border); margin: 0.5rem 1.1rem; }

        /* Panel derecho (contenido) */
        .settings-content { flex: 1; overflow-y: auto; padding: 2rem 2.75rem; color: var(--nm-text); }
        .settings-section { display: none; }
        .settings-section.active { display: block; animation: nmEntrada var(--nm-dur-slow) var(--nm-ease) both; }
        .settings-section-title { font-size: 1.5rem; font-weight: 800; color: var(--nm-text); margin-bottom: 0.3rem; letter-spacing: -0.02em; }
        .settings-section-subtitle { color: var(--nm-text-muted); font-size: 0.9rem; margin-bottom: 1.75rem; max-width: 900px; }
        .settings-group {
            background: var(--nm-surface); border: 1px solid var(--nm-border); border-radius: var(--nm-radius-lg);
            padding: 1.5rem 1.75rem; margin-bottom: 1.5rem; box-shadow: var(--nm-shadow-sm);
        }
        .settings-group-title {
            display: flex; align-items: center; gap: 0.6rem; margin-bottom: 1rem;
            font-size: 0.78rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--nm-primary-2);
        }
        .dark .settings-group-title { color: var(--nm-azure-300); }
        .settings-row {
            display: flex; align-items: center; justify-content: space-between; gap: 1.5rem;
            padding: 1rem 0; border-bottom: 1px solid var(--nm-border);
        }
        .settings-row:last-child { border-bottom: none; }
        .settings-row-label { font-size: 0.92rem; font-weight: 600; color: var(--nm-text); }
        .settings-row-desc { font-size: 0.8rem; color: var(--nm-text-muted); margin-top: 0.2rem; line-height: 1.45; }
        .settings-input, .register-card .form-input {
            padding: 0.6rem 0.85rem; font-size: 0.875rem; color: var(--nm-text); background: var(--nm-surface);
            border: 1px solid var(--nm-input-border); border-radius: var(--nm-input-radius);
            transition: border-color var(--nm-dur-fast) ease, box-shadow var(--nm-dur-fast) ease;
        }
        .settings-input { width: 240px; }
        .register-card .form-input { width: 100%; }
        .settings-input:focus, .register-card .form-input:focus {
            outline: none; border-color: var(--nm-primary); box-shadow: 0 0 0 3px rgb(var(--nm-primary-rgb) / 0.18);
        }

        /* Insignias de icono: azul metálico (antes negro) */
        .icon-black-badge, .icon-black-pill, .icon-black-pill-sm {
            display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; color: #fff;
            background: var(--nm-metal-sheen), var(--nm-metal-azure); box-shadow: 0 2px 6px -2px rgb(var(--nm-primary-rgb) / 0.55);
        }
        .icon-black-badge { width: 2.75rem; height: 2.75rem; border-radius: var(--nm-radius-md); }
        .icon-black-pill { width: 2.35rem; height: 2.35rem; border-radius: var(--nm-radius-sm); }
        .icon-black-pill-sm {
            width: 2rem; height: 2rem; border-radius: var(--nm-radius-sm);
            color: var(--nm-text-muted); background: var(--nm-surface); border: 1px solid var(--nm-border); box-shadow: none;
            transition: background var(--nm-dur-fast) ease, color var(--nm-dur-fast) ease;
        }
        .settings-nav-item.active .icon-black-pill-sm {
            color: #fff; border-color: transparent; background: var(--nm-metal-sheen), var(--nm-metal-azure);
        }

        /* Tabla de usuarios */
        .user-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .user-table thead th {
            padding: 0.8rem 1.1rem; text-align: left; font-size: 0.74rem; font-weight: 700; letter-spacing: 0.05em;
            text-transform: uppercase; color: var(--nm-text-muted); background: var(--nm-sunken); border-bottom: 1px solid var(--nm-border);
        }
        .user-table thead th:first-child { border-radius: var(--nm-radius-sm) 0 0 0; }
        .user-table thead th:last-child { border-radius: 0 var(--nm-radius-sm) 0 0; }
        .user-table tbody td { padding: 0.85rem 1.1rem; font-size: 0.875rem; color: var(--nm-text); border-bottom: 1px solid var(--nm-border); vertical-align: middle; }
        .user-table tbody tr:hover { background: rgb(var(--nm-primary-rgb) / 0.04); }
        .user-table tbody tr:last-child td { border-bottom: none; }

        /* Alta de usuario */
        .register-card { background: var(--nm-sunken); border: 1px solid var(--nm-border); border-radius: var(--nm-radius-lg); padding: 1.75rem; }
        .register-card .form-label { display: flex; align-items: center; gap: 0.35rem; margin-bottom: 0.4rem; font-size: 0.8rem; font-weight: 600; color: var(--nm-text); }

        /* Roles */
        .role-admin { background: var(--nm-primary); color: #fff; border: 1px solid transparent; }
        .role-operador { background: rgb(var(--nm-primary-rgb) / 0.12); color: var(--nm-primary); border: 1px solid rgb(var(--nm-primary-rgb) / 0.3); }
        .role-auditor { background: var(--nm-sunken); color: var(--nm-text-muted); border: 1px solid var(--nm-border-strong); }

        /* Botón principal */
        .btn-black-executive {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; padding: 0.65rem 1.4rem;
            font-size: 0.875rem; font-weight: 700; color: #fff; border: 1px solid transparent; border-radius: var(--nm-btn-radius);
            background: var(--nm-metal-sheen), var(--nm-metal-azure); box-shadow: 0 2px 8px -2px rgb(var(--nm-primary-rgb) / 0.55);
            transition: filter var(--nm-dur-fast) ease, box-shadow var(--nm-dur-fast) ease; cursor: pointer;
        }
        .btn-black-executive:hover { filter: brightness(1.08); box-shadow: 0 4px 14px -3px rgb(var(--nm-primary-rgb) / 0.6); }

        @media (max-width: 1024px) {
            .settings-container { flex-direction: column; height: auto; min-height: auto; }
            .settings-nav { width: 100%; min-width: 100%; flex-direction: row; overflow-x: auto; border-right: none; border-bottom: 1px solid var(--nm-border); }
            .settings-nav-header, .nav-separator { display: none; }
            .settings-nav-item { white-space: nowrap; }
            .settings-content { padding: 1.25rem; }
        }

        .search-highlight { background-color: rgba(251, 191, 36, 0.35); padding: 1px 4px; border-radius: 4px; }
    </style>
@endsection

@section('content')
<div class="h-full flex flex-col w-full max-w-[1780px] mx-auto px-2 sm:px-4 lg:px-6">

    <div class="settings-container flex-1">

        {{-- ==================== LEFT NAV PANEL ==================== --}}
        <nav class="settings-nav custom-scrollbar">
            <div class="settings-nav-header">
                <div class="flex items-center gap-3.5 mb-4">
                    <div class="icon-black-badge anim-spin-hover shadow-md">
                        <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z" /> <circle cx="12" cy="12" r="3" /></svg>
                    </div>
                    <div>
                        <h2 class="text-base font-black text-slate-900 dark:text-white leading-tight">Configuración</h2>
                        <p class="text-xs text-slate-400 font-medium">Ajustes del sistema NOC</p>
                    </div>
                </div>

                {{-- Live Search Filter with Real Filtering --}}
                <div class="relative">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8" /> <path d="m21 21-4.3-4.3" /></svg>
                    <input type="text" placeholder="Buscar en todos los ajustes..." class="w-full pl-9 pr-7 py-2.5 bg-white dark:bg-nm-surface border border-slate-200 dark:border-slate-700/80 rounded-xl text-xs text-slate-800 dark:text-white font-medium focus:outline-none focus:ring-2 focus:ring-black dark:focus:ring-white focus:border-black dark:focus:border-white transition shadow-xs" id="settingsSearch">
                    <button type="button" id="clearSettingsSearch" class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18" /> <path d="m6 6 12 12" /></svg>
                    </button>
                </div>
            </div>

            {{-- Navigation Items with Black Animated SVG Icons --}}
            <div class="py-3 space-y-1" id="navItemsList">
                <a href="#" class="settings-nav-item {{ $section === 'general' ? 'active' : '' }}" data-section="general">
                    <div class="icon-black-pill-sm anim-flash-hover">
                        <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z" /></svg>
                    </div>
                    <span>Motor SNMP</span>
                    <span class="nav-match-count hidden ml-auto text-[11px] font-bold px-2 py-0.5 rounded-full bg-blue-600 text-white"></span>
                </a>

                <a href="#" class="settings-nav-item {{ $section === 'cuentas' ? 'active' : '' }}" data-section="cuentas">
                    <div class="icon-black-pill-sm anim-bounce-hover">
                        <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /> <circle cx="9" cy="7" r="4" /> <path d="M22 21v-2a4 4 0 0 0-3-3.87" /> <path d="M16 3.13a4 4 0 0 1 0 7.75" /></svg>
                    </div>
                    <span>Cuentas</span>
                    <span class="nav-badge">{{ $users->count() }}</span>
                </a>

                <div class="nav-separator"></div>

                <a href="#" class="settings-nav-item {{ $section === 'seguridad' ? 'active' : '' }}" data-section="seguridad">
                    <div class="icon-black-pill-sm anim-wiggle-hover">
                        <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" /> <path d="m9 12 2 2 4-4" /></svg>
                    </div>
                    <span>Seguridad</span>
                    <span class="nav-match-count hidden ml-auto text-[11px] font-bold px-2 py-0.5 rounded-full bg-blue-600 text-white"></span>
                </a>

                <a href="#" class="settings-nav-item {{ $section === 'notificaciones' ? 'active' : '' }}" data-section="notificaciones">
                    <div class="icon-black-pill-sm anim-bell-ring">
                        <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10.268 21a2 2 0 0 0 3.464 0" /> <path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326" /></svg>
                    </div>
                    <span>Notificaciones</span>
                    <span class="nav-match-count hidden ml-auto text-[11px] font-bold px-2 py-0.5 rounded-full bg-blue-600 text-white"></span>
                </a>

                <div class="nav-separator"></div>

                <a href="#" class="settings-nav-item {{ $section === 'apariencia' ? 'active' : '' }}" data-section="apariencia">
                    <div class="icon-black-pill-sm anim-float-loop">
                        <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor" /> <circle cx="17.5" cy="10.5" r=".5" fill="currentColor" /> <circle cx="8.5" cy="7.5" r=".5" fill="currentColor" /> <circle cx="6.5" cy="12.5" r=".5" fill="currentColor" /> <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z" /></svg>
                    </div>
                    <span>Apariencia</span>
                    <span class="nav-match-count hidden ml-auto text-[11px] font-bold px-2 py-0.5 rounded-full bg-blue-600 text-white"></span>
                </a>

                <a href="#" class="settings-nav-item {{ $section === 'acerca' ? 'active' : '' }}" data-section="acerca">
                    <div class="icon-black-pill-sm anim-spin-hover">
                        <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <path d="M12 16v-4" /> <path d="M12 8h.01" /></svg>
                    </div>
                    <span>Acerca de</span>
                    <span class="nav-match-count hidden ml-auto text-[11px] font-bold px-2 py-0.5 rounded-full bg-blue-600 text-white"></span>
                </a>
            </div>

            {{-- User Session Footer --}}
            <div class="mt-auto p-4 border-t border-slate-200 dark:border-slate-800">
                <div class="flex items-center gap-3 p-1.5 mb-2.5">
                    @if(auth()->user()->avatar)
                        <img src="{{ asset('storage/' . auth()->user()->avatar) }}" alt="{{ auth()->user()->name }}" class="w-9 h-9 rounded-full object-cover border-2 border-black/20 dark:border-white/20 flex-shrink-0">
                    @else
                        <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'Admin') }}&background=09090b&color=fff&size=64" alt="{{ auth()->user()->name }}" class="w-9 h-9 rounded-full border-2 border-black/20 dark:border-white/20 flex-shrink-0">
                    @endif
                    <div class="overflow-hidden">
                        <p class="text-xs font-bold text-slate-800 dark:text-slate-100 leading-tight truncate">
                            @if(auth()->user()->prefijo)<span class="text-black dark:text-white font-extrabold">{{ auth()->user()->prefijo }}</span> @endif{{ auth()->user()->name ?? 'Administrador' }}
                        </p>
                        <p class="text-[11px] text-slate-400 font-medium uppercase">{{ auth()->user()->role ?? 'Admin' }}</p>
                    </div>
                </div>

                {{-- Botón Cerrar Sesión --}}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-xl bg-red-50 dark:bg-red-950/40 hover:bg-red-100 dark:hover:bg-red-900/50 text-red-600 dark:text-red-400 text-xs font-bold transition border border-red-200/80 dark:border-red-800/40 shadow-xs" title="Cerrar Sesión" aria-label="Cerrar Sesión">
                        <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /> <polyline points="16 17 21 12 16 7" /> <line x1="21" x2="9" y1="12" y2="12" /></svg>
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
                    <div class="icon-black-pill anim-bounce-hover" style="width: 2.2rem; height: 2.2rem;">
                        <svg class="w-4 h-4 text-emerald-400" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <path d="m9 12 2 2 4-4" /></svg>
                    </div>
                    <span>{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="mb-6 p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-700 dark:text-rose-300 text-sm font-semibold flex items-center gap-3 shadow-xs">
                    <div class="icon-black-pill anim-wiggle-hover" style="width: 2.2rem; height: 2.2rem;">
                        <svg class="w-4 h-4 text-rose-400" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <line x1="12" x2="12" y1="8" y2="12" /> <line x1="12" x2="12.01" y1="16" y2="16" /></svg>
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
            <div id="noSearchResults" class="hidden p-8 rounded-2xl bg-slate-50 dark:bg-nm-surface border border-slate-200 dark:border-slate-800 text-center">
                <div class="icon-black-badge anim-spin-hover mx-auto mb-3">
                    <svg class="w-6 h-6" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8" /> <path d="m21 21-4.3-4.3" /></svg>
                </div>
                <h3 class="text-sm font-bold text-slate-800 dark:text-white">No se encontraron ajustes</h3>
                <p class="text-xs text-slate-400 mt-1">Intenta con otros términos como "SNMP", "CPU", "2FA", "Tema", o "Contraseña".</p>
            </div>

            {{-- ========== SECTION: General / Motor SNMP ========== --}}
            <div class="settings-section {{ $section === 'general' ? 'active' : '' }}" id="section-general" data-section-name="Motor SNMP">
                <div class="flex items-center gap-3.5 mb-2">
                    <div class="icon-black-badge anim-flash-hover">
                        <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z" /></svg>
                    </div>
                    <div>
                        <h1 class="settings-section-title mb-0">Motor de Sondeo SNMP</h1>
                        <p class="settings-section-subtitle mb-0">Configura los parámetros del motor de sondeo, comunidad SNMP y umbrales de alerta.</p>
                    </div>
                </div>

                <form action="{{ route('settings.update') }}" method="POST" class="mt-6">
                    @csrf

                    <div class="grid grid-cols-1 2xl:grid-cols-2 gap-6 items-start">
                        {{-- Grupo 1: Parámetros del Worker --}}
                        <div class="settings-group mb-0">
                            <h4 class="settings-group-title">
                                <svg class="w-4 h-4 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z" /> <circle cx="12" cy="12" r="3" /></svg>
                                Parámetros del Worker Python
                            </h4>
                            
                            {{-- Row: Intervalo --}}
                            <div class="settings-row">
                                <div class="flex items-center gap-4">
                                    <div class="icon-black-pill anim-spin-hover">
                                        <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <polyline points="12 6 12 12 16 14" /></svg>
                                    </div>
                                    <div>
                                        <div class="settings-row-label">Intervalo de Sondeo</div>
                                        <div class="settings-row-desc">Tiempo entre ciclos de escaneo del worker Python</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="number" name="intervalo_sondeo_segundos" value="{{ $settings->intervalo_sondeo_segundos }}" min="10" class="settings-input text-right font-mono font-bold" style="width: 140px;">
                                    <span class="text-xs font-bold text-slate-400">seg</span>
                                </div>
                            </div>

                            {{-- Row: Comunidad SNMP --}}
                            <div class="settings-row">
                                <div class="flex items-center gap-4">
                                    <div class="icon-black-pill anim-wiggle-hover">
                                        <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z" /> <circle cx="16.5" cy="7.5" r=".5" fill="currentColor" /></svg>
                                    </div>
                                    <div>
                                        <div class="settings-row-label">Comunidad SNMP</div>
                                        <div class="settings-row-desc">Cadena de lectura por defecto (ro community)</div>
                                    </div>
                                </div>
                                <input type="text" name="comunidad_snmp_default" value="{{ $settings->comunidad_snmp_default }}" class="settings-input font-mono font-bold" style="width: 200px;">
                            </div>

                            {{-- Row: Comunidad SNMP de firewalls --}}
                            <div class="settings-row">
                                <div class="flex items-center gap-4">
                                    <div class="icon-black-pill anim-wiggle-hover">
                                        <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" /></svg>
                                    </div>
                                    <div>
                                        <div class="settings-row-label">Comunidad SNMP Firewalls (FortiGate)</div>
                                        <div class="settings-row-desc">Para equipos tipo Firewall, salvo que su ficha tenga una propia</div>
                                    </div>
                                </div>
                                <input type="text" name="comunidad_snmp_firewall" value="{{ $settings->comunidad_snmp_firewall }}" placeholder="Igual que la red" autocomplete="off" class="settings-input font-mono font-bold" style="width: 200px;">
                            </div>

                            {{-- Row: IP Switch Core --}}
                            <div class="settings-row">
                                <div class="flex items-center gap-4">
                                    <div class="icon-black-pill anim-pulse-loop">
                                        <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83z" /> <path d="M2 12a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 12" /> <path d="M2 17a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 17" /></svg>
                                    </div>
                                    <div>
                                        <div class="settings-row-label">IP del Switch Core</div>
                                        <div class="settings-row-desc">IP del equipo centralizado para rastreo CDP / Topología</div>
                                    </div>
                                </div>
                                <input type="text" name="ip_switch_core" value="{{ $settings->ip_switch_core }}" class="settings-input font-mono font-bold" placeholder="10.4.254.3" style="width: 200px;">
                            </div>
                        </div>

                        {{-- Grupo 2: Umbrales de Alerta --}}
                        <div class="settings-group mb-0">
                            <h4 class="settings-group-title">
                                <svg class="w-4 h-4 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3" /> <path d="M12 9v4" /> <path d="M12 17h.01" /></svg>
                                Umbrales de Alerta de Rendimiento
                            </h4>
                            
                            {{-- Row: CPU Warning --}}
                            <div class="settings-row">
                                <div class="flex items-center gap-4">
                                    <div class="icon-black-pill anim-pulse-loop">
                                        <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="16" x="4" y="4" rx="2" /> <rect width="6" height="6" x="9" y="9" rx="1" /> <path d="M15 2v2" /> <path d="M15 20v2" /> <path d="M2 15h2" /> <path d="M2 9h2" /> <path d="M20 15h2" /> <path d="M20 9h2" /> <path d="M9 2v2" /> <path d="M9 20v2" /></svg>
                                    </div>
                                    <div>
                                        <div class="settings-row-label">Warning CPU</div>
                                        <div class="settings-row-desc">Marca el dispositivo en advertencia si el uso supera este valor</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="number" name="umbral_cpu_warning" value="{{ $settings->umbral_cpu_warning }}" min="1" max="100" class="settings-input text-right font-mono font-bold" style="width: 120px;">
                                    <span class="text-xs font-bold text-slate-400">%</span>
                                </div>
                            </div>

                            {{-- Row: Packet Loss Warning --}}
                            <div class="settings-row">
                                <div class="flex items-center gap-4">
                                    <div class="icon-black-pill anim-float-loop">
                                        <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17" /> <polyline points="16 7 22 7 22 13" /></svg>
                                    </div>
                                    <div>
                                        <div class="settings-row-label">Warning Packet Loss</div>
                                        <div class="settings-row-desc">Marca advertencia si la pérdida de paquetes ICMP supera este umbral</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="number" name="umbral_loss_warning" value="{{ $settings->umbral_loss_warning }}" min="1" max="100" class="settings-input text-right font-mono font-bold" style="width: 120px;">
                                    <span class="text-xs font-bold text-slate-400">%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Grupo 3: Retención de históricos --}}
                    <div class="settings-group mt-6 mb-0">
                        <h4 class="settings-group-title">
                            <svg class="w-4 h-4 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3" /> <path d="M3 5V19A9 3 0 0 0 21 19V5" /> <path d="M3 12A9 3 0 0 0 21 12" /></svg>
                            Retención de históricos
                        </h4>
                        <p class="settings-row-desc mb-2">Lo más antiguo que estos días se borra automáticamente (una vez por hora). Deja un campo vacío para usar el valor predeterminado.</p>

                        @foreach($retencion as $r)
                            <div class="settings-row">
                                <div class="flex items-center gap-4">
                                    <div class="icon-black-pill anim-spin-hover">
                                        <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4" /> <path d="M16 2v4" /> <rect width="18" height="18" x="3" y="4" rx="2" /> <path d="M3 10h18" /></svg>
                                    </div>
                                    <div>
                                        <div class="settings-row-label">{{ $r['titulo'] }}</div>
                                        <div class="settings-row-desc">
                                            {{ $r['descripcion'] }}<br>
                                            Hoy: {{ number_format($r['registros']) }} registros
                                            @if($r['desde']) · desde {{ \Carbon\Carbon::parse($r['desde'])->format('d/m/Y') }} @endif
                                            · se conservan {{ $r['efectivo'] }} días
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="number" name="{{ $r['campo'] }}" value="{{ old($r['campo'], $r['valor']) }}"
                                           min="{{ $r['min'] }}" max="{{ $r['max'] }}" placeholder="{{ $r['predeterminado'] }}"
                                           title="Entre {{ $r['min'] }} y {{ $r['max'] }} días"
                                           class="settings-input text-right font-mono font-bold" style="width: 120px;">
                                    <span class="text-xs font-bold text-slate-400">días</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex justify-end mt-6">
                        <button type="submit" class="btn-black-executive flex items-center gap-2.5">
                            <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                            <span>Guardar Parámetros</span>
                        </button>
                    </div>
                </form>
            </div>

            {{-- ========== SECTION: Cuentas ========== --}}
            <div class="settings-section {{ $section === 'cuentas' ? 'active' : '' }}" id="section-cuentas" data-section-name="Cuentas">
                <div class="flex items-center gap-3.5 mb-2">
                    <div class="icon-black-badge anim-bounce-hover">
                        <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /> <circle cx="9" cy="7" r="4" /> <path d="M22 21v-2a4 4 0 0 0-3-3.87" /> <path d="M16 3.13a4 4 0 0 1 0 7.75" /></svg>
                    </div>
                    <div>
                        <h1 class="settings-section-title mb-0">Cuentas de Usuario</h1>
                        <p class="settings-section-subtitle mb-0">Administra los operadores del sistema. Registra nuevas cuentas y gestiona roles de acceso.</p>
                    </div>
                </div>

                {{-- Current User Session & Logout Card --}}
                <div class="bg-gradient-to-r from-slate-100/90 to-slate-200/50 dark:from-nm-sunken dark:to-nm-surface rounded-2xl p-5 sm:p-6 mb-6 border border-slate-300/70 dark:border-slate-800/60 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
                    <div class="flex items-center gap-4">
                        <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" class="w-13 h-13 rounded-full object-cover border-2 border-black/30 dark:border-white/30 shadow-md flex-shrink-0" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'Admin') }}&background=09090b&color=fff&size=80';">
                        <div>
                            <div class="flex items-center gap-2.5">
                                <h3 class="text-base font-extrabold text-slate-900 dark:text-white">
                                    @if(auth()->user()->prefijo)<span class="text-black dark:text-white font-black">{{ auth()->user()->prefijo }}</span> @endif{{ auth()->user()->name }}
                                </h3>
                                <span class="text-[11px] font-black px-3 py-1 rounded-full bg-blue-600 text-white uppercase tracking-wider">{{ auth()->user()->role }}</span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5">{{ auth()->user()->email }} · <span class="text-emerald-600 dark:text-emerald-400 font-bold">● Sesión Activa</span></p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-red-50 dark:bg-red-950/40 hover:bg-red-100 dark:hover:bg-red-900/50 text-red-600 dark:text-red-400 text-xs font-bold transition border border-red-200 dark:border-red-800/40 shadow-xs">
                            <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /> <polyline points="16 17 21 12 16 7" /> <line x1="21" x2="9" y1="12" y2="12" /></svg>
                            Cerrar Sesión
                        </button>
                    </form>
                </div>

                {{-- Register New Account Card --}}
                <div class="register-card mb-6">
                    <div class="flex items-center gap-3.5 mb-5">
                        <div class="icon-black-pill anim-bounce-hover">
                            <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /> <circle cx="9" cy="7" r="4" /> <line x1="19" x2="19" y1="8" y2="14" /> <line x1="22" x2="16" y1="11" y2="11" /></svg>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-slate-800 dark:text-white">Registrar Nueva Cuenta</h3>
                            <p class="text-xs text-slate-400">Completa los datos para crear un nuevo operador en el sistema NOC</p>
                        </div>
                    </div>

                    <form action="{{ route('settings.register') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        {{-- Row 1: Avatar + Name + Prefijo + Email --}}
                        <div class="flex flex-col lg:flex-row gap-5 mb-5">
                            {{-- Avatar Upload --}}
                            <div class="flex flex-col items-center justify-center p-3 rounded-2xl bg-white/70 dark:bg-black/40 border border-slate-200 dark:border-slate-800/60" style="min-width: 120px;">
                                <label class="form-label text-center mb-2">
                                    <svg class="w-3.5 h-3.5 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z" /> <circle cx="12" cy="13" r="3" /></svg>
                                    Foto <span class="text-slate-400 font-normal">(opc.)</span>
                                </label>
                                <label for="avatarUpload" class="cursor-pointer group relative">
                                    <div class="w-18 h-18 rounded-full bg-slate-100 dark:bg-slate-800 border-2 border-dashed border-slate-300 dark:border-slate-700 group-hover:border-black dark:group-hover:border-white flex items-center justify-center overflow-hidden transition" id="avatarPreviewContainer">
                                        <svg id="avatarPlaceholderIcon" class="w-7 h-7 text-slate-400 group-hover:text-black dark:group-hover:text-white transition" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"></path></svg>
                                        <img id="avatarPreviewImg" src="" alt="Preview" class="w-full h-full object-cover hidden">
                                    </div>
                                    <input type="file" name="avatar" id="avatarUpload" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="previewAvatar(this)">
                                </label>
                                <p class="text-[11px] text-slate-400 mt-1.5 text-center font-medium">JPG/PNG · 2MB</p>
                            </div>

                            {{-- Name + Prefijo + Email --}}
                            <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-5">
                                <div>
                                    <label class="form-label">
                                        <svg class="w-3.5 h-3.5 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" /> <circle cx="12" cy="7" r="4" /></svg>
                                        Nombre completo
                                    </label>
                                    <input type="text" name="name" required placeholder="Ej. Juan Pérez" class="form-input" value="{{ old('name') }}">
                                </div>
                                <div>
                                    <label class="form-label">
                                        <svg class="w-3.5 h-3.5 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z" /> <circle cx="7.5" cy="7.5" r=".5" fill="currentColor" /></svg>
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
                                        <svg class="w-3.5 h-3.5 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2" /> <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" /></svg>
                                        Correo electrónico
                                    </label>
                                    <input type="email" name="email" required placeholder="usuario@dominio.com" class="form-input" value="{{ old('email') }}">
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
                            <div>
                                <label class="form-label">
                                    <svg class="w-3.5 h-3.5 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2" /> <path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
                                    Contraseña
                                </label>
                                <input type="password" name="password" required placeholder="Mín. 8 caracteres" class="form-input">
                            </div>
                            <div>
                                <label class="form-label">
                                    <svg class="w-3.5 h-3.5 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" /> <path d="m9 12 2 2 4-4" /></svg>
                                    Confirmar Contraseña
                                </label>
                                <input type="password" name="password_confirmation" required placeholder="Repetir contraseña" class="form-input">
                            </div>
                            <div>
                                <label class="form-label">
                                    <svg class="w-3.5 h-3.5 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" /> <path d="m9 12 2 2 4-4" /></svg>
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
                            <button type="submit" class="btn-black-executive flex items-center gap-2">
                                <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /> <circle cx="9" cy="7" r="4" /> <line x1="19" x2="19" y1="8" y2="14" /> <line x1="22" x2="16" y1="11" y2="11" /></svg>
                                <span>Crear Cuenta</span>
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Existing Users Table --}}
                <div class="settings-group" style="padding: 0; overflow: hidden;">
                    <div class="px-6 py-4 flex items-center justify-between border-b border-slate-200 dark:border-slate-800/60 bg-[#f8fafc] dark:bg-nm-sunken">
                        <h4 class="text-xs uppercase tracking-wider font-extrabold text-slate-500 dark:text-slate-400 flex items-center gap-2.5">
                            <svg class="w-4 h-4 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 21a8 8 0 0 0-16 0" /> <circle cx="10" cy="8" r="5" /> <path d="M22 20c0-3.37-2-6.5-4-8a5 5 0 0 0-.45-8.3" /></svg>
                            Usuarios Registrados
                        </h4>
                        <span class="text-xs font-black text-black dark:text-white bg-slate-200/80 dark:bg-zinc-800 px-3 py-1 rounded-full border border-slate-300 dark:border-zinc-700">{{ $users->count() }} total</span>
                    </div>

                    <div class="overflow-x-auto">
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
                                        <div class="flex items-center gap-3.5">
                                            <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="w-9 h-9 rounded-full flex-shrink-0 object-cover border border-slate-300 dark:border-slate-700" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=09090b&color=fff&size=64';">
                                            <div>
                                                <p class="font-extrabold text-slate-800 dark:text-white text-sm">
                                                    @if($user->prefijo)<span class="text-black dark:text-white font-black">{{ $user->prefijo }}</span> @endif{{ $user->name }}
                                                </p>
                                                @if($user->id === auth()->id())
                                                    <p class="text-[11px] text-zinc-900 dark:text-zinc-300 font-extrabold">Tú (Sesión actual)</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="font-mono text-slate-600 dark:text-slate-400 text-xs font-semibold">{{ $user->email }}</td>
                                    <td>
                                        <span class="text-xs font-extrabold rounded-lg py-1 px-3 inline-block {{ strtolower($user->role) === 'admin' ? 'role-admin' : (strtolower($user->role) === 'operador' ? 'role-operador' : 'role-auditor') }}">
                                            {{ $user->role }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex items-center justify-center gap-2">
                                            {{-- Ver --}}
                                            <button type="button" onclick="openViewModal({{ $user->id }})" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-white hover:bg-slate-200 dark:hover:bg-zinc-700 transition border border-slate-200 dark:border-zinc-700" title="Ver detalles">
                                                <svg class="w-3.5 h-3.5 mr-1" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0" /> <circle cx="12" cy="12" r="3" /></svg>
                                                Ver
                                            </button>
                                            {{-- Editar --}}
                                            <button type="button" onclick="openEditModal({{ $user->id }})" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-bold bg-blue-600 text-white hover:opacity-90 transition border border-black dark:border-white" title="Editar usuario">
                                                <svg class="w-3.5 h-3.5 mr-1" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z" /> <path d="m15 5 4 4" /></svg>
                                                Editar
                                            </button>
                                            {{-- Eliminar --}}
                                            @if($user->id !== auth()->id())
                                                <form action="{{ route('settings.delete_user', $user->id) }}" method="POST" class="inline" onsubmit="event.preventDefault(); nmConfirmar('¿Eliminar al usuario {{ addslashes($user->name) }}? Perderá el acceso de inmediato.', { titulo: 'Eliminar usuario', boton: 'Eliminar' }).then(ok => ok && this.submit());">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-bold bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-900/50 transition border border-rose-200 dark:border-rose-800/40" title="Eliminar cuenta" aria-label="Eliminar cuenta">
                                                        <svg class="w-3.5 h-3.5 mr-1" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18" /> <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" /> <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" /> <line x1="10" x2="10" y1="11" y2="17" /> <line x1="14" x2="14" y1="11" y2="17" /></svg>
                                                        Eliminar
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-[11px] text-slate-400 italic px-2">—</span>
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
                </div>

                {{-- ==================== VIEW MODAL (Detail Card) ==================== --}}
                <div id="viewModal" class="fixed inset-0 z-50 hidden">
                    <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm" onclick="closeViewModal()"></div>
                    <div class="absolute right-0 top-0 h-full w-full max-w-md bg-white dark:bg-nm-surface shadow-2xl transform transition-transform duration-300 translate-x-full flex flex-col border-l border-slate-200 dark:border-slate-800" id="viewModalPanel">
                        {{-- Header --}}
                        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-800">
                            <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Detalle de Usuario</h3>
                            <button onclick="closeViewModal()" class="w-8 h-8 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center transition text-slate-400">
                                <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18" /> <path d="m6 6 12 12" /></svg>
                            </button>
                        </div>
                        {{-- Body --}}
                        <div class="flex-1 overflow-y-auto px-6 py-6 custom-scrollbar">
                            <div class="flex flex-col items-center mb-6">
                                <img id="viewAvatar" src="" alt="Avatar" class="w-22 h-22 rounded-full object-cover border-4 border-slate-100 dark:border-slate-800 shadow-md mb-3">
                                <h4 id="viewFullName" class="text-lg font-extrabold text-slate-900 dark:text-white"></h4>
                                <span id="viewRoleBadge" class="text-xs font-extrabold px-3.5 py-1 rounded-full mt-1.5"></span>
                            </div>
                            <div class="space-y-4">
                                <div class="settings-group" style="margin-bottom: 0;">
                                    <div class="settings-row">
                                        <div class="settings-row-label">Correo</div>
                                        <span id="viewEmail" class="text-sm font-mono text-slate-600 dark:text-slate-300"></span>
                                    </div>
                                    <div class="settings-row">
                                        <div class="settings-row-label">Prefijo</div>
                                        <span id="viewPrefijo" class="text-sm font-bold text-black dark:text-white"></span>
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
                            <button id="viewModalEditBtn" onclick="editFromViewModal()" class="flex-1 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-sm font-bold text-white transition flex items-center justify-center gap-1.5 shadow-sm">
                                <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z" /> <path d="m15 5 4 4" /></svg>
                                Editar
                            </button>
                        </div>
                    </div>
                </div>

                {{-- ==================== EDIT MODAL (Slide-over) ==================== --}}
                <div id="editModal" class="fixed inset-0 z-50 hidden">
                    <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm" onclick="closeEditModal()"></div>
                    <div class="absolute right-0 top-0 h-full w-full max-w-lg bg-white dark:bg-nm-surface shadow-2xl transform transition-transform duration-300 translate-x-full flex flex-col border-l border-slate-200 dark:border-slate-800" id="editModalPanel">
                        {{-- Header --}}
                        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-zinc-900/40">
                            <div class="flex items-center gap-3">
                                <div class="icon-black-pill-sm anim-wiggle-hover">
                                    <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z" /> <path d="m15 5 4 4" /></svg>
                                </div>
                                <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Editar Usuario</h3>
                            </div>
                            <button onclick="closeEditModal()" class="w-8 h-8 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center transition text-slate-400">
                                <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18" /> <path d="m6 6 12 12" /></svg>
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
                                        <div class="w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 border-2 border-dashed border-slate-300 dark:border-slate-700 group-hover:border-black dark:group-hover:border-white flex items-center justify-center overflow-hidden transition" id="editAvatarContainer">
                                            <img id="editAvatarPreview" src="" alt="Avatar" class="w-full h-full object-cover">
                                        </div>
                                        <div class="absolute -bottom-0.5 -right-0.5 w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center shadow-md">
                                            <svg class="w-3 h-3" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z" /> <circle cx="12" cy="13" r="3" /></svg>
                                        </div>
                                        <input type="file" name="avatar" id="editAvatarUpload" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="previewEditAvatar(this)">
                                    </label>
                                    <div class="flex-1">
                                        <p class="text-sm font-bold text-slate-800 dark:text-slate-200">Foto de perfil</p>
                                        <p class="text-xs text-slate-400">JPG, PNG o WebP · Máx 2MB</p>
                                        <label class="inline-flex items-center gap-1.5 mt-1.5 cursor-pointer">
                                            <input type="checkbox" name="remove_avatar" id="editRemoveAvatar" class="rounded border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-red-500 focus:ring-red-400 w-3.5 h-3.5">
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
                                <button type="submit" class="btn-black-executive flex items-center gap-2">
                                    <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                                    <span>Guardar Cambios</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ========== SECTION: Seguridad ========== --}}
            <div class="settings-section {{ $section === 'seguridad' ? 'active' : '' }}" id="section-seguridad" data-section-name="Seguridad">
                <div class="flex items-center gap-3.5 mb-2">
                    <div class="icon-black-badge anim-wiggle-hover">
                        <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" /> <path d="m9 12 2 2 4-4" /></svg>
                    </div>
                    <div>
                        <h1 class="settings-section-title mb-0">Seguridad y Control de Acceso</h1>
                        <p class="settings-section-subtitle mb-0">Configuraciones de autenticación de dos factores, sesiones y roles de usuario.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 2xl:grid-cols-2 gap-6 items-start mt-6">
                    <div class="settings-group mb-0">
                        <h4 class="settings-group-title">
                            <svg class="w-4 h-4 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2" /> <path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
                            Autenticación y Sesión
                        </h4>
                        
                        {{-- Row: 2FA --}}
                        <div class="settings-row">
                            <div class="flex items-center gap-4">
                                <div class="icon-black-pill anim-wiggle-hover">
                                    <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" /> <path d="m9 12 2 2 4-4" /></svg>
                                </div>
                                <div>
                                    <div class="settings-row-label">Autenticación de Dos Factores (2FA)</div>
                                    <div class="settings-row-desc">Protege tu cuenta requiriendo un código TOTP (Google Authenticator)</div>
                                </div>
                            </div>
                            <a href="{{ route('setup-2fa') }}" class="btn-black-executive" style="padding: 0.55rem 1.25rem; font-size: 0.775rem;">
                                <span>Configurar</span>
                                <svg class="w-3.5 h-3.5 ml-1.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6" /></svg>
                            </a>
                        </div>

                        {{-- Row: Session Timeout --}}
                        <div class="settings-row">
                            <div class="flex items-center gap-4">
                                <div class="icon-black-pill anim-spin-hover">
                                    <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <polyline points="12 6 12 12 16 14" /></svg>
                                </div>
                                <div>
                                    <div class="settings-row-label">Tiempo de Expiración de Sesión</div>
                                    <div class="settings-row-desc">Duración máxima de inactividad antes de requerir reautenticación</div>
                                </div>
                            </div>
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200 font-mono bg-slate-200/80 dark:bg-slate-800 px-3.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700">120 min</span>
                        </div>

                        {{-- Row: Password Confirmation --}}
                        <div class="settings-row">
                            <div class="flex items-center gap-4">
                                <div class="icon-black-pill anim-bounce-hover">
                                    <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2" /> <path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
                                </div>
                                <div>
                                    <div class="settings-row-label">Confirmación de Contraseña</div>
                                    <div class="settings-row-desc">Protección reforzada antes de acceder a áreas administrativas críticas</div>
                                </div>
                            </div>
                            <span class="text-xs font-black px-3.5 py-1 rounded-full bg-blue-600 text-white flex items-center gap-1.5 shadow-xs">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                Activo
                            </span>
                        </div>
                    </div>

                    <div class="settings-group mb-0">
                        <h4 class="settings-group-title">
                            <svg class="w-4 h-4 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 21a8 8 0 0 0-16 0" /> <circle cx="10" cy="8" r="5" /> <path d="M22 20c0-3.37-2-6.5-4-8a5 5 0 0 0-.45-8.3" /></svg>
                            Matriz de Control de Acceso (RBAC)
                        </h4>
                        
                        <div class="settings-row">
                            <div class="flex items-center gap-4">
                                <div class="icon-black-pill anim-wiggle-hover">
                                    <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" /> <path d="m9 12 2 2 4-4" /></svg>
                                </div>
                                <div>
                                    <div class="settings-row-label">Administrador (Admin)</div>
                                    <div class="settings-row-desc">Control total del NOC, gestión de usuarios, SSH Terminal y ajustes</div>
                                </div>
                            </div>
                            <span class="text-xs font-extrabold px-3 py-1 rounded-full role-admin">Full Access</span>
                        </div>

                        <div class="settings-row">
                            <div class="flex items-center gap-4">
                                <div class="icon-black-pill anim-float-loop">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                </div>
                                <div>
                                    <div class="settings-row-label">Operador (Operator)</div>
                                    <div class="settings-row-desc">Monitoreo activo del Dashboard, Topología y Estado de Enlaces</div>
                                </div>
                            </div>
                            <span class="text-xs font-extrabold px-3 py-1 rounded-full role-operador">Operaciones</span>
                        </div>

                        <div class="settings-row">
                            <div class="flex items-center gap-4">
                                <div class="icon-black-pill anim-bounce-hover">
                                    <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1" /> <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" /> <path d="M12 11h4" /> <path d="M12 16h4" /> <path d="M8 11h.01" /> <path d="M8 16h.01" /></svg>
                                </div>
                                <div>
                                    <div class="settings-row-label">Auditor (Compliance)</div>
                                    <div class="settings-row-desc">Acceso a logs, reportes de cumplimiento e histórico de comandos ejecutados</div>
                                </div>
                            </div>
                            <span class="text-xs font-extrabold px-3 py-1 rounded-full role-auditor">Solo Lectura</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ========== SECTION: Notificaciones ========== --}}
            <div class="settings-section {{ $section === 'notificaciones' ? 'active' : '' }}" id="section-notificaciones" data-section-name="Notificaciones">
                <div class="flex items-center gap-3.5 mb-2">
                    <div class="icon-black-badge anim-bell-ring">
                        <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10.268 21a2 2 0 0 0 3.464 0" /> <path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326" /></svg>
                    </div>
                    <div>
                        <h1 class="settings-section-title mb-0">Canales de Alerta y Notificaciones</h1>
                        <p class="settings-section-subtitle mb-0">Gestiona cómo recibes alertas críticas y notificaciones del estado de la red.</p>
                    </div>
                </div>

                <div class="settings-group mt-6">
                    <h4 class="settings-group-title">
                        <svg class="w-4 h-4 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10.268 21a2 2 0 0 0 3.464 0" /> <path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326" /></svg>
                        Canales de Transmisión
                    </h4>
                    
                    <div class="settings-row">
                        <div class="flex items-center gap-4">
                            <div class="icon-black-pill anim-float-loop">
                                <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2" /> <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" /></svg>
                            </div>
                            <div>
                                <div class="settings-row-label">Alertas por Correo Electrónico (SMTP)</div>
                                <div class="settings-row-desc">Envío de resumen y alertas cuando un equipo cae a estado OFFLINE</div>
                            </div>
                        </div>
                        <span class="text-xs font-bold px-3 py-1 rounded-full bg-slate-200/80 dark:bg-slate-800 text-slate-600 dark:text-slate-400">Próximamente</span>
                    </div>

                    <div class="settings-row">
                        <div class="flex items-center gap-4">
                            <div class="icon-black-pill anim-flash-hover">
                                <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z" /></svg>
                            </div>
                            <div>
                                <div class="settings-row-label">Webhook / Slack / Teams</div>
                                <div class="settings-row-desc">Publicación automática de incidentes de red en canal de chat NOC</div>
                            </div>
                        </div>
                        <span class="text-xs font-bold px-3 py-1 rounded-full bg-slate-200/80 dark:bg-slate-800 text-slate-600 dark:text-slate-400">Próximamente</span>
                    </div>

                    <div class="settings-row">
                        <div class="flex items-center gap-4">
                            <div class="icon-black-pill anim-bell-ring">
                                <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10.268 21a2 2 0 0 0 3.464 0" /> <path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326" /></svg>
                            </div>
                            <div>
                                <div class="settings-row-label">Notificaciones en Dashboard en Tiempo Real</div>
                                <div class="settings-row-desc">Indicador sonoro y marquesina NOC en la barra superior</div>
                            </div>
                        </div>
                        <span class="text-xs font-black px-3.5 py-1 rounded-full bg-blue-600 text-white flex items-center gap-1.5 shadow-xs">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            Activo
                        </span>
                    </div>
                </div>

                {{-- Alertas por Telegram --}}
                <form action="{{ route('settings.telegram') }}" method="POST" class="settings-group" x-data="telegramAjustes()">
                    @csrf
                    <h4 class="settings-group-title">
                        <svg class="w-4 h-4 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z" /> <path d="m21.854 2.147-10.94 10.939" /></svg>
                        Alertas por Telegram
                        @if($settings->telegram_activo)
                            <span class="ml-auto text-[11px] font-black px-3 py-0.5 rounded-full bg-blue-600 text-white flex items-center gap-1.5 normal-case tracking-normal">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Activo
                            </span>
                        @endif
                    </h4>

                    @if($telegram['ultimo_error'])
                        <div class="mb-3 p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-700 dark:text-rose-300 text-xs font-semibold">
                            Último envío fallido ({{ \Carbon\Carbon::parse($telegram['ultimo_error']['fecha'])->format('d/m/Y H:i') }}): {{ $telegram['ultimo_error']['mensaje'] }}
                        </div>
                    @endif

                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Enviar alertas a Telegram</div>
                            <div class="settings-row-desc">Alarmas nuevas y resueltas del NOC. Avisos enviados en las últimas 24 h: {{ $telegram['notificadas_24h'] }}</div>
                        </div>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="telegram_activo" value="1" class="w-5 h-5 accent-black" @checked(old('telegram_activo', $settings->telegram_activo))>
                            <span class="text-sm font-bold">Activar</span>
                        </label>
                    </div>

                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Token del bot</div>
                            <div class="settings-row-desc">
                                Créalo con <b>@BotFather</b> en Telegram (comando /newbot). Se guarda cifrado y no se vuelve a mostrar.
                                @if($telegram['token_guardado'])<br>Guardado: <span class="font-mono">{{ $telegram['token_guardado'] }}</span> · deja el campo vacío para conservarlo.@endif
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-1.5">
                            <input type="password" name="telegram_bot_token" x-model="token" autocomplete="off" placeholder="{{ $telegram['token_guardado'] ? 'Sin cambios' : '123456789:AA...' }}" class="settings-input font-mono" style="width: 300px;">
                            @if($telegram['token_guardado'])
                                <label class="text-[11px] text-slate-500 flex items-center gap-1.5"><input type="checkbox" name="telegram_borrar_token" value="1"> Borrar token guardado</label>
                            @endif
                        </div>
                    </div>

                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Chat ID</div>
                            <div class="settings-row-desc">Escribe cualquier mensaje al bot (o agrégalo a tu grupo y escribe en él) y pulsa <b>Detectar</b>.</div>
                            <template x-if="chats.length">
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <template x-for="c in chats" :key="c.id">
                                        <button type="button" @click="chatId = c.id" class="text-xs font-bold px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
                                                :class="chatId === c.id ? 'ring-2 ring-black dark:ring-white' : ''">
                                            <span x-text="c.nombre || c.id"></span> <span class="text-slate-400" x-text="'(' + c.tipo + ')'"></span>
                                        </button>
                                    </template>
                                </div>
                            </template>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="text" name="telegram_chat_id" x-model="chatId" autocomplete="off" placeholder="-1001234567890" class="settings-input font-mono font-bold" style="width: 200px;">
                            <button type="button" @click="detectar()" :disabled="cargando" class="text-xs font-bold px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50">Detectar</button>
                        </div>
                    </div>

                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Qué alarmas enviar</div>
                            <div class="settings-row-desc">Si llegan más de 5 a la vez (p. ej. una caída masiva) se envía un solo resumen.</div>
                        </div>
                        <select name="telegram_severidad_min" class="settings-input font-bold" style="width: 240px;">
                            <option value="critica" @selected(old('telegram_severidad_min', $settings->telegram_severidad_min) === 'critica')>Solo críticas</option>
                            <option value="advertencia" @selected(old('telegram_severidad_min', $settings->telegram_severidad_min) === 'advertencia')>Críticas y advertencias</option>
                        </select>
                    </div>

                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Opciones</div>
                            <div class="settings-row-desc">La gráfica muestra latencia y pérdida de las últimas 3 h del equipo, con el inicio de la alarma marcado.</div>
                        </div>
                        <div class="flex flex-col gap-2 text-sm font-semibold">
                            <label class="flex items-center gap-2"><input type="checkbox" name="telegram_imagenes" value="1" class="w-4 h-4 accent-black" @checked(old('telegram_imagenes', $settings->telegram_imagenes))> Adjuntar gráfica del equipo</label>
                            <label class="flex items-center gap-2"><input type="checkbox" name="telegram_resueltas" value="1" class="w-4 h-4 accent-black" @checked(old('telegram_resueltas', $settings->telegram_resueltas))> Avisar cuando se resuelvan</label>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-3 mt-5">
                        <span class="text-xs font-semibold mr-auto" :class="ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'" x-text="aviso"></span>
                        <button type="button" @click="probar()" :disabled="cargando" class="text-sm font-bold px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50"
                                title="Envía un mensaje de prueba con la configuración guardada" aria-label="Envía un mensaje de prueba con la configuración guardada">Enviar prueba</button>
                        <button type="submit" class="btn-black-executive flex items-center gap-2.5">
                            <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                            <span>Guardar Telegram</span>
                        </button>
                    </div>
                </form>
                <script>
                    function telegramAjustes() {
                        const llamar = async (url, datos) => {
                            const r = await fetch(url, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                                body: JSON.stringify(datos || {}),
                            });
                            const j = await r.json().catch(() => ({ ok: false, mensaje: 'Respuesta inválida del servidor (HTTP ' + r.status + ').' }));
                            if (r.status === 429) j.mensaje = 'Demasiados intentos, espera un minuto.';
                            return j;
                        };
                        return {
                            token: '', chatId: @json(old('telegram_chat_id', $settings->telegram_chat_id) ?? ''), chats: [], aviso: '', ok: true, cargando: false,
                            async detectar() {
                                this.cargando = true; this.aviso = 'Consultando al bot…'; this.ok = true;
                                const j = await llamar(@json(route('settings.telegram.chats')), { token: this.token });
                                this.cargando = false; this.ok = !!j.ok;
                                if (!j.ok) { this.aviso = j.mensaje; return; }
                                this.chats = j.chats;
                                if (j.chats.length === 1) this.chatId = j.chats[0].id;
                                this.aviso = j.chats.length
                                    ? `Bot ${j.bot}: ${j.chats.length} chat(s) encontrados. Elige uno y guarda.`
                                    : `Bot ${j.bot} válido, pero nadie le ha escrito en las últimas 24 h. Escríbele un mensaje y vuelve a pulsar Detectar.`;
                            },
                            async probar() {
                                this.cargando = true; this.aviso = 'Enviando…'; this.ok = true;
                                const j = await llamar(@json(route('settings.telegram.probar')));
                                this.cargando = false; this.ok = !!j.ok; this.aviso = j.mensaje;
                            },
                        };
                    }
                </script>
            </div>

            {{-- ========== SECTION: Apariencia ========== --}}
            <div class="settings-section {{ $section === 'apariencia' ? 'active' : '' }}" id="section-apariencia" data-section-name="Apariencia" x-data="appearanceManager()" x-init="init()">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                    <div class="flex items-center gap-3.5">
                        <div class="icon-black-badge anim-float-loop">
                            <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor" /> <circle cx="17.5" cy="10.5" r=".5" fill="currentColor" /> <circle cx="8.5" cy="7.5" r=".5" fill="currentColor" /> <circle cx="6.5" cy="12.5" r=".5" fill="currentColor" /> <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z" /></svg>
                        </div>
                        <div>
                            <h1 class="settings-section-title mb-0">Apariencia del Sistema</h1>
                            <p class="settings-section-subtitle mb-0">Gestiona el tema visual y la ergonomía cromática de la consola NOC.</p>
                        </div>
                    </div>

                    {{-- Dynamic Status Pill --}}
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-xs font-extrabold border transition shadow-xs"
                         :class="isDarkActive 
                            ? 'bg-black border-slate-700 text-white' 
                            : 'bg-slate-100 border-slate-300 text-black'">
                        <span class="w-2 h-2 rounded-full" :class="isDarkActive ? 'bg-white shadow-[0_0_8px_rgba(255,255,255,0.8)]' : 'bg-black'"></span>
                        <span x-text="'Modo Activo: ' + (currentTheme === 'light' ? 'Claro (Diurno)' : (currentTheme === 'dark' ? 'Oscuro (NOC OLED)' : 'Automático (' + (isDarkActive ? 'Oscuro' : 'Claro') + ')'))"></span>
                    </div>
                </div>

                {{-- Tarjeta Principal: Selector de Tema de Visualización --}}
                <div class="settings-group mb-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
                        <div>
                            <h4 class="settings-group-title mb-1">
                                <svg class="w-4 h-4 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2" /> <circle cx="9" cy="9" r="2" /> <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" /></svg>
                                Tema de Visualización
                            </h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Selecciona el modo cromático preferido para la estación de trabajo.</p>
                        </div>

                        {{-- Segmented Quick Switcher --}}
                        <div class="inline-flex p-1 rounded-xl bg-slate-200 dark:bg-[#000000] border border-slate-300 dark:border-slate-800 shadow-inner self-start sm:self-auto">
                            <button type="button" @click="selectTheme('light')"
                                    :class="currentTheme === 'light' ? 'bg-white dark:bg-zinc-800 text-black dark:text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-black dark:hover:text-white'"
                                    class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4" /> <path d="M12 2v2" /> <path d="M12 20v2" /> <path d="m4.93 4.93 1.41 1.41" /> <path d="m17.66 17.66 1.41 1.41" /> <path d="M2 12h2" /> <path d="M20 12h2" /> <path d="m6.34 17.66-1.41 1.41" /> <path d="m19.07 4.93-1.41 1.41" /></svg>
                                Claro
                            </button>
                            <button type="button" @click="selectTheme('dark')"
                                    :class="currentTheme === 'dark' ? 'bg-white dark:bg-zinc-800 text-black dark:text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-black dark:hover:text-white'"
                                    class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z" /></svg>
                                Oscuro
                            </button>
                            <button type="button" @click="selectTheme('system')"
                                    :class="currentTheme === 'system' ? 'bg-white dark:bg-zinc-800 text-black dark:text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-black dark:hover:text-white'"
                                    class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="3" rx="2" /> <line x1="8" x2="16" y1="21" y2="21" /> <line x1="12" x2="12" y1="17" y2="21" /></svg>
                                Sistema
                            </button>
                        </div>
                    </div>

                    {{-- 3-Cards Grid Preview --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mt-4">
                        {{-- Card 1: Modo Claro --}}
                        <div @click="selectTheme('light')"
                             :class="currentTheme === 'light' 
                                ? 'border-black dark:border-white ring-2 ring-black/10 dark:ring-white/20 bg-slate-100/60 dark:bg-zinc-900/60' 
                                : 'border-slate-200 dark:border-slate-800/60 bg-white dark:bg-nm-sunken hover:border-slate-400 dark:hover:border-slate-600'"
                             class="rounded-2xl border p-5 cursor-pointer transition relative flex flex-col justify-between group shadow-xs">
                            
                            {{-- Checkmark indicator --}}
                            <div class="absolute top-4 right-4">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center transition"
                                     :class="currentTheme === 'light' ? 'bg-blue-600 text-white' : 'border border-slate-300 dark:border-slate-700 text-transparent'">
                                    <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                                </div>
                            </div>

                            <div>
                                {{-- Mini UI Preview Mockup (Light) --}}
                                <div class="rounded-xl bg-[#f1f5f9] p-3 border border-slate-300 mb-4 shadow-sm">
                                    <div class="flex items-center gap-1.5 pb-2 mb-2 border-b border-slate-300">
                                        <div class="w-2.5 h-2.5 rounded-full bg-slate-400"></div>
                                        <div class="w-2.5 h-2.5 rounded-full bg-slate-400"></div>
                                        <div class="w-2.5 h-2.5 rounded-full bg-slate-400"></div>
                                        <div class="w-16 h-2 rounded-full bg-slate-300 ml-auto"></div>
                                    </div>
                                    <div class="space-y-1.5">
                                        <div class="flex gap-2">
                                            <div class="w-7 h-11 rounded bg-black flex-shrink-0"></div>
                                            <div class="flex-1 space-y-1.5">
                                                <div class="h-4 bg-white rounded border border-slate-300"></div>
                                                <div class="grid grid-cols-2 gap-1.5">
                                                    <div class="h-5 bg-white rounded border border-slate-300"></div>
                                                    <div class="h-5 bg-white rounded border border-slate-300"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 mb-1.5">
                                    <svg class="w-4 h-4 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4" /> <path d="M12 2v2" /> <path d="M12 20v2" /> <path d="m4.93 4.93 1.41 1.41" /> <path d="m17.66 17.66 1.41 1.41" /> <path d="M2 12h2" /> <path d="M20 12h2" /> <path d="m6.34 17.66-1.41 1.41" /> <path d="m19.07 4.93-1.41 1.41" /></svg>
                                    <h5 class="font-extrabold text-sm text-slate-900 dark:text-white">Claro / Diurno</h5>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                    Fondos blancos limpios de alto contraste, ideal para oficinas iluminadas.
                                </p>
                            </div>

                            <div class="mt-4 pt-3 border-t border-slate-200 dark:border-slate-800/40 flex items-center justify-between">
                                <span class="text-[11px] font-mono font-bold text-slate-400">#FFFFFF / #F1F5F9</span>
                                <span class="text-[11px] font-black text-black dark:text-white" x-show="currentTheme === 'light'">Seleccionado</span>
                            </div>
                        </div>

                        {{-- Card 2: Modo Oscuro (NOC OLED) --}}
                        <div @click="selectTheme('dark')"
                             :class="currentTheme === 'dark' 
                                ? 'border-white dark:border-white ring-2 ring-black/20 dark:ring-white/20 bg-zinc-900 text-white' 
                                : 'border-slate-200 dark:border-slate-800/60 bg-white dark:bg-nm-sunken hover:border-slate-400 dark:hover:border-slate-600'"
                             class="rounded-2xl border p-5 cursor-pointer transition relative flex flex-col justify-between group shadow-xs">
                            
                            {{-- Checkmark indicator --}}
                            <div class="absolute top-4 right-4">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center transition"
                                     :class="currentTheme === 'dark' ? 'bg-white text-black' : 'border border-slate-300 dark:border-slate-700 text-transparent'">
                                    <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                                </div>
                            </div>

                            <div>
                                {{-- Mini UI Preview Mockup (Dark OLED) --}}
                                <div class="rounded-xl bg-[#000000] p-3 border border-slate-800 mb-4 shadow-sm">
                                    <div class="flex items-center gap-1.5 pb-2 mb-2 border-b border-slate-800">
                                        <div class="w-2.5 h-2.5 rounded-full bg-zinc-700"></div>
                                        <div class="w-2.5 h-2.5 rounded-full bg-zinc-700"></div>
                                        <div class="w-2.5 h-2.5 rounded-full bg-zinc-700"></div>
                                        <div class="w-16 h-2 rounded-full bg-zinc-800 ml-auto"></div>
                                    </div>
                                    <div class="space-y-1.5">
                                        <div class="flex gap-2">
                                            <div class="w-7 h-11 rounded bg-zinc-800 border border-zinc-700 flex-shrink-0"></div>
                                            <div class="flex-1 space-y-1.5">
                                                <div class="h-4 bg-nm-ink rounded border border-slate-800"></div>
                                                <div class="grid grid-cols-2 gap-1.5">
                                                    <div class="h-5 bg-nm-ink rounded border border-slate-800"></div>
                                                    <div class="h-5 bg-nm-ink rounded border border-slate-800"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 mb-1.5">
                                    <svg class="w-4 h-4 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z" /></svg>
                                    <h5 class="font-extrabold text-sm text-slate-900 dark:text-white">Oscuro / NOC OLED</h5>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                    Consola con negros profundos anti-fatiga para operaciones continuas 24/7.
                                </p>
                            </div>

                            <div class="mt-4 pt-3 border-t border-slate-200 dark:border-slate-800/40 flex items-center justify-between">
                                <span class="text-[11px] font-mono font-bold text-slate-400">#000000 / var(--nm-surface)</span>
                                <span class="text-[11px] font-black text-black dark:text-white" x-show="currentTheme === 'dark'">Seleccionado</span>
                            </div>
                        </div>

                        {{-- Card 3: Modo Automático / Sistema --}}
                        <div @click="selectTheme('system')"
                             :class="currentTheme === 'system' 
                                ? 'border-black dark:border-white ring-2 ring-black/10 dark:ring-white/20 bg-slate-100/60 dark:bg-zinc-900/60' 
                                : 'border-slate-200 dark:border-slate-800/60 bg-white dark:bg-nm-sunken hover:border-slate-400 dark:hover:border-slate-600'"
                             class="rounded-2xl border p-5 cursor-pointer transition relative flex flex-col justify-between group shadow-xs">
                            
                            {{-- Checkmark indicator --}}
                            <div class="absolute top-4 right-4">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center transition"
                                     :class="currentTheme === 'system' ? 'bg-blue-600 text-white' : 'border border-slate-300 dark:border-slate-700 text-transparent'">
                                    <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                                </div>
                            </div>

                            <div>
                                {{-- Mini UI Preview Mockup (Split Light/Dark) --}}
                                <div class="rounded-xl overflow-hidden border border-slate-300 dark:border-slate-800 mb-4 shadow-sm flex h-[66px]">
                                    {{-- Light half --}}
                                    <div class="w-1/2 bg-[#f1f5f9] p-2.5 border-r border-slate-300 dark:border-slate-700 flex flex-col justify-between">
                                        <div class="w-10 h-2 rounded bg-slate-300"></div>
                                        <div class="h-4 bg-white rounded border border-slate-300"></div>
                                    </div>
                                    {{-- Dark half --}}
                                    <div class="w-1/2 bg-[#000000] p-2.5 flex flex-col justify-between">
                                        <div class="w-10 h-2 rounded bg-zinc-800 ml-auto"></div>
                                        <div class="h-4 bg-nm-ink rounded border border-zinc-800"></div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 mb-1.5">
                                    <svg class="w-4 h-4 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="3" rx="2" /> <line x1="8" x2="16" y1="21" y2="21" /> <line x1="12" x2="12" y1="17" y2="21" /></svg>
                                    <h5 class="font-extrabold text-sm text-slate-900 dark:text-white">Automático (Sistema)</h5>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                    Sincroniza en tiempo real con la preferencia de color de tu sistema operativo.
                                </p>
                            </div>

                            <div class="mt-4 pt-3 border-t border-slate-200 dark:border-slate-800/40 flex items-center justify-between">
                                <span class="text-[11px] font-mono font-bold text-slate-400">matchMedia (OS)</span>
                                <span class="text-[11px] font-black text-black dark:text-white" x-show="currentTheme === 'system'">Seleccionado</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Paleta de Colores Institucional --}}
                <div class="settings-group">
                    <h4 class="settings-group-title">
                        <svg class="w-4 h-4 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor" /> <circle cx="17.5" cy="10.5" r=".5" fill="currentColor" /> <circle cx="8.5" cy="7.5" r=".5" fill="currentColor" /> <circle cx="6.5" cy="12.5" r=".5" fill="currentColor" /> <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z" /></svg>
                        Paleta de Colores Institucional NOC
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mt-3">
                        <div class="p-4 rounded-xl bg-white dark:bg-nm-sunken border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                            <div>
                                <div class="text-xs font-bold text-slate-800 dark:text-slate-200">Azul real (principal)</div>
                                <div class="text-[11px] text-slate-400 font-mono mt-0.5">#0168fa</div>
                            </div>
                            <div class="w-8 h-8 rounded-lg shadow-inner border border-slate-300 dark:border-slate-700" style="background: #0168fa;"></div>
                        </div>

                        <div class="p-4 rounded-xl bg-white dark:bg-nm-sunken border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                            <div>
                                <div class="text-xs font-bold text-slate-800 dark:text-slate-200">Marino</div>
                                <div class="text-[11px] text-slate-400 font-mono mt-0.5">#1a3d8f</div>
                            </div>
                            <div class="w-8 h-8 rounded-lg shadow-inner border border-slate-300 dark:border-slate-700" style="background: #1a3d8f;"></div>
                        </div>

                        <div class="p-4 rounded-xl bg-white dark:bg-nm-sunken border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                            <div>
                                <div class="text-xs font-bold text-slate-800 dark:text-slate-200">Naranja NOC</div>
                                <div class="text-[11px] text-slate-400 font-mono mt-0.5">#f07f12</div>
                            </div>
                            <div class="w-8 h-8 rounded-lg shadow-inner border border-slate-300 dark:border-slate-700" style="background: #f07f12;"></div>
                        </div>

                        <div class="p-4 rounded-xl bg-white dark:bg-nm-sunken border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                            <div>
                                <div class="text-xs font-bold text-slate-800 dark:text-slate-200">OLED Canvas</div>
                                <div class="text-[11px] text-slate-400 font-mono mt-0.5">var(--nm-sunken)</div>
                            </div>
                            <div class="w-8 h-8 rounded-lg shadow-inner border border-slate-300 dark:border-slate-700" style="background: var(--nm-sunken);"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ========== SECTION: Acerca de ========== --}}
            <div class="settings-section {{ $section === 'acerca' ? 'active' : '' }}" id="section-acerca" data-section-name="Acerca de">
                <div class="flex items-center gap-3.5 mb-2">
                    <div class="icon-black-badge anim-spin-hover">
                        <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <path d="M12 16v-4" /> <path d="M12 8h.01" /></svg>
                    </div>
                    <div>
                        <h1 class="settings-section-title mb-0">Acerca de la Plataforma</h1>
                        <p class="settings-section-subtitle mb-0">Información del sistema de monitoreo de infraestructura de red.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 2xl:grid-cols-2 gap-6 items-start mt-6">
                    <div class="settings-group mb-0">
                        <h4 class="settings-group-title">
                            <svg class="w-4 h-4 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="16" x="4" y="4" rx="2" /> <rect width="6" height="6" x="9" y="9" rx="1" /> <path d="M15 2v2" /> <path d="M15 20v2" /> <path d="M2 15h2" /> <path d="M2 9h2" /> <path d="M20 15h2" /> <path d="M20 9h2" /> <path d="M9 2v2" /> <path d="M9 20v2" /></svg>
                            Información del Entorno
                        </h4>
                        
                        <div class="settings-row">
                            <div class="flex items-center gap-4">
                                <div class="icon-black-pill anim-pulse-loop">
                                    <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z" /> <circle cx="7.5" cy="7.5" r=".5" fill="currentColor" /></svg>
                                </div>
                                <div class="settings-row-label">Nombre del Sistema</div>
                            </div>
                            <span class="text-sm font-extrabold text-slate-800 dark:text-slate-200">Network Monitor NOC</span>
                        </div>

                        <div class="settings-row">
                            <div class="flex items-center gap-4">
                                <div class="icon-black-pill anim-bounce-hover">
                                    <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                                </div>
                                <div class="settings-row-label">Versión de Producción</div>
                            </div>
                            <span class="text-xs font-black font-mono px-3 py-1 rounded-lg bg-blue-600 text-white">v2.4.0</span>
                        </div>

                        <div class="settings-row">
                            <div class="flex items-center gap-4">
                                <div class="icon-black-pill anim-float-loop">
                                    <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83z" /> <path d="M2 12a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 12" /> <path d="M2 17a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 17" /></svg>
                                </div>
                                <div class="settings-row-label">Framework Backend</div>
                            </div>
                            <span class="text-sm font-bold text-slate-700 dark:text-slate-300 font-mono">Laravel {{ app()->version() }}</span>
                        </div>

                        <div class="settings-row">
                            <div class="flex items-center gap-4">
                                <div class="icon-black-pill anim-spin-hover">
                                    <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6" /> <polyline points="8 6 2 12 8 18" /></svg>
                                </div>
                                <div class="settings-row-label">PHP Engine</div>
                            </div>
                            <span class="text-sm font-bold font-mono text-slate-700 dark:text-slate-300">{{ phpversion() }}</span>
                        </div>

                        <div class="settings-row">
                            <div class="flex items-center gap-4">
                                <div class="icon-black-pill anim-pulse-loop">
                                    <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" /> <path d="M21 3v5h-5" /> <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16" /> <path d="M8 16H3v5" /></svg>
                                </div>
                                <div class="settings-row-label">Workers Python en Background</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span class="text-xs font-bold font-mono text-slate-700 dark:text-slate-300">snmp_poller.py, ssh_executor.py</span>
                            </div>
                        </div>
                    </div>

                    <div class="settings-group mb-0">
                        <h4 class="settings-group-title">
                            <svg class="w-4 h-4 text-black dark:text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83z" /> <path d="M2 12a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 12" /> <path d="M2 17a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 17" /></svg>
                            Tecnologías Integradas
                        </h4>
                        <div class="flex flex-wrap gap-2.5 mt-2">
                            @foreach(['Laravel 12', 'Tailwind CSS', 'Alpine.js', 'xterm.js', 'PySNMP', 'Paramiko SSH', 'MySQL', 'Vite'] as $tech)
                                <span class="px-3.5 py-2 rounded-xl bg-white dark:bg-nm-sunken border border-slate-200 dark:border-slate-800 text-xs font-bold text-slate-800 dark:text-slate-200 shadow-xs flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-black dark:bg-white"></span>
                                    {{ $tech }}
                                </span>
                            @endforeach
                        </div>
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
                avatarImg.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=09090b&color=fff&size=160`;
            }

            // Name with prefix
            fullName.textContent = (user.prefijo ? user.prefijo + ' ' : '') + user.name;

            // Role badge class & label
            roleBadge.textContent = user.role;
            roleBadge.className = 'text-xs font-extrabold px-3 py-1 rounded-full mt-1 ' + (
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
                avatarPreview.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=09090b&color=fff&size=128`;
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
