@extends('layouts.app')

@section('styles')
        .glass-panel {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }
        .dark .glass-panel {
            background: rgba(18, 22, 31, 0.85);
            border-color: rgba(255, 255, 255, 0.08);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
        }
    </style>
@endsection

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <header class="mb-8 flex justify-between items-center bg-white dark:bg-[#12161f] p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800">
            <div class="flex items-center">
                <div>
                    <h1 class="text-3xl font-bold text-[#5c8096] dark:text-blue-400">
                        Consola Segura CLI
                    </h1>
                    <p class="text-gray-500 dark:text-slate-400 mt-1">Diagnóstico remoto con auditoría inmutable</p>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <a href="{{ url('/') }}" class="text-sm font-medium text-gray-500 dark:text-slate-400 hover:text-[#5c8096] dark:hover:text-blue-400">Volver al Dashboard</a>
            </div>
        </header>

        <div class="glass-panel rounded-2xl p-6">
            <div class="mb-6">
                <label for="dispositivo_id" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Seleccionar Dispositivo Destino</label>
                <select id="dispositivo_id" name="dispositivo_id" class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-slate-700 bg-white dark:bg-[#1a1f2c] text-slate-800 dark:text-slate-200 rounded-md shadow-sm focus:outline-none focus:ring-[#e67e22] focus:border-[#e67e22] sm:text-sm font-medium">
                    <option value="">Seleccione un dispositivo...</option>
                    @foreach($dispositivos as $dispositivo)
                        <option value="{{ $dispositivo->id }}" {{ $dispositivo->ip === '192.168.1.254' ? 'selected' : '' }}>
                            {{ $dispositivo->nombre }} ({{ $dispositivo->ip }}) — [{{ strtoupper($dispositivo->estado) }}]
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Comandos Rápidos (Lista Blanca)</label>
                <div class="flex flex-wrap gap-2">
                    @php
                        $comandosPermitidos = [
                            'show version',
                            'show ip interface brief',
                            'show interfaces status',
                            'show running-config',
                            'show vlan brief',
                            'show mac address-table'
                        ];
                    @endphp
                    @foreach($comandosPermitidos as $cmd)
                        <button type="button" class="btn-comando px-3 py-1 bg-[#5c8096] text-white rounded text-sm hover:bg-[#4a6b7d] transition-colors font-mono" data-cmd="{{ $cmd }}">
                            {{ $cmd }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="mb-6">
                <label for="comando" class="block text-sm font-medium text-gray-700">Comando a Ejecutar</label>
                <div class="mt-1 flex rounded-md shadow-sm">
                    <input type="text" name="comando" id="comando" class="flex-1 min-w-0 block w-full px-3 py-2 border border-gray-300 rounded-none rounded-l-md focus:ring-[#e67e22] focus:border-[#e67e22] sm:text-sm font-mono" placeholder="Ej. show ip interface brief o ping 192.168.1.50">
                    <button type="button" id="btn-ejecutar" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-r-md text-white bg-[#e67e22] hover:bg-[#d67118] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#e67e22]">
                        Ejecutar
                    </button>
                </div>
                <p class="mt-2 text-sm text-gray-500" id="estado-ejecucion"></p>
            </div>

            <div class="mt-8">
                <div class="flex justify-between items-center mb-2">
                    <label class="block text-sm font-medium text-gray-700">Terminal Output</label>
                    <button type="button" onclick="document.getElementById('terminal-output').innerHTML='Bienvenido a la Consola Segura.\nSeleccione un dispositivo y ejecute un comando.'" class="text-xs text-gray-400 hover:text-gray-600 transition">
                        Limpiar Pantalla
                    </button>
                </div>
                <div class="bg-[#111827] rounded-lg p-4 overflow-x-auto h-[400px] overflow-y-auto" id="terminal-container">
                    <pre id="terminal-output" class="text-green-400 font-mono text-sm whitespace-pre-wrap">Bienvenido a la Consola Segura.
Seleccione un dispositivo y ejecute un comando.</pre>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btnEjecutar = document.getElementById('btn-ejecutar');
            const inputComando = document.getElementById('comando');
            const selectDispositivo = document.getElementById('dispositivo_id');
            const terminalOutput = document.getElementById('terminal-output');
            const terminalContainer = document.getElementById('terminal-container');
            const estadoEjecucion = document.getElementById('estado-ejecucion');

            document.querySelectorAll('.btn-comando').forEach(button => {
                button.addEventListener('click', function() {
                    inputComando.value = this.dataset.cmd;
                    inputComando.focus();
                    ejecutarComando();
                });
            });

            btnEjecutar.addEventListener('click', ejecutarComando);
            inputComando.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    ejecutarComando();
                }
            });

            function ejecutarComando() {
                const dispositivoId = selectDispositivo.value;
                const comando = inputComando.value.trim();

                if (!dispositivoId) {
                    alert('Por favor, seleccione un dispositivo destino.');
                    return;
                }

                if (!comando) {
                    alert('Por favor, ingrese un comando.');
                    return;
                }

                btnEjecutar.disabled = true;
                btnEjecutar.innerHTML = 'Ejecutando...';
                estadoEjecucion.textContent = 'Procesando comando...';
                estadoEjecucion.className = 'mt-2 text-sm text-blue-500';
                
                const optText = selectDispositivo.options[selectDispositivo.selectedIndex].text;
                terminalOutput.innerHTML += `\n\n<span class="text-yellow-400 font-bold">&gt; [${escapeHtml(optText)}] ${escapeHtml(comando)}</span>\n`;
                scrollToBottom();

                fetch('{{ route('consola.ejecutar') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        dispositivo_id: dispositivoId,
                        comando: comando
                    })
                })
                .then(response => response.json().then(data => ({ status: response.status, body: data })))
                .then(res => {
                    if (res.status === 200) {
                        estadoEjecucion.textContent = 'Comando ejecutado con éxito.';
                        estadoEjecucion.className = 'mt-2 text-sm text-green-500';
                        terminalOutput.innerHTML += `<span class="text-green-300">${escapeHtml(res.body.output)}</span>\n`;
                    } else {
                        estadoEjecucion.textContent = 'Error en la ejecución.';
                        estadoEjecucion.className = 'mt-2 text-sm text-red-500';
                        terminalOutput.innerHTML += `<span class="text-red-400">Error (${res.status}): ${escapeHtml(res.body.error || res.body.message || 'Fallo en la comunicación')}</span>\n`;
                    }
                    scrollToBottom();
                })
                .catch(error => {
                    console.error('Error:', error);
                    estadoEjecucion.textContent = 'Error de conexión.';
                    estadoEjecucion.className = 'mt-2 text-sm text-red-500';
                    terminalOutput.innerHTML += `<span class="text-red-400">Fallo de red al intentar ejecutar el comando.</span>\n`;
                    scrollToBottom();
                })
                .finally(() => {
                    btnEjecutar.disabled = false;
                    btnEjecutar.innerHTML = 'Ejecutar';
                });
            }

            function escapeHtml(unsafe) {
                if(!unsafe) return '';
                return unsafe
                     .replace(/&/g, "&amp;")
                     .replace(/</g, "&lt;")
                     .replace(/>/g, "&gt;")
                     .replace(/"/g, "&quot;")
                     .replace(/'/g, "&#039;");
            }

            function scrollToBottom() {
                terminalContainer.scrollTop = terminalContainer.scrollHeight;
            }
        });
    </script>
@endsection
