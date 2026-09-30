<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Sirs\Ri\RL41Trait;

new class extends Component {
    use RL41Trait;

    public int $tahun;

    // Isian teks yyyy (pola Casemix). $tahun hanya ikut berubah bila isian valid,
    // jadi perhitungan tetap memakai int dan tidak pernah menerima isian setengah jadi.
    public string $filterTahun = '';

    public function mount(): void
    {
        $this->tahun = Carbon::now()->year;
        $this->filterTahun = (string) $this->tahun;
    }

    public function updatedFilterTahun(): void
    {
        if ($this->tahunSalah() === false) {
            $this->tahun = (int) trim($this->filterTahun);
        }
    }

    /** true = isian tahun bukan yyyy 2000–2099 (perhitungan tetap memakai $tahun terakhir yang valid). */
    public function tahunSalah(): bool
    {
        $nilai = trim($this->filterTahun);

        return !(preg_match('/^\d{4}$/', $nilai) && (int) $nilai >= 2000 && (int) $nilai <= 2099);
    }

    /** Dipanggil tombol Reset <x-toolbar-refresh-reset>: periode kembali ke awal. */
    public function resetFilters(): void
    {
        $this->mount();
    }

    #[Computed]
    public function rows(): array
    {
        return $this->computeRL41($this->tahun);
    }

    #[Computed]
    public function totals(): array
    {
        $rows = $this->rows;
        $totalCells = array_fill(0, 25, ['L' => 0, 'P' => 0]);
        $totL = 0; $totP = 0; $matiL = 0; $matiP = 0;
        foreach ($rows as $r) {
            foreach ($r['cells'] as $i => $cell) {
                $totalCells[$i]['L'] += $cell['L'];
                $totalCells[$i]['P'] += $cell['P'];
            }
            $totL += $r['total_l'];
            $totP += $r['total_p'];
            $matiL += $r['mati_l'];
            $matiP += $r['mati_p'];
        }
        return [
            'cells' => $totalCells,
            'total_l' => $totL,
            'total_p' => $totP,
            'total' => $totL + $totP,
            'mati_l' => $matiL,
            'mati_p' => $matiP,
            'mati_total' => $matiL + $matiP,
        ];
    }
};
?>

