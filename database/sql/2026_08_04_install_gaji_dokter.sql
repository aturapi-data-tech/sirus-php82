-- database/sql/2026_08_04_install_gaji_dokter.sql
-- ===============================================================
-- MODUL SLIP GAJI DOKTER — BERKAS PASANG TUNGGAL
--
-- Gabungan dari empat berkas yang sebelumnya terpisah:
--     2026_08_01_table_gajidoctors.sql          struktur tabel & kolom
--     2026_08_01_seed_gajidoctor_params.sql     parameter 19 dokter
--     2026_08_02_alter_gajidoctors_lanjutan.sql 4 kolom susulan
--     2026_08_03_alter_doctors_npwp_status.sql  status NPWP
--
-- Keempatnya lahir bertahap selama pengembangan. Di server tidak ada gunanya
-- menambah kolom lalu mengubahnya lagi beberapa menit kemudian, jadi di sini
-- hasil akhirnya langsung ditulis dalam bentuk final: kolom susulan sudah
-- menyatu ke CREATE TABLE, dan keputusan yang sempat dibalik (status NPWP,
-- lihat bagian 1) sudah dalam bentuk terakhirnya.
--
-- Keempat berkas lama TETAP DISIMPAN sebagai jejak keputusan — jangan
-- dijalankan lagi di server yang memakai berkas ini.
--
--
-- UNTUK SIAPA BERKAS INI
--   Server yang BELUM PERNAH sama sekali dipasangi modul gaji dokter.
--   Cek dulu dengan kueri ini; harus mengembalikan 0 baris:
--
--     SELECT table_name FROM user_tables
--      WHERE table_name IN ('RSTXN_GAJIDOCTORHDRS','RSTXN_GAJIDOCTORDTLS');
--
--   Kalau sudah ada isinya, JANGAN jalankan berkas ini — pakai berkas lama
--   yang sesuai dengan tahap terakhir yang sudah terpasang di sana.
--
-- CARA MENJALANKAN
--   Sekali jalan dari atas ke bawah. Bagian 1-5 struktur, bagian 6 data
--   parameter, bagian 7 pemeriksaan, bagian 8 rollback.
--
--   SQL POLOS — TIDAK aman diulang. Menjalankan dua kali memunculkan
--   ORA-00955 / ORA-01430 / ORA-02264 (nama sudah dipakai). Error itu tidak
--   merusak apa pun, tapi tandanya berkas ini memang tidak untuk diulang.
--
-- PRA-SYARAT (WAJIB DICEK SEBELUM JALAN)
--   1. Tipe & panjang RSMST_DOCTORS.DR_ID harus cocok dengan VARCHAR2(10)
--      yang dipakai FK di bagian 2:
--        SELECT column_name, data_type, data_length
--          FROM user_tab_columns
--         WHERE table_name = 'RSMST_DOCTORS' AND column_name = 'DR_ID';
--   2. RSMST_DOCTORS harus punya PK/UNIQUE pada DR_ID, kalau tidak FK gagal
--      dengan ORA-02270:
--        SELECT c.constraint_name, c.constraint_type, cc.column_name
--          FROM user_constraints c
--          JOIN user_cons_columns cc ON cc.constraint_name = c.constraint_name
--         WHERE c.table_name = 'RSMST_DOCTORS' AND c.constraint_type IN ('P','U');
--      Bila belum ada, JANGAN langsung ADD PRIMARY KEY pada master yang sudah
--      jalan — cek duplikatnya dulu:
--        SELECT dr_id, COUNT(*) FROM rsmst_doctors
--         GROUP BY dr_id HAVING COUNT(*) > 1;
--   3. Nama kolom baru di bagian 1 belum terpakai:
--        SELECT column_name FROM user_tab_columns
--         WHERE table_name = 'RSMST_DOCTORS' ORDER BY column_id;
--   4. Tidak ada INSERT posisional (tanpa daftar kolom) ke RSMST_DOCTORS di
--      sisi Oracle Dev 6i. Di repo ini sudah aman — master-dokter-actions
--      memakai insert($payload) bernama kolom. Form 6i di luar repo tidak
--      bisa dicek dari sini, dan INSERT posisional akan pecah begitu tabel
--      bertambah kolom, berapa pun nullability-nya.
--
--
-- ===============================================================
-- LATAR & RUMUS INDUK
-- ===============================================================
--   Menggantikan workbook Excel (mis. 0726sp.xlsx) yang memakai 1 sheet per
--   dokter. Hasil bedah 20 sheet tsb: SEMUA dokter memakai satu rumus yang
--   sama, hanya berbeda pada 3 parameter (skema gaji pokok, basis potongan
--   RS, daftar komponen). Karena itu tidak dibuat 20 model, cukup 1 pasang
--   tabel header-detail + beberapa kolom parameter yang menumpang di
--   RSMST_DOCTORS (setup gaji memang sudah tempatnya di master dokter).
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
--     PPH 21        = -FLOOR(pph21_persen % x (TOTAL GAJI + POTONGAN RS)
--                            x faktor NPWP)
--                     default 2,5% = NPPN 50% x tarif 5%
--                     faktor NPWP = 1,2 bila npwp_status = 'N'
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
--     npwp           — nama resmi Nomor Pokok Wajib Pajak
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
--   ck_gajidoctorhdrs_periode di bagian 2: baris dengan bulan_gaji yang bukan
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
--   potongan_rs_persen, potongan_rs_aturan, pph21_persen, npwp_status
--   SENGAJA ikut disimpan di header transaksi, tidak hanya di master. Dengan
--   begitu revisi parameter di master tidak mengubah slip periode lama — pola
--   yang sama dengan clause-versioning di modul dokumen. Dokter yang baru
--   mendaftarkan NPWP bulan depan tidak boleh menggeser PPh bulan lalu.
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
-- ===============================================================



