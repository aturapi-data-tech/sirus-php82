<?php

namespace App\Support\Options;

/**
 * Peta label Penolakan Tindakan Resusitasi (DNR) — SATU SUMBER untuk form, cetak, dan viewer.
 * Jangan menduplikasi daftar ini di blade (skill modul-dokumen aturan #5).
 *
 * Lingkup sengaja dirinci per tindakan, bukan satu kotak "DNR" saja: praktiknya penolakan
 * bisa sebagian (mis. menolak kompresi dada & intubasi, tetapi bersedia diberi obat vasopresor).
 */
class PenolakanResusitasiOptions
{
    /** Tindakan resusitasi yang boleh ditolak. */
    public static function lingkupLabels(): array
    {
        return [
            'rjp' => 'Resusitasi Jantung Paru (kompresi dada)',
            'intubasi' => 'Intubasi & ventilasi mekanik (pemasangan alat bantu napas)',
            'defibrilasi' => 'Defibrilasi / kardioversi (kejut listrik jantung)',
            'vasopresor' => 'Obat vasopresor / inotropik penunjang jantung',
        ];
    }

    /** Label satu kode lingkup; kode tak dikenal dikembalikan apa adanya. */
    public static function lingkupLabel(string $kode): string
    {
        return self::lingkupLabels()[$kode] ?? $kode;
    }

    /** Daftar label dari array kode tersimpan — dipakai cetak & viewer. */
    public static function lingkupTerpilih(array $kode): array
    {
        $peta = self::lingkupLabels();
        return array_values(array_map(fn($k) => $peta[$k] ?? $k, array_filter($kode)));
    }
}
