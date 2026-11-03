<?php

namespace App\Support;

use Carbon\Carbon;
use App\Http\Traits\BPJS\VclaimTrait;

/**
 * Cek "surat kontrol untuk SEP ini sudah terbit?" sebelum Insert Rencana Kontrol — dipakai SKDP RJ & RI.
 *
 * VClaim TIDAK punya endpoint cari surat kontrol berdasarkan nomor SEP (RencanaKontrol/nosep/{sep} hanya
 * mengembalikan data SEP-nya). Yang ada: "Data Nomor Surat Kontrol Berdasarkan No Kartu" (Trust Mark)
 *   RencanaKontrol/ListRencanaKontrol/Bulan/{bulan}/Tahun/{tahun}/Nokartu/{kartu}/filter/{filter}
 *   filter 1 = tanggal entri, 2 = tanggal rencana kontrol
 * → daftar disaring lewat noSepAsalKontrol + jnsKontrol "2" (surat kontrol; "1" = SPRI).
 *
 * Latar: dua petugas menyimpan SKDP bersamaan → form yang dibuka sebelum nomor terbit insert ulang,
 * BPJS menolak, nomor kosong menimpa nomor di DB (kasus RI 2026-10-28).
 */
final class SuratKontrolBpjs
{
    /**
     * Nomor surat kontrol yang sudah terbit untuk SEP ini; '' = belum ada / tak bisa dicek.
     * Bulan terbit (hari ini, filter 1) lalu bulan rencana kontrol (filter 2); berhenti begitu ketemu, dan
     * berhenti seketika bila BPJS tak merespons (408) supaya layar tak menunggu timeout kedua.
     */
    public static function nomorTerbit(string $noSep, string $noKartu, ?string $tglKontrol): string
    {
        $noSep = trim($noSep);
        $noKartu = trim($noKartu);
        if ($noSep === '' || $noKartu === '') {
            return '';
        }

        $periode = [[Carbon::now(config('app.timezone')), 1]];
        try {
            $periode[] = [Carbon::createFromFormat('d/m/Y', (string) $tglKontrol), 2];
        } catch (\Throwable) {
            // tanggal kontrol tak terbaca — cukup cek bulan terbit
        }

        foreach ($periode as [$tanggal, $filter]) {
            try {
                $response = VclaimTrait::suratkontrol_list_kartu($tanggal->format('m'), $tanggal->format('Y'), $noKartu, $filter)->getOriginalContent();
            } catch (\Throwable) {
                break;
            }
            $kode = (int) ($response['metadata']['code'] ?? 0);
            if ($kode === 408) {
                break;
            }
            if ($kode !== 200) {
                continue;
            }
            $nomor = self::cocokkan((array) ($response['response']['list'] ?? []), $noSep);
            if ($nomor !== '') {
                return $nomor;
            }
        }

        return '';
    }

    /**
     * Surat kontrol (jnsKontrol 2) milik kartu ini yang rencana kontrolnya JATUH di $tglKontrol (d/m/Y),
     * dari SEP mana pun — untuk menjelaskan tolakan BPJS 203 "Sudah Diterbitkan Rencana Kunjungan Kontrol
     * Di Tanggal Yang Sama" (satu kartu hanya boleh satu rencana kontrol per tanggal). null = tak ketemu / tak bisa dicek.
     */
    public static function cariSuratKontrolPadaTanggal(string $noKartu, ?string $tglKontrol): ?array
    {
        $noKartu = trim($noKartu);
        try {
            $tglRencanaKontrolCarbon = Carbon::createFromFormat('d/m/Y', (string) $tglKontrol);
        } catch (\Throwable) {
            return null;
        }
        if ($noKartu === '') {
            return null;
        }

        try {
            $response = VclaimTrait::suratkontrol_list_kartu($tglRencanaKontrolCarbon->format('m'), $tglRencanaKontrolCarbon->format('Y'), $noKartu, 2)->getOriginalContent();
        } catch (\Throwable) {
            return null;
        }
        if ((int) ($response['metadata']['code'] ?? 0) !== 200) {
            return null;
        }

        return self::cocokkanTanggalRencanaKontrol((array) ($response['response']['list'] ?? []), $tglRencanaKontrolCarbon->format('Y-m-d'));
    }

    /** Murni: surat kontrol (jnsKontrol 2) dengan tglRencanaKontrol = $tglRencanaKontrol; null bila tak ada. */
    public static function cocokkanTanggalRencanaKontrol(array $daftar, string $tglRencanaKontrol): ?array
    {
        foreach ($daftar as $suratKontrol) {
            if (substr((string) ($suratKontrol['tglRencanaKontrol'] ?? ''), 0, 10) === $tglRencanaKontrol && (string) ($suratKontrol['jnsKontrol'] ?? '') === '2'
                && filled($suratKontrol['noSuratKontrol'] ?? null)) {
                return (array) $suratKontrol;
            }
        }

        return null;
    }

    /** Murni: teks penjelas tolakan 203 tanggal sama dari hasil cariSuratKontrolPadaTanggal(); '' bila surat tak ditemukan. */
    public static function penjelasTolakanTanggalSama(?array $suratKontrol, string $noSepSekarang): string
    {
        if ($suratKontrol === null) {
            return '';
        }
        $noSepAsalKontrol = (string) ($suratKontrol['noSepAsalKontrol'] ?? '');
        $namaPoliTujuan = trim((string) ($suratKontrol['namaPoliTujuan'] ?? ''));
        $namaDokter = trim((string) ($suratKontrol['namaDokter'] ?? ''));

        return 'Sudah ada surat kontrol ' . $suratKontrol['noSuratKontrol']
            . ($namaPoliTujuan !== '' ? " ke {$namaPoliTujuan}" : '') . ($namaDokter !== '' ? " ({$namaDokter})" : '')
            . ($noSepAsalKontrol !== '' ? ' dari SEP ' . $noSepAsalKontrol . ($noSepAsalKontrol === trim($noSepSekarang) ? ' (SEP ini)' : ' (SEP lain)') : '')
            . ' — pilih tanggal kontrol lain, atau hapus/ubah surat tersebut di VClaim.';
    }

    /** Murni: nomor surat kontrol (jnsKontrol 2) dari daftar yang SEP asalnya = $noSep; '' bila tak ada. */
    public static function cocokkan(array $daftar, string $noSep): string
    {
        foreach ($daftar as $surat) {
            if ((string) ($surat['noSepAsalKontrol'] ?? '') === $noSep && (string) ($surat['jnsKontrol'] ?? '') === '2'
                && filled($surat['noSuratKontrol'] ?? null)) {
                return (string) $surat['noSuratKontrol'];
            }
        }

        return '';
    }
}
