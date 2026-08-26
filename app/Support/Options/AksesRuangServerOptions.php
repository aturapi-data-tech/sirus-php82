<?php

namespace App\Support\Options;

use Carbon\Carbon;

/**
 * Sumber tunggal daftar pilihan PEMANTAUAN AKSES ruang server — model 2 dari dua
 * model Pemantauan Ruang Server (Akreditasi MRMIK 2.2 - Perlindungan Data).
 *
 * Model 1 (suhu & status AC) punya daftar pilihannya sendiri di
 * SuhuRuangServerOptions. Identitas fisik ruangnya dipakai KEDUA modul, jadi
 * tinggal di RuangServerOptions — bukan disalin ke sini.
 *
 * Dipakai bersama oleh formulir entry, tabel di layar, dan blade cetak - supaya
 * label tak pernah berbeda antara layar dan kertas.
 */
class AksesRuangServerOptions
{
    /**
     * Siapa yang masuk. Kunci sengaja pendek & stabil: yang tersimpan di JSON
     * adalah kuncinya, jadi redaksi label boleh diperbaiki tanpa merusak record lama.
     */
    public const JENIS_PENGUNJUNG = [
        'internal' => 'Petugas IT / SIMRS (internal)',
        'pegawai' => 'Pegawai RS unit lain',
        'vendor' => 'Vendor / pihak ketiga',
        'auditor' => 'Auditor / surveior',
        'lainnya' => 'Lainnya',
    ];

    /**
     * Pengunjung yang WAJIB didampingi petugas IT selama berada di ruang server.
     *
     * Daftar ini yang ditegakkan saat menyimpan, bukan kebalikannya ("siapa yang
     * boleh sendirian") - kalau kelak ada jenis pengunjung baru, bawaannya jadi
     * "wajib didampingi", bukan diam-diam bebas masuk sendiri.
     */
    public const WAJIB_DIDAMPINGI = ['pegawai', 'vendor', 'auditor', 'lainnya'];

    /** Untuk apa masuk ke ruang server. */
    public const KEPERLUAN = [
        'perawatanRutin' => 'Perawatan rutin / pembersihan',
        'perbaikan' => 'Perbaikan gangguan',
        'instalasi' => 'Instalasi / penggantian perangkat',
        'backupRestore' => 'Backup / restore data',
        'pemantauan' => 'Pemantauan suhu & perangkat',
        'audit' => 'Audit / survei akreditasi',
        'lainnya' => 'Lainnya',
    ];

    /** Kunci keperluan yang menuntut penjelasan bebas di kolom "Keperluan lain". */
    public const KEPERLUAN_LAIN = 'lainnya';

    /** Kunci jenis pengunjung -> label. Kunci tak dikenal jadi tanda hubung, bukan kunci mentah. */
    public static function labelJenisPengunjung(?string $kunci): string
    {
        return self::JENIS_PENGUNJUNG[$kunci] ?? '-';
    }

    /** Kunci keperluan -> label. */
    public static function labelKeperluan(?string $kunci): string
    {
        return self::KEPERLUAN[$kunci] ?? '-';
    }

    /**
     * Keperluan siap tampil: label bakunya, atau uraian bebas bila "Lainnya".
     * Satu tempat supaya layar & kertas tak pernah menuliskannya berbeda.
     */
    public static function keperluanTerbaca(array $entri): string
    {
        $kunci = (string) ($entri['keperluan'] ?? '');

        if ($kunci === self::KEPERLUAN_LAIN) {
            $uraian = trim((string) ($entri['keperluanLain'] ?? ''));

            return $uraian === '' ? self::labelKeperluan($kunci) : $uraian;
        }

        return self::labelKeperluan($kunci);
    }

    /** Pengunjung jenis ini wajib didampingi petugas IT? */
    public static function wajibDidampingi(?string $jenisPengunjung): bool
    {
        return in_array((string) $jenisPengunjung, self::WAJIB_DIDAMPINGI, true);
    }

    /**
     * Kunjungan yang belum ditutup (tamunya belum tercatat keluar).
     *
     * Dipakai layar & rekap untuk menyorot baris yang perlu dilengkapi. Bukan
     * error: petugas memang mencatat waktu masuk lebih dulu.
     */
    public static function masihDiDalam(array $catatan): bool
    {
        return blank($catatan['waktuKeluar'] ?? null);
    }

    /**
     * Lama kunjungan sebagai teks, dari waktu masuk & keluar.
     *
     * Selisih dihitung dari TIMESTAMP, bukan diffInMinutes: Carbon 3 membalik
     * tanda pada beberapa pemakaian dan pernah menghasilkan durasi negatif di
     * repo ini (lihat memori feedback_carbon3_diff_signed).
     *
     * Mengembalikan '' bila tamunya belum keluar atau waktunya tak terbaca -
     * bukan '0 menit', yang akan terbaca sebagai "mampir sebentar".
     */
    public static function lamaKunjungan(array $catatan): string
    {
        $masuk = self::waktu($catatan['waktu'] ?? null);
        $keluar = self::waktu($catatan['waktuKeluar'] ?? null);

        if ($masuk === null || $keluar === null) {
            return '';
        }

        $menit = intdiv($keluar->getTimestamp() - $masuk->getTimestamp(), 60);

        if ($menit < 0) {
            return '';
        }

        $jam = intdiv($menit, 60);

        return $jam > 0 ? $jam . ' j ' . ($menit % 60) . ' m' : $menit . ' m';
    }

    /** Waktu keluar mendahului waktu masuk? Dipakai guard saat menyimpan. */
    public static function keluarSebelumMasuk(array $catatan): bool
    {
        $masuk = self::waktu($catatan['waktu'] ?? null);
        $keluar = self::waktu($catatan['waktuKeluar'] ?? null);

        return $masuk !== null && $keluar !== null && $keluar->lt($masuk);
    }

    /** 'd/m/Y H:i:s' -> Carbon, atau null bila tak terbaca. */
    private static function waktu(?string $teks): ?Carbon
    {
        if (blank($teks)) {
            return null;
        }

        try {
            return Carbon::createFromFormat(SuhuRuangServerOptions::FORMAT_WAKTU, trim($teks));
        } catch (\Throwable) {
            return null;
        }
    }
}