-- ===============================================================
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
--                             baru. TANYAKAN DULU ke pemelihara Oracle Dev 6i.
-- ===============================================================
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

    -- STATUS NPWP — 'Y' punya, 'N' tidak. INILAH penentu pajaknya: dokter
    -- ber-status 'N' dikenai PPh Pasal 21 20% LEBIH TINGGI (UU PPh Pasal 21
    -- ayat 5a). Faktor itu dihitung sistem; JANGAN dititipkan ke kolom
    -- pph21_persen — kalau dititipkan, angka di kolom itu tidak lagi
    -- mencerminkan tarifnya dan mustahil diaudit.
    --
    -- DEFAULT 'Y' dipilih dengan sengaja. Rancangan pertama tidak memakai
    -- kolom ini sama sekali dan menyimpulkan status dari terisi/tidaknya
    -- kolom npwp di bawah, dengan alasan dua sumber kebenaran untuk satu
    -- fakta akan bertentangan. Alasan itu benar secara teori tapi salah di
    -- lapangan: nomor NPWP belum pernah didata, jadi seluruh dokter akan
    -- terbaca "tidak ber-NPWP" dan kena tambahan 20% tanpa dasar. Karena itu
    -- statusnya berdiri sendiri dan default-nya menganggap dokter ber-NPWP.
    npwp_status            VARCHAR2(1)    DEFAULT 'Y',

    -- Nomor NPWP — ARSIP saja, boleh menyusul, TIDAK pernah ikut menentukan
    -- perhitungan. Kombinasi npwp_status='Y' dengan npwp kosong itu SAH dan
    -- memang diharapkan selama pendataan berjalan.
    npwp                   VARCHAR2(30),

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
-- adalah master lama yang dipakai bersama Oracle Dev 6i. Perhitungan gaji
-- WAJIB memakai NVL() atas kolom-kolom ini.
--
-- Catatan: pada Oracle 12c ke atas, DEFAULT pada ADD COLUMN ikut mengisi baris
-- yang SUDAH ADA (metadata-only), jadi seluruh dokter langsung memegang nilai
-- default — termasuk npwp_status = 'Y'. Itu memang yang diinginkan: tarif
-- pajak tidak boleh bergeser pada hari pemasangan.

ALTER TABLE RSMST_DOCTORS ADD (
    CONSTRAINT ck_doctors_skema_gaji  CHECK (skema_gaji_pokok  IN ('A','G','N')),
    CONSTRAINT ck_doctors_potongan    CHECK (potongan_rs_basis IN ('T','J','N','B')),
    CONSTRAINT ck_doctors_npwp_status CHECK (npwp_status       IN ('Y','N'))
);


