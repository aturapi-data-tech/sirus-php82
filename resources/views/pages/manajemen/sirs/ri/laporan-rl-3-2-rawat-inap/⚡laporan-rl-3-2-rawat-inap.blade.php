<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use App\Http\Traits\Manajemen\Sirs\Ri\RL32Trait;

new class extends Component {
    use RL32Trait;

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

    public function setBulan(int $b): void
    {
        if ($b >= 1 && $b <= 12) {
            $this->bulan = $b;
        }
    }

    #[Computed]
    public function rows(): array
    {
        return $this->computeRL32($this->bulan, $this->tahun);
    }

    #[Computed]
    public function totalRow(): array
    {
        $rows = $this->rows;
        $sum  = fn(string $k) => array_sum(array_column($rows, $k));

        return [
            'pasien_awal_bulan'     => $sum('pasien_awal_bulan'),
            'pasien_masuk'          => $sum('pasien_masuk'),
            'pasien_pindahan'       => $sum('pasien_pindahan'),
            'pasien_dipindahkan'    => $sum('pasien_dipindahkan'),
            'pasien_keluar_hidup'   => $sum('pasien_keluar_hidup'),
            'pria_mati_lt48'        => $sum('pria_mati_lt48'),
            'pria_mati_ge48'        => $sum('pria_mati_ge48'),
            'wanita_mati_lt48'      => $sum('wanita_mati_lt48'),
            'wanita_mati_ge48'      => $sum('wanita_mati_ge48'),
            'jumlah_lama_dirawat'   => $sum('jumlah_lama_dirawat'),
            'pasien_akhir_bulan'    => $sum('pasien_akhir_bulan'),
            'jumlah_hari_perawatan' => $sum('jumlah_hari_perawatan'),
            'kelas_vvip'            => $sum('kelas_vvip'),
            'kelas_vip'             => $sum('kelas_vip'),
            'kelas_1'               => $sum('kelas_1'),
            'kelas_2'               => $sum('kelas_2'),
            'kelas_3'               => $sum('kelas_3'),
            'kelas_khusus'          => $sum('kelas_khusus'),
            'alokasi_tt_awal'       => $sum('alokasi_tt_awal'),
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
        title="Laporan RL 3.2 — Rawat Inap"
        subtitle='Rekapitulasi rawat inap per jenis pelayanan per bulan, sesuai format SIRS Online Kemenkes.' />

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
                        <span class="truncate">Panduan: sumber data &amp; cara hitung RL 3.2</span>
                    </span>
                    <svg class="w-4 h-4 ml-2 text-blue-600 transition-transform shrink-0" x-bind:class="buka && 'rotate-180'"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="buka" x-cloak class="px-4 pb-4 space-y-4 text-sm text-blue-900 dark:text-blue-100">
                    <div>
                        <div class="font-semibold">Tentang laporan</div>
                        <p class="mt-1">Mapping otomatis: DPJP Utama (JSON levelingDokter) → poli → specialty (keyword match poli_desc). Poli yang tidak match jatuh ke baris "Tidak Ada Data".</p>
                    </div>

                    <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                        <div class="font-semibold">Sumber data &amp; cara hitung</div>
                        <div class="mt-1 leading-relaxed">
                            <strong>Mapping specialty:</strong> DPJP Utama dari JSON
                    <code>pengkajianAwalPasienRawatInap.levelingDokter</code> (entry dengan <code>levelDokter='Utama'</code>)
                    &rarr; <code>rsmst_doctors.poli_id</code> &rarr; eksplisit map 25 poli RSI Madinah ke 36 jenis pelayanan SIRS
                    (lihat <code>RL32Trait::POLI_TO_SIRS</code>); fallback keyword match <code>poli_desc</code> untuk poli baru.
                    Fallback drId ke <code>rstxn_rihdrs.dr_id</code> kalau leveling JSON kosong.
                    Admisi tanpa DPJP atau poli yang tidak match &rarr; baris <span class="font-semibold">"Tidak Ada Data"</span>.
                    Kolom <span class="text-muted-soft">Pasien Pindahan / Dipindahkan</span> = 0 (butuh tracking transfer antar specialty, ditangguhkan).
                    <span class="text-muted-soft">Alokasi TT total RS dipin di baris "Tidak Ada Data" (mapping bangsal&rarr;specialty butuh DDL).</span>
                    Filter: <code>klaim_id &lt;&gt; 'KR'</code> &amp; <code>ri_status &lt;&gt; 'F'</code>. Meninggal: SNOMED 419099009.
                        </div>
                    </div>
                </div>
            </div>

            {{-- MAIN TABLE --}}
            @php $tot = $this->totalRow; @endphp
            <div class="flex flex-col flex-1 min-h-0 mt-4 border shadow-sm bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                        RL 3.2 &mdash; Tabel Rekapitulasi Rawat Inap
                        <span class="ml-2 font-normal text-xs text-muted">
                            ({{ count($this->rows) }} jenis pelayanan, {{ $this->bulanLabel($bulan) }} {{ $tahun }})
                        </span>
                    </h3>
                </div>

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-b-2xl">
                    <table class="min-w-full text-xs border-collapse">
                        <thead class="sticky top-0 z-30 bg-surface-card dark:bg-gray-800 text-body dark:text-gray-200">
                            {{-- Header row 1: groups --}}
                            <tr class="text-[10px] font-semibold tracking-wider uppercase">
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center sticky left-0 bg-surface-soft dark:bg-gray-800 z-20">No.</th>
                                <th rowspan="2" class="px-3 py-2 border border-hairline dark:border-gray-700 text-left sticky left-12 bg-surface-soft dark:bg-gray-800 z-20 min-w-[14rem]">Jenis Pelayanan</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Pasien Awal Bulan</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Pasien Masuk</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Pasien Pindahan</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Pasien Dipindahkan</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Pasien Keluar Hidup</th>
                                <th colspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-rose-700 dark:text-rose-300">Pria Keluar Mati</th>
                                <th colspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-rose-700 dark:text-rose-300">Wanita Keluar Mati</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Jumlah Lama Dirawat</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Pasien Akhir Bulan</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Jumlah Hari Perawatan</th>
                                <th colspan="6" class="px-2 py-2 border border-hairline dark:border-gray-700 text-center text-purple-700 dark:text-purple-300">Rincian Hari Perawatan Per Kelas</th>
                                <th rowspan="2" class="px-2 py-2 border border-hairline dark:border-gray-700 text-right">Jumlah Alokasi TT Awal Bulan</th>
                            </tr>
                            {{-- Header row 2: subcolumns --}}
                            <tr class="text-[10px] font-semibold tracking-wider uppercase">
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-rose-700 dark:text-rose-300">&lt; 48 jam</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-rose-700 dark:text-rose-300">&ge; 48 jam</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-rose-700 dark:text-rose-300">&lt; 48 jam</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-rose-700 dark:text-rose-300">&ge; 48 jam</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">VVIP</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">VIP</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">1</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">2</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">3</th>
                                <th class="px-2 py-2 border border-hairline dark:border-gray-700 text-right text-purple-700 dark:text-purple-300">Khusus</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->rows as $r)
                                @php
                                    $isCatchAll = $r['id'] === 100;
                                    $rowClass = $isCatchAll
                                        ? 'bg-amber-50/70 dark:bg-amber-900/20 font-medium'
                                        : 'hover:bg-surface-soft dark:hover:bg-gray-800/50';
                                @endphp
                                <tr class="{{ $rowClass }} border-b border-hairline-soft dark:border-gray-800">
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-center font-mono text-muted sticky left-0 z-10 {{ $isCatchAll ? 'bg-amber-50/70 dark:bg-amber-900/20' : 'bg-canvas dark:bg-gray-900' }}">{{ $r['id'] }}</td>
                                    <td class="px-3 py-1.5 border-r border-hairline dark:border-gray-700 text-ink dark:text-gray-100 sticky left-12 z-10 {{ $isCatchAll ? 'bg-amber-50/70 dark:bg-amber-900/20' : 'bg-canvas dark:bg-gray-900' }}">{{ $r['nama'] }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($r['pasien_awal_bulan']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($r['pasien_masuk']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-muted-soft">{{ number_format($r['pasien_pindahan']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-muted-soft">{{ number_format($r['pasien_dipindahkan']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format($r['pasien_keluar_hidup']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['pria_mati_lt48']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['pria_mati_ge48']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['wanita_mati_lt48']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($r['wanita_mati_ge48']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($r['jumlah_lama_dirawat']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($r['pasien_akhir_bulan']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums font-semibold">{{ number_format($r['jumlah_hari_perawatan']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['kelas_vvip']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['kelas_vip']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['kelas_1']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['kelas_2']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['kelas_3']) }}</td>
                                    <td class="px-2 py-1.5 border-r border-hairline dark:border-gray-700 text-right tabular-nums text-purple-700 dark:text-purple-300">{{ number_format($r['kelas_khusus']) }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ number_format($r['alokasi_tt_awal']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-surface-soft dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600 font-bold">
                            <tr class="text-[11px] text-ink dark:text-gray-100">
                                <td colspan="2" class="px-3 py-2 border border-hairline dark:border-gray-700 sticky left-0 bg-surface-soft dark:bg-gray-800 z-10">TOTAL</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($tot['pasien_awal_bulan']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($tot['pasien_masuk']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-muted">{{ number_format($tot['pasien_pindahan']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-muted">{{ number_format($tot['pasien_dipindahkan']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-emerald-800 dark:text-emerald-200">{{ number_format($tot['pasien_keluar_hidup']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['pria_mati_lt48']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['pria_mati_ge48']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['wanita_mati_lt48']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ number_format($tot['wanita_mati_ge48']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($tot['jumlah_lama_dirawat']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($tot['pasien_akhir_bulan']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums">{{ number_format($tot['jumlah_hari_perawatan']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['kelas_vvip']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['kelas_vip']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['kelas_1']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['kelas_2']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['kelas_3']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ number_format($tot['kelas_khusus']) }}</td>
                                <td class="px-2 py-2 border border-hairline dark:border-gray-700 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ number_format($tot['alokasi_tt_awal']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
