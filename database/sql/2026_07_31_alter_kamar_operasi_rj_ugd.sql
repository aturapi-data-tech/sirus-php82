-- database/sql/2026_07_31_alter_kamar_operasi_rj_ugd.sql
-- ===============================================================
-- TAHAP 0 — Kamar Operasi untuk RJ & UGD (model data)
--
-- Saat ini rstxn_oks HANYA mengenal rawat inap:
--   - rihdr_no NOT NULL, FK ke rstxn_rihdrs
--   - penampung biaya cuma rstxn_rioks
--   - sl_codefrom seluruhnya '01' (tak pernah dipakai membedakan layanan)
--
-- STATUS: sudah dijalankan di DEV (2026-07-31). BELUM di PRODUKSI.
-- Jalankan berurutan, verifikasi dengan blok di bagian akhir.
-- Lihat docs/kamar-operasi-modul.md §9.
--
-- ---------------------------------------------------------------
-- KENAPA TABEL BIAYA SENDIRI, BUKAN MENUMPANG rstxn_{rj,ugd}others
-- ---------------------------------------------------------------
-- Menumpang tabel Lain-Lain sempat dipertimbangkan karena hilirnya sudah jadi
-- (Administrasi, Kasir, PendapatanRsTrait, PiutangPasienTrait sudah menjumlah
-- rstxn_*others) sehingga nol perubahan query.
--
-- DITOLAK — keputusan user 2026-07-31: di jurnal, biaya operasi akan terbaca
-- sebagai "pendapatan lain-lain" sehingga pos itu terlihat membengkak padahal
-- isinya pendapatan operasi. Kebenaran akuntansi lebih penting daripada hemat
-- query; pemisahan pos pendapatan harus terjaga di tingkat tabel.
--
-- Konsekuensinya: biaya OK RJ/UGD TIDAK otomatis masuk tagihan/laporan. Ada ~10
-- titik hilir yang wajib ditambah di Tahap 1 — daftarnya di §4. Tiap titik yang
-- terlewat = biaya hilang diam-diam dari laporan, jadi §4 bukan opsional.
--
-- Struktur kedua tabel meniru rstxn_rioks (satu baris per pos tarif) dan memakai
-- rj_no seperti rstxn_rjlabs / rstxn_ugdlabs.
--
-- CATATAN PENTING:
--   Pendapatan dokter untuk operasi RJ/UGD TIDAK akan terhitung sampai view
--   RSVIEW_NEWDOCSALARIES ikut diperbarui — lihat §5.
-- ===============================================================


-- ---------------------------------------------------------------
-- 1. rstxn_oks — tandai layanan + referensi kunjungan
-- ---------------------------------------------------------------
-- status_rjri : 'RJ' | 'UGD' | 'RI'   (mengikuti lbtxn_checkuphdrs.status_rjri)
-- ref_no      : rj_no (RJ/UGD) atau rihdr_no (RI)
--               Sengaja TANPA foreign key — menunjuk ke tabel berbeda
--               tergantung status_rjri, persis seperti lbtxn_checkuphdrs.ref_no.
--
-- KENAPA TIDAK MENAMBAH KOLOM rj_no TERPISAH:
--   1. Lab sudah membuktikan pola satu kolom polimorfik: lbtxn_checkuphdrs HANYA
--      punya status_rjri + ref_no (tanpa rj_no/rihdr_no) untuk 169.549 baris
--      lintas RI/UGD/RJ. Tiga kolom untuk satu konsep = tiap query harus
--      bercabang, dan cabang yang terlewat bikin data nyangkut diam-diam.
--   2. FK tetap mustahil: rstxn_rjhdrs dan rstxn_ugdhdrs adalah DUA tabel
--      berbeda yang PK-nya sama-sama bernama rj_no.
--   3. Integritas dijaga di tabel biaya per layanan (§2 & §3), sama seperti
--      rstxn_rjlabs / rstxn_ugdlabs.
--
-- STATUS rihdr_no SESUDAH SKRIP INI:
--   ref_no = SUMBER KEBENARAN untuk semua kode baru (dipasangkan status_rjri).
--   rihdr_no = kompatibilitas mundur SAJA — tetap diisi untuk baris RI supaya
--   view RSVIEW_NEWDOCSALARIES + query laporan lama tidak perlu diubah serentak.
--   Jangan pakai rihdr_no di kode baru; kalau nanti semua konsumen sudah pindah
--   ke ref_no, kolom ini bisa di-drop lewat skrip tersendiri.
ALTER TABLE rstxn_oks ADD (status_rjri VARCHAR2(3));
ALTER TABLE rstxn_oks ADD (ref_no      NUMBER(12));