-- ===============================================================
-- 2. HEADER SLIP GAJI (1 baris per dokter per periode jasa)
-- ===============================================================
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
    -- Alasan yang sama berlaku untuk potongan_rs_aturan dan npwp_status.
    skema_gaji_pokok        VARCHAR2(1)    DEFAULT 'A' NOT NULL,
    nilai_gaji_pokok        NUMBER(14,2)   DEFAULT 0   NOT NULL,
    potongan_rs_basis       VARCHAR2(1)    DEFAULT 'T' NOT NULL,
    potongan_rs_persen      NUMBER(5,2)    DEFAULT 10  NOT NULL,
    potongan_rs_aturan      VARCHAR2(1000),
    pph21_persen            NUMBER(5,2)    DEFAULT 2.5 NOT NULL,
    npwp_status             VARCHAR2(1)    DEFAULT 'Y' NOT NULL,

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
    CONSTRAINT ck_gajidoctorhdrs_status  CHECK (gaji_status       IN ('D','F')),
    CONSTRAINT ck_gajidoctorhdrs_npwp    CHECK (npwp_status       IN ('Y','N'))
);

-- uq_gajidoctorhdrs_periode sekaligus melayani pencarian per dokter+periode.
CREATE INDEX ix_gajidoctorhdrs_periode ON RSTXN_GAJIDOCTORHDRS (tahun_gaji, bulan_gaji);
CREATE INDEX ix_gajidoctorhdrs_status  ON RSTXN_GAJIDOCTORHDRS (gaji_status, tahun_jasa, bulan_jasa);


-- ===============================================================
-- 3. DETAIL SLIP GAJI
--    Komponen baru cukup INSERT baris — tidak perlu ALTER TABLE.
-- ===============================================================
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

    -- Penanda baris yang DITIMPA TANGAN. Baris 'RS' dan 'PPH21' normalnya
    -- hasil rumus dan ditulis ulang setiap detail slip berubah. Begitu bagian
    -- keuangan mengetik angkanya sendiri, baris itu ditandai 'Y' dan hitung
    -- ulang berhenti menimpanya. Penandanya menempel di baris detail, BUKAN
    -- tabel terpisah: yang ditimpa memang baris itu sendiri.
    nilai_manual            VARCHAR2(1)    DEFAULT 'N' NOT NULL,

    -- jumlah pasien/kunjungan di balik nilai tsb. Wajib untuk model per
    -- kapita (nilai = jumlah_pasien x tarif_per_kapita), dan berguna sebagai
    -- informasi pada komponen lain.
    jumlah_pasien           NUMBER(6)      DEFAULT 0 NOT NULL,

    urutan                  NUMBER(3)      DEFAULT 0 NOT NULL,  -- urutan cetak

    entry_user              VARCHAR2(30),
    entry_date              DATE           DEFAULT SYSDATE,
    update_user             VARCHAR2(30),
    update_date             DATE,

    CONSTRAINT pk_gajidoctordtls        PRIMARY KEY (gajidoctor_dtl),
    CONSTRAINT fk_gajidoctordtls_hdr    FOREIGN KEY (gajidoctor_no)
                                        REFERENCES RSTXN_GAJIDOCTORHDRS (gajidoctor_no)
                                        ON DELETE CASCADE,
    -- satu kode hanya boleh muncul sekali per slip per jenis
    CONSTRAINT uq_gajidoctordtls        UNIQUE (gajidoctor_no, jenis, kode),
    CONSTRAINT ck_gajidoctordtls_jenis  CHECK (jenis IN ('J','P','T')),
    CONSTRAINT ck_gajidoctordtls_manual CHECK (nilai_manual IN ('Y','N'))
);

CREATE INDEX ix_gajidoctordtls_kode ON RSTXN_GAJIDOCTORDTLS (kode, jenis);


-- ===============================================================
-- 4. SIMPAN STRUKTUR
-- ===============================================================
COMMIT;


-- ===============================================================
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
-- ===============================================================

