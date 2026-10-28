{{--
    Isi modal Kelengkapan EMR RJ / UGD / RI (info-kelengkapan-emr) — satu tampilan untuk ketiga jalur,
    tema EMR/Coding: latar body surface-soft, kartu putih rounded-2xl, header bagian = pita SOAP berhuruf
    (warna sama dengan grid EMR), panduan = panel biru-info lipat (standar repo, default tertutup).

    Props:
      pct       — hasil calculateEmrPercent*(): ['emr' => int, 'sections' => [kunci => int], 'koding' => int|null]
      checklist — hasil collectChecklist*(): [kunci => ['label','weight','items' => [['label','filled','opsional'?]]]]
      snomed    — baris Coding SATUSEHAT: [['label','value','code','displayId','terkode'?]]
      nama      — [kunci => nama bagian] urut tampil di kartu skor
    Slot `catatan` — catatan khusus jalur di panel Panduan Kriteria.
--}}
@props(['pct' => [], 'checklist' => [], 'snomed' => [], 'nama' => []])

@php
    // Warna pita per bagian — sama dengan header grid SOAP EMR (S biru, O hijau, A amber, P rose, N ungu).
    $gaya = [
        's' => ['pita' => 'bg-blue-100 dark:border-blue-700', 'huruf' => 'text-blue-700 bg-blue-100 dark:bg-blue-900/40 dark:text-blue-300'],
        'o' => ['pita' => 'bg-emerald-100 dark:border-emerald-700', 'huruf' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
        'a' => ['pita' => 'bg-amber-100 dark:border-amber-700', 'huruf' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'],
        'p' => ['pita' => 'bg-rose-100 dark:border-rose-700', 'huruf' => 'bg-rose-100 text-error dark:bg-rose-900/40 dark:text-rose-300'],
        'n' => ['pita' => 'bg-purple-100 dark:border-purple-700', 'huruf' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300'],
        't' => ['pita' => 'bg-teal-100 dark:border-teal-700', 'huruf' => 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300'],
        'c' => ['pita' => 'bg-indigo-100 dark:border-indigo-700', 'huruf' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'],
        'k' => ['pita' => 'bg-cyan-100 dark:border-cyan-700', 'huruf' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300'],
    ];
    $warnaSkor = fn(int $skor) => $skor >= 80
        ? ['teks' => 'text-success dark:text-success', 'bar' => 'bg-emerald-500 dark:bg-emerald-400']
        : ($skor >= 50
            ? ['teks' => 'text-amber-600 dark:text-amber-400', 'bar' => 'bg-amber-400 dark:bg-amber-400']
            : ['teks' => 'text-error dark:text-rose-400', 'bar' => 'bg-rose-400 dark:bg-rose-400']);
    $emr = (int) ($pct['emr'] ?? 0);
    $koding = $pct['koding'] ?? null;
@endphp

<div class="space-y-4">
    {{-- SKOR TOTAL + PER BAGIAN --}}
    <div class="p-4 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-wrap items-end justify-between gap-3 mb-3">
            <div>
                <p class="ds-form-title">Status Pasien Ini</p>
                <p class="text-xs text-muted dark:text-gray-400">Persentase kelengkapan EMR (kolom wajib saja).</p>
            </div>
            <span class="text-3xl font-bold {{ $warnaSkor($emr)['teks'] }}">{{ $emr }}%</span>
        </div>
        <div class="w-full h-1.5 mb-4 rounded-full bg-surface-soft dark:bg-gray-700">
            <div class="h-1.5 rounded-full {{ $warnaSkor($emr)['bar'] }}" style="width: {{ max(0, min(100, $emr)) }}%"></div>
        </div>
        {{-- Kelas ditulis utuh (bukan dirakit) supaya ikut terpindai Tailwind: RJ 5 · UGD 6 · RI 7 bagian. --}}
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 {{ [5 => 'xl:grid-cols-5', 6 => 'xl:grid-cols-6', 7 => 'lg:grid-cols-7'][count($nama)] ?? 'xl:grid-cols-5' }}">
            @foreach ($nama as $kunci => $namaBagian)
                @php $skor = (int) ($pct['sections'][$kunci] ?? 0); @endphp
                <div class="p-2 border rounded-xl border-hairline dark:border-gray-700">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-7 h-7 text-sm font-bold rounded-full shrink-0 {{ $gaya[$kunci]['huruf'] ?? '' }}">{{ strtoupper($kunci) }}</span>
                        <span class="text-xs leading-tight text-body dark:text-gray-300">{{ $namaBagian }}</span>
                    </div>
                    <div class="mt-2 text-sm font-semibold {{ $warnaSkor($skor)['teks'] }}">{{ $skor }}%</div>
                    <div class="w-full h-1.5 mt-1 rounded-full bg-surface-soft dark:bg-gray-700">
                        <div class="h-1.5 rounded-full {{ $warnaSkor($skor)['bar'] }}" style="width: {{ max(0, min(100, $skor)) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="flex flex-wrap items-center gap-4 mt-3 text-xs text-muted dark:text-gray-400">
            <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-1.5 rounded-full bg-rose-400"></span>&lt; 50% kurang</span>
            <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-1.5 rounded-full bg-amber-400"></span>50–79% sedang</span>
            <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-1.5 rounded-full bg-emerald-500"></span>≥ 80% lengkap</span>
        </div>
    </div>

    {{-- CODING SATUSEHAT — indikator terpisah, tidak masuk % --}}
    <div class="p-4 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <div>
                <p class="ds-form-title">Coding SATUSEHAT</p>
                <p class="text-xs text-muted dark:text-gray-400">Terpisah — tidak masuk % EMR; diisi di layar Coding SATUSEHAT.</p>
            </div>
            @if ($koding !== null)
                <x-badge :variant="$koding >= 100 ? 'success' : 'warning'">{{ $koding }}% ter-coding</x-badge>
            @endif
        </div>
        <ul class="divide-y divide-hairline-soft dark:divide-gray-700">
            @foreach ($snomed as $item)
                <li class="flex flex-wrap items-start justify-between gap-2 py-2 text-sm">
                    <span class="font-semibold text-body dark:text-gray-300">{{ $item['label'] }}</span>
                    <span class="text-right">
                        @if ($item['terkode'] ?? filled($item['code']))
                            <x-badge variant="success">✓ Coded</x-badge>
                            <span class="ml-1 font-mono text-xs text-muted dark:text-gray-400">{{ $item['code'] }}</span>
                            @if (filled($item['displayId']))
                                <span class="block mt-0.5 text-xs italic text-muted dark:text-gray-400">{{ $item['displayId'] }}</span>
                            @endif
                        @elseif (filled($item['value']))
                            <x-badge variant="warning">⚠ Teks bebas — belum di-coding</x-badge>
                        @else
                            <x-badge variant="gray">— Belum diisi</x-badge>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    </div>

    {{-- DAFTAR PERIKSA PER BAGIAN — pita SOAP gaya EMR --}}
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        @foreach ($checklist as $kunci => $bagian)
            @php
                $itemWajib = collect($bagian['items'])->reject(fn($item) => $item['opsional'] ?? false);
                $terisi = $itemWajib->where('filled', true)->count();
            @endphp
            <div>
                <div class="flex items-center gap-2 p-2 mb-2 border-2 rounded-t-lg {{ $gaya[$kunci]['pita'] ?? 'bg-surface-soft dark:border-gray-600' }}">
                    <span class="inline-flex items-center justify-center w-8 h-8 text-base font-bold rounded-full {{ $gaya[$kunci]['huruf'] ?? '' }}">{{ strtoupper($kunci) }}</span>
                    <span class="text-base font-semibold text-body dark:text-gray-300">{{ $bagian['label'] }}</span>
                    <span class="text-xs text-muted dark:text-gray-400">({{ $terisi }}/{{ $itemWajib->count() }})</span>
                    <span class="ml-auto text-xs font-medium text-muted dark:text-gray-400">Bobot {{ $bagian['weight'] }}%</span>
                </div>
                <ul class="overflow-hidden border divide-y shadow-sm bg-canvas border-hairline rounded-2xl divide-hairline-soft dark:bg-gray-900 dark:border-gray-700 dark:divide-gray-700">
                    @foreach ($bagian['items'] as $item)
                        {{-- Wajib (masuk hitungan) & belum diisi → baris merah supaya langsung kelihatan. --}}
                        @php $wajibKosong = !($item['opsional'] ?? false) && !$item['filled']; @endphp
                        <li @class([
                            'flex items-start gap-2 px-4 py-2 text-sm',
                            'text-body dark:text-gray-300' => !$wajibKosong,
                            'bg-rose-50 dark:bg-rose-900/20' => $wajibKosong,
                        ])>
                            @if ($item['opsional'] ?? false)
                                <span class="{{ $item['filled'] ? 'text-success dark:text-success' : 'text-muted dark:text-gray-400' }}">{{ $item['filled'] ? '✓' : '○' }}</span>
                                <div class="{{ $item['filled'] ? '' : 'text-muted dark:text-gray-400' }}">{{ $item['label'] }}
                                    <x-badge variant="gray" class="ml-1">opsional — tidak dihitung</x-badge></div>
                            @elseif ($item['filled'])
                                <span class="text-success dark:text-success">✓</span>
                                <div>{{ $item['label'] }}</div>
                            @else
                                <span class="font-bold text-error dark:text-rose-400">✗</span>
                                <div class="font-medium text-error dark:text-rose-300">{{ $item['label'] }}
                                    <x-badge variant="danger" class="ml-1">wajib — belum diisi</x-badge></div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>

    {{-- PANDUAN KRITERIA — panel biru-info standar, lipat, default tertutup --}}
    <div x-data="{ open: false }" class="overflow-hidden border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
        <button type="button" x-on:click="open = !open"
            class="flex items-center justify-between w-full gap-2 px-4 py-3 text-left transition-colors hover:bg-blue-100 dark:hover:bg-blue-900/30">
            <span class="flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm font-semibold text-blue-900 dark:text-blue-200">Panduan Kriteria</span>
            </span>
            <svg class="w-4 h-4 text-blue-600 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        <div x-show="open" x-collapse class="px-4 pb-4 space-y-2 text-sm text-body dark:text-gray-300">
            <p><span class="font-semibold">Kolom "screening" (alergi, riwayat penyakit dahulu):</span> boleh diisi <em>"Tidak ada"</em> bila negatif — yang penting <strong>jangan dibiarkan kosong</strong>.</p>
            {{ $catatan ?? '' }}
            <p class="text-xs text-muted dark:text-gray-400">Standar: <strong>Permenkes 24/2022</strong> tentang RME &amp; <strong>SNARS Ed. 1.1</strong>.</p>
        </div>
    </div>
</div>
