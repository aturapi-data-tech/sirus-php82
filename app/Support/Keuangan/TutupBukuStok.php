<?php

namespace App\Support\Keuangan;

use Illuminate\Support\Facades\DB;

/**
 * Tutup buku tahunan SALDO STOK ke TKTXN_SALDOAWALSTOCKS[NON] — meniru tutup buku Forms 6i
 * (dibuktikan di data lokal 2026-11-18, tutup 2024 & 2025: gudang 99,6%, apotek 98%, non-medis 100%;
 * sisanya mutasi yang berubah setelah tutup buku):
 *
 *   produk yang ikut : SEMUA produk master (aktif maupun tidak) — Forms 2026 = 2.840 = isi master
 *   SA_STOCKWH (T+1)  = SA_STOCKWH (T)  + Σ qty_d − Σ qty_k tahun T   (tkview_iostockwhs / …whsnon)
 *   SA_STOCKAPT (T+1) = SA_STOCKAPT (T) + Σ qty_d − Σ qty_k tahun T   (tkview_iostockapts; non-medis tak punya apotek)
 *   HPP_PRODUCT       = harga beli master (cost_price) SAAT tutup buku dijalankan (snapshot; 326 dari 327
 *                       beda HPP 2026 vs master sekarang = produk yang dibeli lagi di 2026). Karena itu
 *                       baris yang SUDAH ADA dipertahankan HPP-nya — hanya baris baru memakai master sekarang.
 *
 * Nama tabel literal per jenis (keputusan user: auditor membaca SELECT dengan jelas).
 */
final class TutupBukuStok
{
    public const JENIS_MEDIS = 'medis';
    public const JENIS_NON_MEDIS = 'non-medis';

    private const TABEL = [
        self::JENIS_MEDIS => [
            'saldoAwal' => 'tktxn_saldoawalstocks',
            'produk'    => 'immst_products',
            'gudang'    => 'tkview_iostockwhs',
            'apotek'    => 'tkview_iostockapts',
        ],
        self::JENIS_NON_MEDIS => [
            'saldoAwal' => 'tktxn_saldoawalstocksnon',
            'produk'    => 'immst_productsnon',
            'gudang'    => 'tkview_iostockwhsnon',
            'apotek'    => null,
        ],
    ];

