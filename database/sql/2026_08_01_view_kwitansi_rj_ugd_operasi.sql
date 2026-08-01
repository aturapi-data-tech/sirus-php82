-- database/sql/2026_08_01_view_kwitansi_rj_ugd_operasi.sql
-- ===============================================================
-- RSVIEW_RJSTRS & RSVIEW_UGDSTRS — pos KAMAR OPERASI di kwitansi
--
-- MASALAH:
--   Rincian kwitansi RJ & UGD (cetak-kwitansi-{rj,ugd}.blade.php) TIDAK dihitung
--   di PHP — seluruhnya dibaca dari dua view ini:
--       SELECT txn_id, txn_desc, txn_nominal, txn_no
--         FROM rsview_rjstrs WHERE rj_no = :rjno AND txn_nominal > 0
--        ORDER BY txn_no
--   Karena tidak ada cabang untuk rstxn_rjoks / rstxn_ugdoks, biaya Kamar
--   Operasi TIDAK MUNCUL di kwitansi dan subtotal-nya pun kurang — padahal
--   kasir sudah menagihkannya. Kwitansi jadi tidak cocok dengan yang dibayar.
--
-- PERUBAHAN:
--   Menambah SATU cabang UNION ALL di masing-masing view. Cabang lama tidak
--   disentuh sama sekali, jadi angka pos lain tidak bergeser.
--
--     RSVIEW_RJSTRS   : txn_id 'KAMAR OPERASI', txn_no 10
--     RSVIEW_UGDSTRS  : txn_id 'KAMAR OPERASI', txn_no 11
--
--   txn_no dipilih di ujung dan pos lama TIDAK dinomori ulang: kolom itu hanya
--   dipakai ORDER BY, tapi view ini juga dibaca sistem legacy Oracle Dev 6i
--   (lihat memory project_dual_system_oradev_php82) — menggeser nomor pos lama
--   akan mengubah urutan cetakan di sana tanpa ada yang meminta.
--
--   Dikelompokkan per ok_desc supaya tiap pos tarif (SEWA OK, JASA PERAWAT, dst)
--   tampil sebagai barisnya sendiri di kwitansi, sejajar cara LABORAT dan
--   RADIOLOGI ditampilkan.
--
-- CATATAN — TIDAK ADA DOBEL HITUNG:
--   Cabang 'TRF RJ' di RSVIEW_UGDSTRS menjumlah RSTXN_UGDBIAYASELAMADIRJS
--   (total tagihan RJ yang dibawa). Nilai itu sudah memuat biaya operasi RJ
--   karena calculateRJCosts() memuat komponen 'kamarOperasi'. Cabang baru di
--   view UGD hanya membaca rstxn_ugdoks — operasi milik kunjungan UGD sendiri.
--
-- CARA PAKAI:
--   Jalankan seluruh isi file (2 x CREATE OR REPLACE VIEW).
--
-- ROLLBACK:
--   Buang cabang terakhir tiap view, lalu CREATE OR REPLACE lagi. Definisi lama
--   bisa diambil dari:  SELECT text FROM user_views WHERE view_name = '...';
-- ===============================================================

CREATE OR REPLACE VIEW RSVIEW_RJSTRS (TXN_ID, TXN_DESC, TXN_NOMINAL, RJ_NO, TXN_NO) AS
(
select 'ADMIN RAWAT JALAN','ADMIN RUMAH SAKIT',rs_admin,rj_no,1 from rstxn_rjhdrs
union all
select 'ADMIN RAWAT JALAN','ADMIN RAWAT JALAN',rj_admin,rj_no,1 from rstxn_rjhdrs
union all
select 'ADMIN UP','UANG PERIKSA POLI',poli_price,rj_no,2 from rstxn_rjhdrs
union all
select 'JASA DOKTER',dr_name||'  '||accdoc_desc||'  '||count(*)||' (X)',sum(b.accdoc_price)accdoc_price,a.rj_no,3
from rstxn_rjhdrs a,rstxn_rjaccdocs b,rsmst_accdocs c,rsmst_doctors d
where a.rj_no=b.rj_no
and b.accdoc_id=c.accdoc_id
and a.dr_id=d.dr_id
group by dr_name,accdoc_desc,a.rj_no
union all
select 'JASA MEDIS',pact_desc||'  '||count(*)||' (X)',sum(b.PACT_PRICE)PACT_PRICE,a.rj_no,4
from rstxn_rjhdrs a,RSTXN_RJACTPARAMS b,rsmst_actparamedics c
where a.rj_no=b.rj_no
and b.pact_id=c.pact_id
group by  pact_desc,a.rj_no
union all
select 'JASA KARYAWAN','JASA KARYAWAN',sum(acte_price),rj_no,5
from rstxn_rjactemps
group by rj_no
union all
select 'RADIOLOGI',rad_desc||'  '||count(*)||' (X)',sum(a.rad_price)rad_price,rj_no,6
from rstxn_rjrads a,rsmst_radiologis b
where a.rad_id=b.rad_id
group by rj_no,rad_desc
union all
select 'LABORAT',lab_Desc,sum(lab_price)lab_price,rj_no,7
from rstxn_rjlabs
group by lab_desc,rj_no
union all
select 'OBAT','BIAYA OBAT RAWAT JALAN',sum(nvl(qty,0)*nvl(price,0)),rj_no,8
from RSTXN_RJOBATS
group by rj_no
union all
select 'LAIN-LAIN',other_desc||'  '||count(*)||' (X)',SUM(a.other_price),rj_no,9
from rstxn_rjothers a,rsmst_others b
where a.other_id=b.other_id
GROUP BY other_desc,rj_no
union all
select 'KAMAR OPERASI',ok_desc,sum(nvl(ok_price,0)),rj_no,10
from rstxn_rjoks
group by ok_desc,rj_no
)
;

