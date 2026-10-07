@extends('layouts.app')

@section('styles')
        .glass-panel {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }
    </style>
@endsection

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <header class="mb-8 flex justify-between items-center bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
            <div class="flex items-center">
                <div>
                    <h1 class="text-3xl font-bold text-[#5c8096]">
                        Configuración de VLANs
                    </h1>
                    <p class="text-gray-500 mt-1">Aprovisionamiento y asignación de puertos</p>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <a href="{{ url('/') }}" class="text-sm font-medium text-gray-500 hover:text-[#5c8096]">Volver al Dashboard</a>
            </div>
        </header>

        <!-- Main Selector -->
        <div class="glass-panel rounded-2xl p-6 mb-8">
            <label for="global_dispositivo_id" class="block text-sm font-medium text-gray-700">Seleccionar Dispositivo Destino</label>
            <select id="global_dispositivo_id" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-[#e67e22] focus:border-[#e67e22] sm:text-sm">
                <option value="">Seleccione un dispositivo...</option>
                @foreach($dispositivos as $dispositivo)
                    <option value="{{ $dispositivo->id }}">{{ $dispositivo->nombre }} ({{ $dispositivo->ip }})</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            
            <!-- Crear VLAN Form -->
            <div class="glass-panel rounded-2xl p-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">Crear Nueva VLAN</h3>
                <form id="form-create-vlan">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">VLAN ID (2-4094)</label>
                        <input type="number" id="vlan_id" min="2" max="4094" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-[#e67e22] focus:border-[#e67e22] sm:text-sm">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Nombre de la VLAN</label>
                        <input type="text" id="vlan_name" required pattern="^[a-zA-Z0-9_-]+$" title="Sin espacios ni caracteres especiales" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-[#e67e22] focus:border-[#e67e22] sm:text-sm" placeholder="Ej. FINANZAS">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">IP Address (Opcional - para interfaz SVI)</label>
                        <input type="text" id="ip_address" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-[#e67e22] focus:border-[#e67e22] sm:text-sm" placeholder="Ej. 192.168.10.1">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Máscara de Subred (Opcional)</label>
                        <input type="text" id="subnet_mask" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-[#e67e22] focus:border-[#e67e22] sm:text-sm" placeholder="Ej. 255.255.255.0">
                    </div>
                    <button type="button" id="btn-create-vlan" class="fancy w-full justify-center">
                        <span class="top-key"></span>
                        <span class="text">Crear VLAN y Guardar</span>
                        <span class="bottom-key-1"></span>
                        <span class="bottom-key-2"></span>
                    </button>
                </form>
            </div>

            <!-- Asignar Puertos Form -->
            <div class="glass-panel rounded-2xl p-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">Asignar Interfaz (Switchport)</h3>
                <form id="form-assign-port">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Nombre de la Interfaz</label>
                        <input type="text" id="interface_name" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-[#e67e22] focus:border-[#e67e22] sm:text-sm" placeholder="Ej. GigabitEthernet0/1 o gi0/1">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Modo de Operación</label>
                        <select id="mode" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-[#e67e22] focus:border-[#e67e22] sm:text-sm">
                            <option value="access">Access</option>
                            <option value="trunk">Trunk</option>
                        </select>
                    </div>
                    <div class="mb-4" id="vlan-id-container">
                        <label class="block text-sm font-medium text-gray-700">VLAN ID (solo modo access)</label>
                        <input type="number" id="port_vlan_id" min="2" max="4094" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-[#e67e22] focus:border-[#e67e22] sm:text-sm">
                    </div>
                    <button type="button" id="btn-assign-port" class="fancy w-full justify-center mt-4">
                        <span class="top-key"></span>
                        <span class="text">Aplicar Configuración</span>
                        <span class="bottom-key-1"></span>
                        <span class="bottom-key-2"></span>
                    </button>
                </form>
            </div>
            
        </div>

        <div class="glass-panel rounded-2xl p-6">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Terminal de Configuración (Live Output)</h3>
            <div class="bg-[#111827] rounded-lg p-4 overflow-x-auto h-[400px] overflow-y-auto" id="terminal-container">
                <pre id="terminal-output" class="text-green-400 font-mono text-sm whitespace-pre-wrap">Esperando comandos de configuración...</pre>
            </div>
        </div>
    </div>

@endsection

@section('scripts')
        document.addEventListener('DOMContentLoaded', function() {
            const terminalOutput = document.getElementById('terminal-output');
            const terminalContainer = document.getElementById('terminal-container');
            const globalDispositivoId = document.getElementById('global_dispositivo_id');
            const btnCreateVlan = document.getElementById('btn-create-vlan');
            const btnAssignPort = document.getElementById('btn-assign-port');
            const modeSelect = document.getElementById('mode');
            const portVlanContainer = document.getElementById('vlan-id-container');

            modeSelect.addEventListener('change', function() {
                if (this.value === 'trunk') {
                    portVlanContainer.style.display = 'none';
                    document.getElementById('port_vlan_id').value = '';
                } else {
                    portVlanContainer.style.display = 'block';
                }
            });

            function executeApiCall(url, payload, btn) {
                const dispositivoId = globalDispositivoId.value;
                if (!dispositivoId) {
                    alert('Debe seleccionar un dispositivo destino primero.');
                    return;
                }

                payload.dispositivo_id = dispositivoId;

                btn.disabled = true;
                const originalText = btn.innerHTML;
                const textSpan = btn.querySelector('.text');
                if (textSpan) {
                    textSpan.innerText = 'Procesando...';
                } else {
                    btn.innerHTML = 'Procesando...';
                }
                
                terminalOutput.textContent += `\n\n> Enviando configuración al dispositivo ${dispositivoId}...\n`;
                scrollToBottom();

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                })
                .then(response => response.json().then(data => ({ status: response.status, body: data })))
                .then(res => {
                    if (res.status === 200 || res.status === 201) {
                        terminalOutput.innerHTML += `\n<span class="text-green-300">${escapeHtml(res.body.output || 'Configuración exitosa.')}</span>\n`;
                    } else {
                        let errors = '';
                        if (res.body.errors) {
                            errors = Object.values(res.body.errors).flat().join(' | ');
                        } else {
                            errors = res.body.error || res.body.message;
                        }
                        terminalOutput.innerHTML += `\n<span class="text-amber-500">Error (${res.status}): ${escapeHtml(errors)}</span>\n`;
                    }
                    scrollToBottom();
                })
                .catch(error => {
                    console.error('Error:', error);
                    terminalOutput.innerHTML += `\n<span class="text-red-500">Error de conexión al servidor de la aplicación.</span>\n`;
                    scrollToBottom();
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
            }

            btnCreateVlan.addEventListener('click', function() {
                // Form validation simple
                const vlanId = document.getElementById('vlan_id').value;
                const vlanName = document.getElementById('vlan_name').value;
                
                if(!vlanId || !vlanName) {
                    alert('VLAN ID y Nombre son obligatorios.');
                    return;
                }

                const payload = {
                    vlan_id: vlanId,
                    vlan_name: vlanName,
                    ip_address: document.getElementById('ip_address').value,
                    subnet_mask: document.getElementById('subnet_mask').value
                };

                executeApiCall('{{ route("vlans.provision") }}', payload, btnCreateVlan);
            });

            btnAssignPort.addEventListener('click', function() {
                const interfaceName = document.getElementById('interface_name').value;
                const mode = document.getElementById('mode').value;
                const vlanId = document.getElementById('port_vlan_id').value;

                if(!interfaceName) {
                    alert('El nombre de la interfaz es obligatorio.');
                    return;
                }
                
                if (mode === 'access' && !vlanId) {
                    alert('Para modo access, se requiere especificar el VLAN ID.');
                    return;
                }

                const payload = {
                    interface_name: interfaceName,
                    mode: mode,
                    vlan_id: vlanId
                };

                executeApiCall('{{ route("vlans.assign_port") }}', payload, btnAssignPort);
            });

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
