-- ============================================================
-- Alter: RSTXN_RJHDRS — Tambah kolom status_kronis
-- ============================================================
-- Konteks:
--   - Sekali kunjungan RJ punya banyak baris di RSTXN_RJOBATS.
--   - status_kronis di header = 'Y' bila MINIMAL satu obat di kunjungan
--     ini di-split kronis (RSTXN_RJOBATS.status_kronis = 'Y').
--   - App layer (livewire obat-rj) yang maintain — di-sync setiap kali
--     obat di-edit / dihapus.
-- ============================================================

ALTER TABLE rstxn_rjhdrs ADD (status_kronis VARCHAR2(1));

COMMENT ON COLUMN rstxn_rjhdrs.status_kronis IS 'Y = kunjungan ini punya minimal 1 obat dengan split kronis (RSTXN_RJOBATS.status_kronis=Y). N = tidak ada.';

-- ============================================================
-- Backfill dari data existing RSTXN_RJOBATS
-- ============================================================
-- Set 'Y' untuk header yang punya minimal 1 obat status_kronis='Y'
UPDATE rstxn_rjhdrs h
   SET h.status_kronis = 'Y'
 WHERE EXISTS (
       SELECT 1 FROM rstxn_rjobats o
        WHERE o.rj_no = h.rj_no
          AND o.status_kronis = 'Y'
       );

-- Sisanya (NULL) di-set 'N'
UPDATE rstxn_rjhdrs
   SET status_kronis = 'N'
 WHERE status_kronis IS NULL;

COMMIT;
