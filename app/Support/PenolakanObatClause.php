<?php

namespace App\Support;

/**
 * Registry TEKS KLAUSUL Surat Pernyataan Penolakan Pengobatan / Obat Tertentu (RI)
 * per-VERSI — SUMBER TUNGGAL.
 *
 * Pola sama dengan App\Support\KerohanianClause / GeneralConsentClause
 * (lihat docs/clause-versioning.md + skill clause-versioning): dokumen bertanda tangan
 * harus bisa dicetak ulang dengan redaksi SAAT DITANDATANGANI walau teks diubah kemudian.
 *
 * Saat teks/kebijakan berubah:
 *   1. TAMBAH key versi baru (mis. 'v2'), JANGAN ubah versi lama.
 *   2. Naikkan CURRENT.
 * Record baru menstempel CURRENT; record lama tetap render versi tersimpannya.
 *
 * Placeholder statementPre/Post mengapit blok Nama Obat yang ditolak
 * (diisi komponen cetak).
 */
class PenolakanObatClause
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
                'judul' => 'SURAT PERNYATAAN PENOLAKAN PENGOBATAN / OBAT TERTENTU',
                'pembukaIntro' => 'Yang bertanda tangan di bawah ini:',
                // Statement mengapit Nama Obat: "...MENOLAK pemberian pengobatan/obat: <OBAT> terhadap pasien di bawah ini:"
                'statementPre' => 'Dengan ini menyatakan secara sadar, tanpa paksaan dari pihak mana pun, bahwa saya MENOLAK pemberian pengobatan/obat:',
                'statementPost' => 'terhadap pasien di bawah ini:',
                'penjelasanRisiko' => 'Saya telah mendapat penjelasan yang cukup dari Dokter/Petugas mengenai tujuan dan manfaat pengobatan/obat tersebut, serta risiko dan akibat yang mungkin timbul apabila pengobatan/obat tersebut tidak diberikan, dan saya memahami sepenuhnya penjelasan tersebut.',
                'tanggungJawab' => 'Atas penolakan ini saya bertanggung jawab penuh terhadap segala risiko dan akibat yang timbul, serta tidak akan mengajukan tuntutan dalam bentuk apa pun kepada Rumah Sakit, Dokter, maupun petugas kesehatan yang merawat.',
                'penutup' => 'Demikian surat pernyataan ini saya buat dengan sesungguhnya dalam keadaan sadar, tanpa paksaan dari pihak mana pun, untuk dipergunakan sebagaimana mestinya.',
            ],
        ];
    }
}