    /**
     * @return array<int, array{productId: string, nama: string, aktif: bool,
     *   whAwal: float, whMutasi: float, whBaru: float, aptAwal: float, aptMutasi: float, aptBaru: float,
     *   hpp: float, ada: bool, whLama: ?float, aptLama: ?float, status: string}>
     */
    public static function pratinjau(string $jenis, int $tahun): array
    {
        $t = self::TABEL[$jenis] ?? throw new \InvalidArgumentException("Jenis tidak dikenal: {$jenis}");
        $thn = (string) $tahun;
        $berikut = (string) ($tahun + 1);
        $dari = "{$thn}-01-01";
        $sampai = "{$berikut}-01-01";

        $mutasi = fn (string $view) => collect(DB::select(
            "SELECT product_id, SUM(NVL(qty_d,0)) - SUM(NVL(qty_k,0)) AS m FROM {$view}
              WHERE txn_date >= TO_DATE(?,'YYYY-MM-DD') AND txn_date < TO_DATE(?,'YYYY-MM-DD')
              GROUP BY product_id", [$dari, $sampai]))->mapWithKeys(fn ($r) => [(string) $r->product_id => (float) $r->m]);

        $mutasiGudang = $mutasi($t['gudang']);
        $mutasiApotek = $t['apotek'] ? $mutasi($t['apotek']) : collect();

        $awal = DB::table($t['saldoAwal'])->where('sa_year', $thn)
            ->get(['product_id', 'sa_stockwh', 'sa_stockapt'])->keyBy(fn ($r) => (string) $r->product_id);
        $tersimpan = DB::table($t['saldoAwal'])->where('sa_year', $berikut)
            ->get(['product_id', 'sa_stockwh', 'sa_stockapt', 'hpp_product'])->keyBy(fn ($r) => (string) $r->product_id);

        return DB::table($t['produk'])->orderBy('product_name')
            ->get(['product_id', 'product_name', 'cost_price', 'active_status'])
            ->map(function ($p) use ($awal, $tersimpan, $mutasiGudang, $mutasiApotek) {
                $id = (string) $p->product_id;
                $a = $awal[$id] ?? null;
                $s = $tersimpan[$id] ?? null;
                $whAwal = (float) ($a->sa_stockwh ?? 0);
                $aptAwal = (float) ($a->sa_stockapt ?? 0);
                $whMut = (float) ($mutasiGudang[$id] ?? 0);
                $aptMut = (float) ($mutasiApotek[$id] ?? 0);
                $whBaru = $whAwal + $whMut;
                $aptBaru = $aptAwal + $aptMut;

                $status = 'baru';
                if ($s !== null) {
                    $status = (self::sama((float) $s->sa_stockwh, $whBaru) && self::sama((float) ($s->sa_stockapt ?? 0), $aptBaru)) ? 'sama' : 'beda';
                }

                return [
                    'productId' => $id,
                    'nama' => (string) ($p->product_name ?? ''),
                    'aktif' => (string) ($p->active_status ?? '') === '1',
                    'whAwal' => $whAwal,
                    'whMutasi' => $whMut,
                    'whBaru' => $whBaru,
                    'aptAwal' => $aptAwal,
                    'aptMutasi' => $aptMut,
                    'aptBaru' => $aptBaru,
                    // baris ada → HPP snapshot dipertahankan; baris baru → harga master sekarang
                    'hpp' => (float) ($s !== null ? ($s->hpp_product ?? 0) : ($p->cost_price ?? 0)),
                    'ada' => $s !== null,
                    'whLama' => $s !== null ? (float) $s->sa_stockwh : null,
                    'aptLama' => $s !== null ? (float) ($s->sa_stockapt ?? 0) : null,
                    'status' => $status,
                ];
            })->all();
    }

    public static function sama(?float $a, ?float $b): bool
    {
        return $a !== null && $b !== null && abs($a - $b) < 0.001;
    }

    /**
     * Tulis saldo awal stok T+1: baris baru dibuat (HPP = harga master sekarang); baris beda diperbarui
     * qty-nya saja (HPP snapshot dipertahankan) bila $timpa; baris sama dilewati.
     *
     * @return array{tahunBaru: string, dibuat: int, diubah: int, sama: int, dilewati: int, sebelum: array<string, array{0: ?float, 1: ?float}>}
     */
    public static function proses(string $jenis, int $tahun, bool $timpa): array
    {
        $t = self::TABEL[$jenis] ?? throw new \InvalidArgumentException("Jenis tidak dikenal: {$jenis}");
        $berikut = (string) ($tahun + 1);
        $baris = self::pratinjau($jenis, $tahun);

        return DB::transaction(function () use ($t, $baris, $berikut, $timpa) {
            DB::table($t['saldoAwal'])->where('sa_year', $berikut)->lockForUpdate()->get(['product_id']);

            $dibuat = $diubah = $sama = $dilewati = 0;
            $sebelum = [];
            $sisipan = [];
            foreach ($baris as $row) {
                if ($row['status'] === 'sama') {
                    $sama++;
                    continue;
                }
                if ($row['status'] === 'baru') {
                    $sisipan[] = [
                        'sa_year' => $berikut,
                        'product_id' => $row['productId'],
                        'sa_stockwh' => $row['whBaru'],
                        'sa_stockapt' => $row['aptBaru'],
                        'hpp_product' => $row['hpp'],
                    ];
                    $dibuat++;
                    continue;
                }
                if (!$timpa) {
                    $dilewati++;
                    continue;
                }
                $sebelum[$row['productId']] = [$row['whLama'], $row['aptLama']];
                DB::table($t['saldoAwal'])->where('sa_year', $berikut)->where('product_id', $row['productId'])
                    ->update(['sa_stockwh' => $row['whBaru'], 'sa_stockapt' => $row['aptBaru']]);
                $diubah++;
            }

            foreach (array_chunk($sisipan, 200) as $potongan) {
                DB::table($t['saldoAwal'])->insert($potongan);
            }

            return ['tahunBaru' => $berikut, 'dibuat' => $dibuat, 'diubah' => $diubah, 'sama' => $sama, 'dilewati' => $dilewati, 'sebelum' => $sebelum];
        });
    }
}
