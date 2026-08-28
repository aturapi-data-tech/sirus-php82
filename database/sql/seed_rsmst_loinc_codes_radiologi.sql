-- ============================================================
-- Seed: rsmst_loinc_codes — LOINC Radiologi RESMI SATUSEHAT
--
-- Sumber : Lampiran Terminologi Radiologi SATUSEHAT (Bagian 1, Bagian 2, Gigi)
--          https://satusehat.kemkes.go.id/platform/docs/id/terminology/loinc/radiologi/
--          diunduh 2026-08-28 dari Google Drive lampiran resmi.
-- Isi    : 1314 kode unik. Salinan mentahnya: database/data/loinc_radiologi_satusehat.csv
--
-- MENGGANTI seed lama, yang isinya terbukti karangan: dari 63 kode, 44 tidak ada
-- di LOINC dan 17 ada tapi artinya pemeriksaan lain. Kode generik 18748-4 yang
-- dipakai sebagai fallback pengiriman juga TIDAK ada di lampiran resmi ini.
--
-- Radiologi Intervensional SENGAJA tidak dimasukkan: lampiran memakai code system
-- Kemkes (http://terminology.kemkes.go.id, kode RD09934xx), bukan http://loinc.org.
--
-- Jalankan setelah create_rsmst_loinc_codes.sql. Aman diulang (idempoten).
-- ============================================================

-- 1) Buang seluruh baris radiologi lama (seed karangan).
DELETE FROM rsmst_loinc_codes WHERE loinc_class LIKE 'RAD%';

