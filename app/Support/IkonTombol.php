<?php

namespace App\Support;

/**
 * SUMBER TUNGGAL ikon tombol berteks (keputusan user 2026-11-17).
 *
 * Satu AKSI = satu IKON + satu KOMPONEN TOMBOL di seluruh project. Pakai lewat komponen:
 *   <x-primary-button ...><x-ikon nama="simpan" /> Simpan</x-primary-button>
 *
 * Peta diturunkan dari pemindaian 1.648 tombol (2026-11-17): untuk tiap aksi dipilih ikon
 * yang PALING BANYAK sudah dipakai, kecuali Simpan = disket (keputusan user).
 * Katalog visual + label yang dipetakan tampil di /panduan-dev (bagian Komponen → Ikon Tombol)
 * dan dirinci di docs/standar-komponen-tombol.md § Ikon Tombol.
 *
 * ATURAN MENAMBAH:
 *   - Aksi baru yang belum ada di peta → TAMBAH entri di sini, jangan menulis <svg> inline di tombol.
 *   - Nama entri = kata kerja aksi, bahasa Indonesia, kebab-case, ditulis penuh.
 *   - Satu entri = satu makna. Dua aksi beda makna jangan berbagi nama walau ikonnya mirip
 *     (contoh: `batal` = tutup form/modal tanpa menyimpan, `batalkan` = membatalkan transaksi,
 *     `hapus` = menghapus data).
 *   - `tombol` = komponen tombol baku untuk aksi itu (standar pemakaian & warna, 2026-11-17).
 *     Prinsip: komponen ditentukan MAKNA aksi; untuk aksi positif, aksi UTAMA di panel = solid,
 *     PENDAMPING = x-secondary-button. Menulis ke BPJS = x-info-button (biru), ke SATUSEHAT = teal.
 *   - `paths` = atribut d <path> (outline, stroke currentColor, viewBox 0 0 24 24).
 *
 * Komponen khusus yang SUDAH berikon tetap dipakai apa adanya (jangan diganti x-ikon):
 *   x-hapus-button, x-cetak-button, x-lihat-button, x-action-edit, x-now-button, x-toolbar-refresh-reset.
 * Entri `cetak` hanya untuk tombol cetak khusus yang tak bisa memakai x-cetak-button (print agent etiket).
 */
