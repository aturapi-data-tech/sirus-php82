-- database/sql/2026_08_01_table_gajidoctors.sql
-- ===============================================================
-- RSTXN_GAJIDOCTORHDRS — HEADER slip gaji dokter per periode
-- RSTXN_GAJIDOCTORDTLS — DETAIL rincian jasa / potongan / tambahan
-- RSMST_DOCTORS        — ditambah kolom parameter penggajian per dokter
--
-- LATAR:
--   Menggantikan workbook Excel (mis. 0726sp.xlsx) yang memakai 1 sheet per
--   dokter. Hasil bedah 20 sheet tsb: SEMUA dokter memakai satu rumus yang
--   sama, hanya berbeda pada 3 parameter (skema gaji pokok, basis potongan
--   RS, daftar komponen). Karena itu tidak dibuat 20 model, cukup 1 pasang
--   tabel header-detail + beberapa kolom parameter yang menumpang di
--   RSMST_DOCTORS (setup gaji memang sudah tempatnya di master dokter).
--
--   Rumus induk (terverifikasi cocok s/d rupiah pada 18 dari 19 sheet).
--
--   TANDA: potongan disimpan NEGATIF, jasa/tunjangan/tambahan POSITIF. Karena
--   itu seluruh rumus di bawah MENJUMLAH — jangan menulis minus dua kali.
--
--     JASA          = SUM(detail 'J' berkode komponen jasa)
--     TUNJANGAN     = SUM(detail 'J' berkode 'TUNJ %')   <- KENA PAJAK
--     TOTAL GAJI    = JASA + TUNJANGAN + GAJI POKOK           (skema 'A')
--                   = GREATEST(JASA, GAJI POKOK) + TUNJANGAN  (skema 'G')
--                   = JASA + TUNJANGAN                        (skema 'N')
--     POTONGAN RS   = -(potongan_rs_persen % x basis)
--     PPH 21        = -FLOOR(pph21_persen % x (TOTAL GAJI + POTONGAN RS))
--                     default 2,5% = NPPN 50% x tarif 5%
--     GAJI DITERIMA = TOTAL GAJI + POTONGAN RS + PPH21
--                     + SUM(detail 'P') + SUM(detail 'T')
--
--   PEMBULATAN — hanya PPh, dan hanya ke bawah:
--     PPh SELALU dipotong ke rupiah penuh, komponen lain TIDAK. Bukti dari
--     workbook: 1.081.562,5 -> 1.081.562 (SpAN); 286.762,5 -> 286.762 (SpA);
--     932.062,5 -> 932.062 (SpPD Hamdan) — sementara potongan RS di sheet yang
--     sama tetap berdesimal: -4.142.500,20 dan -859.503,60. Tanpa FLOOR ini,
--     separuh dokter akan meleset Rp1 dari slip lama.
--
--   TUNJANGAN — perbedaan yang HARUS dijaga:
--     Tunjangan struktural/fungsional/kehadiran di workbook lama berada DI
--     DALAM penjumlahan pembentuk Total Gaji, jadi ikut menaikkan basis PPh.
--     Sebaliknya 'BONUS'/'RAPEL'/'KOREKSI' berada di kolom sebelah kanan slip
--     dan ditambahkan SETELAH pajak. Keduanya tidak boleh dicampur.
--     CATATAN: pada sheet skema 'G', baris tunjangan kebetulan berada di luar
--     rentang SUM sehingga tidak ikut terhitung. Nilainya nol di semua sheet
--     sehingga tidak bisa dipastikan itu disengaja atau salah rentang; di sini
--     dipilih tetap menambahkan tunjangan. Konfirmasi ke bagian keuangan bila
--     nanti ada dokter garanty fee yang benar-benar bertunjangan.
--
-- KENAPA HEADER-DETAIL:
--   Rancangan awal menaruh 22 komponen jasa sebagai 22 kolom. Diubah jadi
--   detail supaya penambahan komponen baru cukup INSERT baris, bukan ALTER
--   TABLE pada tabel yang sudah berisi data. Hal yang sama berlaku untuk
--   potongan (IDI, arisan, angsuran, BPJS, zariyah, ...) dan tambahan
--   (bonus, rapel, koreksi bulan lalu) — ketiganya daftar yang bisa tumbuh,
--   jadi ketiganya masuk ke tabel detail yang sama dengan pembeda kolom
--   `jenis`.
--
-- PENAMAAN — dua aturan berbeda, jangan dicampur:
--
--   NAMA TABEL & KOLOM KUNCI: ikut konvensi tabel yang sudah ada di database,
--   BUKAN aturan "eja penuh". Pasangan header-detail di sini seragam
--   <prefix>txn_<nama>hdrs / <prefix>txn_<nama>dtls:
--     rstxn_rihdrs / rstxn_ridtls        imtxn_slshdrs / imtxn_slsdtls
--     rstxn_rjhdrs / rstxn_rjdtls        imtxn_trfhdrs / imtxn_trfdtls
--     rstxn_ugdhdrs / rstxn_ugddtls      lbtxn_checkuphdrs / lbtxn_checkupdtls
--   Karena itu: RSTXN_GAJIDOCTORHDRS / RSTXN_GAJIDOCTORDTLS.
--   Kolom kuncinya juga ikut pola tsb — header ber-PK <nama>_no, detail punya
--   nomor urut sendiri <nama>_dtl + FK <nama>_no (bandingkan rstxn_ugddtls
--   yang memakai rjdtl_dtl + rj_no).
--
--   NAMA KOLOM BIASA: ikut skill naming-conventions, singkatan dieja penuh —
--   potongan_ (bukan pot_), jasa_dokter_ (bukan jd_), uang_periksa_ (bukan
--   up_), jumlah_ (bukan jml_), tahun_/bulan_/tanggal_ (bukan thn_/bln_/tgl_),
--   keterangan_ (bukan ket_), radiologi_ (bukan rad_).
--
--   DIPERTAHANKAN karena sudah baku di repo / nama resmi, bukan singkatan
--   buatan sendiri:
--     rj / ri / ugd  — kode modul yang dipakai seluruh repo (rj_no, rihdr_no)
--     rs             — rumah sakit
--     pph21          — nama resmi PPh Pasal 21
--     idi / bpjs     — nama lembaga (Ikatan Dokter Indonesia, BPJS Kesehatan)
--     konsul         — istilah domain yang dipakai apa adanya di seluruh repo
--     visite         — istilah domain (kunjungan dokter ke pasien rawat inap)
--
--   Seluruh identifier dijaga <= 30 karakter agar aman bila database masih
--   Oracle 11g (batas 30; baru longgar jadi 128 sejak 12.2).
--
-- PERIODE — PENTING:
--   Gaji yang dibayarkan bulan ini berasal dari jasa BULAN LALU
--   (mis. jasa Juli 2026 dibayar awal Agustus 2026).
--
--   Periode jasa DAN periode gaji sama-sama disimpan sebagai kolom biasa
--   (VARCHAR2, diisi aplikasi) — bukan virtual column. Supaya keduanya tidak
--   bisa melenceng, hubungan "+1 bulan" dijaga oleh CHECK constraint
--   ck_gajidoctorhdrs_periode di bawah: baris dengan bulan_gaji yang bukan
--   bulan_jasa + 1 akan ditolak database, bukan lolos diam-diam.
--
--   VARCHAR2 untuk periode menuntut ZERO-PADDING konsisten: '07', bukan '7'.
--   Kalau tidak, '7' dan '07' akan jadi dua grup berbeda pada GROUP BY dan
--   urutannya kacau pada ORDER BY. Karena itu bulan dibatasi CHECK ke daftar
--   '01'..'12', dan tahun ke 4 digit angka.
--
-- SUMBER KOMPONEN:
--   RSVIEW_NEWDOCSALARIES (kolom DESC_DOC dipakai apa adanya sebagai
--   RSTXN_GAJIDOCTORDTLS.kode). View itu sudah menghasilkan seluruh
--   komponen yang dipakai Excel + 6 tambahan (UP/JD KLINIK dan pecahan
--   OPERATOR/ANASTESI untuk RJ & UGD yang di Excel belum terpisah).
--
-- SNAPSHOT PARAMETER:
--   Kolom skema_gaji_pokok, nilai_gaji_pokok, potongan_rs_basis,
--   potongan_rs_persen, pph21_persen SENGAJA ikut disimpan di header
--   transaksi, tidak hanya di master. Dengan begitu revisi parameter di
--   master tidak mengubah slip periode lama — pola yang sama dengan
--   clause-versioning di modul dokumen.
--
-- PRA-SYARAT (WAJIB DICEK SEBELUM JALAN):
--   1. Tipe & panjang RSMST_DOCTORS.DR_ID — samakan dengan definisi di bawah:
--        SELECT column_name, data_type, data_length, data_precision
--          FROM user_tab_columns
--         WHERE table_name = 'RSMST_DOCTORS' AND column_name = 'DR_ID';
--   2. RSMST_DOCTORS harus punya PK/UNIQUE pada DR_ID, kalau tidak FK gagal
--      dengan ORA-02270:
--        SELECT c.constraint_name, c.constraint_type, cc.column_name
--          FROM user_constraints c
--          JOIN user_cons_columns cc ON cc.constraint_name = c.constraint_name
--         WHERE c.table_name = 'RSMST_DOCTORS' AND c.constraint_type IN ('P','U');
--      Bila belum ada, JANGAN langsung ADD PRIMARY KEY pada master yang sudah
--      jalan — cek dulu duplikat: SELECT dr_id, COUNT(*) FROM rsmst_doctors
--      GROUP BY dr_id HAVING COUNT(*) > 1;
--
-- CATATAN TIPE & NILAI:
--   - Kode 1 huruf memakai VARCHAR2(1), bukan CHAR(1). CHAR itu blank-padded
--     fixed length; perbandingan CHAR vs VARCHAR2 memakai aturan padding yang
--     berbeda sehingga bind ':x' dari PHP bisa tidak match secara senyap.
--   - Kolom waktu memakai DATE. Di Oracle DATE SUDAH menyimpan tanggal +
--     jam:menit:detik (bukan date-only seperti MySQL), jadi entry_date /
--     update_date / tanggal_bayar sudah merekam waktu. TIMESTAMP hanya perlu
--     kalau butuh pecahan detik atau time zone — di sini tidak.
--   - Kolom nominal di tabel transaksi: DEFAULT 0 NOT NULL.
--   - Kolom tambahan di RSMST_DOCTORS: nullable (alasan di bagian 1), jadi
--     perhitungan gaji WAJIB membungkusnya dengan NVL(). Di Oracle '' = NULL,
--     dan NULL dalam aritmetika membuat seluruh hasil jadi NULL secara senyap.
--
-- LANJUTANNYA: 2026_08_02_alter_gajidoctors_lanjutan.sql menambahkan 4 kolom
--   yang menyusul kemudian — npwp (master), nilai_manual (detail),
--   potongan_rs_aturan & npwp_status (header). Jalankan SETELAH berkas ini.
--
-- JALANKAN: seluruh isi file ini sekali jalan.
-- ROLLBACK: DROP TABLE RSTXN_GAJIDOCTORDTLS PURGE;
--           DROP TABLE RSTXN_GAJIDOCTORHDRS PURGE;
--           ALTER TABLE RSMST_DOCTORS DROP CONSTRAINT ck_doctors_skema_gaji;
--           ALTER TABLE RSMST_DOCTORS DROP CONSTRAINT ck_doctors_potongan;
--           ALTER TABLE RSMST_DOCTORS DROP (
--               skema_gaji_pokok, potongan_rs_basis, potongan_rs_persen,
--               potongan_rs_aturan, pph21_persen,
--               tarif_per_kapita_ri, tarif_per_kapita_rj,
--               tunjangan_struktural, tunjangan_fungsional, tunjangan_hadir,
--               potongan_idi, potongan_arisan, potongan_koperasi,
--               potongan_angsuran, potongan_bpjs, potongan_zariyah);
--           CATATAN: basic_salary JANGAN ikut di-drop — kolom itu sudah ada
--           sebelum perubahan ini dan dipakai form Master Dokter.
-- ===============================================================


