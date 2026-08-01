-- database/sql/2026_07_31_view_docsalaries_ok_rj_ugd.sql
-- ===============================================================
-- RSVIEW_NEWDOCSALARIES — tambah pendapatan dokter dari operasi RJ & UGD
--
-- MASALAH:
--   Dua cabang operasi yang sudah ada (DESC_DOC 'OPERATOR' & 'ANASTESI')
--   mengambil data lewat:
--       FROM RSTXN_OKS a JOIN RSTXN_RIHDRS b ON a.rihdr_no = b.rihdr_no
--   Sesudah rstxn_oks bisa menampung RJ/UGD (rihdr_no NULL), inner join itu
--   membuang seluruh operasi RJ/UGD — jasa dokter operator & anestesi dari
--   sana TIDAK terhitung sama sekali.
--
-- PERUBAHAN:
--   Menambah 4 cabang UNION ALL (GROUP_SEQ 19-22), TANPA menyentuh 18 cabang
--   yang sudah ada. Angka laporan yang lama karena itu tidak berubah;
--   yang muncul hanya baris baru untuk operasi RJ/UGD.
--
--     seq 19  OKRJ   OPERATOR RJ     oprdoc_fee  atas dr_id
--     seq 20  OKRJ   ANASTESI RJ     anesdoc_fee atas dr_id_ok
--     seq 21  OKUGD  OPERATOR UGD    oprdoc_fee  atas dr_id
--     seq 22  OKUGD  ANASTESI UGD    anesdoc_fee atas dr_id_ok
--
--   Penamaan GROUP_DOC OKRJ/OKUGD mengikuti pola RADRJ/RADUGD/RADRI yang sudah
--   dipakai view ini. GROUP_DOC 'OK' yang lama SENGAJA tidak diganti jadi
--   'OKRI' — mengubahnya akan menggeser pengelompokan laporan yang sudah jalan.
--
-- SYARAT STATUS (mengikuti konvensi view ini sendiri):
--   RI      : b.ri_status = 'P'   (sudah ada, tidak diubah)
--   RJ/UGD  : b.rj_status = 'L'   (sama dengan cabang JD RJ / JD UGD / UP UGD)
--   Ditambah a.ok_status = 'L' — hanya operasi yang biayanya sudah ditransfer,
--   sama seperti dua cabang RI yang lama.
--
--   Join memakai a.ref_no (bukan a.rihdr_no) karena RJ/UGD merujuk rj_no.
--   Filter a.status_rjri memastikan tiap cabang hanya mengambil layanannya.
--
-- MENYUSUL DI SISI APLIKASI:
--   pendapatan-jasa-dokter.blade.php::fetchSourceJson() perlu case untuk 4
--   DESC_DOC baru; saat ini jatuh ke `default: return null` sehingga JSON
--   pasien (SEP/klaim) tidak ter-resolve untuk baris RJ/UGD.
--
-- JALANKAN: seluruh isi file ini sekali jalan.
-- ROLLBACK: jalankan ulang CREATE OR REPLACE dengan definisi lama
--           (cadangan: SELECT text FROM user_views WHERE view_name='RSVIEW_NEWDOCSALARIES').
-- ===============================================================

CREATE OR REPLACE VIEW RSVIEW_NEWDOCSALARIES AS
SELECT
    'RJ'                              AS GROUP_DOC,
    'UP RJ'                           AS DESC_DOC,
    a.dr_id                           AS DR_ID,
    a.dr_name                         AS DR_NAME,
    TO_CHAR(b.rj_date, 'DD/MM/YYYY')  AS DOC_DATE,
    SUM(b.poli_price)                 AS DOC_NOMINAL,
    COUNT(*)                          AS JML_PASIEN,
    1                                 AS GROUP_SEQ,
    b.rj_no                           AS TXN_NO,
    b.klaim_id                        AS KLAIM_ID
FROM
    RSMST_DOCTORS       a
    JOIN RSTXN_RJHDRS   b ON a.dr_id = b.dr_id
WHERE
    b.rj_status = 'L'
GROUP BY
    a.dr_id,
    a.dr_name,
    TO_CHAR(b.rj_date, 'DD/MM/YYYY'),
    b.rj_status,
    b.rj_no,
    b.klaim_id 

