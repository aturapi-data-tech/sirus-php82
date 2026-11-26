<?php

/*
 * Hak akses program "Rujukan Antar RS" — kelompok UGD (urutan & judul: _kelompok.php).
 * Satu halaman melayani rujukan Gawat Darurat & Rawat Inap; ditaruh di UGD karena pintu
 * kedatangan pasien rujukan ada di Pendaftaran UGD.
 * Format & pedoman: App\Support\ProgramAkses, docs/standar-hak-akses.md.
 */

return [
    'nama' => 'Rujukan Antar RS',
    'menu' => [
        // Kotak masuk permintaan rujukan dari RS lain (SRBK/SATUSEHAT). Yang
        // menjawab = petugas yang tahu ketersediaan layanan: admisi ranap & IGD.
        ['group' => 'Rujukan', 'groupOrder' => 14, 'order' => 1, 'route' => 'rujukan.masuk', 'aksi' => ['lihat'], 'title' => 'Persetujuan Rujukan Masuk', 'desc' => 'Setujui / tolak permintaan rujukan Rawat Inap & Gawat Darurat dari RS lain (SATUSEHAT Rujukan)', 'roles' => ['admin', 'manager medis', 'supervisor tu', 'perawat', 'dokter', 'tu'], 'badge' => 'RJK'],
        // Arah sebaliknya: memantau rujukan yang KITA kirim sampai dijawab RS tujuan.
        ['group' => 'Rujukan', 'groupOrder' => 14, 'order' => 2, 'route' => 'rujukan.keluar', 'aksi' => ['lihat'], 'title' => 'Pemantauan Rujukan Keluar', 'desc' => 'Pantau rujukan Rawat Inap & Gawat Darurat yang dikirim ke RS lain — disetujui atau ditolak (SATUSEHAT Rujukan)', 'roles' => ['admin', 'manager medis', 'supervisor tu', 'perawat', 'dokter', 'tu'], 'badge' => 'RJK'],
    ],
];
