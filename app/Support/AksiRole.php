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

    /**
     * Mengakses tab Case Manager (Form A & B MPP) di modul dokumen RI.
     *
     * CATATAN — MPP itu JABATAN, bukan role. Yang menjabat MPP biasanya seorang
     * Perawat atau Dokter; jabatannya tidak tersimpan di mana pun saat ini
     * (`users.myuser_profesi` isinya PROFESI: Perawat/Dokter/Apoteker/Gizi, dan
     * 'MPP' di modul Case Manager hanya label yang distempel ke TTD petugas).
     *
     * Karena itu guard di sini memakai ROLE, dan 'MPP' yang dulu ikut ditulis
     * di @hasanyrole DIBUANG: role bernama MPP tidak ada di tabel roles, jadi
     * ia tidak pernah cocok — dead weight yang menyesatkan pembaca.
     *
     * Kalau kelak jabatan MPP mau benar-benar ditegakkan (hanya yang ditunjuk
     * boleh mengisi), jabatannya perlu tempat penyimpanan lebih dulu; ubah
     * cukup di sini + Gate 'ri.caseManager'.
     */
    public const RI_CASE_MANAGER = ['Perawat', 'Admin'];

    /* ─────────────────────────────── GUDANG ─────────────────────────────── */

    /**
     * Stock Opname barang MEDIS (kartu stock obat & apotek) — mengubah saldo awal.
     * Dipisah dari non-medis karena pemiliknya beda unit: obat = Apoteker.
     */
    public const GUDANG_OPNAME_MEDIS = ['Admin', 'Apoteker'];

    /** Stock Opname barang NON-MEDIS — pemiliknya TU, bukan Apoteker. */
    public const GUDANG_OPNAME_NONMEDIS = ['Admin', 'Tu'];

    /**
     * Batalkan / hapus transfer stok — MEDIS (obat).
     * Membalik mutasi stok di Kartu Stock, dan batalFromList() menghapus permanen.
     */
    public const GUDANG_TRANSFER_BATAL_MEDIS = ['Admin', 'Manager Umum', 'Supervisor Tu', 'Gudang Obat', 'Apoteker'];

    /** Batalkan / hapus transfer stok — NON-MEDIS. */
    public const GUDANG_TRANSFER_BATAL_NONMEDIS = ['Admin', 'Manager Umum', 'Supervisor Tu', 'Gudang Non Medis', 'Tu'];

    /*
     * CATATAN — Batal di LABORAT & KAMAR OPERASI sengaja TIDAK diklaster di sini.
     * Keduanya sudah punya guard sendiri yang lebih ketat dan disengaja:
     *   laborat        isAllowedBatal()   -> ['Admin','Supervisor Penunjang']
     *   kamar operasi  isAllowedBatalOk() -> ['Admin','Supervisor Penunjang']
     *                  (KamarOperasiTrait)
     * Aturannya "batal = eskalasi ke atasan": operator Lab/OK tidak boleh
     * membatalkan pekerjaannya sendiri. Jangan disamakan dengan daftar akses
     * modulnya (yang memuat Laboratorium/Perawat) — itu justru melonggarkan.
     */
}
