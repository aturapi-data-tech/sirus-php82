-- ============================================================
-- Remap: rsmst_radiologis.loinc_code -> kode LOINC resmi SATUSEHAT
--
-- Menggantikan pemetaan lama di alter_rsmst_radiologis_add_loinc.sql, yang
-- memakai kode karangan: 44 kode tak ada di LOINC, 17 kode ada tapi artinya
-- pemeriksaan lain, dan satu kode dipakai berulang untuk sisi kanan/kiri serta
-- proyeksi berbeda (mis. lima baris SHOULDER semuanya 37764-9).
--
-- Sumber kode: lampiran resmi SATUSEHAT (lihat seed_rsmst_loinc_codes_radiologi.sql).
-- Jalankan SETELAH seed tersebut. Lembar tinjauan berdampingan:
-- database/data/review_mapping_radiologi_loinc.csv
--
-- BELUM DIVERIFIKASI RADIOGRAFER. 108 baris 'pasti' (anatomi + sisi + proyeksi
-- cocok persis) dan 40 baris 'perlu-cek' — komentar di tiap baris perlu-cek
-- menjelaskan kompromi yang diambil. Tinjau dulu sebelum dijalankan.
-- ============================================================

-- ─── PASTI ────────────────────
UPDATE rsmst_radiologis SET loinc_code = '26387-1', loinc_display = 'XR Ankle - right 2 Views' WHERE rad_id = 'R41';  -- ANKLE JOINT D -> XR ankle dua proyeksi kanan
UPDATE rsmst_radiologis SET loinc_code = '26386-3', loinc_display = 'XR Ankle - left 2 Views' WHERE rad_id = 'R67';  -- ANKLE JOINT S -> XR ankle dua proyeksi kiri
UPDATE rsmst_radiologis SET loinc_code = '37708-5', loinc_display = 'XR Radius and Ulna - right AP and Lateral' WHERE rad_id = 'R17';  -- ANTEBRACHII D -> XR antebrachi AP dan lateral kanan
UPDATE rsmst_radiologis SET loinc_code = '36700-3', loinc_display = 'XR Radius and Ulna - left AP and Lateral' WHERE rad_id = 'R27';  -- ANTEBRACHII S -> XR antebrachi AP dan lateral kiri
UPDATE rsmst_radiologis SET loinc_code = '26132-1', loinc_display = 'XR Hip - right Views' WHERE rad_id = 'R115';  -- ART. COXAE AP D -> XR coxae kanan
UPDATE rsmst_radiologis SET loinc_code = '26131-3', loinc_display = 'XR Hip - left Views' WHERE rad_id = 'R116';  -- ART. COXAE AP S -> XR artc. coxae kiri
UPDATE rsmst_radiologis SET loinc_code = '37730-9', loinc_display = 'XR Hip - right Lateral' WHERE rad_id = 'R117';  -- ART. COXAE LAT D -> XR artc. coxae lateral kanan
UPDATE rsmst_radiologis SET loinc_code = '36603-9', loinc_display = 'XR Hip - left Lateral' WHERE rad_id = 'R118';  -- ART. COXAE LAT S -> XR artc. coxae lateral kiri
UPDATE rsmst_radiologis SET loinc_code = '83019-0', loinc_display = 'XR Chest and Abdomen and Pelvis View babygram' WHERE rad_id = 'R119';  -- BABYGRAM -> XR abdomen dan thorax babygram
UPDATE rsmst_radiologis SET loinc_code = '43574-3', loinc_display = 'RF Upper gastrointestinal tract and Small bowel Views W barium contrast PO' WHERE rad_id = 'R83';  -- BARIUM FOLLOW THROUGH -> RF followtrough
UPDATE rsmst_radiologis SET loinc_code = '48697-7', loinc_display = 'XR Skull base Views' WHERE rad_id = 'R33';  -- BASIS CRANII -> XR basis cranium
UPDATE rsmst_radiologis SET loinc_code = '42429-1', loinc_display = 'XR Thoracic and lumbar spine AP for scoliosis W standing and W right bending' WHERE rad_id = 'R15';  -- BENDING D -> XR vertebra thoracolumbar bending kanan
UPDATE rsmst_radiologis SET loinc_code = '44206-1', loinc_display = 'XR Thoracic and lumbar spine for scoliosis single view' WHERE rad_id = 'R12';  -- BENDING S -> XR thoracolumbal bending kiri
UPDATE rsmst_radiologis SET loinc_code = '24799-9', loinc_display = 'XR Abdomen AP' WHERE rad_id = 'R24';  -- BNO -> XR abdomen AP
UPDATE rsmst_radiologis SET loinc_code = '24799-9', loinc_display = 'XR Abdomen AP' WHERE rad_id = '2A';  -- BOF -> XR abdomen AP
UPDATE rsmst_radiologis SET loinc_code = '24799-9', loinc_display = 'XR Abdomen AP' WHERE rad_id = 'R4';  -- BOF -> XR abdomen AP
UPDATE rsmst_radiologis SET loinc_code = '37046-0', loinc_display = 'XR Abdomen Upright' WHERE rad_id = 'R55';  -- BOF 1/2 DUDUK -> XR BOF/BNO/KUB erect
UPDATE rsmst_radiologis SET loinc_code = '36293-9', loinc_display = 'XR Abdomen 3 Views' WHERE rad_id = 'R121';  -- BOF 3 POSISI -> XR abdomen tiga proyeksi
UPDATE rsmst_radiologis SET loinc_code = '36331-7', loinc_display = 'XR Cervical spine 4 Views' WHERE rad_id = 'R1041';  -- CERV. AP / LAT. / OBL. D / OBL. S -> XR cervical 4 proyeksi
UPDATE rsmst_radiologis SET loinc_code = '30725-6', loinc_display = 'XR Cervical spine AP' WHERE rad_id = 'R10';  -- CERVICAL AP -> XR cervical AP
UPDATE rsmst_radiologis SET loinc_code = '24942-5', loinc_display = 'XR Cervical spine AP and Lateral' WHERE rad_id = '2C';  -- CERVICAL AP LATERAL -> XR cervical AP dan lateral
UPDATE rsmst_radiologis SET loinc_code = '24943-3', loinc_display = 'XR Cervical spine Lateral' WHERE rad_id = 'R108';  -- CERVICAL LAT. -> XR cervical lateral
UPDATE rsmst_radiologis SET loinc_code = '97362-8', loinc_display = 'XR Clavicle - right AP' WHERE rad_id = 'R34';  -- CLAVICULA D -> XR clavicula AP kanan
UPDATE rsmst_radiologis SET loinc_code = '97347-9', loinc_display = 'XR Clavicle - left AP' WHERE rad_id = 'R56';  -- CLAVICULA S -> XR clavicula AP kiri
UPDATE rsmst_radiologis SET loinc_code = '98356-9', loinc_display = 'XR Coccyx AP' WHERE rad_id = 'R21';  -- COCCYGEUS AP -> XR coccygeus AP
UPDATE rsmst_radiologis SET loinc_code = '98357-7', loinc_display = 'XR Coccyx Lateral' WHERE rad_id = 'R54';  -- COCCYGEUS LATERAL -> XR coccygeus lateral
UPDATE rsmst_radiologis SET loinc_code = '44227-7', loinc_display = 'RF Colon Views W barium contrast PR' WHERE rad_id = 'R71';  -- COLON IN LOOP -> RF kolon dengan barium enema
UPDATE rsmst_radiologis SET loinc_code = '37816-6', loinc_display = 'XR Tibia and Fibula - right AP and Lateral' WHERE rad_id = 'R29';  -- CRURIS D -> XR cruris AP dan lateral kanan
UPDATE rsmst_radiologis SET loinc_code = '36718-5', loinc_display = 'XR Tibia and Fibula - left AP and Lateral' WHERE rad_id = 'R20';  -- CRURIS S -> XR cruris AP dan lateral kiri
UPDATE rsmst_radiologis SET loinc_code = '98360-1', loinc_display = 'XR Cervical spine Single view W extension' WHERE rad_id = 'R112';  -- DYNAMIC CERV EXTENSI -> XR cervical extensi
UPDATE rsmst_radiologis SET loinc_code = '98358-5', loinc_display = 'XR Cervical spine Single view W flexion' WHERE rad_id = 'R13';  -- DYNAMIC CERV FLEXI -> XR cervical flexi
UPDATE rsmst_radiologis SET loinc_code = '37684-8', loinc_display = 'XR Elbow - right AP and Lateral' WHERE rad_id = 'R36';  -- ELBOW D -> XR elbow AP dan lateral kanan
UPDATE rsmst_radiologis SET loinc_code = '36691-4', loinc_display = 'XR Elbow - left AP and Lateral' WHERE rad_id = 'R61';  -- ELBOW S -> XR elbow AP dan lateral kiri
UPDATE rsmst_radiologis SET loinc_code = '37692-1', loinc_display = 'XR Femur - right AP and Lateral' WHERE rad_id = 'R28';  -- FEMUR D -> XR femur AP dan lateral kanan
UPDATE rsmst_radiologis SET loinc_code = '36695-5', loinc_display = 'XR Femur - left AP and Lateral' WHERE rad_id = 'R49';  -- FEMUR S -> XR femur AP dan lateral kiri
UPDATE rsmst_radiologis SET loinc_code = '37745-7', loinc_display = 'XR Knee - right AP and Lateral' WHERE rad_id = 'R19';  -- GENU D -> XR genu AP dan lateral kanan
UPDATE rsmst_radiologis SET loinc_code = '36710-2', loinc_display = 'XR Knee - left AP and Lateral' WHERE rad_id = 'R52';  -- GENU S -> XR genu AP dan lateral kiri
UPDATE rsmst_radiologis SET loinc_code = '37725-9', loinc_display = 'XR Hip - right AP and Lateral' WHERE rad_id = 'R57';  -- HIP JOINT D AP/LATERAL -> XR hip joint AP dan lateral kanan
UPDATE rsmst_radiologis SET loinc_code = '36704-5', loinc_display = 'XR Hip - left AP and Lateral' WHERE rad_id = 'R58';  -- HIP JOINT S AP/LATERAL -> XR hip joint AP dan lateral kiri
UPDATE rsmst_radiologis SET loinc_code = '25022-5', loinc_display = 'RF Uterus and Fallopian tubes Views W contrast IU' WHERE rad_id = 'H01';  -- HISTEROSALPINGOGRAFI (HSG) -> RF histerosalfingografi (HSG)
UPDATE rsmst_radiologis SET loinc_code = '25022-5', loinc_display = 'RF Uterus and Fallopian tubes Views W contrast IU' WHERE rad_id = 'R74';  -- HSG -> RF histerosalfingografi (HSG)
UPDATE rsmst_radiologis SET loinc_code = '37607-9', loinc_display = 'XR Kidney Views W contrast IV' WHERE rad_id = 'R44';  -- IVP 1 -> XR urografi (pielografi) dengan kontras IV
UPDATE rsmst_radiologis SET loinc_code = '37607-9', loinc_display = 'XR Kidney Views W contrast IV' WHERE rad_id = 'R45';  -- IVP 2 -> XR urografi (pielografi) dengan kontras IV
UPDATE rsmst_radiologis SET loinc_code = '43521-4', loinc_display = 'XR Mandible 1 or 2 Views' WHERE rad_id = 'R7';  -- MANDIBULA AP -> XR mandibula AP
UPDATE rsmst_radiologis SET loinc_code = '36747-4', loinc_display = 'XR Mandible Oblique Views' WHERE rad_id = 'R46';  -- MANDIBULA EISLER -> XR mandibula eisler
UPDATE rsmst_radiologis SET loinc_code = '37710-1', loinc_display = 'XR Hand - right AP and Lateral' WHERE rad_id = 'R53';  -- MANUS D -> XR manus AP dan lateral kanan
UPDATE rsmst_radiologis SET loinc_code = '38847-0', loinc_display = 'XR Hand - left AP and Lateral' WHERE rad_id = 'R38';  -- MANUS S -> XR manus AP dan lateral kiri
UPDATE rsmst_radiologis SET loinc_code = '26141-2', loinc_display = 'XR Mastoid - right Views' WHERE rad_id = 'R8';  -- MASTOIS SCHULLER D -> XR mastoid schuller kanan
UPDATE rsmst_radiologis SET loinc_code = '26140-4', loinc_display = 'XR Mastoid - left Views' WHERE rad_id = 'R18';  -- MASTOIS SCHULLER S -> XR mastoid schuller kiri
UPDATE rsmst_radiologis SET loinc_code = '24948-2', loinc_display = 'XR Spine Cervical Odontoid and Cervical axis AP' WHERE rad_id = 'R16';  -- ODONTOID -> XR cervical I dan odontoid
UPDATE rsmst_radiologis SET loinc_code = '24678-5', loinc_display = 'RF Esophagus Views W contrast PO' WHERE rad_id = 'R76';  -- OESOPHAGOGRAFI -> RF esofagografi
UPDATE rsmst_radiologis SET loinc_code = '39076-5', loinc_display = 'XR Foot AP and Oblique' WHERE rad_id = 'R43';  -- PEDIS PA / OBL D -> XR pedis AP dan oblique kanan
UPDATE rsmst_radiologis SET loinc_code = '37622-8', loinc_display = 'XR Pelvis AP' WHERE rad_id = 'R6';  -- PELVIS AP -> XR pelvis AP
UPDATE rsmst_radiologis SET loinc_code = '37626-9', loinc_display = 'XR Pelvis Lateral frog' WHERE rad_id = 'R25';  -- PELVIS FROG POSITON -> XR frog position
UPDATE rsmst_radiologis SET loinc_code = '37623-6', loinc_display = 'XR Pelvis AP and Inlet and Outlet' WHERE rad_id = 'R125';  -- PELVIS INLET & OUTLET -> XR pelvis AP, inlet dan outlet
UPDATE rsmst_radiologis SET loinc_code = '24948-2', loinc_display = 'XR Spine Cervical Odontoid and Cervical axis AP' WHERE rad_id = 'R109';  -- PROC. ODONTOIDEUS -> XR cervical I dan odontoid
UPDATE rsmst_radiologis SET loinc_code = '37652-5', loinc_display = 'XR Sacrum AP and Lateral' WHERE rad_id = 'R40';  -- SACRUM LATERAL -> XR sacrum lateral
UPDATE rsmst_radiologis SET loinc_code = '37798-6', loinc_display = 'XR Shoulder - right AP' WHERE rad_id = 'R51';  -- SHOULDER AP D -> XR bahu AP kanan
UPDATE rsmst_radiologis SET loinc_code = '36587-4', loinc_display = 'XR Shoulder - left AP' WHERE rad_id = 'R59';  -- SHOULDER AP S -> XR bahu AP kiri
UPDATE rsmst_radiologis SET loinc_code = '39395-9', loinc_display = 'XR Shoulder - right AP internal rotation and AP external rotation' WHERE rad_id = 'R35';  -- SHOULDER JOINT EXO-ENDO D -> XR shoulder exo dan endo kanan
UPDATE rsmst_radiologis SET loinc_code = '39328-0', loinc_display = 'XR Shoulder - left AP internal rotation and AP external rotation' WHERE rad_id = 'R60';  -- SHOULDER JOINT EXO-ENDO S -> XR shoulder exo dan endo kiri
UPDATE rsmst_radiologis SET loinc_code = '86401-7', loinc_display = 'RF Urinary bladder Views W contrast intra bladder' WHERE rad_id = 'R73';  -- SISTOGRAFI -> RF cystogram w kontras intrabladder
UPDATE rsmst_radiologis SET loinc_code = '69269-9', loinc_display = 'XR Skull AP' WHERE rad_id = 'R30';  -- SKULL AP -> XR skull AP
UPDATE rsmst_radiologis SET loinc_code = '24920-1', loinc_display = 'XR Skull Lateral' WHERE rad_id = 'R31';  -- SKULL LATERAL -> XR skull lateral
UPDATE rsmst_radiologis SET loinc_code = '69256-6', loinc_display = 'XR Knee - right Sunrise' WHERE rad_id = 'R64';  -- SKYLINE D -> XR sky line kanan
UPDATE rsmst_radiologis SET loinc_code = '43779-8', loinc_display = 'XR Knee - left Sunrise' WHERE rad_id = 'R65';  -- SKYLINE S -> XR sky line kiri
UPDATE rsmst_radiologis SET loinc_code = '37153-4', loinc_display = 'XR Mastoid Stenver and Arcelin' WHERE rad_id = 'R104';  -- STEVENVER'S D -> XR stenvers kanan
UPDATE rsmst_radiologis SET loinc_code = '39051-8', loinc_display = 'XR Chest Lateral' WHERE rad_id = 'R2';  -- THORAX LATERAL -> XR thorax lateral
UPDATE rsmst_radiologis SET loinc_code = '37010-6', loinc_display = 'XR Chest Right oblique' WHERE rad_id = 'R22';  -- THORAX OBLIQUE D -> XR thorax oblique kanan
UPDATE rsmst_radiologis SET loinc_code = '37008-0', loinc_display = 'XR Chest Left oblique' WHERE rad_id = 'R3';  -- THORAX OBLIQUE S -> XR thorax oblique kiri
UPDATE rsmst_radiologis SET loinc_code = '24648-8', loinc_display = 'XR Chest PA upright' WHERE rad_id = '2B';  -- THORAX PA -> XR thorax PA
UPDATE rsmst_radiologis SET loinc_code = '36643-5', loinc_display = 'XR Chest 2 Views' WHERE rad_id = 'R1';  -- THORAX PA / AP -> XR thorax dua proyeksi
UPDATE rsmst_radiologis SET loinc_code = '36687-2', loinc_display = 'XR Chest AP and Lateral' WHERE rad_id = 'R1036';  -- THORAX PA / LAT. -> XR thorax dua proyeksi AP dan lateral
UPDATE rsmst_radiologis SET loinc_code = '24640-5', loinc_display = 'XR Chest Apical lordotic' WHERE rad_id = 'R23';  -- THORAX TOP LORDOTIC -> XR top lordotik
UPDATE rsmst_radiologis SET loinc_code = '37546-9', loinc_display = 'XR Temporomandibular joint - bilateral Open and Closed mouth' WHERE rad_id = 'R209';  -- TMJ D/S OPEN & CLOSE MOUTH -> XR sendi temporomandibula buka dan tutup mulut bilateral
UPDATE rsmst_radiologis SET loinc_code = '98283-5', loinc_display = 'XR Gastrointestinal tract upper Views' WHERE rad_id = 'R75';  -- UPPER GI -> XR traktus gastrointestinal atas
UPDATE rsmst_radiologis SET loinc_code = '25020-9', loinc_display = 'RF Urinary bladder and Urethra Views W contrast retrograde via urethra' WHERE rad_id = 'R77';  -- URETHRO-SISTOGRAFI -> RF uretrosistografi retrograd
UPDATE rsmst_radiologis SET loinc_code = '25016-7', loinc_display = 'RF Urethra Views W contrast intra urethra' WHERE rad_id = 'R72';  -- URETHROGRAFI -> RF urethrografi
UPDATE rsmst_radiologis SET loinc_code = '24534-0', loinc_display = 'US.doppler Abdominal vessels' WHERE rad_id = 'R218';  -- USG ABDOMEN + DOPPLER -> US doppler pembuluh darah abdomen
UPDATE rsmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Abdomen' WHERE rad_id = 'R212';  -- USG ABDOMEN CITO -> US abdomen
UPDATE rsmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Abdomen' WHERE rad_id = 'U3';  -- USG ABDOMEN TOTAL -> US abdomen
UPDATE rsmst_radiologis SET loinc_code = '80852-7', loinc_display = 'US Axilla' WHERE rad_id = 'U15';  -- USG AXILLA D/S -> US axilla
UPDATE rsmst_radiologis SET loinc_code = '24842-7', loinc_display = 'US Neck' WHERE rad_id = 'U28';  -- USG COLLI -> US leher
UPDATE rsmst_radiologis SET loinc_code = '43765-7', loinc_display = 'US.doppler Carotid arteries - bilateral' WHERE rad_id = 'U2';  -- USG DOPPLER CAROTIS D+S -> US doppler carotis bilateral
UPDATE rsmst_radiologis SET loinc_code = '39426-2', loinc_display = 'US.doppler Renal vessels' WHERE rad_id = 'U12';  -- USG DOPPLER GINJAL D&S -> US doppler pembuluh darah abdomen bawah (ginjal)
UPDATE rsmst_radiologis SET loinc_code = '39426-2', loinc_display = 'US.doppler Renal vessels' WHERE rad_id = 'U11';  -- USG DOPPLER GINJAL D/S -> US doppler pembuluh darah abdomen bawah (ginjal)
UPDATE rsmst_radiologis SET loinc_code = '69284-8', loinc_display = 'US.doppler Portal vein and Hepatic vein' WHERE rad_id = 'U6';  -- USG HEPAR DOPPLER -> US doppler vaskular hepar
UPDATE rsmst_radiologis SET loinc_code = '24719-7', loinc_display = 'US Groin' WHERE rad_id = 'U19';  -- USG INGUINAL(KGB) D/S -> US inguinal
UPDATE rsmst_radiologis SET loinc_code = '24601-7', loinc_display = 'US Breast' WHERE rad_id = 'U14';  -- USG MAMMAE D&S -> US mammae
UPDATE rsmst_radiologis SET loinc_code = '24601-7', loinc_display = 'US Breast' WHERE rad_id = 'U13';  -- USG MAMMAE D/S -> US mammae
UPDATE rsmst_radiologis SET loinc_code = '80855-0', loinc_display = 'US Extremity musculoskeletal tissue' WHERE rad_id = 'U21';  -- USG MUSCILO. D/S, SHOUL. KNEE -> US muskuloskeletal
UPDATE rsmst_radiologis SET loinc_code = '25002-7', loinc_display = 'US Scrotum and testicle' WHERE rad_id = 'U18';  -- USG TESTIS -> US skrotum dan testis
UPDATE rsmst_radiologis SET loinc_code = '25002-7', loinc_display = 'US Scrotum and testicle' WHERE rad_id = 'R216';  -- USG TESTIS/SCROTUM CITO -> US skrotum dan testis
UPDATE rsmst_radiologis SET loinc_code = '24630-6', loinc_display = 'US Chest' WHERE rad_id = 'U23';  -- USG THORAX MARKER -> US thorax
UPDATE rsmst_radiologis SET loinc_code = '24630-6', loinc_display = 'US Chest' WHERE rad_id = 'U24';  -- USG THORAX MARKER CITO -> US thorax
UPDATE rsmst_radiologis SET loinc_code = '25010-0', loinc_display = 'US Thyroid gland' WHERE rad_id = 'U8';  -- USG TYROID -> US thyroid
UPDATE rsmst_radiologis SET loinc_code = '25010-0', loinc_display = 'US Thyroid gland' WHERE rad_id = 'U7';  -- USG TYROID -> US thyroid
UPDATE rsmst_radiologis SET loinc_code = '35093-4', loinc_display = 'Urology urinary tract ultrasound' WHERE rad_id = 'U10';  -- USG UROLOGI -> US tractus urinarius
UPDATE rsmst_radiologis SET loinc_code = '35093-4', loinc_display = 'Urology urinary tract ultrasound' WHERE rad_id = 'R215';  -- USG UROLOGI CITO -> US tractus urinarius
UPDATE rsmst_radiologis SET loinc_code = '79371-1', loinc_display = 'XR Spine Lumbar and Sacrum GE 2 Views' WHERE rad_id = 'R1034';  -- VERT. LUMBOSACRAL AP / LAT -> XR vertebra lumbosakral AP dan lateral
UPDATE rsmst_radiologis SET loinc_code = '24969-8', loinc_display = 'XR Lumbar spine Lateral' WHERE rad_id = 'R102';  -- VERT. LUMBOSACRAL LATERAL -> XR vertebra lumbosakral lateral
UPDATE rsmst_radiologis SET loinc_code = '30752-0', loinc_display = 'XR Thoracic spine AP' WHERE rad_id = 'R101';  -- VERT. THORACALIS AP -> XR vertebra thorakal AP
UPDATE rsmst_radiologis SET loinc_code = '30753-8', loinc_display = 'XR Thoracic spine AP and Lateral' WHERE rad_id = 'R120';  -- VERT. THORACALIS AP/LAT -> XR vertebra thorakal AP dan lateral
UPDATE rsmst_radiologis SET loinc_code = '30756-1', loinc_display = 'XR Thoracic spine Lateral' WHERE rad_id = 'R103';  -- VERT. THORACALIS LATERAL -> XR vertebra thorakal lateral
UPDATE rsmst_radiologis SET loinc_code = '24921-9', loinc_display = 'XR Skull Waters' WHERE rad_id = 'R32';  -- WATER'S -> XR waters
UPDATE rsmst_radiologis SET loinc_code = '37832-3', loinc_display = 'XR Wrist - right AP and Lateral' WHERE rad_id = 'R37';  -- WRIST JOINT D -> XR wrist joint dextra AP dan lateral
UPDATE rsmst_radiologis SET loinc_code = '37832-3', loinc_display = 'XR Wrist - right AP and Lateral' WHERE rad_id = 'R62';  -- WRIST JOINT D -> XR wrist joint dextra AP dan lateral
UPDATE rsmst_radiologis SET loinc_code = '38860-3', loinc_display = 'XR Wrist - left AP and Lateral' WHERE rad_id = 'R63';  -- WRIST JOINT S -> XR wrist joint sinistra AP dan lateral

