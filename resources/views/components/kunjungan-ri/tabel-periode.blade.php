@props([
    // Baris per periode (bulan/tahun) hasil KunjunganRITrait::enrichWithBORTOIBTO().
    'rows' => [],
    // Baris TOTAL: totalsKunjungan() + totalBORTOIBTO().
    'totals' => [],
    'pasienUnikGlobal' => 0,
    // Judul kolom pertama: "Bulan" | "Tahun".
    'labelPeriode' => 'Periode',
    'kapasitasTT' => 0,
    'defaultKapasitasTT' => 0,
])

@php
    // Komponen hitungan SENGAJA ditampilkan sebagai kolom (bukan cuma tooltip): angka indikator
    // yang janggal harus bisa dilacak ke pembilang/penyebutnya tanpa membuka kode.
    $angka = fn($nilai, int $desimal = 0) => number_format((float) $nilai, $desimal);
    $rumusSel = function (array $row, string $indikator) use ($kapasitasTT, $angka) {
        $lamaDirawat = $angka($row['total_los'] ?? 0, 1);
        $hari = $angka($row['days_in_period'] ?? ($row['days_total'] ?? 0));
        $keluar = $angka($row['keluar_bor'] ?? 0);
        $tt = $angka($kapasitasTT);

        return match ($indikator) {
            'bor' => "BOR = Σ hari rawat ÷ (TT × hari) × 100 = {$lamaDirawat} ÷ ({$tt} × {$hari}) × 100",
            'alos' => "ALOS = Σ hari rawat ÷ pasien keluar = {$lamaDirawat} ÷ {$keluar}",
            'toi' => "TOI = ((TT × hari) − Σ hari rawat) ÷ pasien keluar = (({$tt} × {$hari}) − {$lamaDirawat}) ÷ {$keluar}",
            'bto' => "BTO = pasien keluar ÷ TT = {$keluar} ÷ {$tt}",
        };
    };
    $kelasAnomali = fn($jumlah) => $jumlah > 0 ? 'font-semibold text-amber-700 dark:text-amber-400' : 'text-muted-soft';
    $kelasDikeluarkan = fn($jumlah) => $jumlah > 0 ? 'font-bold text-rose-700 dark:text-rose-400' : 'text-muted-soft';
@endphp

