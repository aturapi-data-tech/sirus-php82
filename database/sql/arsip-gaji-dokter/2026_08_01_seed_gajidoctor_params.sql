-- database/sql/2026_08_01_seed_gajidoctor_params.sql
-- ===============================================================
-- ISI PARAMETER PENGGAJIAN PER DOKTER — hasil bedah 0726sp.xlsx
-- (periode jasa Juli 2026, dibayar Agustus 2026)
--
-- Menyusul 2026_08_01_table_gajidoctors.sql yang membuat kolomnya.
-- JALANKAN SETELAH DDL itu selesai.
--
-- CARA BACA ANGKA DI SINI:
--   Seluruh nilai diturunkan dari RUMUS di tiap sheet, bukan dari labelnya.
--   Label di workbook sering menyesatkan — contoh paling jelas dr. Komang
--   Resty (090): barisnya tertulis "Garanty Fee" padahal rumusnya menjumlah,
--   jadi skemanya 'A', bukan 'G'.
--
-- PEMETAAN NAMA: sheet Excel -> RSMST_DOCTORS.dr_id sudah dicocokkan lewat
--   nama pada sel B5 tiap sheet, BUKAN nama sheet-nya. Nama sheet warisan
--   lama dan sering tidak nyambung — sheet "dr jenar" isinya dr. Bambang
--   Suhadi SpKFR, sheet "dr.bernard" isinya dr. Kristina SpPK.
--   Beberapa ejaan di master beda tipis dengan di Excel:
--     Excel "Silvia Dohan Kartania, SpPD"  -> master "dr. Silvi Kartania D, SPPD"      (055)
--     Excel "Gaji dr.Ira, SpS"             -> master "dr. Dyah Irawati,SP.S"           (011)
--     Excel "dr. Wahyu Wibowo, SpA"        -> master "dr. Wahyu, Sp.A"                 (063)
--     Excel "dr. Tutit Lazuardi, SpOG"     -> master "dr. Tuttit Lazuardi, SP.OG"      (009)
--
-- YANG SENGAJA TIDAK DIISI:
--   1. basic_salary untuk 089 / 088 / 1113 (SpP, SpOT, Sp.M-Karina).
--      Ketiganya skema garanty fee, tapi sel gaji pokoknya KOSONG di workbook
--      sehingga rumus IF-nya selalu memilih jasa. Angkanya tidak diketahui —
--      dibiarkan apa adanya, jangan ditebak. Selama sel itu kosong, hasil
--      hitungnya sama saja dengan tanpa gaji pokok.
--   2. tunjangan_struktural / _fungsional / _hadir dan potongan_koperasi.
--      Kolomnya belum ada di database (menyusul di DDL). Di workbook pun
--      ketiga tunjangan itu nol di semua dokter.
--   3. potongan kasbon / "telah diambil" — nilainya berubah tiap bulan,
--      tempatnya di detail slip, bukan master.
--
-- CATATAN NILAI YANG PERLU DIKONFIRMASI KE KEUANGAN:
--   a. 010 (SpB) potongan_arisan = 700000 adalah GABUNGAN "Arisan/iuran kop"
--      di workbook — dua sel berbeda yang dijumlah jadi satu. Begitu kolom
--      potongan_koperasi ada, angka ini harus dipecah.
--   b. 010 (SpB) potongan_angsuran = 5326900 diambil dari angsuran Juli 2026.
--      Kalau ini cicilan yang nilainya tetap, biarkan; kalau berubah tiap
--      bulan, kosongkan dan isi per slip.
--   c. 107 (Sp.M-Tania) potongan_rs_aturan hanya memuat dua komponen. Rumus
--      aslinya menyebut empat, tapi dua sisanya menunjuk sel TANPA LABEL yang
--      kebetulan kosong (10% dan 65% atas sel entah apa). Tidak ditebak.
--   d. 009 (SpOG-Tutit) berstatus NON-AKTIF di master dan seluruh angkanya
--      nol pada Juli 2026. Parameternya tetap diisi supaya tidak jatuh ke
--      default 10% bila suatu saat diaktifkan lagi.
--
-- KONDISI AWAL (dicek langsung ke database sebelum skrip ini dijalankan):
--   Ke-19 baris TIDAK berisi NULL, melainkan sudah memegang nilai DEFAULT dari
--   DDL — skema 'A', basis 'T', 10%, PPh 2,5%, sisanya 0. Oracle 12c ke atas
--   memang menerapkan DEFAULT ke baris lama saat ADD COLUMN (metadata-only),
--   jadi tidak ada baris yang tertinggal NULL seperti dikhawatirkan di DDL.
--   basic_salary: NULL untuk semua, kecuali 098 dan 107 yang bernilai 0.
--
-- ROLLBACK — kembalikan ke default, bukan ke NULL:
--     UPDATE RSMST_DOCTORS
--        SET skema_gaji_pokok = 'A', potongan_rs_basis = 'T',
--            potongan_rs_persen = 10, potongan_rs_aturan = NULL,
--            pph21_persen = 2.5, tarif_per_kapita_ri = 0, tarif_per_kapita_rj = 0,
--            potongan_idi = 0, potongan_arisan = 0, potongan_angsuran = 0,
--            potongan_bpjs = 0, potongan_zariyah = 0
--      WHERE dr_id IN ('098','089','086','037','063','090','045','041','067',
--                      '010','055','106','011','082','085','088','107','1113','009');
--     UPDATE RSMST_DOCTORS SET basic_salary = 0 WHERE dr_id IN ('098','107');
--     UPDATE RSMST_DOCTORS SET basic_salary = NULL
--      WHERE dr_id IN ('086','063','090','045','041','055','106','011','082','085','067','037','010');
-- ===============================================================


