<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Deteksi order radiologi ganda — pemeriksaan yang sama diorder dua kali
 * untuk kunjungan yang sama.
 *
 * Dipakai bersama oleh 4 titik order (EMR RJ/UGD/RI + menu penunjang), jadi
 * ditaruh sebagai helper statis, bukan trait: keempat komponen sudah memakai
 * trait EMR masing-masing dan method bernama sama akan bentrok.
 *
 * Sifatnya PERINGATAN, bukan larangan. Foto ulang dan kontrol memang sah
 * secara klinis, jadi pemanggil menampilkan hasilnya lalu membiarkan petugas
 * meneruskan dengan sadar.
 */
class OrderRadiologiGanda
{
    /** Peta sumber → [tabel, kolom kunci kunjungan, kolom nomor detail, kolom waktu]. */
    private const SUMBER = [
        'RJ'  => ['rstxn_rjrads',      'rj_no',     'rad_dtl',   'waktu_entry'],
        'UGD' => ['rstxn_ugdrads',     'rj_no',     'rad_dtl',   'waktu_entry'],
        'RI'  => ['rstxn_riradiologs', 'rihdr_no',  'rirad_no',  'waktu_entry'],
    ];

    /**
     * Cari pemeriksaan yang sudah pernah diorder di kunjungan yang sama.
     *
     * Batas waktunya beda per jalur:
     *  - RJ & UGD: sepanjang kunjungan — satu nomor kunjungan = satu hari.
     *  - RI: dibatasi HARI YANG SAMA — satu rihdr_no bisa berjalan berminggu,
     *    dan foto ulang di hari berbeda itu normal, bukan kekeliruan entri.
     *    Konsekuensinya baris RI yang tanggalnya kosong sama sekali tidak pernah
     *    terjaring; per 24/08/2026 itu 4 dari 15.069 baris, semuanya legacy —
     *    baris baru selalu mengisi waktu_entry sekaligus rirad_date.
     *
     * Baris batal tidak perlu disaring: pembatalan order radiologi menghapus
     * barisnya (lihat aksi Batal di ⚡upload-radiologi.blade.php).
     *
     * @param  string  $sumber  RJ | UGD | RI
     * @param  string|int  $nomorKunjungan  rj_no (RJ/UGD) atau rihdr_no (RI)
     * @param  array<int,string|int>  $radIds  rad_id yang hendak diorder
     * @return array<int,array{rad_id:string,rad_desc:string,waktu:string}>
     */
    public static function cari(string $sumber, string|int $nomorKunjungan, array $radIds): array
    {
        $sumber = strtoupper(trim($sumber));

        if (!isset(self::SUMBER[$sumber]) || empty($radIds) || $nomorKunjungan === '' || $nomorKunjungan === null) {
            return [];
        }

        [$tabel, $kolomKunjungan, $kolomDetail, $kolomWaktu] = self::SUMBER[$sumber];

        // rirad_date jadi cadangan waktu RI: baris legacy bisa punya waktu_entry kosong.
        $ekspresiWaktu = $sumber === 'RI'
            ? "NVL(a.{$kolomWaktu}, a.rirad_date)"
            : "a.{$kolomWaktu}";

        $query = DB::table($tabel . ' as a')
            ->leftJoin('rsmst_radiologis as m', 'a.rad_id', '=', 'm.rad_id')
            ->where('a.' . $kolomKunjungan, $nomorKunjungan)
            ->whereIn('a.rad_id', array_values($radIds))
            ->select([
                'a.rad_id',
                'm.rad_desc',
                DB::raw("TO_CHAR({$ekspresiWaktu},'dd/mm/yyyy hh24:mi') as waktu"),
                DB::raw("a.{$kolomDetail} as nomor_detail"),
            ]);

        if ($sumber === 'RI') {
            $query->whereRaw("TRUNC({$ekspresiWaktu}) = TRUNC(SYSDATE)");
        }

        return $query
            ->orderBy('a.' . $kolomDetail)
            ->get()
            ->map(fn($baris) => [
                'rad_id'   => (string) $baris->rad_id,
                'rad_desc' => trim((string) ($baris->rad_desc ?? '')) ?: ('Pemeriksaan #' . $baris->rad_id),
                'waktu'    => (string) ($baris->waktu ?? ''),
            ])
            ->all();
    }

    /**
     * Kelompokkan hasil cari() per pemeriksaan — satu pemeriksaan yang diorder
     * dua kali harus terbaca sebagai SATU pemeriksaan, bukan dua.
     *
     * @param  array<int,array{rad_id:string,rad_desc:string,waktu:string}>  $ganda
     * @return array<int,array{rad_desc:string,jumlah:int,waktu:string}>
     */
    public static function kelompokkan(array $ganda): array
    {
        return collect($ganda)
            ->groupBy('rad_id')
            ->map(function ($baris) {
                // Waktu yang ditampilkan = order terakhir yang waktunya tercatat.
                // Baris legacy bisa punya waktu kosong; jangan tampilkan tanda kurung hampa.
                $waktu = collect($baris)->pluck('waktu')->filter(fn($w) => trim((string) $w) !== '')->last();

                return [
                    'rad_desc' => $baris->first()['rad_desc'],
                    'jumlah'   => count($baris),
                    'waktu'    => (string) ($waktu ?? ''),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Ringkas hasil cari() jadi satu kalimat untuk toast/exception.
     *
     * @param  array<int,array{rad_id:string,rad_desc:string,waktu:string}>  $ganda
     */
    public static function ringkas(array $ganda): string
    {
        $kelompok = self::kelompokkan($ganda);

        $nama = collect($kelompok)
            ->map(function ($baris) {
                $label = $baris['rad_desc'];
                if ($baris['jumlah'] > 1) {
                    $label .= " ({$baris['jumlah']}×)";
                } elseif ($baris['waktu'] !== '') {
                    $label .= " ({$baris['waktu']})";
                }

                return $label;
            })
            ->implode(', ');

        return count($kelompok) === 1
            ? "Pemeriksaan ini sudah pernah diorder di kunjungan yang sama: {$nama}."
            : count($kelompok) . " pemeriksaan sudah pernah diorder di kunjungan yang sama: {$nama}.";
    }
}
