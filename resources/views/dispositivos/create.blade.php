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
            <div class="flex items-center">
                <img src="{{ asset('images/logo.png') }}" alt="Logo Hacienda" class="h-16 mr-6 dark:brightness-0 dark:invert dark:opacity-90 transition">
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
            <div class="mb-6 p-4 rounded-md bg-red-50 border border-red-200">
                <ul class="list-disc pl-5 text-red-700">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200">
            <form method="POST" action="{{ route('dispositivos.store') }}">
                @csrf
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- General Settings -->
                    <div class="md:col-span-2 border-b pb-4 mb-2">
                        <h3 class="text-lg font-bold text-gray-800">Información General</h3>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre (Hostname)</label>
                        <input type="text" name="nombre" value="{{ old('nombre', 'Cisco-SG200-Lab') }}" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-[#5c8096] focus:ring focus:ring-[#5c8096] focus:ring-opacity-50">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dirección IP</label>
                        <input type="text" name="ip" value="{{ old('ip', '192.168.1.254') }}" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-[#5c8096] focus:ring focus:ring-[#5c8096] focus:ring-opacity-50">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Modelo de Hardware</label>
                        <input type="text" name="modelo" value="{{ old('modelo', 'Cisco SG200-26 Smart Switch') }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-[#5c8096] focus:ring focus:ring-[#5c8096] focus:ring-opacity-50" placeholder="ej. Cisco SG200-26">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ubicación</label>
                        <input type="text" name="ubicacion" value="{{ old('ubicacion', 'Mesa de Pruebas / Lab') }}" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-[#5c8096] focus:ring focus:ring-[#5c8096] focus:ring-opacity-50">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Comunidad SNMP (Lectura)</label>
                        <input type="text" name="comunidad_snmp" value="{{ old('comunidad_snmp', 'public') }}" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-[#5c8096] focus:ring focus:ring-[#5c8096] focus:ring-opacity-50">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                        <select name="estado" class="w-full rounded-md border-gray-300 shadow-sm focus:border-[#5c8096] focus:ring focus:ring-[#5c8096] focus:ring-opacity-50">
                            <option value="online" {{ old('estado') == 'online' ? 'selected' : '' }}>Online</option>
                            <option value="offline" {{ old('estado') == 'offline' ? 'selected' : '' }}>Offline</option>
                            <option value="warning" {{ old('estado') == 'warning' ? 'selected' : '' }}>Warning</option>
                        </select>
                    </div>

                    <!-- SSH Settings (Opcional) -->
                    <div class="md:col-span-2 border-b pb-4 mb-2 mt-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold text-gray-800">Credenciales de Acceso SSH <span class="text-xs font-normal text-gray-400">(Opcional)</span></h3>
                            <span class="text-[11px] bg-slate-100 text-slate-600 px-2 py-0.5 rounded font-mono">Para switches con CLI</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Si el switch no utiliza SSH (por ejemplo, switches Smart administrables solo por Web/SNMP), puedes dejar estos campos en blanco.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Usuario SSH <span class="text-gray-400 text-xs font-normal">(Opcional)</span></label>
                        <input type="text" name="ssh_user" value="{{ old('ssh_user', '') }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-[#5c8096] focus:ring focus:ring-[#5c8096] focus:ring-opacity-50" placeholder="admin">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña SSH <span class="text-gray-400 text-xs font-normal">(Opcional)</span></label>
                        <input type="password" name="ssh_password" class="w-full rounded-md border-gray-300 shadow-sm focus:border-[#5c8096] focus:ring focus:ring-[#5c8096] focus:ring-opacity-50" placeholder="••••••••">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Puerto SSH</label>
                        <input type="number" name="ssh_port" value="{{ old('ssh_port', 22) }}" min="1" max="65535" class="w-full rounded-md border-gray-300 shadow-sm focus:border-[#5c8096] focus:ring focus:ring-[#5c8096] focus:ring-opacity-50">
                    </div>
                </div>

                <div class="mt-8 flex justify-end">
                    <button type="submit" class="bg-hacienda-blue hover:bg-[#4a6b7d] text-white font-bold py-3 px-8 rounded-md transition shadow-sm">
                        Registrar Dispositivo
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
