@props([
    // Hasil KunjunganRITrait::anomaliDataRI().
    'anomali' => [],
    // Baris TOTAL tabel periode.
    'totals' => [],
    // Baris breakdown bangsal (enrichBangsalIndicators) — untuk uji silang jumlah.
    'bangsal' => [],
    'kapasitasTT' => 0,
])

@php
    $angka = fn($nilai, int $desimal = 0) => number_format((float) $nilai, $desimal);

    $totalKeluar = (int) ($totals['total'] ?? 0);
    $jumlahBangsalKeluar = array_sum(array_column($bangsal, 'total'));
    $jumlahBangsalTT = array_sum(array_column($bangsal, 'tt'));
    $jumlahPenjamin = (int) ($totals['bpjs'] ?? 0) + (int) ($totals['umum'] ?? 0);

    // [judul, nilai, janggal?, keterangan]
    $daftarPeriksa = [
        ['Lama dirawat negatif', $anomali['los_negatif'] ?? 0, ($anomali['los_negatif'] ?? 0) > 0,
            'Tanggal pulang lebih awal dari tanggal masuk. Mengurangi Σ lama dirawat — BOR & ALOS jadi terlalu kecil.'],
        ['Lama dirawat di bawah 1 hari', $anomali['los_kurang_1'] ?? 0, false,
            'Masuk dan keluar kurang dari 24 jam. Sah secara klinis, tetapi bila menumpuk di satu bangsal ALOS-nya jatuh mendekati 0.'],
        ['Lama dirawat di atas 30 hari', $anomali['los_lebih_30'] ?? 0, false,
            'Bisa benar (rawat lama), bisa juga tanggal pulang terlambat diisi. Satu kasus saja menggeser ALOS & BOR bangsal kecil.'],
        ['Tanpa tanggal masuk', $anomali['tanpa_tgl_masuk'] ?? 0, ($anomali['tanpa_tgl_masuk'] ?? 0) > 0,
            'entry_date kosong — lama dirawatnya dihitung 0 hari.'],
        ['Status bukan P padahal sudah pulang', $anomali['status_lain'] ?? 0, ($anomali['status_lain'] ?? 0) > 0,
            'Punya exit_date tetapi ri_status bukan P (Pulang) dan bukan F (Batal). Tetap ikut dihitung sebagai pasien keluar.'],
        ['Batal tetapi bertanggal pulang', $anomali['batal_berexit'] ?? 0, false,
            'ri_status F dengan exit_date terisi. TIDAK ikut dihitung di laporan ini.'],
        ['Kamar tanpa bangsal', $anomali['tanpa_bangsal'] ?? 0, ($anomali['tanpa_bangsal'] ?? 0) > 0,
            'room_id kosong atau kamarnya belum dipetakan ke bangsal — masuk baris "(Tanpa Bangsal)" dan tidak punya TT.'],
        ['Masih dirawat saat ini', $anomali['masih_dirawat'] ?? 0, false,
            'Belum punya tanggal pulang, jadi belum terhitung di periode mana pun. Hari rawat berjalan: ' . $angka($anomali['masih_dirawat_hari'] ?? 0, 1) . ' hari.'],
    ];

    $ujiSilang = [
        ['BPJS + UMUM = Total keluar', $angka($jumlahPenjamin) . ' vs ' . $angka($totalKeluar), $jumlahPenjamin === $totalKeluar,
            'Selisih berarti ada kunjungan dengan klaim_id kosong.'],
        ['Σ pasien keluar per bangsal = Total keluar', $angka($jumlahBangsalKeluar) . ' vs ' . $angka($totalKeluar), $jumlahBangsalKeluar === $totalKeluar,
            'Harus sama: keduanya memakai penyaring yang sama.'],
        ['Σ TT per bangsal = Kapasitas TT', $angka($jumlahBangsalTT) . ' vs ' . $angka($kapasitasTT), $jumlahBangsalTT === (int) $kapasitasTT,
            'Beda berarti ada bed di kamar tanpa bangsal, bangsal tanpa pasien keluar pada periode ini, atau Kapasitas TT sedang diubah manual.'],
    ];
@endphp

<div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
    <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
        <h3 class="text-sm font-semibold text-body dark:text-gray-200">
            Pemeriksaan Data
            <span class="ml-2 text-xs font-normal text-muted">data janggal pada periode terpilih — angka kuning perlu dicek ke sumbernya</span>
        </h3>
    </div>

    <div class="grid grid-cols-1 gap-x-8 lg:grid-cols-2">
        <table class="min-w-full text-xs">
            <tbody>
                @foreach ($daftarPeriksa as [$judul, $nilai, $janggal, $keterangan])
                    <tr class="{{ $loop->first ? '' : 'border-t border-hairline-soft dark:border-gray-800' }}">
                        <td class="px-4 py-2 align-top">
                            <div class="font-medium text-ink dark:text-gray-100">{{ $judul }}</div>
                            <div class="text-muted dark:text-gray-400">{{ $keterangan }}</div>
                        </td>
                        <td class="w-20 px-4 py-2 text-right align-top tabular-nums text-base font-bold {{ $janggal ? 'text-amber-600 dark:text-amber-400' : ((int) $nilai > 0 ? 'text-ink dark:text-gray-100' : 'text-muted-soft') }}">
                            {{ $angka($nilai) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="min-w-full text-xs self-start">
            <tbody>
                @foreach ($ujiSilang as [$judul, $nilai, $cocok, $keterangan])
                    <tr class="{{ $loop->first ? '' : 'border-t border-hairline-soft dark:border-gray-800' }}">
                        <td class="px-4 py-2 align-top">
                            <div class="font-medium text-ink dark:text-gray-100">Uji silang: {{ $judul }}</div>
                            <div class="text-muted dark:text-gray-400">{{ $keterangan }}</div>
                        </td>
                        <td class="px-4 py-2 text-right align-top whitespace-nowrap">
                            <div class="tabular-nums text-ink dark:text-gray-100">{{ $nilai }}</div>
                            <div class="font-semibold {{ $cocok ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">{{ $cocok ? 'cocok' : 'BEDA' }}</div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