UNION ALL

SELECT
    'RJ'                              AS GROUP_DOC,
    'JD RJ'                           AS DESC_DOC,
    NVL(c.dr_id, b.dr_id)             AS DR_ID,
    d.dr_name                         AS DR_NAME,
    TO_CHAR(b.rj_date, 'DD/MM/YYYY')  AS DOC_DATE,
    SUM(c.accdoc_price)               AS DOC_NOMINAL,
    COUNT(*)                          AS JML_PASIEN,
    2                                 AS GROUP_SEQ,
    RJHN_DTL                          AS TXN_NO,
    b.klaim_id                        AS KLAIM_ID
    
FROM
    RSTXN_RJHDRS       b
    JOIN RSTXN_RJACCDOCS c ON b.rj_no = c.rj_no
    LEFT JOIN RSMST_DOCTORS d
        ON d.dr_id = NVL(c.dr_id, b.dr_id)
WHERE
    b.rj_status = 'L'
GROUP BY
    NVL(c.dr_id, b.dr_id),
    d.dr_name,
    TO_CHAR(b.rj_date, 'DD/MM/YYYY'),
    RJHN_DTL,
    b.klaim_id

UNION ALL

SELECT
    'RJTRF'                            AS GROUP_DOC,
    'UP RJTRF'                         AS DESC_DOC,
    a.dr_id                            AS DR_ID,
    a.dr_name                          AS DR_NAME,
    TO_CHAR(c.exit_date, 'DD/MM/YYYY') AS DOC_DATE,
    SUM(d.poli_price)                  AS DOC_NOMINAL,
    COUNT(*)                           AS JML_PASIEN,
    3                                  AS GROUP_SEQ,
    c.rihdr_no                         AS TXN_NO,
    c.klaim_id                        AS KLAIM_ID
    FROM
    RSTXN_RJHDRS       b
    JOIN RSTXN_RITEMPADMINS d
      ON d.tempadm_ref  = b.rj_no
     AND d.tempadm_flag = 'RJ'
    JOIN RSTXN_RIHDRS   c
      ON c.rihdr_no     = d.rihdr_no
    JOIN RSMST_DOCTORS  a
      ON a.dr_id        = b.dr_id
where
ri_status='P'
GROUP BY
    a.dr_id,
    a.dr_name,
    TO_CHAR(c.exit_date, 'DD/MM/YYYY'),
    c.rihdr_no,
    c.klaim_id


UNION ALL

SELECT
    'RJTRF'                             AS GROUP_DOC,
    'JD RJTRF'                          AS DESC_DOC,
    NVL(e.dr_id, b.dr_id)               AS DR_ID,
    a.dr_name                           AS DR_NAME,
    TO_CHAR(c.exit_date, 'DD/MM/YYYY')  AS DOC_DATE,
    SUM(e.accdoc_price)                 AS DOC_NOMINAL,
    COUNT(*)                            AS JML_PASIEN,
    4                                   AS GROUP_SEQ,
    c.rihdr_no                          AS TXN_NO,
    c.klaim_id                          AS KLAIM_ID
FROM
    RSTXN_RJHDRS       b
    JOIN RSTXN_RITEMPADMINS d
      ON d.tempadm_ref  = b.rj_no
     AND d.tempadm_flag = 'RJ'
    JOIN RSTXN_RIHDRS   c
      ON c.rihdr_no     = d.rihdr_no
    JOIN RSTXN_RJACCDOCS e
      ON b.rj_no        = e.rj_no
    LEFT JOIN RSMST_DOCTORS a
      ON a.dr_id        = NVL(e.dr_id, b.dr_id)

where
ri_status='P'
GROUP BY
    NVL(e.dr_id, b.dr_id),
    a.dr_name,
    TO_CHAR(c.exit_date, 'DD/MM/YYYY'),
    c.rihdr_no,
    c.klaim_id


UNION ALL

