-- database/sql/2026_08_01_view_tkview_accounts_ok_rj_ugd.sql
-- ===============================================================
-- TKVIEW_ACCOUNTS — jurnal pendapatan Kamar Operasi untuk RJ & UGD
--
-- MASALAH:
--   View jurnal ini SUDAH punya 11 pos Kamar Operasi (conf_id OK1..OK11 ->
--   akun 4.1F01..4.1F11), tapi ke-22 cabangnya membaca:
--       from RSTXN_OKS where rihdr_no = a.rihdr_no ... FROM RSTXN_RIHDRS
--   Untuk operasi RJ/UGD kolom `rihdr_no` sengaja NULL (kunjungan induknya
--   ditunjuk status_rjri + ref_no), jadi cabang-cabang itu TIDAK PERNAH
--   menjaringnya, dan tidak ada padanan RJ/UGD.
--
--   Akibatnya: kasir menagih biaya operasi dan pembayarannya mengkredit
--   piutang RJ1/UGD1 secara penuh, tetapi sisi PENDAPATAN-nya tidak pernah
--   diakui. Diukur pada satu kunjungan uji: tagihan Rp 1.115.000, yang
--   terjurnal hanya Rp 179.000 — selisih Rp 936.000 persis nilai operasinya.
--
-- PERUBAHAN:
--   Menambah 44 cabang (11 pos x 2 arah x 2 unit). Cabang lama TIDAK disentuh.
--   Sumbernya RSTXN_OKS dengan kunci baru (status_rjri + ref_no), memakai
--   PEMETAAN POS YANG SAMA PERSIS dengan cabang RI yang sudah ada:
--
--     oprdoc_fee->OK1  asistopr_fee->OK2   anesdoc_fee->OK3
--     changeanesdoc_fee->OK4  asistanes_fee->OK5  instrument_fee->OK6
--     omlop_fee->OK7   rr_fee->OK8   ok_fee->OK9
--     equipment_fee->OK10   rentequipment_fee->OK11
--
--   Lawan piutangnya RJ1 (4.1AA) untuk RJ dan UGD1 (4.1BB) untuk UGD,
--   sejalan dengan seluruh pos RJ/UGD lain.
--
-- AKUN PENDAPATAN DIPAKAI BERSAMA (4.1F01..4.1F11):
--   Akun-akun itu dinamai per PERAN (OPERATOR, ANASTESI, INSTRUMENT, ...),
--   bukan per unit asal — jadi dipakai bersama RI. Kalau nanti pendapatan
--   operasi ingin dipisah per unit, yang diubah cukup conf_id di cabang baru
--   (mis. OKRJ1..OKRJ11) plus barisnya di TKACC_CONFACCTXNS; struktur view
--   tidak perlu disentuh.
--
-- EXISTS DI TIAP CABANG BARU — JANGAN DIHAPUS:
--   Cabang lama menerbitkan satu baris untuk SETIAP kunjungan walau nilainya
--   nol. Kalau pola itu ditiru, view membengkak 26 juta -> 44 juta baris hanya
--   karena RJ/UGD punya ratusan ribu kunjungan. Dengan
--       and exists (select 1 from RSTXN_OKS x where ... x.ok_status = 'L')
--   jumlah baris kembali persis 26.102.285 seperti semula.
--
-- SUDAH DIUJI (view bayangan ZZ_UJI_TKVIEW, lalu dibuang):
--   - status VALID
--   - jumlah baris & total D/K seluruh data IDENTIK dengan view lama
--   - saldo 19 akun kas di halaman Cek Saldo Kas IDENTIK
--   - satu operasi RJ uji (rollback): pendapatan terjurnal naik dari
--     Rp 179.000 menjadi Rp 1.115.000 = tagihan, selisih 0, tersebar benar
--     ke 4.1F01/F02/F03/F05/F06/F07/F08/F09/F11
--
-- CARA PAKAI: jalankan seluruh isi file (satu CREATE OR REPLACE VIEW).
-- ROLLBACK  : definisi lama ada di  SELECT text FROM user_views
--             WHERE view_name = 'TKVIEW_ACCOUNTS';  (ambil SEBELUM dijalankan)
-- ===============================================================