-- ===============================================================
-- 6. PARAMETER PENGGAJIAN 19 DOKTER — hasil bedah 0726sp.xlsx
--    (periode jasa Juli 2026, dibayar Agustus 2026)
--
--    Bagian ini DATA, bukan struktur. Boleh dilewati bila server sudah punya
--    parameter gajinya sendiri; modul tetap jalan dengan nilai DEFAULT dari
--    bagian 1 (skema 'A', basis 'T', 10%, PPh 2,5%).
--
--    dr_id di bawah mengacu pada RSMST_DOCTORS di lingkungan ini. Cocokkan
--    dulu bila server memakai penomoran dokter yang berbeda.
--
-- CARA BACA ANGKA DI SINI:
--   Seluruh nilai diturunkan dari RUMUS di tiap sheet, bukan dari labelnya.
--   Label di workbook sering menyesatkan — contoh paling jelas dr. Komang
--   Resty (090): barisnya tertulis "Garanty Fee" padahal rumusnya menjumlah,
--   jadi skemanya 'A', bukan 'G'.
--
-- PEMETAAN NAMA: sheet Excel -> RSMST_DOCTORS.dr_id sudah dicocokkan lewat
--   nama pada sel B5 tiap sheet, BUKAN nama sheet-nya. Nama sheet warisan
--   lama dan sering tidak nyambung — sheet "dr jenar" isinya dr. Bambang
--   Suhadi SpKFR, sheet "dr.bernard" isinya dr. Kristina SpPK.
--   Beberapa ejaan di master beda tipis dengan di Excel:
--     Excel "Silvia Dohan Kartania, SpPD"  -> master "dr. Silvi Kartania D, SPPD"      (055)
--     Excel "Gaji dr.Ira, SpS"             -> master "dr. Dyah Irawati,SP.S"           (011)
--     Excel "dr. Wahyu Wibowo, SpA"        -> master "dr. Wahyu, Sp.A"                 (063)
--     Excel "dr. Tutit Lazuardi, SpOG"     -> master "dr. Tuttit Lazuardi, SP.OG"      (009)
--
-- YANG SENGAJA TIDAK DIISI:
--   1. basic_salary untuk 089 / 088 / 1113 (SpP, SpOT, Sp.M-Karina).
--      Ketiganya skema garanty fee, tapi sel gaji pokoknya KOSONG di workbook
--      sehingga rumus IF-nya selalu memilih jasa. Angkanya tidak diketahui —
--      dibiarkan apa adanya, jangan ditebak. Selama sel itu kosong, hasil
--      hitungnya sama saja dengan tanpa gaji pokok.
--   2. tunjangan_struktural / _fungsional / _hadir dan potongan_koperasi.
--      Kolomnya dibuat di bagian 1 berkas ini. Di workbook pun
--      ketiga tunjangan itu nol di semua dokter.
--   3. potongan kasbon / "telah diambil" — nilainya berubah tiap bulan,
--      tempatnya di detail slip, bukan master.
--
-- CATATAN NILAI YANG PERLU DIKONFIRMASI KE KEUANGAN:
--   a. 010 (SpB) potongan_arisan = 700000 adalah GABUNGAN "Arisan/iuran kop"
--      di workbook — dua sel berbeda yang dijumlah jadi satu. Begitu kolom
--      potongan_koperasi ada, angka ini harus dipecah.
--   b. 010 (SpB) potongan_angsuran = 5326900 diambil dari angsuran Juli 2026.
--      Kalau ini cicilan yang nilainya tetap, biarkan; kalau berubah tiap
--      bulan, kosongkan dan isi per slip.
--   c. 107 (Sp.M-Tania) potongan_rs_aturan hanya memuat dua komponen. Rumus
--      aslinya menyebut empat, tapi dua sisanya menunjuk sel TANPA LABEL yang
--      kebetulan kosong (10% dan 65% atas sel entah apa). Tidak ditebak.
--   d. 009 (SpOG-Tutit) berstatus NON-AKTIF di master dan seluruh angkanya
--      nol pada Juli 2026. Parameternya tetap diisi supaya tidak jatuh ke
--      default 10% bila suatu saat diaktifkan lagi.
--
-- KONDISI AWAL (dicek langsung ke database sebelum skrip ini dijalankan):
--   Ke-19 baris TIDAK berisi NULL, melainkan sudah memegang nilai DEFAULT dari
--   DDL — skema 'A', basis 'T', 10%, PPh 2,5%, sisanya 0. Oracle 12c ke atas
--   memang menerapkan DEFAULT ke baris lama saat ADD COLUMN (metadata-only),
--   jadi tidak ada baris yang tertinggal NULL seperti dikhawatirkan di DDL.
--   basic_salary: NULL untuk semua, kecuali 098 dan 107 yang bernilai 0.
--
-- ROLLBACK — kembalikan ke default, bukan ke NULL:
--     UPDATE RSMST_DOCTORS
--        SET skema_gaji_pokok = 'A', potongan_rs_basis = 'T',
--            potongan_rs_persen = 10, potongan_rs_aturan = NULL,
--            pph21_persen = 2.5, tarif_per_kapita_ri = 0, tarif_per_kapita_rj = 0,
--            potongan_idi = 0, potongan_arisan = 0, potongan_angsuran = 0,
--            potongan_bpjs = 0, potongan_zariyah = 0
--      WHERE dr_id IN ('098','089','086','037','063','090','045','041','067',
--                      '010','055','106','011','082','085','088','107','1113','009');
--     UPDATE RSMST_DOCTORS SET basic_salary = 0 WHERE dr_id IN ('098','107');
--     UPDATE RSMST_DOCTORS SET basic_salary = NULL
--      WHERE dr_id IN ('086','063','090','045','041','055','106','011','082','085','067','037','010');
-- ===============================================================

