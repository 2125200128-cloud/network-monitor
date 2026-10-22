@extends('layouts.app')

@section('styles')
    <style>
        .text-hacienda-blue { color: var(--nm-primary-2); }
        .bg-hacienda-blue { background-color: var(--nm-primary-2); }
        .text-hacienda-orange { color: #ff8300; }
        .bg-hacienda-orange { background-color: #ff8300; }
        .terminal-panel {
            background-color: #111827;
            color: #d1d5db;
            font-family: 'Courier New', Courier, monospace;
        }
    </style>
@endsection

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <header class="mb-8 flex justify-between items-center bg-white dark:bg-nm-surface p-6 rounded-2xl shadow-sm dark:shadow-none border border-gray-100 dark:border-slate-800/60">
            <div class="flex items-center">
                <img src="{{ asset('images/logo.png') }}" alt="Logo Hacienda" class="h-16 mr-6 dark:brightness-0 dark:invert dark:opacity-90 transition">
                <div>
                    <h1 class="text-3xl font-bold text-hacienda-blue dark:text-blue-400">Gestión de Configuraciones NCM</h1>
                    <p class="text-gray-500 dark:text-slate-400 mt-1">Network Configuration Management</p>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <a href="{{ url('/') }}" class="text-sm font-medium text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white transition">Volver al Dashboard</a>
            </div>
        </header>

        @if(session('success'))
            <div class="mb-6 p-4 rounded-xl bg-green-50 dark:bg-emerald-950/40 border border-green-200 dark:border-emerald-800/60 text-green-700 dark:text-emerald-300 text-sm font-semibold">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 p-4 rounded-xl bg-red-50 dark:bg-rose-950/40 border border-red-200 dark:border-rose-800/60 text-red-700 dark:text-rose-300 text-sm font-semibold">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Panel Izquierdo: Controles e Historial -->
            <div class="lg:col-span-1 space-y-6">
                
                <div class="bg-white dark:bg-nm-surface p-6 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 border-b border-gray-200 dark:border-slate-800 pb-2">Seleccionar Dispositivo</h3>
                    <form method="GET" action="{{ route('configuraciones.index') }}" class="mb-6">
                        <select name="dispositivo_id" class="w-full rounded-xl border border-gray-300 dark:border-slate-700 bg-white dark:bg-nm-sunken text-gray-900 dark:text-white shadow-sm p-2.5 focus:border-[var(--nm-primary-2)] focus:ring focus:ring-[var(--nm-primary-2)] focus:ring-opacity-50 text-sm font-medium" onchange="this.form.submit()">
                            @foreach($dispositivos as $d)
                                <option value="{{ $d->id }}" {{ $selectedDispositivoId == $d->id ? 'selected' : '' }}>
                                    {{ $d->nombre }} ({{ $d->ip }})
                                </option>
                            @endforeach
                        </select>
                    </form>

                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 border-b border-gray-200 dark:border-slate-800 pb-2">Generar Nuevo Respaldo</h3>
                    <form method="POST" action="{{ route('configuraciones.respaldar') }}" onsubmit="document.getElementById('btnRespaldar').disabled = true; const t = document.getElementById('btnRespaldar').querySelector('.text'); if(t) { t.innerText = 'Respaldando... (hasta 60s)'; }">
                        @csrf
                        <input type="hidden" name="dispositivo_id" value="{{ $selectedDispositivoId }}">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Tipo de Configuración</label>
                            <select name="tipo" class="w-full rounded-xl border border-gray-300 dark:border-slate-700 bg-white dark:bg-nm-sunken text-gray-900 dark:text-white shadow-sm p-2.5 focus:border-[#ff8300] focus:ring focus:ring-[#ff8300] focus:ring-opacity-50 text-sm font-medium">
                                <option value="running">Running-Config</option>
                                <option value="startup">Startup-Config</option>
                            </select>
                        </div>

                        <button type="submit" id="btnRespaldar" class="fancy w-full justify-center">
                            <span class="top-key"></span>
                            <span class="text">
                                <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /> <polyline points="17 8 12 3 7 8" /> <line x1="12" x2="12" y1="3" y2="15" /></svg>
                                Obtener Respaldo Actual
                            </span>
                            <span class="bottom-key-1"></span>
                            <span class="bottom-key-2"></span>
                        </button>
                    </form>
                </div>

                <div class="bg-white dark:bg-nm-surface p-6 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 border-b border-gray-200 dark:border-slate-800 pb-2">Historial de Versiones</h3>
                    
                    @if($historial->count() > 0)
                        <div class="space-y-4 max-h-96 overflow-y-auto custom-scrollbar">
                            @foreach($historial as $config)
                                <div class="p-3 bg-gray-50 dark:bg-nm-sunken rounded-xl border border-gray-200 dark:border-slate-800 flex justify-between items-center {{ $loop->first ? 'border-hacienda-blue bg-blue-50/50 dark:bg-blue-950/20' : '' }}">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800 dark:text-slate-100">{{ $config->created_at->format('d/m/Y H:i:s') }}</p>
                                        <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">
                                            <span class="inline-block bg-gray-200 dark:bg-slate-800 rounded px-1.5 py-0.5 text-gray-800 dark:text-slate-300 font-mono text-[11px] font-bold">{{ strtoupper($config->tipo) }}</span>
                                            • {{ $config->user ? $config->user->name : 'Sistema' }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @if(!$loop->first)
                                            <span class="text-xs text-gray-400 dark:text-slate-500" title="Hash: {{ substr($config->checksum_sha256, 0, 8) }}...">Old</span>
                                        @else
                                            <span class="text-xs font-bold text-green-600 dark:text-emerald-400">Latest</span>
                                        @endif
                                        <a href="{{ route('configuraciones.pdf', $config->id) }}" target="_blank" class="p-1 rounded-lg hover:bg-white dark:hover:bg-slate-800 text-gray-400 dark:text-slate-400 hover:text-nm-primary dark:hover:text-blue-400 transition shadow-xs" title="Exportar respaldo a PDF">
                                            <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" /> <path d="M14 2v4a2 2 0 0 0 2 2h4" /></svg>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-slate-400 italic">No hay respaldos registrados para este dispositivo.</p>
                    @endif
                </div>

            </div>

            <!-- Panel Derecho: Visor de Configuración -->
            <div class="lg:col-span-2">
                <div class="bg-[#1f2937] rounded-2xl shadow-xl overflow-hidden h-full flex flex-col border border-gray-700">
                    
                    <!-- Barra de herramientas superior -->
                    <div class="bg-gray-800 px-4 py-3 border-b border-gray-700 flex justify-between items-center">
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 rounded-full bg-red-500"></div>
                            <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                            <div class="w-3 h-3 rounded-full bg-green-500"></div>
                            <span class="ml-4 text-xs font-mono text-gray-300">
                                @if($ultimaConfig)
                                    {{ $ultimaConfig->dispositivo->nombre }} - {{ $ultimaConfig->created_at->format('Y-m-d_H-i-s') }}.cfg
                                @else
                                    Sin configuración disponible
                                @endif
                            </span>
                        </div>
                        @if($ultimaConfig)
                            <div class="flex items-center space-x-3">
                                <button onclick="copiarAlPortapapeles()" class="text-gray-300 hover:text-white transition flex items-center text-sm font-medium">
                                    <svg class="w-4 h-4 mr-1" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2" /> <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2" /></svg>
                                    Copiar
                                </button>
                                <a href="{{ route('configuraciones.descargar', $ultimaConfig->id) }}" class="text-gray-300 hover:text-white transition flex items-center text-sm font-medium">
                                    <svg class="w-4 h-4 mr-1" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /> <polyline points="7 10 12 15 17 10" /> <line x1="12" x2="12" y1="15" y2="3" /></svg>
                                    Descargar
                                </a>
                                <a href="{{ route('configuraciones.pdf', $ultimaConfig->id) }}" target="_blank" class="bg-nm-primary hover:bg-[#505b61] text-white px-3 py-1.5 rounded-lg font-bold transition flex items-center text-xs shadow-sm">
                                    <svg class="w-4 h-4 mr-1.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" /> <path d="M14 2v4a2 2 0 0 0 2 2h4" /> <path d="M12 18v-6" /> <path d="m9 15 3 3 3-3" /></svg>
                                    Reporte PDF
                                </a>
                            </div>
                        @endif
                    </div>

                    <!-- Contenido del archivo -->
                    <div class="terminal-panel p-6 flex-1 overflow-auto whitespace-pre h-[600px] text-sm custom-scrollbar">@if($ultimaConfig){{ $ultimaConfig->contenido }}@else
// Selecciona un dispositivo o genera un nuevo respaldo para ver el contenido.
@endif</div>
                    
                    <!-- Barra de estado inferior -->
                    @if($ultimaConfig)
                    <div class="bg-gray-800 px-4 py-2 border-t border-gray-700 text-xs text-gray-400 flex justify-between">
                        <span>Líneas: {{ substr_count($ultimaConfig->contenido, "\n") + 1 }} | Tamaño: {{ number_format(strlen($ultimaConfig->contenido) / 1024, 2) }} KB</span>
                        <span title="SHA-256">Hash: {{ substr($ultimaConfig->checksum_sha256, 0, 16) }}...</span>
                    </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
@endsection

@section('scripts')
    <script>
        function copiarAlPortapapeles() {
            const contenido = document.querySelector('.terminal-panel').innerText;
            navigator.clipboard.writeText(contenido).then(() => {
                nmAviso('¡Configuración copiada al portapapeles!', 'exito');
            }).catch(err => {
                console.error('Error al copiar: ', err);
            });
        }
    </script>
@endsection
