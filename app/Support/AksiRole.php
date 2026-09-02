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

    /**
     * Role PENUNJANG di layar Rawat Inap — akses SEMPIT, bukan PPA.
     *
     * Boleh MELIHAT konteks klinis order (Pengkajian Perawat/Dokter — read-only,
     * Pemeriksaan, Riwayat), MENULIS catatan di CPPT & SBAR pada kolom profesi
     * 'Penunjang', serta MENGISI dua dokumen yang jadi tugas mereka saat pasien
     * diantar ke unit penunjang: Edukasi Terintegrasi & Form Pindah Antar Ruang.
     * Selebihnya tertutup — asuhan keperawatan, observasi, penilaian, diagnosa,
     * perencanaan, SKDP, dan consent/dokumen bedah-kebidanan.
     *
     * Konstanta ini dipakai dua arah, dan itu disengaja:
     *   - membuka pintu  : menu Daftar RI, tombol Rekam Medis & Modul Dokumen,
     *   - menutup pintu  : daftar tab EMR + Modul Dokumen dipangkas seperlunya.
     * Juga jadi acuan User::profesiKlinis() memetakan role penunjang ke profesi
     * 'Penunjang' — nama tab profesi di CPPT/SBAR. Menambah role penunjang baru
     * di sini otomatis ikut ketiga-tiganya, tidak bisa kelupaan sebelah.
     */
    public const EMR_PENUNJANG_LIHAT = ['Laboratorium', 'Radiologi'];

    /**
     * Mengisi Rekonsiliasi Obat lewat titik-3 Daftar RI / Pelayanan UGD.
     *
     * Pintu ini milik FARMASI: apoteker mendata obat yang dibawa pasien tanpa
     * harus membuka EMR (Anamnesa UGD / Pengkajian Dokter RI) yang bukan
     * wewenangnya. Karena itu daftarnya SENGAJA tidak sama dengan daftar akses
     * EMR — Perawat & Dokter tetap mengisi lewat form EMR-nya masing-masing,
     * bukan lewat pintu ini.
     *
     * Manager Umum & Manager Medis ikut, mengikuti pola klaster lain di berkas
     * ini (DOKUMEN_HAPUS, EMR_LOG_AKTIVITAS) — manager punya akses lintas unit.
     */
    public const REKONSILIASI_OBAT = ['Admin', 'Apoteker', 'Manager Umum', 'Manager Medis'];

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

    /* ─────────────────────────────── SISTEM ─────────────────────────────── */

    /**
     * Menghapus lembar Pemantauan Ruang Server (MRMIK 2.2) — KEDUA modelnya,
     * pemantauan suhu maupun pemantauan akses keluar-masuk.
     *
     * Sengaja satu konstanta untuk dua model: maksudnya sama persis ("hapus bukti
     * pemantauan ruang server"), dan tak ada skenario yang membolehkan menghapus
     * salah satunya saja. Kalau kelak ada, pecah jadi dua di sini — pemanggilnya
     * tak perlu ikut berubah karena mereka memanggil Gate, bukan konstanta.
     *
     * Lembar ini bukti akreditasi, jadi hapus dipegang Admin & Manager Umum saja
     * — Unit IT/SIMRS yang mengisinya tidak boleh menghapus catatannya sendiri,
     * aturan yang sama dengan "batal = eskalasi ke atasan" di Lab & Kamar Operasi.
     */
    public const SISTEM_PEMANTAUAN_RUANG_SERVER_HAPUS = ['Admin', 'Manager Umum'];

    /**
     * Membuka kunci lembar Pemantauan Ruang Server (mencabut TTD) — kedua model.
     *
     * Dipisah dari HAPUS walau daftarnya kebetulan sama: buka kunci mengembalikan
     * lembar jadi draft, hapus melenyapkannya. Lihat aturan "satu konstanta per
     * maksud" di kepala berkas ini.
     */
    public const SISTEM_PEMANTAUAN_RUANG_SERVER_BUKA_KUNCI = ['Admin', 'Manager Umum'];

    /* ────────────────────────────── DOWN TIME ────────────────────────────── */

    /**
     * Menghapus laporan DT-01 Down Time SIMRS (MRMIK 13.1).
     *
     * Laporan waktu henti adalah bahan evaluasi ke pimpinan RS dan bukti
     * akreditasi, jadi menghapusnya bukan wewenang unit yang menulisnya.
     */
    public const DOWNTIME_PELAPORAN_HAPUS = ['Admin', 'Manager Umum'];

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
