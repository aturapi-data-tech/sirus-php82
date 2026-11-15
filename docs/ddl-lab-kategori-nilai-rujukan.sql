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
-- Penilaian: angka diambil dari teks hasil ("SKOR : 2" → 2; rentang "1-2" → 2), lalu dicocokkan
-- NILAI_MIN <= angka <= NILAI_MAX (keduanya inklusif; kosong = tak terbatas).
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
    NILAI_MIN     NUMBER,                     -- batas bawah, inklusif; NULL = tak terbatas
    NILAI_MAX     NUMBER,                     -- batas atas, inklusif; NULL = tak terbatas
    LABEL         VARCHAR2(50)    NOT NULL,   -- mis. NEGATIF / BORDERLINE / POSITIF (ASCII: charset Latin-1)
    STATUS        VARCHAR2(1)     NOT NULL,   -- N normal / B borderline / A abnormal
    KRITIS        VARCHAR2(1)     DEFAULT 'N' NOT NULL,   -- Y = hasil di kategori ini ditandai NILAI KRITIS

    CONSTRAINT PK_CLABITEM_KATEGORIS PRIMARY KEY (CLABITEM_ID, URUT),
    CONSTRAINT CK_CLABITEM_KTG_STATUS CHECK (STATUS IN ('N', 'B', 'A')),
    CONSTRAINT CK_CLABITEM_KTG_KRITIS CHECK (KRITIS IN ('Y', 'N')),
    CONSTRAINT CK_CLABITEM_KTG_RENTANG CHECK (NILAI_MIN IS NOT NULL OR NILAI_MAX IS NOT NULL)
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
-- COMMIT;
-- DROP TABLE LBMST_CLABITEM_KATEGORIS;