CREATE OR REPLACE VIEW TKVIEW_ACCOUNTS (TXN_NAME, TXN_ACC, TXN_ACC_K, SHIFT, TXN_DATE, TXN_D, TXN_K) AS
(
-------TU CI---------------
SELECT 'CI'||' '||tucashk_desc||'('||tucashk_no||')',acc_id_kas,acc_id,nvl(shift,'1'),tucashk_date,tucashk_nominal,0
FROM RSTXN_TUCASHDS a WHERE tucashk_status='L'
union all
SELECT 'CI'||' '||tucashk_desc||'('||tucashk_no||')',acc_id,acc_id_kas,nvl(shift,'1'),tucashk_date,0,tucashk_nominal
FROM RSTXN_TUCASHDS a WHERE tucashk_status='L'
---------------------------
union all
-------TU CO---------------
SELECT 'CO'||' '||tucashk_desc||'('||tucashk_no||')',acc_id_kas,acc_id,nvl(shift,'1'),tucashk_date,0,tucashk_nominal
FROM RSTXN_TUCASHKS a WHERE tucashk_status='L'
union all
SELECT 'CO'||' '||tucashk_desc||'('||tucashk_no||')',acc_id,acc_id_kas,nvl(shift,'1'),tucashk_date,tucashk_nominal,0
FROM RSTXN_TUCASHKS a WHERE tucashk_status='L'
---------------------------
union all
-------RJ---------------
-------RJ_ADMIN to PIUTANG---------------
SELECT 'RJ_ADMIN ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ3'),
nvl(shift,'1'),rj_date,rj_admin,0
FROM RSTXN_RJHDRS a WHERE rj_status not in('A','F')
union all
SELECT 'RJ_ADMIN ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ3'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(shift,'1'),rj_date,0,rj_admin
FROM RSTXN_RJHDRS a WHERE rj_status not in('A','F')
---------------------------
union all
-------RS_ADMIN to PIUTANG---------------
SELECT 'RS_ADMIN ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ2'),
nvl(shift,'1'),rj_date,rs_admin,0
FROM RSTXN_RJHDRS a WHERE rj_status not in('A','F')
union all
SELECT 'RS_ADMIN ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ2'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(shift,'1'),rj_date,0,rs_admin
FROM RSTXN_RJHDRS a WHERE rj_status not in('A','F')
---------------------------
union all
-------UP to PIUTANG---------------
SELECT 'UP ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ11'),
nvl(shift,'1'),rj_date,poli_price,0
FROM RSTXN_RJHDRS a WHERE rj_status not in('A','F')
union all
SELECT 'UP ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ11'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(shift,'1'),rj_date,0,poli_price
FROM RSTXN_RJHDRS a WHERE rj_status not in('A','F')
---------------------------
union all
-------JD to PIUTANG---------------
SELECT 'JD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ4'),
nvl(shift,'1'),rj_date,(select sum(accdoc_price) from RSTXN_RJACCDOCS G where G.rj_no=a.rj_no),0
FROM RSTXN_RJHDRS a WHERE rj_status not in('A','F')
union all
SELECT 'JD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ4'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(shift,'1'),rj_date,0,(select sum(accdoc_price) from RSTXN_RJACCDOCS G where G.rj_no=a.rj_no)
FROM RSTXN_RJHDRS a WHERE rj_status not in('A','F')
---------------------------
union all
-------JM to PIUTANG---------------
SELECT 'JM ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ5'),
nvl(shift,'1'),rj_date,(select sum(pact_price) from RSTXN_RJACTPARAMS G where G.rj_no=a.rj_no),0
FROM RSTXN_RJHDRS a WHERE rj_status not in('A','F')
union all
SELECT 'JM ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ5'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(shift,'1'),rj_date,0,(select sum(pact_price) from RSTXN_RJACTPARAMS G where G.rj_no=a.rj_no)
FROM RSTXN_RJHDRS a WHERE rj_status not in('A','F')
---------------------------
union all
-------JK to PIUTANG---------------
SELECT 'JK ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ6'),
nvl(shift,'1'),rj_date,(select sum(acte_price) from RSTXN_RJACTEMPS G where G.rj_no=a.rj_no),0
FROM RSTXN_RJHDRS a WHERE rj_status not in('A','F')
union all
SELECT 'JK ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ6'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(shift,'1'),rj_date,0,(select sum(acte_price) from RSTXN_RJACTEMPS G where G.rj_no=a.rj_no)
FROM RSTXN_RJHDRS a WHERE rj_status not in('A','F')
---------------------------
union all
-------OBAT to PIUTANG---------------
SELECT 'OBAT ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ9'),
nvl(shift,'1'),rj_date,(select sum((NVL(qty,0)*NVL(price,0))) from RSTXN_RJOBATS G where G.rj_no=a.rj_no),0
FROM RSTXN_RJHDRS a WHERE rj_status not in('A','F')
union all
SELECT 'OBAT ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ9'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(shift,'1'),rj_date,0,(select sum((NVL(qty,0)*NVL(price,0))) from RSTXN_RJOBATS G where G.rj_no=a.rj_no)
FROM RSTXN_RJHDRS a WHERE rj_status not in('A','F')
---------------------------
union all
-------LAB to PIUTANG---------------
SELECT 'LAB ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ7'),
nvl(shift,'1'),rj_date,(select sum(lab_price) from RSTXN_RJLABS G where G.rj_no=a.rj_no),0
FROM RSTXN_RJHDRS a WHERE rj_status not in('A','F')
union all
SELECT 'LAB ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ7'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(shift,'1'),rj_date,0,(select sum(lab_price) from RSTXN_RJLABS G where G.rj_no=a.rj_no)
FROM RSTXN_RJHDRS a WHERE rj_status not in('A','F')
---------------------------
union all
-------RAD to PIUTANG---------------
SELECT 'RAD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ10'),
nvl(shift,'1'),rj_date,(select sum(rad_price) from RSTXN_RJRADS G where G.rj_no=a.rj_no),0
FROM RSTXN_RJHDRS a WHERE rj_status not in('A','F')
union all
SELECT 'RAD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ10'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(shift,'1'),rj_date,0,(select sum(rad_price) from RSTXN_RJRADS G where G.rj_no=a.rj_no)
FROM RSTXN_RJHDRS a WHERE rj_status not in('A','F')
---------------------------
union all
-------LAIN to PIUTANG---------------
SELECT 'LAIN ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ8'),
nvl(shift,'1'),rj_date,(select sum(other_price) from RSTXN_RJOTHERS G where G.rj_no=a.rj_no),0
FROM RSTXN_RJHDRS a WHERE rj_status not in('A','F')
union all
SELECT 'LAIN ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ8'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(shift,'1'),rj_date,0,(select sum(other_price) from RSTXN_RJOTHERS G where G.rj_no=a.rj_no)
FROM RSTXN_RJHDRS a WHERE rj_status not in('A','F')
---------------------------
union all
-------DISKON to PIUTANG---------------
SELECT 'RJ_DISKON ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ12'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(shift,'1'),rj_date,rj_diskon,0
FROM RSTXN_RJHDRS a WHERE rj_status not in('A','F')
union all
SELECT 'RJ_DISKON ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ12'),
nvl(shift,'1'),rj_date,0,rj_diskon
FROM RSTXN_RJHDRS a WHERE rj_status not in('A','F')
---------------------------
union all
-------BAYAR to PIUTANG---------------
SELECT 'BAYAR_RJ ('||a.rjc_desc||')',
a.acc_id,
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(a.shift,'1'),rjc_date,rjc_nominal,0
FROM RSTXN_RJCASHINS a,rstxn_rjhdrs b
WHERE a.rj_no=b.rj_no
and rj_status not in('A','F')
union all
SELECT 'BAYAR_RJ ('||a.rjc_desc||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
a.acc_id,
nvl(a.shift,'1'),rjc_date,0,rjc_nominal
FROM RSTXN_RJCASHINS a,rstxn_rjhdrs b
WHERE a.rj_no=b.rj_no
and rj_status not in('A','F')
---------------------------
union all
-------UGD---------------
-------UGD_ADMIN to PIUTANG---------------
SELECT 'UGD_ADMIN ('||a.RJ_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD3'),
nvl(shift,'1'),RJ_date,RJ_admin,0
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
union all
SELECT 'UGD_ADMIN ('||a.RJ_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD3'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(shift,'1'),RJ_date,0,RJ_admin
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
---------------------------
union all
-------RS_ADMIN to PIUTANG---------------
SELECT 'RS_ADMIN ('||a.RJ_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD2'),
nvl(shift,'1'),RJ_date,rs_admin,0
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
union all
SELECT 'RS_ADMIN ('||a.RJ_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD2'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(shift,'1'),RJ_date,0,rs_admin
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
---------------------------
union all
-------UP to PIUTANG---------------
SELECT 'UP ('||a.RJ_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD11'),
nvl(shift,'1'),RJ_date,poli_price,0
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
union all
SELECT 'UP ('||a.RJ_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD11'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(shift,'1'),RJ_date,0,poli_price
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
---------------------------
union all
-------JD to PIUTANG---------------
SELECT 'JD ('||a.RJ_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD4'),
nvl(shift,'1'),RJ_date,(select sum(accdoc_price) from RSTXN_UGDACCDOCS G where G.RJ_no=a.RJ_no),0
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
union all
SELECT 'JD ('||a.RJ_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD4'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(shift,'1'),RJ_date,0,(select sum(accdoc_price) from RSTXN_UGDACCDOCS G where G.RJ_no=a.RJ_no)
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
---------------------------
union all
-------JM to PIUTANG---------------
SELECT 'JM ('||a.RJ_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD5'),
nvl(shift,'1'),RJ_date,(select sum(pact_price) from RSTXN_UGDACTPARAMS G where G.RJ_no=a.RJ_no),0
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
union all
SELECT 'JM ('||a.RJ_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD5'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(shift,'1'),RJ_date,0,(select sum(pact_price) from RSTXN_UGDACTPARAMS G where G.RJ_no=a.RJ_no)
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
---------------------------
union all
-------JK to PIUTANG---------------
SELECT 'JK ('||a.RJ_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD6'),
nvl(shift,'1'),RJ_date,(select sum(acte_price) from RSTXN_UGDACTEMPS G where G.RJ_no=a.RJ_no),0
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
union all
SELECT 'JK ('||a.RJ_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD6'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(shift,'1'),RJ_date,0,(select sum(acte_price) from RSTXN_UGDACTEMPS G where G.RJ_no=a.RJ_no)
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
---------------------------
union all
-------OBAT to PIUTANG---------------
SELECT 'OBAT ('||a.RJ_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD9'),
nvl(shift,'1'),RJ_date,(select sum((NVL(qty,0)*NVL(price,0))) from RSTXN_UGDOBATS G where G.RJ_no=a.RJ_no),0
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
union all
SELECT 'OBAT ('||a.RJ_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD9'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(shift,'1'),RJ_date,0,(select sum((NVL(qty,0)*NVL(price,0))) from RSTXN_UGDOBATS G where G.RJ_no=a.RJ_no)
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
---------------------------
union all
-------LAB to PIUTANG---------------
SELECT 'LAB ('||a.RJ_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD7'),
nvl(shift,'1'),RJ_date,(select sum(lab_price) from RSTXN_UGDLABS G where G.RJ_no=a.RJ_no),0
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
union all
SELECT 'LAB ('||a.RJ_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD7'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(shift,'1'),RJ_date,0,(select sum(lab_price) from RSTXN_UGDLABS G where G.RJ_no=a.RJ_no)
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
---------------------------
union all
-------RAD to PIUTANG---------------
SELECT 'RAD ('||a.RJ_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD10'),
nvl(shift,'1'),RJ_date,(select sum(rad_price) from RSTXN_UGDRADS G where G.RJ_no=a.RJ_no),0
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
union all
SELECT 'RAD ('||a.RJ_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD10'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(shift,'1'),RJ_date,0,(select sum(rad_price) from RSTXN_UGDRADS G where G.RJ_no=a.RJ_no)
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
---------------------------
union all
-------LAIN to PIUTANG---------------
SELECT 'LAIN ('||a.RJ_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD8'),
nvl(shift,'1'),RJ_date,(select sum(other_price) from RSTXN_UGDOTHERS G where G.RJ_no=a.RJ_no),0
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
union all
SELECT 'LAIN ('||a.RJ_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD8'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(shift,'1'),RJ_date,0,(select sum(other_price) from RSTXN_UGDOTHERS G where G.RJ_no=a.RJ_no)
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
---------------------------
union all
-------DISKON to PIUTANG---------------
SELECT 'UGD_DISKON ('||a.RJ_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD12'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(shift,'1'),RJ_date,RJ_diskon,0
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
union all
SELECT 'UGD_DISKON ('||a.RJ_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD12'),
nvl(shift,'1'),RJ_date,0,RJ_diskon
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
---------------------------
union all
-------BAYAR to PIUTANG---------------
SELECT 'BAYAR_UGD ('||a.RJc_desc||')',
a.acc_id,
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(a.shift,'1'),rjc_date,rjc_nominal,0
FROM RSTXN_UGDCASHINS a,rstxn_UGDhdrs b
WHERE a.RJ_no=b.RJ_no
and RJ_status not in('A','F')
union all
SELECT 'BAYAR_UGD ('||a.RJC_desc||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
a.acc_id,
nvl(a.shift,'1'),RJc_date,0,RJc_nominal
FROM RSTXN_UGDCASHINS a,rstxn_UGDhdrs b
WHERE a.RJ_no=b.RJ_no
and RJ_status not in('A','F')
---------------------------
union all
-------RESEP---------------
-------JK---------------
SELECT 'RESEP JK ('||a.sls_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RESEP1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RESEP2'),
nvl(shift,'1'),sls_date,acte_price,0
FROM IMTXN_SLSHDRS a WHERE status ='L'
union all
SELECT 'RESEP JK ('||a.sls_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RESEP2'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RESEP1'),
nvl(shift,'1'),sls_date,0,acte_price
FROM IMTXN_SLSHDRS a WHERE status ='L'
---------------------------
union all
-------OBAT---------------
SELECT 'RESEP OBAT ('||a.sls_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RESEP1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RESEP3'),
nvl(shift,'1'),sls_date,(select sum(nvl(qty,0)*nvl(sales_price,0)) from IMTXN_SLSDTLS where sls_no=a.sls_no),0
FROM IMTXN_SLSHDRS a WHERE status ='L'
union all
SELECT 'RESEP OBAT ('||a.sls_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RESEP3'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RESEP1'),
nvl(shift,'1'),sls_date,0,(select sum(nvl(qty,0)*nvl(sales_price,0)) from IMTXN_SLSDTLS where sls_no=a.sls_no)
FROM IMTXN_SLSHDRS a WHERE status ='L'
---------------------------
union all
-------BAYAR to PIUTANG langsung---------------
SELECT 'BAYAR_RESEP ('||a.sls_no||' '||a.reg_no||')',
a.acc_id,
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RESEP1'),
nvl(a.shift,'1'),sls_date,sls_bayar,0
FROM IMTXN_SLSHDRS a WHERE status ='L'
union all
SELECT 'BAYAR_RESEP ('||a.sls_no||' '||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RESEP1'),
a.acc_id,
nvl(a.shift,'1'),sls_date,0,sls_bayar
FROM IMTXN_SLSHDRS a WHERE status ='L'
---------------------------
union all
-------RESEP to PIUTANG transfer ke inap---------------
SELECT 'TRF PIUTANG RESEP ke INAP ('||(select string_agg(sls_no||' '||reg_no) from imtxn_slshdrs where rihdr_no=a.rihdr_no)||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RESEPTRFINAP'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RESEP1'),
nvl(a.shift,'1'),exit_date,(select sum(nvl(ribon_price,0)) from RSTXN_RIBONOBATS where rihdr_no=a.rihdr_no),0
FROM RSTXN_RIHDRS a WHERE ri_status ='P'
union all
SELECT 'TRF PIUTANG RESEP ke INAP ('||(select string_agg(sls_no||' '||reg_no) from imtxn_slshdrs where rihdr_no=a.rihdr_no)||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RESEP1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RESEPTRFINAP'),
nvl(a.shift,'1'),exit_date,0,(select sum(nvl(ribon_price,0)) from RSTXN_RIBONOBATS where rihdr_no=a.rihdr_no)
FROM RSTXN_RIHDRS a WHERE ri_status ='P'
---------------------------
union all
-------UGD to PIUTANG transfer ke inap---------------
SELECT 'TRF PIUTANG UGD ke INAP ('||(select string_agg(sls_no||' '||reg_no) from imtxn_slshdrs where rihdr_no=a.rihdr_no)||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGDTRFINAP'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(a.shift,'1'),exit_date,(select nvl(sum(nvl(rj_admin,0)+
    nvl(poli_PRICE,0)+
    nvl(acte_price,0)+
    nvl(actp_price,0)+
    nvl(actd_price,0)+
    nvl(obat,0)+
    nvl(rad,0)+
    nvl(lab,0)+
    nvl(other,0)+nvl(rs_admin,0)),0) from RSTXN_RITEMPADMINS where rihdr_no=a.rihdr_no),0
