@props([
    // Hasil KunjunganRITrait::anomaliDataRI().
    'anomali' => [],
    // Baris TOTAL tabel periode.
    'totals' => [],
    // Baris breakdown bangsal (enrichBangsalIndicators) — untuk uji silang jumlah.
    'bangsal' => [],
    'kapasitasTT' => 0,
    // Σ TT bangsal yang dihitung (parameter TT per bangsal).
    'ttBangsalDihitung' => 0,
    // Batas lama dirawat wajar (hari) yang sedang dipakai, dan bawaannya.
    'batasLos' => 60,
    'batasLosBawaan' => 60,
])

@php
    $angka = fn($nilai, int $desimal = 0) => number_format((float) $nilai, $desimal);

    $totalKeluar = (int) ($totals['total'] ?? 0);
    $keluarDihitung = (int) ($totals['keluar_bor'] ?? 0);
    $luarBangsal = (int) ($totals['luar_bangsal'] ?? 0);
    $anomaliDikeluarkan = (int) ($totals['anomali_dikeluarkan'] ?? 0);
    $jumlahBangsalKeluar = array_sum(array_column($bangsal, 'total'));
    $jumlahBangsalTT = (int) $ttBangsalDihitung;
    $jumlahPenjamin = (int) ($totals['bpjs'] ?? 0) + (int) ($totals['umum'] ?? 0);

    // [judul, nilai, perlakuan ('dikeluarkan' | 'peringatan' | 'info'), keterangan]
    $daftarPeriksa = [
        ['Status bukan P padahal bertanggal pulang', $anomali['status_lain'] ?? 0, 'dikeluarkan',
            'Kasir RI selalu mengisi tanggal pulang dan status P bersamaan, dan batal-bayar mengosongkan keduanya. Yang seperti ini data rusak: di Daftar RI pasiennya masih tampil "Dirawat", tanggal pulangnya tidak bisa dipercaya.'],
        ['Lama dirawat negatif', $anomali['los_negatif'] ?? 0, 'dikeluarkan',
            'Tanggal pulang lebih awal dari tanggal masuk — salah satu tanggalnya keliru.'],
        ['Tanpa tanggal masuk', $anomali['tanpa_tgl_masuk'] ?? 0, 'dikeluarkan',
            'entry_date kosong, lama dirawat tidak bisa dihitung.'],
        ['Lama dirawat di atas ' . $angka($batasLos) . ' hari', $anomali['los_lebih_batas'] ?? 0, 'dikeluarkan',
            'Melewati batas wajar — hampir selalu tanggal pulang terlambat diisi. Satu kasus saja bisa menggeser BOR bangsal kecil. Batasnya bisa diubah di kanan atas kartu ini.'],
        ['Lama dirawat 30 s/d ' . $angka($batasLos) . ' hari', $anomali['los_lebih_30'] ?? 0, 'peringatan',
            'Masih dalam batas, tetap dihitung. Pastikan memang rawat lama, bukan tanggal pulang yang telat diisi.'],
        ['Lama dirawat di bawah 1 hari', $anomali['los_kurang_1'] ?? 0, 'peringatan',
            'Masuk dan keluar kurang dari 24 jam. Sah secara klinis dan tetap dihitung, tetapi bila menumpuk di satu bangsal ALOS-nya jatuh mendekati 0.'],
        ['Kamar tanpa bangsal', $anomali['tanpa_bangsal'] ?? 0, 'peringatan',
            'room_id kosong atau kamarnya belum dipetakan ke bangsal — masuk baris "(Tanpa Bangsal)". Bisa dikeluarkan lewat kartu Parameter TT per Bangsal.'],
        ['Batal tetapi bertanggal pulang', $anomali['batal_berexit'] ?? 0, 'info',
            'ri_status F dengan exit_date terisi. Tidak pernah ikut laporan ini, termasuk jumlah kunjungan.'],
        ['Masih dirawat saat ini', $anomali['masih_dirawat'] ?? 0, 'info',
            'Belum punya tanggal pulang, jadi belum terhitung di periode mana pun. Hari rawat berjalan: ' . $angka($anomali['masih_dirawat_hari'] ?? 0, 1) . ' hari.'],
    ];

    $labelPerlakuan = [
        'dikeluarkan' => ['TIDAK DIHITUNG', 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300'],
        'peringatan' => ['peringatan', 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'],
        'info' => ['info', 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'],
    ];
    $kelasNilai = [
        'dikeluarkan' => 'text-rose-700 dark:text-rose-400',
        'peringatan' => 'text-amber-600 dark:text-amber-400',
        'info' => 'text-ink dark:text-gray-100',
    ];

    $ujiSilang = [
        ['Total = dihitung + luar bangsal + data janggal',
            $angka($totalKeluar) . ' vs ' . $angka($keluarDihitung) . ' + ' . $angka($luarBangsal) . ' + ' . $angka($anomaliDikeluarkan),
            $totalKeluar === $keluarDihitung + $luarBangsal + $anomaliDikeluarkan,
            'Setiap pasien keluar harus jatuh tepat di satu kelompok: ikut hitungan, di bangsal yang tidak dihitung BOR, atau data janggal.'],
        ['BPJS + UMUM = Total keluar', $angka($jumlahPenjamin) . ' vs ' . $angka($totalKeluar), $jumlahPenjamin === $totalKeluar,
            'Selisih berarti ada kunjungan dengan klaim_id kosong.'],
        ['Σ pasien keluar per bangsal = Total keluar', $angka($jumlahBangsalKeluar) . ' vs ' . $angka($totalKeluar), $jumlahBangsalKeluar === $totalKeluar,
            'Harus sama: keduanya menghitung SEMUA pasien keluar, termasuk bangsal yang tidak dihitung BOR dan data janggal.'],
        ['Σ TT bangsal yang dihitung = Kapasitas TT', $angka($jumlahBangsalTT) . ' vs ' . $angka($kapasitasTT), $jumlahBangsalTT === (int) $kapasitasTT,
            'Beda berarti Kapasitas TT total sedang ditimpa manual — BOR total tidak lagi sejalan dengan BOR per bangsal.'],
    ];
@endphp

{{-- Kartu ini berada DI DALAM komponen Livewire pemanggil: wire:model / wire:click di sini
     mengarah ke properti $batasLosHari & method resetBatasLos milik KunjunganRITrait. --}}
<div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3 border-b border-hairline dark:border-gray-700">
        <h3 class="flex-1 min-w-0 text-sm font-semibold text-body dark:text-gray-200">
            Pemeriksaan Data
            <span class="ml-2 text-xs font-normal text-muted">
                <span class="font-semibold text-rose-700 dark:text-rose-400">merah</span> = tidak ikut hitungan indikator ·
                <span class="font-semibold text-amber-700 dark:text-amber-400">kuning</span> = tetap dihitung, perlu dicek
            </span>
        </h3>
        <div class="flex items-center gap-2 text-xs text-body dark:text-gray-300">
            <span>Batas lama dirawat wajar</span>
            <x-text-input type="number" min="1" max="3650" wire:model.live.debounce.600ms="batasLosHari"
                class="!w-24 !py-1 text-right tabular-nums" />
            <span>hari</span>
            @if ((int) $batasLos !== (int) $batasLosBawaan)
                <button type="button" wire:click="resetBatasLos" class="text-blue-600 hover:underline dark:text-blue-400"
                    title="Kembali ke bawaan {{ $batasLosBawaan }} hari">reset</button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-x-8 lg:grid-cols-2">
        <table class="min-w-full text-xs">
            <tbody>
                @foreach ($daftarPeriksa as [$judul, $nilai, $perlakuan, $keterangan])
                    <tr class="{{ $loop->first ? '' : 'border-t border-hairline-soft dark:border-gray-800' }}">
                        <td class="px-4 py-2 align-top">
                            <div class="font-medium text-ink dark:text-gray-100">
                                {{ $judul }}
                                <span class="ml-1 px-2 py-0.5 text-[10px] font-semibold rounded-full {{ $labelPerlakuan[$perlakuan][1] }}">{{ $labelPerlakuan[$perlakuan][0] }}</span>
                            </div>
                            <div class="text-muted dark:text-gray-400">{{ $keterangan }}</div>
                        </td>
                        <td class="w-20 px-4 py-2 text-right align-top tabular-nums text-base font-bold {{ (int) $nilai > 0 ? $kelasNilai[$perlakuan] : 'text-muted-soft' }}">
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
    <div class="px-4 py-2 text-[10px] leading-snug text-muted dark:text-gray-500 border-t border-hairline-soft dark:border-gray-800">
        Data bertanda <strong>TIDAK DIHITUNG</strong> dikeluarkan dari Σ lama dirawat dan penyebut BOR / ALOS / TOI / BTO, tetapi tetap terhitung di jumlah kunjungan
        (Total, BPJS, UMUM) dan di NDR / GDR. Angka di kartu ini mencakup semua bangsal; kolom "Dikeluarkan" pada tabel hanya menghitung bangsal yang dihitung BOR.
    </div>
</div>