-- ---------------------------------------------------------------
-- 6.1. DASAR UNTUK SEMUA DOKTER YANG ADA DI WORKBOOK
--    PPh 2,5% berlaku seragam — terbukti dari rumus inline dua sheet:
--    -(TOTAL + POTONGAN RS) * 0.5 * 0.05  =  50% NPPN x tarif 5%.
-- ---------------------------------------------------------------
UPDATE RSMST_DOCTORS SET pph21_persen = 2.5
 WHERE dr_id IN ('098','089','086','037','063','090','045','041','067',
                 '010','055','106','011','082','085','088','107','1113','009');


-- ---------------------------------------------------------------
-- 6.2. ADITIF + POTONGAN RS 10% DARI TOTAL GAJI  (pola mayoritas)
--    TOTAL = jasa + gaji pokok; potongan 10% x TOTAL.
-- ---------------------------------------------------------------
-- 086 dr. M.A. Budi Purwito, SpRAD
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'T',
       potongan_rs_persen = 10, basic_salary = 5000000
 WHERE dr_id = '086';

-- 063 dr. Wahyu, Sp.A  (Excel: dr. Wahyu Wibowo, SpA)
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'T',
       potongan_rs_persen = 10, basic_salary = 5000000, potongan_idi = 100000
 WHERE dr_id = '063';

-- 090 dr. Komang Resty PW, SpOG
-- Barisnya berlabel "Garanty Fee" tapi rumusnya D143 = B142 + B140 (menjumlah)
-- -> skema 'A'. Jangan tergoda mengisi 'G'.
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'T',
       potongan_rs_persen = 10, basic_salary = 5000000, potongan_idi = 100000
 WHERE dr_id = '090';

-- 045 dr. Predito Prihantoro, SpKJ
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'T',
       potongan_rs_persen = 10, basic_salary = 5000000
 WHERE dr_id = '045';

-- 041 dr. Herlin Kristanti, SP A
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'T',
       potongan_rs_persen = 10, basic_salary = 5000000
 WHERE dr_id = '041';

-- 055 dr. Silvi Kartania D, SPPD  (Excel: Silvia Dohan Kartania, SpPD)
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'T',
       potongan_rs_persen = 10, basic_salary = 5000000, potongan_idi = 100000
 WHERE dr_id = '055';

-- 106 dr. Muhammad Hamdan Yuwaafii, Sp.P.D — gaji pokok 7,5 juta
-- Di slip potongannya berlabel "Potongan BP", rumusnya sama: 10% x TOTAL.
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'T',
       potongan_rs_persen = 10, basic_salary = 7500000
 WHERE dr_id = '106';

-- 082 dr. Bambang Suhadi, SpKFR — dibayar per kunjungan RJ 65.000
-- (G76 = F76 * 65000, label "Uang periksa", blok Rawat Jalan)
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'T',
       potongan_rs_persen = 10, basic_salary = 5000000, tarif_per_kapita_rj = 65000
 WHERE dr_id = '082';


-- ---------------------------------------------------------------
-- 6.3. ADITIF + GAJI POKOK BEBAS POTONGAN  (basis 'J')
--    Rumusnya eksplisit memotong (TOTAL - GAJI POKOK).
-- ---------------------------------------------------------------
-- 010 dr. M Yogiyo Pranoto, SP.B
-- potongan_arisan = gabungan "Arisan/iuran kop" (lihat catatan (a) di atas)
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'J',
       potongan_rs_persen = 10, basic_salary = 5000000,
       potongan_arisan = 700000, potongan_angsuran = 5326900
 WHERE dr_id = '010';

