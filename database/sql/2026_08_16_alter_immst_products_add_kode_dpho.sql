-- ============================================================
-- Pemetaan obat lokal → DPHO BPJS (Apotek Online / apotek-rest)
--
-- Apotek Online menuntut `KDOBT` berupa KODE obat DPHO (11 digit, mis.
-- "11250805294") pada obatnonracikan/v3/insert & obatracikan/v3/insert.
-- Yang kita punya baru nama: rsmst_listobatbpjses.obat_kronis_bpjs. Nama tidak
-- bisa dipakai — BPJS mencocokkan dengan kode.
--
-- KENAPA DI immst_products, BUKAN DI rsmst_listobatbpjses:
-- Kode DPHO adalah sifat OBATNYA, bukan sifat kekronisannya. Apotek Online
-- melayani tiga jenis: PRB, kronis belum stabil, DAN kemoterapi — sedangkan
-- rsmst_listobatbpjses khusus kronis (158 baris). Menaruh kolom di sana berarti
-- obat PRB & kemo tak punya tempat, dan nanti butuh kolom kedua di tabel lain.
-- Di immst_products (2.840 baris) satu kolom melayani ketiganya, dan Master Obat
-- Kronis tetap bisa menyuntingnya lewat join product_id yang sudah ada.
--
-- Database : Oracle 10g
-- ============================================================

-- Jalankan ulang dari nol? buka blok ini:
-- DROP INDEX idx_immst_products_kode_dpho;
-- ALTER TABLE immst_products DROP COLUMN kode_dpho;

ALTER TABLE immst_products ADD (kode_dpho VARCHAR2(20));

COMMENT ON COLUMN immst_products.kode_dpho IS
    'Kode obat DPHO BPJS (Apotek Online, field KDOBT). NULL = obat ini belum/tidak dipetakan ke DPHO. Sumber: referensi/dpho.';

-- Pencarian BALIK (kode DPHO → obat lokal), dipakai saat mencocokkan hasil
-- referensi/dpho ke master. SENGAJA TIDAK UNIQUE: beberapa merek lokal yang
-- berbeda bisa menunjuk satu entri DPHO yang sama (DPHO memakai nama generik
-- + kekuatan, bukan merek).
CREATE INDEX idx_immst_products_kode_dpho ON immst_products (kode_dpho);

COMMIT;

-- ── Verifikasi pemasangan ────────────────────────────────────
-- SELECT column_name, data_type, data_length FROM user_tab_columns
--  WHERE table_name = 'IMMST_PRODUCTS' AND column_name = 'KODE_DPHO';
-- SELECT index_name, status FROM user_indexes
--  WHERE index_name = 'IDX_IMMST_PRODUCTS_KODE_DPHO';

-- ── Memantau kemajuan pemetaan ───────────────────────────────
-- Obat kronis yang BELUM punya kode DPHO (inilah daftar kerja pengisiannya):
--
-- SELECT l.product_id,
--        p.product_name              AS nama_lokal,
--        l.obat_kronis_bpjs          AS nama_bpjs,
--        l.maxqty,
--        l.tarif_klaim
--   FROM rsmst_listobatbpjses l
--   JOIN immst_products p ON p.product_id = l.product_id
--  WHERE p.kode_dpho IS NULL
--  ORDER BY CASE WHEN l.obat_kronis_bpjs IS NULL THEN 2 ELSE 1 END, l.product_id;
--
-- Catatan urutan di atas: yang nama BPJS-nya SUDAH terisi didahulukan (88 baris),
-- karena namanya tinggal dicocokkan ke hasil referensi/dpho. Yang nama BPJS-nya
-- masih kosong (70 baris per 16/08/2026) perlu dicari dari nama lokalnya dulu —
-- pekerjaan yang lebih berat dan lebih rawan salah pilih.
--
-- Rekap kemajuan:
--
-- SELECT COUNT(*)                                                        AS obat_kronis,
--        SUM(CASE WHEN p.kode_dpho IS NOT NULL THEN 1 ELSE 0 END)        AS sudah_dipetakan,
--        SUM(CASE WHEN p.kode_dpho IS NULL THEN 1 ELSE 0 END)            AS belum
--   FROM rsmst_listobatbpjses l
--   JOIN immst_products p ON p.product_id = l.product_id;

-- ── PERINGATAN PENGISIAN ─────────────────────────────────────
-- JANGAN mengisi kolom ini dengan pencocokan nama otomatis. Nama DPHO memakai
-- pola "<generik> <kekuatan> <pabrik> <bentuk> <kekuatan>" (mis. "Akarbose 100
-- Dexa tab 100 mg") sehingga banyak entri hanya beda kekuatan atau pabrik.
-- Salah satu digit kode berarti mengklaim OBAT YANG BERBEDA, dan kekeliruan itu
-- tidak akan ditolak saat kirim — baru ketahuan (atau tidak sama sekali) saat
-- verifikasi klaim. Pemetaan dilakukan manusia, dengan daftar DPHO sebagai
-- pilihan, bukan sebagai penebak.