-- ─── PERLU DICEK RADIOGRAFER ────────────────────
UPDATE rsmst_radiologis SET loinc_code = '69242-6', loinc_display = 'RF Guidance for percutaneous drainage of abscess and placement of drainage catheter of Appendix' WHERE rad_id = 'R78';  -- APPENDICOGRAFI 1 -> Appendicography
UPDATE rsmst_radiologis SET loinc_code = '69242-6', loinc_display = 'RF Guidance for percutaneous drainage of abscess and placement of drainage catheter of Appendix' WHERE rad_id = 'R79';  -- APPENDICOGRAFI 2 -> Appendicography
UPDATE rsmst_radiologis SET loinc_code = '98303-1', loinc_display = 'XR Calcaneus - right Lateral' WHERE rad_id = 'R42';  -- CALCANEUS  AP / TG D -> XR calcaneus lateral kanan
UPDATE rsmst_radiologis SET loinc_code = '98311-4', loinc_display = 'XR Calcaneus - left Lateral' WHERE rad_id = 'R70';  -- CALCANEUS AP / TG S -> XR calcaneus lateral kiri
UPDATE rsmst_radiologis SET loinc_code = '36748-2', loinc_display = 'XR Cervical spine Oblique Views' WHERE rad_id = 'R11';  -- CERVICAL OBLIQUE D -> XR cervical oblique
UPDATE rsmst_radiologis SET loinc_code = '36748-2', loinc_display = 'XR Cervical spine Oblique Views' WHERE rad_id = 'R50';  -- CERVICAL OBLIQUE S -> XR cervical oblique
UPDATE rsmst_radiologis SET loinc_code = '44227-7', loinc_display = 'RF Colon Views W barium contrast PR' WHERE rad_id = 'R81';  -- COLON IN LOOP BAYI -> RF kolon dengan barium enema
UPDATE rsmst_radiologis SET loinc_code = '37132-8', loinc_display = 'XR Lumbar spine Lateral Views W flexion and W extension' WHERE rad_id = 'R114';  -- DYNAMIC LS EXTENSI -> XR vertebra lumbosakral lateral flexi dan extensi
UPDATE rsmst_radiologis SET loinc_code = '37132-8', loinc_display = 'XR Lumbar spine Lateral Views W flexion and W extension' WHERE rad_id = 'R113';  -- DYNAMIC LS FLEXI -> XR vertebra lumbosakral lateral flexi dan extensi
UPDATE rsmst_radiologis SET loinc_code = '37062-7', loinc_display = 'XR Humerus - bilateral Views' WHERE rad_id = 'R26';  -- HUMERUS D -> XR humerus dua proyeksi kanan atau kiri
UPDATE rsmst_radiologis SET loinc_code = '37062-7', loinc_display = 'XR Humerus - bilateral Views' WHERE rad_id = 'R47';  -- HUMERUS S -> XR humerus dua proyeksi kanan atau kiri
UPDATE rsmst_radiologis SET loinc_code = '36641-9', loinc_display = 'XR Abdomen 2 Views' WHERE rad_id = 'R5';  -- LLD & BOF -> XR abdomen dua proyeksi
UPDATE rsmst_radiologis SET loinc_code = '24834-4', loinc_display = 'XR Nasal bones Views' WHERE rad_id = 'R48';  -- NASAL LATERAL -> XR nasal tangensial
UPDATE rsmst_radiologis SET loinc_code = '30784-3', loinc_display = 'XR Foot 2 Views' WHERE rad_id = 'R68';  -- PEDIS PA / OBL S -> XR pedis dua proyeksi
UPDATE rsmst_radiologis SET loinc_code = '37586-5', loinc_display = 'RF Penis Views W contrast intra corpus cavernosum' WHERE rad_id = 'R106';  -- PENIS -> RF corpora cavernosografi
UPDATE rsmst_radiologis SET loinc_code = '44179-0', loinc_display = 'XR Sacrum and Coccyx 2 Views' WHERE rad_id = 'R110';  -- SACRO-COCCYGEUS AP -> XR vertebra sacrococcygeus AP dan lateral
UPDATE rsmst_radiologis SET loinc_code = '44179-0', loinc_display = 'XR Sacrum and Coccyx 2 Views' WHERE rad_id = 'R111';  -- SACRO-COCCYGEUS LAT -> XR vertebra sacrococcygeus AP dan lateral
UPDATE rsmst_radiologis SET loinc_code = '37651-7', loinc_display = 'XR Sacrum 2 Views' WHERE rad_id = 'R39';  -- SACRUM AP -> XR sacrum dua proyeksi
UPDATE rsmst_radiologis SET loinc_code = '37800-0', loinc_display = 'XR Shoulder - right Axillary' WHERE rad_id = 'R107';  -- SHOULDER AXIAL D/S -> XR bahu axial kanan
UPDATE rsmst_radiologis SET loinc_code = '37607-9', loinc_display = 'XR Kidney Views W contrast IV' WHERE rad_id = 'R80';  -- SINGLE SHOT IVP -> XR urografi (pielografi) dengan kontras IV
UPDATE rsmst_radiologis SET loinc_code = '37153-4', loinc_display = 'XR Mastoid Stenver and Arcelin' WHERE rad_id = 'R105';  -- STEVENVER'S S -> XR stenvers kanan
UPDATE rsmst_radiologis SET loinc_code = '48490-7', loinc_display = 'XR Temporomandibular joint - right Open and Closed mouth' WHERE rad_id = 'R9';  -- TMJ D -> XR TMJ buka mulut kanan
UPDATE rsmst_radiologis SET loinc_code = '48491-5', loinc_display = 'XR Temporomandibular joint - left Open and Closed mouth' WHERE rad_id = 'R14';  -- TMJ S -> XR TMJ buka mulut kiri
UPDATE rsmst_radiologis SET loinc_code = '98283-5', loinc_display = 'XR Gastrointestinal tract upper Views' WHERE rad_id = 'R82';  -- UPPER GI BAYI -> XR traktus gastrointestinal atas
UPDATE rsmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Abdomen' WHERE rad_id = 'U5';  -- USG ABDOMEN ATAS -> US abdomen
UPDATE rsmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Abdomen' WHERE rad_id = 'U4';  -- USG ABDOMEN BAWAH -> US abdomen
UPDATE rsmst_radiologis SET loinc_code = '46364-6', loinc_display = 'US Lower extremity vein - bilateral' WHERE rad_id = 'U20';  -- USG DEEP VEIN THROMBOSIS (DVT) D/S -> US vena ekstremitas inferior bilateral
UPDATE rsmst_radiologis SET loinc_code = '39449-4', loinc_display = 'US.doppler Extremity vein' WHERE rad_id = 'U25';  -- USG DOPLER EXTERMITAS -> US doppler vena ekstremitas
UPDATE rsmst_radiologis SET loinc_code = '39423-9', loinc_display = 'US.doppler Upper extremity artery - bilateral' WHERE rad_id = 'U26';  -- USG DOPLER EXTERMITAS ATAS D/S -> US doppler arteri ekstremitas atas bilateral
UPDATE rsmst_radiologis SET loinc_code = '39421-3', loinc_display = 'US.doppler Lower extremity artery - bilateral' WHERE rad_id = 'U27';  -- USG DOPLER EXTERMITAS BWH D/S -> US doppler arteri ekstremitas bawah bilateral lengkap
UPDATE rsmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Abdomen' WHERE rad_id = 'U22';  -- USG DOPPLER PER ORGAN -> US abdomen
UPDATE rsmst_radiologis SET loinc_code = '42455-6', loinc_display = 'US Pelvis transabdominal and transvaginal' WHERE rad_id = 'U17';  -- USG KANDUNGAN 4D -> US obstetri ginekologi 2 dimensi
UPDATE rsmst_radiologis SET loinc_code = '42455-6', loinc_display = 'US Pelvis transabdominal and transvaginal' WHERE rad_id = 'U16';  -- USG KANDUNGAN CDFI -> US obstetri ginekologi 2 dimensi
UPDATE rsmst_radiologis SET loinc_code = '38138-4', loinc_display = 'US Parotid gland' WHERE rad_id = 'U9';  -- USG KELENJAR LIUR -> US parotid
UPDATE rsmst_radiologis SET loinc_code = '81158-8', loinc_display = 'US Pediatric Head' WHERE rad_id = 'U1';  -- USG KEPALA -> US kepala anak
UPDATE rsmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Abdomen' WHERE rad_id = 'R214';  -- USG LOWER ABDOMEN CITO -> US abdomen
UPDATE rsmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Abdomen' WHERE rad_id = 'R220';  -- USG LOWER ABDOMEN CITO -> US abdomen
UPDATE rsmst_radiologis SET loinc_code = '42455-6', loinc_display = 'US Pelvis transabdominal and transvaginal' WHERE rad_id = 'R217';  -- USG OBGYN CITO -> US obstetri ginekologi 2 dimensi
UPDATE rsmst_radiologis SET loinc_code = '24558-9', loinc_display = 'US Abdomen' WHERE rad_id = 'R213';  -- USG UPPER ABDOMEN CITO -> US abdomen
UPDATE rsmst_radiologis SET loinc_code = '36670-8', loinc_display = 'XR Lumbar spine 2 Views' WHERE rad_id = 'R100';  -- VERT. LUMBOSACRAL AP -> XR vertebra lumbal dua proyeksi