SELECT
    'UGD'                             AS GROUP_DOC,
    'UP UGD'                          AS DESC_DOC,
    a.dr_id                           AS DR_ID,
    a.dr_name                         AS DR_NAME,
    TO_CHAR(b.RJ_date, 'DD/MM/YYYY')  AS DOC_DATE,
    SUM(b.poli_price)                 AS DOC_NOMINAL,
    COUNT(*)                          AS JML_PASIEN,
    5                                 AS GROUP_SEQ,
    b.rj_no                           AS TXN_NO,
    b.klaim_id                        AS KLAIM_ID
FROM
    RSMST_DOCTORS      a
    JOIN RSTXN_UGDHDRS  b ON a.dr_id = b.dr_id
WHERE
    b.RJ_status = 'L'
GROUP BY
    a.dr_id,
    a.dr_name,
    TO_CHAR(b.RJ_date, 'DD/MM/YYYY'),
    b.RJ_status,
    b.rj_no,
    b.klaim_id   

UNION ALL

SELECT
    'UGD'                             AS GROUP_DOC,
    'JD UGD'                          AS DESC_DOC,
    NVL(c.dr_id, b.dr_id)             AS DR_ID,
    a.dr_name                         AS DR_NAME,
    TO_CHAR(b.RJ_date, 'DD/MM/YYYY')  AS DOC_DATE,
    SUM(c.accdoc_price)               AS DOC_NOMINAL,
    COUNT(*)                          AS JML_PASIEN,
    6                                 AS GROUP_SEQ,
    RJHN_DTL                          AS TXN_NO,
    b.klaim_id                        AS KLAIM_ID
    
FROM
    RSTXN_UGDHDRS      b
    JOIN RSTXN_UGDACCDOCS c ON b.RJ_no = c.RJ_no
    LEFT JOIN RSMST_DOCTORS a
        ON a.dr_id = NVL(c.dr_id, b.dr_id)
WHERE
    b.RJ_status = 'L'
GROUP BY
    NVL(c.dr_id, b.dr_id),
    a.dr_name,
    TO_CHAR(b.RJ_date, 'DD/MM/YYYY'),
    RJHN_DTL,
    b.klaim_id

UNION ALL

SELECT
    'UGDTRF'                            AS GROUP_DOC,
    'UP UGDTRF'                         AS DESC_DOC,
    a.dr_id                             AS DR_ID,
    a.dr_name                           AS DR_NAME,
    TO_CHAR(c.exit_date, 'DD/MM/YYYY')  AS DOC_DATE,
    SUM(d.poli_price)                   AS DOC_NOMINAL,
    COUNT(*)                            AS JML_PASIEN,
    7                                   AS GROUP_SEQ,
    c.rihdr_no                          AS TXN_NO,
    c.klaim_id                          AS KLAIM_ID
FROM
    RSMST_DOCTORS        a
    JOIN RSTXN_UGDHDRS    b 
      ON a.dr_id = b.dr_id
    JOIN RSTXN_RITEMPADMINS d
      ON d.tempadm_ref  = b.RJ_no
     AND d.tempadm_flag = 'UGD'
    JOIN RSTXN_RIHDRS     c
      ON c.rihdr_no = d.rihdr_no
where
ri_status='P'
GROUP BY
    a.dr_id,
    a.dr_name,
    TO_CHAR(c.exit_date, 'DD/MM/YYYY'),
    c.rihdr_no,
    c.klaim_id
UNION ALL

SELECT
    'UGDTRF'                                  AS GROUP_DOC,
    'JD UGDTRF'                               AS DESC_DOC,
    NVL(e.dr_id, b.dr_id)                     AS DR_ID,
    a.dr_name                                 AS DR_NAME,
    TO_CHAR(c.exit_date, 'DD/MM/YYYY')        AS DOC_DATE,
    SUM(e.accdoc_price)                       AS DOC_NOMINAL,
    COUNT(*)                                  AS JML_PASIEN,
    8                                         AS GROUP_SEQ,
    c.rihdr_no                                AS TXN_NO,
    c.klaim_id                                AS KLAIM_ID
FROM
    RSTXN_UGDHDRS        b
    JOIN RSTXN_UGDACCDOCS e ON b.RJ_no       = e.RJ_no
    JOIN RSTXN_RITEMPADMINS d
         ON d.tempadm_ref  = b.RJ_no
        AND d.tempadm_flag = 'UGD'
    JOIN RSTXN_RIHDRS     c ON c.rihdr_no    = d.rihdr_no
    LEFT JOIN RSMST_DOCTORS a
         ON a.dr_id        = NVL(e.dr_id, b.dr_id)
