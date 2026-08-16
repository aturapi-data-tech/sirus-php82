-- ============================================================
-- Tabel: rstxn_rujukanmasuks
-- Deskripsi: Jejak lokal keputusan atas PERMINTAAN RUJUKAN MASUK
--            (SATUSEHAT Rujukan / SRBK, sisi faskes tujuan).
--
--            Layar /rujukan/persetujuan bersumber MURNI dari API
--            SATUSEHAT — tabel ini TIDAK menjadi sumber tampilan.
--            Fungsinya khusus menjawab pertanyaan yang tidak bisa
--            dijawab API: SIAPA petugas yang menyetujui/menolak,
--            KAPAN menurut jam kita, dan APA alasannya.
--
--            Satu baris = satu kali petugas menekan Setujui/Tolak,
--            BERHASIL MAUPUN GAGAL terkirim (lihat http_code).
--            Percobaan yang gagal sengaja ikut dicatat supaya
--            "petugas sudah memutuskan tapi SATUSEHAT menolak"
--            tidak hilang jejaknya.
--
-- Database : Oracle 10g  (tanpa IDENTITY — pakai sequence+trigger,
--            dan NEXTVAL wajib lewat SELECT ... INTO, bukan :=)
-- ============================================================

-- Jalankan ulang dari nol? buka blok ini:
-- DROP TRIGGER  rstxn_rujukanmasuks_bir;
-- DROP TABLE    rstxn_rujukanmasuks;
-- DROP SEQUENCE seq_rstxn_rujukanmasuks;

CREATE TABLE rstxn_rujukanmasuks (
    id                  NUMBER              NOT NULL,

    -- ── Identitas tugas rujukan di SATUSEHAT ──────────────────
    task_id             VARCHAR2(64)        NOT NULL,
    careplan_id         VARCHAR2(64),
    encounter_id        VARCHAR2(64),
    no_permintaan       VARCHAR2(128),

    -- ── Keputusan petugas ────────────────────────────────────
    keputusan           VARCHAR2(10)        NOT NULL,
    alasan              VARCHAR2(500),

    -- ── Siapa & kapan (inti dari tabel ini) ──────────────────
    user_id             NUMBER,
    user_name           VARCHAR2(255),
    date_ref            DATE DEFAULT SYSDATE NOT NULL,

    -- ── Salinan konteks saat keputusan diambil ───────────────
    -- Disalin apa adanya, BUKAN untuk ditampilkan ulang: kalau
    -- perujuk mengubah CarePlan-nya nanti, jejak ini tetap
    -- memperlihatkan apa yang petugas lihat saat memutuskan.
    perujuk_org_id      VARCHAR2(30),
    perujuk_nama        VARCHAR2(255),
    pasien_ihs          VARCHAR2(30),
    pasien_nama         VARCHAR2(255),
    jalur               VARCHAR2(10),
    layanan_kode        VARCHAR2(30),
    layanan_nama        VARCHAR2(255),

    -- '1' = CarePlan diblokir consent SATUSEHAT saat keputusan
    -- diambil, artinya petugas memutuskan TANPA data klinis.
    rencana_diblokir    VARCHAR2(1) DEFAULT '0' NOT NULL,

    -- ── Hasil kirim ke SATUSEHAT ─────────────────────────────
    http_code           NUMBER(5),

    CONSTRAINT pk_rstxn_rujukanmasuks PRIMARY KEY (id),
    CONSTRAINT ck_rujukanmasuks_keputusan
        CHECK (keputusan IN ('accepted', 'rejected')),
    CONSTRAINT ck_rujukanmasuks_jalur
        CHECK (jalur IS NULL OR jalur IN ('ranap', 'igd')),
    CONSTRAINT ck_rujukanmasuks_diblokir
        CHECK (rencana_diblokir IN ('0', '1'))
);

CREATE SEQUENCE seq_rstxn_rujukanmasuks
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;

-- Penelusuran per tugas rujukan. SENGAJA TIDAK UNIQUE: satu task
-- bisa punya beberapa baris bila percobaan pertama gagal terkirim.
CREATE INDEX idx_rujukanmasuks_task ON rstxn_rujukanmasuks (task_id);

-- Rekap harian "siapa menyetujui apa" dan laporan periodik.
CREATE INDEX idx_rujukanmasuks_tgl  ON rstxn_rujukanmasuks (date_ref);

