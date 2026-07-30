-- =========================================================================
-- DDL: Tambah kolom cito_status di header order penunjang (lab & radiologi)
-- Tujuan: menandai order pemeriksaan berstatus CITO (segera/darurat) supaya
--         petugas Laboratorium & Radiologi langsung tahu ada kiriman pasien
--         yang harus didahulukan.
-- Nilai  : '1' = CITO, '0' = rutin (biasa). Konvensi sama dgn active_status.
--          Baris lama (sebelum kolom ada) bernilai NULL → diperlakukan rutin.
-- Diisi  : dari EMR (modal Order Laboratorium / Order Radiologi).
-- Catatan: kolom ditambahkan ke SEMUA tabel radiologi (RJ/UGD/RI) karena
--         daftar radiologi petugas adalah UNION 3 sumber — jumlah & nama
--         kolom SELECT harus sama. Header lab cuma satu tabel untuk RJ/UGD/RI.
--         PARKED — eksekusi DBA di dev DAN production sebelum deploy
--         (SELECT menyebut kolom eksplisit → kolom belum ada = ORA-00904).
-- =========================================================================

ALTER TABLE lbtxn_checkuphdrs ADD (cito_status VARCHAR2(1));
ALTER TABLE rstxn_rjrads      ADD (cito_status VARCHAR2(1));
ALTER TABLE rstxn_ugdrads     ADD (cito_status VARCHAR2(1));
ALTER TABLE rstxn_riradiologs ADD (cito_status VARCHAR2(1));

COMMENT ON COLUMN lbtxn_checkuphdrs.cito_status IS '1 = order lab CITO (didahulukan), 0/NULL = rutin';
COMMENT ON COLUMN rstxn_rjrads.cito_status      IS '1 = order radiologi CITO (RJ), 0/NULL = rutin';
COMMENT ON COLUMN rstxn_ugdrads.cito_status     IS '1 = order radiologi CITO (UGD), 0/NULL = rutin';
COMMENT ON COLUMN rstxn_riradiologs.cito_status IS '1 = order radiologi CITO (RI), 0/NULL = rutin';

COMMIT;