<div>
    <x-page-title title="Laporan RL 4.1 — Morbiditas Pasien Rawat Inap"
        subtitle="Rekap morbiditas RI per ICD-10 × kelompok umur × gender × hidup/mati, format SIRS Online Kemenkes." />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 border-b bg-surface-soft border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Tahun" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <x-text-input type="text" wire:model.live.debounce.500ms="filterTahun" :error="$this->tahunSalah()"
                                class="block w-full pl-10 sm:w-32" placeholder="yyyy" maxlength="4" />
                        </div>
                    </div>

                    <div class="pb-2 text-xs leading-snug text-muted dark:text-gray-400">
                        Periode: <span class="font-semibold text-body dark:text-gray-200">Januari &ndash; Desember {{ $tahun }}</span>
                        @if ($this->tahunSalah())
                            <span class="ml-2 text-error">Format yyyy — memakai tahun {{ $tahun }}</span>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 ml-auto">
                        {{-- Tombol baku toolbar list: Refresh + Reset. --}}
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>

            {{-- PANDUAN — gaya biru-info standar, default TERTUTUP (pola Gaji Dokter / Penggajian). --}}
            <div x-data="{ buka: false }"
                class="mt-4 overflow-hidden border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
                <button type="button" x-on:click="buka = !buka"
                    class="flex items-center justify-between w-full px-4 py-2.5 text-sm font-semibold text-blue-900 transition-colors hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30">
                    <span class="flex items-center min-w-0 gap-2">
                        <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="truncate">Panduan: sumber data &amp; cara hitung RL 4.1</span>
                    </span>
                    <svg class="w-4 h-4 ml-2 text-blue-600 transition-transform shrink-0" x-bind:class="buka && 'rotate-180'"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="buka" x-cloak class="px-4 pb-4 space-y-4 text-sm text-blue-900 dark:text-blue-100">
                    <div>
                        <div class="font-semibold">Sumber data</div>
                        <ul class="mt-1 ml-4 space-y-1 list-disc">
                            <li>Pasien Rawat Inap yang <span class="font-semibold">pulang</span> di tahun terpilih
                                (<span class="font-mono">exit_date</span>), bukan batal, bukan Pasien Kronis.</li>
                            <li>Diagnosis utama dari EMR (<span class="font-mono">diagnosis[0].icdX</span>). Pasien tanpa
                                diagnosis masuk baris <span class="font-semibold">"0 Tidak Ada Data"</span> (disorot kuning).</li>
                            <li>1 admisi = 1 baris data. Diagnosis sekunder tidak dihitung &mdash; sesuai konvensi SIRS
                                (hitung per pasien, bukan per diagnosis).</li>
                        </ul>
                    </div>

                    <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                        <div class="font-semibold">Cara hitung</div>
                        <ul class="mt-1 ml-4 space-y-1 list-disc">
                            <li><span class="font-semibold">25 kelompok umur</span> (&lt;1 jam s.d. &ge;85 tahun) &times; 2 gender
                                = 50 kolom, ditambah total per gender dan jumlah meninggal.</li>
                            <li>Umur &lt;28 hari memakai lama rawat (pulang &minus; masuk) supaya jam/hari akurat; umur
                                &ge;28 hari memakai tanggal lahir.</li>
                            <li><span class="font-semibold">Meninggal</span> = tindak lanjut pulang bertanda SNOMED 419099009.</li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- MAIN TABLE --}}
            @php $tot = $this->totals; @endphp
            <div
                class="flex flex-col flex-1 min-h-0 mt-4 border shadow-sm bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                        RL 4.1 &mdash; Morbiditas RI per ICD-10 × Umur × Gender
                        <span class="ml-2 font-normal text-xs text-muted">
                            ({{ count($this->rows) }} ICD, total {{ number_format($tot['total']) }} pasien, mati {{ number_format($tot['mati_total']) }})
                        </span>
                    </h3>
                </div>

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-b-2xl">
                    <table class="text-[10px] border-collapse">
                        <thead class="sticky top-0 z-30 bg-surface-card dark:bg-gray-800 text-body dark:text-gray-200">
                            <tr class="font-semibold tracking-wider uppercase">
                                <th rowspan="3" class="px-1 py-2 border border-hairline dark:border-gray-700 text-center sticky left-0 bg-surface-soft dark:bg-gray-800 z-20 w-10">No.</th>
                                <th rowspan="3" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center sticky left-10 bg-surface-soft dark:bg-gray-800 z-20 w-20">ICD-10</th>
                                <th rowspan="3" class="px-2 py-2 border border-hairline dark:border-gray-700 text-left sticky left-30 bg-surface-soft dark:bg-gray-800 z-20 min-w-[14rem]">Diagnosis</th>
                                <th colspan="50" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-blue-700 dark:text-blue-300">Jumlah Hidup &amp; Mati Menurut Kelompok Umur &amp; Gender</th>
                                <th colspan="3" rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-emerald-700 dark:text-emerald-300">Total Hidup &amp; Mati per Gender</th>
                                <th colspan="3" rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-rose-700 dark:text-rose-300">Total Pasien Keluar Mati</th>
                            </tr>
                            <tr>
                                @foreach ($this::AGE_GROUPS_RL41 as $ag)
                                    <th colspan="2" class="px-1 py-1 border border-hairline dark:border-gray-700 text-center text-blue-700 dark:text-blue-300 whitespace-nowrap" style="font-size:9px;">{{ $ag['label'] }}</th>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach ($this::AGE_GROUPS_RL41 as $ag)
                                    <th class="px-1 py-1 border border-hairline dark:border-gray-700 text-right text-blue-700 dark:text-blue-300" style="font-size:9px;">L</th>
                                    <th class="px-1 py-1 border border-hairline dark:border-gray-700 text-right text-pink-700 dark:text-pink-300" style="font-size:9px;">P</th>
                                @endforeach
                                <th class="px-1 py-1 border border-hairline dark:border-gray-700 text-right text-emerald-700 dark:text-emerald-300" style="font-size:9px;">L</th>
                                <th class="px-1 py-1 border border-hairline dark:border-gray-700 text-right text-emerald-700 dark:text-emerald-300" style="font-size:9px;">P</th>
                                <th class="px-1 py-1 border border-hairline dark:border-gray-700 text-right text-emerald-700 dark:text-emerald-300" style="font-size:9px;">Total</th>
                                <th class="px-1 py-1 border border-hairline dark:border-gray-700 text-right text-rose-700 dark:text-rose-300" style="font-size:9px;">L</th>
                                <th class="px-1 py-1 border border-hairline dark:border-gray-700 text-right text-rose-700 dark:text-rose-300" style="font-size:9px;">P</th>
                                <th class="px-1 py-1 border border-hairline dark:border-gray-700 text-right text-rose-700 dark:text-rose-300" style="font-size:9px;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $i => $r)
                                @php $isCatchAll = $r['icd'] === '0'; @endphp
                                <tr class="{{ $isCatchAll ? 'bg-amber-50/60 dark:bg-amber-900/15 font-semibold' : 'hover:bg-surface-soft dark:hover:bg-gray-800/50' }} border-b border-hairline-soft dark:border-gray-800">
                                    <td class="px-1 py-1 border-r border-hairline dark:border-gray-700 text-center font-mono text-muted sticky left-0 z-10 {{ $isCatchAll ? 'bg-amber-50/60 dark:bg-amber-900/15' : 'bg-canvas dark:bg-gray-900' }}">{{ $i + 1 }}</td>
                                    <td class="px-2 py-1 border-r border-hairline dark:border-gray-700 font-mono text-xs text-body dark:text-gray-200 sticky left-10 z-10 {{ $isCatchAll ? 'bg-amber-50/60 dark:bg-amber-900/15' : 'bg-canvas dark:bg-gray-900' }}">{{ $r['icd'] }}</td>
                                    <td class="px-2 py-1 border-r border-hairline dark:border-gray-700 text-ink dark:text-gray-100 sticky left-30 z-10 {{ $isCatchAll ? 'bg-amber-50/60 dark:bg-amber-900/15' : 'bg-canvas dark:bg-gray-900' }}" title="{{ $r['icd_desc'] }}">{{ \Illuminate\Support\Str::limit($r['icd_desc'], 60) }}</td>
                                    @for ($g = 0; $g < 25; $g++)
                                        <td class="px-1 py-1 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ $r['cells'][$g]['L'] ?: '' }}</td>
                                        <td class="px-1 py-1 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-pink-700 dark:text-pink-300">{{ $r['cells'][$g]['P'] ?: '' }}</td>
                                    @endfor
                                    <td class="px-1 py-1 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ $r['total_l'] }}</td>
                                    <td class="px-1 py-1 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ $r['total_p'] }}</td>
                                    <td class="px-1 py-1 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-700 dark:text-emerald-300 font-semibold">{{ $r['total'] }}</td>
                                    <td class="px-1 py-1 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ $r['mati_l'] ?: '' }}</td>
                                    <td class="px-1 py-1 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ $r['mati_p'] ?: '' }}</td>
                                    <td class="px-1 py-1 text-right tabular-nums text-rose-700 dark:text-rose-300 font-semibold">{{ $r['mati_total'] ?: '' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="59" class="px-6 py-10 text-center text-muted dark:text-gray-400 italic">Belum ada data RI exit di tahun {{ $tahun }}</td></tr>
                            @endforelse
                        </tbody>
                        @if (count($this->rows) > 0)
                            <tfoot class="bg-surface-soft dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600 font-bold sticky bottom-0 z-10">
                                <tr class="text-[10px] text-ink dark:text-gray-100">
                                    <td colspan="3" class="px-2 py-2 border border-hairline dark:border-gray-700 sticky left-0 bg-surface-soft dark:bg-gray-800 z-20">TOTAL</td>
                                    @for ($g = 0; $g < 25; $g++)
                                        <td class="px-1 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ $tot['cells'][$g]['L'] ?: '' }}</td>
                                        <td class="px-1 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-pink-800 dark:text-pink-200">{{ $tot['cells'][$g]['P'] ?: '' }}</td>
                                    @endfor
                                    <td class="px-1 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-800 dark:text-emerald-200">{{ $tot['total_l'] }}</td>
                                    <td class="px-1 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-800 dark:text-emerald-200">{{ $tot['total_p'] }}</td>
                                    <td class="px-1 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-800 dark:text-emerald-200">{{ $tot['total'] }}</td>
                                    <td class="px-1 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ $tot['mati_l'] }}</td>
                                    <td class="px-1 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ $tot['mati_p'] }}</td>
                                    <td class="px-1 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ $tot['mati_total'] }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>
