<?php

namespace App\Http\Traits\Txn\Ugd;

use App\Support\OracleLob;

/**
 * Baca JSON kunjungan UGD (datadaftarugd_json) untuk layar DAFTAR — hanya untuk rj_no
 * yang dibutuhkan (baris halaman aktif, atau seluruh baris bila urutan/filter
 * memang butuh isi JSON). Query besar daftar JANGAN memuat CLOB ini.
 *
 * Trait kecil terpisah (bukan di EmrUGDTrait) supaya layar daftar tak perlu
 * menarik trait EMR besar yang rawan tabrakan nama method.
 */
trait JsonKunjunganUGDTrait
{
    /** @return array<rj_no, string> string JSON mentah; gagal dibaca = '' */
    protected function bacaJsonKunjunganUGD(array $rjNoList): array
    {
        return OracleLob::readMany('rstxn_ugdhdrs', 'rj_no', $rjNoList, 'datadaftarugd_json');
    }
}