-- ─── Bukan pemeriksaan / terlalu umum: kode dikosongkan ───
UPDATE rsmst_radiologis SET loinc_code = NULL, loinc_display = NULL WHERE rad_id = '111';  --  (administratif)
UPDATE rsmst_radiologis SET loinc_code = NULL, loinc_display = NULL WHERE rad_id = '2D';  -- BACAAN MADINAH (administratif)
UPDATE rsmst_radiologis SET loinc_code = NULL, loinc_display = NULL WHERE rad_id = '2F';  -- CT SCAN (terlalu-umum)
UPDATE rsmst_radiologis SET loinc_code = NULL, loinc_display = NULL WHERE rad_id = '2G';  -- KIRIM KE RAD LUAR (administratif)
UPDATE rsmst_radiologis SET loinc_code = NULL, loinc_display = NULL WHERE rad_id = '01';  -- RADIOLOGI LAIN-LAIN MADINAH (administratif)
UPDATE rsmst_radiologis SET loinc_code = NULL, loinc_display = NULL WHERE rad_id = '2E';  -- USG DI MADINAH (administratif)
UPDATE rsmst_radiologis SET loinc_code = NULL, loinc_display = NULL WHERE rad_id = '3A';  -- USG KE LUAR (administratif)

COMMIT;
