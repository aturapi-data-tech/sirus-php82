-- ============================================================
-- Alter: RSTXN_RJOBATS — Tambah kolom split kronis (BPJS InaCBG vs Obat Kronis)
-- ============================================================
-- Konteks:
--   - Untuk obat yang terdaftar di RSMST_LISTOBATBPJSES (master obat kronis BPJS),
--     qty bisa di-split menjadi qty yang dibebankan ke InaCBG (qty_bpjs)
--     dan qty yang dibebankan ke kronis di luar paket (qty_kronis).
--   - Constraint qty_kronis + qty_bpjs == qty di-enforce di application layer.
--
-- Catatan eksekusi (2026-05-04):
--   - DDL dieksekusi TANPA klausa DEFAULT → existing rows ber-NULL pada 3 kolom baru.
--   - Backfill di bawah meng-set NULL → 'N'/0/0 agar konsisten.
--   - App layer wajib treat NULL sebagai 'N'/0 untuk safety (kalau backfill
--     belum dijalankan di env lain).
-- ============================================================

ALTER TABLE rstxn_rjobats ADD (
    status_kronis  VARCHAR2(1),
    qty_kronis     NUMBER(9,2),
    qty_bpjs       NUMBER(9,2)
);

COMMENT ON COLUMN rstxn_rjobats.status_kronis IS 'Y = obat ini di-split kronis (qty_kronis + qty_bpjs == qty); N = tidak di-split';
COMMENT ON COLUMN rstxn_rjobats.qty_kronis    IS 'Qty obat dibebankan ke kronis (di luar paket InaCBG). Hanya berarti saat status_kronis=Y';
COMMENT ON COLUMN rstxn_rjobats.qty_bpjs      IS 'Qty obat dibebankan ke BPJS InaCBG (di dalam paket). Hanya berarti saat status_kronis=Y';

-- ============================================================
-- Backfill: existing rows yang NULL → set ke 'N'/0/0 (idempotent).
-- ============================================================
UPDATE rstxn_rjobats
   SET status_kronis = 'N',
       qty_kronis    = 0,
       qty_bpjs      = 0
 WHERE status_kronis IS NULL;

COMMIT;