FROM RSTXN_RIHDRS a WHERE ri_status ='P'
union all
SELECT 'TRF PIUTANG UGD ke INAP ('||(select string_agg(sls_no||' '||reg_no) from imtxn_slshdrs where rihdr_no=a.rihdr_no)||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGDTRFINAP'),
nvl(a.shift,'1'),exit_date,0,(select nvl(sum(nvl(rj_admin,0)+
    nvl(poli_PRICE,0)+
    nvl(acte_price,0)+
    nvl(actp_price,0)+
    nvl(actd_price,0)+
    nvl(obat,0)+
    nvl(rad,0)+
    nvl(lab,0)+
    nvl(other,0)+nvl(rs_admin,0)),0) from RSTXN_RITEMPADMINS where rihdr_no=a.rihdr_no)
FROM RSTXN_RIHDRS a WHERE ri_status ='P'
---------------------------
union all
-------RJ to PIUTANG transfer ke UGD---------------
SELECT 'TRF PIUTANG RJ ke UGD ('||(select rj_no||' '||reg_no from RSTXN_UGDBIAYASELAMADIRJS where rj_no_rsugd=a.rj_no)||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJTRFUGD'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(a.shift,'1'),rj_date,(select nvl(sum(total_biayarj),0) from RSTXN_UGDBIAYASELAMADIRJS where rj_no_rsugd=a.rj_no),0
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
union all
SELECT 'TRF PIUTANG RJ ke UGD ('||(select rj_no||' '||reg_no from RSTXN_UGDBIAYASELAMADIRJS where rj_no_rsugd=a.rj_no)||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJTRFUGD'),
nvl(a.shift,'1'),rj_date,0,(select nvl(sum(total_biayarj),0) from RSTXN_UGDBIAYASELAMADIRJS where rj_no_rsugd=a.rj_no)
FROM RSTXN_UGDHDRS a WHERE RJ_status not in('A','F')
---------------------------
union all
-------RI---------------
-------ADMIN AGE to PIUTANG---------------
SELECT 'RI ADMIN AGE ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI2'),
nvl(shift,'1'),exit_date,admin_age,0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'RI ADMIN AGE ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI2'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,admin_age
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------ADMIN STATUS to PIUTANG---------------
SELECT 'RI ADMIN STATUS ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI3'),
nvl(shift,'1'),exit_date,admin_status,0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'RI ADMIN STATUS ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI3'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,admin_status
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------JD to PIUTANG---------------
SELECT 'RI JD ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI4'),
nvl(shift,'1'),exit_date,(select nvl(sum(actd_price*actd_qty),0) from rstxn_riactdocs where rihdr_no=a.rihdr_no),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'RI JD ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI4'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,(select nvl(sum(actd_price*actd_qty),0) from rstxn_riactdocs where rihdr_no=a.rihdr_no)
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------JM to PIUTANG---------------
SELECT 'RI JM ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI5'),
nvl(shift,'1'),exit_date,(select nvl(sum(actp_price*actp_qty),0) from rstxn_riactparams where rihdr_no=a.rihdr_no),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'RI JM ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI5'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,(select nvl(sum(actp_price*actp_qty),0) from rstxn_riactparams where rihdr_no=a.rihdr_no)
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------VISIT to PIUTANG---------------
SELECT 'RI VISIT ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI6'),
nvl(shift,'1'),exit_date,(select nvl(sum(visit_price),0) from rstxn_rivisits where rihdr_no=a.rihdr_no),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'RI VISIT ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI6'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,(select nvl(sum(visit_price),0) from rstxn_rivisits where rihdr_no=a.rihdr_no)
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------KONSUL to PIUTANG---------------
SELECT 'RI KONSUL ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI7'),
nvl(shift,'1'),exit_date,(select nvl(sum(konsul_price),0) from rstxn_rikonsuls where rihdr_no=a.rihdr_no),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'RI KONSUL ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI7'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,(select nvl(sum(konsul_price),0) from rstxn_rikonsuls where rihdr_no=a.rihdr_no)
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------LAB to PIUTANG---------------
SELECT 'RI LAB ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI8'),
nvl(shift,'1'),exit_date,(select nvl(sum(lab_price),0) from rstxn_rilabs where rihdr_no=a.rihdr_no),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'RI LAB ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI8'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,(select nvl(sum(lab_price),0) from rstxn_rilabs where rihdr_no=a.rihdr_no)
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------RAD to PIUTANG---------------
SELECT 'RI RAD ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI9'),
nvl(shift,'1'),exit_date,(select nvl(sum(rirad_price),0) from rstxn_riradiologs where rihdr_no=a.rihdr_no),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'RI RAD ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI9'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,(select nvl(sum(rirad_price),0) from rstxn_riradiologs where rihdr_no=a.rihdr_no)
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------OBAT to PIUTANG---------------
SELECT 'RI OBAT ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI10'),
nvl(shift,'1'),exit_date,(select nvl(sum(riobat_qty*riobat_price),0) from rstxn_riobats where rihdr_no=a.rihdr_no),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'RI OBAT ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI10'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,(select nvl(sum(riobat_qty*riobat_price),0) from rstxn_riobats where rihdr_no=a.rihdr_no)
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------PERAWATAN to PIUTANG---------------
SELECT 'RI PERAWATAN ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI11'),
nvl(shift,'1'),exit_date,(select sum(nvl(perawatan_price,0)*nvl(DAY, ceil(decode((nvl(end_date,sysdate)-start_date),0,1,(nvl(end_date,sysdate)-start_date))))  ) from rsmst_trfrooms where rihdr_no=a.rihdr_no),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'RI PERAWATAN ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI11'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,(select sum(nvl(perawatan_price,0)*nvl(DAY, ceil(decode((nvl(end_date,sysdate)-start_date),0,1,(nvl(end_date,sysdate)-start_date))))  ) from rsmst_trfrooms where rihdr_no=a.rihdr_no)
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------KAMAR to PIUTANG---------------
SELECT 'RI KAMAR ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI12'),
nvl(shift,'1'),exit_date,(select sum(nvl(room_price,0)*nvl(DAY, ceil(decode((nvl(end_date,sysdate)-start_date),0,1,(nvl(end_date,sysdate)-start_date))))  ) from rsmst_trfrooms where rihdr_no=a.rihdr_no),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'RI KAMAR ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI12'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,(select sum(nvl(room_price,0)*nvl(DAY, ceil(decode((nvl(end_date,sysdate)-start_date),0,1,(nvl(end_date,sysdate)-start_date))))  ) from rsmst_trfrooms where rihdr_no=a.rihdr_no)
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------PELAYANAN UMUM to PIUTANG---------------
SELECT 'RI PELAYANAN UMUM ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI13'),
nvl(shift,'1'),exit_date,(select sum(nvl(common_service,0)*nvl(DAY, ceil(decode((nvl(end_date,sysdate)-start_date),0,1,(nvl(end_date,sysdate)-start_date))))  ) from rsmst_trfrooms where rihdr_no=a.rihdr_no),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'RI PELAYANAN UMUM ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI13'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,(select sum(nvl(common_service,0)*nvl(DAY, ceil(decode((nvl(end_date,sysdate)-start_date),0,1,(nvl(end_date,sysdate)-start_date))))  ) from rsmst_trfrooms where rihdr_no=a.rihdr_no)
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------LAIN to PIUTANG---------------
SELECT 'RI LAIN ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI14'),
nvl(shift,'1'),exit_date,(select nvl(sum(OTHER_PRICE),0) from RSTXN_RIOTHERS where rihdr_no=a.rihdr_no),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'RI LAIN ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI14'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,(select nvl(sum(OTHER_PRICE),0) from RSTXN_RIOTHERS where rihdr_no=a.rihdr_no)
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------SUBSIDI to PIUTANG---------------
SELECT 'SUBSIDI ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI16'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,ri_diskon,0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'SUBSIDI ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI16'),
nvl(shift,'1'),exit_date,0,ri_diskon
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------OK----------------------------
-------OPERATOR to PIUTANG---------------
SELECT 'OPERATOR ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK1'),
nvl(shift,'1'),exit_date,(select nvl(sum(oprdoc_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status='L'),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'OPERATOR ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,(select nvl(sum(oprdoc_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status='L')
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------ASIS OPERATOR to PIUTANG---------------
SELECT 'ASIS OPERATOR ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK2'),
nvl(shift,'1'),exit_date,(select nvl(sum(asistopr_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status='L'),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'ASIS OPERATOR ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK2'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,(select nvl(sum(asistopr_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status='L')
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------ANASTESI to PIUTANG---------------
SELECT 'ANASTESI ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK3'),
nvl(shift,'1'),exit_date,(select nvl(sum(anesdoc_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status='L'),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'ANASTESI ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK3'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,(select nvl(sum(anesdoc_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status='L')
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------PENG ANASTESI to PIUTANG---------------
SELECT 'PENG ANASTESI ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK4'),
nvl(shift,'1'),exit_date,(select nvl(sum(changeanesdoc_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status='L'),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'PENG ANASTESI ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK4'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,(select nvl(sum(changeanesdoc_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status='L')
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------ASIS ANASTESI to PIUTANG---------------
SELECT 'ASIS ANASTESI ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK5'),
nvl(shift,'1'),exit_date,(select nvl(sum(asistanes_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status='L'),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'ASIS ANASTESI ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK5'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,(select nvl(sum(asistanes_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status='L')
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------INSTRUMENT to PIUTANG---------------
SELECT 'INSTRUMENT ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK6'),
nvl(shift,'1'),exit_date,(select nvl(sum(instrument_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status='L'),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'INSTRUMENT ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK6'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,(select nvl(sum(instrument_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status='L')
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------OMLOP to PIUTANG---------------
SELECT 'OMLOP ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK7'),
nvl(shift,'1'),exit_date,(select nvl(sum(omlop_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status='L'),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'OMLOP ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK7'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,(select nvl(sum(omlop_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status='L')
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------RR to PIUTANG---------------
SELECT 'RR ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK8'),
nvl(shift,'1'),exit_date,(select nvl(sum(rr_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status='L'),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'RR ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK8'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,(select nvl(sum(rr_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status='L')
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------OK FEE to PIUTANG---------------
SELECT 'OK FEE ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK9'),
nvl(shift,'1'),exit_date,(select nvl(sum(ok_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status='L'),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'OK FEE ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK9'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,(select nvl(sum(ok_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status='L')
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------BAHAN to PIUTANG---------------
SELECT 'BAHAN ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK10'),
nvl(shift,'1'),exit_date,(select nvl(sum(equipment_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status='L'),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'BAHAN ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK10'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,(select nvl(sum(equipment_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status='L')
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------SEWA ALAT to PIUTANG---------------
SELECT 'OPERATOR ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK11'),
nvl(shift,'1'),exit_date,(select nvl(sum(rentequipment_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status='L'),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'SEWA ALAT ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK11'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,0,(select nvl(sum(rentequipment_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status='L')
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
union all
-------BAYAR to PIUTANG RI---------------
SELECT 'BAYAR_RI ('||(select reg_name||' / '||x.reg_no||'' from rsmst_pasiens x where x.reg_no=b.reg_no)||')',
a.acc_id,
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(a.shift,'1'),ripay_date,ripay_bayar,0
FROM RSTXN_RIPAYMENTPDTLS a,RSTXN_RIHDRS b
WHERE a.Rihdr_no=b.Rihdr_no
and ri_status='P'
union all
SELECT 'BAYAR_RI ('||(select reg_name||' / '||x.reg_no||'' from rsmst_pasiens x where x.reg_no=b.reg_no)||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
a.acc_id,
nvl(a.shift,'1'),ripay_date,0,ripay_bayar
FROM RSTXN_RIPAYMENTPDTLS a,RSTXN_RIHDRS b
WHERE a.Rihdr_no=b.Rihdr_no
and ri_status='P'
---------------------------
union all
-------ANGSURAN AWAL RI---------------
SELECT 'ANGSURAN AWAL ('||a.RIhdr_no||')',
acc_id,
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RIANGAWAL'),
nvl(shift,'1'),ripay_date,ripay_bayar,0
FROM RSTXN_RIPAYMENTDTLS a
union all
SELECT 'ANGSURAN AWAL ('||a.RIhdr_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RIANGAWAL'),
acc_id,
nvl(shift,'1'),ripay_date,0,ripay_bayar
FROM RSTXN_RIPAYMENTDTLS a
----------------------------------------
union all
--------PENGEMBALIAN ANGSURAN AWAL RI
SELECT 'PENGEMBALIAN ANGSURAN AWAL ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RIANGAWAL'),
acc_id,
nvl(shift,'1'),exit_date,(select nvl(sum(ripay_bayar),0) from RSTXN_RIPAYMENTDTLS where rihdr_no=a.rihdr_no),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'PENGEMBALIAN ANGSURAN AWAL ('||a.RIhdr_no||'/'||a.reg_no||')',
acc_id,
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RIANGAWAL'),
nvl(shift,'1'),exit_date,0,(select nvl(sum(ripay_bayar),0) from RSTXN_RIPAYMENTDTLS where rihdr_no=a.rihdr_no)
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------------------
union all
--////////////////////////////pertimbangkan metode angsuran awal setelah pulang (angsuran awal hutang)
--------PENGEMBALIAN ANGSURAN SETELAH PULANG
SELECT 'PENGEMBALIAN ANGSURAN AWAL P ('||a.RIhdr_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='R1'),
b.acc_id,
nvl(b.shift,'1'),ripay_date,nvl(ripay_bayar,0),0
FROM RSTXN_RIHDRS a,RSTXN_RIPAYMENTPKDTLS b WHERE a.rihdr_no=b.rihdr_no and ri_status='P'
union all
SELECT 'PENGEMBALIAN ANGSURAN AWAL P ('||a.RIhdr_no||')',
b.acc_id,
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='R1'),
nvl(b.shift,'1'),ripay_date,0,nvl(ripay_bayar,0)
FROM RSTXN_RIHDRS a,RSTXN_RIPAYMENTPKDTLS b WHERE a.rihdr_no=b.rihdr_no and ri_status='P'
union all
-------RCV FROM PBF---------------
-------BAYAR RCV CASH OUT / HUTANG RCV---------------
select 'BAYAR PBF / '||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id),
a.acc_id,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV1')akun,
nvl(shift,'1'),cashout_date,
0,
cashout_value 
from IMTXN_CASHOUTHDRS a
where nvl(cashout_value,0)>0
union all
select 'BAYAR PBF / '||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id),
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV1')akun,
a.acc_id,
nvl(shift,'1'),cashout_date,
cashout_value,
0 
from IMTXN_CASHOUTHDRS a
where nvl(cashout_value,0)>0
----------------------
union all
-------BAYAR RCV TOPUP CASH OUT / HUTANG RCV---------------
select 'BAYAR PBF TOPUP/ '||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id),
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV6')akun,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV1')akun,
nvl(shift,'1'),cashout_date,
0,
cashout_value_topup 
from IMTXN_CASHOUTHDRS a
where nvl(cashout_value_topup,0)>0
union all
select 'BAYAR PBF TOPUP/ '||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id),
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV1')akun,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV6')akun,
nvl(shift,'1'),cashout_date,
cashout_value_topup,
0 
from IMTXN_CASHOUTHDRS a
where nvl(cashout_value_topup,0)>0
----------------------
union all
-------BAYAR DIMUKA RCV CASH OUT / HUTANG RCV---------------
select 'BAYAR DIMUKA PBF / '||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id),
a.acc_id,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV6')akun,
nvl(shift,'1'),cashout_date,
0,
cashout_value 
from IMTXN_CASHOUTHDRTOPUPS a
union all
select 'BAYAR DIMUKA PBF / '||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id),
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV6')akun,
a.acc_id,
nvl(shift,'1'),cashout_date,
cashout_value,
0 
from IMTXN_CASHOUTHDRTOPUPS a
----------------------
----------TRANSAKSI RCV CASH OUT / HUTANG------------
union all
select 'RCV TRANSAKSI'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)||' '||a.rcv_no,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV2')akun,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV1')akun,
nvl(shift,'1'),RCV_date,
(select nvl(sum(nvl(qty,0)*nvl(cost_price,0)),0)
from imtxn_receivedtls
where RCV_no=a.RCV_no)totalRCV,
0
 from imtxn_receiveHDRS a
 where RCV_status in ('H','L')
union all
select 'RCV TRANSAKSI'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)||' '||a.rcv_no,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV1')akun,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV2')akun,
nvl(shift,'1'),RCV_date,
0,
(select nvl(sum(nvl(qty,0)*nvl(cost_price,0)),0)
from imtxn_receivedtls
where RCV_no=a.RCV_no)totalRCV
 from imtxn_receiveHDRS a
 where RCV_status in ('H','L')
----------------------
-------DISKON RCV ITEM / HUTANG---------------
union all
select 'RCV DISKON ITEM TRANSAKSI'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)||' '||a.rcv_no,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV3')akun,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV1')akun,
nvl(shift,'1'),RCV_date,
0,
(select sum(nvl(qty,0)*nvl(cost_price,0))-
sum(
/*persen1*/(nvl(qty,0)*nvl(cost_price,0))/**/-/**/((nvl(qty,0)*nvl(cost_price,0))*nvl(dtl_persen,0)/100)/**/-/**/nvl(dtl_diskon,0)-
/*persen2*/(((nvl(qty,0)*nvl(cost_price,0))/**/-/**/((nvl(qty,0)*nvl(cost_price,0))*nvl(dtl_persen,0)/100)/**/-/**/nvl(dtl_diskon,0))*
(nvl(dtl_persen1,0)/100))-/**/nvl(dtl_diskon1,0))
from imtxn_receivedtls
where RCV_no=a.RCV_no)totaldiskonitem
from imtxn_receiveHDRS a
where RCV_status in ('H','L')
union all
select 'RCV DISKON ITEM TRANSAKSI'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)||' '||a.rcv_no,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV1')akun,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV3')akun,
nvl(shift,'1'),RCV_date,
(select sum(nvl(qty,0)*nvl(cost_price,0))-
sum(
/*persen1*/(nvl(qty,0)*nvl(cost_price,0))/**/-/**/((nvl(qty,0)*nvl(cost_price,0))*nvl(dtl_persen,0)/100)/**/-/**/nvl(dtl_diskon,0)-
/*persen2*/(((nvl(qty,0)*nvl(cost_price,0))/**/-/**/((nvl(qty,0)*nvl(cost_price,0))*nvl(dtl_persen,0)/100)/**/-/**/nvl(dtl_diskon,0))*
(nvl(dtl_persen1,0)/100))-/**/nvl(dtl_diskon1,0))
from imtxn_receivedtls
where RCV_no=a.RCV_no)totaldiskonitem,
0
from imtxn_receiveHDRS a
where RCV_status in ('H','L')
----------------------
------DISKON RCV TOTAL / HUTANG ----------------
union all
select 'RCV DISKON TOTAL TRANSAKSI'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)||' '||a.rcv_no,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV3')akun,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV1')akun,
nvl(shift,'1'),RCV_date,
0,
nvl(RCV_diskon,0)totaldiskonall
 from imtxn_receiveHDRS a
 where RCV_status in ('H','L')
union all
select 'RCV DISKON TOTAL TRANSAKSI'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)||' '||a.rcv_no,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV1')akun,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV3')akun,
nvl(shift,'1'),RCV_date,
nvl(RCV_diskon,0)totaldiskonall,
0
from imtxn_receiveHDRS a
where RCV_status in ('H','L')
and nvl(RCV_diskon,0)>0
----------------------
------RCV MATERAI / HUTANG ----------------
union all
select 'RCV MATERAI TRANSAKSI'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)||' '||a.rcv_no,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV1')akun,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV5')akun,
nvl(shift,'1'),RCV_date,
0,
nvl(RCV_materai,0)totalmaterai
 from imtxn_receiveHDRS a
 where RCV_status in ('H','L')
union all
select 'RCV MATERAI TRANSAKSI'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)||' '||a.rcv_no,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV5')akun,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV1')akun,
nvl(shift,'1'),RCV_date,
nvl(RCV_materai,0)totalmaterai,
0
from imtxn_receiveHDRS a
where RCV_status in ('H','L')
and nvl(RCV_materai,0)>0
----------------------
------RCV PPN / HUTANG ----------------
union all
select 'RCV PPN TRANSAKSI'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)||' '||a.rcv_no,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV1')akun,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV4')akun,
nvl(shift,'1'),RCV_date,
0,
nvl(totalppn,0)total_ppn
 from TKVIEW_RCVHDRS a
 where RCV_status in ('H','L')
union all
select 'RCV PPN TRANSAKSI'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)||' '||a.rcv_no,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV4')akun,
(select acc_id from TKACC_CONFACCTXNS where conf_id='RCV1')akun,
nvl(shift,'1'),RCV_date,
nvl(totalppn,0)total_ppn,
0
from TKVIEW_RCVHDRS a
where RCV_status in ('H','L')
and nvl(totalppn,0)>0
----------------------
---------------------------
union all
-------RTN OBAT RJ KAS PADA PERSEDIAAN APOTEK---------------
SELECT 'RTN RJ ('||a.rtn_desc||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='PAPOTEK'),
acc_id,
nvl(shift,'1'),rtn_date,(select nvl(sum(qty*rtn_prise),0) from IMTXN_RTNDTLS where rtn_no=a.rtn_no),0
FROM IMTXN_RTNHDRS a where rtn_status='L'
union all
SELECT 'RTN OBAT ('||a.rtn_desc||'/'||a.reg_no||')',
acc_id,
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='PAPOTEK'),
nvl(shift,'1'),rtn_date,0,(select nvl(sum(qty*rtn_prise),0) from IMTXN_RTNDTLS where rtn_no=a.rtn_no)
FROM IMTXN_RTNHDRS a WHERE rtn_status='L'
---------------------------
union all
-------RTN OBAT RJ BEBAN TETURN PADA PENDAPATAN OBAT RJ---------------
SELECT 'RTN RJ ('||a.rtn_desc||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ13'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(shift,'1'),rtn_date,(select nvl(sum(qty*rtn_prise),0) from IMTXN_RTNDTLS where rtn_no=a.rtn_no),0
FROM IMTXN_RTNHDRS a where rtn_status='L'
union all
SELECT 'RTN OBAT ('||a.rtn_desc||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ13'),
nvl(shift,'1'),rtn_date,0,(select nvl(sum(qty*rtn_prise),0) from IMTXN_RTNDTLS where rtn_no=a.rtn_no)
FROM IMTXN_RTNHDRS a WHERE rtn_status='L'
---------------------------
------------krurang persediaan apt atau gudang---------------
union all
-------RTN OBAT RI BEBAN TETURN PADA PENDAPATAN OBAT RESEP---------------
SELECT 'RTN RI ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI15'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
nvl(shift,'1'),exit_date,(select nvl(sum(riobat_qty*riobat_price),0) from rstxn_riobatrtns where rihdr_no=a.rihdr_no),0
FROM RSTXN_RIHDRS a WHERE ri_status='P'
union all
SELECT 'RTN OBAT ('||a.RIhdr_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RI15'),
nvl(shift,'1'),exit_date,0,(select nvl(sum(riobat_qty*riobat_price),0) from rstxn_riobatrtns where rihdr_no=a.rihdr_no)
FROM RSTXN_RIHDRS a WHERE ri_status='P'
---------------------------
 