-- ---------------------------------------------------------------
-- 1. DASAR UNTUK SEMUA DOKTER YANG ADA DI WORKBOOK
--    PPh 2,5% berlaku seragam — terbukti dari rumus inline dua sheet:
--    -(TOTAL + POTONGAN RS) * 0.5 * 0.05  =  50% NPPN x tarif 5%.
-- ---------------------------------------------------------------
UPDATE RSMST_DOCTORS SET pph21_persen = 2.5
 WHERE dr_id IN ('098','089','086','037','063','090','045','041','067',
                 '010','055','106','011','082','085','088','107','1113','009');


-- ---------------------------------------------------------------
-- 2. ADITIF + POTONGAN RS 10% DARI TOTAL GAJI  (pola mayoritas)
--    TOTAL = jasa + gaji pokok; potongan 10% x TOTAL.
-- ---------------------------------------------------------------
-- 086 dr. M.A. Budi Purwito, SpRAD
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'T',
       potongan_rs_persen = 10, basic_salary = 5000000
 WHERE dr_id = '086';

-- 063 dr. Wahyu, Sp.A  (Excel: dr. Wahyu Wibowo, SpA)
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'T',
       potongan_rs_persen = 10, basic_salary = 5000000, potongan_idi = 100000
 WHERE dr_id = '063';

-- 090 dr. Komang Resty PW, SpOG
-- Barisnya berlabel "Garanty Fee" tapi rumusnya D143 = B142 + B140 (menjumlah)
-- -> skema 'A'. Jangan tergoda mengisi 'G'.
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'T',
       potongan_rs_persen = 10, basic_salary = 5000000, potongan_idi = 100000
 WHERE dr_id = '090';

-- 045 dr. Predito Prihantoro, SpKJ
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'T',
       potongan_rs_persen = 10, basic_salary = 5000000
 WHERE dr_id = '045';

-- 041 dr. Herlin Kristanti, SP A
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'T',
       potongan_rs_persen = 10, basic_salary = 5000000
 WHERE dr_id = '041';

-- 055 dr. Silvi Kartania D, SPPD  (Excel: Silvia Dohan Kartania, SpPD)
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'T',
       potongan_rs_persen = 10, basic_salary = 5000000, potongan_idi = 100000
 WHERE dr_id = '055';

-- 106 dr. Muhammad Hamdan Yuwaafii, Sp.P.D — gaji pokok 7,5 juta
-- Di slip potongannya berlabel "Potongan BP", rumusnya sama: 10% x TOTAL.
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'T',
       potongan_rs_persen = 10, basic_salary = 7500000
 WHERE dr_id = '106';

