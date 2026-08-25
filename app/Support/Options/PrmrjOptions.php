<?php

namespace App\Support\Options;

/**
 * Sumber tunggal daftar kriteria PRMRJ — Profil Ringkas Medis Rawat Jalan.
 *
 * Isinya dikutip PERSIS dari SPO "Pengisian Profil Ringkas Medis Rawat Jalan"
 * poin 2. Jangan diringkas atau diterjemahkan ulang: yang dicetak di formulir
 * harus terbaca sama dengan yang tertulis di SPO.
 *
 * Dipakai bersama oleh formulir entry, kartu riwayat, dan blade cetak — supaya
 * label tak pernah berbeda antara layar dan kertas.
 *
 * AMBANG. Kriteria (a) dan (b) berbunyi "3 atau lebih". Ambang itu ditegakkan
 * saat menyimpan, bukan sekadar tulisan di layar.
 */
class PrmrjOptions
{
    /** Kriteria a & b sama-sama menuntut minimal sekian butir tercentang. */
    public const AMBANG_BUTIR = 3;

    /**
     * Kriteria (a) — diagnosis penyerta pada pasien rawat jalan berdiagnosis kompleks.
     * Kunci sengaja pendek & stabil: yang tersimpan di JSON adalah kuncinya,
     * jadi redaksi label boleh diperbaiki tanpa merusak record lama.
     */
    public const DIAGNOSIS_PENYERTA = [
        'dm' => 'Diabetes melitus',
        'ht2' => 'Hipertensi grade II',
        'ggk' => 'Gagal ginjal kronik',
        'chf' => 'Congestive heart failure',
        'tbParu' => 'Tuberculosis paru dalam pengobatan atau dinyatakan sembuh',
        'postOpBesar' => 'Post tindakan operasi besar',
    ];

    /** Kriteria (b) — asuhan yang diterima pasien di instalasi rawat jalan. */
    public const ASUHAN = [
        'gizi' => 'Gizi',
        'radiologi' => 'Radiologi',
        'laboratorium' => 'Laboratorium',
        'rehabMedis' => 'Rehabilitasi medis',
        'kemoterapi' => 'Kemoterapi',
        'ekg' => 'EKG',
        'tindakanOperasi' => 'Tindakan operasi',
    ];

    /** Kunci → label, untuk satu kelompok. */
    public static function labels(string $kelompok): array
    {
        return match ($kelompok) {
            'diagnosis' => self::DIAGNOSIS_PENYERTA,
            'asuhan' => self::ASUHAN,
            default => [],
        };
    }

    /**
     * Daftar kunci tersimpan → daftar label terbaca.
     *
     * Kunci yang tak dikenal DIBUANG, bukan ditampilkan mentah: record lama bisa
     * memuat kunci yang sudah tak dipakai, dan mencetak "postOpBesar" di formulir
     * pasien lebih buruk daripada tak mencetaknya sama sekali.
     */
    public static function labelDari(string $kelompok, array $kunciList): array
    {
        $peta = self::labels($kelompok);

        return array_values(array_filter(array_map(
            fn ($kunci) => $peta[$kunci] ?? null,
            $kunciList
        )));
    }
}