union all
-------KAMAR OPERASI RJ & UGD (ditambahkan 2026-08-01)-------
-------OPERATOR RJ to PIUTANG---------------
SELECT 'OPERATOR RJ ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK1'),
nvl(shift,'1'),rj_date,(select nvl(sum(oprdoc_fee),0) from RSTXN_OKS where status_rjri='RJ' and ref_no=a.rj_no and ok_status='L'),0
FROM rstxn_rjhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='RJ' and x.ref_no=a.rj_no and x.ok_status='L')
union all
SELECT 'OPERATOR RJ ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(shift,'1'),rj_date,0,(select nvl(sum(oprdoc_fee),0) from RSTXN_OKS where status_rjri='RJ' and ref_no=a.rj_no and ok_status='L')
FROM rstxn_rjhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='RJ' and x.ref_no=a.rj_no and x.ok_status='L')
---------------------------
union all
-------ASIS OPERATOR RJ to PIUTANG---------------
SELECT 'ASIS OPERATOR RJ ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK2'),
nvl(shift,'1'),rj_date,(select nvl(sum(asistopr_fee),0) from RSTXN_OKS where status_rjri='RJ' and ref_no=a.rj_no and ok_status='L'),0
FROM rstxn_rjhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='RJ' and x.ref_no=a.rj_no and x.ok_status='L')
union all
SELECT 'ASIS OPERATOR RJ ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK2'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(shift,'1'),rj_date,0,(select nvl(sum(asistopr_fee),0) from RSTXN_OKS where status_rjri='RJ' and ref_no=a.rj_no and ok_status='L')
FROM rstxn_rjhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='RJ' and x.ref_no=a.rj_no and x.ok_status='L')
---------------------------
union all
-------ANASTESI RJ to PIUTANG---------------
SELECT 'ANASTESI RJ ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK3'),
nvl(shift,'1'),rj_date,(select nvl(sum(anesdoc_fee),0) from RSTXN_OKS where status_rjri='RJ' and ref_no=a.rj_no and ok_status='L'),0
FROM rstxn_rjhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='RJ' and x.ref_no=a.rj_no and x.ok_status='L')
union all
SELECT 'ANASTESI RJ ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK3'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(shift,'1'),rj_date,0,(select nvl(sum(anesdoc_fee),0) from RSTXN_OKS where status_rjri='RJ' and ref_no=a.rj_no and ok_status='L')
FROM rstxn_rjhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='RJ' and x.ref_no=a.rj_no and x.ok_status='L')
---------------------------
union all
-------PENG ANASTESI RJ to PIUTANG---------------
SELECT 'PENG ANASTESI RJ ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK4'),
nvl(shift,'1'),rj_date,(select nvl(sum(changeanesdoc_fee),0) from RSTXN_OKS where status_rjri='RJ' and ref_no=a.rj_no and ok_status='L'),0
FROM rstxn_rjhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='RJ' and x.ref_no=a.rj_no and x.ok_status='L')
union all
SELECT 'PENG ANASTESI RJ ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK4'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(shift,'1'),rj_date,0,(select nvl(sum(changeanesdoc_fee),0) from RSTXN_OKS where status_rjri='RJ' and ref_no=a.rj_no and ok_status='L')
FROM rstxn_rjhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='RJ' and x.ref_no=a.rj_no and x.ok_status='L')
---------------------------
union all
-------ASIS ANASTESI RJ to PIUTANG---------------
SELECT 'ASIS ANASTESI RJ ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK5'),
nvl(shift,'1'),rj_date,(select nvl(sum(asistanes_fee),0) from RSTXN_OKS where status_rjri='RJ' and ref_no=a.rj_no and ok_status='L'),0
FROM rstxn_rjhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='RJ' and x.ref_no=a.rj_no and x.ok_status='L')
union all
SELECT 'ASIS ANASTESI RJ ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK5'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(shift,'1'),rj_date,0,(select nvl(sum(asistanes_fee),0) from RSTXN_OKS where status_rjri='RJ' and ref_no=a.rj_no and ok_status='L')
FROM rstxn_rjhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='RJ' and x.ref_no=a.rj_no and x.ok_status='L')
---------------------------
union all
-------INSTRUMENT RJ to PIUTANG---------------
SELECT 'INSTRUMENT RJ ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK6'),
nvl(shift,'1'),rj_date,(select nvl(sum(instrument_fee),0) from RSTXN_OKS where status_rjri='RJ' and ref_no=a.rj_no and ok_status='L'),0
FROM rstxn_rjhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='RJ' and x.ref_no=a.rj_no and x.ok_status='L')
union all
SELECT 'INSTRUMENT RJ ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK6'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(shift,'1'),rj_date,0,(select nvl(sum(instrument_fee),0) from RSTXN_OKS where status_rjri='RJ' and ref_no=a.rj_no and ok_status='L')
FROM rstxn_rjhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='RJ' and x.ref_no=a.rj_no and x.ok_status='L')
---------------------------
union all
-------OMLOP RJ to PIUTANG---------------
SELECT 'OMLOP RJ ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK7'),
nvl(shift,'1'),rj_date,(select nvl(sum(omlop_fee),0) from RSTXN_OKS where status_rjri='RJ' and ref_no=a.rj_no and ok_status='L'),0
FROM rstxn_rjhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='RJ' and x.ref_no=a.rj_no and x.ok_status='L')
union all
SELECT 'OMLOP RJ ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK7'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(shift,'1'),rj_date,0,(select nvl(sum(omlop_fee),0) from RSTXN_OKS where status_rjri='RJ' and ref_no=a.rj_no and ok_status='L')
FROM rstxn_rjhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='RJ' and x.ref_no=a.rj_no and x.ok_status='L')
---------------------------
union all
-------RR RJ to PIUTANG---------------
SELECT 'RR RJ ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK8'),
nvl(shift,'1'),rj_date,(select nvl(sum(rr_fee),0) from RSTXN_OKS where status_rjri='RJ' and ref_no=a.rj_no and ok_status='L'),0
FROM rstxn_rjhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='RJ' and x.ref_no=a.rj_no and x.ok_status='L')
union all
SELECT 'RR RJ ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK8'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(shift,'1'),rj_date,0,(select nvl(sum(rr_fee),0) from RSTXN_OKS where status_rjri='RJ' and ref_no=a.rj_no and ok_status='L')
FROM rstxn_rjhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='RJ' and x.ref_no=a.rj_no and x.ok_status='L')
---------------------------
union all
-------OK FEE RJ to PIUTANG---------------
SELECT 'OK FEE RJ ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK9'),
nvl(shift,'1'),rj_date,(select nvl(sum(ok_fee),0) from RSTXN_OKS where status_rjri='RJ' and ref_no=a.rj_no and ok_status='L'),0
FROM rstxn_rjhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='RJ' and x.ref_no=a.rj_no and x.ok_status='L')
union all
SELECT 'OK FEE RJ ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK9'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(shift,'1'),rj_date,0,(select nvl(sum(ok_fee),0) from RSTXN_OKS where status_rjri='RJ' and ref_no=a.rj_no and ok_status='L')
FROM rstxn_rjhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='RJ' and x.ref_no=a.rj_no and x.ok_status='L')
---------------------------
union all
-------BAHAN RJ to PIUTANG---------------
SELECT 'BAHAN RJ ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK10'),
nvl(shift,'1'),rj_date,(select nvl(sum(equipment_fee),0) from RSTXN_OKS where status_rjri='RJ' and ref_no=a.rj_no and ok_status='L'),0
FROM rstxn_rjhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='RJ' and x.ref_no=a.rj_no and x.ok_status='L')
union all
SELECT 'BAHAN RJ ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK10'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(shift,'1'),rj_date,0,(select nvl(sum(equipment_fee),0) from RSTXN_OKS where status_rjri='RJ' and ref_no=a.rj_no and ok_status='L')
FROM rstxn_rjhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='RJ' and x.ref_no=a.rj_no and x.ok_status='L')
---------------------------
union all
-------SEWA ALAT RJ to PIUTANG---------------
SELECT 'SEWA ALAT RJ ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK11'),
nvl(shift,'1'),rj_date,(select nvl(sum(rentequipment_fee),0) from RSTXN_OKS where status_rjri='RJ' and ref_no=a.rj_no and ok_status='L'),0
FROM rstxn_rjhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='RJ' and x.ref_no=a.rj_no and x.ok_status='L')
union all
SELECT 'SEWA ALAT RJ ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK11'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='RJ1'),
nvl(shift,'1'),rj_date,0,(select nvl(sum(rentequipment_fee),0) from RSTXN_OKS where status_rjri='RJ' and ref_no=a.rj_no and ok_status='L')
FROM rstxn_rjhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='RJ' and x.ref_no=a.rj_no and x.ok_status='L')
---------------------------
union all
-------OPERATOR UGD to PIUTANG---------------
SELECT 'OPERATOR UGD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK1'),
nvl(shift,'1'),rj_date,(select nvl(sum(oprdoc_fee),0) from RSTXN_OKS where status_rjri='UGD' and ref_no=a.rj_no and ok_status='L'),0
FROM rstxn_ugdhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='UGD' and x.ref_no=a.rj_no and x.ok_status='L')
union all
SELECT 'OPERATOR UGD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(shift,'1'),rj_date,0,(select nvl(sum(oprdoc_fee),0) from RSTXN_OKS where status_rjri='UGD' and ref_no=a.rj_no and ok_status='L')
FROM rstxn_ugdhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='UGD' and x.ref_no=a.rj_no and x.ok_status='L')
---------------------------
union all
-------ASIS OPERATOR UGD to PIUTANG---------------
SELECT 'ASIS OPERATOR UGD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK2'),
nvl(shift,'1'),rj_date,(select nvl(sum(asistopr_fee),0) from RSTXN_OKS where status_rjri='UGD' and ref_no=a.rj_no and ok_status='L'),0
FROM rstxn_ugdhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='UGD' and x.ref_no=a.rj_no and x.ok_status='L')
union all
SELECT 'ASIS OPERATOR UGD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK2'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(shift,'1'),rj_date,0,(select nvl(sum(asistopr_fee),0) from RSTXN_OKS where status_rjri='UGD' and ref_no=a.rj_no and ok_status='L')
FROM rstxn_ugdhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='UGD' and x.ref_no=a.rj_no and x.ok_status='L')
---------------------------
union all
-------ANASTESI UGD to PIUTANG---------------
SELECT 'ANASTESI UGD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK3'),
nvl(shift,'1'),rj_date,(select nvl(sum(anesdoc_fee),0) from RSTXN_OKS where status_rjri='UGD' and ref_no=a.rj_no and ok_status='L'),0
FROM rstxn_ugdhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='UGD' and x.ref_no=a.rj_no and x.ok_status='L')
union all
SELECT 'ANASTESI UGD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK3'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(shift,'1'),rj_date,0,(select nvl(sum(anesdoc_fee),0) from RSTXN_OKS where status_rjri='UGD' and ref_no=a.rj_no and ok_status='L')
FROM rstxn_ugdhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='UGD' and x.ref_no=a.rj_no and x.ok_status='L')
---------------------------
union all
-------PENG ANASTESI UGD to PIUTANG---------------
SELECT 'PENG ANASTESI UGD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK4'),
nvl(shift,'1'),rj_date,(select nvl(sum(changeanesdoc_fee),0) from RSTXN_OKS where status_rjri='UGD' and ref_no=a.rj_no and ok_status='L'),0
FROM rstxn_ugdhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='UGD' and x.ref_no=a.rj_no and x.ok_status='L')
union all
SELECT 'PENG ANASTESI UGD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK4'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(shift,'1'),rj_date,0,(select nvl(sum(changeanesdoc_fee),0) from RSTXN_OKS where status_rjri='UGD' and ref_no=a.rj_no and ok_status='L')
FROM rstxn_ugdhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='UGD' and x.ref_no=a.rj_no and x.ok_status='L')
---------------------------
union all
-------ASIS ANASTESI UGD to PIUTANG---------------
SELECT 'ASIS ANASTESI UGD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK5'),
nvl(shift,'1'),rj_date,(select nvl(sum(asistanes_fee),0) from RSTXN_OKS where status_rjri='UGD' and ref_no=a.rj_no and ok_status='L'),0
FROM rstxn_ugdhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='UGD' and x.ref_no=a.rj_no and x.ok_status='L')
union all
SELECT 'ASIS ANASTESI UGD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK5'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(shift,'1'),rj_date,0,(select nvl(sum(asistanes_fee),0) from RSTXN_OKS where status_rjri='UGD' and ref_no=a.rj_no and ok_status='L')
FROM rstxn_ugdhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='UGD' and x.ref_no=a.rj_no and x.ok_status='L')
---------------------------
union all
-------INSTRUMENT UGD to PIUTANG---------------
SELECT 'INSTRUMENT UGD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK6'),
nvl(shift,'1'),rj_date,(select nvl(sum(instrument_fee),0) from RSTXN_OKS where status_rjri='UGD' and ref_no=a.rj_no and ok_status='L'),0
FROM rstxn_ugdhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='UGD' and x.ref_no=a.rj_no and x.ok_status='L')
union all
SELECT 'INSTRUMENT UGD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK6'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(shift,'1'),rj_date,0,(select nvl(sum(instrument_fee),0) from RSTXN_OKS where status_rjri='UGD' and ref_no=a.rj_no and ok_status='L')
FROM rstxn_ugdhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='UGD' and x.ref_no=a.rj_no and x.ok_status='L')
---------------------------
union all
-------OMLOP UGD to PIUTANG---------------
SELECT 'OMLOP UGD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK7'),
nvl(shift,'1'),rj_date,(select nvl(sum(omlop_fee),0) from RSTXN_OKS where status_rjri='UGD' and ref_no=a.rj_no and ok_status='L'),0
FROM rstxn_ugdhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='UGD' and x.ref_no=a.rj_no and x.ok_status='L')
union all
SELECT 'OMLOP UGD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK7'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(shift,'1'),rj_date,0,(select nvl(sum(omlop_fee),0) from RSTXN_OKS where status_rjri='UGD' and ref_no=a.rj_no and ok_status='L')
FROM rstxn_ugdhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='UGD' and x.ref_no=a.rj_no and x.ok_status='L')
---------------------------
union all
-------RR UGD to PIUTANG---------------
SELECT 'RR UGD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK8'),
nvl(shift,'1'),rj_date,(select nvl(sum(rr_fee),0) from RSTXN_OKS where status_rjri='UGD' and ref_no=a.rj_no and ok_status='L'),0
FROM rstxn_ugdhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='UGD' and x.ref_no=a.rj_no and x.ok_status='L')
union all
SELECT 'RR UGD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK8'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(shift,'1'),rj_date,0,(select nvl(sum(rr_fee),0) from RSTXN_OKS where status_rjri='UGD' and ref_no=a.rj_no and ok_status='L')
FROM rstxn_ugdhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='UGD' and x.ref_no=a.rj_no and x.ok_status='L')
---------------------------
union all
-------OK FEE UGD to PIUTANG---------------
SELECT 'OK FEE UGD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK9'),
nvl(shift,'1'),rj_date,(select nvl(sum(ok_fee),0) from RSTXN_OKS where status_rjri='UGD' and ref_no=a.rj_no and ok_status='L'),0
FROM rstxn_ugdhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='UGD' and x.ref_no=a.rj_no and x.ok_status='L')
union all
SELECT 'OK FEE UGD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK9'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(shift,'1'),rj_date,0,(select nvl(sum(ok_fee),0) from RSTXN_OKS where status_rjri='UGD' and ref_no=a.rj_no and ok_status='L')
FROM rstxn_ugdhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='UGD' and x.ref_no=a.rj_no and x.ok_status='L')
---------------------------
union all
-------BAHAN UGD to PIUTANG---------------
SELECT 'BAHAN UGD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK10'),
nvl(shift,'1'),rj_date,(select nvl(sum(equipment_fee),0) from RSTXN_OKS where status_rjri='UGD' and ref_no=a.rj_no and ok_status='L'),0
FROM rstxn_ugdhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='UGD' and x.ref_no=a.rj_no and x.ok_status='L')
union all
SELECT 'BAHAN UGD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK10'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(shift,'1'),rj_date,0,(select nvl(sum(equipment_fee),0) from RSTXN_OKS where status_rjri='UGD' and ref_no=a.rj_no and ok_status='L')
FROM rstxn_ugdhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='UGD' and x.ref_no=a.rj_no and x.ok_status='L')
---------------------------
union all
-------SEWA ALAT UGD to PIUTANG---------------
SELECT 'SEWA ALAT UGD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK11'),
nvl(shift,'1'),rj_date,(select nvl(sum(rentequipment_fee),0) from RSTXN_OKS where status_rjri='UGD' and ref_no=a.rj_no and ok_status='L'),0
FROM rstxn_ugdhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='UGD' and x.ref_no=a.rj_no and x.ok_status='L')
union all
SELECT 'SEWA ALAT UGD ('||a.rj_no||'/'||a.reg_no||')',
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='OK11'),
(select z.acc_id from TKACC_CONFACCTXNS z where conf_id='UGD1'),
nvl(shift,'1'),rj_date,0,(select nvl(sum(rentequipment_fee),0) from RSTXN_OKS where status_rjri='UGD' and ref_no=a.rj_no and ok_status='L')
FROM rstxn_ugdhdrs a WHERE rj_status not in('A','F')
and exists (select 1 from RSTXN_OKS x where x.status_rjri='UGD' and x.ref_no=a.rj_no and x.ok_status='L')
---------------------------
)