-- 011 dr. Dyah Irawati, SP.S  (Excel: Gaji dr.Ira, SpS) — gaji pokok 2,5 juta
-- Rumus: -(B138 - B137) * 10%  =  -(TOTAL - GAJI POKOK) * 10%
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'J',
       potongan_rs_persen = 10, basic_salary = 2500000, potongan_zariyah = 25000
 WHERE dr_id = '011';

-- 085 dr. Nanda Agus Prasetya, SpOG — gaji pokok 6 juta
-- Rumus: -(B133+B134+B135+B136) * 10% -> hanya komponen jasa, pokok bebas.
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'J',
       potongan_rs_persen = 10, basic_salary = 6000000,
       potongan_idi = 100000, potongan_bpjs = 127600
 WHERE dr_id = '085';


-- ---------------------------------------------------------------
-- 6.4. TANPA POTONGAN RS  (basis 'N')
-- ---------------------------------------------------------------
-- 098 dr. Kristina Dyah L, SpPK — dibayar per pasien RI 40.000
-- (H10 = 40000 * D10, label "Konsul pasien total", blok Rawat Inap)
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'N',
       potongan_rs_persen = 0, basic_salary = 5000000, tarif_per_kapita_ri = 40000
 WHERE dr_id = '098';

-- 037 dr. Johan, SPAN — gaji pokok 10 juta, sel persen potongan kosong
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'N',
       potongan_rs_persen = 0, basic_salary = 10000000, potongan_idi = 100000
 WHERE dr_id = '037';


-- ---------------------------------------------------------------
-- 6.5. GARANTY FEE  (skema 'G')
--    Rumus: IF(jasa > gaji pokok, jasa, gaji pokok) = GREATEST(...).
--    PENTING: basis potongannya tetap 'T' — dipotong dari HASIL garanty,
--    jadi gaji pokok ikut terpotong. Memakai 'J' di sini membuat gaji
--    dr. Jimmy Akbar meleset Rp3,28 juta.
--    basic_salary sengaja tidak disentuh, lihat catatan 1 di kepala berkas.
-- ---------------------------------------------------------------
-- 089 Dr. Jimmy Akbar, SpP
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'G', potongan_rs_basis = 'T',
       potongan_rs_persen = 10
 WHERE dr_id = '089';

-- 067 dr. Adriyawan W.N., SPJP — di sini gaji pokoknya TERBACA: 5 juta
-- (B142 = jasa - B139 -> 31.750.000 = 36.750.000 - 5.000.000)
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'G', potongan_rs_basis = 'T',
       potongan_rs_persen = 10, basic_salary = 5000000
 WHERE dr_id = '067';

-- 088 Dr. Deny Mory Aryawan, SpOT
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'G', potongan_rs_basis = 'T',
       potongan_rs_persen = 10
 WHERE dr_id = '088';

-- 1113 dr. Karina Rakhma Meutia, Sp.M
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'G', potongan_rs_basis = 'T',
       potongan_rs_persen = 10
 WHERE dr_id = '1113';


-- ---------------------------------------------------------------
-- 6.6. POTONGAN BERJENJANG PER KOMPONEN  (basis 'B')
-- ---------------------------------------------------------------
-- 107 dr. Tania Rahmania Maulani Sp.M
-- Rumus asli: -((G59*10%) + (G60*50%) + (G62*10%) + (G63*65%))
--   G59 = "Uang Periksa poli"  -> UP RJ 10%, TERPAKAI (12.130.000 -> 1.213.000)
--   G60 = "Jasa Tindakan/ OK"  -> JD RJ 50%, TIDAK TERPAKAI: G60 KOSONG.
--        Angka jasa tindakannya (31.712.220) sebenarnya ada di kolom I, bukan
--        G, sehingga rumus menunjuk sel kosong dan komponen ini tidak pernah
--        ikut memotong.
--   G62, G63 = sel TANPA LABEL & kosong -> tidak ditebak (catatan (c)).
--
-- YANG DIISI DI SINI HANYA 'UP RJ'. Alasannya konsekuensi uang:
--   dengan {"UP RJ":10}            -> potongan 1.213.000  (sama dengan yang
--                                     benar-benar dibayarkan Juli 2026)
--   dengan {"UP RJ":10,"JD RJ":50} -> potongan 17.109.110, gaji diterima turun
--                                     ~Rp15,4 juta dari slip aslinya.
-- Maksud penyusun rumus mungkin memang 50% atas jasa tindakan, tapi itu tidak
-- pernah terjadi. Seed ini mereproduksi yang DIBAYARKAN, bukan yang diduga
-- dimaksud. TANYAKAN ke keuangan sebelum menambahkan "JD RJ":50.
--
-- potongan_rs_persen dinolkan supaya jelas yang dipakai kolom aturan.
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'B',
       potongan_rs_persen = 0, basic_salary = 5000000,
       potongan_rs_aturan = '{"UP RJ":10}'
 WHERE dr_id = '107';


