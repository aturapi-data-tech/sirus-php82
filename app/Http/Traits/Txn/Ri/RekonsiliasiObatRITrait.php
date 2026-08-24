<?php

namespace App\Http\Traits\Txn\Ri;

use App\Support\RekonsiliasiObat;
use Illuminate\Support\Facades\DB;

/**
 * Penarikan Rekonsiliasi Obat dari kunjungan UGD asal transfer, untuk pasien RI.
 *
 * Dipakai DUA pintu yang sama-sama menulis ke
 * pengkajianDokter.anamnesa.rekonsiliasiObat:
 *   - EMR RI → Pengkajian Dokter (dokter)
 *   - Daftar RI → titik-3 → Rekonsiliasi Obat (apoteker)
 *
 * Keduanya harus memakai aturan prefill yang SAMA; kalau salah satu menyimpan
 * duluan dengan aturan berbeda, key rekonsiliasiObat jadi ada dan pintu satunya
 * kehilangan kesempatan prefill selamanya.
 *
 * Pemakai WAJIB juga memakai EmrUGDTrait (butuh findDataUGD()).
 */
trait RekonsiliasiObatRITrait
{
    /**
     * Rekonsiliasi obat dari kunjungan UGD yang MENTRANSFER pasien ini ke ranap.
     *
     * Relasi UGD→RI disimpan permanen saat transfer di rstxn_ribiayaselamadugds
     * (rj_no = UGD, ugd_no_rsri = rihdr_no RI), jadi asalnya pasti — tidak menebak
     * lewat reg_no/tanggal. Bentuk entri di UGD & RI sudah identik, jadi disalin
     * apa adanya; record UGD lama yang belum punya dua field keputusan dinormalkan
     * ke 'Tidak' supaya tampilan/cetak RI tidak kosong.
     */
    protected function rekonsiliasiObatDariUgd(string $riHdrNo): array
    {
        $rjNoUgd = DB::table('rstxn_ribiayaselamadugds')->where('ugd_no_rsri', $riHdrNo)->orderByDesc('rj_no')->value('rj_no');

        if (empty($rjNoUgd)) {
            return [];
        }

        $ugd = $this->findDataUGD((int) $rjNoUgd);

        return RekonsiliasiObat::normalkanDaftar(data_get($ugd, 'anamnesa.rekonsiliasiObat', []));
    }

    /** Gabung daftar obat UGD ke daftar RI — hanya yang belum ada (dedupe nama obat). */
    protected function gabungRekonsiliasiObat(array $daftarRi, array $daftarUgd): array
    {
        return RekonsiliasiObat::gabung($daftarRi, $daftarUgd);
    }
}
