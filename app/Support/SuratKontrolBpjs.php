<?php

namespace App\Support;

use Carbon\Carbon;
use App\Http\Traits\BPJS\VclaimTrait;

/**
 * Cek "surat kontrol untuk kunjungan ini sudah ada di BPJS?" sebelum Insert Rencana Kontrol — dipakai SKDP RJ & RI.
 *
 * VClaim TIDAK punya endpoint cari surat kontrol berdasarkan nomor SEP (RencanaKontrol/nosep/{sep} hanya
 * mengembalikan data SEP-nya). Yang ada: "Data Nomor Surat Kontrol Berdasarkan No Kartu" (Trust Mark)
 *   RencanaKontrol/ListRencanaKontrol/Bulan/{bulan}/Tahun/{tahun}/Nokartu/{kartu}/filter/{filter}
 *   filter 1 = tanggal entri, 2 = tanggal rencana kontrol
 * → daftar disaring jnsKontrol "2" (surat kontrol; "1" = SPRI).
 *
 * Latar:
 *  - dua petugas menyimpan SKDP bersamaan → form yang dibuka sebelum nomor terbit insert ulang, BPJS menolak,
 *    nomor kosong menimpa nomor di DB (kasus RI 2026-10-28);
 *  - surat kontrol terbit di BPJS tetapi nomornya tak tersimpan di DB → insert ulang ditolak 203 "Sudah
 *    Diterbitkan Rencana Kunjungan Kontrol Di Tanggal Yang Sama" (satu kartu = satu rencana kontrol per tanggal).
 */
final class SuratKontrolBpjs
{
    /**
     * Satu-satunya pencocok: cari di BPJS surat kontrol (milik kartu ini) yang SAMA dengan form SKDP.
     * Kecocokan HANYA berdasarkan SEP asal; surat dari SEP lain di tanggal rencana yang sama dilaporkan sebagai
     * tabrakan saja — tidak pernah diambil otomatis (bisa milik kunjungan lain), petugas yang memutuskan.
     * Daftar diambil per bulan terbit (hari ini, filter 1) lalu bulan rencana kontrol (filter 2); berhenti begitu
     * SEP sama ketemu, dan berhenti seketika bila BPJS tak merespons (408) agar layar tak menunggu timeout kedua.
     *
     * Nilai status:
     *  - 'COCOK_SEP'         surat kontrol berasal dari SEP form ini → nomornya dipakai & diperbarui ke BPJS;
     *  - 'TABRAKAN_TANGGAL'  SEP lain di tanggal yang sama → insert pasti ditolak BPJS, jangan dikirim;
     *                        disertai pesanTabrakan (surat mana yang menempati tanggal tsb) untuk toast;
     *  - null                belum ada / tak bisa dicek → boleh insert.
     *
     * @return array{status: ?string, suratKontrol: ?array, pesanTabrakan: string}
     */
    public static function cariSuratKontrolSama(string $noKartu, array $formKontrol): array
    {
        $tidakAda = ['status' => null, 'suratKontrol' => null, 'pesanTabrakan' => ''];
        $noKartu = trim($noKartu);
        $noSep = trim((string) ($formKontrol['noSEP'] ?? ''));
        if ($noKartu === '' || $noSep === '') {
            return $tidakAda;
        }

        $tglRencanaKontrol = null;
        $periode = [[Carbon::now(config('app.timezone')), 1]];
        try {
            $tglRencanaKontrol = Carbon::createFromFormat('d/m/Y', (string) ($formKontrol['tglKontrol'] ?? ''));
            $periode[] = [$tglRencanaKontrol, 2];
        } catch (\Throwable) {
            // tanggal kontrol tak terbaca — cukup cek bulan terbit (pencocokan SEP saja)
        }
        $tglRencanaKontrolYmd = $tglRencanaKontrol?->format('Y-m-d');

        $suratPadaTanggal = null;
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

            foreach ((array) ($response['response']['list'] ?? []) as $suratKontrol) {
                if ((string) ($suratKontrol['jnsKontrol'] ?? '') !== '2' || blank($suratKontrol['noSuratKontrol'] ?? null)) {
                    continue;
                }
                if ((string) ($suratKontrol['noSepAsalKontrol'] ?? '') === $noSep) {
                    return ['status' => 'COCOK_SEP', 'suratKontrol' => (array) $suratKontrol, 'pesanTabrakan' => ''];
                }
                if ($suratPadaTanggal === null && $tglRencanaKontrolYmd !== null
                    && substr((string) ($suratKontrol['tglRencanaKontrol'] ?? ''), 0, 10) === $tglRencanaKontrolYmd) {
                    $suratPadaTanggal = (array) $suratKontrol;
                }
            }
        }

        if ($suratPadaTanggal === null) {
            return $tidakAda;
        }

        $noSepAsalKontrol = trim((string) ($suratPadaTanggal['noSepAsalKontrol'] ?? ''));
        $namaPoliTujuan = trim((string) ($suratPadaTanggal['namaPoliTujuan'] ?? ''));
        $namaDokter = trim((string) ($suratPadaTanggal['namaDokter'] ?? ''));
        $pesanTabrakan = 'Sudah ada surat kontrol ' . $suratPadaTanggal['noSuratKontrol'] . ' di tanggal yang sama'
            . ($namaPoliTujuan !== '' ? " ke {$namaPoliTujuan}" : '') . ($namaDokter !== '' ? " ({$namaDokter})" : '')
            . ($noSepAsalKontrol !== '' ? " dari SEP {$noSepAsalKontrol}" : '')
            . ' — pilih tanggal kontrol lain, atau hapus/ubah surat tersebut di VClaim.';

        return ['status' => 'TABRAKAN_TANGGAL', 'suratKontrol' => $suratPadaTanggal, 'pesanTabrakan' => $pesanTabrakan];
    }
}
