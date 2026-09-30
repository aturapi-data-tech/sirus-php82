<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Sirs\Rj\RL35Trait;

new class extends Component {
    use RL35Trait;

    public int $bulan;
    public int $tahun;

    // Isian teks yyyy (pola Casemix). $tahun hanya ikut berubah bila isian valid,
    // jadi perhitungan tetap memakai int dan tidak pernah menerima isian setengah jadi.
    public string $filterTahun = '';

    public function mount(): void
    {
        $now         = Carbon::now();
        $this->bulan = $now->month;
        $this->tahun = $now->year;
        $this->filterTahun = (string) $this->tahun;
    }

    #[Computed]
    public function rows(): array
    {
        return $this->computeRL35($this->bulan, $this->tahun);
    }

    public function bulanLabel(int $m): string
    {
        return [
            1 => 'Januari',  2 => 'Februari', 3 => 'Maret',     4 => 'April',
            5 => 'Mei',      6 => 'Juni',     7 => 'Juli',      8 => 'Agustus',
            9 => 'September',10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ][$m] ?? (string) $m;
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
        title="Laporan RL 3.5 — Kunjungan"
        subtitle='Rekap kunjungan rawat jalan + UGD per bulan, sesuai format SIRS Online Kemenkes.' />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Bulan" />
                        <x-select-input wire:model.live="bulan" class="w-full mt-1 sm:w-40">
                            @for ($m = 1; $m <= 12; $m++)<option value="{{ $m }}">{{ $this->bulanLabel($m) }}</option>@endfor
                        </x-select-input>
                    </div>
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
                        Periode: <span class="font-semibold text-body dark:text-gray-200">{{ $this->bulanLabel($bulan) }} {{ $tahun }}</span>
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
                        <span class="truncate">Panduan: sumber data &amp; cara hitung RL 3.5</span>
                    </span>
                    <svg class="w-4 h-4 ml-2 text-blue-600 transition-transform shrink-0" x-bind:class="buka && 'rotate-180'"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="buka" x-cloak class="px-4 pb-4 space-y-4 text-sm text-blue-900 dark:text-blue-100">
                    <div>
                        <div class="font-semibold">Tentang laporan</div>
                        <p class="mt-1">"Kunjungan" = setiap visit (bukan distinct pasien). Dalam Kota = pasien dengan kab_id Tulungagung (3504 BPS atau 1 legacy); selainnya Luar Kota.</p>
                    </div>

                    <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                        <div class="font-semibold">Sumber data &amp; cara hitung</div>
                        <div class="mt-1 leading-relaxed">
                            <strong>Source:</strong> RJ poliklinik (rstxn_rjhdrs) + UGD (rstxn_ugdhdrs untuk kategori 24).
                    <strong>Mapping:</strong> 25 poli aktual RSI Madinah ke 34 jenis kegiatan SIRS via
                    <code>POLI_TO_SIRS_RL35</code>. Poli yang tidak ke-map → row 100 "Tidak Ada Data".
                    <strong>Dalam Kota:</strong> <code>kab_id IN ('3504','1')</code> &mdash; '3504' BPS resmi,
                    '1' legacy _TULUNGAGUNG (data lama). <strong>Luar:</strong> selainnya.
                    <strong>Sub-kategori:</strong> Anak Neonatal (id 3), Ibu Hamil (id 5), Stroke (id 30/32) butuh
                    umur/ICD diagnosis matching &mdash; semua admisi default ke "Lainnya" sub-category.
                    <strong>Kategori 9, 10, 11, 13, 15, 20-22, 28-29, 30-32:</strong> tidak ada poli yang map ke sini di RSI Madinah → 0.
                    Filter: <code>klaim_id &lt;&gt; 'KR'</code>, status &lt;&gt; <code>A</code>/<code>F</code>.
                        </div>
                    </div>
                </div>
            </div>

            {{-- MAIN TABLE --}}
            <div class="flex flex-col flex-1 min-h-0 mt-4 border shadow-sm bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                        RL 3.5 &mdash; Tabel Rekap Kunjungan
                        <span class="ml-2 font-normal text-xs text-muted">
                            ({{ count($this->rows) }} jenis kegiatan, {{ $this->bulanLabel($bulan) }} {{ $tahun }})
                        </span>
                    </h3>
                </div>

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-b-2xl">
                    <table class="min-w-full text-xs border-collapse">
                        <thead class="sticky top-0 z-30 bg-surface-card dark:bg-gray-800 text-body dark:text-gray-200">
                            <tr class="text-[10px] font-semibold tracking-wider uppercase">
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center sticky left-0 bg-surface-soft dark:bg-gray-800 z-20">No.</th>
                                <th rowspan="2" class="px-3 py-2 border border-hairline dark:border-gray-700 text-left sticky left-12 bg-surface-soft dark:bg-gray-800 z-20 min-w-[16rem]">Jenis Kegiatan</th>
                                <th colspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-emerald-700 dark:text-emerald-300">Kunjungan Pasien Dalam Kota</th>
                                <th colspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-amber-700 dark:text-amber-300">Kunjungan Pasien Luar Kota</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Total Kunjungan</th>
                            </tr>
                            <tr class="text-[10px] font-semibold tracking-wider uppercase">
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-emerald-700 dark:text-emerald-300">Laki-Laki</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-emerald-700 dark:text-emerald-300">Perempuan</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-amber-700 dark:text-amber-300">Laki-Laki</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-amber-700 dark:text-amber-300">Perempuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->rows as $r)
                                @php
                                    $isSpecial = in_array($r['id'], [66, 77, 99, 100], true);
                                    $isTotal   = $r['id'] === 99;
                                    $rowCls = match (true) {
                                        $isTotal      => 'bg-gray-200 dark:bg-gray-700/60 font-bold',
                                        $r['id']===66 => 'bg-blue-50/40 dark:bg-blue-900/10',
                                        $r['id']===77 => 'bg-blue-50/40 dark:bg-blue-900/10 italic',
                                        $r['id']===100=> 'bg-amber-50/40 dark:bg-amber-900/10',
                                        default       => 'hover:bg-surface-soft dark:hover:bg-gray-800/50',
                                    };
                                @endphp
                                <tr class="{{ $rowCls }} border-b border-hairline-soft dark:border-gray-800">
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-center font-mono text-muted sticky left-0 z-10 {{ $isSpecial ? 'bg-inherit' : 'bg-canvas dark:bg-gray-900' }}">{{ $r['no'] }}</td>
                                    <td class="px-3 py-1.5 border-r border-hairline dark:border-gray-700 text-ink dark:text-gray-100 sticky left-12 z-10 {{ $isSpecial ? 'bg-inherit' : 'bg-canvas dark:bg-gray-900' }}">{{ $r['nama'] }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format($r['dalam_l']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format($r['dalam_p']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-amber-700 dark:text-amber-300">{{ number_format($r['luar_l']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-amber-700 dark:text-amber-300">{{ number_format($r['luar_p']) }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums font-semibold">{{ number_format($r['total']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
