<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Sirs\Rj\RL53Trait;

new class extends Component {
    use RL53Trait;

    public int $tahun;

    // Isian teks yyyy (pola Casemix). $tahun hanya ikut berubah bila isian valid,
    // jadi perhitungan tetap memakai int dan tidak pernah menerima isian setengah jadi.
    public string $filterTahun = '';

    public function mount(): void
    {
        $this->tahun = Carbon::now()->year;
        $this->filterTahun = (string) $this->tahun;
    }

    #[Computed]
    public function rows(): array
    {
        return $this->computeRL53($this->tahun);
    }

    #[Computed]
    public function totals(): array
    {
        $rows = $this->rows;
        return [
            'kasus_l'     => array_sum(array_column($rows, 'kasus_l')),
            'kasus_p'     => array_sum(array_column($rows, 'kasus_p')),
            'kasus_total' => array_sum(array_column($rows, 'kasus_total')),
            'kunj_l'      => array_sum(array_column($rows, 'kunj_l')),
            'kunj_p'      => array_sum(array_column($rows, 'kunj_p')),
            'kunj_total'  => array_sum(array_column($rows, 'kunj_total')),
        ];
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

        return !(preg_match('/^\\d{4}$/', $nilai) && (int) $nilai >= 2000 && (int) $nilai <= 2099);
    }

    /** Dipanggil tombol Reset <x-toolbar-refresh-reset>: periode kembali ke awal. */
    public function resetFilters(): void
    {
        $this->mount();
    }
};
?>

