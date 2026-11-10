-- =============================================================
-- DDL: LOG KIRIM DINKESTA (Portal Satu Data Kesehatan Tulungagung)
-- Jalankan di Oracle sebagai user pemilik schema SIRUS.
-- Target: Oracle 10g (batas nama objek 30 karakter — semua nama di bawah aman).
--
-- KENAPA TABEL INI ADA. Penanda "sudah dikirim ke DINKESTA" versi lama (sirus-lite)
-- ditulis ke JSON kunjungan (datadaftarpolirj_json, key satuDataKesehatanTulungagung).
-- Kirim bulk per bulan berarti ratusan read-modify-write ke CLOB EMR yang sedang
-- dipakai petugas — rawan menimpa / mengunci. Dengan tabel ini halaman
-- /dinkesta/kirim-rj TIDAK PERNAH menulis JSON kunjungan; ICD-10 dibaca dari
-- rstxn_rjdtls + rsmst_mstdiags.
--
-- SATU BARIS = SATU ICD-10 SATU KUNJUNGAN. Primary key (STATUS_RJRI, REF_NO, ICDX)
-- itulah penjaga anti-dobel: sebelum POST, aplikasi "mengklaim" baris (INSERT status P,
-- atau UPDATE dari G ke P). Dua proses bersamaan tak bisa mengklaim ICD yang sama.
--
-- KIRIM_STATUS:
--   P = sedang dikirim (diklaim, balasan belum dicatat)
--   S = sukses: "pesan" balasan memuat "berhasil"   → tak pernah dikirim lagi
--   G = gagal pasti: "pesan" lain (mis. "Data tidak lengkap!"), HTTP 4xx, gagal sambung
--                                                    → bulk berikutnya mengirim ulang
--   T = tidak pasti: timeout SETELAH tersambung, HTTP 5xx, balasan tanpa "pesan"
--                                                    → TIDAK dikirim ulang otomatis;
--       cek di portal, lalu "Kirim ulang" manual per baris bila memang belum masuk.
-- PERHATIAN: portal membalas HTTP 200 untuk SEMUA kasus, termasuk data ditolak —
-- kode HTTP BUKAN penentu sukses (uji ke portal asli 10/11/2026).
--   P yang tertinggal > 15 menit (proses mati di tengah) diperlakukan seperti T.
--
-- STATUS_RJRI disiapkan untuk UGD / RI ('UGD', 'RI') bila kelak dibuat.
-- Kunjungan yang dikirim dari sirus-lite (penanda di JSON) tidak dimigrasi ke sini;
-- aplikasi mengenalinya dengan dbms_lob.instr (baca saja) dan melewatinya.
--
-- AMAN DIPASANG KAPAN SAJA. Selama tabel belum ada, halaman menampilkan peringatan
-- dan tombol kirim dinonaktifkan.
-- =============================================================


-- =============================================================
-- BAGIAN A — BUAT (TANPA bagian hapus: tabel ini bukti pengiriman ke Dinkes,
-- jangan pernah di-DROP oleh skrip. ORA-00955 "nama sudah dipakai" = sudah
-- terpasang atau ada tabel senama — berhenti dan periksa, jangan dipaksa.)
-- =============================================================

CREATE TABLE RSTXN_DINKESTA_KIRIMS (
    STATUS_RJRI   VARCHAR2(3)     NOT NULL,   -- 'RJ' (kelak 'UGD' / 'RI')
    REF_NO        NUMBER          NOT NULL,   -- rj_no
    ICDX          VARCHAR2(10)    NOT NULL,   -- rsmst_mstdiags.icdx yang dikirim
    KIRIM_STATUS  VARCHAR2(1)     NOT NULL,   -- P / S / G / T (lihat kepala berkas)
    KIRIM_DATE    DATE            NOT NULL,   -- waktu klaim / balasan terakhir
    KIRIM_USER    VARCHAR2(50),               -- myuser_name pengirim
    HTTP_CODE     NUMBER(3),                  -- kode HTTP balasan (408 = timeout/gagal sambung)
    RESPON        VARCHAR2(2000),             -- potongan balasan portal / pesan galat

    CONSTRAINT PK_DINKESTA_KIRIMS PRIMARY KEY (STATUS_RJRI, REF_NO, ICDX),
    CONSTRAINT CK_DINKESTA_KIRIM_STATUS CHECK (KIRIM_STATUS IN ('P', 'S', 'G', 'T'))
);


-- =============================================================
-- BAGIAN B — CEK
-- =============================================================
-- SELECT KIRIM_STATUS, COUNT(*) FROM RSTXN_DINKESTA_KIRIMS GROUP BY KIRIM_STATUS;


-- =============================================================
-- BAGIAN C — KIRIMAN UJI COBA 10/11/2026 (WAJIB dijalankan sekali, di DB PRODUKSI)
-- Satu ICD ini sudah tersimpan di portal saat uji coba ("Data berhasil disimpan").
-- Tanpa baris ini, bulk Januari 2026 akan mengirimnya lagi (dobel di portal).
-- =============================================================
INSERT INTO RSTXN_DINKESTA_KIRIMS (STATUS_RJRI, REF_NO, ICDX, KIRIM_STATUS, KIRIM_DATE, KIRIM_USER, HTTP_CODE, RESPON)
VALUES ('RJ', 619433, 'G44.2', 'S', TO_DATE('10/11/2026', 'DD/MM/YYYY'), 'UJI-COBA', 200, 'Data berhasil disimpan (uji coba 10/11/2026)');
COMMIT;