-- ---------------------------------------------------------------
-- 1. PARAMETER PER DOKTER — MENUMPANG DI RSMST_DOCTORS
--
--    TIDAK dibuat tabel config terpisah. Relasinya 1:1 dengan dokter, dan
--    RSMST_DOCTORS SUDAH menjadi tempat setup gaji: kolom BASIC_SALARY
--    (label "Gaji Pokok" di /master/dokter) sudah ada dan sudah diedit lewat
--    form Master Dokter. Tabel config terpisah hanya akan menduplikasinya.
--
--    KOLOM GAJI YANG SUDAH ADA — DIPAKAI ULANG, JANGAN DIBUAT KEMBARANNYA:
--      basic_salary        -> gaji pokok / garanty fee (label "Gaji Pokok")
--
--    KOLOM YANG NAMANYA MIRIP TAPI BUKAN URUSAN GAJI — JANGAN DIPAKAI:
--      rs_admin            -> tarif administrasi RS yang DIBEBANKAN KE PASIEN.
--                             Mengalir ke header transaksi (rstxn_rjhdrs.rs_admin,
--                             dst) bersama rj_admin/poli_price/obat/lab/rad/ok.
--                             Ini komponen TAGIHAN, bukan potongan gaji dokter.
--      poli_price,
--      ugd_price,
--      poli_price_bpjs,
--      ugd_price_bpjs      -> tarif uang periksa. Ini INPUT pembentuk jasa
--                             (lewat RSVIEW_NEWDOCSALARIES), bukan parameter
--                             potongan.
--
--    PERLU DIKONFIRMASI SEBELUM DIPAKAI:
--      contribution_status -> label "Status Kontribusi", isi '0'/'1', TIDAK
--                             dipakai di mana pun dalam kode aplikasi (hanya
--                             disimpan & ditampilkan form Master Dokter).
--                             Diduga warisan Oracle Dev 6i. Bisa jadi kolom ini
--                             sudah bermakna "dokter kena potongan RS atau
--                             tidak" — kalau benar, potongan_rs_basis di bawah
--                             cukup diisi turunan dari kolom ini, bukan kolom
--                             baru. TANYAKAN DULU ke pemelihara Oracle Dev 6i
--                             sebelum menjalankan ALTER ini.
--
--    PRA-SYARAT TAMBAHAN:
--      a. Pastikan nama kolom baru belum terpakai:
--           SELECT column_name FROM user_tab_columns
--            WHERE table_name = 'RSMST_DOCTORS'
--            ORDER BY column_id;
--      b. Cek tidak ada INSERT posisional (tanpa daftar kolom) ke
--         RSMST_DOCTORS di sisi Oracle Dev 6i. Di repo ini sudah aman —
--         master-dokter-actions.blade.php memakai insert($payload) dengan
--         nama kolom. Tapi form 6i di luar repo tidak bisa dicek dari sini,
--         dan INSERT posisional akan pecah begitu tabel bertambah kolom
--         berapa pun nullability-nya.
-- ---------------------------------------------------------------
ALTER TABLE RSMST_DOCTORS ADD (
    -- skema gaji pokok: 'A' aditif (jasa + basic_salary)
    --                   'G' garanty fee (GREATEST(jasa, basic_salary))
    --                       -> basic_salary berlaku sebagai jaminan minimum
    --                   'N' tanpa gaji pokok
    skema_gaji_pokok       VARCHAR2(1)    DEFAULT 'A',

    -- basis potongan RS: 'T' total gaji (gaji pokok ikut dipotong)
    --                    'J' jasa saja  (gaji pokok bebas potongan)
    --                    'N' tidak dipotong
    --                    'B' berjenjang per komponen (lihat potongan_rs_aturan)
    potongan_rs_basis      VARCHAR2(1)    DEFAULT 'T',
    potongan_rs_persen     NUMBER(5,2)    DEFAULT 10,

    -- hanya untuk potongan_rs_basis = 'B'. JSON {"UP RJ":10,"JD RJ":50,...}
    -- Oracle di sini tidak mendukung JSON_VALUE -> parse di sisi aplikasi.
    potongan_rs_aturan     VARCHAR2(1000),

    pph21_persen           NUMBER(5,2)    DEFAULT 2.5,   -- NPPN 50% x 5%

    -- model per kapita. 0 / NULL = tidak dipakai.
    --   RI  40.000/pasien  -> di workbook lama berlabel "Konsul pasien total",
    --                         diletakkan pada blok Rawat Inap (SpPK).
    --   RJ  65.000/kunjungan -> berlabel "Uang periksa", blok Rawat Jalan
    --                         (SpKFR).
    -- Labelnya memang berbeda antar dokter; yang menentukan adalah blok tempat
    -- angka itu berada, bukan namanya.
    tarif_per_kapita_ri    NUMBER(12,2)   DEFAULT 0,
    tarif_per_kapita_rj    NUMBER(12,2)   DEFAULT 0,

    -- tunjangan rutin default. MASUK TOTAL GAJI SEBELUM PPH (lihat rumus induk
    -- di kepala berkas) — jangan disamakan dengan BONUS/RAPEL yang diberikan
    -- setelah pajak dan tidak punya kolom master karena berubah tiap bulan.
    tunjangan_struktural   NUMBER(14,2)   DEFAULT 0,
    tunjangan_fungsional   NUMBER(14,2)   DEFAULT 0,
    tunjangan_hadir        NUMBER(14,2)   DEFAULT 0,

    -- potongan rutin default (disalin jadi baris detail saat generate).
    -- Kasbon / "telah diambil" TIDAK di sini: di workbook lama nilainya
    -- dihitung SUMIF atas tabel Pengeluaran per bulan, jadi tempatnya di
    -- detail slip, bukan master.
    potongan_idi           NUMBER(14,2)   DEFAULT 0,
    potongan_arisan        NUMBER(14,2)   DEFAULT 0,
    potongan_koperasi      NUMBER(14,2)   DEFAULT 0,
    potongan_angsuran      NUMBER(14,2)   DEFAULT 0,
    potongan_bpjs          NUMBER(14,2)   DEFAULT 0,
    potongan_zariyah       NUMBER(14,2)   DEFAULT 0
);

