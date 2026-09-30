<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Sirs\Ugd\RL33Trait;

new class extends Component {
    use RL33Trait;

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
        return $this->computeRL33($this->bulan, $this->tahun);
    }

    #[Computed]
    public function totalRow(): array
    {
        $rows = $this->rows;
        $sum  = fn(string $k) => array_sum(array_column($rows, $k));
        return [
            'rujukan'         => $sum('rujukan'),
            'non_rujukan'     => $sum('non_rujukan'),
            'dirawat'         => $sum('dirawat'),
            'dirujuk'         => $sum('dirujuk'),
            'pulang'          => $sum('pulang'),
            'mati_igd_l'      => $sum('mati_igd_l'),
            'mati_igd_p'      => $sum('mati_igd_p'),
            'doa_l'           => $sum('doa_l'),
            'doa_p'           => $sum('doa_p'),
            'luka_l'          => $sum('luka_l'),
            'luka_p'          => $sum('luka_p'),
            'false_emergency' => $sum('false_emergency'),
        ];
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
        title="Laporan RL 3.3 — Rawat Darurat"
        subtitle="Rekap UGD/IGD per jenis pelayanan per bulan, sesuai format SIRS Online Kemenkes." />

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
                        <span class="truncate">Panduan: sumber data &amp; cara hitung RL 3.3</span>
                    </span>
                    <svg class="w-4 h-4 ml-2 text-blue-600 transition-transform shrink-0" x-bind:class="buka && 'rotate-180'"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="buka" x-cloak class="px-4 pb-4 space-y-4 text-sm text-blue-900 dark:text-blue-100">
                    <div>
                        <div class="font-semibold">Tentang laporan</div>
                        <p class="mt-1">Mapping otomatis: poli DPJP &amp; umur pasien → 13 jenis pelayanan (Bayi/Anak/Geriatri/Kebidanan/Psikiatrik/Non bedah lainnya). Kategori Kecelakaan &amp; Kekerasan butuh ICD diagnosis matching, ditangguhkan.</p>
                    </div>

                    <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                        <div class="font-semibold">Sumber data &amp; cara hitung</div>
                        <div class="mt-1 leading-relaxed">
                            <strong>Mapping jenis pelayanan:</strong> prioritas Psikiatri (poli) &rarr; Kebidanan (POLI OBGIN/KIA)
                    &rarr; Bayi (umur &lt; 1) &rarr; Geriatri (umur &ge; 60) &rarr; Anak (umur 1-17) &rarr; Non bedah lainnya.
                    <strong>Sumber metrik:</strong>
                    <span class="text-muted-soft">Rujukan/Non = </span><code>rsmst_entryugds.rujukan_status</code>;
                    <span class="text-muted-soft">Dirawat = </span><code>rj_status='I'</code>;
                    <span class="text-muted-soft">Dirujuk = </span>JSON <code>perencanaan.tindakLanjut.tindakLanjut='Rujuk'</code>;
                    <span class="text-muted-soft">Mati IGD = </span>JSON
                    <code>perencanaan.tindakLanjut.tindakLanjut='Meninggal'</code> &times; <code>p.sex</code>
                    (semua pasien yang meninggal di IGD, termasuk yang datang hidup lalu gagal diresusitasi);
                    <span class="text-muted-soft">Meninggal Saat Tiba / DOA (Death On Arrival) = </span>bagian dari Mati
                    IGD yang triasenya P0 &mdash; pasien sudah meninggal ketika sampai di IGD;
                    <span class="text-muted-soft">False Emergency = </span>triase P4.
                    <strong>Belum diisi:</strong> kategori 1.1-2.3 (butuh ICD diagnosis matching), kolom Luka-luka (butuh trauma flag).
                    Filter: <code>klaim_id &lt;&gt; 'KR'</code> &amp; <code>rj_status &lt;&gt; 'F'</code>.
                        </div>
                    </div>
                </div>
            </div>

            {{-- MAIN TABLE --}}
            @php $tot = $this->totalRow; @endphp
            <div class="flex flex-col flex-1 min-h-0 mt-4 border shadow-sm bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                        RL 3.3 &mdash; Tabel Rekap Rawat Darurat
                        <span class="ml-2 font-normal text-xs text-muted">
                            ({{ count($this->rows) }} jenis pelayanan, {{ $this->bulanLabel($bulan) }} {{ $tahun }})
                        </span>
                    </h3>
                </div>

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-b-2xl">
                    <table class="min-w-full text-xs border-collapse">
                        <thead class="sticky top-0 z-30 bg-surface-card dark:bg-gray-800 text-body dark:text-gray-200">
                            <tr class="text-[10px] font-semibold tracking-wider uppercase">
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center sticky left-0 bg-surface-soft dark:bg-gray-800 z-20">No.</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center sticky left-12 bg-surface-soft dark:bg-gray-800 z-20">No Pelayanan</th>
                                <th rowspan="2" class="px-3 py-2 border border-hairline dark:border-gray-700 text-left sticky left-28 bg-surface-soft dark:bg-gray-800 z-20 min-w-[16rem]">Jenis Pelayanan</th>
                                <th colspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center">Total Pasien</th>
                                <th colspan="3" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-blue-700 dark:text-blue-300">Tindak Lanjut Pelayanan</th>
                                <th colspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-rose-700 dark:text-rose-300">Mati di IGD</th>
                                <th colspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-rose-700 dark:text-rose-300">Meninggal Saat Tiba (DOA)</th>
                                <th colspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-amber-700 dark:text-amber-300">Luka-luka</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">False Emergency</th>
                            </tr>
                            <tr class="text-[10px] font-semibold tracking-wider uppercase">
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Rujukan</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Non Rujukan</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-blue-700 dark:text-blue-300">Dirawat</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-blue-700 dark:text-blue-300">Dirujuk</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-blue-700 dark:text-blue-300">Pulang</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-rose-700 dark:text-rose-300">L</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-rose-700 dark:text-rose-300">P</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-rose-700 dark:text-rose-300">L</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-rose-700 dark:text-rose-300">P</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-amber-700 dark:text-amber-300">L</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-amber-700 dark:text-amber-300">P</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->rows as $r)
                                <tr class="hover:bg-surface-soft dark:hover:bg-gray-800/50 border-b border-hairline-soft dark:border-gray-800">
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-center font-mono text-muted sticky left-0 z-10 bg-canvas dark:bg-gray-900">{{ $r['id'] }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-center font-mono text-muted sticky left-12 z-10 bg-canvas dark:bg-gray-900">{{ $r['no'] }}</td>
                                    <td class="px-3 py-1.5 border-r border-hairline dark:border-gray-700 text-ink dark:text-gray-100 sticky left-28 z-10 bg-canvas dark:bg-gray-900">{{ $r['nama'] }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($r['rujukan']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($r['non_rujukan']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ number_format($r['dirawat']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ number_format($r['dirujuk']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ number_format($r['pulang']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['mati_igd_l']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['mati_igd_p']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['doa_l']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['doa_p']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-amber-700 dark:text-amber-300 text-muted-soft">{{ number_format($r['luka_l']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-amber-700 dark:text-amber-300 text-muted-soft">{{ number_format($r['luka_p']) }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['false_emergency']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-surface-soft dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600 font-bold">
                            <tr class="text-[11px] text-ink dark:text-gray-100">
                                <td colspan="3" class="px-3 py-2 border border-hairline dark:border-gray-700 sticky left-0 bg-surface-soft dark:bg-gray-800 z-10">TOTAL</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($tot['rujukan']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($tot['non_rujukan']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ number_format($tot['dirawat']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ number_format($tot['dirujuk']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ number_format($tot['pulang']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['mati_igd_l']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['mati_igd_p']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['doa_l']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['doa_p']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-muted">{{ number_format($tot['luka_l']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-muted">{{ number_format($tot['luka_p']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['false_emergency']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