-- 2) Isi dari lampiran resmi. WHERE NOT EXISTS menjaga kode yang kebetulan sudah
--    dipakai baris laboratorium supaya tidak bentrok primary key.
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24776-7', 'NM Kidney Views', 'NM renogram/GFR', 'Views^W radionuclide IV', 'RAD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24776-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30899-9', 'Blood volume by Scintigraphy', 'Penentuan volume whole blood dengan scintigrafi', 'Blood volume', 'RAD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30899-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39747-1', 'NM Salivary gland Views', 'NM Salivary scan', 'Views^W radionuclide IV', 'RAD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39747-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '72139-9', 'DBT Breast - bilateral diagnostic', 'DBT diagnostik bilateral', 'Multisection diagnostic', 'RAD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '72139-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '72142-3', 'DBT Breast - bilateral screening', 'DBT skrining bilateral', 'Multisection screening', 'RAD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '72142-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86462-9', 'DBT Breast - unilateral', 'DBT diagnostik unilateral', 'Multisection', 'RAD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86462-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '97100-2', 'Colonoscopy+US Study', 'Colonoscopy dengan lower endoscopic ultrasound', 'Colonoscopy and endoscopic ultrasound study', 'RAD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '97100-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '97101-0', 'Flexible sigmoidoscopy+US Study', 'Flexible sigmoidoscopy dengan lower endoscopic ultrasound', 'Flexible sigmoidoscopy and endoscopic ultrasound study', 'RAD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '97101-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24701-5', 'DXA Femur [Mass/Area] Bone density', 'Bone mineral densitometry (BMD) femur (g/cm2)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24701-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24890-6', 'DXA Radius and Ulna [Mass/Area] Bone density', 'Bone mineral densitometry (BMD) forearm (g/cm2)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24890-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24966-4', 'DXA Lumbar spine [Mass/Area] Bone density', 'Bone mineral densitometry (BMD) vertebra lumbal (g/cm2)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24966-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38261-4', 'DXA Hip [Mass/Area] Bone density', 'Bone mineral densitometry (BMD) hip (g/cm2)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38261-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38263-0', 'DXA Femur [T-score] Bone density', 'Bone mineral densitometry (BMD) femur (T-score)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38263-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38264-8', 'DXA Hip [T-score] Bone density', 'Bone mineral densitometry (BMD) hip (T-score)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38264-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38265-5', 'DXA Radius and Ulna [T-score] Bone density', 'Bone mineral densitometry (BMD) forearm (T-score)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38265-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38267-1', 'DXA Lumbar spine [T-score] Bone density', 'Bone mineral densitometry (BMD) vertebra lumbal (T-score)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38267-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '46278-8', 'DXA Hip - left [Mass/Area] Bone density', 'Bone mineral densitometry (BMD) hip kiri (g/cm2)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '46278-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '46279-6', 'DXA Hip - right [Mass/Area] Bone density', 'Bone mineral densitometry (BMD) hip kanan (g/cm2)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '46279-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '46383-6', 'DXA Bone [Mass/Area] Bone density', 'Bone mineral densitometry (BMD) bone', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '46383-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80932-7', 'DXA Radius and Ulna [Z-score] Bone density', 'Bone mineral densitometry (BMD) forearm (Z-score)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80932-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80933-5', 'DXA Hip [Z-score] Bone density', 'Bone mineral densitometry (BMD) hip (Z-score)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80933-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80934-3', 'DXA Femur [Z-score] Bone density', 'Bone mineral densitometry (BMD) femur (Z-score)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80934-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80935-0', 'DXA Radius and Ulna - right [Z-score] Bone density', 'Bone mineral densitometry (BMD) forearm kanan (Z-score)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80935-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80936-8', 'DXA Radius and Ulna - left [Z-score] Bone density', 'Bone mineral densitometry (BMD) forearm kiri (Z-score)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80936-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80937-6', 'DXA Hip - right [Z-score] Bone density', 'Bone mineral densitometry (BMD) hip kanan (Z-score)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80937-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80938-4', 'DXA Hip - left [Z-score] Bone density', 'Bone mineral densitometry (BMD) hip kiri (Z-score)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80938-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80939-2', 'DXA Femur - right [Z-score] Bone density', 'Bone mineral densitometry (BMD) femur kanan (Z-score)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80939-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80940-0', 'DXA Femur - left [Z-score] Bone density', 'Bone mineral densitometry (BMD) femur kiri (Z-score)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80940-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80943-4', 'DXA Radius and Ulna - right [T-score] Bone density', 'Bone mineral densitometry (BMD) forearm kanan (T-score)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80943-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80944-2', 'DXA Radius and Ulna - left [T-score] Bone density', 'Bone mineral densitometry (BMD) forearm kiri (T-score)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80944-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80945-9', 'DXA Hip - right [T-score] Bone density', 'Bone mineral densitometry (BMD) hip kanan (T-score)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80945-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80946-7', 'DXA Hip - left [T-score] Bone density', 'Bone mineral densitometry (BMD) hip kiri (T-score)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80946-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80947-5', 'DXA Femur - right [T-score] Bone density', 'Bone mineral densitometry (BMD) femur kanan (T-score)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80947-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80948-3', 'DXA Femur - left [T-score] Bone density', 'Bone mineral densitometry (BMD) femur kiri (T-score)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80948-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80951-7', 'DXA Radius and Ulna - right [Mass/Area] Bone density', 'Bone mineral densitometry (BMD) forearm kanan (g/cm2)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80951-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80952-5', 'DXA Radius and Ulna - left [Mass/Area] Bone density', 'Bone mineral densitometry (BMD) forearm kiri (g/cm2)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80952-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80953-3', 'DXA Femur - right [Mass/Area] Bone density', 'Bone mineral densitometry (BMD) femur kanan (g/cm2)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80953-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80954-1', 'DXA Femur - left [Mass/Area] Bone density', 'Bone mineral densitometry (BMD) femur kiri (g/cm2)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80954-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '83012-5', 'DXA Skeletal system.axial Views for bone density', 'Bone mineral densitometry (BMD) tulang axial', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '83012-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '83013-3', 'DXA Skeletal system.peripheral Views for bone density', 'Bone mineral densitometry (BMD) tulang periferal', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '83013-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '83014-1', 'DXA Skeletal system.axial Views for bone density and vertebral fracture', 'Bone mineral densitometry (BMD) tulang aksial dan fraktur vertebra', 'Views for bone density+vertebral fracture', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '83014-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '83311-1', 'Bone density quantitative measurement by DXA panel', 'Bone mineral densitometry (BMD)', 'Bone density quantitative measurement panel', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '83311-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '85394-5', 'DXA Lumbar spine [Z-score] Bone density', 'Bone mineral densitometry (BMD) vertebra lumbal (Z-score)', 'Views for bone density', 'RAD/BMD', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '85394-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '103866-0', 'CT Lower leg - bilateral W contrast IV', 'CT cruris bilateral dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '103866-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '103877-7', 'CT Forearm - bilateral W contrast IV', 'CT antebrachi bilateral dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '103877-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '103878-5', 'CT Forearm - bilateral WO contrast', 'CT antebrachi bilateral nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '103878-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '103880-1', 'CT Ankle - bilateral WO and W contrast IV', 'CT pergelangan kaki bilateral dengan kontras', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '103880-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '103882-7', 'CT Mastoid WO and W contrast IV', 'CT high resolution mastoid dengan kontras', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '103882-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '18744-3', 'Bronchoscopy study', 'CT virtual bronchoscopy / thorax-nonkontras', 'Bronchoscopy study', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '18744-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24545-6', 'CT Thoracic Aorta W contrast IV', 'CT aorta thorakalis dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24545-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24628-0', 'CT Chest W contrast IV', 'CT thorax dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24628-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24697-5', 'CT Facial bones W contrast IV', 'CT kontras wajah / craniofacial dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24697-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24726-2', 'CT Head WO and W contrast IV', 'CT kepala nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24726-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24727-0', 'CT Head W contrast IV', 'CT kepala dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24727-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24728-8', 'CT perfusion Head', 'CT perfusi kepala dengan kontras', 'Multisection', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24728-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24734-6', 'CT Cerebral cisterns W contrast IT', 'CT cairan serebrospinal sisternografi dengan kontras IT', 'Multisection^W contrast IT', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24734-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24812-0', 'CT Guidance for biopsy of Liver', 'CT guidance untuk biopsi hati', 'Guidance for percutaneous biopsy', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24812-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24813-8', 'CT Guidance for core needle biopsy of Liver', 'CT guidance untuk core needle biopsi hati', 'Guidance for biopsy.core needle', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24813-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24836-9', 'CT Nasopharynx and Neck W contrast IV', 'CT nasofaring, orofaring, laring dan leher dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24836-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24866-6', 'CT Pelvis W contrast IV', 'CT pelvis dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24866-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24904-5', 'CT Pituitary and Sella turcica WO and W contrast IV', 'CT pituitari dan sella turcica nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24904-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24933-4', 'CT Cervical spine W contrast IV', 'CT vertebra cervical dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24933-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24934-2', 'CT Cervical spine W contrast IT', 'CT vertebra cervical dengan kontras IT', 'Multisection^W contrast IT', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24934-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24964-9', 'CT Lumbar spine W contrast IV', 'CT vertebra lumbal dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24964-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24965-6', 'CT Lumbar spine W contrast IT', 'CT vertebra lumbal dengan kontras IT', 'Multisection^W contrast IT', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24965-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24979-7', 'CT Thoracic spine W contrast IV', 'CT vertebra thoracal dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24979-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '25044-9', 'CT Guidance for biopsy of Unspecified body region', 'CT guided + biopsi (tindakan oleh DPJP Radiologi)', 'Guidance for percutaneous biopsy', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '25044-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '29252-4', 'CT Chest WO contrast', 'CT thorax nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '29252-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30583-9', 'CT Internal auditory canal W contrast IV', 'CT telinga dalam dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30583-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30584-7', 'CT Internal auditory canal WO contrast', 'CT telinga dalam nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30584-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30585-4', 'CT Nasopharynx and Neck WO contrast', 'CT nasofaring, orofaring, laring dan leher nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30585-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30586-2', 'CT Neck WO and W contrast IV', 'CT jaringan lunak leher nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30586-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30588-8', 'CT Sinuses', 'CT sinus', 'Multisection', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30588-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30590-4', 'CT Pituitary and Sella turcica W contrast IV', 'CT pituitari dan sella turcica dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30590-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30591-2', 'CT Pituitary and Sella turcica WO contrast', 'CT pituitari dan sella turcica nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30591-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30592-0', 'CT Cervical spine WO contrast', 'CT vertebra cervical nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30592-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30593-8', 'CTA Head vessels WO and W contrast IV', 'CTA kepala nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30593-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30594-6', 'CTA Neck vessels WO and W contrast IV', 'CTA leher nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30594-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30596-1', 'CT Thoracic spine W contrast IT', 'CT vertebra thoracal dengan kontras IT', 'Multisection^W contrast IT', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30596-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30597-9', 'CT Thoracic spine WO contrast', 'CT vertebra thoracal nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30597-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30598-7', 'CT Chest WO and W contrast IV', 'CT thorax nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30598-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30600-1', 'CT Small bowel W positive contrast via enteroclysis tube', 'CT usus halus melalui tabung enteroklisis', 'Multisection^W positive contrast via enteroclysis tube', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30600-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30615-9', 'CT Pelvis WO contrast', 'CT pelvis nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30615-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30616-7', 'CT Pelvis WO and W contrast IV', 'CT pelvis nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30616-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30620-9', 'CT Lumbar spine WO contrast', 'CT vertebra lumbal nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30620-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30623-3', 'CTA Pelvis vessels WO and W contrast IV', 'CTA pelvis nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30623-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30624-1', 'CT Lower extremity W contrast IV', 'CT ekstremitas bawah dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30624-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30625-8', 'CT Lower extremity WO contrast', 'CT ekstremitas bawah nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30625-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30626-6', 'CT Upper extremity W contrast IV', 'CT ekstremitas atas dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30626-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30627-4', 'CT Upper extremity WO contrast', 'CT ekstremitas atas nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30627-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30799-1', 'CT Head WO contrast', 'CT kepala nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30799-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30801-5', 'CT Maxillofacial region W contrast IV', 'CT maxillofacial dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30801-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30802-3', 'CT Maxillofacial region WO contrast', 'CT maxillofacial nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30802-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30803-1', 'CT Maxillofacial region WO and W contrast IV', 'CT maxillofacial nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30803-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30804-9', 'CTA Chest vessels WO and W contrast IV', 'CTA thorax nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30804-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30805-6', 'CTA Abdominal vessels WO and W contrast IV', 'CTA abdomen nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30805-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30807-2', 'CTA Lower extremity vessels WO and W contrast IV', 'CTA ekstremitas bawah nonkontras dilanjutkan dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30807-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '35895-2', 'CT Guidance for biopsy of Chest', 'CT thorax nonkontras + guiding CT', 'Guidance for percutaneous biopsy', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '35895-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '35998-4', 'CT Forearm - bilateral', 'CT antebrachi bilateral nonkontras', 'Multisection', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '35998-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36004-0', 'CT Hand - bilateral', 'CT manus bilateral nonkontras', 'Multisection', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36004-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36049-5', 'CT Maxilla and Mandible', 'CT maxilla dan mandibula', 'Multisection', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36049-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36135-2', 'CT Ankle W contrast IV', 'CT ankle dan pedis dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36135-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36137-8', 'CT Ankle - left W contrast IV', 'CT pergelangan kaki kiri dengan kontras + anestesi', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36137-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36139-4', 'CT Ankle - right W contrast IV', 'CT pergelangan kaki kanan dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36139-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36141-0', 'CTA Thoracic and abdominal aorta W contrast IV', 'CTA aorta toraks - abdomen', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36141-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36142-8', 'CT Thoracic and abdominal aorta W contrast IV', 'CT aorta thorakalis dan abdominalis dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36142-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36143-6', 'CT Abdominal Aorta W contrast IV', 'CT aorta abdominalis dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36143-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36145-1', 'CT Appendix W contrast IV', 'CT appendicogram dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36145-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36146-9', 'CTA Carotid artery W contrast IV', 'CTA carotis dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36146-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36147-7', 'CTA Pulmonary arteries W contrast IV', 'CTA pulmonal arteri', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36147-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36157-6', 'CT Elbow W contrast IV', 'CT cubiti dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36157-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36159-2', 'CT Elbow - left W contrast IV', 'CT cubiti kiri dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36159-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36161-8', 'CT Elbow - right W contrast IV', 'CT cubiti kanan dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36161-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36172-5', 'CT Thigh W contrast IV', 'CT femur dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36172-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36174-1', 'CT Thigh - left W contrast IV', 'CT femur kiri dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36174-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36176-6', 'CT Thigh - right W contrast IV', 'CT femur kanan dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36176-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36178-2', 'CT Foot W contrast IV', 'CT pedis dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36178-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36181-6', 'CT Foot - left W contrast IV', 'CT pedis kiri dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36181-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36183-2', 'CT Foot - right W contrast IV', 'CT pedis kanan dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36183-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36185-7', 'CT Forearm W contrast IV', 'CT antebrachi dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36185-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36187-3', 'CT Forearm - left W contrast IV', 'CT antebrachi kiri dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36187-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36189-9', 'CT Forearm - right W contrast IV', 'CT antebrachi kanan dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36189-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36191-5', 'CT Hand W contrast IV', 'CT manus dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36191-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36193-1', 'CT Hand - left W contrast IV', 'CT manus kiri dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36193-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36195-6', 'CT Hand - right W contrast IV', 'CT manus kanan dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36195-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36207-9', 'CT Upper arm W contrast IV', 'CT humerus dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36207-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36209-5', 'CT Upper arm - left W contrast IV', 'CT humerus kiri dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36209-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36211-1', 'CT Upper arm - right W contrast IV', 'CT humerus kanan dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36211-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36222-8', 'CT Knee W contrast IV', 'CT genu dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36222-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36225-1', 'CT Knee - left W contrast IV', 'CT genu kiri dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36225-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36227-7', 'CT Knee - right W contrast IV', 'CT genu kanan dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36227-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36235-0', 'CT Neck W contrast IV', 'CT jaringan lunak leher dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36235-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36242-6', 'CT Posterior fossa W contrast IV', 'CT fossa posterior dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36242-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36250-9', 'CT Shoulder W contrast IV', 'CT bahu kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36250-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36252-5', 'CT Shoulder - left W contrast IV', 'CT bahu kiri dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36252-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36253-3', 'CT Shoulder - right W contrast IV', 'CT bahu kanan dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36253-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36255-8', 'CT Sinuses W contrast IV', 'CT sinus kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36255-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36258-2', 'CT Lower leg W contrast IV', 'CT cruris dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36258-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36260-8', 'CT Lower leg - left W contrast IV', 'CT cruris kiri dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36260-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36262-4', 'CT Lower leg - right W contrast IV', 'CT cruris kanan dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36262-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36267-3', 'CT Abdomen WO and W contrast IV', 'CT abdomen tanpa kontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36267-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36282-2', 'CT Internal auditory canal WO and W contrast IV', 'CT telinga dalam nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36282-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36288-9', 'CT Lower extremity WO and W contrast IV', 'CT ekstremitas bawah nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36288-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36334-1', 'CT Upper extremity WO and W contrast IV', 'CT ekstremitas atas nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36334-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36387-9', 'CT Posterior fossa WO and W contrast IV', 'CT fossa posterior nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36387-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36401-8', 'CT Cervical spine WO and W contrast IV', 'CT vertebra cervical nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36401-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36402-6', 'CT Lumbar spine WO and W contrast IV', 'CT vertebra lumbal tanpa kontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36402-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36403-4', 'CT Thoracic spine WO and W contrast IV', 'CT vertebra thoracal nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36403-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36421-6', 'CTA Upper extremity vessels WO and W contrast IV', 'CTA ekstremitas atas nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36421-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36424-0', 'CT Abdomen WO contrast', 'CT abdomen atas nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36424-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36425-7', 'CT Ankle WO contrast', 'CT ankle dan pedis nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36425-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36426-5', 'CT Ankle - left WO contrast', 'CT pergelangan kaki kiri nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36426-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36428-1', 'CT Ankle - right WO contrast', 'CT pergelangan kaki kanan nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36428-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36443-0', 'CT Elbow WO contrast', 'CT cubiti nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36443-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36444-8', 'CT Elbow - bilateral WO contrast', 'CT cubiti bilateral nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36444-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36445-5', 'CT Elbow - left WO contrast', 'CT cubiti kiri nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36445-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36447-1', 'CT Elbow - right WO contrast', 'CT cubiti nonkontras kanan', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36447-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36460-4', 'CT Thigh WO contrast', 'CT femur nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36460-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36462-0', 'CT Thigh - left WO contrast', 'CT femur kiri nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36462-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36464-6', 'CT Thigh - right WO contrast', 'CT femur kanan nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36464-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36466-1', 'CT Foot WO contrast', 'CT pedis nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36466-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36468-7', 'CT Foot - left WO contrast', 'CT pedis kiri nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36468-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36470-3', 'CT Foot - right WO contrast', 'CT pedis kanan nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36470-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36472-9', 'CT Forearm WO contrast', 'CT antebrachi nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36472-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36473-7', 'CT Forearm - left WO contrast', 'CT antebrachi kiri nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36473-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36475-2', 'CT Forearm - right WO contrast', 'CT antebrachi kanan nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36475-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36477-8', 'CT Hand WO contrast', 'CT manus nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36477-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36478-6', 'CT Hand - left WO contrast', 'CT manus kiri nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36478-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36480-2', 'CT Hand - right WO contrast', 'CT manus kanan nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36480-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36491-9', 'CT Upper arm WO contrast', 'CT humerus nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36491-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36492-7', 'CT Upper arm - left WO contrast', 'CT humerus kiri nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36492-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36494-3', 'CT Upper arm - right WO contrast', 'CT humerus kanan nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36494-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36505-6', 'CT Knee WO contrast', 'CT genu nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36505-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36507-2', 'CT Knee - left WO contrast', 'CT genu kiri nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36507-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36509-8', 'CT Knee - right WO contrast', 'CT genu kanan nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36509-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36514-8', 'CT Neck WO contrast', 'CT jaringan lunak leher nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36514-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36517-1', 'CT Posterior fossa WO contrast', 'CT fossa posterior nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36517-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36524-7', 'CT Shoulder WO contrast', 'CT bahu nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36524-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36526-2', 'CT Shoulder - left WO contrast', 'CT bahu kiri nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36526-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36527-0', 'CT Shoulder - right WO contrast', 'CT bahu kanan nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36527-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36529-6', 'CT Sinuses WO contrast', 'CT sinus nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36529-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36537-9', 'CT Lower leg WO contrast', 'CT cruris nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36537-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36538-7', 'CT Lower leg - left WO contrast', 'CT cruris kiri nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36538-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36540-3', 'CT Lower leg - right WO contrast', 'CT cruris kanan nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36540-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36813-4', 'CT Abdomen and Pelvis W contrast IV', 'CT abdomen dan pelvis dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36813-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36815-9', 'CT Temporal bone W contrast IV', 'CT os. temporal dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36815-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36824-1', 'CTA Lower extremity veins - left W contrast IV', 'CT venografi ekstremitas inferior kiri', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36824-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36825-8', 'CTA Lower extremity veins - right W contrast IV', 'CT venografi ekstremitas inferior kanan', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36825-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36828-2', 'CTA Abdominal vessels W contrast IV', 'CTA abdomen', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36828-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36830-8', 'CTA Head vessels W contrast IV', 'CTA cerebral', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36830-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36831-6', 'CTA Lower extremity vessels W contrast IV', 'CTA ekstremitas bawah W contrast IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36831-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36866-2', 'CT Temporal bone WO contrast', 'CT Os. temporal nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36866-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36952-0', 'CT Abdomen and Pelvis WO contrast', 'CT abdomen dan pelvis nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36952-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37221-9', 'CT Unspecified body region for fistula', 'CT fistulografi', 'Multisection for fistula', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37221-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37283-9', 'CT Temporomandibular joint WO contrast', 'CT TMJ nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37283-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37441-3', 'CT Lung parenchyma WO contrast', 'HRCT thorax nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37441-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37447-0', 'CT Wrist W contrast IV', 'CT wrist dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37447-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37450-4', 'CT Wrist - left W contrast IV', 'CT pergelangan tangan kiri dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37450-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37452-0', 'CT Wrist - right W contrast IV', 'CT pergelangan tangan kanan dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37452-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37459-5', 'CT Wrist WO contrast', 'CT wrist nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37459-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37461-1', 'CT Wrist - bilateral WO contrast', 'CT pergelangan tangan bilateral nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37461-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37463-7', 'CT Wrist - left WO contrast', 'CT pergelangan tangan kiri nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37463-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37465-2', 'CT Wrist - right WO contrast', 'CT pergelangan tangan kanan nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37465-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37498-3', 'CTA Head vessels and Neck vessels W contrast IV', 'CTA cerebral dan carotis', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37498-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39142-5', 'CT perfusion Head W contrast IV', 'CT perfusi dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39142-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42274-1', 'CT Abdomen and Pelvis WO and W contrast IV', 'CT abdomen dan pelvis nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42274-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42275-8', 'CT Chest and Abdomen W contrast IV', 'CT thorakoabdominal dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42275-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42276-6', 'CT Chest and Abdomen WO contrast', 'CT torakoabdominal nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42276-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42295-6', 'CTA Upper extremity vessels W contrast IV', 'CTA ekstremitas atas dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42295-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43444-9', 'CT Guidance for percutaneous drainage of abscess and placement of drainage catheter of Unspecified body region', 'CT guidance untuk drainase abses perkutan dan penempatan kateter drainase', 'Guidance for percutaneous drainage of abscess+placement of drainage catheter', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43444-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44113-9', 'CT Thoracic spine WO and W contrast IT', 'CT vertebra thoracal nonkontras diikuti dengan kontras IT', 'Multisection^WO & W contrast IT', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44113-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44114-7', 'CT Lumbar spine WO and W contrast IT', 'CT vertebra lumbal nonkontras diikuti dengan kontras IT', 'Multisection^WO & W contrast IT', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44114-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44229-3', 'CT Bone', 'CT bone', 'Multisection', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44229-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '46290-3', 'CT Guidance for biopsy of Unspecified body region-- WO contrast', 'CT guidance biopsy jarum nonkontras', 'Guidance for percutaneous biopsy^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '46290-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '46298-6', 'CT Mastoid - bilateral', 'CT mastoid high resolution nonkontras', 'Multisection', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '46298-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '46320-8', 'CT Orbit and Face W contrast IV', 'CT orbita dan wajah dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '46320-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '46331-5', 'CT Orbit WO contrast', 'CT orbita nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '46331-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48449-3', 'CT Orbit W contrast IV', 'CT orbita dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48449-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48451-9', 'CT Orbit WO and W contrast IV', 'CT orbita nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48451-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '58744-4', 'CT Heart', 'CT jantung', 'Multisection', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '58744-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '58747-7', 'CT Guidance for ablation of tissue of Unspecified body region', 'CT guidance untuk ablasi dan pemantauan jaringan parenkimal', 'Guidance for ablation of tissue', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '58747-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69087-5', 'CT Ankle - bilateral WO contrast', 'CT ankle bilateral nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69087-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69088-3', 'CT Knee - bilateral W contrast IV', 'CT genu bilateral dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69088-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69089-1', 'CT Knee - bilateral WO contrast', 'CT genu bilateral nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69089-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69090-9', 'CT Shoulder - bilateral WO contrast', 'CT bahu bilateral nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69090-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69091-7', 'CT Wrist - bilateral W contrast IV', 'CT pergelangan tangan bilateral dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69091-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69092-5', 'CT Guidance for biopsy of Liver-- WO contrast', 'CT guidance untuk biopsi hati tanpa kontras', 'Guidance for percutaneous biopsy^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69092-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69095-8', 'CT Urinary bladder W contrast IV', 'CT cystography dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69095-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '72249-6', 'CT Facial bones WO contrast', 'CT wajah / craniofacial nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '72249-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '72250-4', 'CT Small bowel W contrast PO and W contrast IV', 'CT enterography dengan kontras', 'Multisection^W contrast PO+W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '72250-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '78031-2', 'CTA Abdominal Aorta and Bilateral Runoff Vessels WO and W contrast IV', 'CTA aorta abdominalis dan iliofemoral bilateral non kontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '78031-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '78037-9', 'CTA Abdominal Aorta and Bilateral Runoff Vessels W contrast IV', 'CTA aorta abdominalis dan iliofemoral bilateral dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '78037-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '78973-5', 'CT scanogram Lower extremity - bilateral for leg measurement WO contrast', 'CT scanogram ekstremitas bawah bilateral nonkontras', 'Multisection for leg measurement^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '78973-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '79069-1', 'CT Colon and Rectum for screening WO contrast IV and W air contrast PR', 'CT kolonografi skrining non kontras IV dan diikuti dengan kontras PR', 'Multisection for screening^WO contrast IV+W air contrast PR', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '79069-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '79071-7', 'CT Colon and Rectum WO contrast IV and W air contrast PR', 'CT kolonografi nonkontras IV diikuti dengan kontras PR', 'Multisection^WO contrast IV+W air contrast PR', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '79071-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '79073-3', 'CTA Heart and Coronary arteries W contrast IV', 'CTA coroner', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '79073-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '79087-3', 'CT Heart and Coronary arteries for calcium scoring WO contrast', 'CT cardiac (calcium scoring) nonkontras', 'Multisection for calcium score^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '79087-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '79088-1', 'CT Heart for congenital disease W contrast IV', 'CT jantung dengan kontras IV untuk penyakit jantung bawaan', 'Multisection for congenital disease^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '79088-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '79089-9', 'CT Heart W contrast IV', 'CT jantung dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '79089-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '79094-9', 'CT Urinary bladder W contrast intra bladder', 'CT cystography', 'Multisection^W contrast intra bladder', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '79094-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '79095-6', 'CT Teeth', 'CT panoramik', 'Multisection', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '79095-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '79103-8', 'CT Abdomen W contrast IV', 'CT abdomen atas dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '79103-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '82679-2', 'CTA Abdominal vessels and Pelvis vessels WO and W contrast IV', 'CTA abdomen dan pelvis nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '82679-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '82688-3', 'CT Colon and Rectum WO and W contrast IV and W air contrast PR', 'CT kolonografi nonkontras dan diikuti dengan kontras IV dan kontras PR', 'Multisection^WO & W contrast IV+W air contrast PR', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '82688-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '82696-6', 'CT Spine Lumbar and Sacrum W contrast IV', 'CT vertebra lumbosacral dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '82696-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '82697-4', 'CT Spine Lumbar and Sacrum WO contrast', 'CT vertebra lumbar dan sacrum nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '82697-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '82709-7', 'CTA Thoracic Aorta', 'CTA aorta torakalis', 'Multisection', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '82709-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '82710-5', 'CTA Lower extremity vessels - right', 'CTA ekstremitas inferior kanan', 'Multisection', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '82710-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '82712-1', 'CTA Lower extremity vessels - left', 'CTA ekstremitas inferior kiri', 'Multisection', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '82712-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '82713-9', 'CTA Upper extremity vessels - left', 'CTA ekstremitas superior kiri', 'Multisection', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '82713-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '82714-7', 'CTA Upper extremity vessels - right', 'CTA ekstremitas superior kanan', 'Multisection', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '82714-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '83289-9', 'CT for calcium scoring WO contrast and CTA W contrast IV Heart and coronary arteries', 'CT cardiac (calcium scoring dan angiografi coroner) dengan kontras', 'Multisection for calcium score^WO contrast && Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '83289-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '83294-9', 'CT Thoracic and lumbar spine W contrast IV', 'CT vertebra thoracolumbar dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '83294-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '83304-6', 'CT Cervical and thoracic spine WO contrast', 'CT vertebra cervical dan thoracic nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '83304-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '83310-3', 'CT Thoracic and lumbar spine WO contrast', 'CT vertebra thoracolumbar nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '83310-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86959-4', 'CT Cervical and thoracic spine WO and W contrast IV', 'CT vertebra cerviothoracal dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86959-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86962-8', 'CTA Upper extremity vessels - bilateral W contrast IV', 'CTA ekstremitas superior bilateral', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86962-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86965-1', 'CTA Lower extremity vessels - bilateral W contrast IV', 'CTA ekstremitas inferior bilateral', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86965-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86967-7', 'CT Foot - bilateral WO contrast', 'CT pedis bilateral nonkontras', 'Multisection^WO Contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86967-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86968-5', 'CT Hand - bilateral W contrast IV', 'CT manus bilateral dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86968-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86969-3', 'CT Shoulder - bilateral W contrast IV', 'CT bahu bilateral dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86969-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86973-5', 'CT Foot - bilateral W contrast IV', 'CT pedis bilateral dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86973-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86974-3', 'CT Thoracic and lumbar spine WO and W contrast IV', 'CT vertebra thoracal + lumbal dengan kontras', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86974-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86984-2', 'CT Cervical and thoracic and lumbar spine W contrast IV', 'CT vertebra lengkap (wholespine) dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86984-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86985-9', 'CT Cervical and thoracic spine W contrast IV', 'CT vertebra cervical + thoracal dengan kontras', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86985-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86987-5', 'CT Cervical and thoracic and lumbar spine WO contrast', 'CT vertebra lengkap (wholespine) nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86987-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86988-3', 'CT Orbit and Face WO contrast', 'CT orbita dan wajah nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86988-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '87281-2', 'CTA Lower extremity veins - bilateral W contrast IV', 'CT venografi ekstremitas inferior bilateral', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '87281-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '87839-7', 'CTA Pulmonary veins W contrast IV', 'CTA pulmonal vena', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '87839-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '87845-4', 'CTA Lower extremity vessels', 'CTA ekstremitas bawah', 'Multisection', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '87845-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '87856-1', 'CTA Circle of Willis and Carotid arteries W contrast IV', 'CTA cerebral - carotis', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '87856-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '87866-0', 'CT Kidney and Ureter and Urinary bladder WO and W contrast IV', 'CT urografi dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '87866-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '87873-6', 'CT Lung parenchyma WO and W contrast IV', 'HRCT thorax dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '87873-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '87879-3', 'CT Lower leg - bilateral WO contrast', 'CT cruris bilateral nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '87879-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '87880-1', 'CT Thigh - bilateral W contrast IV', 'CT femur bilateral dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '87880-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '87881-9', 'CT Thigh - bilateral WO contrast', 'CT femur bilateral nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '87881-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '87885-0', 'CT Upper arm - bilateral WO contrast', 'CT humerus bilateral', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '87885-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '87900-7', 'CT Mastoid W contrast IV', 'CT mastoid high resolution dengan kontras IV', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '87900-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '89722-3', 'CTA Upper extremity veins - left W contrast IV', 'CT venografi ekstremitas superior kiri', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '89722-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '89723-1', 'CTA Upper extremity veins - right W contrast IV', 'CT venografi ekstremitas superior kanan', 'Multisection^W contrast IV', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '89723-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '89851-0', 'CTA Unspecified body region limited', 'CTA untuk perencanaan tindakan bedah', 'Multisection limited', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '89851-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '89932-8', 'CT Guidance for deep placement of needle of Unspecified body region', 'CT guidance untuk penempatan jarum', 'Guidance for deep placement of needle', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '89932-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '95924-7', 'CT Skeletal system Multisection for bone density', 'CT osteo bone densito nonkontras', 'Multisection for bone density', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '95924-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '95925-4', 'CT Skeletal system.axial Multisection for bone density', 'CT pemeriksaan densitas mineral tulang satu lokasi atau lebih pada tulang-tulang aksial', 'Multisection for bone density', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '95925-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '99610-8', 'CT Abdomen and Pelvis W contrast PO', 'CT abdomen dan pelvis dengan kontras PO', 'Multisection^W contrast PO', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '99610-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '99612-4', 'CT Heart WO contrast', 'CT jantung nonkontras', 'Multisection^WO contrast', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '99612-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '99633-0', 'Cone beam CT Teeth', 'CT cone beam (CBCT) gigi lengkap', 'Multisection', 'RAD/CT', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '99633-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '25000-1', 'XR Temporomandibular joint Views', 'Temporomandibular Joint (TMJ) view', 'Views', 'RAD/GIGI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '25000-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30721-5', 'XR Sinuses PA and Lateral', 'XR sinus PA dan lateral', 'Views PA + lateral', 'RAD/GIGI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30721-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30880-9', 'US.doppler Head vessels and Neck vessels', 'USG pembuluh darah kepala dan leher', 'Multisection', 'RAD/GIGI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30880-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '35953-9', 'MR Face', 'MRI wajah', 'Multisection', 'RAD/GIGI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '35953-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36050-3', 'CT Maxilla', 'CT maxilla', 'Multisection', 'RAD/GIGI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36050-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36933-0', 'MR Salivary gland', 'MRI kelenjar saliva', 'Multisection', 'RAD/GIGI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36933-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37026-2', 'XR Skull Submentovertex', 'Submentovertex skull', 'View submentovertex', 'RAD/GIGI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37026-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37165-8', 'XR Facial bones Lateral and Caldwell and Waters and Submentovertex', 'Submentovertex caldwell', 'Views lateral + Caldwell + Waters + submentovertex', 'RAD/GIGI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37165-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37859-6', 'XR Sinuses PA and Lateral and Waters', 'XR Sinuses PA and Lateral and Waters', 'Views PA + lateral + Waters', 'RAD/GIGI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37859-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37860-4', 'XR Sinuses PA and Lateral and Caldwell and Waters', 'XR Sinuses PA and Lateral and Caldwell and Waters', 'Views PA + lateral + Caldwell + Waters', 'RAD/GIGI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37860-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37861-2', 'XR Sinuses Submentovertex', 'Submentovertex sinus', 'View submentovertex', 'RAD/GIGI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37861-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37869-5', 'XR Skull Lateral and Towne', 'Reverse Towne skull lateral', 'Views lateral + Towne', 'RAD/GIGI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37869-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37871-1', 'XR Skull Lateral and Caldwell and Waters and Towne', 'Reverse Towne skull lateral caldwell', 'Views lateral + Caldwell + Waters + Towne', 'RAD/GIGI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37871-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39519-4', 'XR Skull PA and Right lateral and Left lateral', 'XR Skull PA and Right lateral and Left lateral', 'Views PA + R-lateral + L-lateral', 'RAD/GIGI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39519-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39520-2', 'XR Skull PA and Right lateral and Left lateral and Towne', 'XR Skull PA, lateral kanan, lateral kiri, & towne', 'Views PA + R-lateral + L-lateral + Towne', 'RAD/GIGI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39520-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '41808-7', 'CT Maxillofacial region', 'CT regio maxillofacial', 'Multisection', 'RAD/GIGI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '41808-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44164-2', 'US Head and Neck', 'USG kepala dan leher', 'Multisection', 'RAD/GIGI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44164-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '46386-9', 'XR Teeth Bitewing Views', 'Bitewing (Bw)', 'Views bitewing', 'RAD/GIGI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '46386-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69271-5', 'XR Skull PA and Lateral and Waters and Towne', 'XR Skull PA, lateral, waters & towne', 'Views PA + lateral + Waters + Towne', 'RAD/GIGI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69271-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '83309-5', 'CT Sinuses and Mandible WO contrast', 'CT sinus dan nandibula tanpa kontras', 'Multisection^WO contrast', 'RAD/GIGI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '83309-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '87898-3', 'CT Teeth.maxilla WO contrast', 'CT gigi maxilla tanpa kontras', 'Multisection^WO contrast', 'RAD/GIGI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '87898-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '99631-4', 'Cone beam CT Temporomandibular joint - bilateral', 'CBCT Temporomandibular Joint (TMJ) bilateral', 'Multisection', 'RAD/GIGI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '99631-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '99632-2', 'Cone beam CT Temporomandibular joint WO and W contrast IV', 'CBCT TMJ tanpa kontras dan dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/GIGI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '99632-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '87066-7', 'Guidance for percutaneous placement of nephrostomy tube of Kidney', 'Guidance pemasangan nefrostomi perkutan', 'Guidance for percutaneous placement of nephrostomy tube', 'RAD/GUIDE', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '87066-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '87092-3', 'Guidance for placement of venous filter in Inferior vena cava', 'Guidance pemasangan filter inferior vena cava perkutaneus', 'Guidance for placement of venous filter', 'RAD/GUIDE', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '87092-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '87093-1', 'Guidance for removal of venous filter from Inferior vena cava', 'Guidance pelepasan filter vena pada vena cava inferior', 'Guidance for removal of venous filter', 'RAD/GUIDE', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '87093-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '87094-9', 'Guidance for reposition of venous filter in Inferior vena cava', 'Guidance reposisi filter vena pada vena cava inferior', 'Guidance for reposition of venous filter', 'RAD/GUIDE', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '87094-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '87126-9', 'Guidance for removal of venous filter from Superior vena cava', 'Guidance pelepasan filter vena pada vena cava superior', 'Guidance for removal of venous filter', 'RAD/GUIDE', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '87126-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '87127-7', 'Guidance for reposition of venous filter in Superior vena cava', 'Guidance reposisi filter vena pada vena cava superior', 'Guidance for reposition of venous filter', 'RAD/GUIDE', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '87127-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '87128-5', 'Guidance for placement of venous filter in Superior vena cava', 'Guidance pemasangan filter superior vena cava perkutaneus', 'Guidance for placement of venous filter', 'RAD/GUIDE', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '87128-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '87167-3', 'Guidance for transcatheter biopsy', 'Guidance biopsi transkateter', 'Guidance for transcatheter biopsy', 'RAD/GUIDE', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '87167-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '87190-5', 'Guidance for percutaneous placement of nephroureteral stent of Kidney and Ureter and Urinary bladder', 'Guidance pemasangan kateter atau stent ureter melalui pelvis renalis secara perkutan untuk injeksi atau drainase', 'Guidance for percutaneous placement of nephroureteral stent', 'RAD/GUIDE', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '87190-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '87202-8', 'Guidance for venous sampling of Vein', 'Guidance untuk sampling vena', 'Guidance for venous sampling', 'RAD/GUIDE', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '87202-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '88930-3', 'Guidance for dilation of stricture and placement of stent of Biliary ducts-- W contrast IV', 'Guidance pelebaran perkutaneus transhepatik striktur duktus biliaris, dengan atau tanpa pemasangan stent', 'Guidance for dilation of stricture+placement of stent^W contrast IV', 'RAD/GUIDE', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '88930-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '88938-6', 'Guidance for placement of stent in Ureter', 'Guidance pemasanganan stent ureter', 'Guidance for placement of stent', 'RAD/GUIDE', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '88938-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24533-2', 'MRA Abdominal vessels W contrast IV', 'MRA abdomen dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24533-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24549-8', 'MRA Upper extremity vessels W contrast IV', 'MRA extremitas atas dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24549-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24557-1', 'MR Abdomen WO and W contrast IV', 'MR abdomen nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24557-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24587-8', 'MR Brain WO and W contrast IV', 'MR otak nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24587-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24589-4', 'MR Brain W contrast IV', 'MR otak dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24589-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24590-2', 'MR Brain', 'MR brain', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24590-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24593-6', 'MRA Head vessels W contrast IV', 'MRA brain dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24593-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24659-5', 'MRA Chest vessels W contrast IV', 'MRA thorax dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24659-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24748-6', 'MR Heart', 'MR cardiac', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24748-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24802-1', 'MR Knee', 'MR cartigram + genu dengan kontras', 'Multisection^W contrast IS', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24802-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24841-9', 'MR Neck W contrast IV', 'MR leher (faring-laring) dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24841-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24844-3', 'MRA Neck vessels W contrast IV', 'MRA leher dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24844-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24873-2', 'MRA Pelvis vessels W contrast IV', 'MRA pelvis dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24873-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24915-1', 'MR Sinuses W contrast IV', 'MR sinus paranasal dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24915-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24937-5', 'MR Cervical spine WO and W contrast IV', 'MR vertebra cervical nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24937-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24938-3', 'MR Cervical spine W contrast IV', 'MR vertebra cervical dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24938-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24967-2', 'MR Lumbar spine WO and W contrast IV', 'MR vertebra lumbal nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24967-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24981-3', 'MR Thoracic spine WO and W contrast IV', 'MR vertebra thoracal nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24981-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24982-1', 'MR Thoracic spine W contrast IV', 'MR vertebra thoracal dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24982-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24999-5', 'MR Temporomandibular joint', 'MR sendi temporomandibula', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24999-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '25056-3', 'MR Unspecified body region', 'MR prosedur', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '25056-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26196-6', 'MR Thigh - bilateral WO and W contrast IV', 'MR femur bilateral dengan kontras', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26196-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30657-1', 'MR Brain WO contrast', 'MR otak nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30657-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30658-9', 'MR Internal auditory canal WO contrast', 'MR CISS/cochlea nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30658-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30660-5', 'MR Neck WO contrast', 'MR leher (neck soft tissue) nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30660-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30662-1', 'MR Sinuses WO contrast', 'MR sinus paranasal nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30662-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30666-2', 'MR Pituitary and Sella turcica WO contrast', 'MR hipofisis dynamic nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30666-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30667-0', 'MR Cervical spine WO contrast', 'MR vertebra cervical nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30667-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30668-8', 'MR Abdomen WO contrast', 'MR abdomen atas nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30668-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30673-8', 'MR Pelvis WO contrast', 'MR abdomen bawah nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30673-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30674-6', 'MR Pelvis WO and W contrast IV', 'MR pelvis nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30674-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30678-7', 'MR Lumbar spine W contrast IV', 'MR vertebra lumbal dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30678-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30679-5', 'MR Lumbar spine WO contrast', 'MR vertebra lumbal nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30679-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30680-3', 'MR Ankle WO contrast', 'MR pergelangan kaki nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30680-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30681-1', 'MR Foot WO contrast', 'MR pedis nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30681-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30683-7', 'MR Forearm WO contrast', 'MR antebrachi nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30683-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30685-2', 'MR Hand WO contrast', 'MR manus nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30685-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30687-8', 'MR Hip WO contrast', 'MR sendi panggul (hip) - 1 sendi nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30687-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30689-4', 'MR Upper arm WO contrast', 'MR humerus nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30689-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30691-0', 'MR Knee WO contrast', 'MR genu nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30691-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30693-6', 'MR Shoulder WO contrast', 'MR bahu nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30693-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30795-9', 'MR Breast - bilateral', 'MR payudara bilateral', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30795-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30796-7', 'MR Elbow WO contrast', 'MR cubiti nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30796-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30854-4', 'MR Cervical and thoracic and lumbar spine WO contrast', 'MR vertebra lengkap (wholespine) nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30854-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30859-3', 'MRA Carotid vessels and Neck vessels', 'MRA carotis dengan kontras', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30859-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30862-7', 'MRA Chest vessels', 'MRA thorax', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30862-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30867-6', 'MRA Pelvis vessels', 'MRA pelvis', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30867-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30869-2', 'MR Lower leg WO contrast', 'MR cruris nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30869-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30874-2', 'MRA Lower extremity vessels', 'MRA ekstremitas bawah', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30874-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '35974-5', 'MRA Lower extremity vessels - bilateral', 'MRA ekstremitas inferior nonkontras bilateral', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '35974-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '35990-1', 'MR Fetal', 'MR fetal', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '35990-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36020-6', 'MR Hip - left', 'MR sendi panggul (hip) - 1 sendi kiri dengan kontras IV', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36020-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36022-2', 'MR Hip - right', 'MR sendi panggul (hip) - 1 sendi kanan dengan kontras IV', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36022-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36061-0', 'MR Scapula', 'MR scapula bilateral nonkontras', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36061-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36084-2', 'MRA Upper extremity vessels', 'MRA ekstremitas atas', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36084-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36091-7', 'MR Heart limited', 'MR jantung terbatas', 'Multisection limited', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36091-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36096-6', 'MR Brain limited W contrast IV', 'MR otak terbatas dengan kontras IV', 'Multisection limited^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36096-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36105-5', 'MR Brain limited WO contrast', 'MR otak terbatas nonkontras', 'Multisection limited^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36105-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36126-1', 'MR Knee - left Arthrogram', 'MR cartigram + genu kiri dengan kontras', 'Multisection^W contrast IS', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36126-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36127-9', 'MR Knee - right Arthrogram', 'MR cartigram + genu kanan dengan kontras', 'Multisection^W contrast IS', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36127-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36134-5', 'MR Abdomen W contrast IV', 'MR abdomen atas dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36134-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36136-0', 'MR Ankle W contrast IV', 'MR pergelangan kaki dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36136-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36138-6', 'MR Ankle - left W contrast IV', 'MR pergelangan kaki kiri dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36138-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36140-2', 'MR Ankle - right W contrast IV', 'MR pergelangan kaki kanan dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36140-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36150-1', 'MR Breast - bilateral W contrast IV', 'MR payudara bilateral dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36150-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36155-0', 'MR Internal auditory canal W contrast IV', 'MR CISS/cochlea dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36155-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36156-8', 'MR Chest W contrast IV', 'MR thoraks dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36156-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36158-4', 'MR Elbow W contrast IV', 'MR cubiti dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36158-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36160-0', 'MR Elbow - left W contrast IV', 'MR cubiti kiri dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36160-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36162-6', 'MR Elbow - right W contrast IV', 'MR cubiti kanan dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36162-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36173-3', 'MR Thigh W contrast IV', 'MR femur dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36173-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36175-8', 'MR Thigh - left W contrast IV', 'MR femur kiri dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36175-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36177-4', 'MR Thigh - right W contrast IV', 'MR femur kanan dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36177-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36179-0', 'MR Foot W contrast IV', 'MR pedis dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36179-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36180-8', 'MR Foot - bilateral W contrast IV', 'MR pedis bilateral dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36180-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36182-4', 'MR Foot - left W contrast IV', 'MR pedis kiri dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36182-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36184-0', 'MR Foot - right W contrast IV', 'MR pedis kanan dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36184-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36186-5', 'MR Forearm W contrast IV', 'MR antebrachi dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36186-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36188-1', 'MR Forearm - left W contrast IV', 'MR antebrachi kiri dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36188-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36190-7', 'MR Forearm - right W contrast IV', 'MR antebrachi kanan dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36190-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36192-3', 'MR Hand W contrast IV', 'MR manus dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36192-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36194-9', 'MR Hand - left W contrast IV', 'MR manus kiri dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36194-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36196-4', 'MR Hand - right W contrast IV', 'MR manus kanan dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36196-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36197-2', 'MR Heart W contrast IV', 'MR cardiac dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36197-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36202-0', 'MR Hip - bilateral W contrast IV', 'MR sendi panggul bilateral (hip bilateral) dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36202-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36208-7', 'MR Upper arm W contrast IV', 'MR humerus dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36208-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36213-7', 'MR Lower Extremity Joint W contrast IV', 'MR sendi ekstremitas bawah dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36213-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36216-0', 'MR Upper extremity.joint W contrast IV', 'MR sendi ekstremitas atas dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36216-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36218-6', 'MR Sacroiliac Joint W contrast IV', 'MR sendi sakroiliaka dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36218-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36223-6', 'MR Knee W contrast IV', 'MR genu dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36223-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36224-4', 'MR Knee - bilateral W contrast IV', 'MR genu bilateral dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36224-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36226-9', 'MR Knee - left W contrast IV', 'MR genu kiri dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36226-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36228-5', 'MR Knee - right W contrast IV', 'MR genu kanan dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36228-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36230-1', 'MR Larynx W contrast IV', 'MR laring kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36230-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36231-9', 'MR Liver W contrast IV', 'MR liver dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36231-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36233-5', 'MR Nasopharynx W contrast IV', 'MR nasofaring dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36233-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36237-6', 'MR Pelvis W contrast IV', 'MR abdomen bawah dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36237-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36238-4', 'MR Pituitary and Sella turcica W contrast IV', 'MR hipofisis dynamic dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36238-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36239-2', 'MR Brachial plexus W contrast IV', 'MR leher pleksus brachialis dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36239-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36244-2', 'MR Prostate W contrast IV', 'MR prostat dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36244-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36251-7', 'MR Shoulder W contrast IV', 'MR bahu dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36251-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36259-0', 'MR Lower leg W contrast IV', 'MR cruris dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36259-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36261-6', 'MR Lower leg - left W contrast IV', 'MR cruris kiri dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36261-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36263-2', 'MR Lower leg - right W contrast IV', 'MR cruris kanan dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36263-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36272-3', 'MRA Abdominal Aorta WO and W contrast IV', 'MRA aorta abdominalis dengan kontras', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36272-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36277-2', 'MR Breast - bilateral WO and W contrast IV', 'MR payudara nonkontras diikuti dengan kontras bilateral', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36277-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36371-3', 'MR Lower Extremity Joint WO and W contrast IV', 'MR sendi ekstremitas bawah nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36371-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36374-7', 'MR Upper extremity.joint WO and W contrast IV', 'MR sendi ekstremitas atas nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36374-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36389-5', 'MR Prostate WO and W contrast IV', 'MR dinamik multiparametrik prostat dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36389-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36422-4', 'MRA Upper extremity vessels WO and W contrast IV', 'MRA ekstremitas atas nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36422-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36423-2', 'MRA Neck vessels WO and W contrast IV', 'MRA leher nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36423-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36432-3', 'MRA Abdominal Aorta WO contrast', 'MRA aorta abdominalis nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36432-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36433-1', 'MRA Thoracic and abdominal aorta WO and W contrast IV', 'MRA aorta lengkap (Arkus Aorta s/d Bifurkasio) dengan kontras', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36433-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36436-4', 'MR Breast WO contrast', 'MR payudara nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36436-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36437-2', 'MR Breast - bilateral WO contrast', 'MR payudara bilateral nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36437-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36442-2', 'MR Chest WO contrast', 'MR thoraks nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36442-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36461-2', 'MR Thigh WO contrast', 'MR femur nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36461-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36467-9', 'MR Foot - bilateral WO contrast', 'MR pedis bilateral nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36467-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36482-8', 'MR Heart WO contrast', 'MR cardiac nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36482-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36486-9', 'MR Hip - bilateral WO contrast', 'MR sendi panggul bilateral (hip bilateral) nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36486-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36497-6', 'MR Lower Extremity Joint WO contrast', 'MR sendi ekstremitas bawah nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36497-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36500-7', 'MR Upper extremity.joint WO contrast', 'MR sendi ekstremitas atas nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36500-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36502-3', 'MR Sacroiliac Joint WO contrast', 'MR sendi sakroiliaka nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36502-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36504-9', 'MR Kidney - bilateral WO contrast', 'MR urografi nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36504-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36506-4', 'MR Knee - bilateral WO contrast', 'MR genu bilateral nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36506-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36513-0', 'MR Nasopharynx WO contrast', 'MR nasofaring nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36513-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36519-7', 'MR Prostate WO contrast', 'MR prostat nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36519-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36525-4', 'MR Shoulder - bilateral WO contrast', 'MR bahu bilateral nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36525-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36532-0', 'MR Thoracic spine WO contrast', 'MR vertebra thoracal nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36532-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36547-8', 'MRA Chest vessels WO contrast', 'MRA thorax nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36547-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36549-4', 'MRA Neck vessels WO contrast', 'MRA leher nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36549-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36791-2', 'MRA Abdominal vessels', 'MRA abdomen', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36791-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36803-5', 'MRA Pulmonary vessels', 'MRA pulmonal artery nonkontras', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36803-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36820-9', 'MR Orbit W contrast IV', 'MR orbita dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36820-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36842-3', 'MR Orbit WO and W contrast IV', 'MR orbita nonkontras dan diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36842-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36855-5', 'MRA Abdominal vessels WO and W contrast IV', 'MRA abdomen nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36855-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36857-1', 'MRA Head vessels WO and W contrast IV', 'MRA kepala nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36857-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36863-9', 'MRA Pelvis vessels WO and W contrast IV', 'MRA pelvis nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36863-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36872-0', 'MR Orbit WO contrast', 'MR orbita nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36872-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36878-7', 'MRA Abdominal vessels WO contrast', 'MRA abdomen nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36878-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36881-1', 'MRA Head vessels WO contrast', 'MRA brain nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36881-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36883-7', 'MRA Pelvis vessels WO contrast', 'MRA pelvis nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36883-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37244-1', 'MR Temporomandibular joint W contrast IV', 'MR tempora mandibula joint (TMJ) dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37244-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37272-2', 'MR Mediastinum WO and W contrast IV', 'MR mediastinum dengan kontras', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37272-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37284-7', 'MR Temporomandibular joint WO contrast', 'MR tempora mandibula joint (TMJ) nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37284-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37443-9', 'MR spectroscopy Unspecified body region', 'MR spectroscopy mammae dengan kontras', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37443-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37448-8', 'MR Wrist W contrast IV', 'MR pergelangan tangan dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37448-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37449-6', 'MR Wrist - bilateral W contrast IV', 'MR pergelangan tangan bilateral dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37449-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37451-2', 'MR Wrist - left W contrast IV', 'MR pergelangan tangan kiri dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37451-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37453-8', 'MR Wrist - right W contrast IV', 'MR pergelangan tangan kanan dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37453-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37460-3', 'MR Wrist WO contrast', 'MR pergelangan tangan nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37460-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37462-9', 'MR Wrist - bilateral WO contrast', 'MR pergelangan tangan bilateral nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37462-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37497-5', 'MRA Spine vessels', 'MRA vertebra', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37497-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37500-6', 'MRA Spine vessels W contrast IV', 'MRA spinal dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37500-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37503-0', 'MRA Thoracic spine vessels W contrast IV', 'MRA vertebra thoracal dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37503-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37505-5', 'MRA Spine vessels WO and W contrast IV', 'MRA vertebra nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37505-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37506-3', 'MRA Cervical spine vessels WO and W contrast IV', 'MRA vertebra cervical nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37506-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37507-1', 'MRA Lumbar spine vessels WO and W contrast IV', 'MRA vertebra lumbar nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37507-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37508-9', 'MRA Thoracic spine vessels WO and W contrast IV', 'MRA vertebra thoracal nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37508-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37510-5', 'MRA Spine vessels WO contrast', 'MRA vertebra nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37510-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37512-1', 'MRA Thoracic spine vessels WO contrast', 'MRA vertebra thoracal nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37512-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38061-8', 'MR Spine Cervical and Spine Thoracic and Spine Lumbar and Sacrum W contrast IV', 'MR whole spine nonkontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38061-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39028-6', 'MR Guidance for needle localization of Unspecified body region', 'MR guidance untuk penempatan jarum', 'Guidance for needle localization', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39028-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39033-6', 'MR Upper extremity WO contrast', 'MR ekstremitas atas nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39033-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39034-4', 'MR Upper extremity WO and W contrast IV', 'MR ekstremitas atas nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39034-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39037-7', 'MR Upper extremity W contrast IV', 'MR ekstremitas atas dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39037-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39140-9', 'MR Heart cine for blood flow velocity mapping', 'MR cardiac untuk mapping kecepatan aliran', 'Multisection cine for blood flow velocity mapping', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39140-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39141-7', 'MR Bone marrow', 'MR aliran darah sumsum tulang', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39141-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39291-0', 'MR Lower extremity WO and W contrast IV', 'MR ekstremitas bawah nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39291-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39292-8', 'MR Lower extremity WO contrast', 'MR ekstremitas bawah nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39292-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39293-6', 'MR Lower extremity W contrast IV', 'MR ekstremitas bawah dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39293-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42302-0', 'MR Clavicle WO contrast', 'MR clavikula nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42302-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42694-0', 'MR Clavicle W contrast IV', 'MR clavikula dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42694-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42695-7', 'MR Lower leg - bilateral W contrast IV', 'MR cruris bilateral dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42695-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43455-5', 'MR Oropharynx', 'MR orofaring dengan kontras', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43455-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43528-9', 'MR Breast - unilateral WO and W contrast IV', 'MR payudara unilateral nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43528-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44128-7', 'MRA Lower extremity vessels WO and W contrast IV', 'MRA ekstremitas bawah nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44128-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44131-1', 'MRA Thoracic and abdominal aorta WO and W contrast IV', 'MRA whole aorta dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44131-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44132-9', 'MRA Thoracic and abdominal aorta WO contrast', 'MRA whole aorta nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44132-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '46299-4', 'MR Breast - unilateral', 'MR payudara unilateral', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '46299-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '46310-9', 'MR Orbit and Face and Neck WO and W contrast IV', 'MR orbita, wajah, dan leher nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '46310-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '46321-6', 'MR Orbit and Face and Neck W contrast IV', 'MR orbita, wajah, dan leher dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '46321-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '46323-2', 'MR Breast - unilateral W contrast IV', 'MR payudara unilateral dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '46323-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '46324-0', 'MRA Lower extremity vessels W contrast IV', 'MRA ekstremitas bawah dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '46324-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '46332-3', 'MR Orbit and Face and Neck WO contrast', 'MR orbita, wajah, dan leher nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '46332-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '46333-1', 'MR Breast - unilateral WO contrast', 'MR payudara unilateral nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '46333-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '46358-8', 'MR Whole body', 'MR seluruh tubuh', NULL, 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '46358-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48436-0', 'MR Lumbar spine W contrast IT', 'MR vertebra lumbal dengan kontras IT', 'Multisection^W contrast IT', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48436-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48439-4', 'MR Thoracic spine W contrast IT', 'MR vertebra thoracal dengan kontras IT', 'Multisection^W contrast IT', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48439-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48440-2', 'MR Skull base W contrast IV', 'MR mastoid dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48440-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48441-0', 'MR Thoracic spine WO and W contrast IT', 'MR vertebra thoracal nonkontras diikuti dengan kontras IT', 'Multisection^WO & W contrast IT', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48441-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48445-1', 'MR Larynx WO contrast', 'MR laring nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48445-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48447-7', 'MR Cervical spine W contrast IT', 'MR vertebra cervical dengan kontras IT', 'Multisection^W contrast IT', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48447-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48450-1', 'MR Cervical spine WO and W contrast IT', 'MR vertebra cervical nonkontras diikuti dengan kontras IT', 'Multisection^WO & W contrast IT', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48450-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48452-7', 'MR Lumbar spine WO and W contrast IT', 'MR vertebra lumbal nonkontras diikuti dengan kontras IT', 'Multisection^WO & W contrast IT', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48452-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48687-8', 'MR Skull base WO contrast', 'MR mastoid nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48687-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '49565-5', 'MRA Thoracic spine vessels', 'MRA vertebra thoracal', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '49565-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '58740-2', 'MRCP Abdomen WO contrast', 'MRCP abdomen atas nonkontras', 'Guidance for endoscopy^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '58740-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '58748-5', 'Functional MR Brain', 'MR functional brain nonkontras', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '58748-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '58750-1', 'MR Heart W stress', 'MR cardiac dengan stress imaging', 'Multisection^W stress', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '58750-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69163-4', 'MR Ankle - bilateral W contrast IV', 'MR pergelangan kaki bilateral dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69163-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69164-2', 'MR Ankle - bilateral WO contrast', 'MR kaki bilateral nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69164-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69170-9', 'MR Elbow - bilateral W contrast IV', 'MR cubiti bilateral dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69170-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69171-7', 'MR Elbow - bilateral WO contrast', 'MR cubiti bilateral nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69171-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69173-3', 'MR Femur - bilateral WO contrast', 'MR femur bilateral nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69173-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69175-8', 'MR Forearm - bilateral W contrast IV', 'MR antebrachi bilateral dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69175-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69176-6', 'MR Forearm - bilateral WO contrast', 'MR antebrachi bilateral nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69176-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69178-2', 'MR Hand - bilateral W contrast IV', 'MR manus bilateral dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69178-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69179-0', 'MR Hand - bilateral WO contrast', 'MR manus bilateral nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69179-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69180-8', 'MR Upper arm - bilateral', 'MR humerus bilateral dengan kontras IV', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69180-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69183-2', 'MR Upper arm - bilateral WO contrast', 'MR humerus bilateral nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69183-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69184-0', 'MR Shoulder - bilateral W contrast IV', 'MR bahu bilateral dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69184-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69185-7', 'MR Lower leg - bilateral WO contrast', 'MR cruris bilateral nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69185-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69195-6', 'MR Finger W contrast IV', 'MR phalang manus dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69195-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69196-4', 'MR Finger WO contrast', 'MR phalang manus nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69196-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69205-3', 'MR Finger - left W contrast IV', 'MR phalang manus kiri dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69205-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69210-3', 'MR Lower Extremity Joint Arthrogram', 'MR sendi ekstremitas bawah arthrogram', 'Multisection^W contrast IS', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69210-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69211-1', 'MR Nasal bones', 'MR os nasal nonkontras', 'Multisection', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69211-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69215-2', 'MR Finger - right W contrast IV', 'MR phalang manus kanan dengan kontras', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69215-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '72245-4', 'MR Pelvis Defecography W contrast PR', 'MR defekografi', 'Multisection^W contrast PR+at rest+maximal sphincter contraction+during straining+during defecation', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '72245-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '72246-2', 'MR Abdomen and Pelvis W contrast PO and WO and W contrast IV', 'MR enterography dengan kontras', 'Multisection^W contrast PO+WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '72246-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '72247-0', 'MR Abdomen and Pelvis W contrast PO and WO contrast IV', 'MR enterografi', NULL, 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '72247-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '72248-8', 'MRCP Abdomen WO and W contrast IV', 'MRCP abdomen atas nonkontras diikuti dengan kontras IV', 'Guidance for endoscopy^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '72248-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80495-5', 'MR Mediastinum WO contrast', 'MR mediastinum nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80495-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80501-0', 'MR Small bowel W contrast PO and WO contrast IV', 'MR enterography nonkontras', 'Multisection^W contrast PO+WO contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80501-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80502-8', 'MRA Abdominal Aorta and Bilateral Runoff Vessels WO and W contrast IV', 'MRA aorta abdominalis nonkontras diikuti dengan kontras IV bilateral', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80502-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '89284-4', 'MR Cervical and thoracic spine W contrast IV', 'MR vertebra cervikal + thoracal dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '89284-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '91561-1', 'MR Cervical and thoracic and lumbar spine W contrast IV', 'MR vertebra lengkap (wholespine) dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '91561-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '91597-5', 'MR Toe WO and W contrast IV', 'MR phalang pedis dengan kontras', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '91597-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '91715-3', 'MR Spine Lumbar and Sacrum W contrast IV', 'MR lumbosacral dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '91715-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '91717-9', 'MR Spine Lumbar and Sacrum WO contrast', 'MR lumbosacral nonkontras', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '91717-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '94088-2', 'MR Thoracic and lumbar spine WO and W contrast', 'MR vertebra thoracolumbal nonkontras diikuti dengan kontras', 'Multisection^WO & W contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '94088-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '95923-9', 'MR Heart W stress and W contrast IV', 'MR cardiac nonkontras stress imaging diikuti dengan kontras IV', 'Multisection^W stress+W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '95923-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '97391-7', 'MR Abdomen and Pelvis WO contrast', 'MR abdomen bawah dan pelvis nonkontras IV', 'Multisection^WO contrast', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '97391-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '97392-5', 'MR Abdomen and Pelvis WO and W contrast IV', 'MR abdomen lengkap (abdomen atas + pelvis) nonkontras diikuti dengan kontras IV', 'Multisection^WO & W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '97392-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '97393-3', 'MR Abdomen and Pelvis W contrast IV', 'MR abdomen bawah dan pelvis dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '97393-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '99609-0', 'MRA Thoracic Aorta W contrast IV', 'MRA aorta thoracalis dengan kontras IV', 'Multisection^W contrast IV', 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '99609-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '99702-3', 'MR tractography Brain', 'MR otak DTI dengan kontras', NULL, 'RAD/MRI', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '99702-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24570-4', 'RF Guidance for removal of calculus from Biliary duct common-- W contrast retrograde intra biliary', 'RF guidance pengangkatan batu duktus biliaris pasca operasi, perkutaneus melalui saluran T-tube, basket, atau snare (seperti, teknik Burhenne),', 'Guidance for removal of calculus^W contrast retrograde intra biliary', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24570-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24574-6', 'RF Biliary ducts and Gallbladder Views during surgery W contrast biliary duct', 'RF intra-operatif cholangiografi', 'Views^during surgery W contrast biliary duct', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24574-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24575-3', 'RF Biliary ducts and Gallbladder Views W contrast percutaneous transhepatic', 'RF kolangiografi perkutaneus transhepatik', 'Views^W contrast percutaneous transhepatic', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24575-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24666-0', 'RF Colon Views W air and barium contrast PR', 'Colon in loop releasae intususepsi', 'Views^W air contrast PR+W barium contrast PR', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24666-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24678-5', 'RF Esophagus Views W contrast PO', 'RF esofagografi', 'Views^W contrast PO', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24678-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24680-1', 'RF Guidance for dilation of Esophagus', 'RF guidance pelebaran esofagus', 'Guidance for dilation', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24680-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24681-9', 'RF videography Hypopharynx and Esophagus Views', 'RF fungsi menelan, dengan cineXR/videoXR', 'Views^W contrast PO+during swallowing', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24681-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24682-7', 'RF videography Hypopharynx and Esophagus Views W liquid and paste contrast PO during swallowing', 'Videofluoroscopic Swallow Study (VFSS)', 'Views^W liquid contrast PO+W paste contrast PO+during swallowing', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24682-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24764-3', 'RF Hip Arthrogram', 'RF artrografi coxae', 'Views^W contrast IS', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24764-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24779-1', 'RF Guidance for percutaneous placement of nephrostomy tube of Kidney - bilateral-- W contrast via tube', 'RF guidance pemasangan kateter intra pelvis renalis secara perkutan untuk injeksi atau drainase;', 'Guidance for percutaneous placement of nephrostomy tube^W contrast via tube', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24779-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24800-5', 'RF Knee Arthrogram', 'RF artrografi genu', 'Views^W contrast IS', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24800-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24902-9', 'RF Salivary gland Views W contrast intra salivary duct', 'RF sialografi', 'Views^W contrast intra salivary duct', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24902-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24910-2', 'RF Shoulder Arthrogram', 'RF artrografi bahu', 'Views^W contrast IS', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24910-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24912-8', 'RF Sinus tract Views W contrast intra sinus tract', 'RF sinus fistula atau abses dengan kontras intra sinus', 'Views^W contrast intra sinus tract', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24912-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24923-5', 'RF Small bowel Views W positive contrast via enteroclysis tube', 'RF usus halus melalui tabung enteroklisis', 'Views^W positive contrast via enteroclysis tube', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24923-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24947-4', 'RF Cervical spine Views W contrast IT', 'RF mielografi cervical', 'Views^W contrast IT', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24947-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24974-8', 'RF Lumbar spine Views W contrast IT', 'RF mielografi lumbal degan kontras', 'Views^W contrast IT', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24974-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24985-4', 'RF Thoracic spine Views W contrast IT', 'RF mielografi torakal', 'Views^W contrast IT', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24985-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24991-2', 'RFA Splenic vein and Portal vein Views W contrast IA', 'RFA splenoportografi dengan kontras IA', 'Views^W contrast IA', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24991-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '25016-7', 'RF Urethra Views W contrast intra urethra', 'RF urethrografi', 'Views^W contrast intra urethra', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '25016-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '25017-5', 'RF Urinary bladder and Urethra Views W contrast intra bladder', 'RF cystourethrography', 'Views^W contrast intra bladder', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '25017-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '25020-9', 'RF Urinary bladder and Urethra Views W contrast retrograde via urethra', 'RF uretrosistografi retrograd', 'Views^W contrast retrograde via urethra', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '25020-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '25022-5', 'RF Uterus and Fallopian tubes Views W contrast IU', 'RF histerosalfingografi (HSG)', 'Views^W contrast IU', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '25022-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '25026-6', 'RFA Guidance for placement of venous filter in Inferior vena cava-- W contrast IV', 'RFA guidance penempatan filter fena pada vena cava inferior', 'Guidance for placement of venous filter^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '25026-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '25034-0', 'RF Wrist Arthrogram', 'RF artrografi pergelangan tangan', 'Views^W contrast IS', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '25034-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '25070-4', 'RF Unspecified body region Views during surgery', 'C-Arm guided intraoperatif', 'Views^during surgery', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '25070-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '25079-5', 'RFA Renal arteries Views W contrast IA', 'DSA arteri renal dengan kontras', 'Views^W contrast IA', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '25079-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26067-9', 'RF Salivary gland - bilateral Views W contrast intra salivary duct', 'RF sialografi bilateral', 'Views^W contrast intra salivary duct', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26067-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30628-2', 'RF Guidance for removal of foreign body of Unspecified body region', 'RF guidance pengangkatan benda asing esofagus', 'Guidance for substance removal of foreign body', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30628-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30633-2', 'RF Esophagus Views W barium contrast PO', 'RF esofagus (esofagogram)', 'Views^W barium contrast PO', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30633-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30636-5', 'RF Colon Views for reduction W contrast PR', 'RF enema terapi, kontras atau udara, untuk reduksi intususepsi atau obstruksi intraluminal lain (seperti, ileus mekonium)', 'Views for reduction^W contrast PR', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30636-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30645-6', 'RFA Superior vena cava Views W contrast IV', 'RFA vena cava superior dengan kontras IV', 'Views^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30645-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30647-2', 'RF Biliary ducts and Gallbladder Views W contrast via T-tube', 'RF cholangiografi t. tube w kontras', 'Views^W contrast via T-tube', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30647-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30808-0', 'RF Cervical and thoracic and lumbar spine Views W contrast IT', 'RF mielografi cervical, thoracal, lumbal', 'Views^W contrast IT', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30808-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30810-6', 'RF Lacrimal duct Views W contrast intra lacrimal duct', 'RF dakriosistografi', 'Views^W contrast intra lacrimal duct', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30810-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30811-4', 'RF Posterior fossa Views W contrast IT', 'RF mielografi fossa posterior dengan kontras IT', 'Views^W contrast IT', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30811-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30815-5', 'RF Guidance for endoscopy of Biliary ducts and Pancreatic duct-- W contrast retrograde', 'ERCP + tindakan', 'Guidance for endoscopy^W contrast retrograde', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30815-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30816-3', 'RFA Peritoneum Views W contrast percutaneous intraperitoneal', 'RFA peritoneogram', 'Views^W contrast percutaneous intraperitoneal', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30816-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30819-7', 'RFA Epidural veins Views W contrast IV', 'RFA vena epidural dengan kontras IV', 'Views^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30819-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30820-5', 'RFA Carotid artery.external - bilateral Views W contrast IA', 'RFA arteri karotis eksterna bilateral dengan kontras IA', 'Views^W contrast IA', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30820-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30824-7', 'RFA Intercranial vessel and Neck Vessel Views W contrast', 'RFA arteri servikoserebral dengan kontras IA', 'Views^W contrast', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30824-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30825-4', 'RFA Orbit veins Views W contrast IV', 'RFA vena orbita dengan kontras IV', 'Views^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30825-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30829-6', 'RFA Internal thoracic artery Views W contrast IA', 'RFA arteri mammaria dengan kontras IA', 'Views^W contrast IA', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30829-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30830-4', 'RFA Pulmonary artery - bilateral Views W contrast IA', 'RFA arteri pulmonal bilateral dengan kontras IA', 'Views^W contrast IA', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30830-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30834-6', 'RFA Renal artery - bilateral Views W contrast IA', 'RFA arteri ginjal bilateral dengan kontras IA', 'Views^W contrast IA', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30834-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30837-9', 'RFA Abdominal Aorta Views W contrast IA', 'RFA aorta abdominal dengan kontras IA', 'Views^W contrast IA', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30837-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30838-7', 'RFA Aorta and Femoral artery - bilateral Runoff W contrast IA', 'RFA aorta abdominal dengan iliofemoral ekstremitas bawah bilateral dengan kontras IA', 'Views runoff^W contrast IA', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30838-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30841-1', 'RFA Portal vein Views W contrast transhepatic', 'RFA percutaneous transhepatic portography dengan kontras transhepatic', 'Views^W contrast transhepatic', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30841-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30844-5', 'RFA Adrenal vein - bilateral Views W contrast IV', 'RFA vena adrenal bilateral dengan kontras IV', 'Views^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30844-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30845-2', 'RFA Inferior vena cava Views W contrast IV', 'RFA vena cava inferior dengan kontras IV', 'Views^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30845-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30846-0', 'RFA Renal vein - bilateral Views W contrast IV', 'RFA vena ginjal bilateral dengan kontras IV', 'Views^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30846-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30851-0', 'RFA Extremity lymphatic vessels - bilateral Views W contrast intra lymphatic', 'RFA pembuluh darah limfatik ekstremitas bilateral dengan kontras intra lymphatic', 'Views^W contrast intra lymphatic', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30851-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '35900-0', 'RF Guidance for percutaneous biopsy of Liver', 'RF guidance untuk biopsi perkutan hati', 'Guidance for percutaneous biopsy', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '35900-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '35912-5', 'RF Guidance for placement of catheter in Unspecified body region', 'RF fluoroskopi untuk mencari akses dan pemasangan kateter, penggantian kateter, atau pelepasan kateter;', 'Guidance for placement of catheter', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '35912-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37173-2', 'RFA Cerebral artery Views W contrast IA', 'DSA diagnostik cerebral', 'Views^W contrast IA', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37173-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37183-1', 'RF Ankle Arthrogram', 'RF artrografi pergelangan kaki', 'Views^W contrast IS', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37183-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37186-4', 'RF Elbow Arthrogram', 'RF artrografi cubiti', 'Views^W contrast IS', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37186-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37191-4', 'RF Joint Arthrogram', 'RF artrografi', 'Views^W contrast IS', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37191-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37192-2', 'RF Cervical spine Views W contrast intradisc', 'RF diskografi cervikal', 'Views^W contrast intradisc', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37192-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37193-0', 'RF Lumbar spine Views W contrast intradisc', 'RF diskografi lumbal', 'Views^W contrast intradisc', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37193-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37195-5', 'RFA Cerebral vein Views W contrast IV', 'DSA diagnostik cerebral', 'Views^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37195-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37390-2', 'RFA Carotid artery.external - left Views W contrast IA', 'RFA arteri karotis eksterna kiri dengan kontras IA', 'Views^W contrast IA', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37390-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37407-4', 'RFA Vertebral artery - bilateral Views W contrast IA', 'DSA arteri vertebralis bilateral dengan kontras IV', 'Views^W contrast IA', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37407-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37412-4', 'RFA Extremity veins - bilateral Views W contrast IV', 'RFA vena ekstremitas bilateral dengan kontras IV', 'Views^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37412-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37422-3', 'RFA Orbit veins - left Views W contrast IV', 'DSA vena orbit kiri dengan kontras IV', 'Views^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37422-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37567-5', 'RF Colon Views W contrast via colostomy', 'RF lopografi', 'Views^W contrast via colostomy', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37567-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37569-1', 'RF Urinary bladder Views W contrast via suprapubic tube', 'RF sistografi kontras suprapubik', 'Views^W contrast via suprapubic tube', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37569-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37586-5', 'RF Penis Views W contrast intra corpus cavernosum', 'RF corpora cavernosografi', 'Views^W contrast intra corpus cavernosum', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37586-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37596-4', 'RFA Abdominal and pelvic lymphatic vessels - left Views W contrast intra lymphatic', 'RFA pembuluh darah limfatik pelvis dan abdomen kiri dengan kontras intra lymphatic', 'Views^W contrast intra lymphatic', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37596-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37598-0', 'RFA Abdominal and pelvic lymphatic vessels - bilateral Views W contrast intra lymphatic', 'RFA pembuluh darah limfatik pelvis dan abdomen bilateral dengan kontras intra lymphatic', 'Views^W contrast intra lymphatic', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37598-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37599-8', 'RFA Extremity lymphatic vessels - left Views W contrast intra lymphatic', 'RFA pembuluh darah limfatik ekstremitas kiri dengan kontras intra lymphatic', 'Views^W contrast intra lymphatic', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37599-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37602-0', 'RFA Adrenal vein - left Views W contrast IV', 'RFA vena adrenal kiri dengan kontras IV', 'Views^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37602-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37615-2', 'RFA Pelvis vessels Views W contrast', 'RFA pembuluh darah pelvis dengan kontras', 'Views^W contrast', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37615-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37640-0', 'RFA Renal vessels Views W contrast', 'RFA pembuluh darah ginjal', 'Views^W contrast', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37640-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37647-5', 'RF Sacroiliac Joint Arthrogram', 'RF artrografi sakroiliaka', 'Views^W contrast IS', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37647-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37901-6', 'RF Temporomandibular joint Arthrogram', 'RF artrografi sendi temporomandibula', 'Views^W contrast IS', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37901-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37940-4', 'RFA Adrenal vein - right Views W contrast IV', 'RFA vena adrenal kanan dengan kontras IV', 'Views^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37940-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37944-6', 'RFA Carotid artery and Cerebral artery - right Views W contrast IA', 'RFA arteri karotis dan arteri serebral kanan dengan kontras IA', 'Views^W contrast IA', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37944-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37945-3', 'RFA Carotid artery.cervical - right Views W contrast IA', 'RFA arteri karotis cervical kanan dengan kontras IA', 'Views^W contrast IA', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37945-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37948-7', 'RFA Carotid artery.external - right Views W contrast IA', 'RFA arteri karotis eksterna kanan dengan kontras IA', 'Views^W contrast IA', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37948-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37958-6', 'RFA Orbit veins - right Views W contrast IV', 'DSA vena orbit kanan dengan kontras IV', 'Views^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37958-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37969-3', 'RFA Sinus vein Views W contrast IV', 'RFA vena sinus venosus (petrosus dan sagital inferior) atau jugular dengan kontras IV', 'Views^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37969-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37981-8', 'RFA Visceral vessels Views W contrast', 'RFA pembuluh darah viseral dengan kontras', 'Views^W contrast', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37981-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38098-0', 'RF Lacrimal duct - bilateral Views W contrast intra lacrimal duct', 'RF dacryocystography bilateral', 'Views^W contrast intra lacrimal duct', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38098-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38099-8', 'RF Lacrimal duct - left Views W contrast intra lacrimal duct', 'RF dacryocystography kiri', 'Views^W contrast intra lacrimal duct', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38099-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38103-8', 'RF Spine Cervical and Spine Lumbar Views W contrast IT', 'RF mielografi servikal & lumbal', 'Views^W contrast IT', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38103-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38104-6', 'RF Spine epidural space Views W contrast IT', 'RF epidurografi', 'Views^W contrast IT', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38104-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38811-6', 'RFA Abdominal and pelvic lymphatic vessels - right Views W contrast intra lymphatic', 'RFA pembuluh darah limfatik pelvis dan abdomen kanan dengan kontras intra lymphatic', 'Views^W contrast intra lymphatic', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38811-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38812-4', 'RFA Extremity lymphatic vessels - right Views W contrast intra lymphatic', 'RFA pembuluh darah limfatik ekstremitas kanan dengan kontras intra lymphatic', 'Views^W contrast intra lymphatic', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38812-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38827-2', 'RF Lacrimal duct - right Views W contrast intra lacrimal duct', 'RF dacryocystography kanan', 'Views^W contrast intra lacrimal duct', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38827-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38862-9', 'RFA Carotid artery and Cerebral artery - left Views W contrast IA', 'RFA arteri karotis dan arteri serebral kiri dengan kontras IA', 'Views^W contrast IA', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38862-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38863-7', 'RFA Carotid artery.cervical - left Views W contrast IA', 'RFA arteri karotis cervical kiri dengan kontras IA', 'Views^W contrast IA', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38863-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39027-8', 'RF Guidance for needle localization of Unspecified body region', 'RF fluoroskopi untuk lokalisasi jarum atau kateter vertebra untuk diagnostik atau terapetik', 'Guidance for needle localization', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39027-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39057-5', 'RFA Pulmonary arteries Views W contrast IA', 'DSA pulmonary vein dengan kontras', 'Views^W contrast IA', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39057-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39093-0', 'RFA Hepatic veins Views W contrast IV', 'RFA vena hepatik tanpa evaluasi hemodinamik dengan kontras IV', 'Views^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39093-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39096-3', 'RFA Hepatic veins Views for hemodynamics W contrast IV', 'RFA vena hepatik evaluasi hemodinamik dengan kontras IV', 'Views for hemodynamics^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39096-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39097-1', 'RFA Carotid artery - bilateral and Cerebral artery - bilateral Views W contrast IA', 'RFA arteri karotis bilateral dan arteri serebral bilateral dengan kontras IA', 'Views^W contrast IA', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39097-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39098-9', 'RFA Carotid artery.cervical.bilateral Views W contrast IA', 'RFA arteri karotis cervical bilateral dengan kontras IA', 'Views^W contrast IA', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39098-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39151-6', 'RF Vas deferens Views W contrast intra vas deferens', 'RF vasografi', 'Views^W contrast intra vas deferens', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39151-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42157-8', 'RFA Extremity vessels Views W contrast IV', 'RFA pembuluh darah ekstremitas bilateral dengan kontras IV', 'Views^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42157-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42459-8', 'RF Gastrointestinal tract upper Views W contrast PO', 'RF maagduodenography', 'Views^W contrast PO', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42459-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42702-1', 'RF Greater than 1 hour', 'RF fluoroscopy dengan durasi lebih dari 1 jam', 'View', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42702-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43559-4', 'RF Urinary bladder and Urethra Views W contrast intra bladder during voiding', 'RF MCU (Micturating Cysto Urethrography)', 'Views^W contrast intra bladder+during voiding', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43559-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43574-3', 'RF Upper gastrointestinal tract and Small bowel Views W barium contrast PO', 'RF followtrough', 'Views^W barium contrast PO', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43574-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44213-7', 'RF Guidance for endoscopy of Pancreatic duct-- W contrast retrograde', 'RF kateterisasi endoskopi sistem duktus pankreatikus', 'Guidance for endoscopy^W contrast retrograde', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44213-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44214-5', 'RF Guidance for endoscopy of Biliary ducts-- W contrast retrograde', 'RF kateterisasi endoskopi sistem duktus biliaris', 'Guidance for endoscopy^W contrast retrograde', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44214-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44225-1', 'RF Guidance for biopsy of Liver-- W contrast IV', 'RF guidance untuk biopsi hati dengan kontras IV', 'Guidance for percutaneous biopsy^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44225-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44227-7', 'RF Colon Views W barium contrast PR', 'RF kolon dengan barium enema', 'Views^W barium contrast PR', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44227-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48465-9', 'RF Larynx Views', 'RF laringografi kontras', 'Views', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48465-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '63520-1', 'PhenX - body composition by dual-energy x-ray absorptiometry protocol 020301', 'DXA total body composition', 'PhenX - body composition by dual-energy x-ray absorptiometry protocol 020301', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '63520-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '64140-7', 'RFA Renal vessels - left Views W contrast', 'RFA pembuluh darah renal kiri', 'Views^W contrast', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '64140-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '64141-5', 'RFA Renal vessels - right Views W contrast', 'RFA pembuluh darah renal kanan', 'Views^W contrast', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '64141-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '65799-9', 'RF Kidney - bilateral Single view for cyst', 'RF studi kista renal', 'View for cyst', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '65799-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69242-6', 'RF Guidance for percutaneous drainage of abscess and placement of drainage catheter of Appendix', 'Appendicography', 'Guidance for percutaneous drainage of abscess+placement of drainage catheter', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69242-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '70923-8', 'RF Guidance for percutaneous vertebroplasty of Cervical spine', 'RF vertebroplasty servikal perkutan dengan pedoman fluoroskopi,', 'Guidance for percutaneous vertebroplasty', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '70923-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '70924-6', 'RF Guidance for percutaneous vertebroplasty of Lumbar spine', 'RF vertebroplasty lumbal perkutan dengan pedoman fluoroskopi,', 'Guidance for percutaneous vertebroplasty', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '70924-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '70925-3', 'RF Guidance for percutaneous vertebroplasty of Thoracic spine', 'RF vertebroplasty thorakal perkutan dengan pedoman fluoroskopi,', 'Guidance for percutaneous vertebroplasty', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '70925-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '70933-7', 'RF Thoracic spine Views W contrast intradisc', 'RF diskografi thorakal', 'Views^W contrast intradisc', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '70933-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '75853-2', 'RF Vagina Views W contrast VG', 'RF vaginogram', 'Views^W contrast VG', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '75853-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86372-0', 'RF Kidney and Ureter and Urinary bladder Views W contrast IV', 'RF BNO - IVP W kontras', 'Views^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86372-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86376-1', 'RF Biliary ducts Views W contrast via existing catheter', 'RF kolangiografi dan/atau pankreatografi melalui kateter yang telah ada', 'Views^W contrast via existing catheter', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86376-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86379-5', 'RF Small bowel Views for loop diversion', 'RF lopografi distal', 'Views for loop diversion', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86379-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86387-8', 'RF Kidney and Ureter and Urinary bladder Views W contrast antegrade', 'RF APG (antegrade pyelography) kontras', 'Views^W contrast antegrade', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86387-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86388-6', 'RF Kidney and Ureter and Urinary bladder Views W contrast retrograde', 'RF RPG (retrogard pyelography) kontras', 'Views^W contrast retrograde', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86388-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86389-4', 'RF Kidney and Ureter and Urinary bladder Views during surgery W contrast retrograde', 'RF RPG (retrogard pyelography) kontras', 'Views^during surgery W contrast retrograde', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86389-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86393-6', 'RF Kidney - bilateral and Ureter - bilateral and Urinary bladder Views W contrast retrograde', 'RF RPG (retrogard pyelography) kontras', 'Views^W contrast retrograde', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86393-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86394-4', 'RFA Thoracic Aorta Views W contrast IA', 'RFA aorta thorakal dengan kontras IA', 'Views^W contrast IA', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86394-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86395-1', 'RF videography Hypopharynx and Esophagus Views for swallowing function W speech and W barium contrast PO', 'RF evaluasi faring dan bicara dinamik kompleks dengan cine atau rekaman video', 'Views for swallowing function^W speech+W barium contrast PO', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86395-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86396-9', 'RFA Extremity veins - unilateral Views W contrast IV', 'RFA vena ekstremitas unilateral dengan kontras IV', 'Views^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86396-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86398-5', 'RF Cerebral cisterns Views W contrast IT', 'RF sisternografi kontras IT', 'Views^W contrast IT', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86398-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86400-9', 'RF Guidance for placement of needle in Unspecified body region', 'RF fluoroskopi untuk pemasangan jarum (contoh: biopsi, aspirasi, injeksi)', 'Guidance for placement of needle', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86400-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86401-7', 'RF Urinary bladder Views W contrast intra bladder', 'RF cystogram w kontras intrabladder', 'Views^W contrast intra bladder', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86401-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86403-3', 'RF Guidance for placement of catheter in Fallopian tube', 'RF kateterisasi transcervical tuba fallopii', 'Guidance for placement of catheter', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86403-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86407-4', 'RF Guidance of Unspecified body region', 'C-Arm guided non-operatif', 'Guidance', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86407-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86409-0', 'RF Rectum Views for rectal dysfunction W barium contrast PR', 'RF rectografi', 'Views for rectal dysfunction^W barium contrast PR', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86409-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86410-8', 'RF Pharynx and Cervical esophagus Views W barium contrast PO', 'RF faring dan esofagus servikal', 'Views^W barium contrast PO', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86410-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86413-2', 'RF Guidance for placement of long feeding tube in Gastrointestinal tract', 'RF pemasangan tabung gastrointestinal panjang (seperti Miller-Abbott), termasuk fluoroskopi dan film multipel,', 'Guidance for placement of long feeding tube', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86413-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86414-0', 'RFA Renal arteries - unilateral Views W contrast IA', 'RFA arteri ginjal unilateral dengan kontras IA', 'Views^W contrast IA', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86414-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86415-7', 'RFA Renal vein - unilateral Views W contrast IV', 'RFA vena ginjal unilateral dengan kontras IV', 'Views^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86415-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86418-1', 'RFA Pulmonary arteries - unilateral Views W contrast IA', 'RFA arteri pulmonal unilateral dengan kontras', 'Views^W contrast IA', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86418-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86426-4', 'RFA Jugular vein - unilateral Views W contrast IV', 'DSA vena jugularis unilateral dengan kontras IV', 'Views^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86426-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86435-5', 'RFA Jugular vein - bilateral Views W contrast IV', 'DSA vena jugularis bilateral dengan kontras IV', 'Views^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86435-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86437-1', 'RF Fistula Diagnostic W contrast intra fistula', 'RF fistulogram', 'Views diagnostic^W contrast intra fistula', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86437-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86440-5', 'RF Seminal vesicle Views W contrast intra seminal vesicle', 'RF vesikulogram', 'Views^W contrast intra seminal vesicle', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86440-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86442-1', 'RFA Vertebral artery - unilateral Views W contrast IA', 'DSA arteri vertebralis unilateral dengan kontras IV', 'Views^W contrast IA', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86442-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86460-3', 'RFA Spine vessel Views', 'RFA pembuluh darah spinal', 'Views', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86460-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86461-1', 'RFA Adrenal vessel Views', 'RFA pembuluh darah adrenal bilateral', 'Views', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86461-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '88831-3', 'RF Kidney - right and Ureter Views W contrast retrograde intra ureter', 'RF RPG (retrogard pyelography) kontras', 'Views^W contrast retrograde intra ureter', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '88831-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '88832-1', 'RF Kidney - left and Ureter Views W contrast retrograde intra ureter', 'RF RPG (retrogard pyelography) kontras', 'Views^W contrast retrograde intra ureter', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '88832-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '88833-9', 'RF Kidney - bilateral and Ureter Views W contrast retrograde intra ureter', 'RF RPG (retrogard pyelography) kontras', 'Views^W contrast retrograde intra ureter', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '88833-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '88834-7', 'RF Guidance for dilation of nephrostomy tract, ureter, or urethra', 'RF guidance dilatasi nefrostomi, ureter, atau uretra;', 'Guidance for dilation', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '88834-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '94094-0', 'RFA Unspecified body region Limited Views for therapy or embolization or infusion W contrast via existing catheter', 'RFA terapi transkateter infus dengan kontras melalui kateter', 'Views limited for therapy or embolization or infusion^W contrast via existing catheter', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '94094-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '94095-7', 'RFA Extremity vessels Unilateral Views W contrast IV', 'DSA diagnostik angiografi perifer (ekstremitas)', 'Views^W contrast IV', 'RAD/RF', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '94095-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '11525-3', 'US for pregnancy', 'US kehamilan', 'Multisection for pregnancy', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '11525-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '11630-1', 'Fetal Biophysical profile.amniotic fluid volume US', 'US profil biofisik janin amniotic fluid volume', 'Biophysical profile.amniotic fluid volume', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '11630-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '11631-9', 'Fetal Biophysical profile.body movement US', 'US profil biofisik janin body movement', 'Biophysical profile.body movement', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '11631-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '11632-7', 'Fetal Biophysical profile.breathing movement US', 'US profil biofisik janin breathing movement', 'Biophysical profile.breathing movement', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '11632-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '11633-5', 'Fetal Heart rate reactivity US', 'US profil biofisik janin heart rate reactivity', 'Heart rate reactivity', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '11633-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '11635-0', 'Fetal Biophysical profile.tone US', 'US profil biofisik janin tonus', 'Biophysical profile.tone', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '11635-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24531-6', 'US Retroperitoneum', 'US retroperitoneal B-scan lengkap', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24531-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24534-0', 'US.doppler Abdominal vessels', 'US doppler pembuluh darah abdomen', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24534-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24537-3', 'US Guidance for aspiration of amniotic fluid of Uterus', 'US guidance untuk amniosentesis', 'Guidance for aspiration of amniotic fluid', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24537-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24548-0', 'US Appendix', 'US appendiks', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24548-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24558-9', 'US Abdomen', 'US abdomen', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24558-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24559-7', 'US Guidance for drainage and placement of drainage catheter of Abdomen', 'US guided pungsi ascites', 'Guidance for drainage+placement of drainage catheter', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24559-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24600-9', 'US Guidance for needle localization of Breast', 'US guided for needle localization mammae', 'Guidance for needle localization', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24600-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24601-7', 'US Breast', 'US mammae', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24601-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24602-5', 'MG Guidance for biopsy of Breast', 'MG guidance untuk biopsi payudara atau penempatan jarum', 'Guidance for percutaneous biopsy', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24602-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24603-3', 'MG stereo Guidance for biopsy of Breast', 'MG guidance stereotaktik untuk biopsi payudara atau penempatan jarum', 'Guidance for percutaneous biopsy', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24603-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24630-6', 'US Chest', 'US thorax', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24630-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24662-9', 'US Guidance for fluid aspiration of Pleural space', 'US guidance untuk aspirasi cairan pada ruang pleura', 'Guidance for percutaneous aspiration of fluid', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24662-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24672-8', 'US Diaphragm for motion', 'US diafragma', 'Multisection for motion', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24672-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24677-7', 'US Pelvis transvaginal', 'US transvaginal', 'Multisection transvaginal', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24677-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24693-4', 'US Extremity', 'US ekstremitas lengkap', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24693-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24711-4', 'US Gallbladder', 'US empedu', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24711-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24719-7', 'US Groin', 'US inguinal', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24719-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24731-2', 'US Head', 'US ekoensefalografi B-scan', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24731-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24733-8', 'US.doppler Head vessels', 'US doppler arteri intra kranial pemeriksaan lengkap', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24733-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24760-1', 'US Hip', 'US coxae', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24760-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24816-1', 'US Guidance for biopsy of Liver', 'US guidance untuk biopsi hati', 'Guidance for percutaneous biopsy', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24816-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24842-7', 'US Neck', 'US leher', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24842-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24859-1', 'US Pancreas', 'US pankreas', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24859-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24869-0', 'US Pelvis', 'US pelvis', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24869-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24870-8', 'US.doppler Pelvis vessels', 'US doppler ginekologi', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24870-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24881-5', 'US Popliteal space', 'US genu', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24881-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24884-9', 'US Prostate transrectal', 'US prostat transrektal', 'Multisection transrectal', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24884-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24907-8', 'US Shoulder', 'US bahu', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24907-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24926-8', 'US Spine', 'US vertebra', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24926-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '25002-7', 'US Scrotum and testicle', 'US skrotum dan testis', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '25002-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '25010-0', 'US Thyroid gland', 'US thyroid', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '25010-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '25036-5', 'US Wrist', 'US wrist', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '25036-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '25059-7', 'US Guidance for biopsy of Unspecified body region', 'US guidance biopsi atau pengeluaran cairan', 'Guidance for percutaneous biopsy', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '25059-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26175-0', 'MG Breast - bilateral Screening', 'MG skrining bilateral', 'Views screening', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26175-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26265-9', 'US Shoulder - bilateral', 'US bahu bilateral', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26265-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26278-2', 'US Wrist - bilateral', 'US wrist bilateral', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26278-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '28614-6', 'US Liver', 'US liver', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '28614-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30651-4', 'US Guidance for percutaneous biopsy.core needle of Breast', 'US guided + core biopsy', 'Guidance for percutaneous biopsy.core needle', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30651-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30653-0', 'US Guidance for aspiration of cyst of Breast', 'US guided aspirasi kista mammae', 'Guidance for percutaneous aspiration of cyst', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30653-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30698-5', 'US Guidance for aspiration of cyst of Unspecified body region', 'US guided aspirasi kista', 'Guidance for percutaneous aspiration of cyst', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30698-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30703-3', 'US Guidance for fluid aspiration of Pericardial space', 'US guidance untuk perikardiosentesis', 'Guidance for percutaneous aspiration of fluid', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30703-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30704-1', 'US Abdomen limited', 'US FAST abdomen', 'Multisection limited', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30704-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30709-0', 'US Lower extremity', 'US ankle', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30709-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30710-8', 'US Upper extremity', 'US lengan', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30710-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30853-6', 'US Breast duct W contrast intra duct', 'US mammae dengan kontras intraduktal', 'Multisection^W contrast intra duct', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30853-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30878-3', 'US Guidance for fluid aspiration of Unspecified body region', 'US guided aspirasi', 'Guidance for percutaneous aspiration of fluid', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30878-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '33069-6', 'Fetal nuchal translucency measured by US', 'US fetal nuchal translucency', 'Width.translucency', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '33069-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '35093-4', 'Urology urinary tract ultrasound', 'US tractus urinarius', 'Urology urinary tract ultrasound', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '35093-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36626-0', 'MG Breast - bilateral Views', 'MG bilateral', 'Views', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36626-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36627-8', 'MG Breast - left Views', 'MG kiri', 'Views', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36627-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37608-7', 'US Guidance for localization of foreign body of Eye', 'US mata lokalisasi benda asing', 'Guidance for localization of foreign body', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37608-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37774-7', 'MG Breast - right Views', 'MG kanan', 'Views', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37774-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37914-9', 'US Guidance for biopsy of Breast', 'US guided for biopsy mammae', 'Guidance for percutaneous biopsy', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37914-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38013-9', 'US Lower extremity - bilateral', 'US ankle bilateral', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38013-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38014-7', 'US Upper extremity artery - bilateral', 'US arteri ekstremitas superior bilateral', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38014-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38018-8', 'US Guidance for fine needle aspiration of Unspecified body region', 'US guided FNA (Fine Needle Aspiration )', 'Guidance for percutaneous aspiration.fine needle', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38018-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38023-8', 'US Guidance for percutaneous biopsy.core needle of Breast - left', 'US guided + core biopsy', 'Guidance for percutaneous biopsy.core needle', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38023-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38024-6', 'US Guidance for core needle biopsy of Unspecified body region', 'US guided + core biopsy', 'Guidance for biopsy.core needle', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38024-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38025-3', 'US Guidance for percutaneous biopsy.core needle of Breast - right', 'US guided + core biopsy', 'Guidance for percutaneous biopsy.core needle', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38025-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38036-0', 'US Kidney', 'US ginjal', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38036-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38037-8', 'US Femur - left', 'US muskuloskeletal trochanter sinistra', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38037-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38042-8', 'US.doppler Lower extremity artery limited', 'US doppler arteri ekstremitas bawah terbatas', 'Multisection limited', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38042-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38046-9', 'US Pelvis limited', 'US pelvis (nonobstetrik) terbatas', 'Multisection limited', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38046-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38047-7', 'US Retroperitoneum limited', 'US retroperitoneal B-scan terbatas', 'Multisection limited', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38047-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38048-5', 'US Femur - right', 'US muskuloskeletal trochanter kanan', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38048-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38130-1', 'US Lower extremity artery - bilateral', 'US arteri ekstremitas inferior bilateral', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38130-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38133-5', 'US Guidance for aspiration of cyst of Pancreas', 'US guided aspirasi kista pankreas', 'Guidance for percutaneous aspiration of cyst', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38133-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38138-4', 'US Parotid gland', 'US parotid', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38138-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38140-0', 'US Penis', 'US penis', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38140-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38143-4', 'US.doppler Upper extremity artery limited', 'US doppler arteri ekstremitas atas terbatas', 'Multisection limited', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38143-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39031-0', 'US.doppler Extremity artery - bilateral', 'US doppler arteri ekstermitas bilateral', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39031-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39032-8', 'US for transplanted kidney', 'US renal hasil transplantasi', 'Multisection for transplanted kidney', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39032-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39040-1', 'US AV fistula', 'US guidance untuk perbaikan kompresi pseudoaneurisma arteri atau fistula arteriovena', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39040-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39042-7', 'US.doppler Extremity artery', 'US doppler arteri ekstremitas', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39042-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39044-3', 'US.doppler Head vessels limited', 'US doppler arteri intra kranial pemeriksaan terbatas', 'Multisection limited', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39044-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39054-2', 'MG Breast duct Views W contrast intra duct', 'MG duktogram dengan kontras ID satu duktus payudara', 'Views^W contrast intra duct', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39054-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39139-1', 'US Guidance for vascular access of Unspecified body region', 'US guidance untuk akses vaskular', 'Guidance for vascular access', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39139-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39148-2', 'MG Breast duct Views W contrast intra multiple ducts', 'MG duktogram dengan kontras ID lebih dari satu duktus payudara', 'Views^W contrast intra multiple ducts', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39148-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39418-9', 'US.doppler Extremity vein - bilateral', 'US doppler vena ekstremitas bilateral', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39418-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39421-3', 'US.doppler Lower extremity artery - bilateral', 'US doppler arteri ekstremitas bawah bilateral lengkap', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39421-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39422-1', 'US.doppler Lower extremity vessels - bilateral', 'US doppler arteri dan vena ekstremitas inferior bilateral', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39422-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39423-9', 'US.doppler Upper extremity artery - bilateral', 'US doppler arteri ekstremitas atas bilateral', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39423-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39426-2', 'US.doppler Renal vessels', 'US doppler pembuluh darah abdomen bawah (ginjal)', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39426-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39427-0', 'US.doppler Carotid arteries - left', 'US doppler carotis kiri', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39427-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39429-6', 'US.doppler Extremity vein - left', 'US doppler vena ekstremitas kiri', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39429-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39431-2', 'US.doppler Lower extremity vessels - left', 'US doppler pembuluh darah ekstremitas bawah kiri', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39431-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39432-0', 'US.doppler Lower extremity vein - left', 'US DVT (Deep Vein Thrombosis) kiri', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39432-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39433-8', 'US.doppler Upper extremity vessels - left', 'US doppler pembuluh darah ekstremitas superior kiri', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39433-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39437-9', 'US.doppler Carotid arteries -right', 'US doppler carotis kanan', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39437-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39440-3', 'US.doppler Extremity vein - right', 'US doppler vena ekstremitas kanan', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39440-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39442-9', 'US.doppler Lower extremity vessels - right', 'US doppler pembuluh darah ekstremitas bawah kanan', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39442-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39443-7', 'US.doppler Lower extremity vein - right', 'US DVT (Deep Vein Thrombosis) kanan', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39443-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39444-5', 'US.doppler Upper extremity vessels - right', 'US doppler pembuluh darah ekstremitas superior kanan', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39444-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39446-0', 'US.doppler Testicular vessels', 'US doppler pembuluh darah testis', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39446-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39448-6', 'US.doppler Upper extremity vessels', 'US doppler akses dialisis', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39448-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39449-4', 'US.doppler Extremity vein', 'US doppler vena ekstremitas', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39449-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39452-8', 'US Guidance for fluid aspiration of Ovary', 'US guidance untuk aspirasi sel telur', 'Guidance for percutaneous aspiration of fluid', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39452-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39499-9', 'US.doppler Lower extremity artery - left', 'US doppler arteri ekstremitas bawah kiri', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39499-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39500-4', 'US.doppler Upper extremity artery - left', 'US doppler arteri ekstremitas atas kiri', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39500-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39501-2', 'US.doppler Upper extremity vein - left', 'US doppler vena ekstremitas atas kiri', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39501-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39502-0', 'US.doppler Ovarian vessels', 'US doppler ginekologi', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39502-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39505-3', 'US.doppler Lower extremity artery - right', 'US doppler arteri ekstremitas bawah kanan', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39505-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39506-1', 'US.doppler Upper extremity artery - right', 'US doppler arteri ekstremitas atas kanan', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39506-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39507-9', 'US.doppler Upper extremity vein - right', 'US doppler vena ekstremitas atas kanan', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39507-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39526-9', 'US Extremity limited', 'US ekstremitas terbatas', 'Multisection limited', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39526-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '41814-5', 'US Upper extremity artery - right', 'US arteri ekstremitas superior kanan', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '41814-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '41815-2', 'US Lower extremity artery - right', 'US arteri ekstremitas inferior kanan', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '41815-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '41833-5', 'US Upper extremity artery - left', 'US arteri ekstremitas superior kiri', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '41833-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '41834-3', 'US Lower extremity artery - left', 'US arteri ekstremitas inferior kiri', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '41834-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42135-4', 'US Guidance for superficial biopsy of Bone', 'US guided biopsi tulang', 'Guidance for superficial biopsy', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42135-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42140-4', 'US Guidance for placement of tube in Chest', 'US guidance untuk penempatan tube pada dada', 'Guidance for placement of tube', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42140-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42146-1', 'US.doppler Carotid arteries', 'US doppler arteri carotis', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42146-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42148-7', 'US Heart', 'US echocardiografi dengan pemakaian zat kontras', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42148-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42152-9', 'US.doppler Pelvis vessels limited', 'US doppler pembuluh darah pelvis pemeriksaan terbatas', 'Multisection limited', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42152-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42455-6', 'US Pelvis transabdominal and transvaginal', 'US obstetri ginekologi 2 dimensi', 'Multisection transabdominal + transvaginal', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42455-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42461-4', 'US.doppler Lower extremity vessel - left for graft', 'US doppler arteri ekstremitas bawah untuk grafting arteri kiri', 'Multisection for graft', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42461-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42462-2', 'US.doppler Lower extremity vessel - right for graft', 'US doppler arteri ekstremitas bawah untuk grafting arteri kanan', 'Multisection for graft', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42462-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42463-0', 'US Guidance for biopsy of Endomyocardium', 'US guidance untuk biopsi endomiokardium', 'Guidance for percutaneous biopsy', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42463-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42475-4', 'US.doppler Upper extremity vessel - left for graft', 'US doppler arteri ekstremitas atas untuk grafting arteri kiri', 'Multisection for graft', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42475-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42476-2', 'US.doppler Upper extremity vessel - right for graft', 'US doppler arteri ekstremitas atas untuk grafting arteri kanan', 'Multisection for graft', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42476-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43487-8', 'US Guidance for placement of radiation therapy fields in Unspecified body region', 'US guidance untuk pemasangan lapang radiasi terapi', 'Guidance for placement of radiation therapy fields', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43487-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43565-1', 'US Guidance for deep biopsy of Bone', 'US guided biopsi tulang', 'Guidance for deep biopsy', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43565-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43572-7', 'US.doppler Abdominal vessels limited', 'US doppler pembuluh darah abdomen pemeriksaan terbatas', 'Multisection limited', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43572-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43756-6', 'US Guidance for fluid aspiration of Breast', 'US guided aspirasi cairan mammae', 'Guidance for percutaneous aspiration of fluid', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43756-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43759-0', 'US Guidance for localization of Breast - bilateral', 'US guided localization mammae', 'Guidance for localization', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43759-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43765-7', 'US.doppler Carotid arteries - bilateral', 'US doppler carotis bilateral', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43765-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44157-6', 'US Guidance for fine needle aspiration of Pancreas', 'US guided FNA pankreas', 'Guidance for percutaneous aspiration.fine needle', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44157-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44160-0', 'US Guidance for fine needle aspiration of Breast', 'US guided FNA mammae', 'Guidance for percutaneous aspiration.fine needle', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44160-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44163-4', 'US Brachial plexus', 'US plexus brachialis', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44163-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44169-1', 'US Guidance for drainage of abscess and placement of drainage catheter of Peritoneal space', 'US guided pungsi abses intraperitoneal', 'Guidance for drainage of abscess+placement of drainage catheter', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44169-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44172-5', 'US Guidance for drainage and placement of drainage catheter of Pancreas', 'US guided drainase & penempatan kateter di pankreas', 'Guidance for drainage+placement of drainage catheter', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44172-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44174-1', 'US.doppler Lower extremity vessels', 'US doppler arteri dan vena ekstremitas inferior', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44174-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44237-6', 'US.doppler Upper extremity vessel - bilateral for graft limited', 'US doppler arteri ekstremitas atas atau grafting arteri bilateral', 'Multisection limited for graft', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44237-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '46285-3', 'US Guidance for core needle biopsy of Thyroid gland', 'US guided + core biopsy', 'Guidance for biopsy.core needle', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '46285-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '46338-0', 'MG Breast - unilateral Single view', 'MG unilateral', 'View', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '46338-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '46364-6', 'US Lower extremity vein - bilateral', 'US vena ekstremitas inferior bilateral', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '46364-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '46379-4', 'US.doppler Upper extremity vessels - bilateral', 'US doppler arteri dan vena ekstremitas superior bilateral', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '46379-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48688-6', 'US Upper extremity vein - right', 'US vena ekstremitas superior kanan', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48688-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48689-4', 'US Upper extremity vein - left', 'US vena ekstremitas superior kiri', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48689-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48690-2', 'US Upper extremity vein - bilateral', 'US vena ekstremitas superior bilateral', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48690-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48691-0', 'US Lower extremity vein - right', 'US vena ekstremitas inferior kanan', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48691-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48692-8', 'US Lower extremity vein - left', 'US vena ekstremitas inferior kiri', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48692-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48735-5', 'MG Guidance for localization of Breast', 'MG guidance wire', 'Guidance for localization', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48735-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48742-1', 'US.doppler Scrotum and testicle', 'US doppler skrotum', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48742-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48770-2', 'Fetal Biophysical profile panel US', 'US profil biofisik janin', 'Biophysical profile panel', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48770-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '58743-6', 'US Guidance for ablation of tissue of Unspecified body region', 'US guidance ablasi leiomyomata uteri', 'Guidance for ablation of tissue', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '58743-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '59281-6', 'US Heart Transthoracic', 'US echocardiografi transthorakal', 'Multisection transthoracic', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '59281-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '64993-9', 'US Guidance for placement of needle in Unspecified body region', 'US guidance untuk pemasangan jarum (biopsi, aspirasi, injeksi, lokalisasi alat)', 'Guidance for placement of needle', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '64993-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69279-8', 'US Guidance for core needle biopsy of Lymph node', 'US guided + core biopsy', 'Guidance for biopsy.core needle', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69279-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69283-0', 'US.doppler Extremity veins - bilateral', 'US doppler vena ekstremitas bilateral lengkap', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69283-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69284-8', 'US.doppler Portal vein and Hepatic vein', 'US doppler vaskular hepar', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69284-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69287-1', 'US Guidance for fluid aspiration of Lymph node', 'US guided apirasi limpa', 'Guidance for percutaneous aspiration of fluid', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69287-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69391-1', 'US Guidance for cordocentesis', 'US guidance untuk transfusi janin intrauterin atau kordosentesis', 'Guidance for percutaneous aspiration of fluid', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69391-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69400-0', 'US Guidance for chorionic villus sampling', 'US guidance untuk sampling vilus korion', 'Guidance for tissue sampling of chorionic villus', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69400-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69402-6', 'US Kidney - bilateral and Urinary bladder', 'US ginjal dan saluran kemih', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69402-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '72528-3', 'US Axilla - right', 'US axilla Kanan', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '72528-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '72529-1', 'US Axilla - left', 'US axilla Kiri', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '72529-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '72536-6', 'US Guidance for vascular sclerotherapy of Extremity veins - bilateral', 'US guidance skleroterapi vena ekstremitas bilateral', 'Guidance for vascular sclerotherapy', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '72536-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '72537-4', 'US Guidance for vascular sclerotherapy of Extremity vein - bilateral', 'US guided + sclerotheraphy', 'Guidance for vascular sclerotherapy', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '72537-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '72642-2', 'US Guidance for vascular sclerotherapy of Extremity veins - right', 'US guidance skleroterapi vena ekstremitas kanan', 'Guidance for vascular sclerotherapy', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '72642-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '72643-0', 'US Guidance for vascular sclerotherapy of Extremity veins - left', 'US guidance skleroterapi vena ekstremitas kiri', 'Guidance for vascular sclerotherapy', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '72643-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '72644-8', 'US Guidance for vascular sclerotherapy of Extremity vein - right', 'US guided + sclerotheraphy', 'Guidance for vascular sclerotherapy', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '72644-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '72645-5', 'US Guidance for vascular sclerotherapy of Extremity vein - left', 'US guided + sclerotheraphy', 'Guidance for vascular sclerotherapy', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '72645-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '77615-3', 'Liver stiffness by US.transient elastography', 'US hepar + elastografi', 'Liver stiffness', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '77615-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '79374-5', 'US Abdominal Aorta for screening', 'US skrining aorta abdominal dengan B-scan', 'Multisection for screening', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '79374-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80834-5', 'US for multiple gestation pregnancy in first trimester', 'US kehamilan kembar trimester 1', 'Multisection for multiple gestation pregnancy+first trimester', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80834-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80835-2', 'US transabdominal and transvaginal for multiple gestation pregnancy in first trimester', 'US kehamilan kembar trimester 1 (transabdominal dan transvaginal)', 'Multisection transabdominal + transvaginal for multiple gestation pregnancy+first trimester', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80835-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80836-0', 'US for multiple gestation pregnancy in second or third trimester', 'US kehamilan kembar trimester 2 atau 3', 'Multisection for multiple gestation pregnancy+second or third trimester', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80836-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80850-1', 'US.doppler Carotid arteries limited', 'US doppler arteri carotis pemeriksaan terbatas', 'Multisection limited', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80850-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80852-7', 'US Axilla', 'US axilla', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80852-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80853-5', 'US Guidance for injection of Elbow', 'US guidance injeksi elbow', 'Guidance for injection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80853-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80855-0', 'US Extremity musculoskeletal tissue', 'US muskuloskeletal', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80855-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80858-4', 'US Head and neck soft tissue', 'US jaringan lunak kepala dan leher', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80858-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80859-2', 'US.doppler Heart', 'US doppler echocardiografi', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80859-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80864-2', 'US.doppler for pregnancy', 'US doppler obstetri', 'Multisection for pregnancy', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80864-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80866-7', 'US for pregnancy in second or third trimester', 'US kehamilan tunggal trimester 2 atau 3', 'Multisection for pregnancy+second or third trimester', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80866-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80867-5', 'US transabdominal and transvaginal for pregnancy in first trimester', 'US kehamilan tunggal trimester 1 (transabdominal dan transvaginal)', 'Multisection transabdominal + transvaginal for pregnancy+first trimester', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80867-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80869-1', 'US for pregnancy in first trimester', 'US kehamilan tunggal trimester 1', 'Multisection for pregnancy+first trimester', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80869-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80872-5', 'US.doppler Uterus and Fallopian tubes W saline IU', 'US doppler ginekologi', 'Multisection^W saline IU', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80872-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80873-3', 'US.doppler Penis limited', 'US doppler pembuluh darah penis pemeriksaan terbatas', 'Multisection limited', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80873-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80874-1', 'US.doppler Penis vessels', 'US doppler pembuluh darah penis pemeriksaan lengkap', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80874-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80876-6', 'US Prostate transrectal for volume measurement', 'US transrektal prostat untuk pemeriksaan volume', 'Multisection transrectal for volume measurement', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80876-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '80882-4', 'US Guidance of Unspecified body region-- during surgery', 'US guidance intraoperasi', 'Guidance^during surgery', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '80882-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '81158-8', 'US Pediatric Head', 'US kepala anak', 'Multisection for pediatrics', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '81158-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '81160-4', 'US.doppler Pediatric limited Head', 'US doppler kepala anak', 'Multisection limited for pediatrics', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '81160-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '81164-6', 'US Pediatric limited Hip - bilateral', 'US coxae bayi bilateral', 'Multisection limited for pediatrics', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '81164-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '82124-9', 'US Guidance for radiation treatment of Unspecified body region', 'US guidance untuk pemasangan radioelemen interstisial', 'Guidance for radiation treatment', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '82124-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '85475-2', 'US Heart Transesophageal', 'US echocardiografi transesofagus', 'Multisection transesophageal', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '85475-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '92678-2', 'US.elastography Lung', 'US paru + elastografi', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '92678-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '94680-6', 'US.doppler Thoracic and Abdominal Aorta and Inferior Vena Cava and Illiac vessels limited', 'US doppler aorta thoracal dan abdomen, vena cava inferior serta arteri iliaka pemeriksaan terbatas', 'Multisection limited', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '94680-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '94681-4', 'US.doppler Thoracic and Abdominal Aorta and Inferior Vena Cava and Illiac vessels', 'US doppler aorta abdominal', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '94681-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '97396-6', 'US Guidance for injection of Elbow - bilateral', 'US guidance injeksi elbow bilateral', 'Guidance for injection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '97396-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '98035-9', 'US Eye', 'US mata', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '98035-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '99826-0', 'US.A-scan Eye', 'US mata A-scan', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '99826-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '99827-8', 'US.elastography Breast', 'US mammae + elastografi', 'Multisection', 'RAD/US', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '99827-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '103327-3', 'XR Femur - bilateral GE 2 Views', 'XR femur bilateral 3 proyeksi', 'Views GE 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '103327-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '103336-4', 'XR Humerus - bilateral GE 2 Views', 'XR humerus bilateral 3 proyeksi', 'Views GE 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '103336-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '103350-5', 'XR Tibia and Fibula - left 3 Views', 'XR cruris 3 proyeksi', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '103350-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '103354-7', 'XR Tibia and Fibula - right 3 Views', 'XR cruris 3 proyeksi', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '103354-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '103374-5', 'XR Radius and Ulna - left 3 Views', 'XR antebrachi kiri 3 proyeksi', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '103374-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '103378-6', 'XR Radius and Ulna - right 3 Views', 'XR antebrachi kanan 3 proyeksi', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '103378-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '103424-8', 'XR Ankle - bilateral Single view', 'XR pergelangan kaki bilateral 1 proyeksi', 'View', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '103424-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '103431-3', 'XR Knee - bilateral 5 Views', 'XR genu bilateral 5 proyeksi', 'Views 5', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '103431-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '103450-3', 'XR Shoulder - bilateral 5 Views', 'XR bahu bilateral 5 proyeksi', 'Views 5', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '103450-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '103513-8', 'XR Radius and Ulna - bilateral 3 Views', 'XR antebrachi bilateral 3 proyeksi', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '103513-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24540-7', 'XR Ankle 2 Views', 'XR ankle dua proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24540-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24561-3', 'XR Abdomen AP left lateral-decubitus', 'XR abdomen LLD (Left Lateral Decubitus)', 'View AP L-lateral-decubitus', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24561-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24563-9', 'XR Abdomen AP right lateral-decubitus', 'XR abdomen RLD (Right Lateral Decubitus)', 'View AP R-lateral-decubitus', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24563-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24573-8', 'XR Biliary ducts and Gallbladder Views W contrast IV', 'XR cholangiography', 'Views^W contrast IV', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24573-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24579-5', 'XR Long bones Survey Views', 'XR panjang tulang (orthoroentgenogram, scanogram)', 'Views survey', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24579-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24637-1', 'XR Chest AP left lateral-decubitus', 'XR thorax LLD', 'View AP L-lateral-decubitus', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24637-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24640-5', 'XR Chest Apical lordotic', 'XR top lordotik', 'View apical lordotic', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24640-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24648-8', 'XR Chest PA upright', 'XR thorax PA', 'View PA upright', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24648-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24664-5', 'XR Clavicle Views', 'XR clavicula', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24664-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24686-8', 'XR Lower extremity Views', 'XR full ekstremitas inferior', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24686-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24695-9', 'XR Facial bones Views', 'XR facial bones', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24695-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24700-7', 'XR Femur and Tibia Views for leg length', 'XR panjang tulang femur dan tibia', 'Views for leg length', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24700-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24712-2', 'XR Gallbladder Views W contrast PO', 'XR kolesistografi dengan kontras PO', 'Views^W contrast PO', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24712-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24721-3', 'XR Hand 2 Views', 'XR tangan dua proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24721-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24745-2', 'XR Petrous part of temporal bone Views', 'XR schuler view', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24745-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24761-9', 'XR Hip Single view', 'XR coxae satu proyeksi unilateral', 'View', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24761-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24799-9', 'XR Abdomen AP', 'XR abdomen AP', 'View AP', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24799-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24806-2', 'XR Knee 2 Views', 'XR genu dua proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24806-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24828-6', 'XR tomography Mandible Panoramic', 'XR panoramic gigi', 'View panoramic', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24828-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24829-4', 'XR Mandible Views', 'XR mandibula', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24829-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24834-4', 'XR Nasal bones Views', 'XR nasal tangensial', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24834-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24843-5', 'XR Neck Lateral', 'XR soft tissue leher lateral', 'View lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24843-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24846-8', 'XR Optic foramen Views', 'XR foramen optik', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24846-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24854-2', 'XR Orbit - bilateral Views', 'XR orbita bilateral', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24854-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24871-6', 'XR Pelvis Pelvimetry', 'XR pelvimetri', 'View pelvimetry', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24871-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24899-7', 'XR Ribs Views', 'XR costae axial', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24899-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24903-7', 'XR Scapula Views', 'XR scapula', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24903-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24916-9', 'XR Sinuses Views', 'XR sinus paranasal', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24916-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24917-7', 'XR Skull Single view', 'XR cranium 1 proyeksi', 'View', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24917-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24918-5', 'XR Skull 3 Views', 'XR cranium AP, lateral dan towne', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24918-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24919-3', 'XR Skull AP and Lateral', 'XR skull AP dan lateral', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24919-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24920-1', 'XR Skull Lateral', 'XR skull lateral', 'View lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24920-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24921-9', 'XR Skull Waters', 'XR waters', 'View Waters', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24921-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24940-9', 'XR Cervical spine Single view', 'XR vertebra cervical satu proyeksi', 'View', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24940-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24942-5', 'XR Cervical spine AP and Lateral', 'XR cervical AP dan lateral', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24942-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24943-3', 'XR Cervical spine Lateral', 'XR cervical lateral', 'View lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24943-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24945-8', 'XR Cervical spine Views W flexion and W extension', 'XR cervical fleksi dan ekstensi', 'Views^W flexion + W extension', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24945-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24948-2', 'XR Spine Cervical Odontoid and Cervical axis AP', 'XR cervical I dan odontoid', 'View AP', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24948-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24969-8', 'XR Lumbar spine Lateral', 'XR vertebra lumbosakral lateral', 'View lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24969-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24970-6', 'XR Lumbar spine AP and Lateral', 'XR vertebra lumbal AP dan lateral', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24970-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24971-4', 'XR Lumbar spine Views W flexion and W extension', 'XR vertebra lumbal dinamik fleksi dan ekstensi', 'Views^W flexion + W extension', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24971-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '24984-7', 'XR Thoracic and lumbar spine 2 Views', 'XR vertebra thoracolumbar dua proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '24984-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '25011-8', 'XR Tibia and Fibula Views', 'XR cruris', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '25011-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26106-5', 'XR Clavicle - bilateral Views', 'XR clavicula bilateral', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26106-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26107-3', 'XR Clavicle - left Views', 'XR clavicula kiri', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26107-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26108-1', 'XR Clavicle - right Views', 'XR clavicula kanan', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26108-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26109-9', 'XR Elbow - bilateral Views', 'XR cubiti dua proyeksi kanan atau kiri', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26109-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26118-0', 'XR Femur - bilateral Views', 'XR caput femur bilateral', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26118-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26120-6', 'XR Femur - left Views', 'XR caput femur kiri', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26120-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26122-2', 'XR Femur - right Views', 'XR caput femur kanan', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26122-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26131-3', 'XR Hip - left Views', 'XR artc. coxae kiri', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26131-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26132-1', 'XR Hip - right Views', 'XR coxae kanan', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26132-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26139-6', 'XR Mastoid - bilateral Views', 'XR mastoid schuller bilateral', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26139-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26140-4', 'XR Mastoid - left Views', 'XR mastoid schuller kiri', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26140-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26141-2', 'XR Mastoid - right Views', 'XR mastoid schuller kanan', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26141-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26142-0', 'XR Optic foramen - bilateral Views', 'XR foramen opticum (proyeksi rheese) bilateral', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26142-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26143-8', 'XR Optic foramen - left Views', 'XR foramen optik kiri (Rheese)', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26143-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26144-6', 'XR Optic foramen - right Views', 'XR foramen optik kanan (Rheese)', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26144-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26146-1', 'XR Radius and Ulna - bilateral Views', 'XR antebrachi dua proyeksi kanan atau kiri', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26146-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26154-5', 'XR Scapula - bilateral Views', 'XR skapula bilateral', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26154-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26155-2', 'XR Scapula - left Views', 'XR scapula kiri', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26155-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26156-0', 'XR Scapula - right Views', 'XR scapula kanan', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26156-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26169-3', 'XR Wrist - bilateral Views', 'XR wrist bilateral', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26169-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26352-5', 'XR Wrist - bilateral and Hand - bilateral Bone age Views', 'XR manus bone age AP dan bilateral', 'Views bone age', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26352-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26358-2', 'XR Knee - bilateral AP W standing', 'XR genu bilateral, AP, dan erect', 'View AP^W standing', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26358-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26365-7', 'XR Knee - left AP and Lateral W standing', 'XR genu AP dan lateral berdiri kiri', 'Views AP + lateral^W standing', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26365-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26366-5', 'XR Knee - right AP and Lateral W standing', 'XR genu AP dan lateral berdiri kanan', 'Views AP + lateral^W standing', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26366-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26379-8', 'XR Hand - bilateral 3 Views', 'XR manus bilateral 3 proyeksi', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26379-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26382-2', 'XR Shoulder - bilateral 3 Views', 'XR shoulder tiga proyeksi bilateral', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26382-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26383-0', 'XR Shoulder - left 3 Views', 'XR bahu tiga proyeksi kiri', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26383-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26384-8', 'XR Shoulder - right 3 Views', 'XR bahu tiga proyeksi kanan', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26384-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26385-5', 'XR Ankle - bilateral 2 Views', 'XR ankle bilateral dua proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26385-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26386-3', 'XR Ankle - left 2 Views', 'XR ankle dua proyeksi kiri', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26386-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26387-1', 'XR Ankle - right 2 Views', 'XR ankle dua proyeksi kanan', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26387-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26388-9', 'XR Hand - bilateral 2 Views', 'XR manus dua proyeksi bilateral', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26388-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26391-3', 'XR Humerus - bilateral 2 Views', 'XR humerus bilateral 2 proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26391-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26394-7', 'XR Knee - bilateral 2 Views', 'XR genu dua proyeksi bilateral', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26394-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26395-4', 'XR Knee - left 2 Views', 'XR genu dua proyeksi kiri', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26395-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26396-2', 'XR Knee - right 2 Views', 'XR genu dua proyeksi kanan', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26396-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '26400-2', 'XR Hip - bilateral Single view', 'XR coxae bilateral 1 proyeksi', 'View', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '26400-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '28564-3', 'XR Skull Views', 'XR kepala', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '28564-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30716-5', 'XR Thoracic and lumbar spine Lateral Views for scoliosis', 'XR vertebra thoracolumbar lateral', 'Views lateral for scoliosis', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30716-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30717-3', 'XR Thoracic and lumbar spine Views for scoliosis W standing', 'XR vertebra thoracolumbar tegak (pemeriksaan skoliosis)', 'Views for scoliosis^W standing', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30717-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30720-7', 'XR Orbit - bilateral Views for foreign body', 'XR comberg bilateral', 'Views for foreign body', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30720-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30725-6', 'XR Cervical spine AP', 'XR cervical AP', 'View AP', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30725-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30737-1', 'XR Chest Left lateral', 'XR thorax lateral kiri', 'View L-lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30737-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30744-7', 'XR Chest PA and Lateral and Oblique', 'XR thorax PA, lateral dan oblique', 'Views PA + lateral + oblique', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30744-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30748-8', 'XR Shoulder Single view', 'XR bahu satu proyeksi', 'View', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30748-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30752-0', 'XR Thoracic spine AP', 'XR vertebra thorakal AP', 'View AP', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30752-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30753-8', 'XR Thoracic spine AP and Lateral', 'XR vertebra thorakal AP dan lateral', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30753-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30756-1', 'XR Thoracic spine Lateral', 'XR vertebra thorakal lateral', 'View lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30756-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30758-7', 'XR Thoracic spine Oblique', 'XR vertebra thorakal oblique kanan', 'View oblique', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30758-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30766-0', 'XR Pelvis 3 Views', 'XR pelvis tiga proyeksi', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30766-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30773-6', 'XR Lumbar spine Single view', 'XR vertebra lumbal satu proyeksi', 'View', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30773-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30778-5', 'XR Lumbar spine Oblique', 'XR vertebra lumbosakral oblique', 'View oblique', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30778-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30784-3', 'XR Foot 2 Views', 'XR pedis dua proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30784-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30788-4', 'XR Knee 3 Views', 'XR genu tiga proyeksi', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30788-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '30813-0', 'XR Lung - bilateral Views W contrast intrabronchial', 'XR bronkografi bilateral', 'Views^W contrast intrabronchial', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '30813-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36293-9', 'XR Abdomen 3 Views', 'XR abdomen tiga proyeksi', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36293-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36294-7', 'XR Ankle 3 Views', 'XR ankle tiga proyeksi', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36294-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36295-4', 'XR Ankle - bilateral 3 Views', 'XR ankle tiga proyeksi bilateral', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36295-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36296-2', 'XR Ankle - left 3 Views', 'XR ankle tiga proyeksi kiri', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36296-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36297-0', 'XR Facial bones 3 Views', 'XR facial bones caldwell, towne dan lateral', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36297-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36300-2', 'XR Elbow - bilateral 3 Views', 'XR cubiti Bilateral 3 proyeksi', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36300-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36302-8', 'XR Femur 3 Views', 'XR femur 3 proyeksi', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36302-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36306-9', 'XR Foot - bilateral 3 Views', 'XR pedis bilateral 3 proyeksi', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36306-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36308-5', 'XR Hip - bilateral 3 Views', 'XR coxae bilateral 1 proyeksi', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36308-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36310-1', 'XR Knee - bilateral 3 Views', 'XR genu tiga proyeksi bilateral', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36310-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36311-9', 'XR Knee - left 3 Views', 'XR genu tiga proyeksi kiri', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36311-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36313-5', 'XR Ribs - bilateral 3 Views', 'XR iga bilateral tiga proyeksi', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36313-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36322-6', 'XR Elbow - bilateral 4 Views', 'XR cubiti Bilateral 4 proyeksi', 'Views 4', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36322-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36324-2', 'XR Femur - left 4 Views', 'XR femur kiri 4 proyeksi', 'Views 4', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36324-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36325-9', 'XR Knee - bilateral 4 Views', 'XR genu bilateral 4 proyeksi', 'Views 4', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36325-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36329-1', 'XR Shoulder - bilateral 4 Views', 'XR bahu bilateral 4 proyeksi', 'Views 4', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36329-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36331-7', 'XR Cervical spine 4 Views', 'XR cervical 4 proyeksi', 'Views 4', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36331-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36332-5', 'XR Lumbar spine 4 Views', 'XR vertebra lumbal empat proyeksi', 'Views 4', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36332-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36550-2', 'XR Abdomen Single view', 'XR abdomen 1 proyeksi', 'View', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36550-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36551-0', 'XR Ankle Single view', 'XR pergelangan kaki 1 proyeksi', 'View', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36551-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36554-4', 'XR Chest Single view', 'XR thorax satu proyeksi', 'View', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36554-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36572-6', 'XR Chest AP', 'XR thorax AP', 'View AP', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36572-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36582-5', 'XR Hip - left AP', 'XR artc. coxae axial kiri', 'View AP', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36582-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36587-4', 'XR Shoulder - left AP', 'XR bahu AP kiri', 'View AP', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36587-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36590-8', 'XR Knee - bilateral AP and Lateral', 'XR genu AP dan lateral bilateral', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36590-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36603-9', 'XR Hip - left Lateral', 'XR artc. coxae lateral kiri', 'View lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36603-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36628-6', 'XR Internal auditory canal Views', 'XR meatus auditorius internus', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36628-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36636-9', 'XR Knee - left Views', 'XR genu proyeksi kiri', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36636-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36641-9', 'XR Abdomen 2 Views', 'XR abdomen dua proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36641-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36643-5', 'XR Chest 2 Views', 'XR thorax dua proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36643-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36649-2', 'XR Elbow - bilateral 2 Views', 'XR cubiti dua proyeksi bilateral', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36649-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36651-8', 'XR Lower extremity 2 Views', 'XR long leg stitch view', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36651-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36652-6', 'XR Femur 2 Views', 'XR femur dua proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36652-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36653-4', 'XR Femur - bilateral 2 Views', 'XR femur dua proyeksi bilateral', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36653-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36654-2', 'XR Femur - left 2 Views', 'XR femur dua proyeksi kiri', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36654-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36657-5', 'XR Foot - bilateral 2 Views', 'XR pedis dua proyeksi bilateral', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36657-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36658-3', 'XR Radius and Ulna 2 Views', 'XR antebrachi dua proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36658-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36659-1', 'XR Radius and Ulna - bilateral 2 Views', 'XR antebrachi dua proyeksi bilateral', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36659-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36664-1', 'XR Hip - left 2 Views', 'XR hip joint AP dan oblique kiri', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36664-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36666-6', 'XR Scapula - left 2 Views', 'XR skapula dua proyeksi kiri', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36666-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36667-4', 'XR Shoulder - bilateral 2 Views', 'XR bahu dua proyeksi bilateral', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36667-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36668-2', 'XR Shoulder - left 2 Views', 'XR bahu dua proyeksi kiri', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36668-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36669-0', 'XR Cervical spine 2 Views', 'XR cervical dua proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36669-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36670-8', 'XR Lumbar spine 2 Views', 'XR vertebra lumbal dua proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36670-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36671-6', 'XR Tibia and Fibula - bilateral 2 Views', 'XR cruris dua proyeksi bilateral', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36671-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36672-4', 'XR Tibia and Fibula - left 2 Views', 'XR cruris dua proyeksi kiri', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36672-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36678-1', 'XR Knee - bilateral 6 Views', 'XR genu bilateral 6 proyeksi', 'Views 6', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36678-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36685-6', 'XR Ankle - left AP and Lateral', 'XR ankle AP dan lateral kiri', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36685-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36687-2', 'XR Chest AP and Lateral', 'XR thorax dua proyeksi AP dan lateral', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36687-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36691-4', 'XR Elbow - left AP and Lateral', 'XR elbow AP dan lateral kiri', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36691-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36695-5', 'XR Femur - left AP and Lateral', 'XR femur AP dan lateral kiri', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36695-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36697-1', 'XR Foot - left AP and Lateral', 'XR pedis AP dan lateral kiri', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36697-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36700-3', 'XR Radius and Ulna - left AP and Lateral', 'XR antebrachi AP dan lateral kiri', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36700-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36704-5', 'XR Hip - left AP and Lateral', 'XR hip joint AP dan lateral kiri', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36704-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36709-4', 'XR Knee AP and Lateral', 'XR genu AP dan lateral', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36709-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36710-2', 'XR Knee - left AP and Lateral', 'XR genu AP dan lateral kiri', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36710-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36711-0', 'XR Mandible AP and Lateral', 'XR mandibula AP dan lateral', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36711-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36715-1', 'XR Scapula - left AP and Lateral', 'XR scapula AP dan lateral kiri', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36715-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36718-5', 'XR Tibia and Fibula - left AP and Lateral', 'XR cruris AP dan lateral kiri', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36718-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36734-2', 'XR Cervical spine AP and Lateral and oblique', 'XR cervical AP, lateral dan oblique', 'Views AP + lateral + oblique', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36734-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36735-9', 'XR Lumbar spine AP and Lateral and oblique', 'XR vertebra lumbal AP, lateral dan oblique', 'Views AP + lateral + oblique', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36735-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36747-4', 'XR Mandible Oblique Views', 'XR mandibula eisler', 'Views oblique', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36747-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36748-2', 'XR Cervical spine Oblique Views', 'XR cervical oblique', 'Views oblique', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36748-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36751-6', 'XR and RF Chest PA and Lateral and Views', 'XR dan RF thorax proyeksi PA dan lateral', 'Views PA + lateral && Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36751-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36754-0', 'XR Mandible PA and Lateral', 'XR mandibula PA dan lateral', 'Views PA + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36754-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36759-9', 'XR Chest PA and Apical lordotic', 'XR thorax dua proyeksi PA dan lateral dengan prosedur top lordotik', 'Views PA + apical lordotic', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36759-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36964-5', 'XR Shoulder - left Axillary', 'XR bahu axial kiri', 'View axillary', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36964-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36981-9', 'XR Hip Judet', 'XR iliac judet', 'View Judet', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36981-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '36984-3', 'XR Abdomen Lateral crosstable', 'XR atresia ani', 'View lateral crosstable', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '36984-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37007-2', 'XR Ankle Mortise', 'XR ankle mortise kanan', 'View Mortise', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37007-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37008-0', 'XR Chest Left oblique', 'XR thorax oblique kiri', 'View L-oblique', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37008-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37010-6', 'XR Chest Right oblique', 'XR thorax oblique kanan', 'View R-oblique', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37010-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37019-7', 'XR Knee - left Rosenberg W standing', 'XR genu rosenberg kiri', 'View Rosenberg^W standing', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37019-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37046-0', 'XR Abdomen Upright', 'XR BOF/BNO/KUB erect', 'View upright', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37046-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37062-7', 'XR Humerus - bilateral Views', 'XR humerus dua proyeksi kanan atau kiri', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37062-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37070-0', 'XR Wrist - bilateral 4 Views', 'XR pergelangan tangan bilateral 4 proyeksi', 'Views 4', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37070-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37084-1', 'XR Shoulder - left AP and Axillary and Y', 'XR shoulder joint axillary, AP dan Y-view kiri', 'Views AP + axillary + Y', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37084-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37095-7', 'XR Ankle AP and Lateral and Mortise', 'XR ankle AP, lateral dan mortis', 'Views AP + lateral + Mortise', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37095-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37096-5', 'XR Ankle - bilateral AP and Lateral and Mortise', 'XR ankle AP, lateral dan mortis bilateral', 'Views AP + lateral + Mortise', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37096-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37097-3', 'XR Ankle - left AP and Lateral and Mortise', 'XR ankle AP, lateral dan mortise kiri', 'Views AP + lateral + Mortise', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37097-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37110-4', 'XR Patella - left AP and Lateral and Sunrise', 'XR patella AP, lateral dan skyline kiri', 'Views AP + lateral + Sunrise', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37110-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37111-2', 'XR Knee AP and Lateral and Sunrise and tunnel', 'XR genu AP, lateral, skyline dan proyeksi tunnel', 'Views AP + lateral + Sunrise + tunnel', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37111-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37116-1', 'XR Knee - bilateral AP and Lateral and Sunrise and tunnel', 'XR genu AP, lateral, skyline dan proyeksi tunnel bilateral', 'Views AP + lateral + Sunrise + tunnel', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37116-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37132-8', 'XR Lumbar spine Lateral Views W flexion and W extension', 'XR vertebra lumbosakral lateral flexi dan extensi', 'Views lateral^W flexion + W extension', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37132-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37153-4', 'XR Mastoid Stenver and Arcelin', 'XR stenvers kanan', 'Views Stenver + Arcelin', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37153-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37164-1', 'XR Facial bones Lateral and Caldwell and Waters', 'XR facial bones lateral, caldwell, dan waters', 'Views lateral + Caldwell + Waters', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37164-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37325-8', 'XR Temporomandibular joint - bilateral Views', 'XR TMJ bilateral', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37325-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37338-1', 'XR Skull and Facial bones and Mandible Views', 'XR cephalometri', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37338-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37340-7', 'XR Spine Lumbar and Sacrum Views', 'XR vertebra lumbosakral', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37340-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37454-6', 'XR Wrist - bilateral 3 Views', 'XR pergelangan tangan bilateral 3 proyeksi', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37454-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37481-9', 'XR Cervical and thoracic spine Views', 'XR vertebra cervicothoracal', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37481-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37482-7', 'XR Wrist - bilateral 2 Views', 'XR pergelangan tangan (wrist joint) dua proyeksi bilateral', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37482-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37483-5', 'XR Wrist - left 2 Views', 'XR wrist dua proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37483-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37545-1', 'XR Hip - left Oblique crosstable', 'XR artc. coxae oblique kiri', 'View oblique crosstable', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37545-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37546-9', 'XR Temporomandibular joint - bilateral Open and Closed mouth', 'XR sendi temporomandibula buka dan tutup mulut bilateral', 'Views open mouth + closed mouth', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37546-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37607-9', 'XR Kidney Views W contrast IV', 'XR urografi (pielografi) dengan kontras IV', 'Views^W contrast IV', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37607-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37609-5', 'XR Optic foramen 4 Views', 'XR foramen opticum (proyeksi rheese) 4 proyeksi', 'Views 4', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37609-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37616-0', 'XR Pelvis Single view', 'XR pelvis 1 proyeksi', 'View', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37616-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37617-8', 'XR Pelvis 2 Views', 'XR pelvis dua proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37617-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37622-8', 'XR Pelvis AP', 'XR pelvis AP', 'View AP', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37622-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37623-6', 'XR Pelvis AP and Inlet and Outlet', 'XR pelvis AP, inlet dan outlet', 'Views AP + inlet + outlet', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37623-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37626-9', 'XR Pelvis Lateral frog', 'XR frog position', 'View lateral frog', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37626-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37628-5', 'XR Pelvis Inlet', 'XR pelvis inlet', 'View inlet', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37628-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37629-3', 'XR Pelvis Lateral', 'XR pelvis lateral', 'View lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37629-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37630-1', 'XR Pelvis Oblique Views', 'XR pelvis oblique', 'Views oblique', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37630-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37631-9', 'XR Pelvis Outlet', 'XR pelvis outlet', 'View outlet', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37631-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37639-2', 'XR Neck Views', 'XR jaringan lunak leher', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37639-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37651-7', 'XR Sacrum 2 Views', 'XR sacrum dua proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37651-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37652-5', 'XR Sacrum AP and Lateral', 'XR sacrum lateral', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37652-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37655-8', 'XR Scapula 2 Views', 'XR skapula dua proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37655-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37665-7', 'XR Ankle - right 3 Views', 'XR ankle tiga proyeksi kanan', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37665-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37666-5', 'XR Ankle - right AP and Lateral and Mortise', 'XR ankle AP, lateral dan mortise kanan', 'Views AP + lateral + Mortise', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37666-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37667-3', 'XR Ankle - right AP and Lateral', 'XR ankle AP dan lateral kanan', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37667-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37684-8', 'XR Elbow - right AP and Lateral', 'XR elbow AP dan lateral kanan', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37684-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37690-5', 'XR Femur - right 2 Views', 'XR femur dua proyeksi kanan', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37690-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37691-3', 'XR Femur - right 4 Views', 'XR femur kanan 4 proyeksi', 'Views 4', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37691-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37692-1', 'XR Femur - right AP and Lateral', 'XR femur AP dan lateral kanan', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37692-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37701-0', 'XR Foot - right AP and Lateral', 'XR pedis AP dan lateral kanan', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37701-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37708-5', 'XR Radius and Ulna - right AP and Lateral', 'XR antebrachi AP dan lateral kanan', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37708-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37710-1', 'XR Hand - right AP and Lateral', 'XR manus AP dan lateral kanan', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37710-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37721-8', 'XR Hip - right 2 Views', 'XR hip joint AP dan oblique kanan', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37721-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37725-9', 'XR Hip - right AP and Lateral', 'XR hip joint AP dan lateral kanan', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37725-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37726-7', 'XR Hip - right AP', 'XR artc. coxae axial kanan', 'View AP', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37726-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37728-3', 'XR Hip - right Oblique crosstable', 'XR artc. coxae oblique kanan', 'View oblique crosstable', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37728-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37730-9', 'XR Hip - right Lateral', 'XR artc. coxae lateral kanan', 'View lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37730-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37742-4', 'XR Knee - right 3 Views', 'XR genu tiga proyeksi kanan', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37742-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37745-7', 'XR Knee - right AP and Lateral', 'XR genu AP dan lateral kanan', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37745-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37752-3', 'XR Knee - right Rosenberg W standing', 'XR genu rosenberg kanan', 'View Rosenberg^W standing', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37752-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37784-6', 'XR Ribs - right Lateral', 'XR costae lateral kanan', 'View lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37784-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37787-9', 'XR Scapula - right 2 Views', 'XR skapula dua proyeksi kanan', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37787-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37788-7', 'XR Scapula - right AP and Lateral', 'XR scapula AP dan lateral kanan', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37788-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37793-7', 'XR Shoulder - right 2 Views', 'XR bahu dua proyeksi kanan', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37793-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37798-6', 'XR Shoulder - right AP', 'XR bahu AP kanan', 'View AP', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37798-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37800-0', 'XR Shoulder - right Axillary', 'XR bahu axial kanan', 'View axillary', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37800-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37815-8', 'XR Tibia and Fibula - right 2 Views', 'XR cruris dua proyeksi kanan', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37815-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37816-6', 'XR Tibia and Fibula - right AP and Lateral', 'XR cruris AP dan lateral kanan', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37816-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37832-3', 'XR Wrist - right AP and Lateral', 'XR wrist joint dextra AP dan lateral', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37832-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37840-6', 'XR Shoulder 2 Views', 'XR bahu dua proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37840-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37857-0', 'XR Sinuses Caldwell', 'XR sinus caldwell', 'View Caldwell', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37857-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37858-8', 'XR Sinuses Lateral', 'XR sinus adenoid', 'View lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37858-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37862-0', 'XR Sinuses Lateral and Waters', 'XR sinus paranasal waters dan lateral', 'Views lateral + Waters', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37862-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37863-8', 'XR Sinuses Waters', 'XR waters sinus paranasalis', 'View Waters', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37863-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37867-9', 'XR Skull 2 Views', 'XR cranium 2 proyeksi (AP & Lateral )', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37867-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37870-3', 'XR Skull Towne', 'XR towne', 'View Towne', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37870-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37881-0', 'XR Sternoclavicular Joint 3 Views', 'XR sendi sternoklavikula tiga proyeksi', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37881-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37882-8', 'XR Sternoclavicular Joint 4 Views', 'XR sendi sternoklavikula empat proyeksi', 'Views 4', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37882-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37883-6', 'XR Sternum 2 Views', 'XR sternum minimal dua proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37883-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37895-0', 'XR Tibia and Fibula 2 Views', 'XR cruris dua proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37895-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37904-0', 'XR Thoracic spine Single view', 'XR vertebra thorakal satu proyeksi', 'View', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37904-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37905-7', 'XR Thoracic spine 2 Views', 'XR vertebra thorakal dua proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37905-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37906-5', 'XR Thoracic spine 3 Views', 'XR vertebra thorakal tiga proyeksi', 'Views 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37906-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37907-3', 'XR Thoracic spine 4 Views', 'XR vertebra thorakal 4 proyeksi', 'Views 4', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37907-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37908-1', 'XR Thoracic spine AP and Lateral and oblique', 'XR vertebra thorakal AP, lateral dan oblique', 'Views AP + lateral + oblique', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37908-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '37925-5', 'XR Wrist 2 Views', 'XR pergelangan tangan dua proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '37925-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38089-9', 'XR Bones Limited Survey Views for metastasis', 'XR survey tulang terbatas untuk metastasis', 'Views survey limited for metastasis', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38089-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38101-2', 'XR Kidney Views W contrast antegrade', 'XR urografi (pielografi) dengan kontras antegrade', 'Views^W contrast antegrade', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38101-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38105-3', 'XR Kidney Views W contrast retrograde', 'XR urografi (pielografi) dengan kontras retrograde', 'Views^W contrast retrograde', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38105-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38121-0', 'XR Thoracic and lumbar spine Single view', 'XR vertebra thorakal dan lumbar satu proyeksi', 'View', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38121-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38123-6', 'XR Thoracic and lumbar spine AP and Lateral', 'XR vertebra thoracolumbar AP lateral', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38123-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38783-7', 'XR Shoulder - right AP and Axillary and Y', 'XR shoulder joint axillary, AP dan Y-view kanan', 'Views AP + axillary + Y', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38783-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38786-0', 'XR Patella - right AP and Lateral and Sunrise', 'XR patella AP, lateral dan skyline kanan', 'Views AP + lateral + Sunrise', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38786-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38847-0', 'XR Hand - left AP and Lateral', 'XR manus AP dan lateral kiri', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38847-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38857-9', 'XR Ribs - left Lateral', 'XR costae lateral kiri', 'View lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38857-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '38860-3', 'XR Wrist - left AP and Lateral', 'XR wrist joint sinistra AP dan lateral', 'Views AP + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '38860-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39048-4', 'XR Scapula AP', 'XR scapula AP kanan', 'View AP', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39048-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39049-2', 'XR Thoracic and lumbar spine AP', 'XR vertebra thoracolumbar AP', 'View AP', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39049-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39050-0', 'XR Ribs AP', 'XR costae AP', 'View AP', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39050-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39051-8', 'XR Chest Lateral', 'XR thorax lateral', 'View lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39051-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39058-3', 'XR Salivary gland Views', 'XR kelenjar liur untuk batu', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39058-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39066-6', 'XR and RF Chest AP and Lateral and Views', 'XR dan RF thorax proyeksi AP dan lateral', 'Views AP + lateral && Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39066-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39074-0', 'XR Chest AP and Lateral and Right oblique and Left oblique', 'XR thorax PA, lateral dengan proyeksi oblique kanan dan kiri', 'Views AP + lateral + R-oblique + L-oblique', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39074-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39076-5', 'XR Foot AP and Oblique', 'XR pedis AP dan oblique kanan', 'Views AP + oblique', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39076-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39079-9', 'XR Hand PA and Oblique', 'XR manus PA dan oblique', 'Views PA + oblique', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39079-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39099-7', 'XR Ribs - bilateral 4 Views and Chest PA', 'XR iga bilateral minimal empat proyeksi termasuk thorax PA', 'Views 4 && view PA', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39099-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39149-0', 'XR Gastrointestinal tract and Pulmonary system Single view for foreign body', 'XR traktus gastrointestinal dan sistem pulmoner untuk mencari benda asing, satu proyeksi', 'View for foreign body', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39149-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39328-0', 'XR Shoulder - left AP internal rotation and AP external rotation', 'XR shoulder exo dan endo kiri', 'Views AP internal rotation + AP external rotation', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39328-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '39395-9', 'XR Shoulder - right AP internal rotation and AP external rotation', 'XR shoulder exo dan endo kanan', 'Views AP internal rotation + AP external rotation', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '39395-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42019-0', 'XR Abdomen upright and left lateral decubitus', 'XR abdomen lengkap proyeksi erect dan dekubitus', 'Views upright + L-lateral-decubitus', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42019-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42159-4', 'XR Sella turcica Views', 'XR sella tursika', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42159-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42272-5', 'XR Chest PA and Lateral', 'XR thorax PA dan lateral', 'Views PA + lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42272-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42425-9', 'XR Thoracic and lumbar spine AP Views for scoliosis W standing and W right bending and W left bending and WO bending', 'XR vertebra thoracolumbar bending kanan dan kiri', 'Views AP for scoliosis^W standing + W R-bending + W L-bending + WO bending', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42425-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42429-1', 'XR Thoracic and lumbar spine AP for scoliosis W standing and W right bending', 'XR vertebra thoracolumbar bending kanan', 'View AP for scoliosis^W standing + W R-bending', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42429-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '42439-0', 'XR Neck AP', 'XR soft tissue leher AP', 'View AP', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '42439-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43466-2', 'XR Chest AP right lateral-decubitus', 'XR thorax RLD', 'View AP R-lateral-decubitus', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43466-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43470-4', 'XR Skull LE 3 Views', 'XR kalvaria kurang dari empat proyeksi', 'Views LE 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43470-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43486-0', 'XR Sinuses GE 3 Views', 'XR sinus paranasal minimal tiga proyeksi', 'Views GE 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43486-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43498-5', 'XR Knee - left GE 3 Views', 'XR genu minimum tiga proyeksi kiri', 'Views GE 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43498-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43518-0', 'XR Bones Survey Views', 'XR survey tulang (Bone Survey)', 'Views survey', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43518-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43519-8', 'XR Bones Limited Survey Views', 'XR survey tulang terbatas', 'Views survey limited', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43519-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43521-4', 'XR Mandible 1 or 2 Views', 'XR mandibula AP', 'Views 1 or 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43521-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43522-2', 'XR Pelvis 1 or 2 Views', 'XR pelvis satu atau dua proyeksi', 'Views 1 or 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43522-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43523-0', 'XR Sinuses 1 or 2 Views', 'XR sinus paranasal kurang dari tiga proyeksi', 'Views 1 or 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43523-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43539-6', 'XR Cervical spine 2 or 3 Views', 'XR vertebra cervical dua atau tiga proyeksi', 'Views 2 or 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43539-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43543-8', 'XR Pelvis GE 3 Views', 'XR pelvis minimal tiga proyeksi', 'Views GE 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43543-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43779-8', 'XR Knee - left Sunrise', 'XR sky line kiri', 'View Sunrise', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43779-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '43787-1', 'XR Skull and Facial bones and Mandible Views for dental measurement', 'XR sefalogram ortodonti', 'Views for dental measurement', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '43787-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44179-0', 'XR Sacrum and Coccyx 2 Views', 'XR vertebra sacrococcygeus AP dan lateral', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44179-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44188-1', 'XR Foot GE 3 Views', 'XR pedis minimal tiga proyeksi', 'Views GE 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44188-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44189-9', 'XR Sacroiliac Joint GE 3 Views', 'XR sendi sakroiliaka tiga proyeksi atau lebih', 'Views GE 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44189-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44190-7', 'XR Wrist GE 3 Views', 'XR pergelangan tangan lengkap minimal tiga proyeksi', 'Views GE 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44190-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44198-0', 'XR Knee 1 or 2 Views', 'XR genu satu atau dua proyeksi', 'Views 1 or 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44198-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44199-8', 'XR Facial bones 1 or 2 Views', 'XR tulang wajah kurang dari tiga proyeksi', 'Views 1 or 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44199-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44206-1', 'XR Thoracic and lumbar spine for scoliosis single view', 'XR thoracolumbal bending kiri', 'View for scoliosis', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44206-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44208-7', 'XR Orbit Views for foreign body', 'XR comberg unilateral', 'Views for foreign body', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44208-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44210-3', 'XR Ankle GE 3 Views', 'XR ankle lengkap minimal tiga proyeksi', 'Views GE 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44210-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '44212-9', 'XR Cervical spine GE 4 Views', 'XR vertebra cervical minimal empat proyeksi', 'Views GE 4', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '44212-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '47367-8', 'XR and RF Chest GE 4 Views and Views', 'XR dan RF thorax minimal empat proyeksi', 'Views GE 4 && Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '47367-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '47372-8', 'XR Hip Views during surgery', 'XR coxae intraoperatif', 'Views^during surgery', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '47372-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '47379-3', 'XR Mandible GE 4 Views', 'XR mandibula minimal empat proyeksi', 'Views GE 4', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '47379-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '47380-1', 'XR Mandible LE 3 Views', 'XR mandibula kurang dari empat proyeksi', 'Views LE 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '47380-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '47381-9', 'XR Mastoid GE 3 Views', 'XR mastoid minimal tiga proyeksi', 'Views GE 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '47381-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '47983-2', 'XR Mastoid - bilateral 1 or 2 Views', 'XR mastoid kurang dari tiga proyeksi', 'Views 1 or 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '47983-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48433-7', 'XR Calcaneus - bilateral 2 Views', 'XR calcaneus bilateral 2 proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48433-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48467-5', 'XR Sacroiliac Joint 1 or 2 Views', 'XR sendi sakroiliaka kurang dari tiga proyeksi', 'Views 1 or 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48467-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48469-1', 'XR Lumbar spine 2 or 3 Views', 'XR vertebra lumbal dua atau tiga proyeksi', 'Views 2 or 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48469-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48473-3', 'XR Spine Lumbar and Sacrum 4 Views', 'XR vertebra lumbosakral AP, lateral dan oblique bilateral', 'Views 4', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48473-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48479-0', 'XR Facial bones GE 3 Views', 'XR tulang wajah minimal tiga proyeksi', 'Views GE 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48479-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48487-3', 'XR Skull GE 4 Views', 'XR kalvaria minimal empat proyeksi', 'Views GE 4', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48487-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48490-7', 'XR Temporomandibular joint - right Open and Closed mouth', 'XR TMJ buka mulut kanan', 'Views open mouth + closed mouth', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48490-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48491-5', 'XR Temporomandibular joint - left Open and Closed mouth', 'XR TMJ buka mulut kiri', 'Views open mouth + closed mouth', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48491-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48697-7', 'XR Skull base Views', 'XR basis cranium', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48697-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48699-3', 'XR Temporomandibular joint - unilateral Open and Closed mouth', 'XR sendi temporomandibula buka dan tutup mulut unilateral', 'Views open mouth + closed mouth', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48699-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '48747-0', 'XR Orbit - bilateral GE 4 Views', 'XR orbita minimal empat proyeksi', 'Views GE 4', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '48747-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '60648-3', 'Adenoid width Skull X-ray measured', 'XR cervical adenoid/fujioka', 'Adenoid width', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '60648-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '62443-7', 'Single view Teeth Document XR', 'XR dental', 'View', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '62443-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '62444-5', 'Views Teeth.partial Document XR', 'XR gigi pemeriksaan sebagian', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '62444-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '64996-2', 'XR Lung - left Views W contrast intrabronchial', 'XR bronkografi kiri', 'Views^W contrast intrabronchial', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '64996-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '64997-0', 'XR Lung - right Views W contrast intrabronchial', 'XR bronkografi kanan', 'Views^W contrast intrabronchial', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '64997-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69058-6', 'XR Hip - bilateral 2 Views', 'XR coxae bilateral 1 proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69058-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69256-6', 'XR Knee - right Sunrise', 'XR sky line kanan', 'View Sunrise', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69256-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69269-9', 'XR Skull AP', 'XR skull AP', 'View AP', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69269-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '69270-7', 'XR Skull PA', 'XR skull PA', 'View PA', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '69270-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '72876-6', 'XR Surgical specimen Views', 'XR spesimen bedah', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '72876-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '79353-9', 'XR Elbow GE 3 Views', 'XR cubiti minimal tiga proyeksi', 'Views GE 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '79353-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '79354-7', 'XR Finger GE 2 Views', 'XR jari tangan minimal dua proyeksi', 'Views GE 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '79354-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '79357-0', 'XR Hand GE 3 Views', 'XR tangan minimal tiga proyeksi', 'Views GE 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '79357-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '79358-8', 'XR Hip - bilateral GE 4 Views', 'XR coxae bilateral 1 proyeksi', 'Views GE 4', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '79358-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '79361-2', 'XR Hip GE 2 Views', 'XR coxae minimal dua proyeksi unilateral', 'Views GE 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '79361-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '79362-0', 'XR Humerus GE 2 Views', 'XR humerus 3 proyeksi', 'Views GE 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '79362-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '79364-6', 'XR Knee GE 4 Views', 'XR genu empat proyeksi atau lebih', 'Views GE 4', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '79364-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '79367-9', 'XR Sacrum and Coccyx GE 2 Views', 'XR sakrum dan koksigea minimal dua proyeksi', 'Views GE 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '79367-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '79370-3', 'XR Shoulder GE 2 Views', 'XR bahu minimal dua proyeksi', 'Views GE 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '79370-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '79371-1', 'XR Spine Lumbar and Sacrum GE 2 Views', 'XR vertebra lumbosakral AP dan lateral', 'Views GE 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '79371-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '79372-9', 'XR Spine Lumbar and Sacrum GE 4 Views', 'XR vertebra lumbosakral minimal empat proyeksi', 'Views GE 4', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '79372-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '79373-7', 'XR Toe GE 2 Views', 'XR jari kaki dua proyeksi', 'Views GE 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '79373-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '83016-6', 'XR Abdomen GE 3 Views AP and Oblique and Cone', 'XR abdomen AP oblique dan cone', 'Views GE 3 AP + oblique + cone', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '83016-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '83019-0', 'XR Chest and Abdomen and Pelvis View babygram', 'XR abdomen dan thorax babygram', 'View babygram', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '83019-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '83020-8', 'XR Bones Complete Survey Views', 'XR survey tulang lengkap', 'Views survey complete', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '83020-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '83022-4', 'XR Cervical spine 2 or 3 views and (Views W flexion and W extension) and Views oblique', 'XR vertebra cervical oblique, fleksi dan ekstensi', '(Views 2 or 3) + (Views^W flexion + W extension) + (Views oblique)', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '83022-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '83031-5', 'XR Pelvis AP and Hip - bilateral GE 2 Views', 'XR coxae dan pelvis AP minimal dua proyeksi bilateral', 'View AP && Views GE 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '83031-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '83034-9', 'XR Pelvis and Hip - bilateral GE 2 views for pediatrics', 'XR pelvis dan coxae anak minimal dua proyeksi', 'Views GE 2 for pediatrics', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '83034-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '83036-4', 'XR Lower extremity GE 2 Views', 'XR ekstremitas bawah minimal dua proyeksi', 'Views GE 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '83036-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '83038-0', 'XR Lumbar spine Greater than 4 views and (Greater than 1 view W R-bending and W L-bending)', 'XR vertebra lumbosakral proyeksi bending minimal empat proyeksi', '(Views GE 5) + (Views GE 1^W R-bending + W L-bending)', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '83038-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '83040-6', 'XR Ribs - unilateral 2 Views', 'XR iga unilateral dua proyeksi', 'Views 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '83040-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '83049-7', 'XR Upper extremity GE 2 Views', 'XR ekstremitas atas minimal dua proyeksi', 'Views GE 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '83049-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '85151-9', 'XR Bone age', 'XR usia tulang (bone age)', 'Bone age', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '85151-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86421-5', 'XR Abdomen and RF Gastrointestinal tract upper W air contrast PO and W barium contrast PO', 'XR dan RF traktus gastrointestinal atas dan BNO dengan kontras PO dan kontras barium PO', 'View && Views^W air contrast PO+W barium contrast PO', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86421-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '86423-1', 'XR Abdomen and RF Gastrointestinal tract upper W contrast PO', 'XR dan RF traktus gastrointestinal atas dan BNO dengan kontras PO', 'View && Views^W contrast PO', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '86423-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '93605-4', 'XR Skull to Coccyx 2 or 3 Views', 'XR vertebra lengkap AP dan lateral (whole spine)', 'Views 2 or 3', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '93605-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '93606-2', 'XR Skull to Coccyx 4 or 5 Views', 'XR vertebra lengkap empat atau lima proyeksi (whole spine)', 'Views 4 or 5', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '93606-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '93607-0', 'XR Skull to Coccyx GE 6 Views', 'XR vertebra lengkap minimal enam proyeksi (whole spine)', 'Views GE 6', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '93607-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '94679-8', 'XR Chest Single View and Abdomen Supine and Upright and Lateral-decubitus', 'XR abdomen lengkap proyeksi supine, erect, dan lateral dekubitus', 'View && Views supine + upright + lateral-decubitus', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '94679-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '94682-2', 'XR Calcaneus GE 2 Views', 'XR calcaneus minimal dua proyeksi', 'Views GE 2', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '94682-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '94684-8', 'XR Spine Lumbar and Sacrum GE 6 Views W right bending and W left bending', 'XR vertebra lumbosakral proyeksi bending minimal 6 proyeksi', 'Views GE 6^W R-bending + W L-bending', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '94684-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '94685-5', 'XR Ribs - unilateral GE 3 Views and Chest PA', 'XR iga unilateral minimal tiga proyeksi termasuk thorax PA', 'Views GE 3 && view PA', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '94685-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '95610-2', 'XR Teeth Complete Views', 'XR gigi lengkap seluruh mulut', 'Views complete', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '95610-2');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '95611-0', 'XR Teeth Occlusal Views', 'XR occlusal rahang atas', 'Views occlusal', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '95611-0');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '97347-9', 'XR Clavicle - left AP', 'XR clavicula AP kiri', 'View AP', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '97347-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '97349-5', 'XR Clavicle - left Axial', 'XR clavicula AP axial kiri', 'View axial', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '97349-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '97362-8', 'XR Clavicle - right AP', 'XR clavicula AP kanan', 'View AP', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '97362-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '97364-4', 'XR Clavicle - right Axial', 'XR clavicula AP axial kanan', 'View axial', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '97364-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '97365-1', 'XR Ribs - left Oblique', 'XR costae oblique kiri', 'View oblique', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '97365-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '97366-9', 'XR Ribs - left PA', 'XR costae PA kiri', 'View PA', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '97366-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '97376-8', 'XR Clavicle - bilateral AP', 'XR clavicula AP bilateral', 'View AP', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '97376-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '97447-7', 'XR Ribs - right Oblique', 'XR costae oblique kanan', 'View oblique', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '97447-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '97448-5', 'XR Ribs - right PA', 'XR costae PA kanan', 'View PA', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '97448-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '98282-7', 'XR Stomach and Duodenum Views', 'XR duodenografi', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '98282-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '98283-5', 'XR Gastrointestinal tract upper Views', 'XR traktus gastrointestinal atas', 'Views', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '98283-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '98301-5', 'XR Knee - right AP W varus stress', 'XR genu varus stres kanan', 'View AP^W varus stress', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '98301-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '98302-3', 'XR Knee - right AP W valgus stress', 'XR genu valgus stres kanan', 'View AP^W valgus stress', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '98302-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '98303-1', 'XR Calcaneus - right Lateral', 'XR calcaneus lateral kanan', 'View lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '98303-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '98309-8', 'XR Knee - left AP W valgus stress', 'XR genu valgus stres kiri', 'View AP^W valgus stress', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '98309-8');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '98310-6', 'XR Knee - left AP W varus stress', 'XR genu varus stres kiri', 'View AP^W varus stress', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '98310-6');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '98311-4', 'XR Calcaneus - left Lateral', 'XR calcaneus lateral kiri', 'View lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '98311-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '98316-3', 'XR Knee - bilateral Single view W valgus stress', 'XR genu valgus stres bilateral', 'View^W valgus stress', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '98316-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '98317-1', 'XR Knee - bilateral AP W varus stress', 'XR genu varus stres bilateral', 'View AP^W varus stress', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '98317-1');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '98330-4', 'XR Sternum PA Views', 'XR sternum PA', 'Views PA', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '98330-4');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '98356-9', 'XR Coccyx AP', 'XR coccygeus AP', 'View AP', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '98356-9');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '98357-7', 'XR Coccyx Lateral', 'XR coccygeus lateral', 'View lateral', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '98357-7');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '98358-5', 'XR Cervical spine Single view W flexion', 'XR cervical flexi', 'View^W flexion', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '98358-5');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '98359-3', 'XR Cervical spine Odontoid', 'XR cervical AP open mouth', 'View odontoid', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '98359-3');
INSERT INTO rsmst_loinc_codes (loinc_code, display, display_id, component, loinc_class, created_at)
  SELECT '98360-1', 'XR Cervical spine Single view W extension', 'XR cervical extensi', 'View^W extension', 'RAD/XR', SYSDATE FROM dual
  WHERE NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes WHERE loinc_code = '98360-1');

COMMIT;

-- ============================================================
-- 3) SESUDAH dijalankan: sisir master radiologi yang kodenya jadi yatim.
--    Baris ini menunjuk kode yang TIDAK ada di lampiran resmi — harus
--    dikosongkan atau dipetakan ulang lewat layar Master Radiologi.
--    (Sengaja SELECT, bukan UPDATE: pemetaan ulang keputusan manusia.)
-- ============================================================
-- SELECT r.rad_id, r.rad_desc, r.loinc_code, r.loinc_display
--   FROM rsmst_radiologis r
--  WHERE r.loinc_code IS NOT NULL
--    AND NOT EXISTS (SELECT 1 FROM rsmst_loinc_codes c WHERE c.loinc_code = r.loinc_code)
--  ORDER BY r.rad_desc;
