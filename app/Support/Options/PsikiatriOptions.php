<?php

namespace App\Support\Options;

/**
 * Sumber tunggal opsi Status Psikiatri EMR Rawat Jalan — mengikuti formulir kertas
 * "Status Psikiatri". Dipakai form (psikiatri-tab), viewer Rekam Medis, dan cetak PDF.
 *
 * Nilai yang disimpan = teks label itu sendiri (bukan kode), supaya JSON tetap terbaca
 * tanpa peta. Disimpan di datadaftarpolirj_json → pemeriksaan.pemeriksaanPsikiatri.
 *
 * Isian format lama (2026-10-10 s.d. 2026-10-15: kesadaran/kontak/moodAfek/prosesBerpikir/
 * persepsi/kemauan/psikomotor berupa teks bebas) TIDAK dimigrasi — key-nya berbeda dari
 * format baru, jadi tetap utuh di JSON dan ikut tampil lewat ringkasan() bagian "lama".
 */
class PsikiatriOptions
{
    public const PENAMPILAN = ['Rapi', 'Kurang Rapi'];

    /** "GCS" disertai nilai di kesadaranGcs. */
    public const KESADARAN = ['GCS', 'Berkabut', 'Confusional State'];

    public const KONTAK_MATA = ['+', '-'];

    public const KONTAK_VERBAL_RELEVANSI = ['Relevan', 'Irelevan'];

    public const KONTAK_VERBAL_KELANCARAN = ['Lancar', 'Kurang Lancar'];

    public const MOOD = [
        'Euthymia',
        'Murung',
        'Sedih',
        'Depresi',
        'Cemas',
        'Senang',
        'Gembira',
        'Euphoria',
        'Elasi',
        'Marah',
        'Mudah Tersinggung',
    ];

    public const AFEK = ['Tumpul', 'Datar', 'Labil', 'Tidak Wajar'];

    public const BENTUK_PIKIR = [
        'Sirkumstansial',
        'Tangensial',
        'Asosiasi Longgar',
        'Word Salad',
        'Inkoherensi',
        'Neologisme',
        'Asosiasi Bunyi',
    ];

    public const ISI_PIKIR = ['Waham', 'Obsesi', 'Kompulsi', 'Fobia', 'Bunuh Diri', 'Pikiran Tidak Memadai'];

    public const ARUS_PIKIR = [
        'Mutisme',
        'Kemiskinan Pembicaraan',
        'Blocking',
        'Perseverasi',
        'Logorea',
        'Verbigerasi',
        'Pikiran Mendesak',
        'Flight of Idea',
    ];

    public const HALUSINASI = [
        'Auditorik',
        'Visual',
        'Gustatorik',
        'Taktil',
        'Olfaktorik',
        'Kinestetik',
        'Viseral',
        'Histerikal',
        'Hipnagogik',
        'Hipnopompik',
    ];

    public const KEMAUAN = ['Meningkat', 'Normal', 'Menurun'];

    public const PSIKOMOTOR = ['Meningkat', 'Normal', 'Menurun'];

    /** Bentuk kosong node pemeriksaanPsikiatri (format baru). */
    public static function defaultForm(): array
    {
        return [
            'penampilan' => '',
            'kesadaranStatus' => '',
            'kesadaranGcs' => '',
            'kontakMata' => '',
            'kontakVerbalRelevansi' => '',
            'kontakVerbalKelancaran' => '',
            'mood' => [],
            'afek' => '',
            'bentukPikir' => [],
            'isiPikir' => [],
            'arusPikir' => [],
            'halusinasi' => false,
            'halusinasiJenis' => [],
            'depersonalisasi' => false,
            'kemauanStatus' => '',
            'psikomotorStatus' => '',
        ];
    }

