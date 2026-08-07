<?php

namespace App\Support;

/**
 * Satu sumber label & pemetaan opsi Formulir Edukasi Terintegrasi RI.
 * Dipakai komponen form, cetak PDF, viewer rekam medis, dan tab asesmen
 * agar label tidak terduplikasi (docs/modul-dokumen-ri-pattern.md).
 */
class EdukasiTerintegrasiOptions
{
    /** @return array<string,string> */
    public static function tujuan(): array
    {
        return [
            'penyakit'       => 'Pemahaman penyakit/diagnosis',
            'obat'           => 'Penggunaan obat yang aman',
            'nutrisi'        => 'Nutrisi & diet',
            'aktivitas'      => 'Aktivitas & latihan',
            'perawatanRumah' => 'Perawatan di rumah',
            'pencegahan'     => 'Pencegahan komplikasi',
            'lainnya'        => 'Lainnya',
        ];
    }

    /** @return array<string,string> */
    public static function kebutuhan(): array
    {
        return [
            'penyakitHasil'    => 'Penjelasan penyakit & hasil pemeriksaan',
            'prosedur'         => 'Prosedur / tindakan medis',
            'rencanaAsuhan'    => 'Rencana asuhan & tindak lanjut',
            'obatEfek'         => 'Penggunaan obat & efek samping',
            'nutrisiDiet'      => 'Pengaturan nutrisi & diet',
            'aktivitasLatihan' => 'Aktivitas & latihan yang dianjurkan',
            'cuciTangan'       => 'Cuci tangan & pencegahan infeksi',
            'alatRumah'        => 'Penggunaan alat medis di rumah',
            'warningSign'      => 'Tanda bahaya yang perlu diwaspadai',
            'lainnya'          => 'Lainnya',
        ];
    }

    /**
     * Kebutuhan edukasi yang relevan per tujuan — dasar filter checklist
     * Kebutuhan Edukasi mengikuti Tujuan Edukasi yang dipilih.
     * 'rencanaAsuhan' relevan untuk semua tujuan sehingga selalu disertakan.
     *
     * @return array<string,array<int,string>>
     */
    public static function tujuanKebutuhanMap(): array
    {
        return [
            'penyakit'       => ['penyakitHasil', 'prosedur', 'rencanaAsuhan', 'warningSign'],
            'obat'           => ['obatEfek', 'rencanaAsuhan'],
            'nutrisi'        => ['nutrisiDiet', 'rencanaAsuhan'],
            'aktivitas'      => ['aktivitasLatihan', 'rencanaAsuhan'],
            'perawatanRumah' => ['alatRumah', 'cuciTangan', 'warningSign', 'rencanaAsuhan'],
            'pencegahan'     => ['cuciTangan', 'warningSign', 'rencanaAsuhan'],
            'lainnya'        => ['lainnya', 'rencanaAsuhan'],
        ];
    }

    /**
     * Daftar kebutuhan (key => label) yang tampil untuk kombinasi tujuan terpilih.
     * Tanpa tujuan → semua opsi tampil. Kebutuhan yang SUDAH dicentang selalu
     * ikut tampil agar centang lama tidak tersembunyi saat tujuan berubah.
     */
    public static function kebutuhanTampil(array $tujuanTerpilih, array $kebutuhanTercentang = []): array
    {
        $semua = self::kebutuhan();
        if (empty($tujuanTerpilih)) {
            return $semua;
        }

        $map = self::tujuanKebutuhanMap();
        $kebutuhanRelevan = [];
        foreach ($tujuanTerpilih as $tujuan) {
            $kebutuhanRelevan = array_merge($kebutuhanRelevan, $map[$tujuan] ?? []);
        }
        $kebutuhanRelevan = array_merge($kebutuhanRelevan, $kebutuhanTercentang);

        return array_intersect_key($semua, array_flip($kebutuhanRelevan));
    }

    /** @return array<string,string> */
    public static function metode(): array
    {
        return [
            'lisan'       => 'Penjelasan lisan',
            'demonstrasi' => 'Demonstrasi / praktik langsung',
            'leaflet'     => 'Leaflet / brosur',
            'video'       => 'Video edukasi',
            'poster'      => 'Poster / peraga',
            'lainnya'     => 'Lainnya',
        ];
    }

    /** @return array<string,string> */
    public static function preferensi(): array
    {
        return [
            'lisan'       => 'Lisan',
            'tulisan'     => 'Tulisan',
            'demonstrasi' => 'Demonstrasi',
            'video'       => 'Video',
            'poster'      => 'Poster',
            'lainnya'     => 'Lainnya',
        ];
    }

    /** @return array<string,string> */
    public static function hasil(): array
    {
        return [
            'paham'             => 'Pasien/keluarga memahami informasi',
            'mampuMengulang'    => 'Dapat mengulang kembali informasi',
            'tunjukkanSkill'    => 'Menunjukkan keterampilan yang diajarkan',
            'sesuaiNilai'       => 'Edukasi sesuai nilai & keyakinan pasien',
            'perluEdukasiUlang' => 'Diperlukan edukasi ulang',
        ];
    }

    /** @return array<string,string> */
    public static function rujuk(): array
    {
        return [
            'dietisien'    => 'Dietisien',
            'farmasi'      => 'Farmasi',
            'rehabilitasi' => 'Rehabilitasi',
            'psikologi'    => 'Psikologi',
            'lainnya'      => 'Lainnya',
        ];
    }

    /** @return array<string,string> */
    public static function hubungan(): array
    {
        return [
            'pasien'     => 'Pasien Sendiri',
            'suami'      => 'Suami',
            'istri'      => 'Istri',
            'ayah'       => 'Ayah',
            'ibu'        => 'Ibu',
            'anak'       => 'Anak',
            'saudara'    => 'Saudara',
            'wali_hukum' => 'Wali Hukum',
            'lainnya'    => 'Lainnya',
        ];
    }
}