CREATE OR REPLACE VIEW RSVIEW_UGDSTRS (TXN_ID, TXN_DESC, TXN_NOMINAL, RJ_NO, TXN_NO) AS
(
select 'ADMIN UGD','ADMIN RUMAH SAKIT',rs_admin,rj_no,1 from rstxn_ugdhdrs
union all
select 'ADMIN UGD','ADMIN UGD',rj_admin,rj_no,1 from rstxn_ugdhdrs
union all
select 'ADMIN UP','UANG PERIKSA UGD',poli_price,rj_no,2 from rstxn_ugdhdrs
union all
select 'JASA DOKTER',dr_name||'  '||accdoc_desc||'  '||count(*)||' (X)',sum(b.accdoc_price)accdoc_price,a.rj_no,3
from rstxn_ugdhdrs a,rstxn_ugdaccdocs b,rsmst_accdocs c,rsmst_doctors d
where a.rj_no=b.rj_no
and b.accdoc_id=c.accdoc_id
and a.dr_id=d.dr_id
group by dr_name,accdoc_desc,a.rj_no
union all
select 'JASA MEDIS',pact_desc||'  '||count(*)||' (X)',sum(b.PACT_PRICE)PACT_PRICE,a.rj_no,4
from rstxn_ugdhdrs a,rstxn_ugdACTPARAMS b,rsmst_actparamedics c
where a.rj_no=b.rj_no
and b.pact_id=c.pact_id
group by  pact_desc,a.rj_no
union all
select 'JASA KARYAWAN','JASA KARYAWAN',sum(acte_price),rj_no,5
from rstxn_ugdactemps
group by rj_no
union all
select 'RADIOLOGI',rad_desc||'  '||count(*)||' (X)',sum(a.rad_price)rad_price,rj_no,6
from rstxn_ugdrads a,rsmst_radiologis b
where a.rad_id=b.rad_id
group by rj_no,rad_desc
union all
select 'LABORAT',lab_Desc,sum(lab_price)lab_price,rj_no,7
from rstxn_ugdlabs
group by lab_desc,rj_no
union all
select 'OBAT','BIAYA OBAT UGD',sum(nvl(qty,0)*nvl(price,0)),rj_no,8
from rstxn_ugdOBATS
group by rj_no
union all
select 'LAIN-LAIN',other_desc||'  '||count(*)||' (X)',SUM(a.other_price),rj_no,9
from rstxn_ugdothers a,rsmst_others b
where a.other_id=b.other_id
GROUP BY other_desc,rj_no
union all
select 'TRF RJ','BIAYA TRANSFER RAWAT JALAN',sum(total_biayarj),rj_no_rsugd,10
from RSTXN_UGDBIAYASELAMADIRJS
group by rj_no_rsugd
union all
select 'KAMAR OPERASI',ok_desc,sum(nvl(ok_price,0)),rj_no,11
from rstxn_ugdoks
group by ok_desc,rj_no
)
;

-- ---------------------------------------------------------------
-- VERIFIKASI
-- ---------------------------------------------------------------
-- SELECT object_name, status FROM user_objects
--  WHERE object_name IN ('RSVIEW_RJSTRS','RSVIEW_UGDSTRS') AND object_type='VIEW';
-- Keduanya harus VALID.
--
-- SELECT txn_no, txn_id, COUNT(*) FROM rsview_rjstrs  GROUP BY txn_no, txn_id ORDER BY txn_no;
-- SELECT txn_no, txn_id, COUNT(*) FROM rsview_ugdstrs GROUP BY txn_no, txn_id ORDER BY txn_no;
