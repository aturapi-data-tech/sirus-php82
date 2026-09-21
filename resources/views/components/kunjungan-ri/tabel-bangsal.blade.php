@props([
    // Baris hasil KunjunganRITrait::enrichBangsalIndicators().
    'rows' => [],
    // Hari periode yang sudah berjalan (pembagi BOR/TOI semua bangsal).
    'hariPeriode' => 0,
    // Teks periode untuk catatan kaki, mis. "tahun 2026".
    'keteranganPeriode' => '',
])

@php
    $angka = fn($nilai, int $desimal = 0) => number_format((float) $nilai, $desimal);
    $kelasAnomali = fn($jumlah) => $jumlah > 0 ? 'font-semibold text-amber-700 dark:text-amber-400' : 'text-muted-soft';
    $rumusSel = function (array $row, string $indikator) use ($hariPeriode, $angka) {
        $lamaDirawat = $angka($row['total_los'], 1);
        $hari = $angka($hariPeriode);
        $keluar = $angka($row['total']);
        $tt = $angka($row['tt']);

        return match ($indikator) {
            'bor' => "BOR = {$lamaDirawat} ÷ ({$tt} × {$hari}) × 100",
            'alos' => "ALOS = {$lamaDirawat} ÷ {$keluar}",
            'bto' => "BTO = {$keluar} ÷ {$tt}",
            'toi' => "TOI = (({$tt} × {$hari}) − {$lamaDirawat}) ÷ {$keluar}",
            'ndr' => 'NDR = ' . $angka($row['meninggal48']) . " ÷ {$keluar} × 1000",
            'gdr' => 'GDR = ' . $angka($row['meninggal']) . " ÷ {$keluar} × 1000",
        };
    };

    // Baris JUMLAH dihitung ulang dari penjumlahan komponennya (bukan rata-rata persentase).
    $jumlah = [
        'tt' => array_sum(array_column($rows, 'tt')),
        'total' => array_sum(array_column($rows, 'total')),
        'total_los' => array_sum(array_column($rows, 'total_los')),
        'los_kurang_1' => array_sum(array_column($rows, 'los_kurang_1')),
        'meninggal' => array_sum(array_column($rows, 'meninggal')),
        'meninggal48' => array_sum(array_column($rows, 'meninggal48')),
    ];
    $jumlah['hari_tersedia'] = $jumlah['tt'] * (int) $hariPeriode;
    $jumlah['bor'] = $jumlah['hari_tersedia'] > 0 ? round($jumlah['total_los'] / $jumlah['hari_tersedia'] * 100, 1) : null;
    $jumlah['alos'] = $jumlah['total'] > 0 ? round($jumlah['total_los'] / $jumlah['total'], 1) : null;
    $jumlah['bto'] = ($jumlah['tt'] > 0 && $hariPeriode > 0) ? round($jumlah['total'] / $jumlah['tt'], 2) : null;
    $jumlah['toi'] = ($jumlah['total'] > 0 && $jumlah['hari_tersedia'] > 0) ? round(($jumlah['hari_tersedia'] - $jumlah['total_los']) / $jumlah['total'], 1) : null;
    $jumlah['ndr'] = $jumlah['total'] > 0 ? round($jumlah['meninggal48'] / $jumlah['total'] * 1000, 1) : null;
    $jumlah['gdr'] = $jumlah['total'] > 0 ? round($jumlah['meninggal'] / $jumlah['total'] * 1000, 1) : null;
@endphp

