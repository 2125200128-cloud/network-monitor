# -*- coding: utf-8 -*-
import re

with open('resources/views/dashboard.blade.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Update Top Bar Search Input
old_search = '<input type="text" class="w-full bg-white rounded-full py-3 pl-12 pr-4 border-none shadow-sm focus:ring-2 focus:ring-hacienda-blue text-sm" placeholder="Buscar IPs, alertas, switches...">'
new_search = '<input type="text" class="w-full bg-white dark:bg-[#12161f] border border-gray-100 dark:border-slate-800 rounded-full py-3 pl-12 pr-4 shadow-sm focus:ring-2 focus:ring-hacienda-blue dark:focus:ring-blue-500 text-sm text-gray-800 dark:text-slate-100 placeholder-gray-400 dark:placeholder-slate-500" placeholder="Buscar IPs, alertas, switches...">'
content = content.replace(old_search, new_search)

# 2. Insert Theme Toggle Button next to PDF Report / 2FA in Top Bar
toggle_btn_html = '''                    <!-- Theme Toggle Button (Alpine.js) -->
                    <div x-data="{ 
                        isDark: document.documentElement.classList.contains('dark'),
                        toggle() {
                            this.isDark = window.toggleTheme();
                        }
                    }" @theme-changed.window="isDark = $event.detail.isDark">
                        <button 
                            @click="toggle()" 
                            type="button" 
                            class="w-10 h-10 bg-white dark:bg-[#12161f] rounded-full flex items-center justify-center text-gray-500 dark:text-slate-300 hover:text-amber-500 dark:hover:text-cyan-300 shadow-sm border border-gray-100 dark:border-slate-800 transition relative group" 
                            title="Alternar Modo Claro / Oscuro">
                            <!-- Sun (Light Mode) -->
                            <svg x-show="!isDark" class="w-5 h-5 text-amber-500 transition-transform duration-300 group-hover:rotate-45" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                            <!-- Moon (Dark Mode) -->
                            <svg x-show="isDark" class="w-5 h-5 text-cyan-400 transition-transform duration-300 group-hover:-rotate-12 drop-shadow-[0_0_8px_rgba(34,211,238,0.5)]" style="display: none;" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                            </svg>
                        </button>
                    </div>\n'''

# Update PDF button classes
old_pdf = 'class="hidden sm:inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-gray-50 text-gray-700 text-xs font-bold rounded-full shadow-sm transition border border-gray-100 hover:border-gray-200"'
new_pdf = 'class="hidden sm:inline-flex items-center gap-1.5 px-3.5 py-2 bg-white dark:bg-[#12161f] hover:bg-gray-50 dark:hover:bg-slate-800 text-gray-700 dark:text-slate-300 text-xs font-bold rounded-full shadow-sm transition border border-gray-100 dark:border-slate-800 hover:border-gray-200"'
content = content.replace(old_pdf, new_pdf)

# Update 2FA button
old_2fa = '<a href="{{ route(\'setup-2fa\') }}" class="w-10 h-10 bg-white rounded-full flex items-center justify-center text-gray-500 hover:text-hacienda-blue shadow-sm transition" title="Configurar 2FA">'
new_2fa = '<a href="{{ route(\'setup-2fa\') }}" class="w-10 h-10 bg-white dark:bg-[#12161f] rounded-full flex items-center justify-center text-gray-500 dark:text-slate-400 hover:text-hacienda-blue dark:hover:text-blue-400 shadow-sm border border-gray-100 dark:border-slate-800 transition" title="Configurar 2FA">'
content = content.replace(old_2fa, toggle_btn_html + '                    ' + new_2fa)

# Update Bell Button
old_bell = 'class="w-10 h-10 bg-white rounded-full flex items-center justify-center text-gray-500 hover:text-hacienda-orange shadow-sm transition relative focus:outline-none focus:ring-2 focus:ring-[#3b5998]/30"'
new_bell = 'class="w-10 h-10 bg-white dark:bg-[#12161f] rounded-full flex items-center justify-center text-gray-500 dark:text-slate-400 hover:text-hacienda-orange shadow-sm border border-gray-100 dark:border-slate-800 transition relative focus:outline-none focus:ring-2 focus:ring-[#3b5998]/30"'
content = content.replace(old_bell, new_bell)

# Update Dropdown Card
old_dropdown = 'class="hidden absolute right-0 mt-3 w-80 sm:w-96 md:w-[440px] bg-white rounded-2xl shadow-2xl border border-gray-100 z-50 overflow-hidden transform transition-all duration-200 origin-top-right"'
new_dropdown = 'class="hidden absolute right-0 mt-3 w-80 sm:w-96 md:w-[440px] bg-white dark:bg-[#12161f] rounded-2xl shadow-2xl border border-gray-100 dark:border-slate-800 z-50 overflow-hidden transform transition-all duration-200 origin-top-right"'
content = content.replace(old_dropdown, new_dropdown)

old_drop_header = 'class="p-4 bg-gradient-to-r from-slate-50 to-blue-50/40 border-b border-gray-100 flex items-center justify-between"'
new_drop_header = 'class="p-4 bg-gradient-to-r from-slate-50 to-blue-50/40 dark:from-slate-900/90 dark:to-slate-800/90 border-b border-gray-100 dark:border-slate-800 flex items-center justify-between"'
content = content.replace(old_drop_header, new_drop_header)

old_drop_title = '<h3 class="text-sm font-bold text-gray-900 leading-tight">Centro de Notificaciones</h3>'
new_drop_title = '<h3 class="text-sm font-bold text-gray-900 dark:text-white leading-tight">Centro de Notificaciones</h3>'
content = content.replace(old_drop_title, new_drop_title)

old_drop_tabs = 'class="px-4 py-2 bg-gray-50/70 border-b border-gray-100 flex items-center gap-1.5 overflow-x-auto text-xs"'
new_drop_tabs = 'class="px-4 py-2 bg-gray-50/70 dark:bg-slate-900/80 border-b border-gray-100 dark:border-slate-800 flex items-center gap-1.5 overflow-x-auto text-xs"'
content = content.replace(old_drop_tabs, new_drop_tabs)

# Hero Banner
old_hero = 'class="bg-white rounded-[2rem] p-6 lg:p-8 text-gray-800 mb-8 relative overflow-hidden shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4 lg:gap-6 min-h-min"'
new_hero = 'class="bg-white dark:bg-[#12161f] border border-gray-100 dark:border-slate-800/80 rounded-[2rem] p-6 lg:p-8 text-gray-800 dark:text-slate-100 mb-8 relative overflow-hidden shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4 lg:gap-6 min-h-min"'
content = content.replace(old_hero, new_hero)

old_hero_title = '<h2 class="text-2xl lg:text-4xl font-bold mb-2 text-gray-900 leading-tight">{{ $saludo }}, {{ $nombreCompleto }}!</h2>'
new_hero_title = '<h2 class="text-2xl lg:text-4xl font-bold mb-2 text-gray-900 dark:text-white leading-tight">{{ $saludo }}, {{ $nombreCompleto }}!</h2>'
content = content.replace(old_hero_title, new_hero_title)

old_hero_desc = '<p class="text-gray-500 text-sm lg:text-lg max-w-2xl">Estado de la red estable. Tienes <span class="font-bold text-hacienda-blue">{{ $dispositivosOnline }}</span> dispositivos monitoreados activos y respondiendo correctamente.</p>'
new_hero_desc = '<p class="text-gray-500 dark:text-slate-400 text-sm lg:text-lg max-w-2xl">Estado de la red estable. Tienes <span class="font-bold text-hacienda-blue dark:text-blue-400">{{ $dispositivosOnline }}</span> dispositivos monitoreados activos y respondiendo correctamente.</p>'
content = content.replace(old_hero_desc, new_hero_desc)

# Mini Stat Cards
content = content.replace(
    'class="text-2xl font-bold text-gray-900 font-mono tracking-tight">{{ number_format($avgConexiones) }}</span>',
    'class="text-2xl font-bold text-gray-900 dark:text-white font-mono tracking-tight">{{ number_format($avgConexiones) }}</span>'
)
content = content.replace(
    'class="text-2xl font-bold text-gray-900 font-mono tracking-tight">{{ round($avgPing) }}</span>',
    'class="text-2xl font-bold text-gray-900 dark:text-white font-mono tracking-tight">{{ round($avgPing) }}</span>'
)
content = content.replace(
    'class="text-2xl font-bold text-gray-900 font-mono tracking-tight">{{ number_format($avgPacketLoss, 2) }}</span>',
    'class="text-2xl font-bold text-gray-900 dark:text-white font-mono tracking-tight">{{ number_format($avgPacketLoss, 2) }}</span>'
)

# Concentric Donut Center
content = content.replace(
    '<span class="text-2xl font-extrabold text-gray-900 font-mono leading-none tracking-tight">{{ round($avgCpu) }}<span class="text-xs text-gray-400 font-sans ml-0.5">%</span></span>',
    '<span class="text-2xl font-extrabold text-gray-900 dark:text-white font-mono leading-none tracking-tight">{{ round($avgCpu) }}<span class="text-xs text-gray-400 dark:text-slate-500 font-sans ml-0.5">%</span></span>'
)
content = content.replace(
    'class="flex items-center justify-between p-2.5 rounded-xl bg-gray-50/70 border border-gray-100"',
    'class="flex items-center justify-between p-2.5 rounded-xl bg-gray-50/70 dark:bg-slate-900/60 border border-gray-100 dark:border-slate-800"'
)
content = content.replace(
    '<p class="text-xs font-bold text-gray-800">CPU Consumo</p>',
    '<p class="text-xs font-bold text-gray-800 dark:text-slate-200">CPU Consumo</p>'
)
content = content.replace(
    '<p class="text-xs font-bold text-gray-800">Memoria RAM</p>',
    '<p class="text-xs font-bold text-gray-800 dark:text-slate-200">Memoria RAM</p>'
)
content = content.replace(
    '<p class="text-xs font-bold text-gray-800">Nodos Activos</p>',
    '<p class="text-xs font-bold text-gray-800 dark:text-slate-200">Nodos Activos</p>'
)
content = content.replace(
    '<span class="text-lg font-extrabold text-gray-900 font-mono">{{ round($avgCpu) }}%</span>',
    '<span class="text-lg font-extrabold text-gray-900 dark:text-white font-mono">{{ round($avgCpu) }}%</span>'
)
content = content.replace(
    '<span class="text-lg font-extrabold text-gray-900 font-mono">{{ round($avgMem) }}%</span>',
    '<span class="text-lg font-extrabold text-gray-900 dark:text-white font-mono">{{ round($avgMem) }}%</span>'
)
content = content.replace(
    '<span class="text-sm font-bold text-gray-900 font-mono">{{ $dispositivosOnline }} / {{ $totalDispositivos }}</span>',
    '<span class="text-sm font-bold text-gray-900 dark:text-white font-mono">{{ $dispositivosOnline }} / {{ $totalDispositivos }}</span>'
)

# Table Card Updates
content = content.replace(
    '<h3 class="text-lg font-bold text-gray-900 tracking-tight">Inventario de Infraestructura y Switches</h3>',
    '<h3 class="text-lg font-bold text-gray-900 dark:text-white tracking-tight">Inventario de Infraestructura y Switches</h3>'
)
content = content.replace(
    '<p class="text-xs text-gray-500 mt-0.5">Supervisión en tiempo real de nodos de red y switches L2/L3</p>',
    '<p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">Supervisión en tiempo real de nodos de red y switches L2/L3</p>'
)
content = content.replace(
    'class="flex bg-gray-100/80 p-1 rounded-xl text-xs font-semibold"',
    'class="flex bg-gray-100/80 dark:bg-slate-900/80 border border-transparent dark:border-slate-800 p-1 rounded-xl text-xs font-semibold"'
)
content = content.replace(
    ":class=\"filtroEstado === 'todos' ? 'bg-white text-hacienda-blue shadow-sm font-bold' : 'text-gray-500 hover:text-gray-800'\"",
    ":class=\"filtroEstado === 'todos' ? 'bg-white dark:bg-slate-800 text-hacienda-blue dark:text-blue-400 shadow-sm font-bold' : 'text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-slate-200'\""
)
content = content.replace(
    ":class=\"filtroEstado === 'online' ? 'bg-white text-emerald-600 shadow-sm font-bold' : 'text-gray-500 hover:text-gray-800'\"",
    ":class=\"filtroEstado === 'online' ? 'bg-white dark:bg-slate-800 text-emerald-600 dark:text-emerald-400 shadow-sm font-bold' : 'text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-slate-200'\""
)
content = content.replace(
    ":class=\"filtroEstado === 'offline' ? 'bg-white text-red-600 shadow-sm font-bold' : 'text-gray-500 hover:text-gray-800'\"",
    ":class=\"filtroEstado === 'offline' ? 'bg-white dark:bg-slate-800 text-red-600 dark:text-red-400 shadow-sm font-bold' : 'text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-slate-200'\""
)

# Search Input in Table
old_table_search = 'class="w-full pl-9 pr-8 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-hacienda-blue focus:border-transparent outline-none transition placeholder-gray-400 text-gray-800"'
new_table_search = 'class="w-full pl-9 pr-8 py-2 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-hacienda-blue dark:focus:ring-blue-500 focus:border-transparent outline-none transition placeholder-gray-400 dark:placeholder-slate-500 text-gray-800 dark:text-slate-200"'
content = content.replace(old_table_search, new_table_search)

# Table Container
content = content.replace(
    'class="rounded-xl border border-gray-200/80 overflow-hidden shadow-xs bg-white"',
    'class="rounded-xl border border-gray-200/80 dark:border-slate-800 overflow-hidden shadow-xs bg-white dark:bg-[#12161f]"'
)
content = content.replace(
    '<thead class="sticky top-0 z-20 shadow-[0_2px_4px_rgba(0,0,0,0.04)]">',
    '<thead class="sticky top-0 z-20 shadow-[0_2px_4px_rgba(0,0,0,0.04)] dark:shadow-[0_2px_8px_rgba(0,0,0,0.5)]">'
)
content = content.replace(
    'bg-slate-50/95 backdrop-blur-md border-b border-gray-200',
    'bg-slate-50/95 dark:bg-[#161b26]/95 backdrop-blur-md border-b border-gray-200 dark:border-slate-800 text-gray-500 dark:text-slate-400'
)
content = content.replace(
    '<tbody class="divide-y divide-gray-100 bg-white">',
    '<tbody class="divide-y divide-gray-100 dark:divide-slate-800/60 bg-white dark:bg-[#12161f]">'
)
content = content.replace(
    'class="hover:bg-blue-50/40 transition duration-150 group">',
    'class="hover:bg-blue-50/40 dark:hover:bg-slate-800/40 transition duration-150 group">'
)
content = content.replace(
    'whitespace-nowrap border-b border-gray-100',
    'whitespace-nowrap border-b border-gray-100 dark:border-slate-800/60'
)
content = content.replace(
    'class="py-2.5 px-3.5 border-b border-gray-100"',
    'class="py-2.5 px-3.5 border-b border-gray-100 dark:border-slate-800/60"'
)
content = content.replace(
    'class="w-7 h-7 rounded-lg {{ $disp->estado === \'online\' ? \'bg-blue-50 text-hacienda-blue\' : \'bg-gray-100 text-gray-400\' }} flex items-center justify-center flex-shrink-0 font-bold"',
    'class="w-7 h-7 rounded-lg {{ $disp->estado === \'online\' ? \'bg-blue-50 dark:bg-blue-950/60 text-hacienda-blue dark:text-blue-400\' : \'bg-gray-100 dark:bg-slate-800 text-gray-400 dark:text-slate-500\' }} flex items-center justify-center flex-shrink-0 font-bold"'
)
content = content.replace(
    'class="font-bold text-gray-900 group-hover:text-hacienda-blue hover:underline transition truncate block"',
    'class="font-bold text-gray-900 dark:text-slate-100 group-hover:text-hacienda-blue dark:group-hover:text-blue-400 hover:underline transition truncate block"'
)
content = content.replace(
    'class="text-[10px] text-gray-400 font-mono truncate block"',
    'class="text-[10px] text-gray-400 dark:text-slate-500 font-mono truncate block"'
)
content = content.replace(
    'class="font-mono text-xs text-gray-700 bg-gray-50 px-2 py-0.5 rounded-md border border-gray-200"',
    'class="font-mono text-xs text-gray-700 dark:text-slate-300 bg-gray-50 dark:bg-slate-900 px-2 py-0.5 rounded-md border border-gray-200 dark:border-slate-700"'
)
content = content.replace(
    'class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 text-[#5c8096] border border-slate-200"',
    'class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-[#5c8096] dark:text-slate-300 border border-slate-200 dark:border-slate-700"'
)
content = content.replace(
    'class="w-14 bg-gray-100 rounded-full h-1.5 overflow-hidden flex-shrink-0"',
    'class="w-14 bg-gray-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden flex-shrink-0"'
)
content = content.replace(
    'class="font-mono font-bold text-gray-800 text-xs"',
    'class="font-mono font-bold text-gray-800 dark:text-slate-200 text-xs"'
)
content = content.replace(
    'class="font-mono text-xs {{ $disp->estado === \'online\' ? \'text-gray-600\' : \'text-gray-400\' }}"',
    'class="font-mono text-xs {{ $disp->estado === \'online\' ? \'text-gray-600 dark:text-slate-400\' : \'text-gray-400 dark:text-slate-600\' }}"'
)
content = content.replace(
    'class="px-2.5 py-1 rounded-lg text-xs font-semibold text-hacienda-blue bg-blue-50 hover:bg-hacienda-blue hover:text-white transition flex items-center gap-1 shadow-xs"',
    'class="px-2.5 py-1 rounded-lg text-xs font-semibold text-hacienda-blue dark:text-blue-400 bg-blue-50 dark:bg-blue-950/60 hover:bg-hacienda-blue dark:hover:bg-blue-600 hover:text-white dark:hover:text-white transition flex items-center gap-1 shadow-xs border border-transparent dark:border-blue-900/40"'
)
content = content.replace(
    'class="px-2.5 py-1 rounded-lg text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-900 hover:text-emerald-400 transition font-mono flex items-center gap-1 shadow-xs"',
    'class="px-2.5 py-1 rounded-lg text-xs font-semibold text-gray-700 dark:text-slate-300 bg-gray-100 dark:bg-slate-800 hover:bg-gray-900 dark:hover:bg-slate-700 hover:text-emerald-400 transition font-mono flex items-center gap-1 shadow-xs border border-transparent dark:border-slate-700"'
)

# Table Footer
content = content.replace(
    'class="mt-3.5 pt-3 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between text-xs text-gray-400 gap-2"',
    'class="mt-3.5 pt-3 border-t border-gray-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between text-xs text-gray-400 dark:text-slate-400 gap-2"'
)
content = content.replace(
    '<span class="font-medium text-gray-600">Desplazamiento interno independiente · <strong class="text-gray-800">{{ $totalDispositivos }}</strong> nodos monitoreados</span>',
    '<span class="font-medium text-gray-600 dark:text-slate-400">Desplazamiento interno independiente · <strong class="text-gray-800 dark:text-white">{{ $totalDispositivos }}</strong> nodos monitoreados</span>'
)
content = content.replace(
    'class="px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 hover:bg-gray-200 text-gray-600 transition flex items-center gap-1"',
    'class="px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 text-gray-600 dark:text-slate-300 transition flex items-center gap-1 border border-transparent dark:border-slate-700"'
)

# Right Sidebar
content = content.replace(
    '<p class="text-sm font-extrabold text-hacienda-blue mt-0.5">Top Switches L2/L3</p>',
    '<p class="text-sm font-extrabold text-hacienda-blue dark:text-blue-400 mt-0.5">Top Switches L2/L3</p>'
)
content = content.replace(
    'class="text-xs font-bold font-mono px-2.5 py-1 rounded-full bg-blue-50 text-hacienda-blue border border-blue-100"',
    'class="text-xs font-bold font-mono px-2.5 py-1 rounded-full bg-blue-50 dark:bg-blue-950/60 text-hacienda-blue dark:text-blue-400 border border-blue-100 dark:border-blue-900/40"'
)
content = content.replace(
    'class="flex items-center relative pl-4 hover:bg-blue-50/30 p-2 rounded-xl transition group"',
    'class="flex items-center relative pl-4 hover:bg-blue-50/30 dark:hover:bg-slate-800/40 p-2 rounded-xl transition group"'
)
content = content.replace(
    'class="text-xs font-bold text-gray-800 group-hover:text-hacienda-blue group-hover:underline transition truncate min-w-0"',
    'class="text-xs font-bold text-gray-800 dark:text-slate-200 group-hover:text-hacienda-blue dark:group-hover:text-blue-400 group-hover:underline transition truncate min-w-0"'
)
content = content.replace(
    '<span class="font-mono text-[10px]">CPU: <strong class="text-gray-700">{{ $device->latest_cpu }}%</strong> | Loss: <strong class="{{ $device->latest_loss > 0 ? \'text-red-500\' : \'text-emerald-600\' }}">{{ number_format($device->latest_loss, 1) }}%</strong></span>',
    '<span class="font-mono text-[10px] text-gray-500 dark:text-slate-400">CPU: <strong class="text-gray-700 dark:text-slate-200">{{ $device->latest_cpu }}%</strong> | Loss: <strong class="{{ $device->latest_loss > 0 ? \'text-red-500\' : \'text-emerald-600 dark:text-emerald-400\' }}">{{ number_format($device->latest_loss, 1) }}%</strong></span>'
)
content = content.replace(
    'class="border-b border-dashed border-gray-100 w-full mt-2"',
    'class="border-b border-dashed border-gray-100 dark:border-slate-800 w-full mt-2"'
)
content = content.replace(
    'class="mt-4 py-2.5 text-center text-xs font-bold text-hacienda-blue uppercase tracking-widest hover:bg-blue-50 rounded-xl transition block border border-dashed border-blue-200"',
    'class="mt-4 py-2.5 text-center text-xs font-bold text-hacienda-blue dark:text-blue-400 uppercase tracking-widest hover:bg-blue-50 dark:hover:bg-slate-800 rounded-xl transition block border border-dashed border-blue-200 dark:border-blue-900/60"'
)

with open('resources/views/dashboard.blade.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Dashboard template successfully updated with dark mode classes.")
