<?php
// resources/views/pages/manajemen/rs/ugd/laporan-hak-kelas-ugd/laporan-hak-kelas-ugd.blade.php
// Laporan Kunjungan UGD per Hak Kelas Rawat BPJS (kelas 1 / 2 / 3).
// State, query, dan turunan ada di App\Http\Traits\Manajemen\Rs\HakKelasTrait (satu sumber untuk RJ/UGD/RI).
// Tampilan sengaja ditanam per jalur — mengubah kolom/teks berarti mengubah ketiga berkas laporan-hak-kelas-*.
// Berkas ini memegang konfigurasi + tampilan jalur UGD.

use Livewire\Component;
use App\Http\Traits\Manajemen\Rs\HakKelasTrait;

new class extends Component {
    use HakKelasTrait;

    protected function konfigurasiHakKelas(): array
    {
        return [
            'tabel'           => 'rstxn_ugdhdrs',
            'kunci'           => 'rj_no',
            'json'            => 'datadaftarugd_json',
            'tanggal'         => 'rj_date',
            // rj_status: A antrian, L selesai, I transfer RI, F batal
            'syaratAktif'     => "NVL(h.rj_status,'A') <> 'F'",
            // UGD tidak punya poli — pengelompokan memakai dokter
            'grupJoin'        => fn($query) => $query->leftJoin('rsmst_doctors as g', 'g.dr_id', '=', 'h.dr_id'),
            'grupSelect'      => 'h.dr_id as grup_id, g.dr_name as grup_nama',
            'labelGrupKosong' => '(Tanpa Dokter)',
        ];
    }
};
?>

