@extends('layouts.app')

@section('styles')
    <style>
        .text-hacienda-blue { color: #5c8096; }
        .bg-hacienda-blue { background-color: #5c8096; }
        .text-hacienda-orange { color: #e67e22; }
        .bg-hacienda-orange { background-color: #e67e22; }
    </style>
@endsection

@section('content')
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        
        <header class="mb-8 flex justify-between items-center bg-white dark:bg-[#0d1017] p-6 rounded-2xl shadow-sm dark:shadow-none border border-gray-100 dark:border-slate-800/40">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl text-white flex items-center justify-center shrink-0 shadow-md" style="background: linear-gradient(135deg, #3b5998, #5c8096);">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-hacienda-blue dark:text-blue-400">Agregar Dispositivo</h1>
                    <p class="text-gray-500 dark:text-slate-400 mt-1">Registrar nuevo equipo de red en el sistema</p>
                </div>
            </div>
            <div>
                <a href="{{ url('/') }}" class="text-sm font-medium text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white transition">Volver al Dashboard</a>
            </div>
        </header>

        @if($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800/60">
                <ul class="list-disc pl-5 text-red-700 dark:text-red-300 text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white dark:bg-[#0d1017] p-8 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-800">
            <form method="POST" action="{{ route('dispositivos.store') }}">
                @csrf
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- General Settings -->
                    <div class="md:col-span-2 border-b border-gray-200 dark:border-slate-800 pb-4 mb-2">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white">Información General</h3>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Nombre (Hostname)</label>
                        <input type="text" name="nombre" value="{{ old('nombre', 'Cisco-SG200-Lab') }}" required class="w-full rounded-xl border border-gray-300 dark:border-slate-700 bg-white dark:bg-[#090b10] text-gray-900 dark:text-white px-3.5 py-2.5 shadow-sm focus:border-[#5c8096] focus:ring focus:ring-[#5c8096] focus:ring-opacity-50">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Dirección IP</label>
                        <input type="text" name="ip" value="{{ old('ip', '192.168.1.254') }}" required class="w-full rounded-xl border border-gray-300 dark:border-slate-700 bg-white dark:bg-[#090b10] text-gray-900 dark:text-white px-3.5 py-2.5 shadow-sm focus:border-[#5c8096] focus:ring focus:ring-[#5c8096] focus:ring-opacity-50">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Ubicación</label>
                        <input type="text" name="ubicacion" value="{{ old('ubicacion', 'Mesa de Pruebas / Lab') }}" required class="w-full rounded-xl border border-gray-300 dark:border-slate-700 bg-white dark:bg-[#090b10] text-gray-900 dark:text-white px-3.5 py-2.5 shadow-sm focus:border-[#5c8096] focus:ring focus:ring-[#5c8096] focus:ring-opacity-50">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Comunidad SNMP (Lectura)</label>
                        <input type="text" name="comunidad_snmp" value="{{ old('comunidad_snmp', 'public') }}" required class="w-full rounded-xl border border-gray-300 dark:border-slate-700 bg-white dark:bg-[#090b10] text-gray-900 dark:text-white px-3.5 py-2.5 shadow-sm focus:border-[#5c8096] focus:ring focus:ring-[#5c8096] focus:ring-opacity-50">
                    </div>
                    
                    <div class="text-xs text-gray-500 dark:text-slate-400 self-end">
                        El estado, el modelo y las interfaces se obtienen del propio equipo por ICMP/SNMP tras el registro.
                    </div>

                    <!-- SSH Settings (Opcional) -->
                    <div class="md:col-span-2 border-b border-gray-200 dark:border-slate-800 pb-4 mb-2 mt-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white">Credenciales de Acceso SSH <span class="text-xs font-normal text-gray-400 dark:text-slate-500">(Opcional)</span></h3>
                            <span class="text-[11px] bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-transparent dark:border-slate-700 px-2 py-0.5 rounded font-mono">Para switches con CLI</span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">Si el switch no utiliza SSH (por ejemplo, switches Smart administrables solo por Web/SNMP), puedes dejar estos campos en blanco.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Usuario SSH <span class="text-gray-400 dark:text-slate-500 text-xs font-normal">(Opcional)</span></label>
                        <input type="text" name="ssh_user" value="{{ old('ssh_user', '') }}" class="w-full rounded-xl border border-gray-300 dark:border-slate-700 bg-white dark:bg-[#090b10] text-gray-900 dark:text-white px-3.5 py-2.5 shadow-sm focus:border-[#5c8096] focus:ring focus:ring-[#5c8096] focus:ring-opacity-50" placeholder="admin">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Contraseña SSH <span class="text-gray-400 dark:text-slate-500 text-xs font-normal">(Opcional)</span></label>
                        <input type="password" name="ssh_password" class="w-full rounded-xl border border-gray-300 dark:border-slate-700 bg-white dark:bg-[#090b10] text-gray-900 dark:text-white px-3.5 py-2.5 shadow-sm focus:border-[#5c8096] focus:ring focus:ring-[#5c8096] focus:ring-opacity-50" placeholder="••••••••">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Puerto SSH</label>
                        <input type="number" name="ssh_port" value="{{ old('ssh_port', 22) }}" min="1" max="65535" class="w-full rounded-xl border border-gray-300 dark:border-slate-700 bg-white dark:bg-[#090b10] text-gray-900 dark:text-white px-3.5 py-2.5 shadow-sm focus:border-[#5c8096] focus:ring focus:ring-[#5c8096] focus:ring-opacity-50">
                    </div>
                </div>

                <div class="mt-8 flex justify-end">
                    <button type="submit" class="fancy">
                        <span class="top-key"></span>
                        <span class="text">Registrar Dispositivo</span>
                        <span class="bottom-key-1"></span>
                        <span class="bottom-key-2"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