-- 082 dr. Bambang Suhadi, SpKFR — dibayar per kunjungan RJ 65.000
-- (G76 = F76 * 65000, label "Uang periksa", blok Rawat Jalan)
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'T',
       potongan_rs_persen = 10, basic_salary = 5000000, tarif_per_kapita_rj = 65000
 WHERE dr_id = '082';


-- ---------------------------------------------------------------
-- 3. ADITIF + GAJI POKOK BEBAS POTONGAN  (basis 'J')
--    Rumusnya eksplisit memotong (TOTAL - GAJI POKOK).
-- ---------------------------------------------------------------
-- 010 dr. M Yogiyo Pranoto, SP.B
-- potongan_arisan = gabungan "Arisan/iuran kop" (lihat catatan (a) di atas)
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'J',
       potongan_rs_persen = 10, basic_salary = 5000000,
       potongan_arisan = 700000, potongan_angsuran = 5326900
 WHERE dr_id = '010';

-- 011 dr. Dyah Irawati, SP.S  (Excel: Gaji dr.Ira, SpS) — gaji pokok 2,5 juta
-- Rumus: -(B138 - B137) * 10%  =  -(TOTAL - GAJI POKOK) * 10%
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'J',
       potongan_rs_persen = 10, basic_salary = 2500000, potongan_zariyah = 25000
 WHERE dr_id = '011';

-- 085 dr. Nanda Agus Prasetya, SpOG — gaji pokok 6 juta
-- Rumus: -(B133+B134+B135+B136) * 10% -> hanya komponen jasa, pokok bebas.
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'J',
       potongan_rs_persen = 10, basic_salary = 6000000,
       potongan_idi = 100000, potongan_bpjs = 127600
 WHERE dr_id = '085';


-- ---------------------------------------------------------------
-- 4. TANPA POTONGAN RS  (basis 'N')
-- ---------------------------------------------------------------
-- 098 dr. Kristina Dyah L, SpPK — dibayar per pasien RI 40.000
-- (H10 = 40000 * D10, label "Konsul pasien total", blok Rawat Inap)
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'N',
       potongan_rs_persen = 0, basic_salary = 5000000, tarif_per_kapita_ri = 40000
 WHERE dr_id = '098';

-- 037 dr. Johan, SPAN — gaji pokok 10 juta, sel persen potongan kosong
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'N',
       potongan_rs_persen = 0, basic_salary = 10000000, potongan_idi = 100000
 WHERE dr_id = '037';


-- ---------------------------------------------------------------
-- 5. GARANTY FEE  (skema 'G')
--    Rumus: IF(jasa > gaji pokok, jasa, gaji pokok) = GREATEST(...).
--    PENTING: basis potongannya tetap 'T' — dipotong dari HASIL garanty,
--    jadi gaji pokok ikut terpotong. Memakai 'J' di sini membuat gaji
--    dr. Jimmy Akbar meleset Rp3,28 juta.
--    basic_salary sengaja tidak disentuh, lihat catatan 1 di kepala berkas.
-- ---------------------------------------------------------------
-- 089 Dr. Jimmy Akbar, SpP
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'G', potongan_rs_basis = 'T',
       potongan_rs_persen = 10
 WHERE dr_id = '089';

-- 067 dr. Adriyawan W.N., SPJP — di sini gaji pokoknya TERBACA: 5 juta
-- (B142 = jasa - B139 -> 31.750.000 = 36.750.000 - 5.000.000)
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'G', potongan_rs_basis = 'T',
       potongan_rs_persen = 10, basic_salary = 5000000
 WHERE dr_id = '067';

-- 088 Dr. Deny Mory Aryawan, SpOT
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'G', potongan_rs_basis = 'T',
       potongan_rs_persen = 10
 WHERE dr_id = '088';

-- 1113 dr. Karina Rakhma Meutia, Sp.M
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'G', potongan_rs_basis = 'T',
       potongan_rs_persen = 10
 WHERE dr_id = '1113';


