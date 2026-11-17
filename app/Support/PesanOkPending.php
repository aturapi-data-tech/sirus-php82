<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Pesan toast saat kasir/transfer tertahan karena transaksi Kamar Operasi belum
 * dikirim ke tagihan induk (`ok_status = 'A'`).
 *
 * Pesan lama hanya menyebut nomor OK ("Transaksi Tindakan Operasi No. 5539 belum
 * ditransfer ...") sehingga petugas kasir harus menebak operasi yang mana, kapan,
 * dan siapa yang harus dihubungi. Di sini setiap nomor OK dirangkai dengan
 * tanggal, dokter, dan tindakannya, plus identitas pasien & langkah penyelesaian.
 *
 * Isi berupa HTML (toastr tidak meng-escape) — semua nilai dari DB lewat e().
 */
final class PesanOkPending
{
    /** Tindakan yang ditampilkan per OK; sisanya diringkas "+N lainnya". */
    private const MAKS_TINDAKAN = 3;

    /** Toast ini panjang, jadi diberi waktu baca lebih lama dari default 4 detik. */
    public const OPSI_TOAST = ['timeOut' => 20000, 'extendedTimeOut' => 8000];

    /**
     * @param  array<int,string>  $nomorOk  hasil daftarOkPending{RJ,UGD,RI}()
     * @param  string  $sumber  'RJ' | 'UGD' | 'RI'
     * @param  int|string  $refNo  rj_no / rihdr_no kunjungan induk
     * @param  string  $akibat  mis. "Proses pulang tidak bisa dilakukan."
     */
    public static function rangkai(array $nomorOk, string $sumber, int|string $refNo, string $akibat): string
    {
        $labelTagihan = match ($sumber) {
            'RJ' => 'RJ',
            'UGD' => 'UGD',
            default => 'Rawat Inap',
        };

        $baris = ['<b>' . e($akibat) . '</b>'];

        $pasien = self::pasien($sumber, $refNo);
        if ($pasien) {
            $baris[] = 'Pasien: ' . e($pasien->reg_no) . ' — ' . e($pasien->reg_name)
                . ($pasien->masuk ? ' (masuk ' . e($pasien->masuk) . ')' : '');
        }

        $baris[] = 'Masih ada ' . count($nomorOk) . ' transaksi Kamar Operasi yang biayanya belum dikirim ke tagihan ' . $labelTagihan . ':';

        foreach (self::rincianOk($nomorOk) as $ok) {
            $baris[] = '• <b>OK No. ' . e($ok['ok_reg']) . '</b>'
                . ($ok['tanggal'] ? ' — ' . e($ok['tanggal']) : '')
                . '<br>&nbsp;&nbsp;Operator: ' . e($ok['operator'] ?: '-')
                . ' · Anestesi: ' . e($ok['anestesi'] ?: '-')
                . '<br>&nbsp;&nbsp;Tindakan: ' . e($ok['tindakan'] ?: 'belum diisi');
        }

        $baris[] = 'Hubungi petugas <b>Kamar Operasi</b> untuk membuka transaksi tersebut lalu menekan tombol '
            . '<b>"Kirim ke Tagihan ' . $labelTagihan . '"</b>. Setelah itu ulangi proses ini.';

        return implode('<br>', $baris);
    }

    /** @return object{reg_no:string,reg_name:string,masuk:?string}|null */
    private static function pasien(string $sumber, int|string $refNo): ?object
    {
        [$tabel, $kunci, $kolomTanggal] = match ($sumber) {
            'RJ' => ['rstxn_rjhdrs', 'rj_no', 'rj_date'],
            'UGD' => ['rstxn_ugdhdrs', 'rj_no', 'rj_date'],
            default => ['rstxn_rihdrs', 'rihdr_no', 'entry_date'],
        };

        return DB::table($tabel . ' as h')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->select('h.reg_no', 'p.reg_name', DB::raw("to_char(h.{$kolomTanggal},'dd/mm/yyyy hh24:mi') as masuk"))
            ->where("h.{$kunci}", $refNo)
            ->first();
    }

    /** @return array<int,array{ok_reg:string,tanggal:?string,operator:?string,anestesi:?string,tindakan:string}> */
    private static function rincianOk(array $nomorOk): array
    {
        if ($nomorOk === []) {
            return [];
        }

        $header = DB::table('rstxn_oks as o')
            ->leftJoin('rsmst_doctors as dopr', 'dopr.dr_id', '=', 'o.dr_id')
            ->leftJoin('rsmst_doctors as danes', 'danes.dr_id', '=', 'o.dr_id_ok')
            ->select(
                'o.ok_reg',
                DB::raw("to_char(o.ok_date,'dd/mm/yyyy hh24:mi') as tanggal"),
                DB::raw('dopr.dr_name as operator'),
                DB::raw('danes.dr_name as anestesi'),
            )
            ->whereIn('o.ok_reg', $nomorOk)
            ->orderBy('o.ok_reg')
            ->get();

        $tindakanPerOk = DB::table('rstxn_okacts as t')
            ->leftJoin('rsmst_accdocs as a', 'a.accdoc_id', '=', 't.accdoc_id')
            ->select('t.ok_reg', 'a.accdoc_desc')
            ->whereIn('t.ok_reg', $nomorOk)
            ->orderBy('t.okact_id')
            ->get()
            ->groupBy(fn($tindakan) => (string) $tindakan->ok_reg);

        return $header->map(function ($ok) use ($tindakanPerOk) {
            $daftar = ($tindakanPerOk[(string) $ok->ok_reg] ?? collect())
                ->pluck('accdoc_desc')
                ->filter()
                ->values();

            $tindakan = $daftar->take(self::MAKS_TINDAKAN)->implode(', ');
            if ($daftar->count() > self::MAKS_TINDAKAN) {
                $tindakan .= ' +' . ($daftar->count() - self::MAKS_TINDAKAN) . ' lainnya';
            }

            return [
                'ok_reg' => (string) $ok->ok_reg,
                'tanggal' => $ok->tanggal,
                'operator' => $ok->operator,
                'anestesi' => $ok->anestesi,
                'tindakan' => $tindakan,
            ];
        })->all();
    }
}
