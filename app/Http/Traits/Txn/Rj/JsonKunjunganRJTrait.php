<?php

namespace App\Http\Traits\Txn\Rj;

use App\Support\OracleLob;

/**
 * Baca JSON kunjungan Rawat Jalan (datadaftarpolirj_json) untuk layar DAFTAR — hanya untuk rj_no
 * yang dibutuhkan (baris halaman aktif, atau seluruh baris bila urutan/filter
 * memang butuh isi JSON). Query besar daftar JANGAN memuat CLOB ini.
 *
 * Trait kecil terpisah (bukan di EmrRJTrait) supaya layar daftar tak perlu
 * menarik trait EMR besar yang rawan tabrakan nama method.
 */
trait JsonKunjunganRJTrait
{
    /** @return array<rj_no, string> string JSON mentah; gagal dibaca = '' */
    protected function bacaJsonKunjunganRJ(array $rjNoList): array
    {
        return OracleLob::readMany('rstxn_rjhdrs', 'rj_no', $rjNoList, 'datadaftarpolirj_json');
    }
}
