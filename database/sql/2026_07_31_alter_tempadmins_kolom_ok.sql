-- database/sql/2026_07_31_alter_tempadmins_kolom_ok.sql
-- ===============================================================
-- Kolom OK di penampung transfer antar-unit
--
-- MASALAH:
--   rstxn_ugdtempadmins (biaya bawaan RJ ke UGD) dan rstxn_ritempadmins
--   (biaya bawaan RJ/UGD ke RI) memetakan biaya ke KOLOM TETAP:
--       rj_admin, poli_price, acte_price, actp_price, actd_price,
--       obat, lab, rad, other, rs_admin
--   Tidak ada kolom untuk Kamar Operasi. Akibatnya biaya operasi yang sudah
--   masuk tagihan RJ/UGD tidak bisa ikut berpindah saat pasien ditransfer —
--   nominalnya akan hilang diam-diam dari tagihan pasien.
--
-- PERUBAHAN:
--   Menambah satu kolom `ok` di kedua tabel, sejajar `lab` / `rad` / `obat`.
--   Penamaannya mengikuti tetangganya yang memang ringkas (bare word), dan
--   `OK` bukan reserved word Oracle.
--
--   Kolom dibuat NULL-able tanpa DEFAULT: 90rb+ baris lama tidak perlu ditulis
--   ulang, dan semua konsumen sudah membungkus kolom biaya dengan NVL(...,0).
--
-- SESUDAH INI DI SISI APLIKASI (sudah disiapkan di commit yang sama):
--   - calculate{RJ,UGD}Costs() memuat komponen 'kamarOperasi'
--   - kedua komponen transfer memetakan 'ok' saat insert + saat cascade copy
--   - 10 tempat yang menjumlah kolom tetap menambahkan NVL(ok,0)
--   - guard "biaya operasi belum bisa dibawa" DICABUT — uangnya sekarang ikut
--
-- ⚠️  WAJIB dijalankan di SETIAP environment (dev dan produksi) SEBELUM kode
--     baru dipakai. Query di modul ini menyebut kolom secara eksplisit, jadi
--     kolom yang belum ada = ORA-00904 dan halamannya rusak total, bukan
--     sekadar fiturnya tidak jalan.
--
-- ROLLBACK (kalau perlu):
--   ALTER TABLE rstxn_ugdtempadmins DROP COLUMN ok;
--   ALTER TABLE rstxn_ritempadmins  DROP COLUMN ok;
-- ===============================================================

ALTER TABLE rstxn_ugdtempadmins ADD (ok NUMBER(9))
;

ALTER TABLE rstxn_ritempadmins ADD (ok NUMBER(9))
;

-- ---------------------------------------------------------------
-- VERIFIKASI
-- ---------------------------------------------------------------
-- SELECT table_name, column_name, data_type, nullable
--   FROM user_tab_columns
--  WHERE table_name IN ('RSTXN_UGDTEMPADMINS', 'RSTXN_RITEMPADMINS')
--    AND column_name = 'OK';
-- Harus mengembalikan 2 baris, NUMBER, nullable = Y.