-- Kolom baru sengaja NULLABLE (hanya DEFAULT, tanpa NOT NULL): RSMST_DOCTORS
-- adalah master lama yang dipakai bersama Oracle Dev 6i, dan baris lama tidak
-- ikut ter-backfill oleh DEFAULT pada ALTER. Perhitungan gaji WAJIB memakai
-- NVL() atas kolom-kolom ini.

ALTER TABLE RSMST_DOCTORS ADD (
    CONSTRAINT ck_doctors_skema_gaji CHECK (skema_gaji_pokok  IN ('A','G','N')),
    CONSTRAINT ck_doctors_potongan   CHECK (potongan_rs_basis IN ('T','J','N','B'))
);


-- ---------------------------------------------------------------
-- 2. HEADER SLIP GAJI (1 baris per dokter per periode jasa)
-- ---------------------------------------------------------------
CREATE TABLE RSTXN_GAJIDOCTORHDRS (
    gajidoctor_no           NUMBER(12)     NOT NULL,   -- MAX+1 + retry ORA-00001
    dr_id                   VARCHAR2(10)   NOT NULL,

    -- ---- PERIODE ----
    -- Semua VARCHAR2 dengan zero-padding: bulan '01'..'12', tahun 4 digit.
    -- periode JASA = bulan kerja; periode GAJI = bulan bayar = jasa + 1 bulan.
    -- Diisi aplikasi, dijaga CHECK ck_gajidoctorhdrs_periode.
    tahun_jasa              VARCHAR2(4)    NOT NULL,
    bulan_jasa              VARCHAR2(2)    NOT NULL,
    tahun_gaji              VARCHAR2(4)    NOT NULL,
    bulan_gaji              VARCHAR2(2)    NOT NULL,

    -- ---- PARAMETER (snapshot dari RSMST_DOCTORS saat generate) ----
    -- nilai_gaji_pokok = salinan RSMST_DOCTORS.basic_salary pada saat generate.
    -- Tetap disalin ke sini, TIDAK di-join saat cetak: kalau gaji pokok dokter
    -- direvisi tahun depan, slip periode lama harus tetap memakai angka lama.
    skema_gaji_pokok        VARCHAR2(1)    DEFAULT 'A' NOT NULL,
    nilai_gaji_pokok        NUMBER(14,2)   DEFAULT 0   NOT NULL,
    potongan_rs_basis       VARCHAR2(1)    DEFAULT 'T' NOT NULL,
    potongan_rs_persen      NUMBER(5,2)    DEFAULT 10  NOT NULL,
    pph21_persen            NUMBER(5,2)    DEFAULT 2.5 NOT NULL,

    -- ---- HASIL HITUNG ----
    -- Ringkasan; rinciannya ada di RSTXN_GAJIDOCTORDTLS.
    jasa_total              NUMBER(14,2)   DEFAULT 0 NOT NULL,  -- SUM detail 'J'
    total_gaji              NUMBER(14,2)   DEFAULT 0 NOT NULL,  -- per skema_gaji_pokok
    potongan_rs             NUMBER(14,2)   DEFAULT 0 NOT NULL,  -- disimpan NEGATIF
    pph21                   NUMBER(14,2)   DEFAULT 0 NOT NULL,  -- disimpan NEGATIF
    potongan_lain_total     NUMBER(14,2)   DEFAULT 0 NOT NULL,  -- SUM detail 'P' (NEGATIF)
    tambahan_total          NUMBER(14,2)   DEFAULT 0 NOT NULL,  -- SUM detail 'T' (POSITIF)
    gaji_diterima           NUMBER(14,2)   DEFAULT 0 NOT NULL,

    -- ---- KONTROL ----
    -- 'D' draft (masih boleh di-generate ulang) / 'F' final (terkunci, siap cetak)
    gaji_status             VARCHAR2(1)    DEFAULT 'D' NOT NULL,
    tanggal_bayar           DATE,
    entry_user              VARCHAR2(30),
    entry_date              DATE           DEFAULT SYSDATE,
    update_user             VARCHAR2(30),
    update_date             DATE,

    CONSTRAINT pk_gajidoctorhdrs         PRIMARY KEY (gajidoctor_no),
    CONSTRAINT uq_gajidoctorhdrs_periode UNIQUE (dr_id, tahun_jasa, bulan_jasa),
    CONSTRAINT fk_gajidoctorhdrs_doctor  FOREIGN KEY (dr_id)
                                         REFERENCES RSMST_DOCTORS (dr_id),

    -- zero-padding wajib, kalau tidak GROUP BY / ORDER BY periode kacau
    CONSTRAINT ck_gajidoctorhdrs_blnjasa CHECK (bulan_jasa IN
        ('01','02','03','04','05','06','07','08','09','10','11','12')),
    CONSTRAINT ck_gajidoctorhdrs_blngaji CHECK (bulan_gaji IN
        ('01','02','03','04','05','06','07','08','09','10','11','12')),
    CONSTRAINT ck_gajidoctorhdrs_thnjasa CHECK (REGEXP_LIKE(tahun_jasa, '^[0-9]{4}$')),
    CONSTRAINT ck_gajidoctorhdrs_thngaji CHECK (REGEXP_LIKE(tahun_gaji, '^[0-9]{4}$')),

    -- periode gaji WAJIB tepat 1 bulan setelah periode jasa.
    -- Menggantikan jaminan yang tadinya diberikan virtual column.
    CONSTRAINT ck_gajidoctorhdrs_periode CHECK (
        (bulan_jasa =  '12' AND bulan_gaji = '01'
             AND TO_NUMBER(tahun_gaji) = TO_NUMBER(tahun_jasa) + 1)
     OR (bulan_jasa <> '12' AND tahun_gaji = tahun_jasa
             AND TO_NUMBER(bulan_gaji) = TO_NUMBER(bulan_jasa) + 1)
    ),

    CONSTRAINT ck_gajidoctorhdrs_skema   CHECK (skema_gaji_pokok  IN ('A','G','N')),
    CONSTRAINT ck_gajidoctorhdrs_basis   CHECK (potongan_rs_basis IN ('T','J','N','B')),
    CONSTRAINT ck_gajidoctorhdrs_status  CHECK (gaji_status       IN ('D','F'))
);