where
ri_status='P'
GROUP BY
    NVL(e.dr_id, b.dr_id),
    a.dr_name,
    TO_CHAR(c.exit_date, 'DD/MM/YYYY'),
    c.rihdr_no,
    c.klaim_id

UNION ALL

SELECT
    'RI'                               AS GROUP_DOC,
    'VISIT'                            AS DESC_DOC,
    b.dr_id                            AS DR_ID,
    a.dr_name                          AS DR_NAME,
    TO_CHAR(c.exit_date, 'DD/MM/YYYY') AS DOC_DATE,
    SUM(b.visit_price)                 AS DOC_NOMINAL,
    COUNT(*)                           AS JML_PASIEN,
    9                                  AS GROUP_SEQ,
    VISIT_NO                           AS TXN_NO,
    c.klaim_id                         AS KLAIM_ID
FROM
    RSMST_DOCTORS     a
    JOIN RSTXN_RIVISITS b ON a.dr_id   = b.dr_id
    JOIN RSTXN_RIHDRS   c ON b.rihdr_no = c.rihdr_no
WHERE
    c.ri_status = 'P'
GROUP BY
    b.dr_id,
    a.dr_name,
    TO_CHAR(c.exit_date, 'DD/MM/YYYY'),
    VISIT_NO,
    c.klaim_id

UNION ALL

SELECT
    'RI'                               AS GROUP_DOC,
    'KONSUL'                           AS DESC_DOC,
    b.dr_id                            AS DR_ID,
    a.dr_name                          AS DR_NAME,
    TO_CHAR(c.exit_date, 'DD/MM/YYYY') AS DOC_DATE,
    SUM(b.konsul_price)                AS DOC_NOMINAL,
    COUNT(*)                           AS JML_PASIEN,
    10                                 AS GROUP_SEQ,
    KONSUL_NO                          AS TXN_NO,
    c.klaim_id                         AS KLAIM_ID
FROM
    RSMST_DOCTORS     a
    JOIN RSTXN_RIKONSULS b ON a.dr_id = b.dr_id
    JOIN RSTXN_RIHDRS   c ON b.rihdr_no = c.rihdr_no
WHERE
    c.ri_status = 'P'
GROUP BY
    b.dr_id,
    a.dr_name,
    TO_CHAR(c.exit_date, 'DD/MM/YYYY'),
    KONSUL_NO,
    c.klaim_id

UNION ALL

SELECT
  'RI'                                            AS GROUP_DOC,
  'JD RI'                                         AS DESC_DOC,
  b.dr_id                                         AS DR_ID,
  a.dr_name                                       AS DR_NAME,
  TO_CHAR(c.exit_date, 'DD/MM/YYYY')              AS DOC_DATE,
  SUM(NVL(b.actd_price, 0) * NVL(b.actd_qty, 0))  AS DOC_NOMINAL,
  COUNT(*)                                        AS JML_PASIEN,
  11                                              AS GROUP_SEQ,
  ACTD_NO                                         AS TXN_NO,
  c.klaim_id                                      AS KLAIM_ID
FROM
  RSMST_DOCTORS     a
  JOIN RSTXN_RIACTDOCS b
    ON a.dr_id = b.dr_id
  JOIN RSTXN_RIHDRS   c
    ON b.rihdr_no = c.rihdr_no
WHERE
  c.ri_status = 'P'
GROUP BY
  b.dr_id,
  a.dr_name,
  TO_CHAR(c.exit_date, 'DD/MM/YYYY'),
  ACTD_NO,
  c.klaim_id

UNION ALL

SELECT
    'OK'                              AS GROUP_DOC,
    'OPERATOR'                        AS DESC_DOC,
    a.dr_id                           AS DR_ID,
    c.dr_name                         AS DR_NAME,
    TO_CHAR(b.exit_date, 'DD/MM/YYYY') AS DOC_DATE,
    SUM(a.OPRDOC_FEE)                 AS DOC_NOMINAL,
    COUNT(*)                          AS JML_PASIEN,
    12                                AS GROUP_SEQ,
    OK_REG                             AS TXN_NO,
    b.klaim_id                        AS KLAIM_ID
