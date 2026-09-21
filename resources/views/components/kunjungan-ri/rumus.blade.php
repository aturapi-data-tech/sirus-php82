@props([
    // Baris TOTAL: totalsKunjungan() + totalBORTOIBTO().
    'totals' => [],
    'kapasitasTT' => 0,
    // Panjang kalender penuh periode terpilih (365/366 atau jumlah beberapa tahun).
    'hariKalender' => 0,
])

@php
    $angka = fn($nilai, int $desimal = 0) => number_format((float) $nilai, $desimal);

    $lamaDirawat = (float) ($totals['total_los'] ?? 0);
    $keluar = (int) ($totals['total'] ?? 0);
    $hari = (int) ($totals['days_total'] ?? 0);
    $tt = (int) $kapasitasTT;
    $hariTersedia = $tt * $hari;

    // BTO periode berjalan belum setahun penuh — disetahunkan supaya bisa dibanding angka ideal 40–50x/tahun.
    $btoSetahun = ($tt > 0 && $hari > 0) ? round($keluar / $tt * 365 / $hari, 2) : null;

    $daftarRumus = [
        [
            'kode' => 'BOR', 'nama' => 'Bed Occupancy Rate', 'ideal' => '60–85%',
            'rumus' => 'Σ lama dirawat ÷ (TT × hari periode) × 100',
            'isi' => $angka($lamaDirawat, 1) . ' ÷ (' . $angka($tt) . ' × ' . $angka($hari) . ') × 100',
            'hasil' => ($totals['bor'] ?? null) !== null ? $totals['bor'] . '%' : '—',
        ],
        [
            'kode' => 'ALOS', 'nama' => 'Average Length of Stay', 'ideal' => '6–9 hari',
            'rumus' => 'Σ lama dirawat ÷ pasien keluar',
            'isi' => $angka($lamaDirawat, 1) . ' ÷ ' . $angka($keluar),
            'hasil' => $keluar > 0 ? ($totals['alos'] ?? 0) . ' hari' : '—',
        ],
        [
            'kode' => 'TOI', 'nama' => 'Turn Over Interval', 'ideal' => '1–3 hari',
            'rumus' => '((TT × hari periode) − Σ lama dirawat) ÷ pasien keluar',
            'isi' => '(' . $angka($hariTersedia) . ' − ' . $angka($lamaDirawat, 1) . ') ÷ ' . $angka($keluar),
            'hasil' => ($totals['toi'] ?? null) !== null ? $totals['toi'] . ' hari' : '—',
        ],
        [
            'kode' => 'BTO', 'nama' => 'Bed Turn Over', 'ideal' => '40–50x / tahun',
            'rumus' => 'pasien keluar ÷ TT',
            'isi' => $angka($keluar) . ' ÷ ' . $angka($tt),
            'hasil' => ($totals['bto'] ?? null) !== null ? $totals['bto'] . 'x' : '—',
        ],
    ];
@endphp

<div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
    <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
        <h3 class="text-sm font-semibold text-body dark:text-gray-200">
            Rumus &amp; Komponen Perhitungan
            <span class="ml-2 text-xs font-normal text-muted">angka baris TOTAL — untuk mencocokkan hasil dengan sumbernya</span>
        </h3>
    </div>

    <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($daftarRumus as $rumus)
            <div class="p-3 border border-purple-200 rounded-xl bg-purple-50 dark:bg-purple-900/20 dark:border-purple-700">
                <div class="flex items-baseline justify-between gap-2">
                    <div class="text-xs font-bold text-purple-700 uppercase dark:text-purple-300">{{ $rumus['kode'] }}</div>
                    <div class="text-[10px] text-purple-600 dark:text-purple-400">ideal {{ $rumus['ideal'] }}</div>
                </div>
                <div class="text-[11px] text-muted dark:text-gray-400">{{ $rumus['nama'] }}</div>
                <div class="mt-2 text-xs text-body dark:text-gray-300">= {{ $rumus['rumus'] }}</div>
                <div class="mt-1 text-xs tabular-nums text-ink dark:text-gray-100">= {{ $rumus['isi'] }}</div>
                <div class="mt-1 text-lg font-bold tabular-nums text-purple-800 dark:text-purple-200">= {{ $rumus['hasil'] }}</div>
                @if ($rumus['kode'] === 'BTO' && $btoSetahun !== null && $hari !== (int) $hariKalender)
                    <div class="mt-1 text-[11px] text-muted dark:text-gray-400">
                        Disetahunkan: {{ $rumus['hasil'] }} × 365 ÷ {{ $angka($hari) }} hari = <strong>{{ $btoSetahun }}x</strong>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="px-4 pb-4">
        <dl class="grid grid-cols-1 gap-x-8 gap-y-2 text-xs md:grid-cols-2 text-body dark:text-gray-300">
            <div>
                <dt class="font-semibold text-ink dark:text-gray-100">Pasien keluar = {{ $angka($keluar) }}</dt>
                <dd class="text-muted dark:text-gray-400">Kunjungan RI yang tanggal pulangnya (exit_date) jatuh dalam periode; klaim Kronis dan status Batal (F) dikeluarkan.</dd>
            </div>
            <div>
                <dt class="font-semibold text-ink dark:text-gray-100">Σ lama dirawat = {{ $angka($lamaDirawat, 1) }} hari</dt>
                <dd class="text-muted dark:text-gray-400">Jumlah (exit_date − entry_date) tiap pasien keluar, dalam hari berpecahan (masuk 23.00 keluar 01.00 = 0,08 hari). Seluruhnya dibebankan ke periode tanggal PULANG.</dd>
            </div>
            <div>
                <dt class="font-semibold text-ink dark:text-gray-100">Hari periode = {{ $angka($hari) }} dari {{ $angka($hariKalender) }} hari kalender</dt>
                <dd class="text-muted dark:text-gray-400">Hanya hari yang sudah berjalan s/d hari ini. Periode yang belum berjalan tidak ikut membagi, supaya BOR/TOI tidak terencerkan.</dd>
            </div>
            <div>
                <dt class="font-semibold text-ink dark:text-gray-100">TT × hari = {{ $angka($tt) }} × {{ $angka($hari) }} = {{ $angka($hariTersedia) }} hari TT tersedia</dt>
                <dd class="text-muted dark:text-gray-400">TT = jumlah bed di master saat ini (bukan TT historis). Bisa diubah di kotak Kapasitas TT untuk mengeluarkan bed yang tidak dihitung BOR resmi.</dd>
            </div>
        </dl>
        <p class="mt-3 text-[11px] leading-snug text-muted dark:text-gray-400">
            <strong class="text-body dark:text-gray-300">Keterbatasan cara hitung ini:</strong>
            bukan sensus harian — pasien yang MASIH dirawat belum terhitung sama sekali, dan hari rawat yang melintasi pergantian
            bulan jatuh seluruhnya ke bulan pulangnya. Akibatnya BOR periode yang baru mulai bisa terlalu rendah atau melonjak.
            Jumlahnya ada di kotak Pemeriksaan Data di bawah.
        </p>
    </div>
</div>