<div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
    <div class="overflow-x-auto rounded-t-2xl">
        <table class="min-w-full text-sm">
            <thead class="bg-surface-card dark:bg-gray-800">
                <tr class="text-xs font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                    <th rowspan="2" class="px-4 py-2 text-left align-bottom">{{ $labelPeriode }}</th>
                    <th colspan="4" class="px-3 pt-2 pb-1 text-center border-b border-hairline dark:border-gray-700">Pasien Keluar</th>
                    <th colspan="4" class="px-3 pt-2 pb-1 text-center text-blue-700 border-b border-hairline dark:text-blue-300 dark:border-gray-700">Komponen Hitungan</th>
                    <th colspan="4" class="px-3 pt-2 pb-1 text-center text-purple-700 border-b border-hairline dark:text-purple-300 dark:border-gray-700">Indikator</th>
                    <th colspan="3" class="px-3 pt-2 pb-1 text-center text-amber-700 border-b border-hairline dark:text-amber-400 dark:border-gray-700">Data Janggal</th>
                </tr>
                <tr class="text-xs font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                    <th class="px-3 py-2 text-right" title="Kunjungan RI ber-exit_date dalam periode, klaim bukan Kronis, status bukan Batal (F)">Total</th>
                    <th class="px-3 py-2 text-right">Unik</th>
                    <th class="px-3 py-2 text-right text-emerald-700 dark:text-emerald-300">BPJS</th>
                    <th class="px-3 py-2 text-right text-amber-700 dark:text-amber-300">UMUM</th>
                    <th class="px-3 py-2 text-right text-blue-700 dark:text-blue-300" title="Hari periode yang SUDAH berjalan s/d hari ini (periode mendatang = 0)">Hari</th>
                    <th class="px-3 py-2 text-right text-blue-700 dark:text-blue-300" title="Hari TT tersedia = TT × hari periode">TT × Hari</th>
                    <th class="px-3 py-2 text-right text-blue-700 dark:text-blue-300" title="Pasien keluar yang ikut hitungan indikator = Total − data janggal − yang tidak pernah dirawat di bangsal yang dihitung BOR">Keluar Dihitung</th>
                    <th class="px-3 py-2 text-right text-blue-700 dark:text-blue-300" title="Σ hari rawat dari riwayat kamar, hanya di bangsal yang dihitung BOR; dibebankan ke periode tanggal pulang">Σ Hari Rawat</th>
                    <th class="px-2 py-2 text-right text-purple-700 dark:text-purple-300" title="Bed Occupancy Rate (%)">BOR</th>
                    <th class="px-2 py-2 text-right text-purple-700 dark:text-purple-300" title="Average Length of Stay (hari)">ALOS</th>
                    <th class="px-2 py-2 text-right text-purple-700 dark:text-purple-300" title="Turn Over Interval (hari)">TOI</th>
                    <th class="px-2 py-2 text-right text-purple-700 dark:text-purple-300" title="Bed Turn Over (kali)">BTO</th>
                    <th class="px-2 py-2 text-right text-rose-700 dark:text-rose-400" title="TIDAK ikut hitungan indikator: status bukan P, lama dirawat negatif, tanpa tanggal masuk, atau di atas batas wajar. Tetap terhitung di Total.">Dikeluarkan</th>
                    <th class="px-2 py-2 text-right text-amber-700 dark:text-amber-400" title="Peringatan saja (tetap dihitung): lama dirawat di bawah 1 hari, termasuk masuk = keluar">LOS &lt; 1 hr</th>
                    <th class="px-2 py-2 text-right text-rose-700 dark:text-rose-400" title="Bagian dari kolom Dikeluarkan: punya tanggal pulang tetapi ri_status bukan P (Pulang) — di Daftar RI masih tampil Dirawat">Status ≠ P</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="border-t border-hairline-soft dark:border-gray-800 hover:bg-surface-soft dark:hover:bg-gray-800/50 {{ $row['total'] === 0 ? 'opacity-50' : '' }}">
                        <td class="px-4 py-2.5 font-medium text-ink dark:text-gray-100">{{ $row['periode_label'] }}</td>
                        <td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ $angka($row['total']) }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-muted dark:text-gray-400">{{ $angka($row['pasien_unik']) }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ $angka($row['bpjs']) }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-amber-700 dark:text-amber-300">{{ $angka($row['umum']) }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ $angka($row['days_in_period']) }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ $angka($row['hari_tersedia']) }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums {{ $row['keluar_bor'] !== $row['total'] ? 'font-semibold text-amber-700 dark:text-amber-400' : 'text-blue-700 dark:text-blue-300' }}">{{ $angka($row['keluar_bor']) }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ $angka($row['total_los'], 1) }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums text-purple-700 dark:text-purple-300" title="{{ $rumusSel($row, 'bor') }}">{{ $row['bor'] !== null ? $row['bor'] . '%' : '—' }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums text-purple-700 dark:text-purple-300" title="{{ $rumusSel($row, 'alos') }}">{{ $row['keluar_bor'] > 0 ? $row['alos'] . ' hr' : '—' }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums text-purple-700 dark:text-purple-300" title="{{ $rumusSel($row, 'toi') }}">{{ $row['toi'] !== null ? $row['toi'] . ' hr' : '—' }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums text-purple-700 dark:text-purple-300" title="{{ $rumusSel($row, 'bto') }}">{{ $row['bto'] !== null ? $row['bto'] . 'x' : '—' }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums {{ $kelasDikeluarkan($row['anomali_dikeluarkan']) }}">{{ $angka($row['anomali_dikeluarkan']) }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums {{ $kelasAnomali($row['los_kurang_1']) }}">{{ $angka($row['los_kurang_1']) }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums {{ $kelasDikeluarkan($row['status_lain']) }}">{{ $angka($row['status_lain']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-surface-soft dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600">
                <tr class="text-sm font-bold text-ink dark:text-gray-100">
                    <td class="px-4 py-3">TOTAL</td>
                    <td class="px-3 py-3 text-right tabular-nums">{{ $angka($totals['total']) }}</td>
                    <td class="px-3 py-3 text-right tabular-nums text-muted" title="Pasien unik global (bukan jumlah kolom — pasien yang sama bisa pulang di beberapa periode)">{{ $angka($pasienUnikGlobal) }}*</td>
                    <td class="px-3 py-3 text-right tabular-nums text-emerald-800 dark:text-emerald-200">{{ $angka($totals['bpjs']) }}</td>
                    <td class="px-3 py-3 text-right tabular-nums text-amber-800 dark:text-amber-200">{{ $angka($totals['umum']) }}</td>
                    <td class="px-3 py-3 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ $angka($totals['days_total']) }}</td>
                    <td class="px-3 py-3 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ $angka($totals['hari_tersedia']) }}</td>
                    <td class="px-3 py-3 text-right tabular-nums {{ $totals['keluar_bor'] !== $totals['total'] ? 'text-amber-700 dark:text-amber-400' : 'text-blue-800 dark:text-blue-200' }}">{{ $angka($totals['keluar_bor']) }}</td>
                    <td class="px-3 py-3 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ $angka($totals['total_los'], 1) }}</td>
                    <td class="px-2 py-3 text-right tabular-nums text-purple-800 dark:text-purple-200" title="{{ $rumusSel($totals, 'bor') }}">{{ $totals['bor'] !== null ? $totals['bor'] . '%' : '—' }}</td>
                    <td class="px-2 py-3 text-right tabular-nums text-purple-800 dark:text-purple-200" title="{{ $rumusSel($totals, 'alos') }}">{{ $totals['keluar_bor'] > 0 ? $totals['alos'] . ' hr' : '—' }}</td>
                    <td class="px-2 py-3 text-right tabular-nums text-purple-800 dark:text-purple-200" title="{{ $rumusSel($totals, 'toi') }}">{{ $totals['toi'] !== null ? $totals['toi'] . ' hr' : '—' }}</td>
                    <td class="px-2 py-3 text-right tabular-nums text-purple-800 dark:text-purple-200" title="{{ $rumusSel($totals, 'bto') }}">{{ $totals['bto'] !== null ? $totals['bto'] . 'x' : '—' }}</td>
                    <td class="px-2 py-3 text-right tabular-nums {{ $kelasDikeluarkan($totals['anomali_dikeluarkan']) }}">{{ $angka($totals['anomali_dikeluarkan']) }}</td>
                    <td class="px-2 py-3 text-right tabular-nums {{ $kelasAnomali($totals['los_kurang_1']) }}">{{ $angka($totals['los_kurang_1']) }}</td>
                    <td class="px-2 py-3 text-right tabular-nums {{ $kelasDikeluarkan($totals['status_lain']) }}">{{ $angka($totals['status_lain']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="px-4 py-2 text-[10px] text-muted dark:text-gray-500 border-t border-hairline-soft dark:border-gray-800">
        *) Pasien unik global. Arahkan kursor ke angka BOR / ALOS / TOI / BTO untuk melihat rumus berikut angkanya.
        <strong>TT</strong> = {{ $angka($kapasitasTT) }} bed
        @if ((int) $kapasitasTT !== (int) $defaultKapasitasTT)
            <span class="text-amber-600 dark:text-amber-400">(ditimpa manual; Σ bangsal yang dihitung = {{ $angka($defaultKapasitasTT) }})</span>
        @endif.
        <strong>Hari</strong> = hari yang sudah berjalan s/d hari ini; periode yang belum berjalan ditampilkan "—".
        <strong class="text-rose-700 dark:text-rose-400">Dikeluarkan</strong> = data janggal yang tidak ikut hitungan indikator (rinciannya di kartu Pemeriksaan Data); <strong class="text-amber-700 dark:text-amber-400">kuning</strong> = peringatan saja.
    </div>
</div>