-- uq_gajidoctorhdrs_periode sekaligus melayani pencarian per dokter+periode.
CREATE INDEX ix_gajidoctorhdrs_periode ON RSTXN_GAJIDOCTORHDRS (tahun_gaji, bulan_gaji);
CREATE INDEX ix_gajidoctorhdrs_status  ON RSTXN_GAJIDOCTORHDRS (gaji_status, tahun_jasa, bulan_jasa);


-- ---------------------------------------------------------------
-- 3. DETAIL SLIP GAJI
--    Komponen baru cukup INSERT baris — tidak perlu ALTER TABLE.
-- ---------------------------------------------------------------
CREATE TABLE RSTXN_GAJIDOCTORDTLS (
    gajidoctor_dtl          NUMBER(12)     NOT NULL,   -- MAX+1 + retry ORA-00001
    gajidoctor_no           NUMBER(12)     NOT NULL,   -- FK ke header

    -- 'J' jasa (pemasukan, dari RSVIEW_NEWDOCSALARIES)
    -- 'P' potongan (disimpan NEGATIF)
    -- 'T' tambahan (bonus / rapel / koreksi bulan lalu, POSITIF)
    jenis                   VARCHAR2(1)    NOT NULL,

    -- jenis 'J' -> isi apa adanya dari RSVIEW_NEWDOCSALARIES.DESC_DOC:
    --   'UP RJ','JD RJ','UP RJTRF','JD RJTRF','UP UGD','JD UGD',
    --   'UP UGDTRF','JD UGDTRF','VISIT','KONSUL','JD RI',
    --   'OPERATOR','ANASTESI','OPERATOR RJ','ANASTESI RJ',
    --   'OPERATOR UGD','ANASTESI UGD','UP KLINIK','JD KLINIK',
    --   'RAD RJ','RAD UGD','RAD RI'
    --   plus kode di luar view untuk model per kapita:
    --   'KAPITA RI','KAPITA RJ'
    --   plus tunjangan rutin — TETAP jenis 'J' karena ikut membentuk Total
    --   Gaji dan karenanya kena PPh:
    --   'TUNJ STRUKTURAL','TUNJ FUNGSIONAL','TUNJ HADIR'
    -- jenis 'P' -> 'RS','PPH21','KASBON','IDI','ARISAN','KOPERASI',
    --              'ANGSURAN','BPJS','ZARIYAH','LAIN'
    --              (catatan: 'RS' & 'PPH21' juga diringkas di header pada
    --               kolom potongan_rs & pph21 — detail dipakai untuk cetak.
    --               'KASBON' = baris "telah diambil" pada slip lama.)
    -- jenis 'T' -> 'BONUS','RAPEL','KOREKSI','LAIN'
    --              Ketiganya diberikan SETELAH pajak. Bila suatu saat ada
    --              tambahan yang harus kena pajak, tempatnya jenis 'J',
    --              bukan di sini.
    kode                    VARCHAR2(20)   NOT NULL,

    -- label cetak. Di-snapshot supaya slip lama tidak berubah teksnya kalau
    -- penamaan komponen direvisi (mis. RAD -> "Bacaan USG dan Rontgen").
    keterangan              VARCHAR2(100),

    nilai                   NUMBER(14,2)   DEFAULT 0 NOT NULL,

    -- jumlah pasien/kunjungan di balik nilai tsb. Wajib untuk model per
    -- kapita (nilai = jumlah_pasien x tarif_per_kapita), dan berguna sebagai
    -- informasi pada komponen lain.
    jumlah_pasien           NUMBER(6)      DEFAULT 0 NOT NULL,

    urutan                  NUMBER(3)      DEFAULT 0 NOT NULL,  -- urutan cetak

    entry_user              VARCHAR2(30),
    entry_date              DATE           DEFAULT SYSDATE,
    update_user             VARCHAR2(30),
    update_date             DATE,

    CONSTRAINT pk_gajidoctordtls       PRIMARY KEY (gajidoctor_dtl),
    CONSTRAINT fk_gajidoctordtls_hdr   FOREIGN KEY (gajidoctor_no)
                                       REFERENCES RSTXN_GAJIDOCTORHDRS (gajidoctor_no)
                                       ON DELETE CASCADE,
    -- satu kode hanya boleh muncul sekali per slip per jenis
    CONSTRAINT uq_gajidoctordtls       UNIQUE (gajidoctor_no, jenis, kode),
    CONSTRAINT ck_gajidoctordtls_jenis CHECK (jenis IN ('J','P','T'))
);

