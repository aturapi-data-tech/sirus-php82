-- =============================================================
-- DDL: OPNAME SALDO DP (UANG MUKA) SUPPLIER — PBF & NON-MEDIS
-- Jalankan di Oracle sebagai user pemilik schema SIRUS.
-- Target: Oracle 10g (nama objek <= 30 karakter).
--
-- POLA = OPNAME STOK (TKTXN_SOWHS): satu baris per opname. Saldo awal & transaksi lama
-- TIDAK diubah; selisih antara saldo sistem dan saldo sebenarnya (kartu / rekening koran
-- supplier) dicatat sebagai baris bertanggal → kelihatan ada selisih, berapa, kapan, siapa.
--
-- TIDAK DIJURNAL ke keuangan (keputusan user 2026-11-18: fungsi dasarnya beda, sama
-- dengan opname stok). Saldo Kas / Buku Besar / Neraca tidak membaca tabel ini.
--
-- RUMUS SALDO DP (App\Support\Keuangan\SaldoDpSupplier):
--   saldo awal tahun terakhir yang ada (TKTXN_SALDOAWALHUTANGS[NON].SAH_VALUE_TOP)
--   + setor − terpakai sejak tahun itu
--   + Σ SEMUA opname (so_d − so_k)
-- Opname dijumlah semua tahun karena tutup buku Forms 6i (saldo awal + setor − pakai)
-- tidak mengenal tabel ini. Bila kelak tutup buku DP dipindah ke web dan memasukkan
-- opname ke SAH_VALUE_TOP, rumus harus diubah agar hanya opname sejak tahun saldo awal.
--
-- Kolom:
--   SO_NO         nomor opname (sequence)
--   SUPP_ID       supplier
--   SO_DATE       tanggal-jam opname (SYSDATE saat simpan; hanya tahun berjalan)
--   SALDO_SISTEM  saldo DP menurut sistem saat opname
--   SALDO_FISIK   saldo DP sebenarnya (kartu / rekening koran supplier)
--   SO_D / SO_K   selisih: D = saldo bertambah (fisik > sistem), K = berkurang
--   SO_DESC       alasan (wajib)
--   EMP_ID, SHIFT, USER_CODE   siapa & kapan (users.myuser_code)
-- =============================================================


-- =============================================================
-- BAGIAN A — BUAT (tanpa DROP: tabel ini jejak koreksi saldo, jangan di-DROP oleh skrip.
-- ORA-00955 "nama sudah dipakai" = sudah terpasang, lewati.)
-- Cek dulu: SELECT table_name FROM user_tables WHERE table_name LIKE 'TKTXN_SODPSUPP%';
-- =============================================================

CREATE TABLE TKTXN_SODPSUPPS (
    SO_NO         NUMBER(12)     NOT NULL,
    SUPP_ID       VARCHAR2(25)   NOT NULL,
    SO_DATE       DATE           NOT NULL,
    SALDO_SISTEM  NUMBER(18,2)   DEFAULT 0 NOT NULL,
    SALDO_FISIK   NUMBER(18,2)   DEFAULT 0 NOT NULL,
    SO_D          NUMBER(18,2)   DEFAULT 0 NOT NULL,
    SO_K          NUMBER(18,2)   DEFAULT 0 NOT NULL,
    SO_DESC       VARCHAR2(200)  NOT NULL,
    EMP_ID        VARCHAR2(25),
    SHIFT         VARCHAR2(3),
    USER_CODE     VARCHAR2(25),
    CONSTRAINT PK_TKTXN_SODPSUPPS PRIMARY KEY (SO_NO)
);
CREATE INDEX IX_SODPSUPPS_SUPP ON TKTXN_SODPSUPPS (SUPP_ID, SO_DATE);
CREATE SEQUENCE SODPSUPP_SEQ START WITH 1 INCREMENT BY 1 NOCACHE;

CREATE TABLE TKTXN_SODPSUPPSNON (
    SO_NO         NUMBER(12)     NOT NULL,
    SUPP_ID       VARCHAR2(25)   NOT NULL,
    SO_DATE       DATE           NOT NULL,
    SALDO_SISTEM  NUMBER(18,2)   DEFAULT 0 NOT NULL,
    SALDO_FISIK   NUMBER(18,2)   DEFAULT 0 NOT NULL,
    SO_D          NUMBER(18,2)   DEFAULT 0 NOT NULL,
    SO_K          NUMBER(18,2)   DEFAULT 0 NOT NULL,
    SO_DESC       VARCHAR2(200)  NOT NULL,
    EMP_ID        VARCHAR2(25),
    SHIFT         VARCHAR2(3),
    USER_CODE     VARCHAR2(25),
    CONSTRAINT PK_TKTXN_SODPSUPPSNON PRIMARY KEY (SO_NO)
);
CREATE INDEX IX_SODPSUPPSNON_SUPP ON TKTXN_SODPSUPPSNON (SUPP_ID, SO_DATE);
CREATE SEQUENCE SODPSUPPNON_SEQ START WITH 1 INCREMENT BY 1 NOCACHE;


-- =============================================================
-- BAGIAN B — CEK
-- =============================================================
-- SELECT table_name FROM user_tables WHERE table_name LIKE 'TKTXN_SODPSUPP%';
-- SELECT sequence_name FROM user_sequences WHERE sequence_name LIKE 'SODPSUPP%';
