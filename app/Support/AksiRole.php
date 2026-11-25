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
 * MENAMBAH AKSI KHUSUS BARU (lihat docs/standar-hak-akses.md):
 *   1. tambah konstanta di sini (beri prefix area) = role CENTANG AWAL,
 *   2. daftarkan di KHUSUS (nama Gate, area, label) — Gate terdaftar otomatis
 *      di AppServiceProvider dan muncul di kolom "Khusus" Role Control,
 *   3. pakai @can('nama') di blade, DAN guard SERVER #[Authorize('nama')] /
 *      ->can() di method-nya — guard blade saja bisa ditembus karena wire:click
 *      memanggil method publik,
 *   4. jalankan `php artisan akses:sinkron` (lokal & produksi).
 * Setelah sinkron, hak sebenarnya = centang di Role Control; konstanta tinggal cadangan.
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
    public const DOKUMEN_BUKA_KUNCI = ['Admin', 'Manager Umum', 'Manager Medis'];

    /**
     * Role yang boleh menandatangani ATAS NAMA pejabat yang semestinya (sistem perwakilan)
     * — dipakai Resume Medis RI: stempel tetap DPJP Utama dari Leveling Dokter, tetapi
     * user yang menekan tombol ikut tersimpan di stempel (`diwakilkanOleh`) & log RI.
     * Dipisah dari DOKUMEN_BUKA_KUNCI walau isinya kini sama: menandatangani atas nama
     * orang lain adalah hak yang berbeda dari mencabut TTD.
     */
    public const DOKUMEN_TTD_PERWAKILAN = ['Admin', 'Manager Umum', 'Manager Medis'];

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

    /* ───────────────────────────── PENUNJANG ───────────────────────────── */

    /**
     * Melihat & mencetak HASIL penunjang pasien: hasil Laboratorium (lihat + cetak),
     * Hasil Bacaan & Foto Radiologi, dan PDF Lab Luar.
     *
     * Komponen tampilannya dipakai bersama di banyak layar — tab Hasil Penunjang EMR
     * RJ/UGD/RI, Cetak Rekam Medis, Berkas BPJS, layar Laborat/Radiologi — jadi
     * daftar ini harus memuat SEMUA pembaca sah di layar-layar itu. Dulu tiap
     * komponen menulis daftarnya sendiri (Lab ≠ Radiologi, Lab Luar terbuka) dan
     * Casemix/Tu — pemakai Berkas BPJS — justru tak melihat tombolnya.
     */
    public const PENUNJANG_LIHAT_HASIL = [
        'Admin', 'Dokter', 'Perawat', 'Apoteker', 'Gizi', 'Laboratorium', 'Radiologi',
        'Supervisor Penunjang', 'Casemix', 'Tu', 'Mr', 'Manager Umum', 'Manager Medis',
    ];

    /* ───────────────────────── BRIDGING EKSTERNAL ───────────────────────── */

    /** Membuka panel kirim iDRG / INA-CBG (casemix). */
    public const IDRG_KIRIM = ['Admin', 'Casemix', 'Tu'];

    /** Membuka panel kirim SATUSEHAT. */
    public const SATUSEHAT_KIRIM = ['Admin', 'Mr'];

    /**
     * Membuka & menyimpan layar Coding SATUSEHAT (EMR RJ FHIR) — kode SNOMED dkk diisi
     * SESUDAH pelayanan, terpisah dari form EMR klinis. Dokter, Perawat, MR, manager ke
     * atas (keputusan user 2026-10-28). Dipisah dari SATUSEHAT_KIRIM: mengisi kode ≠ mengirim.
     */
    public const EMR_CODING_FHIR = ['Dokter', 'Perawat', 'Mr', 'Manager Medis', 'Manager Umum', 'Admin'];

    /* ────────────────────────────── TRANSAKSI ────────────────────────────── */

    /** Membatalkan penerimaan barang dari PBF yang sedang dibuka (medis & non-medis). */
    public const TRANSAKSI_BATAL_PENERIMAAN = ['Admin', 'Tu'];

    /**
     * Ubah Data Kunjungan RJ dari Antrian Kasir — koreksi pasien, tanggal, shift & klaim selama
     * pasien masih dalam pelayanan (di form Daftar RJ field itu terkunci di mode edit).
     * Daftar awal = role yang membuka Administrasi di Antrian Kasir RJ.
     */
    public const RJ_UBAH_DATA_KUNJUNGAN = ['Admin', 'Tu', 'Manager Umum', 'Supervisor Tu'];

    /**
     * Kembalikan kunjungan RJ yang Batal (rj_status 'F') ke antrian kasir (Aktif 'A'), dari
     * Antrian Kasir RJ. Sementara manager ke atas saja (keputusan user 2026-10-31); tiap
     * pemakaian tercatat di log admin kunjungan.
     */
    public const RJ_KEMBALIKAN_BATAL = ['Admin', 'Manager Umum', 'Manager Medis'];

    /**
     * Lihat modal Detail Kunjungan di grid Pelayanan RJ (⚡info-kunjungan-rj) — ringkasan Pendaftaran,
     * EMR & Administrasi termasuk rincian biaya. Manager ke atas saja (keputusan user 2026-11-04).
     */
    public const RJ_DETAIL_KUNJUNGAN = ['Admin', 'Manager Umum', 'Manager Medis'];

    /**
     * Buka Daftar Surat Kontrol BPJS (form SKDP RJ & RI) — modal berisi HAPUS surat kontrol di VClaim
     * (permanen). Admin & manager ke atas saja (keputusan user 2026-11-03).
     */
    public const BPJS_HAPUS_SURAT_KONTROL = ['Admin', 'Manager Umum', 'Manager Medis'];

    /** Ubah Data Kunjungan UGD dari Antrian Kasir UGD — sama maksudnya dengan RJ, jalur terpisah. */
    public const UGD_UBAH_DATA_KUNJUNGAN = ['Admin', 'Tu', 'Manager Umum', 'Supervisor Tu'];

    /**
     * Ubah Data Kunjungan RI (KLAIM saja) dari menu titik-3 Daftar Kasir RI — kasir RI (Tu) tak punya
     * akses "Pendaftaran Ubah" (Mr/Admin/Supervisor Tu), jadi koreksi klaim lewat jalur ini.
     */
    public const RI_UBAH_DATA_KUNJUNGAN = ['Admin', 'Tu', 'Manager Umum', 'Supervisor Tu'];

    /**
     * Ubah tanggal & shift RESEP RI (imtxn_slshdrs) dari Resep Inap (kasir, antrian-kasir-ri) — hanya selama resep
     * masih Antrian (belum diproses kasir). Daftar = role yang membuka Administrasi kasir resep RI.
     */
    public const RI_UBAH_DATA_RESEP = ['Admin', 'Tu', 'Manager Umum', 'Supervisor Tu'];

    /** Membuka form Pindah Kamar pasien rawat inap. */
    public const RI_PINDAH_KAMAR = ['Mr', 'Admin', 'Perawat', 'Tu'];

    /**
     * Cek Jumlah Record & Reset per tab Administrasi RI (menu titik-3 Daftar RI).
     * Manager ke atas: reset menghapus SEMUA baris satu tab sekaligus, dipakai membereskan
     * data anomali (mis. paket ber-Qty salah ketik) — petugas entri tidak boleh memakainya.
     */
    public const RI_RESET_ADMINISTRASI = ['Admin', 'Manager Umum', 'Manager Medis'];

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

    /**
     * Koreksi (Edit) transaksi Penerimaan & Pengeluaran Kas TU (rstxn_tucashds / rstxn_tucashks).
     * Manager ke atas saja (keputusan user 2026-11-17) — entri baru tetap boleh petugas kas.
     */
    public const KAS_TU_KOREKSI = ['Admin', 'Manager Umum', 'Manager Medis'];

    /** Hapus transaksi Penerimaan & Pengeluaran Kas TU — manager ke atas (keputusan user 2026-11-17). */
    public const KAS_TU_HAPUS = ['Admin', 'Manager Umum', 'Manager Medis'];

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
     *   kamar operasi  isAllowedBatalOk() -> ['Admin','Supervisor Penunjang','Supervisor Tu']
     *                  (KamarOperasiTrait; Supervisor Tu = atasan Tu yang ikut entri OK)
     * Aturannya "batal = eskalasi ke atasan": operator Lab/OK tidak boleh
     * membatalkan pekerjaannya sendiri. Jangan disamakan dengan daftar akses
     * modulnya (yang memuat Laboratorium/Perawat) — itu justru melonggarkan.
     */

    /* ───────────────────── KASIR & APOTEK: POSTING / BATAL ───────────────────── */
    /*
     * Disalin persis dari @hasanyrole / hasAnyRole lama di komponen kasir (T3, 2026-11-25).
     * Batal TIDAK memakai kolom Hapus halaman: batal membalik jurnal/stok/status dan
     * sering eskalasi ke atasan — kebijakannya beda dari menghapus satu baris data.
     */
    public const KASIR_RJ_POSTING = ['Admin', 'Tu'];
    public const KASIR_RJ_BATAL = ['Admin', 'Tu', 'Perawat', 'Manager Umum', 'Supervisor Tu'];
    public const KASIR_RJ_BATAL_TRANSFER_UGD = ['Admin', 'Tu', 'Perawat', 'Manager Umum', 'Supervisor Tu'];
    public const KASIR_RJ_BATAL_KUNJUNGAN = ['Admin', 'Supervisor Tu'];
    public const KASIR_UGD_POSTING = ['Admin', 'Tu'];
    public const KASIR_UGD_BATAL = ['Admin', 'Tu', 'Perawat', 'Manager Umum', 'Supervisor Tu'];
    public const KASIR_UGD_BATAL_TRANSFER_RI = ['Admin', 'Tu', 'Perawat', 'Manager Umum', 'Supervisor Tu'];
    public const KASIR_UGD_BATAL_KUNJUNGAN = ['Admin', 'Supervisor Tu'];
    public const KASIR_RI_POSTING = ['Admin', 'Tu'];
    /**
     * Batal Transaksi Pulang RI. Dulu tombol = Admin|Supervisor Tu, server = Admin|Tu (tidak
     * sama). Diputuskan user 2026-11-25: Admin + Supervisor Tu (SEMENTARA — role akan
     * disesuaikan lagi).
     */
    public const KASIR_RI_BATAL = ['Admin', 'Supervisor Tu'];
    public const KASIR_RI_BATAL_INAP = ['Admin', 'Supervisor Tu'];
    public const KASIR_RESEP_RI_POSTING = ['Admin', 'Tu', 'Manager Umum', 'Supervisor Tu'];
    public const KASIR_RESEP_RI_BATAL = ['Admin', 'Manager Umum', 'Supervisor Tu'];
    public const KASIR_RESEP_RI_UBAH_JASA = ['Admin', 'Tu', 'Manager Umum', 'Supervisor Tu'];
    public const APOTEK_RESEP_RI_POSTING = ['Apoteker', 'Admin', 'Tu'];
    public const APOTEK_RESEP_RI_BATAL = ['Apoteker', 'Admin', 'Tu'];
    public const APOTEK_RESEP_RI_UBAH_JASA = ['Admin', 'Tu'];

    /* ─────────────────────────── PIUTANG (T4) ─────────────────────────── */
    /*
     * Komponen pembayaran-piutang-actions dipakai DUA halaman (Pelunasan Klaim BPJS & Piutang
     * Per Pasien), jadi haknya aksi khusus, bukan `{route}.{aksi}`.
     */
    /** Mencatat pembayaran/pelunasan piutang — dulu cukup bisa membuka halaman (role menu). */
    public const PIUTANG_BAYAR = ['Admin', 'Manager Umum', 'Supervisor Tu', 'Tu'];
    /** Hapus pembayaran piutang yang salah — dulu ROLE_HAPUS_BAYAR (aturan tanggal/keterangan tetap di kode). */
    public const PIUTANG_HAPUS_BAYAR = ['Admin', 'Supervisor Tu'];

    /* ─────────────────────── CPPT & SBAR RAWAT INAP ─────────────────────── */
    /**
     * Aturan PEMILIK entri (edit = penulisnya) dan DPJP (review = DPJP Utama) tetap di kode
     * modul — bukan role. Yang di sini hanya PENGECUALIAN-nya, dulu tertulis `hasRole('Admin')`.
     */
    /** Hapus entri CPPT — manager ke atas, walau bukan pemilik entri. */
    public const RI_CPPT_HAPUS = ['Admin', 'Manager Umum', 'Manager Medis'];
    /** Edit entri CPPT milik petugas lain. */
    public const RI_CPPT_UBAH_SEMUA = ['Admin'];
    /** Copy entri CPPT dari profesi lain. */
    public const RI_CPPT_COPY_SEMUA = ['Admin'];
    /** Review/TTD CPPT walau bukan DPJP Utama pasien. */
    public const RI_CPPT_REVIEW_SEMUA = ['Admin'];
    /** Hapus entri SBAR. */
    public const RI_SBAR_HAPUS = ['Admin', 'Manager Umum', 'Manager Medis', 'Supervisor Penunjang', 'Supervisor Tu', 'Mr', 'Casemix'];
    /** Edit entri SBAR milik petugas lain. */
    public const RI_SBAR_UBAH_SEMUA = ['Admin'];
    /** Copy entri SBAR dari profesi lain. */
    public const RI_SBAR_COPY_SEMUA = ['Admin'];
    /** Review/TTD SBAR walau bukan DPJP Utama pasien. */
    public const RI_SBAR_REVIEW_SEMUA = ['Admin'];

    /**
     * KATALOG AKSI KHUSUS — nama Gate => [area, label, roles awal].
     *
     * Tiap entri jadi (1) Gate dengan nama yang sama (AppServiceProvider) dan
     * (2) permission yang dicentang per role di kolom "Khusus" Role Control
     * (dibuat oleh `php artisan akses:sinkron`; roles di sini = centang awalnya).
     * Setelah permission ada, centang Role Control yang menentukan; konstanta di atas
     * tinggal cadangan bila sinkron belum dijalankan.
     *
     * Sengaja TIDAK di sini: EMR_PENUNJANG_LIHAT — bukan hak aksi melainkan penanda
     * profesi (User::profesiKlinis, pemangkas tab EMR); tetap berbasis role.
     */
    public const KHUSUS = [
        'dokumen.hapus' => ['area' => 'Modul Dokumen', 'label' => 'Hapus entri dokumen', 'roles' => self::DOKUMEN_HAPUS],
        'dokumen.bukaKunci' => ['area' => 'Modul Dokumen', 'label' => 'Buka kunci (cabut TTD) dokumen', 'roles' => self::DOKUMEN_BUKA_KUNCI],
        'dokumen.ttdPerwakilan' => ['area' => 'Modul Dokumen', 'label' => 'TTD atas nama pejabat (perwakilan)', 'roles' => self::DOKUMEN_TTD_PERWAKILAN],

        'emr.logAktivitas' => ['area' => 'EMR', 'label' => 'Lihat log aktivitas EMR', 'roles' => self::EMR_LOG_AKTIVITAS],
        'emr.cetakEresep' => ['area' => 'EMR', 'label' => 'Cetak e-resep dari EMR', 'roles' => self::EMR_CETAK_ERESEP],
        'emr.codingFhir' => ['area' => 'EMR', 'label' => 'Coding SATUSEHAT', 'roles' => self::EMR_CODING_FHIR],
        'rekonsiliasi.obat' => ['area' => 'EMR', 'label' => 'Rekonsiliasi obat (titik-3)', 'roles' => self::REKONSILIASI_OBAT],
        'penunjang.lihatHasil' => ['area' => 'EMR', 'label' => 'Lihat & cetak hasil penunjang', 'roles' => self::PENUNJANG_LIHAT_HASIL],

        'ri.cppt.hapus' => ['area' => 'CPPT & SBAR RI', 'label' => 'CPPT: hapus entri', 'roles' => self::RI_CPPT_HAPUS],
        'ri.cppt.ubahSemua' => ['area' => 'CPPT & SBAR RI', 'label' => 'CPPT: edit entri petugas lain', 'roles' => self::RI_CPPT_UBAH_SEMUA],
        'ri.cppt.copySemua' => ['area' => 'CPPT & SBAR RI', 'label' => 'CPPT: copy dari profesi lain', 'roles' => self::RI_CPPT_COPY_SEMUA],
        'ri.cppt.reviewSemua' => ['area' => 'CPPT & SBAR RI', 'label' => 'CPPT: review walau bukan DPJP', 'roles' => self::RI_CPPT_REVIEW_SEMUA],
        'ri.sbar.hapus' => ['area' => 'CPPT & SBAR RI', 'label' => 'SBAR: hapus entri', 'roles' => self::RI_SBAR_HAPUS],
        'ri.sbar.ubahSemua' => ['area' => 'CPPT & SBAR RI', 'label' => 'SBAR: edit entri petugas lain', 'roles' => self::RI_SBAR_UBAH_SEMUA],
        'ri.sbar.copySemua' => ['area' => 'CPPT & SBAR RI', 'label' => 'SBAR: copy dari profesi lain', 'roles' => self::RI_SBAR_COPY_SEMUA],
        'ri.sbar.reviewSemua' => ['area' => 'CPPT & SBAR RI', 'label' => 'SBAR: review walau bukan DPJP', 'roles' => self::RI_SBAR_REVIEW_SEMUA],

        'idrg.kirim' => ['area' => 'Bridging', 'label' => 'Kirim iDRG / INA-CBG', 'roles' => self::IDRG_KIRIM],
        'satusehat.kirim' => ['area' => 'Bridging', 'label' => 'Kirim SATUSEHAT', 'roles' => self::SATUSEHAT_KIRIM],
        'bpjs.hapusSuratKontrol' => ['area' => 'Bridging', 'label' => 'Hapus surat kontrol BPJS', 'roles' => self::BPJS_HAPUS_SURAT_KONTROL],

        'rj.ubahDataKunjungan' => ['area' => 'Transaksi RJ/UGD/RI', 'label' => 'RJ: ubah data kunjungan (kasir)', 'roles' => self::RJ_UBAH_DATA_KUNJUNGAN],
        'rj.kembalikanBatal' => ['area' => 'Transaksi RJ/UGD/RI', 'label' => 'RJ: kembalikan kunjungan batal', 'roles' => self::RJ_KEMBALIKAN_BATAL],
        'rj.detailKunjungan' => ['area' => 'Transaksi RJ/UGD/RI', 'label' => 'RJ: lihat detail kunjungan', 'roles' => self::RJ_DETAIL_KUNJUNGAN],
        'ugd.ubahDataKunjungan' => ['area' => 'Transaksi RJ/UGD/RI', 'label' => 'UGD: ubah data kunjungan (kasir)', 'roles' => self::UGD_UBAH_DATA_KUNJUNGAN],
        'ri.ubahDataKunjungan' => ['area' => 'Transaksi RJ/UGD/RI', 'label' => 'RI: ubah data kunjungan (klaim)', 'roles' => self::RI_UBAH_DATA_KUNJUNGAN],
        'ri.ubahDataResep' => ['area' => 'Transaksi RJ/UGD/RI', 'label' => 'RI: ubah tanggal/shift resep', 'roles' => self::RI_UBAH_DATA_RESEP],
        'ri.pindahKamar' => ['area' => 'Transaksi RJ/UGD/RI', 'label' => 'RI: pindah kamar', 'roles' => self::RI_PINDAH_KAMAR],
        'ri.resetAdministrasi' => ['area' => 'Transaksi RJ/UGD/RI', 'label' => 'RI: reset tab administrasi', 'roles' => self::RI_RESET_ADMINISTRASI],
        'ri.caseManager' => ['area' => 'Transaksi RJ/UGD/RI', 'label' => 'RI: Case Manager (MPP)', 'roles' => self::RI_CASE_MANAGER],
        'transaksi.batalPenerimaan' => ['area' => 'Transaksi RJ/UGD/RI', 'label' => 'Batal penerimaan barang PBF', 'roles' => self::TRANSAKSI_BATAL_PENERIMAAN],

        'kasir.rj.posting' => ['area' => 'Kasir & Apotek', 'label' => 'Kasir RJ: posting pembayaran', 'roles' => self::KASIR_RJ_POSTING],
        'kasir.rj.batal' => ['area' => 'Kasir & Apotek', 'label' => 'Kasir RJ: batal transaksi', 'roles' => self::KASIR_RJ_BATAL],
        'kasir.rj.batalTransferUgd' => ['area' => 'Kasir & Apotek', 'label' => 'Kasir RJ: batal transfer ke UGD', 'roles' => self::KASIR_RJ_BATAL_TRANSFER_UGD],
        'kasir.rj.batalKunjungan' => ['area' => 'Kasir & Apotek', 'label' => 'Kasir RJ: batal kunjungan', 'roles' => self::KASIR_RJ_BATAL_KUNJUNGAN],
        'kasir.ugd.posting' => ['area' => 'Kasir & Apotek', 'label' => 'Kasir UGD: posting pembayaran', 'roles' => self::KASIR_UGD_POSTING],
        'kasir.ugd.batal' => ['area' => 'Kasir & Apotek', 'label' => 'Kasir UGD: batal transaksi', 'roles' => self::KASIR_UGD_BATAL],
        'kasir.ugd.batalTransferRi' => ['area' => 'Kasir & Apotek', 'label' => 'Kasir UGD: batal transfer ke RI', 'roles' => self::KASIR_UGD_BATAL_TRANSFER_RI],
        'kasir.ugd.batalKunjungan' => ['area' => 'Kasir & Apotek', 'label' => 'Kasir UGD: batal kunjungan', 'roles' => self::KASIR_UGD_BATAL_KUNJUNGAN],
        'kasir.ri.posting' => ['area' => 'Kasir & Apotek', 'label' => 'Kasir RI: posting pembayaran pulang', 'roles' => self::KASIR_RI_POSTING],
        'kasir.ri.batal' => ['area' => 'Kasir & Apotek', 'label' => 'Kasir RI: batal transaksi pulang', 'roles' => self::KASIR_RI_BATAL],
        'kasir.ri.batalInap' => ['area' => 'Kasir & Apotek', 'label' => 'Kasir RI: batal inap', 'roles' => self::KASIR_RI_BATAL_INAP],
        'kasir.resepRi.posting' => ['area' => 'Kasir & Apotek', 'label' => 'Kasir Resep Inap: posting', 'roles' => self::KASIR_RESEP_RI_POSTING],
        'kasir.resepRi.batal' => ['area' => 'Kasir & Apotek', 'label' => 'Kasir Resep Inap: batal', 'roles' => self::KASIR_RESEP_RI_BATAL],
        'kasir.resepRi.ubahJasa' => ['area' => 'Kasir & Apotek', 'label' => 'Kasir Resep Inap: ubah jasa karyawan', 'roles' => self::KASIR_RESEP_RI_UBAH_JASA],
        'apotek.resepRi.posting' => ['area' => 'Kasir & Apotek', 'label' => 'Apotek Resep RI: posting', 'roles' => self::APOTEK_RESEP_RI_POSTING],
        'apotek.resepRi.batal' => ['area' => 'Kasir & Apotek', 'label' => 'Apotek Resep RI: batal', 'roles' => self::APOTEK_RESEP_RI_BATAL],
        'apotek.resepRi.ubahJasa' => ['area' => 'Kasir & Apotek', 'label' => 'Apotek Resep RI: ubah jasa karyawan', 'roles' => self::APOTEK_RESEP_RI_UBAH_JASA],

        'piutang.bayar' => ['area' => 'Keuangan', 'label' => 'Piutang: catat pembayaran / pelunasan', 'roles' => self::PIUTANG_BAYAR],
        'piutang.hapusBayar' => ['area' => 'Keuangan', 'label' => 'Piutang: hapus pembayaran salah', 'roles' => self::PIUTANG_HAPUS_BAYAR],
        'kasTu.koreksi' => ['area' => 'Keuangan', 'label' => 'Kas TU: koreksi transaksi', 'roles' => self::KAS_TU_KOREKSI],
        'kasTu.hapus' => ['area' => 'Keuangan', 'label' => 'Kas TU: hapus transaksi', 'roles' => self::KAS_TU_HAPUS],

        'gudang.opnameMedis' => ['area' => 'Gudang', 'label' => 'Stock opname medis', 'roles' => self::GUDANG_OPNAME_MEDIS],
        'gudang.opnameNonMedis' => ['area' => 'Gudang', 'label' => 'Stock opname non-medis', 'roles' => self::GUDANG_OPNAME_NONMEDIS],
        'gudang.transferBatalMedis' => ['area' => 'Gudang', 'label' => 'Batal transfer stok medis', 'roles' => self::GUDANG_TRANSFER_BATAL_MEDIS],
        'gudang.transferBatalNonMedis' => ['area' => 'Gudang', 'label' => 'Batal transfer stok non-medis', 'roles' => self::GUDANG_TRANSFER_BATAL_NONMEDIS],

        // Tombol pembuka modul DI DALAM EMR (komponen dipakai lintas halaman, jadi tidak
        // terikat route). Roles = salinan persis @hasanyrole lama di emr-*-isi (T2, 2026-11-25).
        'rj.emr.bukaScreening' => ['area' => 'EMR RJ', 'label' => 'Buka Screening', 'roles' => ['Admin', 'Dokter', 'Perawat']],
        'rj.emr.bukaAdministrasi' => ['area' => 'EMR RJ', 'label' => 'Buka Administrasi', 'roles' => ['Admin', 'Casemix', 'Perawat']],
        'rj.emr.bukaModulDokumen' => ['area' => 'EMR RJ', 'label' => 'Buka Modul Dokumen', 'roles' => ['Admin', 'Casemix', 'Dokter', 'Mr', 'Perawat']],
        'rj.emr.bukaRekamMedisNasional' => ['area' => 'EMR RJ', 'label' => 'Rekam Medis Nasional (SSRME)', 'roles' => ['Admin', 'Dokter', 'Perawat']],
        'ugd.emr.bukaAdministrasi' => ['area' => 'EMR UGD', 'label' => 'Buka Administrasi', 'roles' => ['Admin', 'Casemix', 'Perawat']],
        'ugd.emr.bukaModulDokumen' => ['area' => 'EMR UGD', 'label' => 'Buka Modul Dokumen', 'roles' => ['Admin', 'Casemix', 'Dokter', 'Mr', 'Perawat']],
        'ugd.emr.bukaRekamMedisNasional' => ['area' => 'EMR UGD', 'label' => 'Rekam Medis Nasional (SSRME)', 'roles' => ['Admin', 'Dokter', 'Perawat']],
        'ri.emr.bukaModulDokumen' => ['area' => 'EMR RI', 'label' => 'Buka Modul Dokumen', 'roles' => ['Admin', 'Apoteker', 'Casemix', 'Dokter', 'Gizi', 'Laboratorium', 'Perawat', 'Radiologi']],
        'ri.emr.bukaAdministrasi' => ['area' => 'EMR RI', 'label' => 'Buka Administrasi', 'roles' => ['Admin', 'Apoteker', 'Casemix', 'Gizi', 'Perawat']],
        'ri.emr.bukaEresep' => ['area' => 'EMR RI', 'label' => 'Buka E-Resep', 'roles' => ['Admin', 'Dokter', 'Perawat']],
        'ri.emr.bukaRingkasanPulang' => ['area' => 'EMR RI', 'label' => 'Ringkasan Pulang', 'roles' => ['Admin', 'Perawat']],
        'ri.emr.bukaRekamMedisNasional' => ['area' => 'EMR RI', 'label' => 'Rekam Medis Nasional (SSRME)', 'roles' => ['Admin', 'Dokter', 'Perawat']],
        'ri.emr.bukaHasilPenunjang' => ['area' => 'EMR RI', 'label' => 'Lihat Hasil Penunjang', 'roles' => ['Admin', 'Dokter', 'Perawat']],

        'downtime.pelaporanHapus' => ['area' => 'Sistem', 'label' => 'Hapus pelaporan down time', 'roles' => self::DOWNTIME_PELAPORAN_HAPUS],
        'sistem.pemantauanRuangServer.hapus' => ['area' => 'Sistem', 'label' => 'Hapus lembar pemantauan ruang server', 'roles' => self::SISTEM_PEMANTAUAN_RUANG_SERVER_HAPUS],
        'sistem.pemantauanRuangServer.bukaKunci' => ['area' => 'Sistem', 'label' => 'Buka kunci pemantauan ruang server', 'roles' => self::SISTEM_PEMANTAUAN_RUANG_SERVER_BUKA_KUNCI],
    ];
}