-- ---------------------------------------------------------------
-- 6.7. POTONGAN RS 5%  — bukti bahwa 10% itu kelaziman, bukan aturan
-- ---------------------------------------------------------------
-- 009 dr. Tuttit Lazuardi, SP.OG (NON-AKTIF di master, lihat catatan (d))
-- Sheet-nya memuat rumus PPh inline: -(D121 + H115) * 0.5 * 0.05
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'N', potongan_rs_basis = 'T',
       potongan_rs_persen = 5
 WHERE dr_id = '009';

COMMIT;


-- ---------------------------------------------------------------
-- 6.8. VERIFIKASI
-- ---------------------------------------------------------------
-- SELECT dr_id, dr_name, skema_gaji_pokok, potongan_rs_basis, potongan_rs_persen,
--        pph21_persen, basic_salary, tarif_per_kapita_ri, tarif_per_kapita_rj,
--        potongan_idi, potongan_arisan, potongan_angsuran, potongan_bpjs,
--        potongan_zariyah, potongan_rs_aturan
--   FROM RSMST_DOCTORS
--  WHERE dr_id IN ('098','089','086','037','063','090','045','041','067',
--                  '010','055','106','011','082','085','088','107','1113','009')
--  ORDER BY dr_id;
--
-- Dokter di luar daftar itu memegang nilai DEFAULT dari DDL (A / T / 10% /
-- 2,5%), bukan parameter hasil kesepakatan. Sisir ulang bila mereka mulai
-- digaji lewat modul ini.
--
-- ---------------------------------------------------------------
-- 6.9. HASIL UJI ULANG TERHADAP SLIP JULI 2026
--
--    Parameter di atas dijalankan lewat rumus induk lalu dibandingkan dengan
--    kolom "Gaji Diterima" tiap sheet:
--
--      18 dari 19 dokter COCOK SAMPAI RUPIAH.
--
--    Satu-satunya sisa selisih ada di 107 (Sp.M-Tania): model 46.901.615 vs
--    slip 46.840.965, beda Rp60.650. Itu PERSIS cacat tanda pada workbook
--    pajak eksternal yang sudah dicatat di
--    bagian 5 berkas ini — basis PPh-nya menambahkan potongan RS, bukan mengurangi.
--    Model di sini memakai tanda yang benar, jadi selisih ini disengaja dan
--    justru menguntungkan dokter yang bersangkutan.
-- ---------------------------------------------------------------
COMMIT;


-- ===============================================================
-- 7. PEMERIKSAAN SESUDAH PASANG
-- ===============================================================
-- a. Dua tabel transaksi harus ada:
--      SELECT table_name FROM user_tables
--       WHERE table_name IN ('RSTXN_GAJIDOCTORHDRS','RSTXN_GAJIDOCTORDTLS');
--
-- b. Kolom parameter di master harus lengkap (18 baris):
--      SELECT column_name, data_type, data_default
--        FROM user_tab_columns
--       WHERE table_name = 'RSMST_DOCTORS'
--         AND column_name IN ('SKEMA_GAJI_POKOK','POTONGAN_RS_BASIS',
--             'POTONGAN_RS_PERSEN','POTONGAN_RS_ATURAN','PPH21_PERSEN',
--             'NPWP_STATUS','NPWP','TARIF_PER_KAPITA_RI','TARIF_PER_KAPITA_RJ',
--             'TUNJANGAN_STRUKTURAL','TUNJANGAN_FUNGSIONAL','TUNJANGAN_HADIR',
--             'POTONGAN_IDI','POTONGAN_ARISAN','POTONGAN_KOPERASI',
--             'POTONGAN_ANGSURAN','POTONGAN_BPJS','POTONGAN_ZARIYAH')
--       ORDER BY column_name;
--
-- c. Seluruh dokter harus ber-npwp_status 'Y' (belum ada yang 'N'):
--      SELECT npwp_status, COUNT(*) jumlah FROM RSMST_DOCTORS GROUP BY npwp_status;
--
-- d. Parameter bagian 6 sudah masuk:
--      SELECT dr_id, dr_name, skema_gaji_pokok, potongan_rs_basis,
--             potongan_rs_persen, basic_salary
--        FROM RSMST_DOCTORS
--       WHERE dr_id IN ('098','089','086','037','063','090','045','041','067',
--                       '010','055','106','011','082','085','088','107','1113','009')
--       ORDER BY dr_name;


