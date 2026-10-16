@extends('layouts.app')

@section('styles')
    <style>
        /* ======================== WIDE EXECUTIVE SETTINGS LAYOUT ======================== */
        .settings-container {
            display: flex;
            gap: 0;
            width: 100%;
            height: calc(100vh - 4.5rem);
            min-height: 750px;
            background: white;
            border-radius: 1.5rem;
            overflow: hidden;
            box-shadow: 0 12px 36px -4px rgba(0, 0, 0, 0.06), 0 4px 12px -2px rgba(0, 0, 0, 0.03);
            border: 1px solid #e2e8f0;
        }

        /* -- Left Panel (Category List) -- */
        .settings-nav {
            width: 320px;
            min-width: 320px;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            border-right: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        .settings-nav-header {
            padding: 1.75rem 1.5rem 1.25rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .settings-nav-item {
            display: flex;
            align-items: center;
            padding: 0.85rem 1.35rem;
            gap: 0.95rem;
            cursor: pointer;
            color: #475569;
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border-left: 3px solid transparent;
            text-decoration: none;
        }
        .settings-nav-item:hover {
            background: rgba(0, 0, 0, 0.04);
            color: #09090b;
        }
        .settings-nav-item.active {
            background: rgba(0, 0, 0, 0.06);
            border-left-color: #09090b;
            color: #09090b;
            font-weight: 800;
        }
        .settings-nav-item .nav-badge {
            margin-left: auto;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            background: #09090b;
            color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        .nav-separator {
            height: 1px;
            background: #e2e8f0;
            margin: 0.5rem 1.35rem;
        }

        /* -- Right Panel (Content) -- */
        .settings-content {
            flex: 1;
            overflow-y: auto;
            padding: 2.25rem 3.5rem;
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
            font-size: 1.75rem;
            font-weight: 900;
            color: #09090b;
            margin-bottom: 0.35rem;
            letter-spacing: -0.025em;
        }
        .settings-section-subtitle {
            color: #64748b;
            font-size: 0.925rem;
            margin-bottom: 2rem;
            max-width: 900px;
        }
        .settings-group {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 1.25rem;
            padding: 1.75rem 2rem;
            margin-bottom: 1.75rem;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        }
        .settings-group-title {
            font-size: 0.775rem;
            text-transform: uppercase;
            letter-spacing: 0.09em;
            font-weight: 800;
            color: #475569;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .settings-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.1rem 0;
            border-bottom: 1px solid #eef2f6;
            transition: all 0.15s ease;
            gap: 1.5rem;
        }
        .settings-row:last-child {
            border-bottom: none;
        }
        .settings-row-label {
            font-size: 0.925rem;
            font-weight: 700;
            color: #1e293b;
        }
        .settings-row-desc {
            font-size: 0.785rem;
            color: #64748b;
            margin-top: 0.25rem;
            line-height: 1.4;
        }
        .settings-input {
            width: 240px;
            padding: 0.65rem 0.95rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            background: white;
            color: #0f172a;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .settings-input:focus {
            outline: none;
            border-color: #09090b;
            box-shadow: 0 0 0 3px rgba(9, 9, 11, 0.15);
        }

        /* ======================== BLACK & ANIMATED ICONS SYSTEM ======================== */
        .icon-black-badge {
            width: 2.85rem;
            height: 2.85rem;
            border-radius: 1rem;
            background: #09090b;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 6px 16px -2px rgba(0, 0, 0, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(0, 0, 0, 0.85);
            position: relative;
            overflow: hidden;
            transition: all 0.25s ease;
        }
        .icon-black-badge::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 50%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.25), transparent);
            transform: skewX(-20deg);
            transition: 0.6s ease;
        }
        .icon-black-badge:hover::after {
            left: 150%;
        }

        .icon-black-pill {
            width: 2.45rem;
            height: 2.45rem;
            border-radius: 0.8rem;
            background: #09090b;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 10px -2px rgba(0, 0, 0, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(0, 0, 0, 0.8);
            position: relative;
            overflow: hidden;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .icon-black-pill::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 60%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.22), transparent);
            transform: skewX(-25deg);
            transition: 0.5s ease;
        }
        .settings-row:hover .icon-black-pill::after,
        .icon-black-pill:hover::after {
            left: 150%;
        }
        .settings-row:hover .icon-black-pill {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 8px 18px -3px rgba(0, 0, 0, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.35);
        }

        .icon-black-pill-sm {
            width: 2.15rem;
            height: 2.15rem;
            border-radius: 0.7rem;
            background: #18181b;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 2px 6px -1px rgba(0, 0, 0, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(0, 0, 0, 0.65);
            position: relative;
            overflow: hidden;
            transition: all 0.25s ease;
        }
        .settings-nav-item:hover .icon-black-pill-sm {
            background: #09090b;
            transform: translateY(-1px) scale(1.06);
            box-shadow: 0 4px 10px -2px rgba(0, 0, 0, 0.4);
        }
        .settings-nav-item.active .icon-black-pill-sm {
            background: #000000;
            color: #ffffff;
            box-shadow: 0 0 0 2px #000000, 0 4px 12px rgba(0, 0, 0, 0.4);
        }

        /* ==================== KEYFRAME ANIMATIONS ==================== */
        @keyframes iconSpinSlow {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @keyframes iconPulseSubtle {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.14); }
        }
        @keyframes iconFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }
        @keyframes iconBellRing {
            0%, 100% { transform: rotate(0); }
            20% { transform: rotate(15deg); }
            40% { transform: rotate(-15deg); }
            60% { transform: rotate(8deg); }
            80% { transform: rotate(-8deg); }
        }
        @keyframes iconWiggle {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(-10deg); }
            75% { transform: rotate(10deg); }
        }
        @keyframes iconBounceMicro {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }
        @keyframes iconFlashBolt {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.18); }
        }

        .anim-spin-hover:hover svg,
        .settings-row:hover .anim-spin-hover svg,
        .settings-nav-item:hover .anim-spin-hover svg {
            animation: iconSpinSlow 6s linear infinite;
        }
        .anim-pulse-loop svg {
            animation: iconPulseSubtle 2.4s ease-in-out infinite;
        }
        .anim-float-loop svg {
            animation: iconFloat 2.8s ease-in-out infinite;
        }
        .anim-bell-ring:hover svg,
        .settings-row:hover .anim-bell-ring svg,
        .settings-nav-item:hover .anim-bell-ring svg {
            animation: iconBellRing 1s ease-in-out infinite;
            transform-origin: top center;
        }
        .anim-wiggle-hover:hover svg,
        .settings-row:hover .anim-wiggle-hover svg,
        .settings-nav-item:hover .anim-wiggle-hover svg {
            animation: iconWiggle 1s ease-in-out infinite;
        }
        .anim-bounce-hover:hover svg,
        .settings-row:hover .anim-bounce-hover svg,
        .settings-nav-item:hover .anim-bounce-hover svg {
            animation: iconBounceMicro 1.1s ease-in-out infinite;
        }
        .anim-flash-hover:hover svg,
        .settings-row:hover .anim-flash-hover svg,
        .settings-nav-item:hover .anim-flash-hover svg {
            animation: iconFlashBolt 1.2s ease-in-out infinite;
        }

        .icon-black-badge svg,
        .icon-black-pill svg,
        .icon-black-pill-sm svg {
            transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        /* User Table */
        .user-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .user-table thead th {
            font-size: 0.725rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 800;
            color: #64748b;
            padding: 1rem 1.25rem;
            text-align: left;
            background: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
        }
        .user-table thead th:first-child { border-radius: 0.75rem 0 0 0; }
        .user-table thead th:last-child { border-radius: 0 0.75rem 0 0; }
        .user-table tbody td {
            padding: 1rem 1.25rem;
            font-size: 0.875rem;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .user-table tbody tr:hover { background: #f8fafc; }
        .user-table tbody tr:last-child td { border-bottom: none; }

        /* Register Form Card */
        .register-card {
            background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 100%);
            border: 1px solid #cbd5e1;
            border-radius: 1.25rem;
            padding: 2rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
        }
        .register-card .form-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 0.45rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        .register-card .form-input {
            width: 100%;
            padding: 0.65rem 0.95rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            background: white;
            color: #1e293b;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .register-card .form-input:focus {
            outline: none;
            border-color: #09090b;
            box-shadow: 0 0 0 3px rgba(9, 9, 11, 0.15);
        }

        /* Role badges */
        .role-admin { background: #09090b; color: #ffffff; border: 1px solid #000; }
        .role-operador { background: #18181b; color: #f4f4f5; border: 1px solid #27272a; }
        .role-auditor { background: #27272a; color: #e4e4e7; border: 1px solid #3f3f46; }

        /* Buttons */
        .btn-black-executive {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 0.7rem 1.6rem; border-radius: 0.75rem;
            font-size: 0.875rem; font-weight: 800;
            background: linear-gradient(135deg, #18181b 0%, #09090b 100%);
            color: white; border: 1px solid rgba(0, 0, 0, 0.9);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }
        .btn-black-executive:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.35);
            filter: brightness(1.15);
        }
        .btn-black-executive:active {
            transform: translateY(0);
        }

        @media (max-width: 1024px) {
            .settings-container { flex-direction: column; height: auto; min-height: auto; }
            .settings-nav { width: 100%; min-width: 100%; flex-direction: row; overflow-x: auto; border-right: none; border-bottom: 1px solid #e2e8f0; }
            .settings-nav-header { display: none; }
            .settings-nav-item { padding: 0.75rem 1rem; border-left: none; border-bottom: 3px solid transparent; white-space: nowrap; }
            .settings-nav-item.active { border-left-color: transparent; border-bottom-color: #09090b; }
            .nav-separator { display: none; }
            .settings-content { padding: 1.5rem; }
        }

        /* ======================== DARK MODE NOC OLED OVERRIDES ======================== */
        .dark .settings-container {
            background: #090b10 !important;
            border: 1px solid rgba(51, 65, 85, 0.4) !important;
            box-shadow: 0 14px 40px rgba(0, 0, 0, 0.8) !important;
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
            background: rgba(255, 255, 255, 0.04) !important;
            color: #ffffff !important;
        }
        .dark .settings-nav-item.active {
            background: rgba(255, 255, 255, 0.08) !important;
            border-left-color: #ffffff !important;
            color: #ffffff !important;
        }
        .dark .settings-nav-item .nav-badge {
            background: #ffffff !important;
            color: #000000 !important;
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
            border: 1px solid rgba(51, 65, 85, 0.45) !important;
            box-shadow: none !important;
        }
        .dark .settings-group-title {
            color: #94a3b8 !important;
        }
        .dark .settings-row {
            border-bottom-color: rgba(51, 65, 85, 0.3) !important;
        }
        .dark .settings-row-label {
            color: #f1f5f9 !important;
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
            border-color: #ffffff !important;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.15) !important;
        }
        .dark .register-card {
            background: #0d1017 !important;
            border: 1px solid rgba(51, 65, 85, 0.45) !important;
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
            color: #94a3b8 !important;
        }
        .dark .user-table tbody td {
            color: #cbd5e1 !important;
            border-bottom-color: rgba(51, 65, 85, 0.3) !important;
        }
        .dark .user-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.03) !important;
        }
        .dark .role-admin { background: #ffffff !important; color: #000000 !important; border-color: #ffffff !important; }
        .dark .role-operador { background: #27272a !important; color: #f4f4f5 !important; border-color: #3f3f46 !important; }
        .dark .role-auditor { background: #18181b !important; color: #a1a1aa !important; border-color: #27272a !important; }

        .dark .icon-black-badge {
            background: #000000;
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.22);
            box-shadow: 0 4px 16px -2px rgba(0, 0, 0, 0.8), inset 0 1px 0 rgba(255, 255, 255, 0.25);
        }
        .dark .icon-black-pill {
            background: #000000;
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.8), inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }
        .dark .settings-row:hover .icon-black-pill {
            border-color: rgba(255, 255, 255, 0.45);
            box-shadow: 0 6px 18px -2px rgba(255, 255, 255, 0.18);
        }
        .dark .icon-black-pill-sm {
            background: #000000;
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        .dark .settings-nav-item:hover .icon-black-pill-sm {
            background: #18181b;
        }
        .dark .settings-nav-item.active .icon-black-pill-sm {
            background: #ffffff;
            color: #000000;
            box-shadow: 0 0 0 2px #ffffff, 0 4px 12px rgba(255, 255, 255, 0.3);
        }
        .dark .btn-black-executive {
            background: linear-gradient(135deg, #ffffff 0%, #e4e4e7 100%);
            color: #09090b;
            border: 1px solid #ffffff;
            box-shadow: 0 4px 14px rgba(255, 255, 255, 0.15);
        }
        .dark .btn-black-executive:hover {
            filter: brightness(1.05);
            box-shadow: 0 6px 20px rgba(255, 255, 255, 0.25);
        }

        .search-highlight {
            background-color: rgba(251, 191, 36, 0.35);
            padding: 1px 4px;
            border-radius: 4px;
        }
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
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </div>
                    <div>
                        <h2 class="text-base font-black text-slate-900 dark:text-white leading-tight">Configuración</h2>
                        <p class="text-xs text-slate-400 font-medium">Ajustes del sistema NOC</p>
                    </div>
                </div>

                {{-- Live Search Filter with Real Filtering --}}
                <div class="relative">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="text" placeholder="Buscar en todos los ajustes..." class="w-full pl-9 pr-7 py-2.5 bg-white dark:bg-[#0d1017] border border-slate-200 dark:border-slate-700/80 rounded-xl text-xs text-slate-800 dark:text-white font-medium focus:outline-none focus:ring-2 focus:ring-black dark:focus:ring-white focus:border-black dark:focus:border-white transition shadow-xs" id="settingsSearch">
                    <button type="button" id="clearSettingsSearch" class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            </div>

            {{-- Navigation Items with Black Animated SVG Icons --}}
            <div class="py-3 space-y-1" id="navItemsList">
                <a href="#" class="settings-nav-item {{ $section === 'general' ? 'active' : '' }}" data-section="general">
                    <div class="icon-black-pill-sm anim-flash-hover">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <span>Motor SNMP</span>
                    <span class="nav-match-count hidden ml-auto text-[10px] font-bold px-2 py-0.5 rounded-full bg-zinc-900 text-white dark:bg-white dark:text-black"></span>
                </a>

                <a href="#" class="settings-nav-item {{ $section === 'cuentas' ? 'active' : '' }}" data-section="cuentas">
                    <div class="icon-black-pill-sm anim-bounce-hover">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </div>
                    <span>Cuentas</span>
                    <span class="nav-badge">{{ $users->count() }}</span>
                </a>

                <div class="nav-separator"></div>

                <a href="#" class="settings-nav-item {{ $section === 'seguridad' ? 'active' : '' }}" data-section="seguridad">
                    <div class="icon-black-pill-sm anim-wiggle-hover">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <span>Seguridad</span>
                    <span class="nav-match-count hidden ml-auto text-[10px] font-bold px-2 py-0.5 rounded-full bg-zinc-900 text-white dark:bg-white dark:text-black"></span>
                </a>

                <a href="#" class="settings-nav-item {{ $section === 'notificaciones' ? 'active' : '' }}" data-section="notificaciones">
                    <div class="icon-black-pill-sm anim-bell-ring">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    </div>
                    <span>Notificaciones</span>
                    <span class="nav-match-count hidden ml-auto text-[10px] font-bold px-2 py-0.5 rounded-full bg-zinc-900 text-white dark:bg-white dark:text-black"></span>
                </a>

                <div class="nav-separator"></div>

                <a href="#" class="settings-nav-item {{ $section === 'apariencia' ? 'active' : '' }}" data-section="apariencia">
                    <div class="icon-black-pill-sm anim-float-loop">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path></svg>
                    </div>
                    <span>Apariencia</span>
                    <span class="nav-match-count hidden ml-auto text-[10px] font-bold px-2 py-0.5 rounded-full bg-zinc-900 text-white dark:bg-white dark:text-black"></span>
                </a>

                <a href="#" class="settings-nav-item {{ $section === 'acerca' ? 'active' : '' }}" data-section="acerca">
                    <div class="icon-black-pill-sm anim-spin-hover">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <span>Acerca de</span>
                    <span class="nav-match-count hidden ml-auto text-[10px] font-bold px-2 py-0.5 rounded-full bg-zinc-900 text-white dark:bg-white dark:text-black"></span>
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
                    <div class="icon-black-pill anim-bounce-hover" style="width: 2.2rem; height: 2.2rem;">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <span>{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="mb-6 p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-700 dark:text-rose-300 text-sm font-semibold flex items-center gap-3 shadow-xs">
                    <div class="icon-black-pill anim-wiggle-hover" style="width: 2.2rem; height: 2.2rem;">
                        <svg class="w-4 h-4 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
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
                <div class="icon-black-badge anim-spin-hover mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <h3 class="text-sm font-bold text-slate-800 dark:text-white">No se encontraron ajustes</h3>
                <p class="text-xs text-slate-400 mt-1">Intenta con otros términos como "SNMP", "CPU", "2FA", "Tema", o "Contraseña".</p>
            </div>

            {{-- ========== SECTION: General / Motor SNMP ========== --}}
            <div class="settings-section {{ $section === 'general' ? 'active' : '' }}" id="section-general" data-section-name="Motor SNMP">
                <div class="flex items-center gap-3.5 mb-2">
                    <div class="icon-black-badge anim-flash-hover">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
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
                                <svg class="w-4 h-4 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                                Parámetros del Worker Python
                            </h4>
                            
                            {{-- Row: Intervalo --}}
                            <div class="settings-row">
                                <div class="flex items-center gap-4">
                                    <div class="icon-black-pill anim-spin-hover">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
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
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
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
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l8 4v5c0 4.5-3.4 8.4-8 9-4.6-.6-8-4.5-8-9V7l8-4z"></path></svg>
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
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
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
                                <svg class="w-4 h-4 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                Umbrales de Alerta de Rendimiento
                            </h4>
                            
                            {{-- Row: CPU Warning --}}
                            <div class="settings-row">
                                <div class="flex items-center gap-4">
                                    <div class="icon-black-pill anim-pulse-loop">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path></svg>
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
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
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
                            <svg class="w-4 h-4 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2 3.6 3 8 3s8-1 8-3V7M4 7c0 2 3.6 3 8 3s8-1 8-3M4 7c0-2 3.6-3 8-3s8 1 8 3m-8 6c4.4 0 8-1 8-3"></path></svg>
                            Retención de históricos
                        </h4>
                        <p class="settings-row-desc mb-2">Lo más antiguo que estos días se borra automáticamente (una vez por hora). Deja un campo vacío para usar el valor predeterminado.</p>

                        @foreach($retencion as $r)
                            <div class="settings-row">
                                <div class="flex items-center gap-4">
                                    <div class="icon-black-pill anim-spin-hover">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
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
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                            <span>Guardar Parámetros</span>
                        </button>
                    </div>
                </form>
            </div>

            {{-- ========== SECTION: Cuentas ========== --}}
            <div class="settings-section {{ $section === 'cuentas' ? 'active' : '' }}" id="section-cuentas" data-section-name="Cuentas">
                <div class="flex items-center gap-3.5 mb-2">
                    <div class="icon-black-badge anim-bounce-hover">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </div>
                    <div>
                        <h1 class="settings-section-title mb-0">Cuentas de Usuario</h1>
                        <p class="settings-section-subtitle mb-0">Administra los operadores del sistema. Registra nuevas cuentas y gestiona roles de acceso.</p>
                    </div>
                </div>

                {{-- Current User Session & Logout Card --}}
                <div class="bg-gradient-to-r from-slate-100/90 to-slate-200/50 dark:from-[#090b10] dark:to-[#0d1017] rounded-2xl p-5 sm:p-6 mb-6 border border-slate-300/70 dark:border-slate-800/60 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
                    <div class="flex items-center gap-4">
                        <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" class="w-13 h-13 rounded-full object-cover border-2 border-black/30 dark:border-white/30 shadow-md flex-shrink-0" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'Admin') }}&background=09090b&color=fff&size=80';">
                        <div>
                            <div class="flex items-center gap-2.5">
                                <h3 class="text-base font-extrabold text-slate-900 dark:text-white">
                                    @if(auth()->user()->prefijo)<span class="text-black dark:text-white font-black">{{ auth()->user()->prefijo }}</span> @endif{{ auth()->user()->name }}
                                </h3>
                                <span class="text-[10px] font-black px-3 py-1 rounded-full bg-black text-white dark:bg-white dark:text-black uppercase tracking-wider">{{ auth()->user()->role }}</span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5">{{ auth()->user()->email }} · <span class="text-emerald-600 dark:text-emerald-400 font-bold">● Sesión Activa</span></p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-red-50 dark:bg-red-950/40 hover:bg-red-100 dark:hover:bg-red-900/50 text-red-600 dark:text-red-400 text-xs font-bold transition border border-red-200 dark:border-red-800/40 shadow-xs">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                            Cerrar Sesión
                        </button>
                    </form>
                </div>

                {{-- Register New Account Card --}}
                <div class="register-card mb-6">
                    <div class="flex items-center gap-3.5 mb-5">
                        <div class="icon-black-pill anim-bounce-hover">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
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
                                    <svg class="w-3.5 h-3.5 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path></svg>
                                    Foto <span class="text-slate-400 font-normal">(opc.)</span>
                                </label>
                                <label for="avatarUpload" class="cursor-pointer group relative">
                                    <div class="w-18 h-18 rounded-full bg-slate-100 dark:bg-slate-800 border-2 border-dashed border-slate-300 dark:border-slate-700 group-hover:border-black dark:group-hover:border-white flex items-center justify-center overflow-hidden transition" id="avatarPreviewContainer">
                                        <svg id="avatarPlaceholderIcon" class="w-7 h-7 text-slate-400 group-hover:text-black dark:group-hover:text-white transition" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"></path></svg>
                                        <img id="avatarPreviewImg" src="" alt="Preview" class="w-full h-full object-cover hidden">
                                    </div>
                                    <input type="file" name="avatar" id="avatarUpload" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="previewAvatar(this)">
                                </label>
                                <p class="text-[9px] text-slate-400 mt-1.5 text-center font-medium">JPG/PNG · 2MB</p>
                            </div>

                            {{-- Name + Prefijo + Email --}}
                            <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-5">
                                <div>
                                    <label class="form-label">
                                        <svg class="w-3.5 h-3.5 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                        Nombre completo
                                    </label>
                                    <input type="text" name="name" required placeholder="Ej. Juan Pérez" class="form-input" value="{{ old('name') }}">
                                </div>
                                <div>
                                    <label class="form-label">
                                        <svg class="w-3.5 h-3.5 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
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
                                        <svg class="w-3.5 h-3.5 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                        Correo electrónico
                                    </label>
                                    <input type="email" name="email" required placeholder="usuario@dominio.com" class="form-input" value="{{ old('email') }}">
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
                            <div>
                                <label class="form-label">
                                    <svg class="w-3.5 h-3.5 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                    Contraseña
                                </label>
                                <input type="password" name="password" required placeholder="Mín. 8 caracteres" class="form-input">
                            </div>
                            <div>
                                <label class="form-label">
                                    <svg class="w-3.5 h-3.5 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                    Confirmar Contraseña
                                </label>
                                <input type="password" name="password_confirmation" required placeholder="Repetir contraseña" class="form-input">
                            </div>
                            <div>
                                <label class="form-label">
                                    <svg class="w-3.5 h-3.5 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
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
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                                <span>Crear Cuenta</span>
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Existing Users Table --}}
                <div class="settings-group" style="padding: 0; overflow: hidden;">
                    <div class="px-6 py-4 flex items-center justify-between border-b border-slate-200 dark:border-slate-800/60 bg-[#f8fafc] dark:bg-[#090b10]">
                        <h4 class="text-xs uppercase tracking-wider font-extrabold text-slate-500 dark:text-slate-400 flex items-center gap-2.5">
                            <svg class="w-4 h-4 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
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
                                                    <p class="text-[10px] text-zinc-900 dark:text-zinc-300 font-extrabold">Tú (Sesión actual)</p>
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
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                Ver
                                            </button>
                                            {{-- Editar --}}
                                            <button type="button" onclick="openEditModal({{ $user->id }})" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-bold bg-zinc-900 text-white dark:bg-white dark:text-black hover:opacity-90 transition border border-black dark:border-white" title="Editar usuario">
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                Editar
                                            </button>
                                            {{-- Eliminar --}}
                                            @if($user->id !== auth()->id())
                                                <form action="{{ route('settings.delete_user', $user->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Estás seguro de eliminar a {{ $user->name }}?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-bold bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-900/50 transition border border-rose-200 dark:border-rose-800/40" title="Eliminar cuenta">
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
                            <button id="viewModalEditBtn" onclick="editFromViewModal()" class="flex-1 py-2.5 rounded-xl bg-black hover:bg-zinc-800 text-sm font-bold text-white transition flex items-center justify-center gap-1.5 shadow-sm">
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
                        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-zinc-900/40">
                            <div class="flex items-center gap-3">
                                <div class="icon-black-pill-sm anim-wiggle-hover">
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
                                        <div class="w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 border-2 border-dashed border-slate-300 dark:border-slate-700 group-hover:border-black dark:group-hover:border-white flex items-center justify-center overflow-hidden transition" id="editAvatarContainer">
                                            <img id="editAvatarPreview" src="" alt="Avatar" class="w-full h-full object-cover">
                                        </div>
                                        <div class="absolute -bottom-0.5 -right-0.5 w-6 h-6 rounded-full bg-black dark:bg-white text-white dark:text-black flex items-center justify-center shadow-md">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
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
                <div class="flex items-center gap-3.5 mb-2">
                    <div class="icon-black-badge anim-wiggle-hover">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <div>
                        <h1 class="settings-section-title mb-0">Seguridad y Control de Acceso</h1>
                        <p class="settings-section-subtitle mb-0">Configuraciones de autenticación de dos factores, sesiones y roles de usuario.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 2xl:grid-cols-2 gap-6 items-start mt-6">
                    <div class="settings-group mb-0">
                        <h4 class="settings-group-title">
                            <svg class="w-4 h-4 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            Autenticación y Sesión
                        </h4>
                        
                        {{-- Row: 2FA --}}
                        <div class="settings-row">
                            <div class="flex items-center gap-4">
                                <div class="icon-black-pill anim-wiggle-hover">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                </div>
                                <div>
                                    <div class="settings-row-label">Autenticación de Dos Factores (2FA)</div>
                                    <div class="settings-row-desc">Protege tu cuenta requiriendo un código TOTP (Google Authenticator)</div>
                                </div>
                            </div>
                            <a href="{{ route('setup-2fa') }}" class="btn-black-executive" style="padding: 0.55rem 1.25rem; font-size: 0.775rem;">
                                <span>Configurar</span>
                                <svg class="w-3.5 h-3.5 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                            </a>
                        </div>

                        {{-- Row: Session Timeout --}}
                        <div class="settings-row">
                            <div class="flex items-center gap-4">
                                <div class="icon-black-pill anim-spin-hover">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
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
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                </div>
                                <div>
                                    <div class="settings-row-label">Confirmación de Contraseña</div>
                                    <div class="settings-row-desc">Protección reforzada antes de acceder a áreas administrativas críticas</div>
                                </div>
                            </div>
                            <span class="text-xs font-black px-3.5 py-1 rounded-full bg-black text-white dark:bg-white dark:text-black flex items-center gap-1.5 shadow-xs">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                Activo
                            </span>
                        </div>
                    </div>

                    <div class="settings-group mb-0">
                        <h4 class="settings-group-title">
                            <svg class="w-4 h-4 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            Matriz de Control de Acceso (RBAC)
                        </h4>
                        
                        <div class="settings-row">
                            <div class="flex items-center gap-4">
                                <div class="icon-black-pill anim-wiggle-hover">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
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
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
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
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    </div>
                    <div>
                        <h1 class="settings-section-title mb-0">Canales de Alerta y Notificaciones</h1>
                        <p class="settings-section-subtitle mb-0">Gestiona cómo recibes alertas críticas y notificaciones del estado de la red.</p>
                    </div>
                </div>

                <div class="settings-group mt-6">
                    <h4 class="settings-group-title">
                        <svg class="w-4 h-4 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        Canales de Transmisión
                    </h4>
                    
                    <div class="settings-row">
                        <div class="flex items-center gap-4">
                            <div class="icon-black-pill anim-float-loop">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
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
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
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
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                            </div>
                            <div>
                                <div class="settings-row-label">Notificaciones en Dashboard en Tiempo Real</div>
                                <div class="settings-row-desc">Indicador sonoro y marquesina NOC en la barra superior</div>
                            </div>
                        </div>
                        <span class="text-xs font-black px-3.5 py-1 rounded-full bg-black text-white dark:bg-white dark:text-black flex items-center gap-1.5 shadow-xs">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            Activo
                        </span>
                    </div>
                </div>

                {{-- Alertas por Telegram --}}
                <form action="{{ route('settings.telegram') }}" method="POST" class="settings-group" x-data="telegramAjustes()">
                    @csrf
                    <h4 class="settings-group-title">
                        <svg class="w-4 h-4 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 4L3 11l6 2m12-9l-4 16-8-7m12-9L9 13m0 0v6l3-4"></path></svg>
                        Alertas por Telegram
                        @if($settings->telegram_activo)
                            <span class="ml-auto text-[11px] font-black px-3 py-0.5 rounded-full bg-black text-white dark:bg-white dark:text-black flex items-center gap-1.5 normal-case tracking-normal">
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
                                title="Envía un mensaje de prueba con la configuración guardada">Enviar prueba</button>
                        <button type="submit" class="btn-black-executive flex items-center gap-2.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
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
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path></svg>
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
                                <svg class="w-4 h-4 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                Tema de Visualización
                            </h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Selecciona el modo cromático preferido para la estación de trabajo.</p>
                        </div>

                        {{-- Segmented Quick Switcher --}}
                        <div class="inline-flex p-1 rounded-xl bg-slate-200 dark:bg-[#000000] border border-slate-300 dark:border-slate-800 shadow-inner self-start sm:self-auto">
                            <button type="button" @click="selectTheme('light')"
                                    :class="currentTheme === 'light' ? 'bg-white dark:bg-zinc-800 text-black dark:text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-black dark:hover:text-white'"
                                    class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                Claro
                            </button>
                            <button type="button" @click="selectTheme('dark')"
                                    :class="currentTheme === 'dark' ? 'bg-white dark:bg-zinc-800 text-black dark:text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-black dark:hover:text-white'"
                                    class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                                Oscuro
                            </button>
                            <button type="button" @click="selectTheme('system')"
                                    :class="currentTheme === 'system' ? 'bg-white dark:bg-zinc-800 text-black dark:text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-black dark:hover:text-white'"
                                    class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
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
                                : 'border-slate-200 dark:border-slate-800/60 bg-white dark:bg-[#090b10] hover:border-slate-400 dark:hover:border-slate-600'"
                             class="rounded-2xl border p-5 cursor-pointer transition relative flex flex-col justify-between group shadow-xs">
                            
                            {{-- Checkmark indicator --}}
                            <div class="absolute top-4 right-4">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center transition"
                                     :class="currentTheme === 'light' ? 'bg-black text-white' : 'border border-slate-300 dark:border-slate-700 text-transparent'">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
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
                                    <svg class="w-4 h-4 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                    <h5 class="font-extrabold text-sm text-slate-900 dark:text-white">Claro / Diurno</h5>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                    Fondos blancos limpios de alto contraste, ideal para oficinas iluminadas.
                                </p>
                            </div>

                            <div class="mt-4 pt-3 border-t border-slate-200 dark:border-slate-800/40 flex items-center justify-between">
                                <span class="text-[10px] font-mono font-bold text-slate-400">#FFFFFF / #F1F5F9</span>
                                <span class="text-[11px] font-black text-black dark:text-white" x-show="currentTheme === 'light'">Seleccionado</span>
                            </div>
                        </div>

                        {{-- Card 2: Modo Oscuro (NOC OLED) --}}
                        <div @click="selectTheme('dark')"
                             :class="currentTheme === 'dark' 
                                ? 'border-white dark:border-white ring-2 ring-black/20 dark:ring-white/20 bg-zinc-900 text-white' 
                                : 'border-slate-200 dark:border-slate-800/60 bg-white dark:bg-[#090b10] hover:border-slate-400 dark:hover:border-slate-600'"
                             class="rounded-2xl border p-5 cursor-pointer transition relative flex flex-col justify-between group shadow-xs">
                            
                            {{-- Checkmark indicator --}}
                            <div class="absolute top-4 right-4">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center transition"
                                     :class="currentTheme === 'dark' ? 'bg-white text-black' : 'border border-slate-300 dark:border-slate-700 text-transparent'">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
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
                                                <div class="h-4 bg-[#0d1017] rounded border border-slate-800"></div>
                                                <div class="grid grid-cols-2 gap-1.5">
                                                    <div class="h-5 bg-[#0d1017] rounded border border-slate-800"></div>
                                                    <div class="h-5 bg-[#0d1017] rounded border border-slate-800"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 mb-1.5">
                                    <svg class="w-4 h-4 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                                    <h5 class="font-extrabold text-sm text-slate-900 dark:text-white">Oscuro / NOC OLED</h5>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                    Consola con negros profundos anti-fatiga para operaciones continuas 24/7.
                                </p>
                            </div>

                            <div class="mt-4 pt-3 border-t border-slate-200 dark:border-slate-800/40 flex items-center justify-between">
                                <span class="text-[10px] font-mono font-bold text-slate-400">#000000 / #0D1017</span>
                                <span class="text-[11px] font-black text-black dark:text-white" x-show="currentTheme === 'dark'">Seleccionado</span>
                            </div>
                        </div>

                        {{-- Card 3: Modo Automático / Sistema --}}
                        <div @click="selectTheme('system')"
                             :class="currentTheme === 'system' 
                                ? 'border-black dark:border-white ring-2 ring-black/10 dark:ring-white/20 bg-slate-100/60 dark:bg-zinc-900/60' 
                                : 'border-slate-200 dark:border-slate-800/60 bg-white dark:bg-[#090b10] hover:border-slate-400 dark:hover:border-slate-600'"
                             class="rounded-2xl border p-5 cursor-pointer transition relative flex flex-col justify-between group shadow-xs">
                            
                            {{-- Checkmark indicator --}}
                            <div class="absolute top-4 right-4">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center transition"
                                     :class="currentTheme === 'system' ? 'bg-black dark:bg-white text-white dark:text-black' : 'border border-slate-300 dark:border-slate-700 text-transparent'">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
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
                                        <div class="h-4 bg-[#0d1017] rounded border border-zinc-800"></div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 mb-1.5">
                                    <svg class="w-4 h-4 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                    <h5 class="font-extrabold text-sm text-slate-900 dark:text-white">Automático (Sistema)</h5>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                    Sincroniza en tiempo real con la preferencia de color de tu sistema operativo.
                                </p>
                            </div>

                            <div class="mt-4 pt-3 border-t border-slate-200 dark:border-slate-800/40 flex items-center justify-between">
                                <span class="text-[10px] font-mono font-bold text-slate-400">matchMedia (OS)</span>
                                <span class="text-[11px] font-black text-black dark:text-white" x-show="currentTheme === 'system'">Seleccionado</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Paleta de Colores Institucional --}}
                <div class="settings-group">
                    <h4 class="settings-group-title">
                        <svg class="w-4 h-4 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path></svg>
                        Paleta de Colores Institucional NOC
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mt-3">
                        <div class="p-4 rounded-xl bg-white dark:bg-[#090b10] border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                            <div>
                                <div class="text-xs font-bold text-slate-800 dark:text-slate-200">Azul Institucional</div>
                                <div class="text-[10px] text-slate-400 font-mono mt-0.5">#3b5998</div>
                            </div>
                            <div class="w-8 h-8 rounded-lg shadow-inner border border-slate-300 dark:border-slate-700" style="background: #3b5998;"></div>
                        </div>

                        <div class="p-4 rounded-xl bg-white dark:bg-[#090b10] border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                            <div>
                                <div class="text-xs font-bold text-slate-800 dark:text-slate-200">Slate Blue</div>
                                <div class="text-[10px] text-slate-400 font-mono mt-0.5">#5c8096</div>
                            </div>
                            <div class="w-8 h-8 rounded-lg shadow-inner border border-slate-300 dark:border-slate-700" style="background: #5c8096;"></div>
                        </div>

                        <div class="p-4 rounded-xl bg-white dark:bg-[#090b10] border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                            <div>
                                <div class="text-xs font-bold text-slate-800 dark:text-slate-200">Naranja NOC</div>
                                <div class="text-[10px] text-slate-400 font-mono mt-0.5">#f26419</div>
                            </div>
                            <div class="w-8 h-8 rounded-lg shadow-inner border border-slate-300 dark:border-slate-700" style="background: #f26419;"></div>
                        </div>

                        <div class="p-4 rounded-xl bg-white dark:bg-[#090b10] border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                            <div>
                                <div class="text-xs font-bold text-slate-800 dark:text-slate-200">OLED Canvas</div>
                                <div class="text-[10px] text-slate-400 font-mono mt-0.5">#07090e</div>
                            </div>
                            <div class="w-8 h-8 rounded-lg shadow-inner border border-slate-300 dark:border-slate-700" style="background: #07090e;"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ========== SECTION: Acerca de ========== --}}
            <div class="settings-section {{ $section === 'acerca' ? 'active' : '' }}" id="section-acerca" data-section-name="Acerca de">
                <div class="flex items-center gap-3.5 mb-2">
                    <div class="icon-black-badge anim-spin-hover">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div>
                        <h1 class="settings-section-title mb-0">Acerca de la Plataforma</h1>
                        <p class="settings-section-subtitle mb-0">Información del sistema de monitoreo de infraestructura de red.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 2xl:grid-cols-2 gap-6 items-start mt-6">
                    <div class="settings-group mb-0">
                        <h4 class="settings-group-title">
                            <svg class="w-4 h-4 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path></svg>
                            Información del Entorno
                        </h4>
                        
                        <div class="settings-row">
                            <div class="flex items-center gap-4">
                                <div class="icon-black-pill anim-pulse-loop">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                                </div>
                                <div class="settings-row-label">Nombre del Sistema</div>
                            </div>
                            <span class="text-sm font-extrabold text-slate-800 dark:text-slate-200">Network Monitor NOC</span>
                        </div>

                        <div class="settings-row">
                            <div class="flex items-center gap-4">
                                <div class="icon-black-pill anim-bounce-hover">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                                <div class="settings-row-label">Versión de Producción</div>
                            </div>
                            <span class="text-xs font-black font-mono px-3 py-1 rounded-lg bg-black text-white dark:bg-white dark:text-black">v2.4.0</span>
                        </div>

                        <div class="settings-row">
                            <div class="flex items-center gap-4">
                                <div class="icon-black-pill anim-float-loop">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                </div>
                                <div class="settings-row-label">Framework Backend</div>
                            </div>
                            <span class="text-sm font-bold text-slate-700 dark:text-slate-300 font-mono">Laravel {{ app()->version() }}</span>
                        </div>

                        <div class="settings-row">
                            <div class="flex items-center gap-4">
                                <div class="icon-black-pill anim-spin-hover">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                                </div>
                                <div class="settings-row-label">PHP Engine</div>
                            </div>
                            <span class="text-sm font-bold font-mono text-slate-700 dark:text-slate-300">{{ phpversion() }}</span>
                        </div>

                        <div class="settings-row">
                            <div class="flex items-center gap-4">
                                <div class="icon-black-pill anim-pulse-loop">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
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
                            <svg class="w-4 h-4 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                            Tecnologías Integradas
                        </h4>
                        <div class="flex flex-wrap gap-2.5 mt-2">
                            @foreach(['Laravel 12', 'Tailwind CSS', 'Alpine.js', 'xterm.js', 'PySNMP', 'Paramiko SSH', 'MySQL', 'Vite'] as $tech)
                                <span class="px-3.5 py-2 rounded-xl bg-white dark:bg-[#090b10] border border-slate-200 dark:border-slate-800 text-xs font-bold text-slate-800 dark:text-slate-200 shadow-xs flex items-center gap-2">
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