<div>
    @php
        $tot = $this->totals;
        [$tahunMin, $tahunMax] = $this->rentangTahun();
        $teksPeriode = $tahunMin === $tahunMax ? (string) $tahunMin : $tahunMin . '–' . $tahunMax;
        $angka = fn($nilai) => number_format((float) $nilai);
        $persen = fn($nilai) => $nilai !== null ? $nilai . '%' : '—';
        $kelasPeringatan = fn($jumlah) => $jumlah > 0 ? 'font-semibold text-amber-700 dark:text-amber-400' : 'text-muted-soft';
        $bpjsTakPasti = $tot['sep_tak_terbaca'] + $tot['belum_sep'];
    @endphp

    <x-page-title title="Laporan Kunjungan UGD per Hak Kelas"
        subtitle="Kunjungan UGD BPJS menurut hak kelas rawat peserta (kelas 1 / 2 / 3) yang tercatat saat pembuatan SEP. Periode berdasarkan tanggal kunjungan; pasien Kronis dan kunjungan batal tidak dihitung." />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-canvas dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TAB + FILTER (sticky) --}}
            <div class="sticky z-30 px-4 py-3 border-b bg-surface-soft border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <span class="text-sm font-medium text-body dark:text-gray-300">Periode:</span>
                    <div class="inline-flex overflow-hidden border border-gray-300 rounded-lg dark:border-gray-600">
                        <button type="button" wire:click="setMode('bulanan')"
                            class="px-4 py-2 text-sm font-medium transition-colors
                                {{ $mode === 'bulanan'
                                    ? 'bg-brand-green text-white dark:bg-brand-lime dark:text-slate-900'
                                    : 'bg-canvas text-body hover:bg-surface-soft dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800' }}"
                            title="1 tahun — rincian per bulan">
                            Tahunan
                        </button>
                        <button type="button" wire:click="setMode('tahunan')"
                            class="px-4 py-2 text-sm font-medium transition-colors border-l border-gray-300 dark:border-gray-600
                                {{ $mode === 'tahunan'
                                    ? 'bg-brand-green text-white dark:bg-brand-lime dark:text-slate-900'
                                    : 'bg-canvas text-body hover:bg-surface-soft dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800' }}"
                            title="Rentang tahun — rincian per tahun">
                            Multi-Tahun
                        </button>
                    </div>

                    @if ($mode === 'bulanan')
                        <div class="flex items-center gap-2 text-sm text-body dark:text-gray-300">
                            <span>Tahun</span>
                            <x-text-input type="number" min="2000" max="2099" wire:model.live.debounce.600ms="filterTahun"
                                class="!w-24 !py-1 text-right tabular-nums" />
                        </div>
                    @else
                        <div class="flex items-center gap-2 text-sm text-body dark:text-gray-300">
                            <span>Tahun</span>
                            <x-text-input type="number" min="2000" max="2099" wire:model.live.debounce.600ms="tahunFrom"
                                class="!w-24 !py-1 text-right tabular-nums" />
                            <span>&ndash;</span>
                            <x-text-input type="number" min="2000" max="2099" wire:model.live.debounce.600ms="tahunTo"
                                class="!w-24 !py-1 text-right tabular-nums" />
                        </div>
                    @endif

                    <span wire:loading class="text-xs text-muted dark:text-gray-400">Menghitung…</span>
                </div>
            </div>

            {{-- RINGKASAN --}}
            <div class="grid grid-cols-2 gap-3 mt-4 sm:grid-cols-3 lg:grid-cols-6">
                <div class="p-3 border bg-canvas border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs uppercase text-muted">Kunjungan UGD {{ $teksPeriode }}</div>
                    <div class="mt-1 text-2xl font-bold text-ink dark:text-gray-100">{{ $angka($tot['total']) }}</div>
                    <div class="text-[10px] text-muted">{{ $angka($this->pasienUnikGlobal) }} pasien unik</div>
                </div>
                <div class="p-3 border bg-emerald-50 border-emerald-200 rounded-xl dark:bg-emerald-900/20 dark:border-emerald-700">
                    <div class="text-xs uppercase text-emerald-700 dark:text-emerald-300">BPJS</div>
                    <div class="mt-1 text-2xl font-bold text-emerald-800 dark:text-emerald-200">{{ $angka($tot['bpjs']) }}</div>
                    <div class="text-[10px] text-emerald-600 dark:text-emerald-400">Non-BPJS {{ $angka($tot['non_bpjs']) }}</div>
                </div>
                @foreach ([1 => 'kelas1', 2 => 'kelas2', 3 => 'kelas3'] as $nomorKelas => $kunciKelas)
                    <div class="p-3 border border-purple-200 bg-purple-50 rounded-xl dark:bg-purple-900/20 dark:border-purple-700">
                        <div class="text-xs font-bold text-purple-700 uppercase dark:text-purple-300">Hak Kelas {{ $nomorKelas }}</div>
                        <div class="mt-1 text-2xl font-bold text-purple-800 dark:text-purple-200">{{ $angka($tot[$kunciKelas]) }}</div>
                        <div class="text-[10px] text-purple-600 dark:text-purple-400">{{ $persen($tot['persen' . $nomorKelas]) }} dari yang terbaca</div>
                    </div>
                @endforeach
                <div class="p-3 border rounded-xl {{ $bpjsTakPasti > 0 ? 'bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-700' : 'bg-canvas border-hairline dark:border-gray-700 dark:bg-gray-900' }}">
                    <div class="text-xs uppercase {{ $bpjsTakPasti > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-muted' }}">BPJS Tanpa Hak Kelas</div>
                    <div class="mt-1 text-2xl font-bold {{ $bpjsTakPasti > 0 ? 'text-amber-800 dark:text-amber-200' : 'text-ink dark:text-gray-100' }}">{{ $angka($bpjsTakPasti) }}</div>
                    <div class="text-[10px] text-muted dark:text-gray-400">tidak masuk kelas mana pun</div>
                </div>
            </div>

            {{-- TABEL PER PERIODE --}}
            <div class="mt-4 border shadow-sm bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-card dark:bg-gray-800">
                            <tr class="text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-300">
                                <th rowspan="2" class="px-4 py-2 text-left align-bottom">{{ $mode === 'bulanan' ? 'Bulan' : 'Tahun' }}</th>
                                <th colspan="3" class="px-3 pt-2 pb-1 text-center border-b border-hairline dark:border-gray-700">Kunjungan</th>
                                <th colspan="6" class="px-3 pt-2 pb-1 text-center text-purple-700 border-b border-hairline dark:text-purple-300 dark:border-gray-700">Hak Kelas Rawat (BPJS)</th>
                                <th colspan="2" class="px-3 pt-2 pb-1 text-center text-amber-700 border-b border-hairline dark:text-amber-400 dark:border-gray-700">BPJS Tanpa Hak Kelas</th>
                            </tr>
                            <tr class="text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-300">
                                <th class="px-3 py-2 text-right" title="Kunjungan UGD dalam periode, bukan Kronis, bukan batal">Total</th>
                                <th class="px-3 py-2 text-right text-emerald-700 dark:text-emerald-300" title="klaim BPJS atau JKN Mobile">BPJS</th>
                                <th class="px-3 py-2 text-right" title="Umum, asuransi lain, dsb. — tidak punya hak kelas BPJS">Non-BPJS</th>
                                @foreach ([1, 2, 3] as $nomorKelas)
                                    <th class="px-3 py-2 text-right text-purple-700 dark:text-purple-300">Kelas {{ $nomorKelas }}</th>
                                    <th class="px-2 py-2 text-right text-purple-700 dark:text-purple-300" title="Persentase terhadap BPJS yang hak kelasnya terbaca (kelas 1 + 2 + 3)">%</th>
                                @endforeach
                                <th class="px-3 py-2 text-right text-amber-700 dark:text-amber-400" title="Sudah punya nomor SEP tetapi hak kelas tidak ditemukan di data SEP kunjungan">SEP, Tak Terbaca</th>
                                <th class="px-3 py-2 text-right text-amber-700 dark:text-amber-400" title="Klaim BPJS tetapi belum punya nomor SEP">Belum SEP</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->rows as $row)
                                <tr class="border-t border-hairline-soft dark:border-gray-800 hover:bg-surface-soft dark:hover:bg-gray-800/50 {{ $row['total'] === 0 ? 'opacity-50' : '' }}">
                                    <td class="px-4 py-2.5 font-medium text-ink dark:text-gray-100">{{ $row['label'] }}</td>
                                    <td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ $angka($row['total']) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ $angka($row['bpjs']) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-muted dark:text-gray-400">{{ $angka($row['non_bpjs']) }}</td>
                                    @foreach ([1, 2, 3] as $nomorKelas)
                                        <td class="px-3 py-2.5 text-right font-semibold tabular-nums text-purple-700 dark:text-purple-300">{{ $angka($row['kelas' . $nomorKelas]) }}</td>
                                        <td class="px-2 py-2.5 text-right tabular-nums text-muted dark:text-gray-400"
                                            title="{{ $angka($row['kelas' . $nomorKelas]) }} ÷ {{ $angka($row['terbaca']) }} × 100">{{ $persen($row['persen' . $nomorKelas]) }}</td>
                                    @endforeach
                                    <td class="px-3 py-2.5 text-right tabular-nums {{ $kelasPeringatan($row['sep_tak_terbaca']) }}">{{ $angka($row['sep_tak_terbaca']) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums {{ $kelasPeringatan($row['belum_sep']) }}">{{ $angka($row['belum_sep']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-gray-300 bg-surface-soft dark:bg-gray-800 dark:border-gray-600">
                            <tr class="text-sm font-bold text-ink dark:text-gray-100">
                                <td class="px-4 py-3">TOTAL</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ $angka($tot['total']) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums text-emerald-800 dark:text-emerald-200">{{ $angka($tot['bpjs']) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums text-muted">{{ $angka($tot['non_bpjs']) }}</td>
                                @foreach ([1, 2, 3] as $nomorKelas)
                                    <td class="px-3 py-3 text-right text-purple-800 tabular-nums dark:text-purple-200">{{ $angka($tot['kelas' . $nomorKelas]) }}</td>
                                    <td class="px-2 py-3 text-right tabular-nums text-muted"
                                        title="{{ $angka($tot['kelas' . $nomorKelas]) }} ÷ {{ $angka($tot['terbaca']) }} × 100">{{ $persen($tot['persen' . $nomorKelas]) }}</td>
                                @endforeach
                                <td class="px-3 py-3 text-right tabular-nums {{ $kelasPeringatan($tot['sep_tak_terbaca']) }}">{{ $angka($tot['sep_tak_terbaca']) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums {{ $kelasPeringatan($tot['belum_sep']) }}">{{ $angka($tot['belum_sep']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="px-4 py-2 text-[10px] leading-snug text-muted dark:text-gray-500 border-t border-hairline-soft dark:border-gray-800">
                    <strong>%</strong> = jumlah kelas ÷ (kelas 1 + kelas 2 + kelas 3) × 100 — arahkan kursor untuk melihat angkanya.
                    Uji silang: BPJS {{ $angka($tot['bpjs']) }} = kelas 1–3 {{ $angka($tot['terbaca']) }} + tanpa hak kelas {{ $angka($bpjsTakPasti) }}
                    → <strong class="{{ $tot['bpjs'] === $tot['terbaca'] + $bpjsTakPasti ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">{{ $tot['bpjs'] === $tot['terbaca'] + $bpjsTakPasti ? 'cocok' : 'BEDA' }}</strong>.
                </div>
            </div>

            {{-- PER DOKTER --}}
            <div class="mt-4 border shadow-sm bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-body dark:text-gray-200">
                        Hak Kelas per Dokter
                        <span class="ml-2 text-xs font-normal text-muted">{{ $teksPeriode }} · {{ count($this->grupBreakdown) }} dokter · urut BPJS terbanyak · UGD tidak punya poli</span>
                    </h3>
                </div>
                <div class="overflow-x-auto max-h-[600px] overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-surface-card dark:bg-gray-800">
                            <tr class="text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-300">
                                <th class="w-12 px-4 py-3 text-left">#</th>
                                <th class="px-3 py-3 text-left">Dokter</th>
                                <th class="px-3 py-3 text-right">Total</th>
                                <th class="px-3 py-3 text-right text-emerald-700 dark:text-emerald-300">BPJS</th>
                                <th class="px-3 py-3 text-right">Non-BPJS</th>
                                @foreach ([1, 2, 3] as $nomorKelas)
                                    <th class="px-3 py-3 text-right text-purple-700 dark:text-purple-300">Kelas {{ $nomorKelas }}</th>
                                    <th class="px-2 py-3 text-right text-purple-700 dark:text-purple-300">%</th>
                                @endforeach
                                <th class="px-3 py-3 text-right text-amber-700 dark:text-amber-400">SEP, Tak Terbaca</th>
                                <th class="px-3 py-3 text-right text-amber-700 dark:text-amber-400">Belum SEP</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->grupBreakdown as $nomor => $row)
                                <tr class="border-t border-hairline-soft dark:border-gray-800 hover:bg-surface-soft dark:hover:bg-gray-800/50">
                                    <td class="px-4 py-2.5 font-bold text-muted-soft">{{ $nomor + 1 }}</td>
                                    <td class="px-3 py-2.5 font-medium text-ink dark:text-gray-100">{{ $row['label'] }}</td>
                                    <td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ $angka($row['total']) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ $angka($row['bpjs']) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-muted dark:text-gray-400">{{ $angka($row['non_bpjs']) }}</td>
                                    @foreach ([1, 2, 3] as $nomorKelas)
                                        <td class="px-3 py-2.5 text-right font-semibold tabular-nums text-purple-700 dark:text-purple-300">{{ $angka($row['kelas' . $nomorKelas]) }}</td>
                                        <td class="px-2 py-2.5 text-right tabular-nums text-muted dark:text-gray-400">{{ $persen($row['persen' . $nomorKelas]) }}</td>
                                    @endforeach
                                    <td class="px-3 py-2.5 text-right tabular-nums {{ $kelasPeringatan($row['sep_tak_terbaca']) }}">{{ $angka($row['sep_tak_terbaca']) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums {{ $kelasPeringatan($row['belum_sep']) }}">{{ $angka($row['belum_sep']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="px-6 py-12 text-center text-muted dark:text-gray-400">Belum ada data</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- CARA BACA & SUMBER DATA --}}
            <div class="mt-4 border shadow-sm bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-body dark:text-gray-200">Sumber Data &amp; Cara Hitung</h3>
                </div>
                <dl class="grid grid-cols-1 gap-x-8 gap-y-3 p-4 text-xs md:grid-cols-2 text-body dark:text-gray-300">
                    <div>
                        <dt class="font-semibold text-ink dark:text-gray-100">Hak kelas</dt>
                        <dd class="text-muted dark:text-gray-400">Hak kelas rawat peserta BPJS yang tercatat di data SEP kunjungan (kelas rawat hak), terisi otomatis dari cek kepesertaan saat SEP dibuat. Bukan kelas yang ditempati — UGD tidak punya kelas kamar. Pasien UGD yang lanjut rawat inap mendapat SEP Rawat Inap tersendiri dan muncul di laporan RI.</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-ink dark:text-gray-100">Yang dihitung</dt>
                        <dd class="text-muted dark:text-gray-400">Kunjungan UGD menurut tanggal kunjungan. Pasien Kronis dan kunjungan batal dikeluarkan. Satu pasien yang berkunjung beberapa kali dihitung tiap kunjungan; jumlah orangnya ada di "pasien unik".</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-amber-700 dark:text-amber-400">SEP, Tak Terbaca</dt>
                        <dd class="text-muted dark:text-gray-400">Kunjungan BPJS yang sudah punya nomor SEP tetapi hak kelasnya tidak ada di data SEP kunjungan — biasanya SEP dibuat di luar aplikasi ini (V-Claim langsung / sistem lama) lalu nomornya saja yang dimasukkan. Tidak ditebak ke kelas mana pun.</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-amber-700 dark:text-amber-400">Belum SEP</dt>
                        <dd class="text-muted dark:text-gray-400">Kunjungan berklaim BPJS yang belum punya nomor SEP. Untuk periode yang sudah lewat, angka ini layak ditelusuri ke Pendaftaran / Casemix.</dd>
                    </div>
                </dl>
            </div>

        </div>
    </div>
</div>