<div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
    <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
        <h3 class="text-sm font-semibold text-body dark:text-gray-200">
            Indikator Pelayanan per Bangsal (Periode Terpilih)
            <span class="ml-2 font-normal text-xs text-muted">{{ count($rows) }} bangsal</span>
        </h3>
    </div>
    <div class="overflow-x-auto max-h-[600px] overflow-y-auto">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 z-10 bg-surface-card dark:bg-gray-800">
                <tr class="text-xs font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                    <th class="px-4 py-3 text-left w-12">#</th>
                    <th class="px-3 py-3 text-left">Jenis Pelayanan (Bangsal)</th>
                    <th class="px-3 py-3 text-right text-blue-700 dark:text-blue-300" title="Jumlah bed bangsal ini (rsmst_beds lewat kamar → bangsal)">TT</th>
                    <th class="px-3 py-3 text-right text-blue-700 dark:text-blue-300" title="Hari TT tersedia = TT × hari periode">TT × Hari</th>
                    <th class="px-3 py-3 text-right">Pasien Keluar</th>
                    <th class="px-3 py-3 text-right text-blue-700 dark:text-blue-300" title="Σ (exit_date − entry_date) pasien keluar bangsal ini">Σ Lama Dirawat</th>
                    <th class="px-2 py-3 text-right text-amber-700 dark:text-amber-400" title="Pasien keluar dengan lama dirawat di bawah 1 hari">LOS &lt; 1 hr</th>
                    <th class="px-2 py-3 text-right text-purple-700 dark:text-purple-300" title="Bed Occupancy Rate (%)">BOR</th>
                    <th class="px-2 py-3 text-right text-purple-700 dark:text-purple-300" title="Average Length of Stay (hari)">ALOS</th>
                    <th class="px-2 py-3 text-right text-purple-700 dark:text-purple-300" title="Bed Turn Over (kali)">BTO</th>
                    <th class="px-2 py-3 text-right text-purple-700 dark:text-purple-300" title="Turn Over Interval (hari)">TOI</th>
                    <th class="px-2 py-3 text-right text-rose-700 dark:text-rose-300" title="Jumlah pasien keluar meninggal (semua)">Meninggal</th>
                    <th class="px-2 py-3 text-right text-rose-700 dark:text-rose-300" title="Meninggal dengan lama dirawat 48 jam atau lebih">≥ 48 jam</th>
                    <th class="px-2 py-3 text-right text-rose-700 dark:text-rose-300" title="Net Death Rate (per 1000) — meninggal ≥ 48 jam">NDR</th>
                    <th class="px-2 py-3 text-right text-rose-700 dark:text-rose-300" title="Gross Death Rate (per 1000) — semua meninggal">GDR</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $nomor => $row)
                    <tr class="border-t border-hairline-soft dark:border-gray-800 hover:bg-surface-soft dark:hover:bg-gray-800/50">
                        <td class="px-4 py-2.5 font-bold text-muted-soft">{{ $nomor + 1 }}</td>
                        <td class="px-3 py-2.5 font-medium text-ink dark:text-gray-100">{{ $row['bangsal_name'] ?? '(Tanpa Bangsal)' }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ $row['tt'] > 0 ? $angka($row['tt']) : '—' }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ $row['tt'] > 0 ? $angka($row['hari_tersedia']) : '—' }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums font-semibold">{{ $angka($row['total']) }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ $angka($row['total_los'], 1) }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums {{ $kelasAnomali($row['los_kurang_1']) }}">{{ $angka($row['los_kurang_1']) }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums text-purple-700 dark:text-purple-300" title="{{ $rumusSel($row, 'bor') }}">{{ $row['bor'] !== null ? $row['bor'] . '%' : '—' }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums text-purple-700 dark:text-purple-300" title="{{ $rumusSel($row, 'alos') }}">{{ $row['alos'] }} hr</td>
                        <td class="px-2 py-2.5 text-right tabular-nums text-purple-700 dark:text-purple-300" title="{{ $rumusSel($row, 'bto') }}">{{ $row['bto'] !== null ? $row['bto'] . 'x' : '—' }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums text-purple-700 dark:text-purple-300" title="{{ $rumusSel($row, 'toi') }}">{{ $row['toi'] !== null ? $row['toi'] . ' hr' : '—' }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ $angka($row['meninggal']) }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ $angka($row['meninggal48']) }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums text-rose-700 dark:text-rose-300" title="{{ $rumusSel($row, 'ndr') }}">{{ $row['ndr'] }}‰</td>
                        <td class="px-2 py-2.5 text-right tabular-nums text-rose-700 dark:text-rose-300" title="{{ $rumusSel($row, 'gdr') }}">{{ $row['gdr'] }}‰</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="15" class="px-6 py-12">
                            <div class="flex flex-col items-center justify-center gap-3">
                                <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                <p class="text-base font-medium text-muted dark:text-gray-400">Belum ada data</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if (count($rows) > 0)
                <tfoot class="bg-surface-soft dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600">
                    <tr class="text-sm font-bold text-ink dark:text-gray-100">
                        <td class="px-4 py-3"></td>
                        <td class="px-3 py-3">JUMLAH</td>
                        <td class="px-3 py-3 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ $angka($jumlah['tt']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ $angka($jumlah['hari_tersedia']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ $angka($jumlah['total']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-blue-800 dark:text-blue-200">{{ $angka($jumlah['total_los'], 1) }}</td>
                        <td class="px-2 py-3 text-right tabular-nums {{ $kelasAnomali($jumlah['los_kurang_1']) }}">{{ $angka($jumlah['los_kurang_1']) }}</td>
                        <td class="px-2 py-3 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ $jumlah['bor'] !== null ? $jumlah['bor'] . '%' : '—' }}</td>
                        <td class="px-2 py-3 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ $jumlah['alos'] !== null ? $jumlah['alos'] . ' hr' : '—' }}</td>
                        <td class="px-2 py-3 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ $jumlah['bto'] !== null ? $jumlah['bto'] . 'x' : '—' }}</td>
                        <td class="px-2 py-3 text-right tabular-nums text-purple-800 dark:text-purple-200">{{ $jumlah['toi'] !== null ? $jumlah['toi'] . ' hr' : '—' }}</td>
                        <td class="px-2 py-3 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ $angka($jumlah['meninggal']) }}</td>
                        <td class="px-2 py-3 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ $angka($jumlah['meninggal48']) }}</td>
                        <td class="px-2 py-3 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ $jumlah['ndr'] !== null ? $jumlah['ndr'] . '‰' : '—' }}</td>
                        <td class="px-2 py-3 text-right tabular-nums text-rose-800 dark:text-rose-200">{{ $jumlah['gdr'] !== null ? $jumlah['gdr'] . '‰' : '—' }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
    <div class="px-4 py-2 text-[10px] leading-snug text-muted dark:text-gray-500 border-t border-hairline-soft dark:border-gray-800">
        <strong>Hari periode</strong> = {{ $angka($hariPeriode) }} hari yang sudah berjalan{{ $keteranganPeriode !== '' ? ' (' . $keteranganPeriode . ')' : '' }}.
        <strong>TT</strong> per bangsal dari master bed, tidak ikut perubahan Kapasitas TT di atas.
        <strong>Bangsal</strong> = bangsal kamar TERAKHIR pasien: bila pasien pindah kamar, seluruh lama dirawatnya dibebankan ke bangsal terakhir —
        bangsal transit (mis. ICU, perinatologi) bisa tampak terlalu sepi.
        <strong>Meninggal</strong> dibaca dari EMR Perencanaan RI (tindak lanjut SNOMED 419099009); kematian yang tidak dicatat di sana tidak terhitung.
        NDR/GDR per 1000 pasien keluar.
    </div>
</div>
