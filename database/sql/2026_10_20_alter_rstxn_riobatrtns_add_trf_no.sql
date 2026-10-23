-- database/sql/2026_10_20_alter_rstxn_riobatrtns_add_trf_no.sql
-- ===============================================================
-- Return Obat RI dikirim ke Apotek lewat Transfer Stok
--
-- MASALAH:
--   Return obat RI (rstxn_riobatrtns) hanya mengurangi tagihan pasien.
--   Obatnya secara fisik kembali ke Apotek, tetapi ledger stok tidak
--   mencatatnya → stok Apotek tidak klop.
--
-- PERUBAHAN:
--   Tombol "Kirim Return ke Apotek" di tab Return Obat RI membuat dua
--   transfer terposting (imtxn_trfhdrs/trfdtls):
--     1. Bangsal pasien (rsmst_bangsals.sl_codefrom) → Gudang Medis (04)
--     2. Gudang Medis (04) → Apotek (02)
--   Nomor kedua transfer dicatat per baris return supaya tidak terkirim
--   dua kali. NULL = belum dikirim.
--
-- ⚠️  WAJIB dijalankan di SETIAP environment (dev dan produksi) SEBELUM kode
--     baru dipakai. Tab Return Obat RI menyebut kolom ini secara eksplisit,
--     jadi kolom yang belum ada = ORA-00904 dan tab Return Obat RI rusak.
-- ===============================================================

-- Cek dulu: harus 0 baris (kolom belum ada)
SELECT column_name FROM user_tab_columns
 WHERE table_name = 'RSTXN_RIOBATRTNS' AND column_name IN ('TRF_NO_GUDANG', 'TRF_NO_APOTEK');

ALTER TABLE rstxn_riobatrtns ADD (trf_no_gudang NUMBER, trf_no_apotek NUMBER);

-- Verifikasi
SELECT column_name, data_type, nullable FROM user_tab_columns
 WHERE table_name = 'RSTXN_RIOBATRTNS' AND column_name IN ('TRF_NO_GUDANG', 'TRF_NO_APOTEK');

-- Cek bangsal yang belum punya Kode SL (tombol kirim ditolak untuk bangsal ini)
SELECT bangsal_id, bangsal_name FROM rsmst_bangsals WHERE TRIM(sl_codefrom) IS NULL;
