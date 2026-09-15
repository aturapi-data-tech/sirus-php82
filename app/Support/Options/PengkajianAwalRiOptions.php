<?php

namespace App\Support\Options;

/**
 * Opsi & peta label Pengkajian Awal Keperawatan Rawat Inap (node JSON pengkajianAwalPasienRawatInap).
 * SATU sumber untuk form EMR (rm-pengkajian-awal-ri) + cetak (RM-03.11) — jangan duplikasi label di blade.
 * Kunci = nilai tersimpan di JSON; nilai = label tampil.
 */
class PengkajianAwalRiOptions
{
    public const KONDISI_SAAT_MASUK = ['mandiri' => 'Mandiri', 'dibantu' => 'Dibantu', 'tirahBaring' => 'Tirah Baring'];

    public const ASAL_PASIEN = ['poliklinik' => 'Poliklinik', 'igd' => 'IGD', 'kamarOperasi' => 'Kamar Operasi', 'lainnya' => 'Lainnya'];

    public const BARANG_BERHARGA = ['ada' => 'Ada', 'tidakAda' => 'Tidak Ada'];

    public const ALAT_BANTU = ['kacamata' => 'Kacamata', 'gigiPalsu' => 'Gigi Palsu', 'alatBantuDengar' => 'Alat Bantu Dengar', 'lainnya' => 'Lainnya'];

    public const RIWAYAT_PENYAKIT = ['hipertensi' => 'Hipertensi', 'diabetes' => 'Diabetes', 'asma' => 'Asma', 'stroke' => 'Stroke', 'penyakitJantung' => 'Penyakit Jantung', 'lainnya' => 'Lainnya'];

    public const KEBIASAAN = ['ya' => 'Ya', 'tidak' => 'Tidak', 'berhenti' => 'Berhenti'];

    public const VAKSINASI = ['ya' => 'Ya', 'tidak' => 'Tidak', 'menolak' => 'Menolak'];

    public const RIWAYAT_KELUARGA = ['penyakitJantung' => 'Penyakit Jantung', 'hipertensi' => 'Hipertensi', 'diabetes' => 'Diabetes', 'stroke' => 'Stroke', 'lainnya' => 'Lainnya'];

    public const AGAMA = ['islam' => 'Islam', 'kristen' => 'Kristen', 'hindu' => 'Hindu', 'budha' => 'Budha', 'lainnya' => 'Lainnya'];

    public const STATUS_PERNIKAHAN = ['menikah' => 'Menikah', 'belumMenikah' => 'Belum Menikah', 'dudaJanda' => 'Duda / Janda'];

    public const TEMPAT_TINGGAL = ['rumah' => 'Rumah', 'panti' => 'Panti', 'lainnya' => 'Lainnya'];

    public const AKTIVITAS = ['mandiri' => 'Mandiri', 'dibantu' => 'Dibantu', 'tirahBaring' => 'Tirah Baring'];

    public const STATUS_EMOSIONAL = ['kooperatif' => 'Kooperatif', 'cemas' => 'Cemas', 'depresi' => 'Depresi', 'lainnya' => 'Lainnya'];

    public const INFORMASI_DARI = ['pasien' => 'Pasien', 'keluarga' => 'Keluarga', 'lainnya' => 'Lainnya'];

    public const TINGKAT_KESADARAN = ['komposMentis' => 'Kompos Mentis', 'apatis' => 'Apatis', 'somnolen' => 'Somnolen', 'sopor' => 'Sopor', 'koma' => 'Koma', 'delirium' => 'Delirium'];

    /** Pemeriksaan sistem organ (bagian4PemeriksaanFisik.pemeriksaanSistemOrgan) — path => [label, opsi]. Neurologi terpisah (punya GCS). */
    public const SISTEM_ORGAN = [
        'mataTelingaHidungTenggorokan' => ['label' => 'Mata, Telinga, Hidung & Tenggorokan', 'opsi' => ['normal' => 'Normal', 'gangguanVisus' => 'Gangguan Visus', 'tuli' => 'Tuli', 'lainnya' => 'Lainnya']],
        'paru' => ['label' => 'Paru', 'opsi' => ['normal' => 'Normal', 'ronki' => 'Ronki', 'wheezing' => 'Wheezing', 'lainnya' => 'Lainnya']],
        'jantung' => ['label' => 'Jantung', 'opsi' => ['normal' => 'Normal', 'takikardi' => 'Takikardi', 'bradikardi' => 'Bradikardi', 'lainnya' => 'Lainnya']],
        'gastrointestinal' => ['label' => 'Gastrointestinal', 'opsi' => ['normal' => 'Normal', 'distensi' => 'Distensi', 'diare' => 'Diare', 'konstipasi' => 'Konstipasi', 'lainnya' => 'Lainnya']],
        'genitourinaria' => ['label' => 'Genitourinaria', 'opsi' => ['normal' => 'Normal', 'hematuria' => 'Hematuria', 'inkontinensia' => 'Inkontinensia', 'lainnya' => 'Lainnya']],
        'muskuloskeletalDanKulit' => ['label' => 'Muskuloskeletal & Kulit', 'opsi' => ['normal' => 'Normal', 'deformitas' => 'Deformitas', 'luka' => 'Luka', 'lainnya' => 'Lainnya']],
    ];

    /**
     * Teks tampil sebuah pilihan: label opsinya, ditambah keterangan bila pilihan "lainnya"
     * (atau bila $keteranganSelalu = true). Nilai kosong → '-'; nilai di luar peta → nilainya apa adanya.
     */
    public static function teks(array $opsi, ?string $pilihan, ?string $keterangan = null, bool $keteranganSelalu = false): string
    {
        if (blank($pilihan)) {
            return '-';
        }

        $label = $opsi[$pilihan] ?? $pilihan;
        $tampilKeterangan = filled($keterangan) && ($keteranganSelalu || $pilihan === 'lainnya');

        return $tampilKeterangan ? "{$label} — {$keterangan}" : $label;
    }
}