CREATE INDEX ix_gajidoctordtls_kode ON RSTXN_GAJIDOCTORDTLS (kode, jenis);


-- ---------------------------------------------------------------
-- 4. CONTOH PENGISIAN PARAMETER (hasil bedah 0726sp.xlsx, periode Juli 2026)
--    dr_id dikosongkan -> isi sesuai RSMST_DOCTORS sebelum dijalankan.
--
--    Sebaran 19 sheet berisi: skema 'A' 14 dokter / 'G' 4 dokter;
--    basis 'T' 13 / 'J' 3 / 'N' 2 / 'B' 1. DEFAULT kolom di bagian 1
--    (A, T, 10%, 2,5%) memang mengikuti mayoritas ini.
-- ---------------------------------------------------------------
-- Aditif, potongan 10% dari total gaji (mayoritas)
--   skema_gaji_pokok='A', basic_salary=5000000,
--   potongan_rs_basis='T', potongan_rs_persen=10
--   Gaji pokok tidak selalu 5 juta: SpAN 10jt, SpPD-Hamdan 7,5jt,
--   SpOG-Nanda 6jt, SpS 2,5jt.
--
-- Aditif, gaji pokok bebas potongan (SpB, SpS, SpOG-Nanda)
--   skema_gaji_pokok='A', potongan_rs_basis='J'
--   Rumus aslinya eksplisit: -(TOTAL - GAJI POKOK) x 10%.
--
-- Garanty fee (SpP, SpJP, SpOT, Sp.M-Karina)
--   skema_gaji_pokok='G', basic_salary=5000000,
--   potongan_rs_basis='T', potongan_rs_persen=10
--   PENTING: basis 'T', BUKAN 'J'. Rumus di sheet memotong dari hasil
--   garanty (-D140*10%), jadi gaji pokok tetap ikut terpotong. Memakai 'J'
--   membuat gaji dokter SpP meleset Rp3,28 juta.
--
-- Tanpa potongan RS (SpAN, SpPK)
--   skema_gaji_pokok='A', potongan_rs_basis='N'
--   SpAN basic_salary=10000000 + potongan_idi=100000.
--
-- Potongan RS 5% (SpOG-Tutit)
--   potongan_rs_basis='T', potongan_rs_persen=5
--   Bukti bahwa 10% adalah default, bukan aturan tetap.
--
-- Per kapita (SpPK 40rb/pasien RI, SpKFR 65rb/kunjungan RJ)
--   tarif_per_kapita_ri=40000  /  tarif_per_kapita_rj=65000
--   -> menghasilkan baris detail jenis 'J' kode 'KAPITA RI' / 'KAPITA RJ'
--
-- Berjenjang (Sp.M-Tania)
--   potongan_rs_basis='B',
--   potongan_rs_aturan='{"UP RJ":10,"JD RJ":50}'
--   Rumus aslinya menyebut empat komponen: 10% uang periksa poli, 50% jasa
--   tindakan/OK, lalu 10% dan 65% atas dua sel yang TIDAK BERLABEL dan
--   kebetulan kosong. Dua komponen terakhir sengaja tidak ditebak di sini —
--   TANYAKAN ke bagian keuangan sebelum mengisinya.


