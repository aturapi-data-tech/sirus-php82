<?php

namespace App\Support\Keuangan;

/**
 * KATALOG CABANG JURNAL — DIBANGKITKAN OTOMATIS, JANGAN DIEDIT MANUAL.
 *
 * Sumber : database/sql/2026_08_01_view_tkview_accounts_ok_rj_ugd.sql (definisi TKVIEW_ACCOUNTS).
 * Pembangkit: database/sql/tools/gen-jurnal-cabang.py  (jalankan ulang setiap DDL view berubah).
 *
 * Tiap entri = satu cabang UNION ALL view, kolom persis urutan view:
 *   name  : ekspresi TXN_NAME      acc : TXN_ACC      accK : TXN_ACC_K
 *   shift : ekspresi SHIFT         date: TXN_DATE     d/k  : TXN_D / TXN_K
 *   from  : klausa FROM            where: klausa WHERE (boleh kosong)
 * Ekspresi akun 'conf:XXX' = akun konfigurasi tkacc_confacctxns.conf_id = XXX;
 * selain itu kolom akun tabel sumber (a.acc_id, acc_id_kas, ...).
 */
final class JurnalCabang
{
    public static function semua(): array
    {
        return [
            // #0
            ['name' => '\'CI\'||\' \'||tucashk_desc||\'(\'||tucashk_no||\')\'', 'acc' => 'acc_id_kas', 'accK' => 'acc_id', 'shift' => 'nvl(shift,\'1\')', 'date' => 'tucashk_date', 'd' => 'tucashk_nominal', 'k' => '0', 'from' => 'RSTXN_TUCASHDS a', 'where' => 'tucashk_status=\'L\''],
            // #1
            ['name' => '\'CI\'||\' \'||tucashk_desc||\'(\'||tucashk_no||\')\'', 'acc' => 'acc_id', 'accK' => 'acc_id_kas', 'shift' => 'nvl(shift,\'1\')', 'date' => 'tucashk_date', 'd' => '0', 'k' => 'tucashk_nominal', 'from' => 'RSTXN_TUCASHDS a', 'where' => 'tucashk_status=\'L\''],
            // #2
            ['name' => '\'CO\'||\' \'||tucashk_desc||\'(\'||tucashk_no||\')\'', 'acc' => 'acc_id_kas', 'accK' => 'acc_id', 'shift' => 'nvl(shift,\'1\')', 'date' => 'tucashk_date', 'd' => '0', 'k' => 'tucashk_nominal', 'from' => 'RSTXN_TUCASHKS a', 'where' => 'tucashk_status=\'L\''],
            // #3
            ['name' => '\'CO\'||\' \'||tucashk_desc||\'(\'||tucashk_no||\')\'', 'acc' => 'acc_id', 'accK' => 'acc_id_kas', 'shift' => 'nvl(shift,\'1\')', 'date' => 'tucashk_date', 'd' => 'tucashk_nominal', 'k' => '0', 'from' => 'RSTXN_TUCASHKS a', 'where' => 'tucashk_status=\'L\''],
            // #4
            ['name' => '\'RJ_ADMIN (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:RJ3', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => 'rj_admin', 'k' => '0', 'from' => 'RSTXN_RJHDRS a', 'where' => 'rj_status not in(\'A\',\'F\')'],
            // #5
            ['name' => '\'RJ_ADMIN (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ3', 'accK' => 'conf:RJ1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => 'rj_admin', 'from' => 'RSTXN_RJHDRS a', 'where' => 'rj_status not in(\'A\',\'F\')'],
            // #6
            ['name' => '\'RS_ADMIN (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:RJ2', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => 'rs_admin', 'k' => '0', 'from' => 'RSTXN_RJHDRS a', 'where' => 'rj_status not in(\'A\',\'F\')'],
            // #7
            ['name' => '\'RS_ADMIN (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ2', 'accK' => 'conf:RJ1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => 'rs_admin', 'from' => 'RSTXN_RJHDRS a', 'where' => 'rj_status not in(\'A\',\'F\')'],
            // #8
            ['name' => '\'UP (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:RJ11', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => 'poli_price', 'k' => '0', 'from' => 'RSTXN_RJHDRS a', 'where' => 'rj_status not in(\'A\',\'F\')'],
            // #9
            ['name' => '\'UP (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ11', 'accK' => 'conf:RJ1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => 'poli_price', 'from' => 'RSTXN_RJHDRS a', 'where' => 'rj_status not in(\'A\',\'F\')'],
            // #10
            ['name' => '\'JD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:RJ4', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select sum(accdoc_price) from RSTXN_RJACCDOCS G where G.rj_no=a.rj_no)', 'k' => '0', 'from' => 'RSTXN_RJHDRS a', 'where' => 'rj_status not in(\'A\',\'F\')'],
            // #11
            ['name' => '\'JD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ4', 'accK' => 'conf:RJ1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select sum(accdoc_price) from RSTXN_RJACCDOCS G where G.rj_no=a.rj_no)', 'from' => 'RSTXN_RJHDRS a', 'where' => 'rj_status not in(\'A\',\'F\')'],
            // #12
            ['name' => '\'JM (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:RJ5', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select sum(pact_price) from RSTXN_RJACTPARAMS G where G.rj_no=a.rj_no)', 'k' => '0', 'from' => 'RSTXN_RJHDRS a', 'where' => 'rj_status not in(\'A\',\'F\')'],
            // #13
            ['name' => '\'JM (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ5', 'accK' => 'conf:RJ1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select sum(pact_price) from RSTXN_RJACTPARAMS G where G.rj_no=a.rj_no)', 'from' => 'RSTXN_RJHDRS a', 'where' => 'rj_status not in(\'A\',\'F\')'],
            // #14
            ['name' => '\'JK (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:RJ6', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select sum(acte_price) from RSTXN_RJACTEMPS G where G.rj_no=a.rj_no)', 'k' => '0', 'from' => 'RSTXN_RJHDRS a', 'where' => 'rj_status not in(\'A\',\'F\')'],
            // #15
            ['name' => '\'JK (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ6', 'accK' => 'conf:RJ1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select sum(acte_price) from RSTXN_RJACTEMPS G where G.rj_no=a.rj_no)', 'from' => 'RSTXN_RJHDRS a', 'where' => 'rj_status not in(\'A\',\'F\')'],
            // #16
            ['name' => '\'OBAT (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:RJ9', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select sum((NVL(qty,0)*NVL(price,0))) from RSTXN_RJOBATS G where G.rj_no=a.rj_no)', 'k' => '0', 'from' => 'RSTXN_RJHDRS a', 'where' => 'rj_status not in(\'A\',\'F\')'],
            // #17
            ['name' => '\'OBAT (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ9', 'accK' => 'conf:RJ1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select sum((NVL(qty,0)*NVL(price,0))) from RSTXN_RJOBATS G where G.rj_no=a.rj_no)', 'from' => 'RSTXN_RJHDRS a', 'where' => 'rj_status not in(\'A\',\'F\')'],
            // #18
            ['name' => '\'LAB (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:RJ7', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select sum(lab_price) from RSTXN_RJLABS G where G.rj_no=a.rj_no)', 'k' => '0', 'from' => 'RSTXN_RJHDRS a', 'where' => 'rj_status not in(\'A\',\'F\')'],
            // #19
            ['name' => '\'LAB (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ7', 'accK' => 'conf:RJ1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select sum(lab_price) from RSTXN_RJLABS G where G.rj_no=a.rj_no)', 'from' => 'RSTXN_RJHDRS a', 'where' => 'rj_status not in(\'A\',\'F\')'],
            // #20
            ['name' => '\'RAD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:RJ10', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select sum(rad_price) from RSTXN_RJRADS G where G.rj_no=a.rj_no)', 'k' => '0', 'from' => 'RSTXN_RJHDRS a', 'where' => 'rj_status not in(\'A\',\'F\')'],
            // #21
            ['name' => '\'RAD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ10', 'accK' => 'conf:RJ1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select sum(rad_price) from RSTXN_RJRADS G where G.rj_no=a.rj_no)', 'from' => 'RSTXN_RJHDRS a', 'where' => 'rj_status not in(\'A\',\'F\')'],
            // #22
            ['name' => '\'LAIN (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:RJ8', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select sum(other_price) from RSTXN_RJOTHERS G where G.rj_no=a.rj_no)', 'k' => '0', 'from' => 'RSTXN_RJHDRS a', 'where' => 'rj_status not in(\'A\',\'F\')'],
            // #23
            ['name' => '\'LAIN (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ8', 'accK' => 'conf:RJ1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select sum(other_price) from RSTXN_RJOTHERS G where G.rj_no=a.rj_no)', 'from' => 'RSTXN_RJHDRS a', 'where' => 'rj_status not in(\'A\',\'F\')'],
            // #24
            ['name' => '\'RJ_DISKON (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ12', 'accK' => 'conf:RJ1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => 'rj_diskon', 'k' => '0', 'from' => 'RSTXN_RJHDRS a', 'where' => 'rj_status not in(\'A\',\'F\')'],
            // #25
            ['name' => '\'RJ_DISKON (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:RJ12', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => 'rj_diskon', 'from' => 'RSTXN_RJHDRS a', 'where' => 'rj_status not in(\'A\',\'F\')'],
            // #26
            ['name' => '\'BAYAR_RJ (\'||a.rjc_desc||\')\'', 'acc' => 'a.acc_id', 'accK' => 'conf:RJ1', 'shift' => 'nvl(a.shift,\'1\')', 'date' => 'rjc_date', 'd' => 'rjc_nominal', 'k' => '0', 'from' => 'RSTXN_RJCASHINS a,rstxn_rjhdrs b', 'where' => 'a.rj_no=b.rj_no and rj_status not in(\'A\',\'F\')'],
            // #27
            ['name' => '\'BAYAR_RJ (\'||a.rjc_desc||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'a.acc_id', 'shift' => 'nvl(a.shift,\'1\')', 'date' => 'rjc_date', 'd' => '0', 'k' => 'rjc_nominal', 'from' => 'RSTXN_RJCASHINS a,rstxn_rjhdrs b', 'where' => 'a.rj_no=b.rj_no and rj_status not in(\'A\',\'F\')'],
            // #28
            ['name' => '\'UGD_ADMIN (\'||a.RJ_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'conf:UGD3', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RJ_date', 'd' => 'RJ_admin', 'k' => '0', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #29
            ['name' => '\'UGD_ADMIN (\'||a.RJ_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD3', 'accK' => 'conf:UGD1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RJ_date', 'd' => '0', 'k' => 'RJ_admin', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #30
            ['name' => '\'RS_ADMIN (\'||a.RJ_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'conf:UGD2', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RJ_date', 'd' => 'rs_admin', 'k' => '0', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #31
            ['name' => '\'RS_ADMIN (\'||a.RJ_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD2', 'accK' => 'conf:UGD1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RJ_date', 'd' => '0', 'k' => 'rs_admin', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #32
            ['name' => '\'UP (\'||a.RJ_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'conf:UGD11', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RJ_date', 'd' => 'poli_price', 'k' => '0', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #33
            ['name' => '\'UP (\'||a.RJ_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD11', 'accK' => 'conf:UGD1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RJ_date', 'd' => '0', 'k' => 'poli_price', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #34
            ['name' => '\'JD (\'||a.RJ_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'conf:UGD4', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RJ_date', 'd' => '(select sum(accdoc_price) from RSTXN_UGDACCDOCS G where G.RJ_no=a.RJ_no)', 'k' => '0', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #35
            ['name' => '\'JD (\'||a.RJ_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD4', 'accK' => 'conf:UGD1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RJ_date', 'd' => '0', 'k' => '(select sum(accdoc_price) from RSTXN_UGDACCDOCS G where G.RJ_no=a.RJ_no)', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #36
            ['name' => '\'JM (\'||a.RJ_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'conf:UGD5', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RJ_date', 'd' => '(select sum(pact_price) from RSTXN_UGDACTPARAMS G where G.RJ_no=a.RJ_no)', 'k' => '0', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #37
            ['name' => '\'JM (\'||a.RJ_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD5', 'accK' => 'conf:UGD1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RJ_date', 'd' => '0', 'k' => '(select sum(pact_price) from RSTXN_UGDACTPARAMS G where G.RJ_no=a.RJ_no)', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #38
            ['name' => '\'JK (\'||a.RJ_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'conf:UGD6', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RJ_date', 'd' => '(select sum(acte_price) from RSTXN_UGDACTEMPS G where G.RJ_no=a.RJ_no)', 'k' => '0', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #39
            ['name' => '\'JK (\'||a.RJ_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD6', 'accK' => 'conf:UGD1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RJ_date', 'd' => '0', 'k' => '(select sum(acte_price) from RSTXN_UGDACTEMPS G where G.RJ_no=a.RJ_no)', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #40
            ['name' => '\'OBAT (\'||a.RJ_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'conf:UGD9', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RJ_date', 'd' => '(select sum((NVL(qty,0)*NVL(price,0))) from RSTXN_UGDOBATS G where G.RJ_no=a.RJ_no)', 'k' => '0', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #41
            ['name' => '\'OBAT (\'||a.RJ_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD9', 'accK' => 'conf:UGD1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RJ_date', 'd' => '0', 'k' => '(select sum((NVL(qty,0)*NVL(price,0))) from RSTXN_UGDOBATS G where G.RJ_no=a.RJ_no)', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #42
            ['name' => '\'LAB (\'||a.RJ_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'conf:UGD7', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RJ_date', 'd' => '(select sum(lab_price) from RSTXN_UGDLABS G where G.RJ_no=a.RJ_no)', 'k' => '0', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #43
            ['name' => '\'LAB (\'||a.RJ_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD7', 'accK' => 'conf:UGD1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RJ_date', 'd' => '0', 'k' => '(select sum(lab_price) from RSTXN_UGDLABS G where G.RJ_no=a.RJ_no)', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #44
            ['name' => '\'RAD (\'||a.RJ_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'conf:UGD10', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RJ_date', 'd' => '(select sum(rad_price) from RSTXN_UGDRADS G where G.RJ_no=a.RJ_no)', 'k' => '0', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #45
            ['name' => '\'RAD (\'||a.RJ_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD10', 'accK' => 'conf:UGD1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RJ_date', 'd' => '0', 'k' => '(select sum(rad_price) from RSTXN_UGDRADS G where G.RJ_no=a.RJ_no)', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #46
            ['name' => '\'LAIN (\'||a.RJ_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'conf:UGD8', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RJ_date', 'd' => '(select sum(other_price) from RSTXN_UGDOTHERS G where G.RJ_no=a.RJ_no)', 'k' => '0', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #47
            ['name' => '\'LAIN (\'||a.RJ_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD8', 'accK' => 'conf:UGD1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RJ_date', 'd' => '0', 'k' => '(select sum(other_price) from RSTXN_UGDOTHERS G where G.RJ_no=a.RJ_no)', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #48
            ['name' => '\'UGD_DISKON (\'||a.RJ_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD12', 'accK' => 'conf:UGD1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RJ_date', 'd' => 'RJ_diskon', 'k' => '0', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #49
            ['name' => '\'UGD_DISKON (\'||a.RJ_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'conf:UGD12', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RJ_date', 'd' => '0', 'k' => 'RJ_diskon', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #50
            ['name' => '\'BAYAR_UGD (\'||a.RJc_desc||\')\'', 'acc' => 'a.acc_id', 'accK' => 'conf:UGD1', 'shift' => 'nvl(a.shift,\'1\')', 'date' => 'rjc_date', 'd' => 'rjc_nominal', 'k' => '0', 'from' => 'RSTXN_UGDCASHINS a,rstxn_UGDhdrs b', 'where' => 'a.RJ_no=b.RJ_no and RJ_status not in(\'A\',\'F\')'],
            // #51
            ['name' => '\'BAYAR_UGD (\'||a.RJC_desc||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'a.acc_id', 'shift' => 'nvl(a.shift,\'1\')', 'date' => 'RJc_date', 'd' => '0', 'k' => 'RJc_nominal', 'from' => 'RSTXN_UGDCASHINS a,rstxn_UGDhdrs b', 'where' => 'a.RJ_no=b.RJ_no and RJ_status not in(\'A\',\'F\')'],
            // #52
            ['name' => '\'RESEP JK (\'||a.sls_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RESEP1', 'accK' => 'conf:RESEP2', 'shift' => 'nvl(shift,\'1\')', 'date' => 'sls_date', 'd' => 'acte_price', 'k' => '0', 'from' => 'IMTXN_SLSHDRS a', 'where' => 'status =\'L\''],
            // #53
            ['name' => '\'RESEP JK (\'||a.sls_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RESEP2', 'accK' => 'conf:RESEP1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'sls_date', 'd' => '0', 'k' => 'acte_price', 'from' => 'IMTXN_SLSHDRS a', 'where' => 'status =\'L\''],
            // #54
            ['name' => '\'RESEP OBAT (\'||a.sls_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RESEP1', 'accK' => 'conf:RESEP3', 'shift' => 'nvl(shift,\'1\')', 'date' => 'sls_date', 'd' => '(select sum(nvl(qty,0)*nvl(sales_price,0)) from IMTXN_SLSDTLS where sls_no=a.sls_no)', 'k' => '0', 'from' => 'IMTXN_SLSHDRS a', 'where' => 'status =\'L\''],
            // #55
            ['name' => '\'RESEP OBAT (\'||a.sls_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RESEP3', 'accK' => 'conf:RESEP1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'sls_date', 'd' => '0', 'k' => '(select sum(nvl(qty,0)*nvl(sales_price,0)) from IMTXN_SLSDTLS where sls_no=a.sls_no)', 'from' => 'IMTXN_SLSHDRS a', 'where' => 'status =\'L\''],
            // #56
            ['name' => '\'BAYAR_RESEP (\'||a.sls_no||\' \'||a.reg_no||\')\'', 'acc' => 'a.acc_id', 'accK' => 'conf:RESEP1', 'shift' => 'nvl(a.shift,\'1\')', 'date' => 'sls_date', 'd' => 'sls_bayar', 'k' => '0', 'from' => 'IMTXN_SLSHDRS a', 'where' => 'status =\'L\''],
            // #57
            ['name' => '\'BAYAR_RESEP (\'||a.sls_no||\' \'||a.reg_no||\')\'', 'acc' => 'conf:RESEP1', 'accK' => 'a.acc_id', 'shift' => 'nvl(a.shift,\'1\')', 'date' => 'sls_date', 'd' => '0', 'k' => 'sls_bayar', 'from' => 'IMTXN_SLSHDRS a', 'where' => 'status =\'L\''],
            // #58
            ['name' => '\'TRF PIUTANG RESEP ke INAP (\'||(select string_agg(sls_no||\' \'||reg_no) from imtxn_slshdrs where rihdr_no=a.rihdr_no)||\')\'', 'acc' => 'conf:RESEPTRFINAP', 'accK' => 'conf:RESEP1', 'shift' => 'nvl(a.shift,\'1\')', 'date' => 'exit_date', 'd' => '(select sum(nvl(ribon_price,0)) from RSTXN_RIBONOBATS where rihdr_no=a.rihdr_no)', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status =\'P\''],
            // #59
            ['name' => '\'TRF PIUTANG RESEP ke INAP (\'||(select string_agg(sls_no||\' \'||reg_no) from imtxn_slshdrs where rihdr_no=a.rihdr_no)||\')\'', 'acc' => 'conf:RESEP1', 'accK' => 'conf:RESEPTRFINAP', 'shift' => 'nvl(a.shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select sum(nvl(ribon_price,0)) from RSTXN_RIBONOBATS where rihdr_no=a.rihdr_no)', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status =\'P\''],
            // #60
            ['name' => '\'TRF PIUTANG UGD ke INAP (\'||(select string_agg(sls_no||\' \'||reg_no) from imtxn_slshdrs where rihdr_no=a.rihdr_no)||\')\'', 'acc' => 'conf:UGDTRFINAP', 'accK' => 'conf:UGD1', 'shift' => 'nvl(a.shift,\'1\')', 'date' => 'exit_date', 'd' => '(select nvl(sum(nvl(rj_admin,0)+ nvl(poli_PRICE,0)+ nvl(acte_price,0)+ nvl(actp_price,0)+ nvl(actd_price,0)+ nvl(obat,0)+ nvl(rad,0)+ nvl(lab,0)+ nvl(other,0)+nvl(rs_admin,0)),0) from RSTXN_RITEMPADMINS where rihdr_no=a.rihdr_no)', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status =\'P\''],
            // #61
            ['name' => '\'TRF PIUTANG UGD ke INAP (\'||(select string_agg(sls_no||\' \'||reg_no) from imtxn_slshdrs where rihdr_no=a.rihdr_no)||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'conf:UGDTRFINAP', 'shift' => 'nvl(a.shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select nvl(sum(nvl(rj_admin,0)+ nvl(poli_PRICE,0)+ nvl(acte_price,0)+ nvl(actp_price,0)+ nvl(actd_price,0)+ nvl(obat,0)+ nvl(rad,0)+ nvl(lab,0)+ nvl(other,0)+nvl(rs_admin,0)),0) from RSTXN_RITEMPADMINS where rihdr_no=a.rihdr_no)', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status =\'P\''],
            // #62
            ['name' => '\'TRF PIUTANG RJ ke UGD (\'||(select rj_no||\' \'||reg_no from RSTXN_UGDBIAYASELAMADIRJS where rj_no_rsugd=a.rj_no)||\')\'', 'acc' => 'conf:RJTRFUGD', 'accK' => 'conf:RJ1', 'shift' => 'nvl(a.shift,\'1\')', 'date' => 'rj_date', 'd' => '(select nvl(sum(total_biayarj),0) from RSTXN_UGDBIAYASELAMADIRJS where rj_no_rsugd=a.rj_no)', 'k' => '0', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #63
            ['name' => '\'TRF PIUTANG RJ ke UGD (\'||(select rj_no||\' \'||reg_no from RSTXN_UGDBIAYASELAMADIRJS where rj_no_rsugd=a.rj_no)||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:RJTRFUGD', 'shift' => 'nvl(a.shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select nvl(sum(total_biayarj),0) from RSTXN_UGDBIAYASELAMADIRJS where rj_no_rsugd=a.rj_no)', 'from' => 'RSTXN_UGDHDRS a', 'where' => 'RJ_status not in(\'A\',\'F\')'],
            // #64
            ['name' => '\'RI ADMIN AGE (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:RI2', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => 'admin_age', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #65
            ['name' => '\'RI ADMIN AGE (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI2', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => 'admin_age', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #66
            ['name' => '\'RI ADMIN STATUS (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:RI3', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => 'admin_status', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #67
            ['name' => '\'RI ADMIN STATUS (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI3', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => 'admin_status', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #68
            ['name' => '\'RI JD (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:RI4', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select nvl(sum(actd_price*actd_qty),0) from rstxn_riactdocs where rihdr_no=a.rihdr_no)', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #69
            ['name' => '\'RI JD (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI4', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select nvl(sum(actd_price*actd_qty),0) from rstxn_riactdocs where rihdr_no=a.rihdr_no)', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #70
            ['name' => '\'RI JM (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:RI5', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select nvl(sum(actp_price*actp_qty),0) from rstxn_riactparams where rihdr_no=a.rihdr_no)', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #71
            ['name' => '\'RI JM (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI5', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select nvl(sum(actp_price*actp_qty),0) from rstxn_riactparams where rihdr_no=a.rihdr_no)', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #72
            ['name' => '\'RI VISIT (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:RI6', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select nvl(sum(visit_price),0) from rstxn_rivisits where rihdr_no=a.rihdr_no)', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #73
            ['name' => '\'RI VISIT (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI6', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select nvl(sum(visit_price),0) from rstxn_rivisits where rihdr_no=a.rihdr_no)', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #74
            ['name' => '\'RI KONSUL (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:RI7', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select nvl(sum(konsul_price),0) from rstxn_rikonsuls where rihdr_no=a.rihdr_no)', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #75
            ['name' => '\'RI KONSUL (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI7', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select nvl(sum(konsul_price),0) from rstxn_rikonsuls where rihdr_no=a.rihdr_no)', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #76
            ['name' => '\'RI LAB (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:RI8', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select nvl(sum(lab_price),0) from rstxn_rilabs where rihdr_no=a.rihdr_no)', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #77
            ['name' => '\'RI LAB (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI8', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select nvl(sum(lab_price),0) from rstxn_rilabs where rihdr_no=a.rihdr_no)', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #78
            ['name' => '\'RI RAD (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:RI9', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select nvl(sum(rirad_price),0) from rstxn_riradiologs where rihdr_no=a.rihdr_no)', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #79
            ['name' => '\'RI RAD (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI9', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select nvl(sum(rirad_price),0) from rstxn_riradiologs where rihdr_no=a.rihdr_no)', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #80
            ['name' => '\'RI OBAT (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:RI10', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select nvl(sum(riobat_qty*riobat_price),0) from rstxn_riobats where rihdr_no=a.rihdr_no)', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #81
            ['name' => '\'RI OBAT (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI10', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select nvl(sum(riobat_qty*riobat_price),0) from rstxn_riobats where rihdr_no=a.rihdr_no)', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #82
            ['name' => '\'RI PERAWATAN (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:RI11', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select sum(nvl(perawatan_price,0)*nvl(DAY, ceil(decode((nvl(end_date,sysdate)-start_date),0,1,(nvl(end_date,sysdate)-start_date)))) ) from rsmst_trfrooms where rihdr_no=a.rihdr_no)', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #83
            ['name' => '\'RI PERAWATAN (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI11', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select sum(nvl(perawatan_price,0)*nvl(DAY, ceil(decode((nvl(end_date,sysdate)-start_date),0,1,(nvl(end_date,sysdate)-start_date)))) ) from rsmst_trfrooms where rihdr_no=a.rihdr_no)', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #84
            ['name' => '\'RI KAMAR (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:RI12', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select sum(nvl(room_price,0)*nvl(DAY, ceil(decode((nvl(end_date,sysdate)-start_date),0,1,(nvl(end_date,sysdate)-start_date)))) ) from rsmst_trfrooms where rihdr_no=a.rihdr_no)', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #85
            ['name' => '\'RI KAMAR (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI12', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select sum(nvl(room_price,0)*nvl(DAY, ceil(decode((nvl(end_date,sysdate)-start_date),0,1,(nvl(end_date,sysdate)-start_date)))) ) from rsmst_trfrooms where rihdr_no=a.rihdr_no)', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #86
            ['name' => '\'RI PELAYANAN UMUM (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:RI13', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select sum(nvl(common_service,0)*nvl(DAY, ceil(decode((nvl(end_date,sysdate)-start_date),0,1,(nvl(end_date,sysdate)-start_date)))) ) from rsmst_trfrooms where rihdr_no=a.rihdr_no)', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #87
            ['name' => '\'RI PELAYANAN UMUM (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI13', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select sum(nvl(common_service,0)*nvl(DAY, ceil(decode((nvl(end_date,sysdate)-start_date),0,1,(nvl(end_date,sysdate)-start_date)))) ) from rsmst_trfrooms where rihdr_no=a.rihdr_no)', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #88
            ['name' => '\'RI LAIN (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:RI14', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select nvl(sum(OTHER_PRICE),0) from RSTXN_RIOTHERS where rihdr_no=a.rihdr_no)', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #89
            ['name' => '\'RI LAIN (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI14', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select nvl(sum(OTHER_PRICE),0) from RSTXN_RIOTHERS where rihdr_no=a.rihdr_no)', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #90
            ['name' => '\'SUBSIDI (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI16', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => 'ri_diskon', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #91
            ['name' => '\'SUBSIDI (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:RI16', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => 'ri_diskon', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #92
            ['name' => '\'OPERATOR (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:OK1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select nvl(sum(oprdoc_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status=\'L\')', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #93
            ['name' => '\'OPERATOR (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK1', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select nvl(sum(oprdoc_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status=\'L\')', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #94
            ['name' => '\'ASIS OPERATOR (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:OK2', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select nvl(sum(asistopr_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status=\'L\')', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #95
            ['name' => '\'ASIS OPERATOR (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK2', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select nvl(sum(asistopr_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status=\'L\')', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #96
            ['name' => '\'ANASTESI (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:OK3', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select nvl(sum(anesdoc_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status=\'L\')', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #97
            ['name' => '\'ANASTESI (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK3', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select nvl(sum(anesdoc_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status=\'L\')', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #98
            ['name' => '\'PENG ANASTESI (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:OK4', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select nvl(sum(changeanesdoc_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status=\'L\')', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #99
            ['name' => '\'PENG ANASTESI (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK4', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select nvl(sum(changeanesdoc_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status=\'L\')', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #100
            ['name' => '\'ASIS ANASTESI (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:OK5', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select nvl(sum(asistanes_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status=\'L\')', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #101
            ['name' => '\'ASIS ANASTESI (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK5', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select nvl(sum(asistanes_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status=\'L\')', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #102
            ['name' => '\'INSTRUMENT (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:OK6', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select nvl(sum(instrument_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status=\'L\')', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #103
            ['name' => '\'INSTRUMENT (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK6', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select nvl(sum(instrument_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status=\'L\')', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #104
            ['name' => '\'OMLOP (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:OK7', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select nvl(sum(omlop_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status=\'L\')', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #105
            ['name' => '\'OMLOP (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK7', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select nvl(sum(omlop_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status=\'L\')', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #106
            ['name' => '\'RR (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:OK8', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select nvl(sum(rr_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status=\'L\')', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #107
            ['name' => '\'RR (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK8', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select nvl(sum(rr_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status=\'L\')', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #108
            ['name' => '\'OK FEE (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:OK9', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select nvl(sum(ok_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status=\'L\')', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #109
            ['name' => '\'OK FEE (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK9', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select nvl(sum(ok_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status=\'L\')', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #110
            ['name' => '\'BAHAN (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:OK10', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select nvl(sum(equipment_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status=\'L\')', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #111
            ['name' => '\'BAHAN (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK10', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select nvl(sum(equipment_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status=\'L\')', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #112
            ['name' => '\'OPERATOR (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:OK11', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select nvl(sum(rentequipment_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status=\'L\')', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #113
            ['name' => '\'SEWA ALAT (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK11', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select nvl(sum(rentequipment_fee),0) from RSTXN_OKS where rihdr_no=a.rihdr_no and ok_status=\'L\')', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #114
            ['name' => '\'BAYAR_RI (\'||(select reg_name||\' / \'||x.reg_no||\'\' from rsmst_pasiens x where x.reg_no=b.reg_no)||\')\'', 'acc' => 'a.acc_id', 'accK' => 'conf:RI1', 'shift' => 'nvl(a.shift,\'1\')', 'date' => 'ripay_date', 'd' => 'ripay_bayar', 'k' => '0', 'from' => 'RSTXN_RIPAYMENTPDTLS a,RSTXN_RIHDRS b', 'where' => 'a.Rihdr_no=b.Rihdr_no and ri_status=\'P\''],
            // #115
            ['name' => '\'BAYAR_RI (\'||(select reg_name||\' / \'||x.reg_no||\'\' from rsmst_pasiens x where x.reg_no=b.reg_no)||\')\'', 'acc' => 'conf:RI1', 'accK' => 'a.acc_id', 'shift' => 'nvl(a.shift,\'1\')', 'date' => 'ripay_date', 'd' => '0', 'k' => 'ripay_bayar', 'from' => 'RSTXN_RIPAYMENTPDTLS a,RSTXN_RIHDRS b', 'where' => 'a.Rihdr_no=b.Rihdr_no and ri_status=\'P\''],
            // #116
            ['name' => '\'ANGSURAN AWAL (\'||a.RIhdr_no||\')\'', 'acc' => 'acc_id', 'accK' => 'conf:RIANGAWAL', 'shift' => 'nvl(shift,\'1\')', 'date' => 'ripay_date', 'd' => 'ripay_bayar', 'k' => '0', 'from' => 'RSTXN_RIPAYMENTDTLS a', 'where' => ''],
            // #117
            ['name' => '\'ANGSURAN AWAL (\'||a.RIhdr_no||\')\'', 'acc' => 'conf:RIANGAWAL', 'accK' => 'acc_id', 'shift' => 'nvl(shift,\'1\')', 'date' => 'ripay_date', 'd' => '0', 'k' => 'ripay_bayar', 'from' => 'RSTXN_RIPAYMENTDTLS a', 'where' => ''],
            // #118
            ['name' => '\'PENGEMBALIAN ANGSURAN AWAL (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RIANGAWAL', 'accK' => 'acc_id', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select nvl(sum(ripay_bayar),0) from RSTXN_RIPAYMENTDTLS where rihdr_no=a.rihdr_no)', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #119
            ['name' => '\'PENGEMBALIAN ANGSURAN AWAL (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'acc_id', 'accK' => 'conf:RIANGAWAL', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select nvl(sum(ripay_bayar),0) from RSTXN_RIPAYMENTDTLS where rihdr_no=a.rihdr_no)', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #120
            ['name' => '\'PENGEMBALIAN ANGSURAN AWAL P (\'||a.RIhdr_no||\')\'', 'acc' => 'conf:R1', 'accK' => 'b.acc_id', 'shift' => 'nvl(b.shift,\'1\')', 'date' => 'ripay_date', 'd' => 'nvl(ripay_bayar,0)', 'k' => '0', 'from' => 'RSTXN_RIHDRS a,RSTXN_RIPAYMENTPKDTLS b', 'where' => 'a.rihdr_no=b.rihdr_no and ri_status=\'P\''],
            // #121
            ['name' => '\'PENGEMBALIAN ANGSURAN AWAL P (\'||a.RIhdr_no||\')\'', 'acc' => 'b.acc_id', 'accK' => 'conf:R1', 'shift' => 'nvl(b.shift,\'1\')', 'date' => 'ripay_date', 'd' => '0', 'k' => 'nvl(ripay_bayar,0)', 'from' => 'RSTXN_RIHDRS a,RSTXN_RIPAYMENTPKDTLS b', 'where' => 'a.rihdr_no=b.rihdr_no and ri_status=\'P\''],
            // #122
            ['name' => '\'BAYAR PBF / \'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)', 'acc' => 'a.acc_id', 'accK' => 'conf:RCV1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'cashout_date', 'd' => '0', 'k' => 'cashout_value', 'from' => 'IMTXN_CASHOUTHDRS a', 'where' => 'nvl(cashout_value,0)>0'],
            // #123
            ['name' => '\'BAYAR PBF / \'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)', 'acc' => 'conf:RCV1', 'accK' => 'a.acc_id', 'shift' => 'nvl(shift,\'1\')', 'date' => 'cashout_date', 'd' => 'cashout_value', 'k' => '0', 'from' => 'IMTXN_CASHOUTHDRS a', 'where' => 'nvl(cashout_value,0)>0'],
            // #124
            ['name' => '\'BAYAR PBF TOPUP/ \'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)', 'acc' => 'conf:RCV6', 'accK' => 'conf:RCV1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'cashout_date', 'd' => '0', 'k' => 'cashout_value_topup', 'from' => 'IMTXN_CASHOUTHDRS a', 'where' => 'nvl(cashout_value_topup,0)>0'],
            // #125
            ['name' => '\'BAYAR PBF TOPUP/ \'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)', 'acc' => 'conf:RCV1', 'accK' => 'conf:RCV6', 'shift' => 'nvl(shift,\'1\')', 'date' => 'cashout_date', 'd' => 'cashout_value_topup', 'k' => '0', 'from' => 'IMTXN_CASHOUTHDRS a', 'where' => 'nvl(cashout_value_topup,0)>0'],
            // #126
            ['name' => '\'BAYAR DIMUKA PBF / \'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)', 'acc' => 'a.acc_id', 'accK' => 'conf:RCV6', 'shift' => 'nvl(shift,\'1\')', 'date' => 'cashout_date', 'd' => '0', 'k' => 'cashout_value', 'from' => 'IMTXN_CASHOUTHDRTOPUPS a', 'where' => ''],
            // #127
            ['name' => '\'BAYAR DIMUKA PBF / \'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)', 'acc' => 'conf:RCV6', 'accK' => 'a.acc_id', 'shift' => 'nvl(shift,\'1\')', 'date' => 'cashout_date', 'd' => 'cashout_value', 'k' => '0', 'from' => 'IMTXN_CASHOUTHDRTOPUPS a', 'where' => ''],
            // #128
            ['name' => '\'RCV TRANSAKSI\'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)||\' \'||a.rcv_no', 'acc' => 'conf:RCV2', 'accK' => 'conf:RCV1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RCV_date', 'd' => '(select nvl(sum(nvl(qty,0)*nvl(cost_price,0)),0) from imtxn_receivedtls where RCV_no=a.RCV_no)', 'k' => '0', 'from' => 'imtxn_receiveHDRS a', 'where' => 'RCV_status in (\'H\',\'L\')'],
            // #129
            ['name' => '\'RCV TRANSAKSI\'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)||\' \'||a.rcv_no', 'acc' => 'conf:RCV1', 'accK' => 'conf:RCV2', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RCV_date', 'd' => '0', 'k' => '(select nvl(sum(nvl(qty,0)*nvl(cost_price,0)),0) from imtxn_receivedtls where RCV_no=a.RCV_no)', 'from' => 'imtxn_receiveHDRS a', 'where' => 'RCV_status in (\'H\',\'L\')'],
            // #130
            ['name' => '\'RCV DISKON ITEM TRANSAKSI\'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)||\' \'||a.rcv_no', 'acc' => 'conf:RCV3', 'accK' => 'conf:RCV1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RCV_date', 'd' => '0', 'k' => '(select sum(nvl(qty,0)*nvl(cost_price,0))- sum( /*persen1*/(nvl(qty,0)*nvl(cost_price,0))/**/-/**/((nvl(qty,0)*nvl(cost_price,0))*nvl(dtl_persen,0)/100)/**/-/**/nvl(dtl_diskon,0)- /*persen2*/(((nvl(qty,0)*nvl(cost_price,0))/**/-/**/((nvl(qty,0)*nvl(cost_price,0))*nvl(dtl_persen,0)/100)/**/-/**/nvl(dtl_diskon,0))* (nvl(dtl_persen1,0)/100))-/**/nvl(dtl_diskon1,0)) from imtxn_receivedtls where RCV_no=a.RCV_no)', 'from' => 'imtxn_receiveHDRS a', 'where' => 'RCV_status in (\'H\',\'L\')'],
            // #131
            ['name' => '\'RCV DISKON ITEM TRANSAKSI\'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)||\' \'||a.rcv_no', 'acc' => 'conf:RCV1', 'accK' => 'conf:RCV3', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RCV_date', 'd' => '(select sum(nvl(qty,0)*nvl(cost_price,0))- sum( /*persen1*/(nvl(qty,0)*nvl(cost_price,0))/**/-/**/((nvl(qty,0)*nvl(cost_price,0))*nvl(dtl_persen,0)/100)/**/-/**/nvl(dtl_diskon,0)- /*persen2*/(((nvl(qty,0)*nvl(cost_price,0))/**/-/**/((nvl(qty,0)*nvl(cost_price,0))*nvl(dtl_persen,0)/100)/**/-/**/nvl(dtl_diskon,0))* (nvl(dtl_persen1,0)/100))-/**/nvl(dtl_diskon1,0)) from imtxn_receivedtls where RCV_no=a.RCV_no)', 'k' => '0', 'from' => 'imtxn_receiveHDRS a', 'where' => 'RCV_status in (\'H\',\'L\')'],
            // #132
            ['name' => '\'RCV DISKON TOTAL TRANSAKSI\'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)||\' \'||a.rcv_no', 'acc' => 'conf:RCV3', 'accK' => 'conf:RCV1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RCV_date', 'd' => '0', 'k' => 'nvl(RCV_diskon,0)', 'from' => 'imtxn_receiveHDRS a', 'where' => 'RCV_status in (\'H\',\'L\')'],
            // #133
            ['name' => '\'RCV DISKON TOTAL TRANSAKSI\'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)||\' \'||a.rcv_no', 'acc' => 'conf:RCV1', 'accK' => 'conf:RCV3', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RCV_date', 'd' => 'nvl(RCV_diskon,0)', 'k' => '0', 'from' => 'imtxn_receiveHDRS a', 'where' => 'RCV_status in (\'H\',\'L\') and nvl(RCV_diskon,0)>0'],
            // #134
            ['name' => '\'RCV MATERAI TRANSAKSI\'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)||\' \'||a.rcv_no', 'acc' => 'conf:RCV1', 'accK' => 'conf:RCV5', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RCV_date', 'd' => '0', 'k' => 'nvl(RCV_materai,0)', 'from' => 'imtxn_receiveHDRS a', 'where' => 'RCV_status in (\'H\',\'L\')'],
            // #135
            ['name' => '\'RCV MATERAI TRANSAKSI\'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)||\' \'||a.rcv_no', 'acc' => 'conf:RCV5', 'accK' => 'conf:RCV1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RCV_date', 'd' => 'nvl(RCV_materai,0)', 'k' => '0', 'from' => 'imtxn_receiveHDRS a', 'where' => 'RCV_status in (\'H\',\'L\') and nvl(RCV_materai,0)>0'],
            // #136
            ['name' => '\'RCV PPN TRANSAKSI\'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)||\' \'||a.rcv_no', 'acc' => 'conf:RCV1', 'accK' => 'conf:RCV4', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RCV_date', 'd' => '0', 'k' => 'nvl(totalppn,0)', 'from' => 'TKVIEW_RCVHDRS a', 'where' => 'RCV_status in (\'H\',\'L\')'],
            // #137
            ['name' => '\'RCV PPN TRANSAKSI\'||(select supp_name from immst_suppliers x where x.supp_id=a.supp_id)||\' \'||a.rcv_no', 'acc' => 'conf:RCV4', 'accK' => 'conf:RCV1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'RCV_date', 'd' => 'nvl(totalppn,0)', 'k' => '0', 'from' => 'TKVIEW_RCVHDRS a', 'where' => 'RCV_status in (\'H\',\'L\') and nvl(totalppn,0)>0'],
            // #138
            ['name' => '\'RTN RJ (\'||a.rtn_desc||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:PAPOTEK', 'accK' => 'acc_id', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rtn_date', 'd' => '(select nvl(sum(qty*rtn_prise),0) from IMTXN_RTNDTLS where rtn_no=a.rtn_no)', 'k' => '0', 'from' => 'IMTXN_RTNHDRS a', 'where' => 'rtn_status=\'L\''],
            // #139
            ['name' => '\'RTN OBAT (\'||a.rtn_desc||\'/\'||a.reg_no||\')\'', 'acc' => 'acc_id', 'accK' => 'conf:PAPOTEK', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rtn_date', 'd' => '0', 'k' => '(select nvl(sum(qty*rtn_prise),0) from IMTXN_RTNDTLS where rtn_no=a.rtn_no)', 'from' => 'IMTXN_RTNHDRS a', 'where' => 'rtn_status=\'L\''],
            // #140
            ['name' => '\'RTN RJ (\'||a.rtn_desc||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ13', 'accK' => 'conf:RJ1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rtn_date', 'd' => '(select nvl(sum(qty*rtn_prise),0) from IMTXN_RTNDTLS where rtn_no=a.rtn_no)', 'k' => '0', 'from' => 'IMTXN_RTNHDRS a', 'where' => 'rtn_status=\'L\''],
            // #141
            ['name' => '\'RTN OBAT (\'||a.rtn_desc||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:RJ13', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rtn_date', 'd' => '0', 'k' => '(select nvl(sum(qty*rtn_prise),0) from IMTXN_RTNDTLS where rtn_no=a.rtn_no)', 'from' => 'IMTXN_RTNHDRS a', 'where' => 'rtn_status=\'L\''],
            // #142
            ['name' => '\'RTN RI (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI15', 'accK' => 'conf:RI1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '(select nvl(sum(riobat_qty*riobat_price),0) from rstxn_riobatrtns where rihdr_no=a.rihdr_no)', 'k' => '0', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #143
            ['name' => '\'RTN OBAT (\'||a.RIhdr_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RI1', 'accK' => 'conf:RI15', 'shift' => 'nvl(shift,\'1\')', 'date' => 'exit_date', 'd' => '0', 'k' => '(select nvl(sum(riobat_qty*riobat_price),0) from rstxn_riobatrtns where rihdr_no=a.rihdr_no)', 'from' => 'RSTXN_RIHDRS a', 'where' => 'ri_status=\'P\''],
            // #144
            ['name' => '\'OPERATOR RJ (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:OK1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select nvl(sum(oprdoc_fee),0) from RSTXN_OKS where status_rjri=\'RJ\' and ref_no=a.rj_no and ok_status=\'L\')', 'k' => '0', 'from' => 'rstxn_rjhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'RJ\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #145
            ['name' => '\'OPERATOR RJ (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK1', 'accK' => 'conf:RJ1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select nvl(sum(oprdoc_fee),0) from RSTXN_OKS where status_rjri=\'RJ\' and ref_no=a.rj_no and ok_status=\'L\')', 'from' => 'rstxn_rjhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'RJ\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #146
            ['name' => '\'ASIS OPERATOR RJ (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:OK2', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select nvl(sum(asistopr_fee),0) from RSTXN_OKS where status_rjri=\'RJ\' and ref_no=a.rj_no and ok_status=\'L\')', 'k' => '0', 'from' => 'rstxn_rjhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'RJ\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #147
            ['name' => '\'ASIS OPERATOR RJ (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK2', 'accK' => 'conf:RJ1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select nvl(sum(asistopr_fee),0) from RSTXN_OKS where status_rjri=\'RJ\' and ref_no=a.rj_no and ok_status=\'L\')', 'from' => 'rstxn_rjhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'RJ\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #148
            ['name' => '\'ANASTESI RJ (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:OK3', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select nvl(sum(anesdoc_fee),0) from RSTXN_OKS where status_rjri=\'RJ\' and ref_no=a.rj_no and ok_status=\'L\')', 'k' => '0', 'from' => 'rstxn_rjhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'RJ\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #149
            ['name' => '\'ANASTESI RJ (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK3', 'accK' => 'conf:RJ1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select nvl(sum(anesdoc_fee),0) from RSTXN_OKS where status_rjri=\'RJ\' and ref_no=a.rj_no and ok_status=\'L\')', 'from' => 'rstxn_rjhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'RJ\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #150
            ['name' => '\'PENG ANASTESI RJ (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:OK4', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select nvl(sum(changeanesdoc_fee),0) from RSTXN_OKS where status_rjri=\'RJ\' and ref_no=a.rj_no and ok_status=\'L\')', 'k' => '0', 'from' => 'rstxn_rjhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'RJ\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #151
            ['name' => '\'PENG ANASTESI RJ (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK4', 'accK' => 'conf:RJ1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select nvl(sum(changeanesdoc_fee),0) from RSTXN_OKS where status_rjri=\'RJ\' and ref_no=a.rj_no and ok_status=\'L\')', 'from' => 'rstxn_rjhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'RJ\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #152
            ['name' => '\'ASIS ANASTESI RJ (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:OK5', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select nvl(sum(asistanes_fee),0) from RSTXN_OKS where status_rjri=\'RJ\' and ref_no=a.rj_no and ok_status=\'L\')', 'k' => '0', 'from' => 'rstxn_rjhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'RJ\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #153
            ['name' => '\'ASIS ANASTESI RJ (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK5', 'accK' => 'conf:RJ1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select nvl(sum(asistanes_fee),0) from RSTXN_OKS where status_rjri=\'RJ\' and ref_no=a.rj_no and ok_status=\'L\')', 'from' => 'rstxn_rjhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'RJ\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #154
            ['name' => '\'INSTRUMENT RJ (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:OK6', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select nvl(sum(instrument_fee),0) from RSTXN_OKS where status_rjri=\'RJ\' and ref_no=a.rj_no and ok_status=\'L\')', 'k' => '0', 'from' => 'rstxn_rjhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'RJ\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #155
            ['name' => '\'INSTRUMENT RJ (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK6', 'accK' => 'conf:RJ1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select nvl(sum(instrument_fee),0) from RSTXN_OKS where status_rjri=\'RJ\' and ref_no=a.rj_no and ok_status=\'L\')', 'from' => 'rstxn_rjhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'RJ\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #156
            ['name' => '\'OMLOP RJ (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:OK7', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select nvl(sum(omlop_fee),0) from RSTXN_OKS where status_rjri=\'RJ\' and ref_no=a.rj_no and ok_status=\'L\')', 'k' => '0', 'from' => 'rstxn_rjhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'RJ\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #157
            ['name' => '\'OMLOP RJ (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK7', 'accK' => 'conf:RJ1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select nvl(sum(omlop_fee),0) from RSTXN_OKS where status_rjri=\'RJ\' and ref_no=a.rj_no and ok_status=\'L\')', 'from' => 'rstxn_rjhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'RJ\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #158
            ['name' => '\'RR RJ (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:OK8', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select nvl(sum(rr_fee),0) from RSTXN_OKS where status_rjri=\'RJ\' and ref_no=a.rj_no and ok_status=\'L\')', 'k' => '0', 'from' => 'rstxn_rjhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'RJ\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #159
            ['name' => '\'RR RJ (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK8', 'accK' => 'conf:RJ1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select nvl(sum(rr_fee),0) from RSTXN_OKS where status_rjri=\'RJ\' and ref_no=a.rj_no and ok_status=\'L\')', 'from' => 'rstxn_rjhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'RJ\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #160
            ['name' => '\'OK FEE RJ (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:OK9', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select nvl(sum(ok_fee),0) from RSTXN_OKS where status_rjri=\'RJ\' and ref_no=a.rj_no and ok_status=\'L\')', 'k' => '0', 'from' => 'rstxn_rjhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'RJ\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #161
            ['name' => '\'OK FEE RJ (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK9', 'accK' => 'conf:RJ1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select nvl(sum(ok_fee),0) from RSTXN_OKS where status_rjri=\'RJ\' and ref_no=a.rj_no and ok_status=\'L\')', 'from' => 'rstxn_rjhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'RJ\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #162
            ['name' => '\'BAHAN RJ (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:OK10', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select nvl(sum(equipment_fee),0) from RSTXN_OKS where status_rjri=\'RJ\' and ref_no=a.rj_no and ok_status=\'L\')', 'k' => '0', 'from' => 'rstxn_rjhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'RJ\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #163
            ['name' => '\'BAHAN RJ (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK10', 'accK' => 'conf:RJ1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select nvl(sum(equipment_fee),0) from RSTXN_OKS where status_rjri=\'RJ\' and ref_no=a.rj_no and ok_status=\'L\')', 'from' => 'rstxn_rjhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'RJ\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #164
            ['name' => '\'SEWA ALAT RJ (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:RJ1', 'accK' => 'conf:OK11', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select nvl(sum(rentequipment_fee),0) from RSTXN_OKS where status_rjri=\'RJ\' and ref_no=a.rj_no and ok_status=\'L\')', 'k' => '0', 'from' => 'rstxn_rjhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'RJ\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #165
            ['name' => '\'SEWA ALAT RJ (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK11', 'accK' => 'conf:RJ1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select nvl(sum(rentequipment_fee),0) from RSTXN_OKS where status_rjri=\'RJ\' and ref_no=a.rj_no and ok_status=\'L\')', 'from' => 'rstxn_rjhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'RJ\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #166
            ['name' => '\'OPERATOR UGD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'conf:OK1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select nvl(sum(oprdoc_fee),0) from RSTXN_OKS where status_rjri=\'UGD\' and ref_no=a.rj_no and ok_status=\'L\')', 'k' => '0', 'from' => 'rstxn_ugdhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'UGD\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #167
            ['name' => '\'OPERATOR UGD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK1', 'accK' => 'conf:UGD1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select nvl(sum(oprdoc_fee),0) from RSTXN_OKS where status_rjri=\'UGD\' and ref_no=a.rj_no and ok_status=\'L\')', 'from' => 'rstxn_ugdhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'UGD\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #168
            ['name' => '\'ASIS OPERATOR UGD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'conf:OK2', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select nvl(sum(asistopr_fee),0) from RSTXN_OKS where status_rjri=\'UGD\' and ref_no=a.rj_no and ok_status=\'L\')', 'k' => '0', 'from' => 'rstxn_ugdhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'UGD\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #169
            ['name' => '\'ASIS OPERATOR UGD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK2', 'accK' => 'conf:UGD1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select nvl(sum(asistopr_fee),0) from RSTXN_OKS where status_rjri=\'UGD\' and ref_no=a.rj_no and ok_status=\'L\')', 'from' => 'rstxn_ugdhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'UGD\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #170
            ['name' => '\'ANASTESI UGD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'conf:OK3', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select nvl(sum(anesdoc_fee),0) from RSTXN_OKS where status_rjri=\'UGD\' and ref_no=a.rj_no and ok_status=\'L\')', 'k' => '0', 'from' => 'rstxn_ugdhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'UGD\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #171
            ['name' => '\'ANASTESI UGD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK3', 'accK' => 'conf:UGD1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select nvl(sum(anesdoc_fee),0) from RSTXN_OKS where status_rjri=\'UGD\' and ref_no=a.rj_no and ok_status=\'L\')', 'from' => 'rstxn_ugdhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'UGD\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #172
            ['name' => '\'PENG ANASTESI UGD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'conf:OK4', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select nvl(sum(changeanesdoc_fee),0) from RSTXN_OKS where status_rjri=\'UGD\' and ref_no=a.rj_no and ok_status=\'L\')', 'k' => '0', 'from' => 'rstxn_ugdhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'UGD\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #173
            ['name' => '\'PENG ANASTESI UGD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK4', 'accK' => 'conf:UGD1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select nvl(sum(changeanesdoc_fee),0) from RSTXN_OKS where status_rjri=\'UGD\' and ref_no=a.rj_no and ok_status=\'L\')', 'from' => 'rstxn_ugdhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'UGD\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #174
            ['name' => '\'ASIS ANASTESI UGD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'conf:OK5', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select nvl(sum(asistanes_fee),0) from RSTXN_OKS where status_rjri=\'UGD\' and ref_no=a.rj_no and ok_status=\'L\')', 'k' => '0', 'from' => 'rstxn_ugdhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'UGD\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #175
            ['name' => '\'ASIS ANASTESI UGD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK5', 'accK' => 'conf:UGD1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select nvl(sum(asistanes_fee),0) from RSTXN_OKS where status_rjri=\'UGD\' and ref_no=a.rj_no and ok_status=\'L\')', 'from' => 'rstxn_ugdhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'UGD\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #176
            ['name' => '\'INSTRUMENT UGD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'conf:OK6', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select nvl(sum(instrument_fee),0) from RSTXN_OKS where status_rjri=\'UGD\' and ref_no=a.rj_no and ok_status=\'L\')', 'k' => '0', 'from' => 'rstxn_ugdhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'UGD\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #177
            ['name' => '\'INSTRUMENT UGD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK6', 'accK' => 'conf:UGD1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select nvl(sum(instrument_fee),0) from RSTXN_OKS where status_rjri=\'UGD\' and ref_no=a.rj_no and ok_status=\'L\')', 'from' => 'rstxn_ugdhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'UGD\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #178
            ['name' => '\'OMLOP UGD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'conf:OK7', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select nvl(sum(omlop_fee),0) from RSTXN_OKS where status_rjri=\'UGD\' and ref_no=a.rj_no and ok_status=\'L\')', 'k' => '0', 'from' => 'rstxn_ugdhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'UGD\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #179
            ['name' => '\'OMLOP UGD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK7', 'accK' => 'conf:UGD1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select nvl(sum(omlop_fee),0) from RSTXN_OKS where status_rjri=\'UGD\' and ref_no=a.rj_no and ok_status=\'L\')', 'from' => 'rstxn_ugdhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'UGD\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #180
            ['name' => '\'RR UGD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'conf:OK8', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select nvl(sum(rr_fee),0) from RSTXN_OKS where status_rjri=\'UGD\' and ref_no=a.rj_no and ok_status=\'L\')', 'k' => '0', 'from' => 'rstxn_ugdhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'UGD\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #181
            ['name' => '\'RR UGD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK8', 'accK' => 'conf:UGD1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select nvl(sum(rr_fee),0) from RSTXN_OKS where status_rjri=\'UGD\' and ref_no=a.rj_no and ok_status=\'L\')', 'from' => 'rstxn_ugdhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'UGD\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #182
            ['name' => '\'OK FEE UGD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'conf:OK9', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select nvl(sum(ok_fee),0) from RSTXN_OKS where status_rjri=\'UGD\' and ref_no=a.rj_no and ok_status=\'L\')', 'k' => '0', 'from' => 'rstxn_ugdhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'UGD\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #183
            ['name' => '\'OK FEE UGD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK9', 'accK' => 'conf:UGD1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select nvl(sum(ok_fee),0) from RSTXN_OKS where status_rjri=\'UGD\' and ref_no=a.rj_no and ok_status=\'L\')', 'from' => 'rstxn_ugdhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'UGD\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #184
            ['name' => '\'BAHAN UGD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'conf:OK10', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select nvl(sum(equipment_fee),0) from RSTXN_OKS where status_rjri=\'UGD\' and ref_no=a.rj_no and ok_status=\'L\')', 'k' => '0', 'from' => 'rstxn_ugdhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'UGD\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #185
            ['name' => '\'BAHAN UGD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK10', 'accK' => 'conf:UGD1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select nvl(sum(equipment_fee),0) from RSTXN_OKS where status_rjri=\'UGD\' and ref_no=a.rj_no and ok_status=\'L\')', 'from' => 'rstxn_ugdhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'UGD\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #186
            ['name' => '\'SEWA ALAT UGD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:UGD1', 'accK' => 'conf:OK11', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '(select nvl(sum(rentequipment_fee),0) from RSTXN_OKS where status_rjri=\'UGD\' and ref_no=a.rj_no and ok_status=\'L\')', 'k' => '0', 'from' => 'rstxn_ugdhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'UGD\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
            // #187
            ['name' => '\'SEWA ALAT UGD (\'||a.rj_no||\'/\'||a.reg_no||\')\'', 'acc' => 'conf:OK11', 'accK' => 'conf:UGD1', 'shift' => 'nvl(shift,\'1\')', 'date' => 'rj_date', 'd' => '0', 'k' => '(select nvl(sum(rentequipment_fee),0) from RSTXN_OKS where status_rjri=\'UGD\' and ref_no=a.rj_no and ok_status=\'L\')', 'from' => 'rstxn_ugdhdrs a', 'where' => 'rj_status not in(\'A\',\'F\') and exists (select 1 from RSTXN_OKS x where x.status_rjri=\'UGD\' and x.ref_no=a.rj_no and x.ok_status=\'L\')'],
        ];
    }
}