-- Backfill 5.091 baris lama: semuanya rawat inap.
UPDATE rstxn_oks
   SET status_rjri = 'RI',
       ref_no      = rihdr_no
 WHERE status_rjri IS NULL;
COMMIT;

-- Baris RJ/UGD tidak punya rihdr_no, jadi NOT NULL harus dilepas.
-- FK RO3_RR10_FK ke rstxn_rihdrs TETAP dipertahankan: Oracle mengizinkan NULL
-- pada kolom ber-FK, dan baris RI lama tetap tervalidasi seperti semula.
ALTER TABLE rstxn_oks MODIFY (rihdr_no NULL);

ALTER TABLE rstxn_oks ADD CONSTRAINT ro3_status_rjri_ck
    CHECK (status_rjri IN ('RJ', 'UGD', 'RI'));

-- Worklist & guard pulang menyaring per layanan + status.
CREATE INDEX ro3_status_ref_ix ON rstxn_oks (status_rjri, ref_no);


-- ---------------------------------------------------------------
-- 2. rstxn_rjoks — baris biaya OK di tagihan Rawat Jalan
-- ---------------------------------------------------------------
-- ok_no PK global per tabel, tanpa sequence — konvensi repo: MAX+1 di dalam
-- transaksi + retry ORA-00001 (Oracle menolak FOR UPDATE pada query agregat,
-- ORA-01786). Lihat KamarOperasiTrait::jalankanDenganRetryOk().
CREATE TABLE rstxn_rjoks (
    ok_no    NUMBER(10)    NOT NULL,
    ok_date  DATE,
    ok_desc  VARCHAR2(100),
    ok_price NUMBER(9),
    rj_no    NUMBER(10)    NOT NULL,
    ok_reg   NUMBER(15)
);

ALTER TABLE rstxn_rjoks ADD CONSTRAINT rjok_pk     PRIMARY KEY (ok_no);
ALTER TABLE rstxn_rjoks ADD CONSTRAINT rjok_rj_fk  FOREIGN KEY (rj_no)  REFERENCES rstxn_rjhdrs (rj_no);
ALTER TABLE rstxn_rjoks ADD CONSTRAINT rjok_oks_fk FOREIGN KEY (ok_reg) REFERENCES rstxn_oks (ok_reg);

-- rj_no: dijumlah Administrasi/Kasir per kunjungan.
-- ok_reg: dipakai Batal Transaksi untuk menghapus baris milik satu transaksi OK.
CREATE INDEX rjok_rj_ix  ON rstxn_rjoks (rj_no);
CREATE INDEX rjok_reg_ix ON rstxn_rjoks (ok_reg);


-- ---------------------------------------------------------------
-- 3. rstxn_ugdoks — baris biaya OK di tagihan UGD
-- ---------------------------------------------------------------
-- UGD memakai rj_no juga (PK rstxn_ugdhdrs = RJ_NO), sama seperti rstxn_ugdlabs.
CREATE TABLE rstxn_ugdoks (
    ok_no    NUMBER(10)    NOT NULL,
    ok_date  DATE,
    ok_desc  VARCHAR2(100),
    ok_price NUMBER(9),
    rj_no    NUMBER(10)    NOT NULL,
    ok_reg   NUMBER(15)
);

ALTER TABLE rstxn_ugdoks ADD CONSTRAINT ugdok_pk     PRIMARY KEY (ok_no);
ALTER TABLE rstxn_ugdoks ADD CONSTRAINT ugdok_ugd_fk FOREIGN KEY (rj_no)  REFERENCES rstxn_ugdhdrs (rj_no);
ALTER TABLE rstxn_ugdoks ADD CONSTRAINT ugdok_oks_fk FOREIGN KEY (ok_reg) REFERENCES rstxn_oks (ok_reg);

CREATE INDEX ugdok_ugd_ix ON rstxn_ugdoks (rj_no);
CREATE INDEX ugdok_reg_ix ON rstxn_ugdoks (ok_reg);