COMMENT ON TABLE  rstxn_rujukanmasuks                  IS 'Jejak keputusan setuju/tolak permintaan rujukan masuk SATUSEHAT (SRBK) — pelengkap audit, bukan sumber tampilan';
COMMENT ON COLUMN rstxn_rujukanmasuks.id               IS 'PK, diisi otomatis oleh trigger rstxn_rujukanmasuks_bir';
COMMENT ON COLUMN rstxn_rujukanmasuks.task_id          IS 'UUID FHIR Task (code=referral-approval-request) yang dijawab';
COMMENT ON COLUMN rstxn_rujukanmasuks.careplan_id      IS 'UUID CarePlan rujukan (Task.basedOn), boleh kosong bila tidak terbaca';
COMMENT ON COLUMN rstxn_rujukanmasuks.encounter_id     IS 'UUID Encounter perujuk (Task.encounter)';
COMMENT ON COLUMN rstxn_rujukanmasuks.no_permintaan    IS 'Task.identifier[0].value dari perujuk, mis. TASK-RBKRI-RJK-100024122-...';
COMMENT ON COLUMN rstxn_rujukanmasuks.keputusan        IS 'accepted | rejected — sama dengan kode yang dikirim ke Task.output';
COMMENT ON COLUMN rstxn_rujukanmasuks.alasan           IS 'Alasan penolakan/persetujuan versi lokal. SATUSEHAT belum menerima teks alasan, jadi ini HANYA tersimpan di sini';
COMMENT ON COLUMN rstxn_rujukanmasuks.user_id          IS 'users.id petugas yang menekan tombol';
COMMENT ON COLUMN rstxn_rujukanmasuks.user_name        IS 'users.myuser_name (fallback users.name) saat keputusan diambil — dibekukan, tidak ikut berubah bila nama user diedit';
COMMENT ON COLUMN rstxn_rujukanmasuks.date_ref         IS 'Waktu keputusan menurut jam server kita (SATUSEHAT memakai UTC)';
COMMENT ON COLUMN rstxn_rujukanmasuks.perujuk_org_id   IS 'Organization id RS perujuk (Task.requester)';
COMMENT ON COLUMN rstxn_rujukanmasuks.perujuk_nama     IS 'Nama RS perujuk hasil GET Organization saat itu';
COMMENT ON COLUMN rstxn_rujukanmasuks.pasien_ihs       IS 'IHS number pasien (Task.for)';
COMMENT ON COLUMN rstxn_rujukanmasuks.pasien_nama      IS 'Nama pasien dari CarePlan.subject.display — kosong bila CarePlan disensor consent';
COMMENT ON COLUMN rstxn_rujukanmasuks.jalur            IS 'ranap | igd, dibaca dari CarePlan.category (736353004 vs TK000068)';
COMMENT ON COLUMN rstxn_rujukanmasuks.layanan_kode     IS 'Kode kelompok layanan yang diminta, mis. LY133';
COMMENT ON COLUMN rstxn_rujukanmasuks.layanan_nama     IS 'Nama kelompok layanan yang diminta';
COMMENT ON COLUMN rstxn_rujukanmasuks.rencana_diblokir IS '1 = CarePlan diblokir consent saat keputusan diambil (petugas memutuskan tanpa data klinis), 0 = terbaca';
COMMENT ON COLUMN rstxn_rujukanmasuks.http_code        IS 'Status HTTP balasan PATCH Task. 2xx = terkirim; selain itu keputusan TIDAK sampai ke SATUSEHAT';

COMMIT;

-- ── Trigger SENGAJA DITARUH PALING AKHIR ─────────────────────
-- Blok PL/SQL yang diakhiri `/` membuat sebagian klien SQL
-- berhenti dan mengabaikan sisa skrip (terjadi 16/08/2026 di dev:
-- tabel & trigger jadi, index dan seluruh COMMENT tidak). Dengan
-- trigger di bawah, yang terkorbankan tinggal trigger itu sendiri
-- — dan itu langsung ketahuan karena INSERT akan gagal NOT NULL.
CREATE OR REPLACE TRIGGER rstxn_rujukanmasuks_bir
    BEFORE INSERT ON rstxn_rujukanmasuks
    FOR EACH ROW
DECLARE
    v_id NUMBER;
BEGIN
    IF :new.id IS NULL THEN
        SELECT seq_rstxn_rujukanmasuks.NEXTVAL INTO v_id FROM dual;
        :new.id := v_id;
    END IF;
END;
/

-- ── Verifikasi pemasangan ────────────────────────────────────
-- SELECT table_name FROM user_tables    WHERE table_name    = 'RSTXN_RUJUKANMASUKS';
-- SELECT sequence_name FROM user_sequences WHERE sequence_name = 'SEQ_RSTXN_RUJUKANMASUKS';
-- SELECT trigger_name, status FROM user_triggers WHERE trigger_name = 'RSTXN_RUJUKANMASUKS_BIR';
--
-- Uji trigger (jangan dijalankan di produksi):
-- INSERT INTO rstxn_rujukanmasuks (task_id, keputusan, user_name, http_code)
--      VALUES ('uji-trigger', 'rejected', 'UJI', 200);
-- SELECT id, task_id, keputusan, date_ref FROM rstxn_rujukanmasuks WHERE task_id = 'uji-trigger';
-- ROLLBACK;
