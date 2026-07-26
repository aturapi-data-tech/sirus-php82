<?php

namespace App\Support;

/**
 * Sumber tunggal peta label Surveilans HAIs (Healthcare-Associated Infections)
 * mengikuti Formulir Surveilans HIPPII (F/011/001/R/03).
 *
 * Dipakai bersama oleh komponen form (modul-dokumen RI), cetak PDF, dan viewer
 * Rekam Medis — jangan duplikasi daftar opsi di blade.
 *
 * Grup dokumen: Plebitis/IADP · ISK · VAP (Pneumonia Ventilator) · ILO.
 */
class SurveilansHaisOptions
{
    /* ═══ BAGIAN BERSAMA (dipakai keempat modul) ═══ */

    public const CARA_MASUK = [
        'ird' => 'IRD',
        'irj' => 'IRJ',
        'langsung' => 'Langsung',
        'rujukan' => 'Rujukan',
    ];

    public const CARA_KELUAR = [
        'hidup' => 'Hidup',
        'meninggal' => 'Meninggal',
        'pindahRs' => 'Pindah RS',
        'pulangPaksa' => 'Pulang Paksa',
        'kabur' => 'Kabur',
    ];

    public const FAKTOR_RISIKO = [
        'diabetesMelitus' => 'Diabetes melitus',
        'gangguanFaalHati' => 'Gangguan faal hati',
        'gangguanImun' => 'Gangguan sistem kekebalan tubuh',
        'obesitas' => 'Obesitas',
        'giziBuruk' => 'Gizi buruk',
        'bayiPartusNormal' => 'Bayi, partus normal',
        'keganasan' => 'Keganasan',
        'gangguanFaalGinjal' => 'Gangguan faal ginjal',
        'perokok' => 'Perokok',
        'lanjutUsia' => 'Lanjut usia',
    ];

    public const KELOMPOK_USIA = [
        'balita' => 'Pasien usia < 1 tahun',
        'dewasa' => 'Pasien usia > 1 tahun',
    ];

    public const RUTE_ANTIBIOTIK = [
        'PO' => 'PO',
        'IV' => 'IV',
        'IM' => 'IM',
    ];

    public const INDIKASI_ANTIBIOTIK = [
        'pengobatan' => 'Pengobatan',
        'profilaksis' => 'Profilaksis',
    ];

    /* ═══ IADP & PLEBITIS ═══ */

    /** Tanda infeksi pada pasien usia < 1 tahun (IADP/plebitis). */
    public const TANDA_IADP_BALITA = [
        'distensiAbdomen' => 'Distensi abdomen',
        'nadiGt100' => 'Nadi > 100 x/mnt',
        'suhuGt38' => 'Suhu > 38 °C',
        'suhuLt37' => 'Suhu < 37 °C',
        'apnu' => 'Apnu',
        'nyeri' => 'Nyeri',
        'merah' => 'Merah',
        'kalor' => 'Kalor',
        'pus' => 'Pus',
        'bengkak' => 'Bengkak',
    ];

    /** Tanda infeksi pada pasien usia > 1 tahun (IADP/plebitis). */
    public const TANDA_IADP_DEWASA = [
        'suhuGt38' => 'Suhu > 38 °C',
        'sistolikLt90' => 'Sistolik < 90 mmHg',
        'menggigil' => 'Menggigil',
        'nyeri' => 'Nyeri',
        'merah' => 'Merah',
        'kalor' => 'Kalor',
        'pus' => 'Pus',
        'bengkak' => 'Bengkak',
    ];

    public const TUJUAN_PEMASANGAN = [
        'antibiotikSitostatika' => 'Pemberian antibiotik / sitostatika',
        'transfusi' => 'Transfusi (WB / PRC / FFP / Trombosit)',
        'nutrisiParenteral' => 'Nutrisi parenteral (protein / lemak / glukosa)',
        'terapiCairan' => 'Terapi cairan (RL / NaCl 0,9% / KaEn 3B)',
    ];

    /* ═══ INFEKSI SALURAN KEMIH ═══ */

    public const JENIS_KATETER_ISK = [
        'spp' => 'SPP',
        'douer' => 'Douer',
        'intermiten' => 'Intermiten',
        'kondom' => 'Kondom',
    ];