-- ---------------------------------------------------------------
-- 4. WAJIB menyusul di sisi aplikasi (Tahap 1) — jangan dilewat
-- ---------------------------------------------------------------
-- Tanpa ini, tabel terisi tapi angkanya TIDAK muncul di tagihan & laporan.
-- Pola persis mengikuti baris lab (rstxn_rjlabs / rstxn_ugdlabs) di tiap file:
--
--   administrasi-rj.blade.php     : sumOk + masukkan ke sumTotalRJ   (acuan baris 214, 218)
--   administrasi-ugd.blade.php    : sumOk + masukkan ke total        (acuan baris 231)
--   kasir-rj.blade.php            : tambah ->exists() cek biaya      (acuan baris 451)
--   kasir-ugd.blade.php           : tambah ->exists() cek biaya      (acuan baris 467)
--   PendapatanRsTrait.php         : 4 query  (acuan baris 77, 105, 225, 267)
--   PiutangPasienTrait.php        : 2 query  (acuan baris 86, 126)
--
-- Cek ulang dengan: grep -rn "rstxn_rjlabs\|rstxn_ugdlabs" app resources/views
-- Setiap tempat yang menjumlah lab RJ/UGD, di situ juga OK harus dijumlah.


-- ---------------------------------------------------------------
-- 5. TIDAK termasuk skrip ini — perlu keputusan terpisah
-- ---------------------------------------------------------------
-- RSVIEW_NEWDOCSALARIES (pendapatan jasa dokter) mengambil operasi lewat:
--     FROM RSTXN_OKS a JOIN RSTXN_RIHDRS b ON a.rihdr_no = b.rihdr_no
--     WHERE b.ri_status = 'P'
-- Karena inner join ke rihdr_no, baris RJ/UGD (rihdr_no NULL) TIDAK ikut —
-- jasa dokter operator & anestesi dari operasi RJ/UGD tidak terhitung.
-- Perbaikannya menambah UNION ALL cabang RJ & UGD dengan syarat status
-- kunjungan yang setara ('L' untuk RJ/UGD, bukan 'P'). Itu mengubah angka
-- laporan pendapatan, jadi harus disepakati dulu.


-- ===============================================================
-- VERIFIKASI — jalankan setelah skrip di atas
-- ===============================================================
-- 1) Kolom baru ada & terisi
-- SELECT status_rjri, COUNT(*) FROM rstxn_oks GROUP BY status_rjri;
--    Ekspektasi: 'RI' = 5091 (atau lebih bila sudah ada transaksi baru)
--
-- 2) ref_no konsisten dengan rihdr_no untuk baris RI lama
-- SELECT COUNT(*) AS tidak_cocok FROM rstxn_oks
--  WHERE status_rjri = 'RI' AND NVL(ref_no,-1) <> NVL(rihdr_no,-1);
--    Ekspektasi: 0
--
-- 2b) TIDAK ada baris yang kehilangan referensi kunjungan
-- SELECT COUNT(*) AS tanpa_ref FROM rstxn_oks WHERE ref_no IS NULL;
--    Ekspektasi: 0
--
-- 3) rihdr_no sudah boleh NULL
-- SELECT nullable FROM user_tab_columns
--  WHERE table_name='RSTXN_OKS' AND column_name='RIHDR_NO';
--    Ekspektasi: Y
--
-- 4) Dua tabel biaya baru terbentuk lengkap
-- SELECT table_name FROM user_tables WHERE table_name IN ('RSTXN_RJOKS','RSTXN_UGDOKS');
-- SELECT table_name, constraint_name, constraint_type FROM user_constraints
--  WHERE table_name IN ('RSTXN_RJOKS','RSTXN_UGDOKS') ORDER BY table_name, constraint_type;
--    Ekspektasi tiap tabel: 1 P + 2 R (+ check NOT NULL bawaan)
-- SELECT index_name FROM user_indexes
--  WHERE index_name IN ('RJOK_RJ_IX','RJOK_REG_IX','UGDOK_UGD_IX','UGDOK_REG_IX');
--    Ekspektasi: 4
--
-- 5) Data lama tetap utuh — bandingkan dengan angka sebelum skrip dijalankan
-- SELECT COUNT(*) AS baris, SUM(ok_price) AS total FROM rstxn_rioks;
--    Sebelum: 43302 baris, 18.021.034.368
-- SELECT COUNT(*) FROM rstxn_oks;   -- sebelum: 5091