FROM
    RSTXN_OKS        a
    JOIN RSTXN_RIHDRS b ON a.rihdr_no = b.rihdr_no
    JOIN RSMST_DOCTORS c ON a.dr_id = c.dr_id
WHERE
    b.ri_status = 'P'
    AND a.ok_status = 'L'
GROUP BY
    a.dr_id,
    c.dr_name,
    TO_CHAR(b.exit_date, 'DD/MM/YYYY'),
    OK_REG,
    b.klaim_id

UNION ALL

SELECT
    'OK'                              AS GROUP_DOC,
    'ANASTESI'                        AS DESC_DOC,
    a.dr_id_ok                        AS DR_ID,
    c.dr_name                         AS DR_NAME,
    TO_CHAR(b.exit_date, 'DD/MM/YYYY') AS DOC_DATE,
    SUM(a.ANESDOC_FEE)                AS DOC_NOMINAL,
    COUNT(*)                          AS JML_PASIEN,
    13                                AS GROUP_SEQ,
    OK_REG                             AS TXN_NO,
    b.klaim_id                        AS KLAIM_ID
FROM
    RSTXN_OKS        a
    JOIN RSTXN_RIHDRS b ON a.rihdr_no = b.rihdr_no
    JOIN RSMST_DOCTORS c ON a.dr_id_ok = c.dr_id
WHERE
    b.ri_status = 'P'
    AND a.ok_status = 'L'
GROUP BY
    a.dr_id_ok,
    c.dr_name,
    TO_CHAR(b.exit_date, 'DD/MM/YYYY'),
    OK_REG,
    b.klaim_id

UNION ALL

SELECT
    'RJK'                             AS GROUP_DOC,
    'UP KLINIK'                       AS DESC_DOC,
    a.dr_id                           AS DR_ID,
    a.dr_name                         AS DR_NAME,
    TO_CHAR(b.rj_date, 'DD/MM/YYYY')  AS DOC_DATE,
    SUM(b.poli_price)                 AS DOC_NOMINAL,
    COUNT(*)                          AS JML_PASIEN,
    14                                AS GROUP_SEQ,
    rj_no                             AS TXN_NO,
    b.klaim_id                        AS KLAIM_ID
FROM
    RSMST_DOCTORS  a
    JOIN RSTXN_RJHDRKS b ON a.dr_id = b.dr_id
WHERE
    b.rj_status = 'L'
GROUP BY
    a.dr_id,
    a.dr_name,
    TO_CHAR(b.rj_date, 'DD/MM/YYYY'),
    b.rj_status,
    rj_no,
    b.klaim_id

UNION ALL

SELECT
    'RJK'                             AS GROUP_DOC,
    'JD KLINIK'                       AS DESC_DOC,
    a.dr_id                           AS DR_ID,
    a.dr_name                         AS DR_NAME,
    TO_CHAR(b.rj_date, 'DD/MM/YYYY')  AS DOC_DATE,
    SUM(c.accdoc_price)               AS DOC_NOMINAL,
    COUNT(*)                          AS JML_PASIEN,
    15                                AS GROUP_SEQ,
    rj_no                             AS TXN_NO,
    b.klaim_id                        AS KLAIM_ID
FROM
    RSMST_DOCTORS       a
    JOIN RSTXN_RJHDRKS  b ON a.dr_id = b.dr_id
    JOIN RSTXN_RJACCDOCKS c ON b.rj_no = c.rj_no
WHERE
    b.rj_status = 'L'
GROUP BY
    a.dr_id,
    a.dr_name,
    TO_CHAR(b.rj_date, 'DD/MM/YYYY'),
    b.rj_status,
    rj_no,
    b.klaim_id

UNION ALL

SELECT
    'RADRJ'                           AS GROUP_DOC,
    'RAD RJ'                          AS DESC_DOC,
    '086'                             AS DR_ID,
    'dr. M.A. Budi Purwito, SpRAD'    AS DR_NAME,
    TO_CHAR(rj_date, 'DD/MM/YYYY')    AS DOC_DATE,
    SUM(rad_jd)                       AS DOC_NOMINAL,
    COUNT(*)                          AS JML_PASIEN,
    16                                AS GROUP_SEQ,
    rad_dtl                           AS TXN_NO,
    klaim_id                        AS KLAIM_ID
    
