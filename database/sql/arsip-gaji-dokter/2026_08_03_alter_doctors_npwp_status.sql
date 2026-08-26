-- database/sql/2026_08_03_alter_doctors_npwp_status.sql
-- ===============================================================
-- LANJUTAN dari 2026_08_02_alter_gajidoctors_lanjutan.sql
--
-- MEMBALIK KEPUTUSAN DESAIN DI BERKAS SEBELUMNYA.
--
-- Berkas 2026_08_02 sengaja TIDAK membuat flag terpisah: status NPWP dibaca
-- dari terisi atau tidaknya RSMST_DOCTORS.npwp, dengan alasan dua sumber
-- kebenaran untuk satu fakta akan bertentangan.
--
-- Alasan itu benar secara teori tapi salah di lapangan. Saat kolom npwp
-- dipasang, 122 dari 122 dokter nomornya kosong — dan kosong DI SITU berarti
-- "belum sempat didata", bukan "tidak punya NPWP". Membaca kekosongan itu
-- sebagai status pajak membuat seluruh dokter kena PPh 21 +20%: untuk periode
-- Juli 2026 saja potongannya bertambah Rp1.212.060 dari 36 slip.
--
-- Jadi keduanya dipisah, masing-masing menjawab pertanyaan yang berbeda:
--   npwp_status  -> PUNYA NPWP atau tidak. INI yang dipakai menghitung pajak.
--   npwp         -> nomornya berapa. Dokumentasi, boleh menyusul, tidak
--                   pernah ikut menentukan perhitungan.
--
-- Konsekuensi yang diterima: kombinasi npwp_status='Y' dengan npwp kosong itu
-- SAH dan memang diharapkan selama pendataan berjalan. Yang janggal justru
-- kebalikannya (nomor terisi tapi status 'N') — lihat kueri di bagian 3.
--
-- SQL POLOS — TIDAK aman diulang. Jalan dua kali akan memunculkan ORA-01430
-- (kolom sudah ada) dan ORA-02264 (nama constraint sudah dipakai). Keduanya
-- tidak merusak apa pun.
-- ===============================================================


-- ---------------------------------------------------------------
-- 1. RSMST_DOCTORS.npwp_status — status NPWP dokter (master)
--
--    DEFAULT 'Y' dipilih dengan sengaja. Di Oracle 12c ke atas, DEFAULT pada
--    ADD COLUMN ikut mengisi baris yang sudah ada, jadi ke-122 dokter langsung
--    dianggap ber-NPWP dan tarif pajaknya tidak berubah pada hari pemasangan.
--    Bagian keuangan tinggal mematikan toggle untuk yang memang tidak punya —
--    jauh lebih sedikit daripada menyalakan satu per satu untuk semuanya.
--
--    Kembarannya di RSTXN_GAJIDOCTORHDRS.npwp_status tetap ada dan tetap
--    menjadi SNAPSHOT: dokter yang baru mendaftarkan NPWP bulan depan tidak
--    boleh menggeser PPh slip bulan-bulan sebelumnya.
-- ---------------------------------------------------------------
ALTER TABLE RSMST_DOCTORS ADD (npwp_status VARCHAR2(1) DEFAULT 'Y');

ALTER TABLE RSMST_DOCTORS ADD CONSTRAINT ck_doctors_npwp_status
    CHECK (npwp_status IN ('Y','N'));

COMMIT;


-- ---------------------------------------------------------------
-- 2. CEK SESUDAH JALAN
-- ---------------------------------------------------------------
-- SELECT column_name, data_type, data_default
--   FROM user_tab_columns
--  WHERE table_name = 'RSMST_DOCTORS'
--    AND column_name IN ('NPWP','NPWP_STATUS');
--
-- SELECT npwp_status, COUNT(*) jumlah FROM RSMST_DOCTORS GROUP BY npwp_status;


-- ---------------------------------------------------------------
-- 3. SISIR KOMBINASI JANGGAL — nomor terisi tapi status 'N'
--
--    Punya nomor NPWP tapi ditandai tidak ber-NPWP hampir pasti salah
--    setel, dan akibatnya dokter kelebihan potong 20%. Kebalikannya
--    (status 'Y', nomor kosong) TIDAK janggal — itu kondisi normal selama
--    nomornya belum didata.
-- ---------------------------------------------------------------
-- SELECT dr_id, dr_name, npwp, npwp_status
--   FROM RSMST_DOCTORS
--  WHERE npwp_status = 'N'
--    AND npwp IS NOT NULL
--    AND LENGTH(TRIM(npwp)) > 0
--  ORDER BY dr_name;


-- ---------------------------------------------------------------
-- 4. SISIR SLIP LAMA — snapshot yang tidak lagi cocok dengan master
--
--    Slip DRAFT tinggal diproses ulang. Slip FINAL sengaja dibiarkan:
--    isinya sudah dibayarkan, koreksinya lewat periode berikutnya.
-- ---------------------------------------------------------------
-- SELECT h.gajidoctor_no, d.dr_name, h.tahun_jasa, h.bulan_jasa,
--        h.gaji_status, h.npwp_status AS status_slip, d.npwp_status AS status_master
--   FROM RSTXN_GAJIDOCTORHDRS h
--   JOIN RSMST_DOCTORS d ON d.dr_id = h.dr_id
--  WHERE h.npwp_status <> d.npwp_status
--  ORDER BY h.gaji_status, h.tahun_jasa, h.bulan_jasa, d.dr_name;


-- ---------------------------------------------------------------
-- 5. ROLLBACK
-- ---------------------------------------------------------------
-- ALTER TABLE RSMST_DOCTORS DROP CONSTRAINT ck_doctors_npwp_status;
-- ALTER TABLE RSMST_DOCTORS DROP (npwp_status);