final class IkonTombol
{
    public const PETA = [
        'simpan' => [
            'tombol' => 'x-primary-button; perlu konfirmasi → x-confirm-button variant="primary"',
            'label' => 'Simpan, Simpan Perubahan, Simpan Koreksi, Simpan & Posting, Simpan Draft',
            'paths' => ['M17 21v-8H7v8M7 3v5h8M5 3h11l4 4v12a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z'],
        ],
        'batal' => [
            'tombol' => 'x-secondary-button',
            'label' => 'Batal, Tutup, Batal Edit, Tidak — menutup form/modal TANPA menyimpan',
            'paths' => ['M6 18L18 6M6 6l12 12'],
        ],
        'tambah' => [
            'tombol' => 'toolbar daftar (buka form baru) → x-primary-button; tambah baris/sub-item di dalam form → x-secondary-button',
            'label' => 'Tambah …, Buat, Order, Isi, Pendaftaran baru (tanpa tanda "+" di teks)',
            'paths' => ['M12 4v16m8-8H4'],
        ],
        'ubah' => [
            'tombol' => 'x-secondary-button; di baris tabel → x-action-edit',
            'label' => 'Ubah, Ubah Data, Edit, Ganti, Koreksi (di baris tabel pakai x-action-edit)',
            'paths' => ['M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z'],
        ],
        'hapus' => [
            'tombol' => 'x-confirm-button variant="danger"; tombol kecil di dalam tabel/form → variant="danger-soft"; di baris tabel → x-hapus-button',
            'label' => 'Hapus data (di baris tabel pakai x-hapus-button)',
            'paths' => ['M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0'],
        ],
        'batalkan' => [
            'tombol' => 'x-confirm-button variant="danger"',
            'label' => 'Batalkan transaksi/order/SEP, Batal Inap, Unmount — membatalkan sesuatu yang sudah tercatat',
            'paths' => ['M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636'],
        ],
        'kembalikan' => [
            'tombol' => 'x-secondary-button; bila mengubah data tersimpan → x-confirm-button variant="warning-soft"',
            'label' => 'Kembalikan ke Antrian, Kosongkan Form, Reset, Bersihkan, Batal / Entri Baru',
            'paths' => ['M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3'],
        ],
        'kembali' => [
            'tombol' => 'x-secondary-button',
            'label' => 'Kembali ke Daftar, Kembali ke Entri Baru — navigasi mundur',
            'paths' => ['M10 19l-7-7m0 0l7-7m-7 7h18'],
        ],
        'muat-ulang' => [
            'tombol' => 'x-secondary-button; aksi utama satu-satunya di panel → x-primary-button',
            'label' => 'Muat Ulang, Tarik Data, Sinkron, Replace, Cek Ulang',
            'paths' => ['M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'],
        ],
        'lihat' => [
            'tombol' => 'x-outline-button (di baris tabel → x-lihat-button)',
            'label' => 'Lihat, Lihat Hasil, Lihat Foto, Preview',
            'paths' => [
                'M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z',
                'M15 12a3 3 0 11-6 0 3 3 0 016 0z',
            ],
        ],
        'cari' => [
            'tombol' => 'x-outline-button',
            'label' => 'Cari, Cek (lookup ke master/BPJS)',
            'paths' => ['M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'],
        ],
        'kirim' => [
            'tombol' => 'ke BPJS → x-info-button; ke SATUSEHAT → x-primary-button teal; lainnya → x-primary-button',
            'label' => 'Kirim ke BPJS/SATUSEHAT/SISRUTE/DINKESTA, Post, TaskId',
            'paths' => ['M12 19l9 2-9-18-9 18 9-2zm0 0v-8'],
        ],
        'unggah' => [
            'tombol' => 'x-primary-button (pendamping → x-secondary-button)',
            'label' => 'Upload, Unggah Berkas, Upload Penunjang',
            'paths' => ['M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12'],
        ],
        'unduh' => [
            'tombol' => 'x-outline-button',
            'label' => 'Unduh, Unduh Excel, Download',
            'paths' => ['M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4'],
        ],
        'setujui' => [
            'tombol' => 'aksi utama panel → x-primary-button (menulis ke BPJS → x-info-button, ke SATUSEHAT → teal); pendamping → x-secondary-button',
            'label' => 'Selesai, Konfirmasi, Review, Setujui, Telaah, Saya Mengerti, Daftarkan',
            'paths' => ['M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ],
        'ttd' => [
            'tombol' => 'x-primary-button',
            'label' => 'TTD, TTD-E, Tanda Tangan',
            'paths' => ['M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z'],
        ],
        'kunci' => [
            'tombol' => 'x-confirm-button variant="primary"',
            'label' => 'Kunci',
            'paths' => ['M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
        ],
        'buka-kunci' => [
            'tombol' => 'x-confirm-button variant="warning-soft"',
            'label' => 'Buka Kunci',
            'paths' => ['M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z'],
        ],
        'proses' => [
            'tombol' => 'aksi utama panel → x-primary-button (grouping/klaim BPJS → x-info-button); pembantu isian → x-outline-button',
            'label' => 'Proses, Generate, Gabung jadi 1 PDF, Hitung',
            'paths' => ['M13 10V3L4 14h7v7l9-11h-7z'],
        ],
        'hentikan' => [
            'tombol' => 'x-danger-button',
            'label' => 'Hentikan, Stop, Kill Session',
            'paths' => ['M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9 9h6v6H9z'],
        ],
        'dokumen' => [
            'tombol' => 'toolbar EMR → x-primary-button !bg-indigo-600 (palet semantik); lainnya → x-outline-button',
            'label' => 'Modul Dokumen, E-Resep, Kelola, Rekam Medis (buka dokumen/form)',
            'paths' => ['M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ],
        'riwayat' => [
            'tombol' => 'x-outline-button',
            'label' => 'Log, Log Aktivitas, Riwayat',
            'paths' => ['M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
        ],
        'pembayaran' => [
            'tombol' => 'toolbar EMR & antrian → ungu (x-primary-button !bg-purple-600; di antrian x-secondary-button !bg-purple-600) — keputusan user 2026-11-17',
            'label' => 'Administrasi, Bayar, Kasir',
            'paths' => ['M2 8h20v12a1 1 0 01-1 1H3a1 1 0 01-1-1V8zm0 0V6a1 1 0 011-1h18a1 1 0 011 1v2M12 14a2 2 0 100-4 2 2 0 000 4z'],
        ],
        'cetak' => [
            'tombol' => 'x-cetak-button (baku, sudah berikon); cetak khusus yang tak bisa memakainya (print agent etiket) → x-secondary-button',
            'label' => 'Print Etiket, Cetak langsung ke printer (tombol cetak biasa pakai x-cetak-button)',
            'paths' => ['M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5z'],
        ],
        'pindah' => [
            'tombol' => 'toolbar EMR → x-primary-button !bg-amber-500 (palet: ubah/transfer reversible); lainnya → x-secondary-button',
            'label' => 'Pindah Kamar, Transfer ke UGD/RI (memindahkan pasien/biaya ke tempat lain)',
            'paths' => ['M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4'],
        ],
        'waktu' => [
            'tombol' => 'x-now-button (isian tanggal); lainnya → x-secondary-button',
            'label' => 'Jam Sekarang, Hari Ini (isian tanggal pakai x-now-button)',
            'paths' => ['M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        ],
    ];

    /** Daftar path untuk satu nama ikon; null bila nama tidak dikenal. */
    public static function paths(string $nama): ?array
    {
        return self::PETA[$nama]['paths'] ?? null;
    }
}
