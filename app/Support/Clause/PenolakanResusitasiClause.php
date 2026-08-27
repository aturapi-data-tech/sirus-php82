<?php

namespace App\Support\Clause;

/**
 * Registry TEKS KLAUSUL Surat Pernyataan Penolakan Tindakan Resusitasi (DNR) — SUMBER TUNGGAL.
 *
 * Pola sama dengan App\Support\Clause\PenolakanObatClause (lihat docs/clause-versioning.md +
 * skill clause-versioning): dokumen bertanda tangan harus bisa dicetak ulang dengan redaksi
 * SAAT DITANDATANGANI walau teks/kebijakan diubah kemudian.
 *
 * Saat teks berubah:
 *   1. TAMBAH key versi baru (mis. 'v2'), JANGAN ubah versi lama.
 *   2. Naikkan CURRENT.
 *
 * Isi mengikuti praktik formulir DNR rumah sakit di Indonesia: pernyataan menolak Resusitasi
 * Jantung Paru bila terjadi henti jantung/henti napas, penegasan bahwa perawatan lain TETAP
 * diberikan, serta hak mencabut keputusan sewaktu-waktu.
 */
class PenolakanResusitasiClause
{
    /** Versi teks yang distempel untuk record BARU. */
    public const CURRENT = 'v1';

    public static function get(?string $version = null): array
    {
        $reg = self::registry();
        $ver = $version && isset($reg[$version]) ? $version : self::CURRENT;
        return $reg[$ver] ?? $reg[self::CURRENT];
    }

    private static function registry(): array
    {
        return [
            'v1' => [
                'judul' => 'SURAT PERNYATAAN PENOLAKAN TINDAKAN RESUSITASI (DNR)',
                'pembukaIntro' => 'Yang bertanda tangan di bawah ini:',
                // Statement mengapit daftar tindakan yang ditolak (diisi komponen cetak).
                'statementPre' => 'Dengan ini menyatakan secara sadar, tanpa paksaan dari pihak mana pun, bahwa saya MENOLAK dilakukannya tindakan resusitasi berikut apabila terjadi henti jantung dan/atau henti napas:',
                'statementPost' => 'terhadap pasien di bawah ini:',
                'penjelasanRisiko' => 'Saya telah mendapat penjelasan yang cukup dari Dokter Penanggung Jawab Pelayanan mengenai kondisi dan prognosis pasien, tujuan serta manfaat tindakan resusitasi, dan risiko yang timbul apabila tindakan tersebut tidak dilakukan — yaitu pasien dapat meninggal dunia — dan saya memahami sepenuhnya penjelasan tersebut.',
                'perawatanTetap' => 'Penolakan ini HANYA berlaku untuk tindakan resusitasi yang disebutkan di atas. Perawatan lain — termasuk pemberian oksigen, cairan, nutrisi, obat, serta tindakan untuk mengurangi nyeri dan menjaga kenyamanan pasien — TETAP diberikan sebagaimana mestinya.',
                'pencabutan' => 'Keputusan ini dapat saya cabut atau ubah sewaktu-waktu dengan menyampaikannya kepada Dokter Penanggung Jawab Pelayanan atau petugas ruangan, dan pencabutan tersebut dicatat dalam rekam medis pasien.',
                'tanggungJawab' => 'Atas penolakan ini saya bertanggung jawab penuh terhadap segala risiko dan akibat yang timbul, serta tidak akan mengajukan tuntutan dalam bentuk apa pun kepada Rumah Sakit, Dokter, maupun petugas kesehatan yang merawat.',
                'penutup' => 'Demikian surat pernyataan ini saya buat dengan sesungguhnya dalam keadaan sadar, tanpa paksaan dari pihak mana pun, untuk dipergunakan sebagaimana mestinya.',
            ],
        ];
    }
}