-- ---------------------------------------------------------------
-- 5. SELISIH YANG DISENGAJA TERHADAP WORKBOOK LAMA
--
--    Tiga hal berikut adalah cacat workbook, bukan aturan penggajian. Hasil
--    generate akan BERBEDA dari slip Juli 2026 pada titik-titik ini, dan itu
--    memang yang diinginkan. Dicatat supaya nanti tidak dikira bug.
--
--    a. SpKFR — angka 36 (jumlah kunjungan) berada di kolom yang ikut
--       ter-SUM sebagai kolom OK, sehingga Total Gaji tertulis 8.595.036
--       alih-alih 8.595.000. Model ini memisahkan jumlah_pasien dari nilai,
--       jadi Rp36 tsb hilang dengan sendirinya. Ini satu-satunya sheet yang
--       tidak cocok dari 19.
--
--    b. Sp.M-Tania — PPh di workbook pajak eksternal memakai basis
--       (TOTAL GAJI + potongan RS) dengan tanda TERBALIK: 2,5% x 50.530.220
--       = 1.263.255, seharusnya 2,5% x 48.104.220 = 1.202.605. Dokter
--       kelebihan potong Rp60.650. Rumus di berkas ini memakai tanda benar.
--
--    c. Slip vs daftar gaji tidak selalu sama di workbook lama — SpOG-Nanda
--       48.606.738 (slip) vs 48.206.738 (daftar gaji), dan beberapa sel
--       rekap menarik angka milik dokter lain. Dengan satu sumber di
--       database, perbedaan semacam ini tidak mungkin lagi terjadi.
-- ---------------------------------------------------------------