<div>
    <x-page-title
        title="Laporan RL 5.3 — 10 Besar Kunjungan Penyakit Rawat Jalan"
        subtitle='Top 10 ICD-10 dengan jumlah kunjungan terbanyak di RJ poliklinik per tahun.' />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
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

                    {{-- Tombol baku toolbar list: Refresh + Reset. --}}
                    <div class="flex items-center gap-2 ml-auto">
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
                        <span class="truncate">Panduan: sumber data &amp; cara hitung RL 5.3</span>
                    </span>
                    <svg class="w-4 h-4 ml-2 text-blue-600 transition-transform shrink-0" x-bind:class="buka && 'rotate-180'"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="buka" x-cloak class="px-4 pb-4 space-y-4 text-sm text-blue-900 dark:text-blue-100">
                    <div>
                        <div class="font-semibold">Tentang laporan</div>
                        <p class="mt-1">Sorted desc by total kunjungan (L + P). "Kasus Baru" = pasien unik (DISTINCT reg_no), "Kunjungan" = total visits.</p>
                    </div>

                    <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                        <div class="font-semibold">Sumber data &amp; cara hitung</div>
                        <div class="mt-1 leading-relaxed">
                            <strong>Source:</strong> rstxn_rjhdrs (RJ saja, rj_date di tahun, rj_status NOT IN ('A','F')).
                    Diagnosis utama dari JSON <code>datadaftarpolirj_json.diagnosis[0].icdX</code>.
                    <strong>Sorting:</strong> desc by total kunjungan (L + P).
                    <strong>"Kasus Baru":</strong> DISTINCT reg_no per (icd, gender). 1 pasien dgn multiple visit ICD-sama → 1 kasus baru.
                    <strong>"Kunjungan":</strong> COUNT semua visits.
                    Pasien tanpa diagnosis utama dikecualikan dari ranking.
                    Filter: <code>klaim_id ≠ 'KR'</code>.
                    <strong>Catatan:</strong> Versi simpler dari RL 5.1 — tanpa breakdown 25 kelompok umur, hanya total per gender.
                        </div>
                    </div>
                </div>
            </div>

            {{-- MAIN TABLE --}}
            @php $tot = $this->totals; @endphp
            <div class="flex flex-col flex-1 min-h-0 mt-4 border shadow-sm bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                        RL 5.3 &mdash; Top 10 ICD-10 Kunjungan di Rawat Jalan
                        <span class="ml-2 font-normal text-xs text-muted">
                            ({{ count($this->rows) }} ICD top, {{ number_format($tot['kasus_total']) }} kasus baru, {{ number_format($tot['kunj_total']) }} kunjungan)
                        </span>
                    </h3>
                </div>

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-b-2xl">
                    <table class="min-w-full text-sm border-collapse">
                        <thead class="sticky top-0 z-30 bg-surface-card dark:bg-gray-800 text-body dark:text-gray-200">
                            <tr class="text-xs font-semibold tracking-wider uppercase">
                                <th rowspan="2" class="px-2 py-3 text-center w-12 border border-hairline dark:border-gray-700">No.</th>
                                <th rowspan="2" class="px-2 py-3 text-center w-24 border border-hairline dark:border-gray-700">Kelompok ICD-10</th>
                                <th rowspan="2" class="px-3 py-3 text-left border border-hairline dark:border-gray-700">Kelompok Diagnosa Penyakit</th>
                                <th colspan="3" class="px-2 py-3 text-center border border-hairline dark:border-gray-700 text-emerald-700 dark:text-emerald-300">Jumlah Kasus Baru</th>
                                <th colspan="3" class="px-2 py-3 text-center border border-hairline dark:border-gray-700 text-purple-700 dark:text-purple-300">Jumlah Kunjungan (sort key)</th>
                            </tr>
                            <tr class="text-xs font-semibold tracking-wider uppercase">
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-blue-700 dark:text-blue-300">L</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-pink-700 dark:text-pink-300">P</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-emerald-700 dark:text-emerald-300">Total</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-blue-700 dark:text-blue-300">L</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-pink-700 dark:text-pink-300">P</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $i => $r)
                                <tr class="hover:bg-surface-soft dark:hover:bg-gray-800/50 border-b border-hairline-soft dark:border-gray-800">
                                    <td class="px-2 py-2 border-r border-hairline dark:border-gray-700 text-center font-mono font-bold text-muted">{{ $i + 1 }}</td>
                                    <td class="px-2 py-2 border-r border-hairline dark:border-gray-700 text-center font-mono text-body dark:text-gray-200">{{ $r['icd'] }}</td>
                                    <td class="px-3 py-2 border-r border-hairline dark:border-gray-700 text-ink dark:text-gray-100" title="{{ $r['icd_desc'] }}">{{ $r['icd_desc'] }}</td>
                                    <td class="px-2 py-2 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ number_format($r['kasus_l']) }}</td>
                                    <td class="px-2 py-2 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-pink-700 dark:text-pink-300">{{ number_format($r['kasus_p']) }}</td>
                                    <td class="px-2 py-2 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-700 dark:text-emerald-300 font-semibold">{{ number_format($r['kasus_total']) }}</td>
                                    <td class="px-2 py-2 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ number_format($r['kunj_l']) }}</td>
                                    <td class="px-2 py-2 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-pink-700 dark:text-pink-300">{{ number_format($r['kunj_p']) }}</td>
                                    <td class="px-2 py-2 text-right tabular-nums text-purple-700 dark:text-purple-300 font-bold text-base">{{ number_format($r['kunj_total']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="px-6 py-10 text-center text-muted dark:text-gray-400 italic">Belum ada data RJ di tahun {{ $tahun }}</td></tr>
                            @endforelse
                        </tbody>
                        @if (count($this->rows) > 0)
                            <tfoot class="bg-surface-soft dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600 font-bold">
                                <tr class="text-sm text-ink dark:text-gray-100">
                                    <td colspan="3" class="px-3 py-3 border border-hairline dark:border-gray-700">TOTAL (top {{ count($this->rows) }})</td>
                                    <td class="px-2 py-3 border border-hairline dark:border-gray-700 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ number_format($tot['kasus_l']) }}</td>
                                    <td class="px-2 py-3 border border-hairline dark:border-gray-700 text-right tabular-nums text-pink-800 dark:text-pink-200">{{ number_format($tot['kasus_p']) }}</td>
                                    <td class="px-2 py-3 border border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-800 dark:text-emerald-200">{{ number_format($tot['kasus_total']) }}</td>
                                    <td class="px-2 py-3 border border-hairline dark:border-gray-700 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ number_format($tot['kunj_l']) }}</td>
                                    <td class="px-2 py-3 border border-hairline dark:border-gray-700 text-right tabular-nums text-pink-800 dark:text-pink-200">{{ number_format($tot['kunj_p']) }}</td>
                                    <td class="px-2 py-3 border border-hairline dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['kunj_total']) }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
