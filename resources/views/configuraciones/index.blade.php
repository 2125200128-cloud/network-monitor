@extends('layouts.app')

@section('styles')
        .text-hacienda-blue { color: #5c8096; }
        .bg-hacienda-blue { background-color: #5c8096; }
        .text-hacienda-orange { color: #e67e22; }
        .bg-hacienda-orange { background-color: #e67e22; }
        .terminal-panel {
            background-color: #111827;
            color: #d1d5db;
            font-family: 'Courier New', Courier, monospace;
        }
    </style>
@endsection

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <header class="mb-8 flex justify-between items-center bg-white dark:bg-[#0d1017] p-6 rounded-2xl shadow-sm dark:shadow-none border border-gray-100 dark:border-slate-800/40">
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
            <div class="mb-6 p-4 rounded-md bg-green-50 border border-green-200 text-green-700">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 p-4 rounded-md bg-red-50 border border-red-200 text-red-700">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Panel Izquierdo: Controles e Historial -->
            <div class="lg:col-span-1 space-y-6">
                
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Seleccionar Dispositivo</h3>
                    <form method="GET" action="{{ route('configuraciones.index') }}" class="mb-6">
                        <select name="dispositivo_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-[#5c8096] focus:ring focus:ring-[#5c8096] focus:ring-opacity-50" onchange="this.form.submit()">
                            @foreach($dispositivos as $d)
                                <option value="{{ $d->id }}" {{ $selectedDispositivoId == $d->id ? 'selected' : '' }}>
                                    {{ $d->nombre }} ({{ $d->ip }})
                                </option>
                            @endforeach
                        </select>
                    </form>

                    <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Generar Nuevo Respaldo</h3>
                    <form method="POST" action="{{ route('configuraciones.respaldar') }}" onsubmit="document.getElementById('btnRespaldar').disabled = true; const t = document.getElementById('btnRespaldar').querySelector('.text'); if(t) { t.innerText = 'Respaldando... (hasta 60s)'; }">
                        @csrf
                        <input type="hidden" name="dispositivo_id" value="{{ $selectedDispositivoId }}">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Configuración</label>
                            <select name="tipo" class="w-full rounded-md border-gray-300 shadow-sm focus:border-[#e67e22] focus:ring focus:ring-[#e67e22] focus:ring-opacity-50">
                                <option value="running">Running-Config</option>
                                <option value="startup">Startup-Config</option>
                            </select>
                        </div>

                        <button type="submit" id="btnRespaldar" class="fancy w-full justify-center">
                            <span class="top-key"></span>
                            <span class="text">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                Obtener Respaldo Actual
                            </span>
                            <span class="bottom-key-1"></span>
                            <span class="bottom-key-2"></span>
                        </button>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Historial de Versiones</h3>
                    
                    @if($historial->count() > 0)
                        <div class="space-y-4 max-h-96 overflow-y-auto">
                            @foreach($historial as $config)
                                <div class="p-3 bg-gray-50 rounded-lg border border-gray-100 flex justify-between items-center {{ $loop->first ? 'border-hacienda-blue bg-blue-50' : '' }}">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800">{{ $config->created_at->format('d/m/Y H:i:s') }}</p>
                                        <p class="text-xs text-gray-500">
                                            <span class="inline-block bg-gray-200 rounded px-1">{{ strtoupper($config->tipo) }}</span>
                                            • {{ $config->user ? $config->user->name : 'Sistema' }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @if(!$loop->first)
                                            <span class="text-xs text-gray-400" title="Hash: {{ substr($config->checksum_sha256, 0, 8) }}...">Old</span>
                                        @else
                                            <span class="text-xs font-bold text-green-600">Latest</span>
                                        @endif
                                        <a href="{{ route('configuraciones.pdf', $config->id) }}" target="_blank" class="p-1 rounded hover:bg-white text-gray-400 hover:text-[#3b5998] transition shadow-xs" title="Exportar respaldo a PDF">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500 italic">No hay respaldos registrados para este dispositivo.</p>
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
                            <span class="ml-4 text-xs font-mono text-gray-400">
                                @if($ultimaConfig)
                                    {{ $ultimaConfig->dispositivo->nombre }} - {{ $ultimaConfig->created_at->format('Y-m-d_H-i-s') }}.cfg
                                @else
                                    Sin configuración disponible
                                @endif
                            </span>
                        </div>
                        @if($ultimaConfig)
                            <div class="flex items-center space-x-3">
                                <button onclick="copiarAlPortapapeles()" class="text-gray-400 hover:text-white transition flex items-center text-sm">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                    Copiar
                                </button>
                                <a href="{{ route('configuraciones.descargar', $ultimaConfig->id) }}" class="text-gray-400 hover:text-white transition flex items-center text-sm">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    Descargar
                                </a>
                                <a href="{{ route('configuraciones.pdf', $ultimaConfig->id) }}" target="_blank" class="bg-[#3b5998] hover:bg-[#2d4677] text-white px-3 py-1.5 rounded-lg font-bold transition flex items-center text-xs shadow-sm">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    Reporte PDF
                                </a>
                            </div>
                        @endif
                    </div>

                    <!-- Contenido del archivo -->
                    <div class="terminal-panel p-6 flex-1 overflow-auto whitespace-pre h-[600px] text-sm">@if($ultimaConfig){{ $ultimaConfig->contenido }}@else
// Selecciona un dispositivo o genera un nuevo respaldo para ver el contenido.
@endif</div>
                    
                    <!-- Barra de estado inferior -->
                    @if($ultimaConfig)
                    <div class="bg-gray-800 px-4 py-2 border-t border-gray-700 text-xs text-gray-500 flex justify-between">
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
        function copiarAlPortapapeles() {
            const contenido = document.querySelector('.terminal-panel').innerText;
            navigator.clipboard.writeText(contenido).then(() => {
                alert('¡Configuración copiada al portapapeles!');
            }).catch(err => {
                console.error('Error al copiar: ', err);
            });
        }
    </script>
</body>
@endsection
