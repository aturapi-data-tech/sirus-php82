-- database/sql/2026_09_28_alter_rstxn_oks_add_emp_id_omlop.sql
-- ===============================================================
-- Petugas ON LOOP di header Kamar Operasi
--
-- MASALAH:
--   Kartu ON LOOP di Crew & Jasa hanya pos tarif (omlop_fee) — tidak ada
--   kolom untuk MENCATAT SIAPA petugasnya, beda dengan Instrument/Asisten
--   yang masing-masing punya emp_id_* di rstxn_oks.
--
-- PERUBAHAN:
--   Tambah kolom emp_id_omlop, meniru emp_id_instrument persis:
--   VARCHAR2(25) NULL-able + FK ke hrmst_employees(emp_id).
--   Baris lama tidak perlu diisi (NULL = belum dicatat).
--
-- ⚠️  WAJIB dijalankan di SETIAP environment (dev dan produksi) SEBELUM kode
--     baru dipakai. Kartu Crew & Jasa menyebut kolom ini secara eksplisit,
--     jadi kolom yang belum ada = ORA-00904 dan modal Kamar Operasi rusak.
-- ===============================================================

-- Cek dulu: harus 0 baris (kolom belum ada)
SELECT column_name FROM user_tab_columns
 WHERE table_name = 'RSTXN_OKS' AND column_name = 'EMP_ID_OMLOP';

ALTER TABLE rstxn_oks ADD (emp_id_omlop VARCHAR2(25));

ALTER TABLE rstxn_oks ADD CONSTRAINT ro3_he1_omlop_fk
    FOREIGN KEY (emp_id_omlop) REFERENCES hrmst_employees (emp_id);

-- Verifikasi
SELECT column_name, data_type, data_length, nullable FROM user_tab_columns
 WHERE table_name = 'RSTXN_OKS' AND column_name = 'EMP_ID_OMLOP';
