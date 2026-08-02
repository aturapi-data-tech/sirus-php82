-- database/sql/2026_08_02_alter_gajidoctors_lanjutan.sql
-- ===============================================================
-- LANJUTAN dari 2026_08_01_table_gajidoctors.sql
--
-- Empat kolom di bawah ditambahkan SETELAH berkas pertama dijalankan di
-- lingkungan pengembangan, jadi dipisah supaya lingkungan yang sudah
-- menjalankan berkas pertama tinggal menjalankan yang ini.
--
-- INSTALASI BARU: jalankan 2026_08_01_table_gajidoctors.sql lebih dulu,
-- lalu berkas ini, lalu 2026_08_01_seed_gajidoctor_params.sql.
--
-- SQL POLOS — TIDAK aman diulang. Menjalankan dua kali akan memunculkan:
--   ORA-01430  kolom sudah ada
--   ORA-02264  nama constraint sudah dipakai
-- Keduanya tidak merusak apa pun; lewati saja perintah yang error itu.
-- Cek dulu apa yang sudah terpasang lewat kueri di bagian 5.
-- ===============================================================


-- ---------------------------------------------------------------
-- 1. RSMST_DOCTORS.npwp — nomor NPWP dokter
--
--    !! DIBATALKAN oleh 2026_08_03_alter_doctors_npwp_status.sql !!
--    Kolom ini SEMPAT menjadi penentu status pajak: terisi = ber-NPWP, dengan
--    alasan dua sumber kebenaran untuk satu fakta akan bertentangan. Sejak
--    berkas 2026_08_03 penentunya pindah ke RSMST_DOCTORS.npwp_status dan
--    kolom npwp turun pangkat jadi arsip nomor semata — sebab kolom ini lahir
--    kosong untuk 122 dari 122 dokter, sehingga kekosongannya berarti "belum
--    didata", bukan "tidak punya". Alasan lengkap ada di kepala berkas itu.
--
--    Yang TETAP berlaku: dokter tanpa NPWP dikenai PPh Pasal 21 sebesar
--    20% LEBIH TINGGI (UU PPh Pasal 21 ayat 5a). Faktor itu dihitung sistem,
--    JANGAN dititipkan ke kolom pph21_persen — kalau dititipkan, angka di
--    kolom itu tidak lagi mencerminkan tarif pajaknya dan mustahil diaudit.
-- ---------------------------------------------------------------
ALTER TABLE RSMST_DOCTORS ADD (npwp VARCHAR2(30));


-- ---------------------------------------------------------------
-- 2. RSTXN_GAJIDOCTORDTLS.nilai_manual — penanda baris yang ditimpa tangan
--
--    Baris 'RS' dan 'PPH21' normalnya hasil rumus dan ditulis ulang setiap
--    detail slip berubah. Begitu bagian keuangan mengetik angkanya sendiri,
--    baris itu ditandai 'Y' dan hitung ulang berhenti menimpanya.
--
--    Penandanya menempel di baris detail, BUKAN tabel terpisah: yang ditimpa
--    memang baris itu sendiri.
-- ---------------------------------------------------------------
ALTER TABLE RSTXN_GAJIDOCTORDTLS ADD (nilai_manual VARCHAR2(1) DEFAULT 'N');

ALTER TABLE RSTXN_GAJIDOCTORDTLS ADD CONSTRAINT ck_gajidoctordtls_manual
    CHECK (nilai_manual IN ('Y','N'));


-- ---------------------------------------------------------------
-- 3. RSTXN_GAJIDOCTORHDRS.potongan_rs_aturan — SNAPSHOT aturan berjenjang
--
--    Kembaran kolom bernama sama di RSMST_DOCTORS. Tanpa snapshot ini,
--    hitung ulang membaca aturan dari master sehingga merevisi aturan hari
--    ini menggeser slip periode lama — padahal seluruh parameter lain
--    (skema, basis, persen, gaji pokok) sudah di-snapshot.
--
--    Slip lama yang dibuat sebelum kolom ini ada akan bernilai NULL;
--    GajiDokter::potonganRs() menanganinya dengan jatuh balik ke master.
-- ---------------------------------------------------------------
ALTER TABLE RSTXN_GAJIDOCTORHDRS ADD (potongan_rs_aturan VARCHAR2(1000));


-- ---------------------------------------------------------------
-- 4. RSTXN_GAJIDOCTORHDRS.npwp_status — SNAPSHOT status NPWP
--
--    Alasannya sama dengan nomor 3: dokter yang baru mendaftarkan NPWP bulan
--    depan tidak boleh mengubah PPh slip bulan-bulan sebelumnya.
--
--    DEFAULT 'Y' (dianggap ber-NPWP) supaya baris lama tidak mendadak kena
--    tambahan 20%.
-- ---------------------------------------------------------------
ALTER TABLE RSTXN_GAJIDOCTORHDRS ADD (npwp_status VARCHAR2(1) DEFAULT 'Y');

ALTER TABLE RSTXN_GAJIDOCTORHDRS ADD CONSTRAINT ck_gajidoctorhdrs_npwp
    CHECK (npwp_status IN ('Y','N'));

COMMIT;


-- ---------------------------------------------------------------
-- 5. CEK SEBELUM / SESUDAH JALAN — keempatnya harus muncul
-- ---------------------------------------------------------------
-- SELECT table_name, column_name, data_type, data_default
--   FROM user_tab_columns
--  WHERE (table_name = 'RSMST_DOCTORS'        AND column_name = 'NPWP')
--     OR (table_name = 'RSTXN_GAJIDOCTORDTLS' AND column_name = 'NILAI_MANUAL')
--     OR (table_name = 'RSTXN_GAJIDOCTORHDRS' AND column_name IN ('POTONGAN_RS_ATURAN','NPWP_STATUS'))
--  ORDER BY table_name, column_name;


-- ---------------------------------------------------------------
-- 6. SISIR SLIP LAMA — DIGANTIKAN
--
--    Kueri di sini dulu membandingkan snapshot slip dengan terisinya d.npwp.
--    Perbandingan itu tidak lagi bermakna sejak status pindah ke kolom
--    npwp_status. Versi penggantinya ada di bagian 4 berkas
--    2026_08_03_alter_doctors_npwp_status.sql.
-- ---------------------------------------------------------------


-- ---------------------------------------------------------------
-- 7. ROLLBACK
-- ---------------------------------------------------------------
-- ALTER TABLE RSTXN_GAJIDOCTORHDRS DROP CONSTRAINT ck_gajidoctorhdrs_npwp;
-- ALTER TABLE RSTXN_GAJIDOCTORDTLS DROP CONSTRAINT ck_gajidoctordtls_manual;
-- ALTER TABLE RSTXN_GAJIDOCTORHDRS DROP (npwp_status, potongan_rs_aturan);
-- ALTER TABLE RSTXN_GAJIDOCTORDTLS DROP (nilai_manual);
-- ALTER TABLE RSMST_DOCTORS        DROP (npwp);
