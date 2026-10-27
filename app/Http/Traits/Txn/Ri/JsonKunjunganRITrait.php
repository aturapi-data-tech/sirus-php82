<?php

namespace App\Http\Traits\Txn\Ri;

use App\Support\OracleLob;

/**
 * Baca JSON kunjungan Rawat Inap (datadaftarri_json) untuk layar DAFTAR — hanya untuk rihdr_no
 * yang dibutuhkan (baris halaman aktif, atau seluruh baris bila urutan/filter
 * memang butuh isi JSON). Query besar daftar JANGAN memuat CLOB ini.
 *
 * Trait kecil terpisah (bukan di EmrRITrait) supaya layar daftar tak perlu
 * menarik trait EMR besar yang rawan tabrakan nama method.
 */
trait JsonKunjunganRITrait
{
    /** @return array<rihdr_no, string> string JSON mentah; gagal dibaca = '' */
    protected function bacaJsonKunjunganRI(array $riHdrNoList): array
    {
        return OracleLob::readMany('rstxn_rihdrs', 'rihdr_no', $riHdrNoList, 'datadaftarri_json');
    }
}
