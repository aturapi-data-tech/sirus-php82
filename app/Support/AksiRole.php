<?php

namespace App\Support;

/**
 * SUMBER TUNGGAL daftar role untuk aksi yang dibatasi — DIKLASTER per area.
 *
 * Menggantikan penulisan literal berulang di tiap berkas:
 *
 *   SEBELUM  @hasanyrole('Admin|Manager Umum|Manager Medis')      (6 tempat, mudah melenceng)
 *   SESUDAH  @can('emr.logAktivitas')                             (1 sumber)
 *
 * Konstanta dikonsumsi lewat Gate yang didaftarkan di
 * App\Providers\AppServiceProvider::boot(). Pemakaian:
 *   - Blade  : @can('dokumen.hapus') ... @endcan
 *   - Server : auth()->user()?->can('dokumen.hapus')
 *
 * ATURAN PENTING — SATU KONSTANTA PER MAKSUD, BUKAN PER DAFTAR ROLE.
 * Dua aksi yang kebetulan punya daftar sama TETAP dipisah konstantanya, supaya
 * kelak bisa diberi role berbeda tanpa menyentuh kode pemanggil. Contoh nyata:
 * DOKUMEN_HAPUS dan EMR_LOG_AKTIVITAS sekarang isinya sama, tapi yang pertama
 * aksi destruktif dan yang kedua hanya melihat audit log — menggabungkannya
 * berarti menaikkan hak lihat log setiap kali kebijakan hapus dilonggarkan.
 *
 * MENAMBAH KLASTER BARU:
 *   1. tambah konstanta di sini (beri prefix area),
 *   2. daftarkan Gate-nya di AppServiceProvider,
 *   3. ganti @hasanyrole literal di blade dengan @can, DAN
 *   4. pastikan guard SERVER di method-nya ikut memakai ->can() — guard blade
 *      saja bisa ditembus karena wire:click memanggil method publik.
 *
 * BELUM diklaster (sengaja): aksi yang daftar role-nya BERBEDA antar jalur/modul
 * — openAdministrasiPasien (6 varian), openModulDokumen (4), openAdministrasi (3),
 * openBerkasBpjs & openRekamMedis (2), batalTransaksi (5). Menyatukannya akan
 * mengubah hak akses secara senyap, jadi perlu keputusan kebijakan lebih dulu.
 */
class AksiRole
{
    /* ─────────────── MODUL DOKUMEN (formulir bertanda tangan) ─────────────── */

    /** Role yang boleh MENGHAPUS entri dokumen (draft maupun terkunci). */
    public const DOKUMEN_HAPUS = ['Admin', 'Manager Umum', 'Manager Medis'];

    /** Role yang boleh MEMBUKA KUNCI (mencabut TTD petugas) entri dokumen. */
    public const DOKUMEN_BUKA_KUNCI = ['Admin', 'Manager Umum', 'Manager Medis', 'Perawat'];

    /* ─────────────────────────────── EMR ─────────────────────────────── */

    /** Melihat Log Aktivitas EMR/Administrasi (jejak audit) — manager ke atas. */
    public const EMR_LOG_AKTIVITAS = ['Admin', 'Manager Umum', 'Manager Medis'];

    /** Cetak e-resep dari layar EMR. */
    public const EMR_CETAK_ERESEP = ['Perawat', 'Dokter', 'Casemix', 'Manager Medis', 'Manager Umum', 'Admin'];

    /* ───────────────────────── BRIDGING EKSTERNAL ───────────────────────── */

    /** Membuka panel kirim iDRG / INA-CBG (casemix). */
    public const IDRG_KIRIM = ['Admin', 'Casemix', 'Tu'];

    /** Membuka panel kirim SATUSEHAT. */
    public const SATUSEHAT_KIRIM = ['Admin', 'Mr'];

    /* ────────────────────────────── TRANSAKSI ────────────────────────────── */

    /** Membatalkan penerimaan barang dari PBF yang sedang dibuka (medis & non-medis). */
    public const TRANSAKSI_BATAL_PENERIMAAN = ['Admin', 'Tu'];

    /** Membuka form Pindah Kamar pasien rawat inap. */
    public const RI_PINDAH_KAMAR = ['Mr', 'Admin', 'Perawat', 'Tu'];
}