FROM
    rsview_rjrads
WHERE
    rj_status NOT IN ('A','F')
GROUP BY
    '086',
    'dr. M.A. Budi Purwito, SpRAD',
    TO_CHAR(rj_date, 'DD/MM/YYYY'),
    rj_status,
    rad_dtl,
    klaim_id

UNION ALL

SELECT
    'RADUGD'                          AS GROUP_DOC,
    'RAD UGD'                         AS DESC_DOC,
    '086'                             AS DR_ID,
    'dr. M.A. Budi Purwito, SpRAD'    AS DR_NAME,
    TO_CHAR(rj_date, 'DD/MM/YYYY')    AS DOC_DATE,
    SUM(rad_jd)                       AS DOC_NOMINAL,
    COUNT(*)                          AS JML_PASIEN,
    17                                AS GROUP_SEQ,
    rad_dtl                           AS TXN_NO,
    klaim_id                          AS KLAIM_ID
FROM
    rsview_UGDrads
WHERE
    rj_status NOT IN ('A','F')
GROUP BY
    '086',
    'dr. M.A. Budi Purwito, SpRAD',
    TO_CHAR(rj_date, 'DD/MM/YYYY'),
    rj_status,
    rad_dtl,
    klaim_id

UNION ALL

SELECT
    'RADRI'                           AS GROUP_DOC,
    'RAD RI'                          AS DESC_DOC,
    '086'                             AS DR_ID,
    'dr. M.A. Budi Purwito, SpRAD'    AS DR_NAME,
    TO_CHAR(exit_date, 'DD/MM/YYYY')  AS DOC_DATE,
    SUM(rad_jd)                       AS DOC_NOMINAL,
    COUNT(*)                          AS JML_PASIEN,
    18                                AS GROUP_SEQ,
    rirad_no                          AS TXN_NO,
    klaim_id                          AS KLAIM_ID
FROM
    rsview_RIrads
WHERE
    ri_status = 'P'
GROUP BY
    '086',
    'dr. M.A. Budi Purwito, SpRAD',
    TO_CHAR(exit_date, 'DD/MM/YYYY'),
    ri_status,
    rirad_no,
    klaim_id
UNION ALL

-- OPERATOR RJ — operasi pada kunjungan RJ
SELECT
    'OKRJ'                              AS GROUP_DOC,
    'OPERATOR RJ'                        AS DESC_DOC,
    a.dr_id                           AS DR_ID,
    c.dr_name                         AS DR_NAME,
    TO_CHAR(b.rj_date, 'DD/MM/YYYY')  AS DOC_DATE,
    SUM(a.OPRDOC_FEE)                 AS DOC_NOMINAL,
    COUNT(*)                          AS JML_PASIEN,
    19                                AS GROUP_SEQ,
    a.OK_REG                          AS TXN_NO,
    b.klaim_id                        AS KLAIM_ID
FROM
    RSTXN_OKS        a
    JOIN RSTXN_RJHDRS b ON a.ref_no = b.rj_no
    JOIN RSMST_DOCTORS c ON a.dr_id = c.dr_id
WHERE
    a.status_rjri = 'RJ'
    AND b.rj_status = 'L'
    AND a.ok_status = 'L'
GROUP BY
    a.dr_id,
    c.dr_name,
    TO_CHAR(b.rj_date, 'DD/MM/YYYY'),
    a.OK_REG,
    b.klaim_id
UNION ALL

-- ANASTESI RJ — operasi pada kunjungan RJ
SELECT
    'OKRJ'                              AS GROUP_DOC,
    'ANASTESI RJ'                        AS DESC_DOC,
    a.dr_id_ok                           AS DR_ID,
    c.dr_name                         AS DR_NAME,
    TO_CHAR(b.rj_date, 'DD/MM/YYYY')  AS DOC_DATE,
    SUM(a.ANESDOC_FEE)                 AS DOC_NOMINAL,
    COUNT(*)                          AS JML_PASIEN,
    20                                AS GROUP_SEQ,
    a.OK_REG                          AS TXN_NO,
    b.klaim_id                        AS KLAIM_ID
