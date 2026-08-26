-- ===============================================================
-- SIRUS Approval Hub
-- Tabel universal untuk approval workflow AI → Human → Execute.
-- Pilot: Casemix (ICD coding) — expandable ke SATUSEHAT, billing, dll.
-- Jalankan sebagai user RS (schema owner) di Oracle production.
-- 2026-08-18
-- ===============================================================


-- 1. SEQUENCE
-- ===============================================================
CREATE SEQUENCE seq_approval_queue START WITH 1 INCREMENT BY 1 NOCACHE;


-- 2. TABEL UTAMA
-- ===============================================================
CREATE TABLE RSTXN_APPROVAL_QUEUE (
    approval_id             NUMBER(12)      NOT NULL,

    -- ---- MODUL & REFERENSI ----
    module                  VARCHAR2(30)    NOT NULL,   -- 'casemix', 'satusehat', 'billing', dll
    ref_no                  VARCHAR2(50)    NOT NULL,   -- rj_no / rihdr_no / vno_sep
    ref_type                VARCHAR2(10)    NOT NULL,   -- 'RJ', 'RI', 'UGD'
    reg_no                  VARCHAR2(20),               -- no RM pasien
    reg_name                VARCHAR2(100),              -- nama pasien (snapshot)
    vno_sep                 VARCHAR2(30),               -- nomor SEP (casemix)

    -- ---- DATA AI ----
    ai_payload              CLOB,                       -- JSON: kode ICD, bundle FHIR, dll
    ai_confidence           NUMBER(3)       DEFAULT 0,  -- 0-100
    ai_notes                VARCHAR2(1000),             -- alasan suggest / catatan AI
    ai_model                VARCHAR2(50),               -- model AI yang dipakai

    -- ---- REVIEW HUMAN ----
    status                  VARCHAR2(20)    DEFAULT 'pending' NOT NULL,
    human_payload           CLOB,                       -- JSON: hasil edit (kode ICD dikoreksi, dll)
    reviewer                VARCHAR2(50),               -- username yang review
    review_notes            VARCHAR2(1000),             -- catatan reviewer

    -- ---- EKSEKUSI ----
    exec_status             VARCHAR2(20),               -- 'success', 'failed', 'skipped'
    exec_result             CLOB,                       -- JSON: response E-Klaim / SATUSEHAT / dll
    exec_error              VARCHAR2(1000),             -- pesan error kalau gagal

    -- ---- AUDIT ----
    created_by              VARCHAR2(50)    DEFAULT 'AI_POE',
    created_at              DATE            DEFAULT SYSDATE,
    reviewed_at             DATE,
    executed_at             DATE,
    updated_at              DATE,

    -- ---- CONSTRAINTS ----
    CONSTRAINT pk_approval_queue            PRIMARY KEY (approval_id),

    CONSTRAINT ck_approval_queue_module     CHECK (module IN (
        'casemix', 'satusehat', 'billing', 'emr', 'vclaim'
    )),

    CONSTRAINT ck_approval_queue_reftype    CHECK (ref_type IN ('RJ', 'RI', 'UGD')),

    CONSTRAINT ck_approval_queue_status     CHECK (status IN (
        'pending', 'approved', 'rejected', 'edited', 'executing', 'executed', 'failed'
    )),

    CONSTRAINT ck_approval_queue_execstat   CHECK (exec_status IS NULL OR exec_status IN (
        'success', 'failed', 'skipped'
    )),

    CONSTRAINT ck_approval_queue_confidence CHECK (ai_confidence BETWEEN 0 AND 100)
);


-- 3. INDEX
-- ===============================================================

-- Query paling sering: daftar pending per modul
CREATE INDEX idx_approval_queue_modstat
    ON RSTXN_APPROVAL_QUEUE (module, status);

-- Lookup by referensi (rj_no / rihdr_no)
CREATE INDEX idx_approval_queue_refno
    ON RSTXN_APPROVAL_QUEUE (ref_no);

-- Lookup by SEP (casemix)
CREATE INDEX idx_approval_queue_sep
    ON RSTXN_APPROVAL_QUEUE (vno_sep);

-- Filter by tanggal + status
CREATE INDEX idx_approval_queue_created
    ON RSTXN_APPROVAL_QUEUE (created_at, status);

-- Filter by pasien
CREATE INDEX idx_approval_queue_regno
    ON RSTXN_APPROVAL_QUEUE (reg_no);


-- 4. TRIGGER AUTO-INCREMENT
-- ===============================================================
CREATE OR REPLACE TRIGGER trg_approval_queue_bi
BEFORE INSERT ON RSTXN_APPROVAL_QUEUE
FOR EACH ROW
WHEN (NEW.approval_id IS NULL)
BEGIN
    SELECT seq_approval_queue.NEXTVAL INTO :NEW.approval_id FROM DUAL;
END;
/


-- 5. GRANT READ-ONLY KE USER AI
-- ===============================================================
-- Poe (SIRUS_AI) perlu SELECT + INSERT (tulis antrian).
-- UPDATE hanya untuk set exec_status setelah eksekusi.
-- Tim casemix pakai user RS biasa (sudah punya full access).

GRANT SELECT, INSERT, UPDATE ON RSTXN_APPROVAL_QUEUE TO SIRUS_AI;
GRANT SELECT ON SEQ_APPROVAL_QUEUE TO SIRUS_AI;


-- 6. CONTOH DATA (opsional, hapus kalau gak perlu)
-- ===============================================================
/*
INSERT INTO RSTXN_APPROVAL_QUEUE (
    module, ref_no, ref_type, reg_no, reg_name, vno_sep,
    ai_payload, ai_confidence, ai_notes, status
) VALUES (
    'casemix',
    '12345',
    'RI',
    '000001',
    'PASIEN CONTOH',
    '0089S0010125V000001',
    '{"diagnosa":[{"code":"J18.9","desc":"Pneumonia, unspecified organism","kategori":"Primary","confidence":95},{"code":"E11.9","desc":"Type 2 diabetes mellitus without complications","kategori":"Secondary","confidence":88}],"prosedur":[]}',
    91,
    'Diagnosa diambil dari SOAP + resume medis. Confidence tinggi karena ICD match exact.',
    'pending'
);
COMMIT;
*/


-- ===============================================================
-- VERIFIKASI
-- ===============================================================
-- Jalankan setelah CREATE untuk pastikan semua OK:
--
--   SELECT table_name FROM user_tables WHERE table_name = 'RSTXN_APPROVAL_QUEUE';
--   SELECT sequence_name FROM user_sequences WHERE sequence_name = 'SEQ_APPROVAL_QUEUE';
--   DESC RSTXN_APPROVAL_QUEUE;
--
