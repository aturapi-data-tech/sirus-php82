<?php

namespace App\Support\Clause;

/**
 * Registry TEKS KLAUSUL Surat Pernyataan Pulang Atas Permintaan Sendiri (APS) — RI.
 * SUMBER TUNGGAL, per-VERSI.
 *
 * Pola sama dengan App\Support\Clause\PenolakanObatClause / GeneralConsentClause
 * (lihat docs/clause-versioning.md + skill clause-versioning): dokumen bertanda tangan
 * harus bisa dicetak ulang dengan redaksi SAAT DITANDATANGANI, walau teksnya diubah
 * kemudian karena kebijakan baru.
 *
 * Saat teks/kebijakan berubah:
 *   1. TAMBAH key versi baru (mis. 'v2'), JANGAN ubah versi lama — versi lama arsip legal.
 *   2. Naikkan CURRENT.
 * Record baru menstempel CURRENT; record lama tetap render versi tersimpannya.
 *
 * Bagian dinamis (nama penyataan, hubungan dengan pasien, alasan pulang, identitas RS)
 * TIDAK disimpan di sini — diinterpolasi komponen cetak dari data entri.
 */
class PulangApsClause
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
                'judul' => 'SURAT PERNYATAAN PULANG ATAS PERMINTAAN SENDIRI',
                'pembukaIntro' => 'Yang bertanda tangan di bawah ini:',
                'statementPre' => 'Dengan ini menyatakan secara sadar dan tanpa paksaan dari pihak mana pun, bahwa saya meminta untuk PULANG ATAS PERMINTAAN SENDIRI sebelum perawatan dinyatakan selesai oleh Dokter, terhadap pasien di bawah ini:',
                'penjelasanRisiko' => 'Saya telah mendapat penjelasan yang cukup dari Dokter/Petugas mengenai kondisi kesehatan pasien saat ini, rencana perawatan yang seharusnya dijalani, serta risiko dan akibat yang mungkin timbul apabila perawatan dihentikan sebelum waktunya — termasuk kemungkinan perburukan kondisi, kecacatan, hingga kematian. Saya memahami sepenuhnya penjelasan tersebut.',
                'tanggungJawab' => 'Atas keputusan ini saya bertanggung jawab penuh terhadap segala risiko dan akibat yang timbul, serta tidak akan mengajukan tuntutan dalam bentuk apa pun kepada Rumah Sakit, Dokter, maupun petugas kesehatan yang merawat.',
                'kontrolUlang' => 'Saya bersedia untuk segera kembali ke Rumah Sakit atau mencari pertolongan ke fasilitas kesehatan terdekat apabila kondisi pasien memburuk.',
                'penutup' => 'Demikian surat pernyataan ini saya buat dengan sesungguhnya dalam keadaan sadar, tanpa paksaan dari pihak mana pun, untuk dipergunakan sebagaimana mestinya.',
            ],
        ];
    }
}