    public const TANDA_ISK_BALITA = [
        'suhuGt38' => 'Suhu > 38 °C',
        'suhuLt37' => 'Suhu < 37 °C',
        'nadiLt100' => 'Frek. nadi < 100 x/mnt',
        'apnu' => 'Apnu',
        'letargi' => 'Letargi',
        'muntah' => 'Muntah',
    ];

    public const TANDA_ISK_DEWASA = [
        'suhuGt38' => 'Suhu > 38 °C',
        'anyangAnyangan' => 'Anyang-anyangan',
        'nyeriSuprapubik' => 'Nyeri supra pubik',
        'nyeriBerkemih' => 'Nyeri berkemih',
        'pus' => 'Pus',
    ];

    /* ═══ PNEUMONIA VENTILATOR (VAP) ═══ */

    public const FOTO_TORAKS = [
        'infiltrat' => 'Infiltrat',
        'merata' => 'Merata',
        'patchy' => 'Patchy',
        'terkalsifikasi' => 'Terkalsifikasi',
    ];

    /* ═══ INFEKSI LUKA OPERASI (ILO) ═══ */

    public const JENIS_OPERASI = [
        'bersih' => 'Bersih',
        'bersihTerkontaminasi' => 'Bersih terkontaminasi',
        'kontaminasi' => 'Kontaminasi',
        'kotor' => 'Kotor',
    ];

    public const ASA_SCORE = [
        '1' => 'ASA 1',
        '2' => 'ASA 2',
        '3' => 'ASA 3',
        '4' => 'ASA 4',
        '5' => 'ASA 5',
    ];

    /** Parameter yang dipantau tiap hari pada luka operasi (hari ke-1 s/d 17). */
    public const PARAM_PEMANTAUAN_ILO = [
        'suhuGt38' => 'Suhu ≥ 38 °C',
        'drainase' => 'Drainase',
        'pus' => 'Pus',
        'perforasi' => 'Perforasi',
        'fistula' => 'Fistula',
    ];

    /** Jumlah hari pemantauan luka operasi (sesuai formulir kertas). */
    public const HARI_PEMANTAUAN_ILO = 17;

    /**
     * Ambil label dari salah satu peta di atas; kembalikan key apa adanya bila tak dikenal.
     */
    public static function label(array $map, ?string $key): string
    {
        if (!filled($key)) {
            return '-';
        }

        return $map[$key] ?? $key;
    }

    /**
     * Rangkai label dari array flag boolean (mis. faktorRisiko / fotoToraks).
     */
    public static function flagLabels(array $map, array $flags): array
    {
        $hasil = [];
        foreach ($map as $key => $label) {
            if (!empty($flags[$key])) {
                $hasil[] = $label;
            }
        }

        return $hasil;
    }

    /**
     * Peta label lengkap — dipakai viewer/cetak supaya satu sumber.
     */
    public static function labels(): array
    {
        return [
            'caraMasuk' => self::CARA_MASUK,
            'caraKeluar' => self::CARA_KELUAR,
            'faktorRisiko' => self::FAKTOR_RISIKO,
            'kelompokUsia' => self::KELOMPOK_USIA,
            'ruteAntibiotik' => self::RUTE_ANTIBIOTIK,
            'indikasiAntibiotik' => self::INDIKASI_ANTIBIOTIK,
            'tandaIadpBalita' => self::TANDA_IADP_BALITA,
            'tandaIadpDewasa' => self::TANDA_IADP_DEWASA,
            'tujuanPemasangan' => self::TUJUAN_PEMASANGAN,
            'jenisKateterIsk' => self::JENIS_KATETER_ISK,
            'tandaIskBalita' => self::TANDA_ISK_BALITA,
            'tandaIskDewasa' => self::TANDA_ISK_DEWASA,
            'fotoToraks' => self::FOTO_TORAKS,
            'jenisOperasi' => self::JENIS_OPERASI,
            'asaScore' => self::ASA_SCORE,
            'paramPemantauanIlo' => self::PARAM_PEMANTAUAN_ILO,
        ];
    }
}