-- ===============================================================
-- 8. SISIR BERKALA — dijalankan kapan saja, bukan bagian pemasangan
-- ===============================================================
-- a. Kombinasi janggal: punya nomor NPWP tapi ditandai tidak ber-NPWP.
--    Hampir pasti salah setel, dan akibatnya dokter kelebihan potong 20%.
--    Kebalikannya (status 'Y', nomor kosong) TIDAK janggal — itu kondisi
--    normal selama nomornya belum didata.
--      SELECT dr_id, dr_name, npwp, npwp_status
--        FROM RSMST_DOCTORS
--       WHERE npwp_status = 'N'
--         AND npwp IS NOT NULL AND LENGTH(TRIM(npwp)) > 0
--       ORDER BY dr_name;
--
-- b. Snapshot slip yang tidak lagi cocok dengan master. Slip DRAFT tinggal
--    diproses ulang; slip FINAL sengaja dibiarkan karena isinya sudah
--    dibayarkan — koreksinya lewat periode berikutnya.
--      SELECT h.gajidoctor_no, d.dr_name, h.tahun_jasa, h.bulan_jasa,
--             h.gaji_status, h.npwp_status AS status_slip,
--             d.npwp_status AS status_master
--        FROM RSTXN_GAJIDOCTORHDRS h
--        JOIN RSMST_DOCTORS d ON d.dr_id = h.dr_id
--       WHERE h.npwp_status <> d.npwp_status
--       ORDER BY h.gaji_status, h.tahun_jasa, h.bulan_jasa, d.dr_name;


-- ===============================================================
-- 9. ROLLBACK — urutan terbalik, detail dulu baru header
-- ===============================================================
-- DROP TABLE RSTXN_GAJIDOCTORDTLS PURGE;
-- DROP TABLE RSTXN_GAJIDOCTORHDRS PURGE;
--
-- ALTER TABLE RSMST_DOCTORS DROP CONSTRAINT ck_doctors_skema_gaji;
-- ALTER TABLE RSMST_DOCTORS DROP CONSTRAINT ck_doctors_potongan;
-- ALTER TABLE RSMST_DOCTORS DROP CONSTRAINT ck_doctors_npwp_status;
-- ALTER TABLE RSMST_DOCTORS DROP (
--     skema_gaji_pokok, potongan_rs_basis, potongan_rs_persen,
--     potongan_rs_aturan, pph21_persen, npwp_status, npwp,
--     tarif_per_kapita_ri, tarif_per_kapita_rj,
--     tunjangan_struktural, tunjangan_fungsional, tunjangan_hadir,
--     potongan_idi, potongan_arisan, potongan_koperasi,
--     potongan_angsuran, potongan_bpjs, potongan_zariyah);
--
-- CATATAN: basic_salary JANGAN ikut di-drop — kolom itu sudah ada sebelum
-- perubahan ini dan dipakai form Master Dokter.
--
-- Mengembalikan parameter bagian 6 ke DEFAULT tanpa membuang kolomnya:
--   UPDATE RSMST_DOCTORS
--      SET skema_gaji_pokok = 'A', potongan_rs_basis = 'T',
--          potongan_rs_persen = 10, potongan_rs_aturan = NULL,
--          pph21_persen = 2.5, tarif_per_kapita_ri = 0, tarif_per_kapita_rj = 0,
--          potongan_idi = 0, potongan_arisan = 0, potongan_angsuran = 0,
--          potongan_bpjs = 0, potongan_zariyah = 0
--    WHERE dr_id IN ('098','089','086','037','063','090','045','041','067',
--                    '010','055','106','011','082','085','088','107','1113','009');
--   COMMIT;
