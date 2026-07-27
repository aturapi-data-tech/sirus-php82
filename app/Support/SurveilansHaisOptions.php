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

    /*
     * Cara masuk & cara keluar RS TIDAK didaftar di sini, dan sengaja TIDAK muncul di form
     * maupun cetakan surveilans: keduanya sudah terekam di alur induk (pendaftaran RI &
     * Perencanaan → Tindak Lanjut). Yang memakainya cuma tabel audit kasus di Laporan
     * Surveilans HAIs, lewat App\Support\AdmisiPulangRI. Jangan dikembalikan sebagai isian —
     * nilainya akan berbeda dari data resmi episode rawat.
     */

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

    /**
     * Alat invasif yang lama pemakaiannya menjadi PENYEBUT insiden rate HAIs.
     *
     * Diisi perawat ruangan lewat Observasi RI → tab "Alat Invasif" untuk SETIAP
     * pasien terpasang alat, bukan hanya yang dicurigai infeksi. Kalau hanya pasien
     * bermasalah yang tercatat, penyebutnya timpang dan rate jadi meledak.
     * Formulir surveilans tetap memasok PEMBILANG (kasus).
     */
    public const ALAT_INVASIF = [
        'ivPerifer' => 'IV Line Perifer',
        'cvcUmbilikal' => 'CVC / Kateter Umbilikal',
        'kateterUrine' => 'Kateter Urine',
        'ventilator' => 'Ventilator Mekanik',
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

    /**
     * Jenis akses vaskular per baris pemasangan.
     *
     * Penentu PENYEBUT di Laporan Surveilans HAIs: sentral/umbilikal → hari CVL
     * (basis IAD), perifer → hari IV line (basis plebitis). Satu entri boleh
     * memuat keduanya sekaligus, karena itu dipilih per baris, bukan per entri.
     */
    public const JENIS_AKSES = [
        'perifer' => 'Kateter V Perifer',
        'sentral' => 'Kateter V Sentral',
        'umbilikal' => 'Kateter Umbilikal',
    ];

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
            'faktorRisiko' => self::FAKTOR_RISIKO,
            'alatInvasif' => self::ALAT_INVASIF,
            'kelompokUsia' => self::KELOMPOK_USIA,
            'ruteAntibiotik' => self::RUTE_ANTIBIOTIK,
            'indikasiAntibiotik' => self::INDIKASI_ANTIBIOTIK,
            'jenisAkses' => self::JENIS_AKSES,
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
