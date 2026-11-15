-- =============================================================
-- DDL: JENIS NILAI RUJUKAN "KATEGORI" UNTUK ITEM LAB (mis. TUBEX TF)
-- Jalankan di Oracle sebagai user pemilik schema SIRUS, di SETIAP database (lokal, dev, produksi).
-- Target: Oracle 10g (nama objek <= 30 karakter).
--
-- MASALAH YANG DISELESAIKAN. Item lab berjenis "Teks" (lowhigh_status kosong/N) dinilai dengan
-- membandingkan hasil HURUF PER HURUF dengan teks nilai normal. TUBEX (normal "SKOR : 0-2 (NEGATIF);
-- SKOR :3 (BORDERLINE); SKOR >4 (POSITIF)") karena itu SELALU "Abnormal", termasuk skor 0 dan 2.
--
-- JENIS NILAI RUJUKAN di lbmst_clabitems.lowhigh_status (kolom lama, VARCHAR2(3), tanpa CHECK):
--   'Y'          = Rentang Angka (Low/High)          → sistem lama, tidak berubah
--   'N' / kosong = Teks Deskriptif (cocok persis)    → sistem lama, tidak berubah
--   'K'          = Kategori (BARU)                    → baris rentang berlabel di tabel di bawah
-- Kode yang belum mengenal 'K' (termasuk aplikasi 6i) membacanya seperti Teks dan menampilkan
-- normal_m/normal_f, yang diisi otomatis dari kategori oleh Master Laborat.
--
-- STATUS HASIL di lbtxn_checkupdtls.lab_result_status (VARCHAR2(3), tanpa CHECK):
--   kategori N (Normal)     → NULL (tidak ditandai, sama seperti hasil normal sistem lama)
--   kategori B (Borderline) → 'B'  (BARU, tampil kuning "Borderline" / label kategori)
--   kategori A (Abnormal)   → 'R'  (sudah dikenal: "Abnormal")
-- SATU BARIS = SATU KATEGORI, berupa SALAH SATU dari:
--   - TEKS HASIL (NILAI_TEKS): alternatif dipisah "|", "*" = wildcard (mis. 'NEGATIF|NEG', '+1|+2', '*').
--     Dibandingkan setelah dinormalkan: huruf besar, tanpa spasi & tanda kurung ("(+1)" = "+1").
--   - RENTANG ANGKA (NILAI_MIN/NILAI_MAX, inklusif; kosong = tak terbatas): angka diambil dari teks hasil
--     ("SKOR : 2" → 2; "1-2" → 2; "1/200" → 200).
-- Baris dicocokkan menurut URUT, yang pertama cocok dipakai. Tidak cocok: hasil berangka atau item punya
-- kategori teks → 'R'; selain itu tidak ditandai.
-- Kode: App\Support\KategoriNilaiRujukanLab.
--
-- AMAN DIPASANG KAPAN SAJA. Selama tabel belum ada, layar lab tetap jalan (kategori dibaca kosong);
-- hanya menyimpan item berjenis Kategori di Master Laborat yang menampilkan pesan "jalankan DDL".
-- Belum dipakai laporan Nilai Kritis (manajemen) — kritis per kategori baru tampil di layar & cetak hasil.
-- =============================================================


-- =============================================================
-- BAGIAN A — BUAT TABEL
-- Tanpa DROP. ORA-00955 "nama sudah dipakai" = sudah terpasang atau ada tabel senama:
-- berhenti dan periksa dulu (SELECT * FROM user_tables WHERE table_name LIKE 'LBMST_CLABITEM%').
-- =============================================================

CREATE TABLE LBMST_CLABITEM_KATEGORIS (
    CLABITEM_ID   VARCHAR2(10)    NOT NULL,   -- = lbmst_clabitems.clabitem_id
    URUT          NUMBER(3)       NOT NULL,   -- urutan tampil & pencocokan (kategori pertama yang cocok dipakai)
    NILAI_TEKS    VARCHAR2(100),              -- pola teks hasil ('A|B', '*' wildcard); NULL = baris rentang angka
    NILAI_MIN     NUMBER,                     -- batas bawah, inklusif; NULL = tak terbatas
    NILAI_MAX     NUMBER,                     -- batas atas, inklusif; NULL = tak terbatas
    LABEL         VARCHAR2(50)    NOT NULL,   -- mis. NEGATIF / BORDERLINE / POSITIF (ASCII: charset Latin-1)
    STATUS        VARCHAR2(1)     NOT NULL,   -- N normal / B borderline / A abnormal
    KRITIS        VARCHAR2(1)     DEFAULT 'N' NOT NULL,   -- Y = hasil di kategori ini ditandai NILAI KRITIS

    CONSTRAINT PK_CLABITEM_KATEGORIS PRIMARY KEY (CLABITEM_ID, URUT),
    CONSTRAINT CK_CLABITEM_KTG_STATUS CHECK (STATUS IN ('N', 'B', 'A')),
    CONSTRAINT CK_CLABITEM_KTG_KRITIS CHECK (KRITIS IN ('Y', 'N')),
    CONSTRAINT CK_CLABITEM_KTG_ISI CHECK (
        (NILAI_TEKS IS NOT NULL AND NILAI_MIN IS NULL AND NILAI_MAX IS NULL)
        OR (NILAI_TEKS IS NULL AND (NILAI_MIN IS NOT NULL OR NILAI_MAX IS NOT NULL)))
);


-- =============================================================
-- BAGIAN B — DATA AWAL: TUBEX TF (IGM SALMONELLA), TU00211
-- 0-2 NEGATIF (normal) · 3 BORDERLINE · >= 4 POSITIF (abnormal). Tidak ada yang kritis;
-- centang Kritis bisa diatur di Master Laborat.
-- =============================================================

INSERT INTO LBMST_CLABITEM_KATEGORIS (CLABITEM_ID, URUT, NILAI_MIN, NILAI_MAX, LABEL, STATUS, KRITIS)
VALUES ('TU00211', 1, 0, 2, 'NEGATIF', 'N', 'N');
INSERT INTO LBMST_CLABITEM_KATEGORIS (CLABITEM_ID, URUT, NILAI_MIN, NILAI_MAX, LABEL, STATUS, KRITIS)
VALUES ('TU00211', 2, 3, 3, 'BORDERLINE', 'B', 'N');
INSERT INTO LBMST_CLABITEM_KATEGORIS (CLABITEM_ID, URUT, NILAI_MIN, NILAI_MAX, LABEL, STATUS, KRITIS)
VALUES ('TU00211', 3, 4, NULL, 'POSITIF', 'A', 'N');

-- Pindahkan TUBEX ke jenis Kategori. Teks rujukan = gabungan kategori (sama dengan yang dibuat Master Laborat).
UPDATE lbmst_clabitems
   SET lowhigh_status = 'K',
       normal_m = '0-2 NEGATIF ; 3 BORDERLINE ; >= 4 POSITIF',
       normal_f = '0-2 NEGATIF ; 3 BORDERLINE ; >= 4 POSITIF',
       nilai_kritis = 'N'
 WHERE clabitem_id = 'TU00211';

COMMIT;


-- =============================================================
-- BAGIAN B2 — DATA AWAL LAIN (hanya menerjemahkan nilai rujukan yang SUDAH tertulis di master;
-- tidak mengubah kesepakatan klinis). normal_m/normal_f tidak diubah.
-- =============================================================

-- Urine sedimen (rujukan 0-3): LE00037 Leukosit, EP00038 Epitel, ER00036 Eritrosit.
-- Dulu "1-2", "2-3", "0-1" ikut Abnormal karena tidak sama persis dengan teks "0-3".
INSERT INTO LBMST_CLABITEM_KATEGORIS (CLABITEM_ID, URUT, NILAI_TEKS, NILAI_MIN, NILAI_MAX, LABEL, STATUS, KRITIS)
SELECT clabitem_id, 1, 'BANYAK|PENUH*', NULL, NULL, 'BANYAK', 'A', 'N' FROM lbmst_clabitems WHERE clabitem_id IN ('LE00037', 'EP00038', 'ER00036');
INSERT INTO LBMST_CLABITEM_KATEGORIS (CLABITEM_ID, URUT, NILAI_TEKS, NILAI_MIN, NILAI_MAX, LABEL, STATUS, KRITIS)
SELECT clabitem_id, 2, NULL, 0, 3, 'NORMAL', 'N', 'N' FROM lbmst_clabitems WHERE clabitem_id IN ('LE00037', 'EP00038', 'ER00036');
INSERT INTO LBMST_CLABITEM_KATEGORIS (CLABITEM_ID, URUT, NILAI_TEKS, NILAI_MIN, NILAI_MAX, LABEL, STATUS, KRITIS)
SELECT clabitem_id, 3, NULL, 4, NULL, 'MENINGKAT', 'A', 'N' FROM lbmst_clabitems WHERE clabitem_id IN ('LE00037', 'EP00038', 'ER00036');

-- Urine dipstik (rujukan NEGATIF): AL00031 Albumin, RE00033 Reduksi, KE00035 Keton, BI00032 Bilirubin,
-- NI00172 Nitrit. Perilaku sama dengan sekarang (selain NEGATIF = Abnormal), tetapi "negatif" huruf kecil &
-- salah ketik "NEAGTIF" tidak lagi ditandai Abnormal.
-- UR00034 Urobilinogen SENGAJA TIDAK diikutkan: rujukan di master "NEGATIF", padahal lab melaporkan "NORMAL",
-- +1, +2 (urobilinogen sedikit memang normal). Tentukan dulu kategorinya bersama laboratorium.
INSERT INTO LBMST_CLABITEM_KATEGORIS (CLABITEM_ID, URUT, NILAI_TEKS, NILAI_MIN, NILAI_MAX, LABEL, STATUS, KRITIS)
SELECT clabitem_id, 1, 'NEGATIF|NEG|NEAGTIF|-', NULL, NULL, 'NEGATIF', 'N', 'N' FROM lbmst_clabitems
 WHERE clabitem_id IN ('AL00031', 'RE00033', 'KE00035', 'BI00032', 'NI00172');
-- Tingkat positif per baris supaya penulisannya baku (pilihan pertama = yang disimpan); semua tetap Abnormal.
INSERT INTO LBMST_CLABITEM_KATEGORIS (CLABITEM_ID, URUT, NILAI_TEKS, NILAI_MIN, NILAI_MAX, LABEL, STATUS, KRITIS)
SELECT clabitem_id, 2, '+1|+|1+', NULL, NULL, 'POSITIF', 'A', 'N' FROM lbmst_clabitems
 WHERE clabitem_id IN ('AL00031', 'RE00033', 'KE00035', 'BI00032', 'NI00172');
INSERT INTO LBMST_CLABITEM_KATEGORIS (CLABITEM_ID, URUT, NILAI_TEKS, NILAI_MIN, NILAI_MAX, LABEL, STATUS, KRITIS)
SELECT clabitem_id, 3, '+2|++|2+', NULL, NULL, 'POSITIF', 'A', 'N' FROM lbmst_clabitems
 WHERE clabitem_id IN ('AL00031', 'RE00033', 'KE00035', 'BI00032', 'NI00172');
INSERT INTO LBMST_CLABITEM_KATEGORIS (CLABITEM_ID, URUT, NILAI_TEKS, NILAI_MIN, NILAI_MAX, LABEL, STATUS, KRITIS)
SELECT clabitem_id, 4, '+3|+++|3+', NULL, NULL, 'POSITIF', 'A', 'N' FROM lbmst_clabitems
 WHERE clabitem_id IN ('AL00031', 'RE00033', 'KE00035', 'BI00032', 'NI00172');
INSERT INTO LBMST_CLABITEM_KATEGORIS (CLABITEM_ID, URUT, NILAI_TEKS, NILAI_MIN, NILAI_MAX, LABEL, STATUS, KRITIS)
SELECT clabitem_id, 5, '+4|++++|4+', NULL, NULL, 'POSITIF', 'A', 'N' FROM lbmst_clabitems
 WHERE clabitem_id IN ('AL00031', 'RE00033', 'KE00035', 'BI00032', 'NI00172');
INSERT INTO LBMST_CLABITEM_KATEGORIS (CLABITEM_ID, URUT, NILAI_TEKS, NILAI_MIN, NILAI_MAX, LABEL, STATUS, KRITIS)
SELECT clabitem_id, 6, '*', NULL, NULL, 'POSITIF', 'A', 'N' FROM lbmst_clabitems
 WHERE clabitem_id IN ('AL00031', 'RE00033', 'KE00035', 'BI00032', 'NI00172');

-- Urine sedimen Sylinder SY00039 & Kristal KR00040 (rujukan NEGATIF): temuan apa pun = Abnormal.
INSERT INTO LBMST_CLABITEM_KATEGORIS (CLABITEM_ID, URUT, NILAI_TEKS, NILAI_MIN, NILAI_MAX, LABEL, STATUS, KRITIS)
SELECT clabitem_id, 1, 'NEGATIF|NEG|NEAGTIF|-', NULL, NULL, 'NEGATIF', 'N', 'N' FROM lbmst_clabitems WHERE clabitem_id IN ('SY00039', 'KR00040');
INSERT INTO LBMST_CLABITEM_KATEGORIS (CLABITEM_ID, URUT, NILAI_TEKS, NILAI_MIN, NILAI_MAX, LABEL, STATUS, KRITIS)
SELECT clabitem_id, 2, '*', NULL, NULL, 'DITEMUKAN', 'A', 'N' FROM lbmst_clabitems WHERE clabitem_id IN ('SY00039', 'KR00040');

UPDATE lbmst_clabitems SET lowhigh_status = 'K'
 WHERE clabitem_id IN ('LE00037', 'EP00038', 'ER00036', 'AL00031', 'RE00033', 'KE00035', 'BI00032', 'NI00172', 'SY00039', 'KR00040')
   AND EXISTS (SELECT 1 FROM LBMST_CLABITEM_KATEGORIS k WHERE k.clabitem_id = lbmst_clabitems.clabitem_id);

COMMIT;

-- -------------------------------------------------------------
-- CONTOH OPSIONAL — KEPUTUSAN KLINIS, jalankan hanya bila disetujui laboratorium / dokter:
--
-- (1) Dipstik "+/-" (trace) sebagai BORDERLINE (kuning); tanpa ini "+/-" jatuh ke baris '*' = POSITIF:
-- INSERT INTO LBMST_CLABITEM_KATEGORIS (CLABITEM_ID, URUT, NILAI_TEKS, NILAI_MIN, NILAI_MAX, LABEL, STATUS, KRITIS)
-- SELECT clabitem_id, 7, '+/-|TRACE|+-', NULL, NULL, 'TRACE', 'B', 'N' FROM lbmst_clabitems WHERE clabitem_id IN ('AL00031','RE00033','KE00035','BI00032','NI00172');
-- lalu pindahkan baris '*' ke paling akhir:  UPDATE LBMST_CLABITEM_KATEGORIS SET URUT = 99 WHERE NILAI_TEKS = '*' AND clabitem_id IN (...);
--     dan baris TRACE ke urutan 2 (geser yang lain) — lebih mudah lewat Master Laborat.
--
-- (2) Widal (S.00022 O, S.00023 H, S.00024 Paratyphi A, S.00025 Paratyphi B) dengan ambang titer.
--     "1/200" dibaca 200. Ambang di bawah HANYA CONTOH (1/160) — sesuaikan kebijakan lab:
-- INSERT ... (clabitem_id, 1, 'NEGATIF|NEG', NULL, NULL, 'NEGATIF', 'N', 'N')
-- INSERT ... (clabitem_id, 2, NULL, 0, 80, 'TIDAK BERMAKNA', 'N', 'N')
-- INSERT ... (clabitem_id, 3, NULL, 160, NULL, 'BERMAKNA', 'A', 'N')
-- UPDATE lbmst_clabitems SET lowhigh_status = 'K' WHERE clabitem_id IN ('S.00022','S.00023','S.00024','S.00025');
--
-- (3) Plano Test PL00049: POSITIF = hamil, bukan "Abnormal". Mis.: 'NEGATIF' N label NEGATIF;
--     'POSITIF' N label POSITIF (tanpa tanda) — atau Borderline bila ingin tetap disorot.
-- Semua bisa juga diatur lewat Master Laborat (jenis Semikuantitatif) tanpa SQL.
-- -------------------------------------------------------------


-- =============================================================
-- BAGIAN C — HITUNG ULANG STATUS HASIL TUBEX LAMA
-- Sebelumnya semua hasil TUBEX berstatus 'R' (Abnormal). Angka pertama di teks hasil dipakai
-- ("SKOR : 2", "SKOR 2", "2" → 2). Hasil tanpa angka → NULL (tidak ditandai).
-- Jalankan BAGIAN D (sebelum) dulu bila ingin mencatat sebaran lama.
-- =============================================================

UPDATE lbtxn_checkupdtls
   SET lab_result_status =
       CASE
           WHEN REGEXP_SUBSTR(lab_result, '[0-9]+') IS NULL THEN NULL
           WHEN TO_NUMBER(REGEXP_SUBSTR(lab_result, '[0-9]+')) <= 2 THEN NULL
           WHEN TO_NUMBER(REGEXP_SUBSTR(lab_result, '[0-9]+')) = 3 THEN 'B'
           ELSE 'R'
       END
 WHERE clabitem_id = 'TU00211';

COMMIT;


-- =============================================================
-- BAGIAN C2 — CABUT TANDA ABNORMAL YANG SALAH PADA HASIL LAMA ITEM BAGIAN B2
-- KONSERVATIF: hanya mengubah 'R' → NULL untuk hasil yang menurut kategori NORMAL. Tidak menambah tanda
-- baru ke data lama (ribuan hasil positif lama berstatus kosong dari sistem lama — dibiarkan).
-- Hasil baru dinilai penuh oleh aplikasi. Normalisasi = huruf besar tanpa spasi & tanda kurung.
-- =============================================================

-- Dipstik, Sylinder, Kristal: "negatif" / "NEG" / "NEAGTIF" / "-" yang tertandai Abnormal → normal.
UPDATE lbtxn_checkupdtls
   SET lab_result_status = NULL
 WHERE clabitem_id IN ('AL00031', 'RE00033', 'KE00035', 'BI00032', 'NI00172', 'SY00039', 'KR00040')
   AND lab_result_status = 'R'
   AND UPPER(REGEXP_REPLACE(lab_result, '[[:space:]()]', '')) IN ('NEGATIF', 'NEG', 'NEAGTIF', '-');

-- Sedimen 0-3: "1-2", "2-3", "0-1", "3" dst. (angka terbesar <= 3, bukan BANYAK/PENUH) yang tertandai Abnormal → normal.
UPDATE lbtxn_checkupdtls
   SET lab_result_status = NULL
 WHERE clabitem_id IN ('LE00037', 'EP00038', 'ER00036')
   AND lab_result_status = 'R'
   AND UPPER(REGEXP_REPLACE(lab_result, '[[:space:]()]', '')) NOT LIKE 'BANYAK'
   AND UPPER(REGEXP_REPLACE(lab_result, '[[:space:]()]', '')) NOT LIKE 'PENUH%'
   AND REGEXP_SUBSTR(lab_result, '[0-9]+') IS NOT NULL
   AND GREATEST(TO_NUMBER(REGEXP_SUBSTR(lab_result, '[0-9]+', 1, 1)),
                NVL(TO_NUMBER(REGEXP_SUBSTR(lab_result, '[0-9]+', 1, 2)), 0)) <= 3;

COMMIT;


-- =============================================================
-- BAGIAN D — CEK
-- =============================================================
-- SELECT * FROM LBMST_CLABITEM_KATEGORIS ORDER BY CLABITEM_ID, URUT;
-- SELECT clabitem_id, lowhigh_status, normal_m FROM lbmst_clabitems WHERE lowhigh_status = 'K';
-- SELECT TRIM(lab_result) hasil, NVL(lab_result_status, '-') status, COUNT(*) jumlah
--   FROM lbtxn_checkupdtls WHERE clabitem_id = 'TU00211'
--  GROUP BY TRIM(lab_result), NVL(lab_result_status, '-') ORDER BY 3 DESC;


-- =============================================================
-- BAGIAN E — KEMBALIKAN (HANYA bila fitur dibatalkan)
-- Item kembali ke jenis Teks dengan teks rujukan lama; status hasil lama TIDAK dikembalikan
-- otomatis (jalankan ulang penilaian per checkup bila perlu). DROP sengaja dikomentari.
-- =============================================================
-- UPDATE lbmst_clabitems
--    SET lowhigh_status = NULL,
--        normal_m = 'SKOR :  0-2 (NEGATIF) ;  SKOR :3 (BORDERLINE) ; SKOR >4 (POSITIF)',
--        normal_f = 'SKOR :  0-2 (NEGATIF) ;  SKOR : 3 (BORDERLINE) ; SKOR :  >4 (POSITIF)'
--  WHERE clabitem_id = 'TU00211';
-- DELETE FROM LBMST_CLABITEM_KATEGORIS WHERE clabitem_id = 'TU00211';
-- Item Bagian B2: UPDATE lbmst_clabitems SET lowhigh_status = NULL WHERE clabitem_id IN (...B2...);
--                 DELETE FROM LBMST_CLABITEM_KATEGORIS WHERE clabitem_id IN (...B2...);
-- COMMIT;
-- DROP TABLE LBMST_CLABITEM_KATEGORIS;
