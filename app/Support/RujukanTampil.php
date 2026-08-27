<?php

namespace App\Support;

/**
 * Perapian angka kandidat rujukan untuk DITAMPILKAN.
 *
 * Ditaruh terpisah karena dipakai dua jalur yang traitnya berbeda: Ranap/IGD
 * lewat SatuSehatRujukanTrait, Rawat Jalan lewat SisruteTrait. Menyalin
 * logikanya ke dua tempat berarti satuan & ambangnya bisa berbeda diam-diam.
 */
final class RujukanTampil
{
    /** Setengah keliling bumi — jarak di atas ini mustahil untuk rujukan pasien. */
    private const BATAS_KM = 20015.0;

    /** Seminggu perjalanan; di atas ini jelas bukan estimasi tempuh. */
    private const BATAS_MENIT = 10080.0;

    /**
     * SATUSEHAT/BPJS kadang mengirim 1.7976931348623E+308 (nilai float terbesar —
     * penanda "tak terhitung", bukan jarak) yang kalau dicetak apa adanya jadi
     * sampah di layar.
     *
     * Yang disaring HANYA nilai mustahil. Angka yang cuma MENCURIGAKAN sengaja
     * dibiarkan tampil apa adanya (pernah terlihat 634 km untuk RS sekota) —
     * itu data pusat; menyembunyikannya dengan ambang karangan justru menutupi
     * masalah yang perlu dilaporkan.
     */
    public static function jarak($nilai): string
    {
        $angka = self::angkaWajar($nilai, self::BATAS_KM);

        return $angka === null ? '—' : rtrim(rtrim(number_format($angka, 1, ',', '.'), '0'), ',') . ' km';
    }

    public static function waktu($nilai): string
    {
        $angka = self::angkaWajar($nilai, self::BATAS_MENIT);

        return $angka === null ? '—' : number_format(round($angka), 0, ',', '.') . ' menit';
    }

    /**
     * Meratakan satu kandidat faskes dari DUA sumber yang bentuknya berbeda
     * menjadi satu bentuk baku untuk ditampilkan:
     *
     *   SISRUTE (BPJS)     kdppk, kodeFaskesSatuSehat, nmppk->nama, alamat, kota,
     *                      kelas, distance, jmlRujuk/kapasitas
     *   FHIR (SATUSEHAT)   bpjsCode, orgId, nama, distance, estimatedTime, bed
     *
     * Dipusatkan di sini supaya keenam panel rujukan memakai sebutan yang sama
     * untuk angka yang sama — "Kode BPJS" dan "Org ID", bukan PPK/SATUSEHAT di
     * satu layar lalu nama lain di layar sebelah. Kunci yang tak dimiliki suatu
     * sumber dikembalikan sebagai string kosong, bukan absen, supaya pemakainya
     * tak perlu ?? di Blade.
     */
    public static function kandidatBaris(array $kandidat): array
    {
        $bpjs = trim((string) ($kandidat['kdppk'] ?? ($kandidat['bpjsCode'] ?? '')));
        // Gateway BPJS pernah mengirim string "null" — itu KOSONG, bukan kode.
        if (strtolower($bpjs) === 'null') {
            $bpjs = '';
        }

        // Sebagian alamat sudah memuat nama kota di ekornya — jangan ditempeli
        // lagi supaya kotanya tak tertulis dua kali.
        $alamat = trim((string) ($kandidat['alamat'] ?? ''));
        $kota = trim((string) ($kandidat['kota'] ?? ''));
        $kotaSudahAda = $kota !== '' && stripos($alamat, $kota) !== false;
        $alamatKota = trim($alamat . ($kota !== '' && !$kotaSudahAda ? ' · ' . $kota : ''), ' ·');

        $jmlRujuk = trim((string) ($kandidat['jmlRujuk'] ?? ''));
        $kapasitas = trim((string) ($kandidat['kapasitas'] ?? ''));

        return [
            'nama' => trim((string) ($kandidat['nama'] ?? '')) ?: '-',
            'alamat' => $alamatKota,
            'bpjs' => $bpjs,
            'orgId' => trim((string) ($kandidat['kodeFaskesSatuSehat'] ?? ($kandidat['orgId'] ?? ''))),
            'kelas' => trim((string) ($kandidat['kelas'] ?? '')),
            'jarak' => $kandidat['distance'] ?? '',
            'estimasi' => $kandidat['estimatedTime'] ?? '',
            // Beban hanya berarti kalau kapasitasnya diketahui — "0/" bukan informasi.
            'beban' => $jmlRujuk !== '' && $kapasitas !== '' ? $jmlRujuk . '/' . $kapasitas : '',
            'bed' => trim((string) ($kandidat['bed'] ?? '')),
        ];
    }

    /**
     * Baris "Tujuan: …" di bawah tabel kandidat. Memakai sebutan yang SAMA
     * dengan tabelnya; sebelumnya jalur BPJS menulis "PPK … / SATUSEHAT …"
     * sementara jalur FHIR menulis "Org …" untuk angka yang sama persis.
     */
    public static function infoTujuan(array $kandidat): string
    {
        $baris = self::kandidatBaris($kandidat);
        $kode = array_filter([
            $baris['bpjs'] !== '' ? 'Kode BPJS ' . $baris['bpjs'] : null,
            $baris['orgId'] !== '' ? 'Org ID ' . $baris['orgId'] : null,
        ]);

        return 'Tujuan: ' . $baris['nama'] . ($kode ? ' (' . implode(' · ', $kode) . ')' : '');
    }

    private static function angkaWajar($nilai, float $batas): ?float
    {
        if ($nilai === null || $nilai === '' || !is_numeric($nilai)) {
            return null;
        }

        $angka = (float) $nilai;
        if (!is_finite($angka) || $angka < 0 || $angka > $batas) {
            return null;
        }

        return $angka;
    }
}