FROM
    RSTXN_OKS        a
    JOIN RSTXN_RJHDRS b ON a.ref_no = b.rj_no
    JOIN RSMST_DOCTORS c ON a.dr_id_ok = c.dr_id
WHERE
    a.status_rjri = 'RJ'
    AND b.rj_status = 'L'
    AND a.ok_status = 'L'
GROUP BY
    a.dr_id_ok,
    c.dr_name,
    TO_CHAR(b.rj_date, 'DD/MM/YYYY'),
    a.OK_REG,
    b.klaim_id
UNION ALL

-- OPERATOR UGD — operasi pada kunjungan UGD
SELECT
    'OKUGD'                              AS GROUP_DOC,
    'OPERATOR UGD'                        AS DESC_DOC,
    a.dr_id                           AS DR_ID,
    c.dr_name                         AS DR_NAME,
    TO_CHAR(b.rj_date, 'DD/MM/YYYY')  AS DOC_DATE,
    SUM(a.OPRDOC_FEE)                 AS DOC_NOMINAL,
    COUNT(*)                          AS JML_PASIEN,
    21                                AS GROUP_SEQ,
    a.OK_REG                          AS TXN_NO,
    b.klaim_id                        AS KLAIM_ID
FROM
    RSTXN_OKS        a
    JOIN RSTXN_UGDHDRS b ON a.ref_no = b.rj_no
    JOIN RSMST_DOCTORS c ON a.dr_id = c.dr_id
WHERE
    a.status_rjri = 'UGD'
    AND b.rj_status = 'L'
    AND a.ok_status = 'L'
GROUP BY
    a.dr_id,
    c.dr_name,
    TO_CHAR(b.rj_date, 'DD/MM/YYYY'),
    a.OK_REG,
    b.klaim_id
UNION ALL

-- ANASTESI UGD — operasi pada kunjungan UGD
SELECT
    'OKUGD'                              AS GROUP_DOC,
    'ANASTESI UGD'                        AS DESC_DOC,
    a.dr_id_ok                           AS DR_ID,
    c.dr_name                         AS DR_NAME,
    TO_CHAR(b.rj_date, 'DD/MM/YYYY')  AS DOC_DATE,
    SUM(a.ANESDOC_FEE)                 AS DOC_NOMINAL,
    COUNT(*)                          AS JML_PASIEN,
    22                                AS GROUP_SEQ,
    a.OK_REG                          AS TXN_NO,
    b.klaim_id                        AS KLAIM_ID
FROM
    RSTXN_OKS        a
    JOIN RSTXN_UGDHDRS b ON a.ref_no = b.rj_no
    JOIN RSMST_DOCTORS c ON a.dr_id_ok = c.dr_id
WHERE
    a.status_rjri = 'UGD'
    AND b.rj_status = 'L'
    AND a.ok_status = 'L'
GROUP BY
    a.dr_id_ok,
    c.dr_name,
    TO_CHAR(b.rj_date, 'DD/MM/YYYY'),
    a.OK_REG,
    b.klaim_id
;

-- ===============================================================
-- VERIFIKASI
-- ===============================================================
-- 1) View valid
-- SELECT object_name, status FROM user_objects
--  WHERE object_name = 'RSVIEW_NEWDOCSALARIES';
--    Ekspektasi: VALID
--
-- 2) Cabang baru ada (belum tentu berisi — RJ/UGD belum bisa kirim ke OK)
-- SELECT group_doc, desc_doc, group_seq, COUNT(*) AS baris
--   FROM rsview_newdocsalaries
--  GROUP BY group_doc, desc_doc, group_seq ORDER BY group_seq;
--    Ekspektasi: seq 1..18 seperti semula + seq 19..22 muncul (baris 0 dulu)
--
-- 3) Angka lama TIDAK berubah — bandingkan sebelum & sesudah
-- SELECT group_seq, COUNT(*) AS baris, SUM(doc_nominal) AS nominal
--   FROM rsview_newdocsalaries WHERE group_seq <= 18
--  GROUP BY group_seq ORDER BY group_seq;
