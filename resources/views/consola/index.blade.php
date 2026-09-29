@extends('layouts.app')

@section('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.css" />
    <style>
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
        #terminal-container {
            width: 100%;
            height: 450px;
            background-color: #000;
            padding: 10px;
            border-radius: 8px;
            overflow: hidden;
        }
    </style>
@endsection

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <header class="mb-8 flex justify-between items-center bg-white dark:bg-[#12161f] p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800">
            <div class="flex items-center">
                <div>
                    <h1 class="text-3xl font-bold text-[#5c8096] dark:text-blue-400">
                        Web SSH Terminal
                    </h1>
                    <p class="text-gray-500 dark:text-slate-400 mt-1">Consola interactiva incrustada (Powered by xterm.js)</p>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <a href="{{ url('/') }}" class="text-sm font-medium text-gray-500 dark:text-slate-400 hover:text-[#5c8096] dark:hover:text-blue-400">Volver al Dashboard</a>
            </div>
        </header>

        <div class="glass-panel rounded-2xl p-6">
            <div class="mb-6">
                <label for="dispositivo_id" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Conectar al Dispositivo:</label>
                <select id="dispositivo_id" name="dispositivo_id" class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-slate-700 bg-white dark:bg-[#1a1f2c] text-slate-800 dark:text-slate-200 rounded-md shadow-sm focus:outline-none focus:ring-[#e67e22] focus:border-[#e67e22] sm:text-sm font-medium">
                    <option value="">Seleccione un equipo para iniciar sesión SSH...</option>
                    @foreach($dispositivos as $dispositivo)
                        <option value="{{ $dispositivo->id }}" {{ $dispositivo->ip === '192.168.1.254' ? 'selected' : '' }}>
                            {{ $dispositivo->nombre }} ({{ $dispositivo->ip }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mt-4">
                <div id="terminal-container"></div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectDispositivo = document.getElementById('dispositivo_id');
            const term = new Terminal({
                cursorBlink: true,
                theme: { background: '#000000', foreground: '#00ff00' },
                fontFamily: 'Courier New, monospace',
                fontSize: 14
            });
            const fitAddon = new FitAddon.FitAddon();
            term.loadAddon(fitAddon);
            
            term.open(document.getElementById('terminal-container'));
            fitAddon.fit();

            let currentLine = '';
            let isExecuting = false;
            let currentHostname = 'router# ';

            function writePrompt() {
                term.write('\r\n' + currentHostname);
            }

            term.write('Welcome to Network Monitor Web SSH\r\n');
            term.write('Select a device from the dropdown to start.\r\n');
            
            selectDispositivo.addEventListener('change', function() {
                if (this.value) {
                    const optText = this.options[this.selectedIndex].text;
                    const hostname = optText.split(' ')[0];
                    currentHostname = hostname + '# ';
                    term.write('\r\nConnecting to ' + optText + '...\r\n');
                    term.write('Connection established.\r\n');
                    writePrompt();
                } else {
                    currentHostname = 'router# ';
                }
            });

            term.onData(e => {
                if (isExecuting || !selectDispositivo.value) return;

                switch (e) {
                    case '\r': // Enter
                        if (currentLine.trim() !== '') {
                            executeCommand(currentLine.trim());
                        } else {
                            writePrompt();
                        }
                        currentLine = '';
                        break;
                    case '\u007F': // Backspace
                        if (currentLine.length > 0) {
                            currentLine = currentLine.substring(0, currentLine.length - 1);
                            term.write('\b \b');
                        }
                        break;
                    default:
                        if (e >= String.fromCharCode(0x20) && e <= String.fromCharCode(0x7E) || e >= '\u00a0') {
                            currentLine += e;
                            term.write(e);
                        }
                }
            });

            window.addEventListener('resize', () => {
                fitAddon.fit();
            });

            function executeCommand(comando) {
                isExecuting = true;
                const dispositivoId = selectDispositivo.value;
                term.write('\r\n'); // Mueve al usuario a la siguiente línea mientras espera

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
                        const outputLines = res.body.output.replace(/\r\n/g, '\n').split('\n');
                        outputLines.forEach(line => {
                            term.write(line + '\r\n');
                        });
                    } else {
                        term.write('\x1b[31mError (' + res.status + '): ' + (res.body.error || res.body.message || 'Fallo') + '\x1b[0m\r\n');
                    }
                })
                .catch(error => {
                    term.write('\x1b[31mNetwork Error: Cannot reach server.\x1b[0m\r\n');
                })
                .finally(() => {
                    isExecuting = false;
                    writePrompt();
                });
            }
        });
    </script>
@endsection
