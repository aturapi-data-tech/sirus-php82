{{--
    Pipeline visual — deretan kotak tahap dengan panah, dipakai di tiap seksi
    tutorial alur-pelayanan supaya alurnya kebaca sekilas (bukan teks doang).

    Pemakaian:
    @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
        ['chip' => 'taskId 6', 'judul' => 'Resep masuk', 'sub' => 'antrian apotek'],
        ['chip' => null,       'judul' => 'Telaah resep', 'sub' => 'TTD apoteker'],
    ]])

    Key opsional per step:
      chip      — pill kecil di atas judul (mis. status / taskId); null = tanpa pill
      chipWarna — 'primary' (default) | 'amber' | 'sky' | 'green' | 'red'
--}}
@php
    $warnaChip = [
        'primary' => 'background:color-mix(in srgb, var(--primary) 12%, transparent); color:var(--primary)',
        'amber' => 'background:#fef3c7; color:#92400e',
        'sky' => 'background:#e0f2fe; color:#075985',
        'green' => 'background:#dcfce7; color:#166534',
        'red' => 'background:#fee2e2; color:#991b1b',
    ];
@endphp
<div class="flex flex-wrap items-stretch gap-2">
    @foreach ($steps as $step)
        <div class="flex-1 min-w-[140px] rounded-xl p-3" style="background:var(--surface-card)">
            @if (!empty($step['chip']))
                <span class="inline-block px-2 py-0.5 mb-1.5 text-xs font-bold rounded-full"
                    style="{{ $warnaChip[$step['chipWarna'] ?? 'primary'] ?? $warnaChip['primary'] }}">
                    {{ $step['chip'] }}
                </span>
            @endif
            <div class="text-sm font-semibold leading-snug" style="color:var(--ink)">{{ $step['judul'] }}</div>
            @if (!empty($step['sub']))
                <div class="text-xs mt-0.5 leading-snug" style="color:var(--muted)">{{ $step['sub'] }}</div>
            @endif
        </div>
        @if (!$loop->last)
            <div class="self-center text-lg font-bold shrink-0" style="color:var(--muted-soft)">→</div>
        @endif
    @endforeach
</div>