-- ---------------------------------------------------------------
-- 6. POTONGAN BERJENJANG PER KOMPONEN  (basis 'B')
-- ---------------------------------------------------------------
-- 107 dr. Tania Rahmania Maulani Sp.M
-- Rumus asli: -((G59*10%) + (G60*50%) + (G62*10%) + (G63*65%))
--   G59 = "Uang Periksa poli"  -> UP RJ 10%, TERPAKAI (12.130.000 -> 1.213.000)
--   G60 = "Jasa Tindakan/ OK"  -> JD RJ 50%, TIDAK TERPAKAI: G60 KOSONG.
--        Angka jasa tindakannya (31.712.220) sebenarnya ada di kolom I, bukan
--        G, sehingga rumus menunjuk sel kosong dan komponen ini tidak pernah
--        ikut memotong.
--   G62, G63 = sel TANPA LABEL & kosong -> tidak ditebak (catatan (c)).
--
-- YANG DIISI DI SINI HANYA 'UP RJ'. Alasannya konsekuensi uang:
--   dengan {"UP RJ":10}            -> potongan 1.213.000  (sama dengan yang
--                                     benar-benar dibayarkan Juli 2026)
--   dengan {"UP RJ":10,"JD RJ":50} -> potongan 17.109.110, gaji diterima turun
--                                     ~Rp15,4 juta dari slip aslinya.
-- Maksud penyusun rumus mungkin memang 50% atas jasa tindakan, tapi itu tidak
-- pernah terjadi. Seed ini mereproduksi yang DIBAYARKAN, bukan yang diduga
-- dimaksud. TANYAKAN ke keuangan sebelum menambahkan "JD RJ":50.
--
-- potongan_rs_persen dinolkan supaya jelas yang dipakai kolom aturan.
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'A', potongan_rs_basis = 'B',
       potongan_rs_persen = 0, basic_salary = 5000000,
       potongan_rs_aturan = '{"UP RJ":10}'
 WHERE dr_id = '107';


-- ---------------------------------------------------------------
-- 7. POTONGAN RS 5%  — bukti bahwa 10% itu kelaziman, bukan aturan
-- ---------------------------------------------------------------
-- 009 dr. Tuttit Lazuardi, SP.OG (NON-AKTIF di master, lihat catatan (d))
-- Sheet-nya memuat rumus PPh inline: -(D121 + H115) * 0.5 * 0.05
UPDATE RSMST_DOCTORS SET skema_gaji_pokok = 'N', potongan_rs_basis = 'T',
       potongan_rs_persen = 5
 WHERE dr_id = '009';

COMMIT;


-- ---------------------------------------------------------------
-- 8. VERIFIKASI
-- ---------------------------------------------------------------
-- SELECT dr_id, dr_name, skema_gaji_pokok, potongan_rs_basis, potongan_rs_persen,
--        pph21_persen, basic_salary, tarif_per_kapita_ri, tarif_per_kapita_rj,
--        potongan_idi, potongan_arisan, potongan_angsuran, potongan_bpjs,
--        potongan_zariyah, potongan_rs_aturan
--   FROM RSMST_DOCTORS
--  WHERE dr_id IN ('098','089','086','037','063','090','045','041','067',
--                  '010','055','106','011','082','085','088','107','1113','009')
--  ORDER BY dr_id;
--
-- Dokter di luar daftar itu memegang nilai DEFAULT dari DDL (A / T / 10% /
-- 2,5%), bukan parameter hasil kesepakatan. Sisir ulang bila mereka mulai
-- digaji lewat modul ini.
--
-- ---------------------------------------------------------------
-- 9. HASIL UJI ULANG TERHADAP SLIP JULI 2026
--
--    Parameter di atas dijalankan lewat rumus induk lalu dibandingkan dengan
--    kolom "Gaji Diterima" tiap sheet:
--
--      18 dari 19 dokter COCOK SAMPAI RUPIAH.
--
--    Satu-satunya sisa selisih ada di 107 (Sp.M-Tania): model 46.901.615 vs
--    slip 46.840.965, beda Rp60.650. Itu PERSIS cacat tanda pada workbook
--    pajak eksternal yang sudah dicatat di 2026_08_01_table_gajidoctors.sql
--    bagian 5.b — basis PPh-nya menambahkan potongan RS, bukan mengurangi.
--    Model di sini memakai tanda yang benar, jadi selisih ini disengaja dan
--    justru menguntungkan dokter yang bersangkutan.
-- ---------------------------------------------------------------
