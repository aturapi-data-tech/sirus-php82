-- =============================================================
-- DDL: Rujukan Masuk yang DISETUJUI (Rujukan Berbasis Kompetensi / SRBK)
--      Sisi FASKES TUJUAN. Layar /rujukan/masuk.
--
-- Jalankan di Oracle sebagai user pemilik schema SIRUS.
-- Target: Oracle 10g (batas nama objek 30 karakter - semua nama di bawah aman).
--
-- BERSIH-PASANG: berkas ini MENGHAPUS dulu lalu MEMBUAT ulang. Bagian A wajar
-- mengeluarkan error "objek tidak ada" di environment yang masih bersih.
--
-- ⚠️ BAGIAN A MENGHAPUS TABEL BESERTA ISINYA. PERIKSA DULU apakah nama
--    tabelnya sudah terpakai di basis data tujuan (SELECT COUNT(*) dan
--    USER_TAB_COLUMNS) sebelum menjalankan. 26/08 berkas ini menghapus sebuah
--    RSTXN_RUJUKANMASUKS rancangan lama (19 kolom datar, kebetulan senama)
--    yang ada di DB tapi tak pernah masuk repo. Kebetulan isinya kosong.
--
-- APA INI & KENAPA PERLU. Saat kita menyetujui permintaan rujukan masuk, yang
-- terjadi sebelumnya HANYA satu PATCH Task ke SATUSEHAT - tak ada jejak apa pun
-- di basis data kita. Akibatnya:
--   1. petugas UGD tidak punya daftar "siapa saja yang sudah kita setujui dan
--      ditunggu kedatangannya" - informasinya cuma hidup di SATUSEHAT
--   2. saat pasiennya tiba, tak ada yang bisa dipakai mengisi pendaftaran, dan
--      Encounter.basedOn (WAJIB menunjuk ServiceRequest rujukan sejak aturan
--      19/08) tak pernah bisa diisi - tiga pengirim Encounter sudah membaca
--      rujukanMasuk.serviceRequestId, tapi tak ada yang menulisnya.
-- Tabel ini yang menutup lubang itu: menyimpan JANJI rujukan yang disetujui.
--
-- MENYETUJUI BUKAN BERARTI PASIEN DATANG. Pasien bisa disetujui sore ini dan
-- tiba besok, atau tidak datang sama sekali. Karena itu baris di sini BUKAN
-- kunjungan: ia janji yang menunggu. Pendaftaran RJ/UGD/RI dibuat terpisah saat
-- pasiennya benar-benar tiba, lalu nomornya dicatat balik ke node 'pendaftaran'
-- di CLOB ini. Baris tanpa node itu = masih ditunggu.
--
-- TASK_ID DATAR & UNIK - ini inti idempotensinya. Menekan Setujui dua kali,
-- atau menyetujui ulang permintaan yang sama dari perangkat lain, TIDAK boleh
-- melahirkan dua janji. Basis data yang menolaknya (ORA-00001), bukan cuma
-- pemeriksaan di PHP yang bisa kalah balapan. Pelajaran dari insiden 10
-- permintaan kembar di kotak masuk faskes tujuan (26/08).
--
-- Kolom datar lain sengaja TIDAK ada: Oracle di sini tak mendukung JSON_VALUE
-- (ORA-00904), dan volume rujukan masuk kecil (belasan per bulan), jadi
-- penyaringan "belum didaftarkan" cukup di-decode di PHP. Kalau kelak ramai,
-- yang dinaikkan jadi kolom datar adalah penanda sudah-didaftarkan.
-- =============================================================


-- =============================================================
-- BAGIAN A - BERSIHKAN
-- =============================================================
-- Abaikan error berikut kalau objeknya memang belum pernah dibuat:
--   ORA-00942  tabel/view tidak ada
--   ORA-02289  sequence tidak ada
-- Selain dua itu, berhenti dan periksa.

DROP TABLE RSTXN_RUJUKANMASUKS;

DROP SEQUENCE SEQ_RUJUKANMASUKS;


-- =============================================================
-- BAGIAN B - BUAT
-- =============================================================

CREATE TABLE RSTXN_RUJUKANMASUKS (
    RUJUKANMASUK_NO     NUMBER          NOT NULL,   -- PK, dari SEQ_RUJUKANMASUKS
    TASK_ID             VARCHAR2(64)    NOT NULL,   -- id Task SATUSEHAT, penjaga kembar
    RUJUKANMASUK_JSON   CLOB,

    CONSTRAINT PK_RUJUKANMASUKS PRIMARY KEY (RUJUKANMASUK_NO),
    CONSTRAINT UK_RUJUKANMASUK_TASK UNIQUE (TASK_ID)
);

