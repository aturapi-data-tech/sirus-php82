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
