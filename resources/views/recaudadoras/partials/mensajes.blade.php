@if(session('success'))
    <div class="floating-card px-4 py-3 text-sm font-semibold flex items-start gap-2" style="color: #059669; border-color: #05966955">
        <svg class="w-4 h-4 mt-0.5 shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
        <span>{{ session('success') }}</span>
    </div>
@endif
@if(session('error'))
    <div class="floating-card px-4 py-3 text-sm font-semibold" style="color: #dc2626; border-color: #dc262655">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="floating-card px-4 py-3 text-sm" style="color: #dc2626; border-color: #dc262655">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
@endif
@if(session('avisosImportacion'))
    <details class="floating-card px-4 py-3 text-xs" style="color: var(--nm-text)" open>
        <summary class="font-bold cursor-pointer" style="color: #d97706">{{ count(session('avisosImportacion')) }} {{ session('avisosTitulo', 'filas del Excel con datos que revisar') }}</summary>
        <ul class="mt-2 space-y-1 list-disc pl-5" style="color: var(--nm-text-muted)">
            @foreach(session('avisosImportacion') as $a)<li>{{ $a }}</li>@endforeach
        </ul>
    </details>
@endif