CREATE SEQUENCE SEQ_RUJUKANMASUKS START WITH 1 INCREMENT BY 1 NOCACHE;


-- =============================================================
-- BAGIAN C - KETERANGAN KOLOM
-- =============================================================
-- Tanpa titik koma di dalam teks komentar: pemecah statement per-';' akan
-- memotongnya jadi ORA-01756.

COMMENT ON TABLE RSTXN_RUJUKANMASUKS IS
    'Janji rujukan masuk yang sudah kita setujui (SRBK sisi faskes tujuan). Satu baris = satu permintaan disetujui, BUKAN kunjungan';

COMMENT ON COLUMN RSTXN_RUJUKANMASUKS.RUJUKANMASUK_NO IS
    'PK dari SEQ_RUJUKANMASUKS';

COMMENT ON COLUMN RSTXN_RUJUKANMASUKS.TASK_ID IS
    'id Task SATUSEHAT. UNIK - menyetujui dua kali tidak melahirkan dua janji';

COMMENT ON COLUMN RSTXN_RUJUKANMASUKS.RUJUKANMASUK_JSON IS
    'Isi janji. Node permintaan (salinan dari SATUSEHAT), disetujui (siapa & kapan), pendaftaran (kosong = pasien belum tiba)';


-- =============================================================
-- BAGIAN D - BENTUK RUJUKANMASUK_JSON (acuan, bukan perintah)
-- =============================================================
-- {
--   "permintaan": {
--       "taskId": "5e4f2b01-3a94-42af-b16a-0761e202759a",
--       "noPermintaan": "...",
--       "pasienIhs": "P02478375538",
--       "pasienNama": "",                     <- SATUSEHAT tak memberi nama, lihat catatan
--       "perujukOrgId": "100026236",
--       "perujukNama": "RSUD Dr. Iskak",     <- disalin dari kotak masuk, lihat catatan
--       "dokterPerujuk": "dr. Yuniar Hisa",
--       "encounterPerujukId": "...",          <- Encounter di faskes PERUJUK
--       "diagnosaId": "...", "rencanaId": "...",
--       "jalur": "igd", "layananKode": "L03",
--       "layananNama": "Pelayanan Gawat Darurat",
--       "deskripsi": "..."
--   },
--   "disetujui":   { "oleh": "Nama User", "kode": "ADM", "waktu": "26/08/2026 16:20:11" },
--   "rujukanResmi": { "serviceRequestId": "", "noRujukan": "", "waktu": "" },
--   "pendaftaran": { "regNo": "", "jenis": "", "noKunjungan": null, "waktu": "", "oleh": "" }
-- }
--
-- CATATAN ISI:
--   - pasienNama HAMPIR SELALU KOSONG. Patient/<ihs> dari SATUSEHAT itu cangkang:
--     'name' null dan NIK di-mask '################'. Jadi pencocokan ke pasien
--     lokal HARUS lewat RSMST_PASIENS.PATIENT_UUID, bukan nama. Per 26/08 baru
--     6.242 dari 132.417 pasien (4,7%) punya kolom itu terisi - sisanya perlu
--     dicari manual oleh petugas, dan PATIENT_UUID-nya ditulis balik saat itu
--     supaya cakupannya menambal sendiri seiring waktu.
--   - perujukNama disalin dari kotak masuk (yang mengambilnya lewat GET
--     Organization) supaya daftar tunggu tak perlu memanggil SATUSEHAT lagi cuma
--     untuk menampilkan nama RS. Kosong -> layar jatuh ke Org ID.
--   - rujukanResmi kosong saat disetujui: rujukan resminya baru diterbitkan
--     perujuk SESUDAH kita setuju. Ia dipungut belakangan - tepat sebelum
--     Encounter kunjungannya dibuat - dan itulah yang mengisi Encounter.basedOn.
--     Node terpisah, bukan disusupkan ke permintaan: permintaan adalah salinan
--     apa adanya dari SATUSEHAT saat disetujui dan harus tetap begitu.
--   - pendaftaran kosong = pasien masih ditunggu. Terisi saat pendaftaran
--     RJ/UGD/RI dibuat, sekaligus menandai janji ini selesai.
-- =============================================================
