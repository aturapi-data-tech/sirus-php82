-- database/sql/2026_11_07_drop_gaji_dokter.sql
-- ===============================================================
-- PENCABUTAN MODUL SLIP GAJI DOKTER DARI DATABASE SIRUS
--
-- Penggajian dokter diproses di SIAP sejak November 2026: struktur gaji
-- disetel di SIAP, slip dihitung di SIAP. SIRUS tinggal pemasok transaksi
-- jasa (GET /api/siap/gaji-dokter/{tahun}/{bulan}/transaksi) dan layar
-- Monitoring Jasa Dokter. Objek yang dipasang berkas
-- arsip-gaji-dokter/2026_08_04_install_gaji_dokter.sql tidak dipakai lagi:
--
--   RSTXN_GAJIDOCTORHDRS  header slip per dokter per bulan jasa
--   RSTXN_GAJIDOCTORDTLS  detail slip (jasa / potongan / tambahan)
--   RSMST_DOCTORS         18 kolom parameter gaji + 3 CHECK constraint
--
-- Index dan constraint kedua tabel (PK_, UQ_, FK_, CK_, IX_GAJIDOCTOR*)
-- ikut hilang bersama tabelnya; tidak perlu DROP terpisah.
-- Tidak ada SEQUENCE / TRIGGER: nomor slip dibuat MAX+1 di aplikasi.
--
-- YANG TIDAK DISENTUH
--   RSMST_DOCTORS.BASIC_SALARY — kolom lama (sebelum modul ini), masih
--   dipakai form Master Dokter & LOV dokter.
--
-- URUTAN KERJA (WAJIB)
--   1. Deploy SIRUS yang sudah tanpa pembaca objek ini (commit "Monitoring
--      Jasa Dokter" + "DDL drop gaji dokter"). Kalau DDL jalan lebih dulu,
--      endpoint /api/siap/dokter dan /transaksi lama akan ORA-00904.
--   2. Di SIAP: pastikan struktur gaji semua dokter sudah ada di
--      siapmst_gdok_strukturs (sudah diimpor dari ekspor SIRUS 7 Nov 2026),
--      mode mesin slip = SIAP, dan fitur "Ambil dari SIRUS" (mesin SIRUS,
--      GajiDokterSirus::ambil) sudah dilepas — endpoint slip & lampiran yang
--      dibacanya hilang bersama berkas ini.
--   3. Baru jalankan bagian A–C di bawah, satu per satu, baca hasilnya.
--
-- CARA MENJALANKAN
--   SQL polos, sekali jalan, dari atas ke bawah. Bagian A hanya membaca.
--   DROP TABLE di bagian B SENGAJA TANPA PURGE: tabel masuk recyclebin dan
--   bisa dipulihkan (bagian E) selama recyclebin belum dikosongkan.
-- ===============================================================


-- ===============================================================
-- A. PERIKSA DULU — pastikan yang akan dihapus memang objek modul ini
-- ===============================================================
-- A1. Objek yang ada. Harus hanya: 2 TABLE + 7 INDEX ber-nama *GAJIDOCTOR*,
--     semuanya dibuat Agustus 2026. Objek lain dengan nama serupa = BERHENTI.
SELECT object_type, object_name, status, created
  FROM user_objects
 WHERE object_name LIKE '%GAJIDOCTOR%'
 ORDER BY object_type, object_name;

-- A2. Isi tabel — catat angkanya di laporan.
SELECT 'HDR' tabel, COUNT(*) baris, MIN(tahun_jasa || '-' || bulan_jasa) periode_awal,
       MAX(tahun_jasa || '-' || bulan_jasa) periode_akhir
  FROM rstxn_gajidoctorhdrs
UNION ALL
SELECT 'DTL', COUNT(*), NULL, NULL FROM rstxn_gajidoctordtls;

-- A3. Kolom parameter gaji di RSMST_DOCTORS — harus 18 baris (BASIC_SALARY
--     tidak ikut daftar ini).
SELECT column_name, data_type
  FROM user_tab_columns
 WHERE table_name = 'RSMST_DOCTORS'
   AND column_name IN ('SKEMA_GAJI_POKOK', 'POTONGAN_RS_BASIS', 'POTONGAN_RS_PERSEN',
                       'POTONGAN_RS_ATURAN', 'PPH21_PERSEN', 'NPWP_STATUS', 'NPWP',
                       'TARIF_PER_KAPITA_RI', 'TARIF_PER_KAPITA_RJ',
                       'TUNJANGAN_STRUKTURAL', 'TUNJANGAN_FUNGSIONAL', 'TUNJANGAN_HADIR',
                       'POTONGAN_IDI', 'POTONGAN_ARISAN', 'POTONGAN_KOPERASI',
                       'POTONGAN_ANGSURAN', 'POTONGAN_BPJS', 'POTONGAN_ZARIYAH')
 ORDER BY column_id;

-- A4. Objek lain yang bergantung pada kedua tabel slip. Harus 0 baris.
SELECT name, type FROM user_dependencies
 WHERE referenced_name IN ('RSTXN_GAJIDOCTORHDRS', 'RSTXN_GAJIDOCTORDTLS');

-- A5. View / PL-SQL yang bergantung pada RSMST_DOCTORS. Di DB dev (7 Nov 2026)
--     ada 14 view (RSVIEW_DOCSALARIES, RSVIEW_NEWDOCSALARIES, RSVIEW_RJKASIR, …)
--     dan SEMUANYA sudah diperiksa: tidak satu pun menyebut 18 kolom di A3,
--     jadi tetap VALID sesudah bagian C. Bila di server muncul nama yang
--     tidak ada di dev, periksa teksnya dulu:
--       SELECT text FROM user_views WHERE view_name = '<nama>';
SELECT name, type FROM user_dependencies
 WHERE referenced_name = 'RSMST_DOCTORS'
   AND type IN ('VIEW', 'PROCEDURE', 'FUNCTION', 'PACKAGE BODY', 'TRIGGER')
 ORDER BY type, name;


-- ===============================================================
-- B. HAPUS TABEL SLIP — detail dulu (punya FK ke header)
-- ===============================================================
-- Isi tabel sudah tidak dibaca aplikasi mana pun. SIAP menyimpan slip
-- sebagai snapshot sendiri (siaptxn_gdok_*). Bila ingin menyimpan salinan
-- di luar recyclebin, jalankan dua baris ini DULU (opsional):
--   CREATE TABLE rsars_gajidoctorhdrs_2026 AS SELECT * FROM rstxn_gajidoctorhdrs;
--   CREATE TABLE rsars_gajidoctordtls_2026 AS SELECT * FROM rstxn_gajidoctordtls;

DROP TABLE rstxn_gajidoctordtls;
DROP TABLE rstxn_gajidoctorhdrs;


-- ===============================================================
-- C. LEPAS KOLOM PARAMETER GAJI DARI RSMST_DOCTORS
-- ===============================================================
-- Constraint dulu, baru kolomnya. RSMST_DOCTORS kecil (±120 baris), DROP
-- kolom langsung — tidak perlu SET UNUSED.
ALTER TABLE rsmst_doctors DROP CONSTRAINT ck_doctors_skema_gaji;
ALTER TABLE rsmst_doctors DROP CONSTRAINT ck_doctors_potongan;
ALTER TABLE rsmst_doctors DROP CONSTRAINT ck_doctors_npwp_status;

ALTER TABLE rsmst_doctors DROP (
    skema_gaji_pokok, potongan_rs_basis, potongan_rs_persen,
    potongan_rs_aturan, pph21_persen, npwp_status, npwp,
    tarif_per_kapita_ri, tarif_per_kapita_rj,
    tunjangan_struktural, tunjangan_fungsional, tunjangan_hadir,
    potongan_idi, potongan_arisan, potongan_koperasi,
    potongan_angsuran, potongan_bpjs, potongan_zariyah
);


-- ===============================================================
-- D. PERIKSA HASIL
-- ===============================================================
-- D1. Harus 0 baris (tabel & index sudah tidak ada di skema).
SELECT object_type, object_name FROM user_objects WHERE object_name LIKE '%GAJIDOCTOR%';

-- D2. Harus 0 baris (kolom sudah lepas); BASIC_SALARY tetap ada.
SELECT column_name FROM user_tab_columns
 WHERE table_name = 'RSMST_DOCTORS'
   AND (column_name LIKE 'POTONGAN%' OR column_name LIKE 'TUNJANGAN%'
        OR column_name LIKE 'TARIF_PER_KAPITA%' OR column_name LIKE 'NPWP%'
        OR column_name IN ('SKEMA_GAJI_POKOK', 'PPH21_PERSEN'));
SELECT column_name FROM user_tab_columns WHERE table_name = 'RSMST_DOCTORS' AND column_name = 'BASIC_SALARY';

-- D3. Kedua tabel ada di recyclebin (bisa dipulihkan).
SELECT original_name, object_name, droptime FROM user_recyclebin WHERE original_name LIKE 'RSTXN_GAJIDOCTOR%';

-- D4. Tidak ada objek yang jadi INVALID karena bagian C. Harus 0 baris.
SELECT object_type, object_name FROM user_objects WHERE status = 'INVALID' AND object_type IN ('VIEW', 'PROCEDURE', 'FUNCTION', 'PACKAGE BODY', 'TRIGGER');

-- D5. Dari aplikasi: buka Manajemen → Monitoring Keuangan → Monitoring Jasa
--     Dokter dan Master Dokter; dari SIAP: Uji sambungan + Proses slip satu
--     dokter (GET /dokter dan /transaksi masih harus 200).


-- ===============================================================
-- E. ROLLBACK
-- ===============================================================
-- E1. Tabel (selama masih di recyclebin — header dulu karena FK):
--   FLASHBACK TABLE rstxn_gajidoctorhdrs TO BEFORE DROP;
--   FLASHBACK TABLE rstxn_gajidoctordtls TO BEFORE DROP;
--     Index & constraint ikut pulih dengan nama sistem BIN$…; kembalikan
--     namanya bila perlu: ALTER INDEX "BIN$…" RENAME TO ix_gajidoctorhdrs_periode;
--
-- E2. Kolom RSMST_DOCTORS tidak bisa di-flashback. Pasang ulang dari bagian
--     1 berkas arsip-gaji-dokter/2026_08_04_install_gaji_dokter.sql (ALTER
--     TABLE RSMST_DOCTORS ADD … + CHECK constraint); nilainya kembali DEFAULT,
--     isi per dokter diambil dari SIAP (struktur gaji di sana adalah sumbernya).
--
-- E3. Bila recyclebin sudah kosong: pakai backup RMAN/export malam sebelumnya,
--     atau tabel rsars_* bila bagian B opsionalnya dijalankan.