    /**
     * Baris label => nilai yang terisi saja, untuk viewer & cetak.
     *
     * @return array<string, string>
     */
    public static function ringkasan(array $psikiatri): array
    {
        $daftar = fn($nilai) => is_array($nilai) ? implode(', ', array_filter($nilai, 'filled')) : (string) $nilai;

        $kesadaran = (string) ($psikiatri['kesadaranStatus'] ?? '');
        if ($kesadaran === 'GCS' && filled($psikiatri['kesadaranGcs'] ?? '')) {
            $kesadaran = 'GCS ' . $psikiatri['kesadaranGcs'];
        }

        $kontakVerbal = implode(', ', array_filter([
            $psikiatri['kontakVerbalRelevansi'] ?? '',
            $psikiatri['kontakVerbalKelancaran'] ?? '',
        ], 'filled'));

        $persepsi = [];
        if (! empty($psikiatri['halusinasi'])) {
            $jenis = $daftar($psikiatri['halusinasiJenis'] ?? []);
            $persepsi[] = 'Halusinasi' . ($jenis !== '' ? ' (' . $jenis . ')' : '');
        }
        if (! empty($psikiatri['depersonalisasi'])) {
            $persepsi[] = 'Depersonalisasi';
        }

        $baru = [
            'Penampilan' => $psikiatri['penampilan'] ?? '',
            'Kesadaran' => $kesadaran,
            'Kontak Mata' => $psikiatri['kontakMata'] ?? '',
            'Kontak Verbal' => $kontakVerbal,
            'Mood' => $daftar($psikiatri['mood'] ?? []),
            'Afek' => $psikiatri['afek'] ?? '',
            'Bentuk Pikir' => $daftar($psikiatri['bentukPikir'] ?? []),
            'Isi Pikir' => $daftar($psikiatri['isiPikir'] ?? []),
            'Arus Pikir' => $daftar($psikiatri['arusPikir'] ?? []),
            'Persepsi' => implode('; ', $persepsi),
            'Kemauan' => $psikiatri['kemauanStatus'] ?? '',
            'Psikomotor' => $psikiatri['psikomotorStatus'] ?? '',
        ];

        return array_filter($baru + self::ringkasanLama($psikiatri), fn($nilai) => trim((string) $nilai) !== '');
    }

    /**
     * Isian format lama (teks bebas) — label diberi akhiran "(lama)" supaya tak tertukar
     * dengan isian format baru bila keduanya ada di satu kunjungan.
     *
     * @return array<string, string>
     */
    public static function ringkasanLama(array $psikiatri): array
    {
        $prosesBerpikir = is_array($psikiatri['prosesBerpikir'] ?? null) ? $psikiatri['prosesBerpikir'] : [];
        $isiPikirLama = is_array($prosesBerpikir['isiPikir'] ?? null) ? $prosesBerpikir['isiPikir'] : [];
        $yaKeterangan = fn($jawab, $keterangan) => $jawab === 'Ya' && trim((string) $keterangan) !== ''
            ? 'Ya (' . trim((string) $keterangan) . ')'
            : (string) $jawab;

        $lama = [
            'Kesadaran (lama)' => is_string($psikiatri['kesadaran'] ?? null) ? $psikiatri['kesadaran'] : '',
            'Kontak Mata (lama)' => $psikiatri['kontak']['mata'] ?? '',
            'Kontak Verbal (lama)' => $psikiatri['kontak']['verbal'] ?? '',
            'Mood / Afek (lama)' => $psikiatri['moodAfek'] ?? '',
            'Bentuk Pikir (lama)' => $prosesBerpikir['bentukPikir'] ?? '',
            'Arus Pikir (lama)' => $prosesBerpikir['arusPikir'] ?? '',
            'Pikiran Tidak Masuk Akal (lama)' => $yaKeterangan($isiPikirLama['pikiranTidakMasukAkal'] ?? '', $isiPikirLama['keteranganPikiranTidakMasukAkal'] ?? ''),
            'Waham (lama)' => $yaKeterangan($isiPikirLama['waham'] ?? '', $isiPikirLama['keteranganWaham'] ?? ''),
            'Persepsi (lama)' => is_string($psikiatri['persepsi'] ?? null) ? $psikiatri['persepsi'] : '',
            'Kemauan (lama)' => is_string($psikiatri['kemauan'] ?? null) ? $psikiatri['kemauan'] : '',
            'Psikomotor (lama)' => is_string($psikiatri['psikomotor'] ?? null) ? $psikiatri['psikomotor'] : '',
        ];

        return array_filter($lama, fn($nilai) => trim((string) $nilai) !== '');
    }
}
