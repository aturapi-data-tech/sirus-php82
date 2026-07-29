<?php

namespace App\Http\Traits\Keuangan;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Analisa perputaran obat — FAST / SLOW / DEAD moving.
 *
 * Sumber data = LEDGER stok, sama dengan Kartu Stock. JANGAN pakai kolom snapshot
 * IMMST_PRODUCTS.STOCK_* — kolom itu tidak punya dimensi waktu.
 *
 * Query tiap lokasi ditulis TERPISAH dengan nama tabel LITERAL (pola sama dengan
 * PiutangPasienTrait) supaya programmer yang mengaudit SELECT tidak perlu menelusuri
 * helper untuk tahu tabel mana yang dibaca. Konsekuensinya memang lebih banyak baris,
 * dan itu disengaja:
 *
 *   perputaranGudangMedis()    immst_products    · tkview_iostockwhs    · tktxn_saldoawalstocks(sa_stockwh)    · limit_stockwh
 *   perputaranApotekMedis()    immst_products    · tkview_iostockapts   · tktxn_saldoawalstocks(sa_stockapt)   · limit_stock
 *   perputaranGudangNonMedis() immst_productsnon · tkview_iostockwhsnon · tktxn_saldoawalstocksnon(sa_stockwh) · limit_stockwh
 *
 * Klasifikasi memakai **jumlah bulan yang ada pemakaian** dalam periode, bukan
 * sekadar total qty — barang mahal yang keluar sekali setahun tetap SLOW:
 *   DEAD : tidak ada pemakaian sama sekali sepanjang periode
 *   FAST : persen bulan aktif ≥ ambang (default 70% panjang periode)
 *   SLOW : sisanya (ada pemakaian, tapi belum memenuhi ambang fast)
 *
 * "Ambang fast" = berapa persen bulan dalam periode yang HARUS ada pemakaian.
 * Contoh: periode 12 bulan, ambang 70% → butuh pemakaian di ≥ 9 bulan berbeda.
 *
 * Kolom bantu untuk menentukan mana yang krusial diperiksa lebih dulu:
 *   persenAktif   → kolom `persen_aktif`, bulan aktif dibagi panjang periode
 *   cakupanBulan  → kolom `cakupan_bulan`, stok ÷ rata-rata pemakaian per bulan
 *                   (NULL bila tidak ada pemakaian sama sekali)
 *   limitStok     → kolom `limit_stok`, batas minimum dari master obat
 *
 * Semua agregasi & klasifikasi dikerjakan di SQL supaya bisa difilter, diurut,
 * dan dipaginasi tanpa menarik seluruh produk ke PHP.
 */
trait AnalisaPerputaranObatTrait
{
    /** Ambang default: minimal 70% bulan dalam periode ada pemakaian → FAST. */
    public const AMBANG_FAST_DEFAULT = 70;

    /** Panjang periode default (bulan ke belakang, termasuk bulan berjalan). */
    public const PERIODE_BULAN_DEFAULT = 12;

    /** Kombinasi kategori|lokasi yang punya ledger — di luar ini ditolak, bukan ditebak. */
    public const LOKASI_DIDUKUNG = ['medis|04', 'medis|02', 'nonmedis|04'];

    /* ═══════════════════════════════════════════════════════════════
     | Periode & ambang
     ═══════════════════════════════════════════════════════════════ */

    /** Awal periode = awal bulan, N bulan ke belakang termasuk bulan berjalan. */
    protected function perputaranAwalPeriode(int $bulanPeriode): Carbon
    {
        return Carbon::now()->startOfMonth()->subMonths(max(1, $bulanPeriode) - 1);
    }

    protected function perputaranAkhirPeriode(): Carbon
    {
        return Carbon::now()->endOfMonth();
    }

    /** Minimal jumlah bulan aktif supaya sebuah produk dianggap FAST. */
    protected function perputaranMinimalBulanFast(int $bulanPeriode, int $ambangFastPersen): int
    {
        $minimalBulan = (int) ceil(max(1, $bulanPeriode) * max(1, $ambangFastPersen) / 100);

        return max(1, $minimalBulan);
    }

    /* ═══════════════════════════════════════════════════════════════
     | Query per lokasi — nama tabel ditulis literal
     ═══════════════════════════════════════════════════════════════ */

    /**
     * GUDANG MEDIS — immst_products + tkview_iostockwhs + tktxn_saldoawalstocks.sa_stockwh.
     */
    protected function perputaranGudangMedis(int $bulanPeriode, int $ambangFastPersen, string $keyword): Builder
    {
        $awalPeriode = $this->perputaranAwalPeriode($bulanPeriode)->format('d/m/Y');
        $akhirPeriode = $this->perputaranAkhirPeriode()->format('d/m/Y');
        $tahunBerjalan = Carbon::now()->format('Y');
        $minimalBulanFast = $this->perputaranMinimalBulanFast($bulanPeriode, $ambangFastPersen);

        $pemakaianPeriode = DB::table('tkview_iostockwhs')
            ->select('product_id')
            ->selectRaw('NVL(SUM(qty_k),0) as keluar')
            ->selectRaw("COUNT(DISTINCT CASE WHEN NVL(qty_k,0) > 0 THEN TO_CHAR(txn_date,'YYYYMM') END) as bulan_aktif")
            ->selectRaw('MAX(CASE WHEN NVL(qty_k,0) > 0 THEN txn_date END) as keluar_terakhir')
            ->whereRaw("txn_date >= TO_DATE('{$awalPeriode}','dd/mm/yyyy')")
            ->whereRaw("txn_date < TO_DATE('{$akhirPeriode}','dd/mm/yyyy') + 1")
            ->groupBy('product_id');

        $mutasiTahunBerjalan = DB::table('tkview_iostockwhs')
            ->select('product_id')
            ->selectRaw('NVL(SUM(qty_d),0) as masuk_tahun')
            ->selectRaw('NVL(SUM(qty_k),0) as keluar_tahun')
            ->whereRaw("TO_CHAR(txn_date,'YYYY') = '{$tahunBerjalan}'")
            ->groupBy('product_id');

        $query = DB::table('immst_products as p')
            ->leftJoinSub($pemakaianPeriode, 'm', fn($join) => $join->on('m.product_id', '=', 'p.product_id'))
            ->leftJoinSub($mutasiTahunBerjalan, 't', fn($join) => $join->on('t.product_id', '=', 'p.product_id'))
            ->leftJoin('tktxn_saldoawalstocks as s', function ($join) use ($tahunBerjalan) {
                $join->on('s.product_id', '=', 'p.product_id')
                    ->whereRaw("s.sa_year = '{$tahunBerjalan}'");
            })
            ->whereRaw("p.active_status = '1'")
            ->selectRaw("
                p.product_id, p.product_name, p.uom_id, NVL(p.cost_price,0) as cost_price,
                NVL(p.limit_stockwh,0) as limit_stok,
                NVL(s.sa_stockwh,0) + NVL(t.masuk_tahun,0) - NVL(t.keluar_tahun,0) as saldo_akhir,
                NVL(m.keluar,0) as keluar,
                NVL(m.bulan_aktif,0) as bulan_aktif,
                ROUND(NVL(m.bulan_aktif,0) * 100 / {$bulanPeriode}) as persen_aktif,
                ROUND(NVL(m.keluar,0) / {$bulanPeriode}, 2) as rata_bulan,
                CASE WHEN NVL(m.keluar,0) > 0
                     THEN ROUND((NVL(s.sa_stockwh,0) + NVL(t.masuk_tahun,0) - NVL(t.keluar_tahun,0))
                                / (NVL(m.keluar,0) / {$bulanPeriode}), 1)
                     END as cakupan_bulan,
                TO_CHAR(m.keluar_terakhir,'dd/mm/yyyy') as keluar_terakhir,
                CASE WHEN m.keluar_terakhir IS NULL THEN NULL
                     ELSE TRUNC(SYSDATE) - TRUNC(m.keluar_terakhir) END as hari_diam,
                CASE WHEN NVL(m.bulan_aktif,0) = 0 THEN 'DEAD'
                     WHEN NVL(m.bulan_aktif,0) >= {$minimalBulanFast} THEN 'FAST'
                     ELSE 'SLOW' END as klasifikasi
            ");

        $this->perputaranTerapkanPencarian($query, $keyword);

        return $query;
    }

    /**
     * APOTEK MEDIS — immst_products + tkview_iostockapts + tktxn_saldoawalstocks.sa_stockapt.
     * Batas minimum apotek memakai kolom limit_stock (bukan limit_stockwh).
     */
    protected function perputaranApotekMedis(int $bulanPeriode, int $ambangFastPersen, string $keyword): Builder
    {
        $awalPeriode = $this->perputaranAwalPeriode($bulanPeriode)->format('d/m/Y');
        $akhirPeriode = $this->perputaranAkhirPeriode()->format('d/m/Y');
        $tahunBerjalan = Carbon::now()->format('Y');
        $minimalBulanFast = $this->perputaranMinimalBulanFast($bulanPeriode, $ambangFastPersen);

        $pemakaianPeriode = DB::table('tkview_iostockapts')
            ->select('product_id')
            ->selectRaw('NVL(SUM(qty_k),0) as keluar')
            ->selectRaw("COUNT(DISTINCT CASE WHEN NVL(qty_k,0) > 0 THEN TO_CHAR(txn_date,'YYYYMM') END) as bulan_aktif")
            ->selectRaw('MAX(CASE WHEN NVL(qty_k,0) > 0 THEN txn_date END) as keluar_terakhir')
            ->whereRaw("txn_date >= TO_DATE('{$awalPeriode}','dd/mm/yyyy')")
            ->whereRaw("txn_date < TO_DATE('{$akhirPeriode}','dd/mm/yyyy') + 1")
            ->groupBy('product_id');

        $mutasiTahunBerjalan = DB::table('tkview_iostockapts')
            ->select('product_id')
            ->selectRaw('NVL(SUM(qty_d),0) as masuk_tahun')
            ->selectRaw('NVL(SUM(qty_k),0) as keluar_tahun')
            ->whereRaw("TO_CHAR(txn_date,'YYYY') = '{$tahunBerjalan}'")
            ->groupBy('product_id');

        $query = DB::table('immst_products as p')
            ->leftJoinSub($pemakaianPeriode, 'm', fn($join) => $join->on('m.product_id', '=', 'p.product_id'))
            ->leftJoinSub($mutasiTahunBerjalan, 't', fn($join) => $join->on('t.product_id', '=', 'p.product_id'))
            ->leftJoin('tktxn_saldoawalstocks as s', function ($join) use ($tahunBerjalan) {
                $join->on('s.product_id', '=', 'p.product_id')
                    ->whereRaw("s.sa_year = '{$tahunBerjalan}'");
            })
            ->whereRaw("p.active_status = '1'")
            ->selectRaw("
                p.product_id, p.product_name, p.uom_id, NVL(p.cost_price,0) as cost_price,
                NVL(p.limit_stock,0) as limit_stok,
                NVL(s.sa_stockapt,0) + NVL(t.masuk_tahun,0) - NVL(t.keluar_tahun,0) as saldo_akhir,
                NVL(m.keluar,0) as keluar,
                NVL(m.bulan_aktif,0) as bulan_aktif,
                ROUND(NVL(m.bulan_aktif,0) * 100 / {$bulanPeriode}) as persen_aktif,
                ROUND(NVL(m.keluar,0) / {$bulanPeriode}, 2) as rata_bulan,
                CASE WHEN NVL(m.keluar,0) > 0
                     THEN ROUND((NVL(s.sa_stockapt,0) + NVL(t.masuk_tahun,0) - NVL(t.keluar_tahun,0))
                                / (NVL(m.keluar,0) / {$bulanPeriode}), 1)
                     END as cakupan_bulan,
                TO_CHAR(m.keluar_terakhir,'dd/mm/yyyy') as keluar_terakhir,
                CASE WHEN m.keluar_terakhir IS NULL THEN NULL
                     ELSE TRUNC(SYSDATE) - TRUNC(m.keluar_terakhir) END as hari_diam,
                CASE WHEN NVL(m.bulan_aktif,0) = 0 THEN 'DEAD'
                     WHEN NVL(m.bulan_aktif,0) >= {$minimalBulanFast} THEN 'FAST'
                     ELSE 'SLOW' END as klasifikasi
            ");

        $this->perputaranTerapkanPencarian($query, $keyword);

        return $query;
    }

    /**
     * GUDANG NON-MEDIS — immst_productsnon + tkview_iostockwhsnon + tktxn_saldoawalstocksnon.
     * Master non-medis tabelnya SENDIRI; menjoin ke immst_products menghasilkan
     * angka yang kelihatan wajar tapi salah pasangan produknya.
     */
    protected function perputaranGudangNonMedis(int $bulanPeriode, int $ambangFastPersen, string $keyword): Builder
    {
        $awalPeriode = $this->perputaranAwalPeriode($bulanPeriode)->format('d/m/Y');
        $akhirPeriode = $this->perputaranAkhirPeriode()->format('d/m/Y');
        $tahunBerjalan = Carbon::now()->format('Y');
        $minimalBulanFast = $this->perputaranMinimalBulanFast($bulanPeriode, $ambangFastPersen);

        $pemakaianPeriode = DB::table('tkview_iostockwhsnon')
            ->select('product_id')
            ->selectRaw('NVL(SUM(qty_k),0) as keluar')
            ->selectRaw("COUNT(DISTINCT CASE WHEN NVL(qty_k,0) > 0 THEN TO_CHAR(txn_date,'YYYYMM') END) as bulan_aktif")
            ->selectRaw('MAX(CASE WHEN NVL(qty_k,0) > 0 THEN txn_date END) as keluar_terakhir')
            ->whereRaw("txn_date >= TO_DATE('{$awalPeriode}','dd/mm/yyyy')")
            ->whereRaw("txn_date < TO_DATE('{$akhirPeriode}','dd/mm/yyyy') + 1")
            ->groupBy('product_id');

        $mutasiTahunBerjalan = DB::table('tkview_iostockwhsnon')
            ->select('product_id')
            ->selectRaw('NVL(SUM(qty_d),0) as masuk_tahun')
            ->selectRaw('NVL(SUM(qty_k),0) as keluar_tahun')
            ->whereRaw("TO_CHAR(txn_date,'YYYY') = '{$tahunBerjalan}'")
            ->groupBy('product_id');

        $query = DB::table('immst_productsnon as p')
            ->leftJoinSub($pemakaianPeriode, 'm', fn($join) => $join->on('m.product_id', '=', 'p.product_id'))
            ->leftJoinSub($mutasiTahunBerjalan, 't', fn($join) => $join->on('t.product_id', '=', 'p.product_id'))
            ->leftJoin('tktxn_saldoawalstocksnon as s', function ($join) use ($tahunBerjalan) {
                $join->on('s.product_id', '=', 'p.product_id')
                    ->whereRaw("s.sa_year = '{$tahunBerjalan}'");
            })
            ->whereRaw("p.active_status = '1'")
            ->selectRaw("
                p.product_id, p.product_name, p.uom_id, NVL(p.cost_price,0) as cost_price,
                NVL(p.limit_stockwh,0) as limit_stok,
                NVL(s.sa_stockwh,0) + NVL(t.masuk_tahun,0) - NVL(t.keluar_tahun,0) as saldo_akhir,
                NVL(m.keluar,0) as keluar,
                NVL(m.bulan_aktif,0) as bulan_aktif,
                ROUND(NVL(m.bulan_aktif,0) * 100 / {$bulanPeriode}) as persen_aktif,
                ROUND(NVL(m.keluar,0) / {$bulanPeriode}, 2) as rata_bulan,
                CASE WHEN NVL(m.keluar,0) > 0
                     THEN ROUND((NVL(s.sa_stockwh,0) + NVL(t.masuk_tahun,0) - NVL(t.keluar_tahun,0))
                                / (NVL(m.keluar,0) / {$bulanPeriode}), 1)
                     END as cakupan_bulan,
                TO_CHAR(m.keluar_terakhir,'dd/mm/yyyy') as keluar_terakhir,
                CASE WHEN m.keluar_terakhir IS NULL THEN NULL
                     ELSE TRUNC(SYSDATE) - TRUNC(m.keluar_terakhir) END as hari_diam,
                CASE WHEN NVL(m.bulan_aktif,0) = 0 THEN 'DEAD'
                     WHEN NVL(m.bulan_aktif,0) >= {$minimalBulanFast} THEN 'FAST'
                     ELSE 'SLOW' END as klasifikasi
            ");

        $this->perputaranTerapkanPencarian($query, $keyword);

        return $query;
    }

    /** Pencarian kode / nama produk — satu-satunya nilai yang di-bind. */
    protected function perputaranTerapkanPencarian(Builder $query, string $keyword): void
    {
        if (trim($keyword) === '') {
            return;
        }

        $keywordUpper = mb_strtoupper(trim($keyword));

        $query->where(function ($subQuery) use ($keywordUpper) {
            $subQuery->whereRaw('UPPER(p.product_name) LIKE ?', ["%{$keywordUpper}%"])
                ->orWhereRaw('UPPER(p.product_id) LIKE ?', ["%{$keywordUpper}%"]);
        });
    }

    /* ═══════════════════════════════════════════════════════════════
     | Pemilih lokasi + kolom turunan
     ═══════════════════════════════════════════════════════════════ */

    /**
     * Query siap pakai untuk (kategori, lokasi) tertentu.
     *
     * Cabang ditulis eksplisit per kombinasi — TIDAK ada `else` yang menampung
     * nilai tak terduga, supaya lokasi yang belum punya ledger tidak diam-diam
     * dibaca dari tabel milik lokasi lain.
     */
    protected function perputaranQuery(
        string $slCode,
        string $kategori,
        int $bulanPeriode,
        int $ambangFastPersen,
        string $klasifikasi = '',
        string $keyword = '',
    ): Builder {
        $lokasi = $kategori . '|' . $slCode;

        if (!in_array($lokasi, self::LOKASI_DIDUKUNG, true)) {
            return $this->perputaranQueryKosong();
        }

        $inti = null;

        if ($lokasi === 'medis|04') {
            $inti = $this->perputaranGudangMedis($bulanPeriode, $ambangFastPersen, $keyword);
        }

        if ($lokasi === 'medis|02') {
            $inti = $this->perputaranApotekMedis($bulanPeriode, $ambangFastPersen, $keyword);
        }

        if ($lokasi === 'nonmedis|04') {
            $inti = $this->perputaranGudangNonMedis($bulanPeriode, $ambangFastPersen, $keyword);
        }

        $query = DB::query()
            ->fromSub($inti, 'f')
            ->selectRaw('f.*, ROUND(f.saldo_akhir * f.cost_price) as nilai_stok');

        if (in_array($klasifikasi, ['FAST', 'SLOW', 'DEAD'], true)) {
            $query->where('klasifikasi', $klasifikasi);
        }

        return $query;
    }

    /** Hasil kosong berkolom lengkap — dipakai saat lokasi belum punya ledger. */
    protected function perputaranQueryKosong(): Builder
    {
        return DB::query()->fromSub(
            DB::table('dual')->selectRaw("
                CAST(NULL AS VARCHAR2(30)) as product_id, CAST(NULL AS VARCHAR2(200)) as product_name,
                CAST(NULL AS VARCHAR2(30)) as uom_id, 0 as cost_price, 0 as limit_stok, 0 as saldo_akhir,
                0 as keluar, 0 as bulan_aktif, 0 as persen_aktif, 0 as rata_bulan,
                CAST(NULL AS NUMBER) as cakupan_bulan, CAST(NULL AS VARCHAR2(30)) as keluar_terakhir,
                CAST(NULL AS NUMBER) as hari_diam, CAST('DEAD' AS VARCHAR2(10)) as klasifikasi,
                0 as nilai_stok
            ")->whereRaw('1 = 0'),
            'kosong',
        );
    }

    /**
     * Urutan "perlu dicek lebih dulu" — SATU aturan per kelas, bukan seragam:
     *   FAST → cakupan stok PALING TIPIS dulu (risiko kehabisan obat yang dipakai rutin)
     *   SLOW & DEAD → NILAI STOK terbesar dulu (modal paling banyak mengendap)
     * Produk FAST tanpa cakupan (pemakaian nol) didorong ke belakang bloknya.
     */
    protected function perputaranUrutPerhatian(Builder $query): Builder
    {
        return $query
            ->orderByRaw("CASE klasifikasi WHEN 'FAST' THEN 1 WHEN 'SLOW' THEN 2 ELSE 3 END")
            ->orderByRaw("CASE WHEN klasifikasi = 'FAST' THEN NVL(cakupan_bulan, 9999) ELSE 0 END")
            ->orderByRaw("CASE WHEN klasifikasi <> 'FAST' THEN nilai_stok ELSE 0 END DESC");
    }

    /* ═══════════════════════════════════════════════════════════════
     | Ringkasan
     ═══════════════════════════════════════════════════════════════ */

    /**
     * Jumlah item & nilai stok per klasifikasi — di-cache 120 detik karena
     * agregatnya menyapu seluruh ledger periode.
     *
     * @return array{FAST: array{item: int, nilai: int}, SLOW: array{item: int, nilai: int}, DEAD: array{item: int, nilai: int}, total: array{item: int, nilai: int}}
     */
    protected function perputaranRingkasan(
        string $slCode,
        string $kategori,
        int $bulanPeriode,
        int $ambangFastPersen,
        string $keyword = '',
    ): array {
        $kunciCache = $this->perputaranKunciCache($slCode, $kategori, $bulanPeriode, $ambangFastPersen, $keyword);

        return Cache::remember($kunciCache, 120, function () use ($slCode, $kategori, $bulanPeriode, $ambangFastPersen, $keyword) {
            $ringkasanList = DB::query()
                ->fromSub($this->perputaranQuery($slCode, $kategori, $bulanPeriode, $ambangFastPersen, '', $keyword), 'r')
                ->selectRaw("
                    klasifikasi,
                    COUNT(*) as item,
                    NVL(SUM(CASE WHEN nilai_stok > 0 THEN nilai_stok ELSE 0 END),0) as nilai
                ")
                ->groupBy('klasifikasi')
                ->get();

            $ringkasan = [
                'FAST' => ['item' => 0, 'nilai' => 0],
                'SLOW' => ['item' => 0, 'nilai' => 0],
                'DEAD' => ['item' => 0, 'nilai' => 0],
                'total' => ['item' => 0, 'nilai' => 0],
            ];

            foreach ($ringkasanList as $barisRingkasan) {
                $kelas = (string) $barisRingkasan->klasifikasi;

                if (!isset($ringkasan[$kelas])) {
                    continue;
                }

                $ringkasan[$kelas] = ['item' => (int) $barisRingkasan->item, 'nilai' => (int) $barisRingkasan->nilai];
                $ringkasan['total']['item'] += (int) $barisRingkasan->item;
                $ringkasan['total']['nilai'] += (int) $barisRingkasan->nilai;
            }

            return $ringkasan;
        });
    }

    /** Buang cache ringkasan untuk kombinasi filter tertentu. */
    protected function perputaranForgetRingkasan(
        string $slCode,
        string $kategori,
        int $bulanPeriode,
        int $ambangFastPersen,
        string $keyword = '',
    ): void {
        Cache::forget($this->perputaranKunciCache($slCode, $kategori, $bulanPeriode, $ambangFastPersen, $keyword));
    }

    protected function perputaranKunciCache(
        string $slCode,
        string $kategori,
        int $bulanPeriode,
        int $ambangFastPersen,
        string $keyword,
    ): string {
        return 'perputaran-obat:ringkasan:' . md5(implode('|', [$slCode, $kategori, $bulanPeriode, $ambangFastPersen, $keyword]));
    }

    /* ═══════════════════════════════════════════════════════════════
     | Detail per produk — riwayat pembelian & pemakaian bulanan
     ═══════════════════════════════════════════════════════════════ */

    /**
     * RIWAYAT PEMBELIAN OBAT MEDIS — imtxn_receivedtls + imtxn_receivehdrs.
     *
     * Harga satuan netto = nilai baris setelah dua lapis diskon (persen + rupiah),
     * dibagi qty. Rumusnya disamakan dengan Pembayaran Hutang PBF supaya angka
     * pembelian di dua layar tidak pernah berbeda. PPN dihitung terpisah karena
     * melekat di faktur (header), bukan di baris.
     */
    protected function perputaranPembelianMedis(string $productId, int $bulanPeriode): \Illuminate\Support\Collection
    {
        $awalPeriode = $this->perputaranAwalPeriode($bulanPeriode)->format('d/m/Y');

        return DB::table('imtxn_receivedtls as d')
            ->join('imtxn_receivehdrs as h', 'h.rcv_no', '=', 'd.rcv_no')
            ->leftJoin('immst_suppliers as sup', 'sup.supp_id', '=', 'h.supp_id')
            ->where('d.product_id', $productId)
            ->whereRaw("h.rcv_date >= TO_DATE('{$awalPeriode}','dd/mm/yyyy')")
            ->selectRaw("
                h.rcv_no, h.faktur, TO_CHAR(h.rcv_date,'dd/mm/yyyy') as tgl_display, h.rcv_date,
                NVL(sup.supp_name, h.supp_id) as supp_name,
                NVL(d.qty,0) as qty, NVL(d.cost_price,0) as harga_bruto,
                NVL(d.dtl_persen,0) as diskon_persen, NVL(d.dtl_diskon,0) as diskon_rupiah,
                NVL(d.dtl_persen1,0) as diskon_persen2, NVL(d.dtl_diskon1,0) as diskon_rupiah2,
                CASE WHEN NVL(h.rcv_ppn_status,'1') = '1' THEN NVL(h.rcv_ppn,0) ELSE 0 END as ppn_persen,
                (
                    (NVL(d.qty,0)*NVL(d.cost_price,0))
                    - ((NVL(d.qty,0)*NVL(d.cost_price,0)) * NVL(d.dtl_persen,0)/100)
                    - NVL(d.dtl_diskon,0)
                    - ((((NVL(d.qty,0)*NVL(d.cost_price,0))
                         - ((NVL(d.qty,0)*NVL(d.cost_price,0)) * NVL(d.dtl_persen,0)/100)
                         - NVL(d.dtl_diskon,0)) * NVL(d.dtl_persen1,0)/100))
                    - NVL(d.dtl_diskon1,0)
                ) as netto_baris
            ")
            ->orderByDesc('h.rcv_date')
            ->orderByDesc('h.rcv_no')
            ->get();
    }

    /**
     * RIWAYAT PEMBELIAN BARANG NON-MEDIS — imtxn_receivedtlsnon + imtxn_receivehdrsnon.
     */
    protected function perputaranPembelianNonMedis(string $productId, int $bulanPeriode): \Illuminate\Support\Collection
    {
        $awalPeriode = $this->perputaranAwalPeriode($bulanPeriode)->format('d/m/Y');

        return DB::table('imtxn_receivedtlsnon as d')
            ->join('imtxn_receivehdrsnon as h', 'h.rcv_no', '=', 'd.rcv_no')
            ->leftJoin('immst_suppliers as sup', 'sup.supp_id', '=', 'h.supp_id')
            ->where('d.product_id', $productId)
            ->whereRaw("h.rcv_date >= TO_DATE('{$awalPeriode}','dd/mm/yyyy')")
            ->selectRaw("
                h.rcv_no, h.faktur, TO_CHAR(h.rcv_date,'dd/mm/yyyy') as tgl_display, h.rcv_date,
                NVL(sup.supp_name, h.supp_id) as supp_name,
                NVL(d.qty,0) as qty, NVL(d.cost_price,0) as harga_bruto,
                NVL(d.dtl_persen,0) as diskon_persen, NVL(d.dtl_diskon,0) as diskon_rupiah,
                NVL(d.dtl_persen1,0) as diskon_persen2, NVL(d.dtl_diskon1,0) as diskon_rupiah2,
                CASE WHEN NVL(h.rcv_ppn_status,'1') = '1' THEN NVL(h.rcv_ppn,0) ELSE 0 END as ppn_persen,
                (
                    (NVL(d.qty,0)*NVL(d.cost_price,0))
                    - ((NVL(d.qty,0)*NVL(d.cost_price,0)) * NVL(d.dtl_persen,0)/100)
                    - NVL(d.dtl_diskon,0)
                    - ((((NVL(d.qty,0)*NVL(d.cost_price,0))
                         - ((NVL(d.qty,0)*NVL(d.cost_price,0)) * NVL(d.dtl_persen,0)/100)
                         - NVL(d.dtl_diskon,0)) * NVL(d.dtl_persen1,0)/100))
                    - NVL(d.dtl_diskon1,0)
                ) as netto_baris
            ")
            ->orderByDesc('h.rcv_date')
            ->orderByDesc('h.rcv_no')
            ->get();
    }

    /** PEMAKAIAN BULANAN — GUDANG MEDIS (tkview_iostockwhs). */
    protected function perputaranBulananGudangMedis(string $productId, int $bulanPeriode): \Illuminate\Support\Collection
    {
        $awalPeriode = $this->perputaranAwalPeriode($bulanPeriode)->format('d/m/Y');

        return DB::table('tkview_iostockwhs')
            ->selectRaw("TO_CHAR(txn_date,'YYYYMM') as bulan_kode, TO_CHAR(txn_date,'mm/yyyy') as bulan_display")
            ->selectRaw('NVL(SUM(qty_d),0) as masuk, NVL(SUM(qty_k),0) as keluar')
            ->where('product_id', $productId)
            ->whereRaw("txn_date >= TO_DATE('{$awalPeriode}','dd/mm/yyyy')")
            ->groupByRaw("TO_CHAR(txn_date,'YYYYMM'), TO_CHAR(txn_date,'mm/yyyy')")
            ->orderByRaw("TO_CHAR(txn_date,'YYYYMM')")
            ->get();
    }

    /** PEMAKAIAN BULANAN — APOTEK MEDIS (tkview_iostockapts). */
    protected function perputaranBulananApotekMedis(string $productId, int $bulanPeriode): \Illuminate\Support\Collection
    {
        $awalPeriode = $this->perputaranAwalPeriode($bulanPeriode)->format('d/m/Y');

        return DB::table('tkview_iostockapts')
            ->selectRaw("TO_CHAR(txn_date,'YYYYMM') as bulan_kode, TO_CHAR(txn_date,'mm/yyyy') as bulan_display")
            ->selectRaw('NVL(SUM(qty_d),0) as masuk, NVL(SUM(qty_k),0) as keluar')
            ->where('product_id', $productId)
            ->whereRaw("txn_date >= TO_DATE('{$awalPeriode}','dd/mm/yyyy')")
            ->groupByRaw("TO_CHAR(txn_date,'YYYYMM'), TO_CHAR(txn_date,'mm/yyyy')")
            ->orderByRaw("TO_CHAR(txn_date,'YYYYMM')")
            ->get();
    }

    /** PEMAKAIAN BULANAN — GUDANG NON-MEDIS (tkview_iostockwhsnon). */
    protected function perputaranBulananGudangNonMedis(string $productId, int $bulanPeriode): \Illuminate\Support\Collection
    {
        $awalPeriode = $this->perputaranAwalPeriode($bulanPeriode)->format('d/m/Y');

        return DB::table('tkview_iostockwhsnon')
            ->selectRaw("TO_CHAR(txn_date,'YYYYMM') as bulan_kode, TO_CHAR(txn_date,'mm/yyyy') as bulan_display")
            ->selectRaw('NVL(SUM(qty_d),0) as masuk, NVL(SUM(qty_k),0) as keluar')
            ->where('product_id', $productId)
            ->whereRaw("txn_date >= TO_DATE('{$awalPeriode}','dd/mm/yyyy')")
            ->groupByRaw("TO_CHAR(txn_date,'YYYYMM'), TO_CHAR(txn_date,'mm/yyyy')")
            ->orderByRaw("TO_CHAR(txn_date,'YYYYMM')")
            ->get();
    }

    /** Master obat medis (harga jual ada di sini). */
    protected function perputaranMasterMedis(string $productId): ?object
    {
        return DB::table('immst_products as p')
            ->leftJoin('immst_suppliers as sup', 'sup.supp_id', '=', 'p.supp_id')
            ->leftJoin('immst_uoms as u', 'u.uom_id', '=', 'p.uom_id')
            ->where('p.product_id', $productId)
            ->selectRaw("
                p.product_id, p.product_name, p.uom_id, NVL(u.uom_desc, p.uom_id) as uom_desc,
                NVL(p.cost_price,0) as cost_price, NVL(p.sales_price,0) as sales_price,
                NVL(p.qty_box,0) as qty_box, NVL(p.limit_stock,0) as limit_stock,
                NVL(p.limit_stockwh,0) as limit_stockwh,
                NVL(sup.supp_name, p.supp_id) as supp_name
            ")
            ->first();
    }

    /** Master barang non-medis — TIDAK punya kolom sales_price. */
    protected function perputaranMasterNonMedis(string $productId): ?object
    {
        return DB::table('immst_productsnon as p')
            ->leftJoin('immst_suppliers as sup', 'sup.supp_id', '=', 'p.supp_id')
            ->leftJoin('immst_uoms as u', 'u.uom_id', '=', 'p.uom_id')
            ->where('p.product_id', $productId)
            ->selectRaw("
                p.product_id, p.product_name, p.uom_id, NVL(u.uom_desc, p.uom_id) as uom_desc,
                NVL(p.cost_price,0) as cost_price, 0 as sales_price,
                NVL(p.qty_box,0) as qty_box, 0 as limit_stock,
                NVL(p.limit_stockwh,0) as limit_stockwh,
                NVL(sup.supp_name, p.supp_id) as supp_name
            ")
            ->first();
    }

    /**
     * DISTRIBUSI KE RUANGAN — OBAT MEDIS (imtxn_trfdtls + imtxn_trfhdrs).
     *
     * Ruangan (OK, UGD, VK, ICU, laborat, dst.) TIDAK punya ledger stok sendiri —
     * yang ada di database hanya view gudang, apotek, dan gudang non-medis. Jadi
     * jejak obat ke ruangan hanya bisa ditelusuri sampai **serah-terima transfer**,
     * bukan sampai pemakaian di dalam ruangannya.
     *
     * Hanya transfer berstatus POSTED ('L') yang dihitung; draft ('A') dan batal
     * ('F') diabaikan supaya angkanya sama dengan yang benar-benar mengurangi stok.
     */
    protected function perputaranDistribusiRuanganMedis(string $productId, int $bulanPeriode): \Illuminate\Support\Collection
    {
        $awalPeriode = $this->perputaranAwalPeriode($bulanPeriode)->format('d/m/Y');

        return DB::table('imtxn_trfdtls as d')
            ->join('imtxn_trfhdrs as h', 'h.trf_no', '=', 'd.trf_no')
            ->leftJoin('immst_stocklocations as asal', 'asal.sl_code', '=', 'h.sl_codefrom')
            ->leftJoin('immst_stocklocations as tujuan', 'tujuan.sl_code', '=', 'h.sl_codeto')
            ->where('d.product_id', $productId)
            ->where('h.trf_status', 'L')
            ->whereRaw("h.trf_date >= TO_DATE('{$awalPeriode}','dd/mm/yyyy')")
            ->groupBy('h.sl_codefrom', 'asal.sl_name', 'h.sl_codeto', 'tujuan.sl_name')
            ->selectRaw("
                h.sl_codefrom, NVL(asal.sl_name, h.sl_codefrom) as asal_nama,
                h.sl_codeto, NVL(tujuan.sl_name, h.sl_codeto) as tujuan_nama,
                COUNT(DISTINCT h.trf_no) as jumlah_transfer,
                NVL(SUM(d.qty),0) as qty,
                TO_CHAR(MAX(h.trf_date),'dd/mm/yyyy') as transfer_terakhir
            ")
            ->orderByRaw('NVL(SUM(d.qty),0) DESC')
            ->get();
    }

    /** DISTRIBUSI KE RUANGAN — NON-MEDIS (imtxn_trfdtlnonmedes + imtxn_trfhdrnonmedes). */
    protected function perputaranDistribusiRuanganNonMedis(string $productId, int $bulanPeriode): \Illuminate\Support\Collection
    {
        $awalPeriode = $this->perputaranAwalPeriode($bulanPeriode)->format('d/m/Y');

        return DB::table('imtxn_trfdtlnonmedes as d')
            ->join('imtxn_trfhdrnonmedes as h', 'h.trf_no', '=', 'd.trf_no')
            ->leftJoin('immst_stocklocations as asal', 'asal.sl_code', '=', 'h.sl_codefrom')
            ->leftJoin('immst_stocklocations as tujuan', 'tujuan.sl_code', '=', 'h.sl_codeto')
            ->where('d.product_id', $productId)
            ->where('h.trf_status', 'L')
            ->whereRaw("h.trf_date >= TO_DATE('{$awalPeriode}','dd/mm/yyyy')")
            ->groupBy('h.sl_codefrom', 'asal.sl_name', 'h.sl_codeto', 'tujuan.sl_name')
            ->selectRaw("
                h.sl_codefrom, NVL(asal.sl_name, h.sl_codefrom) as asal_nama,
                h.sl_codeto, NVL(tujuan.sl_name, h.sl_codeto) as tujuan_nama,
                COUNT(DISTINCT h.trf_no) as jumlah_transfer,
                NVL(SUM(d.qty),0) as qty,
                TO_CHAR(MAX(h.trf_date),'dd/mm/yyyy') as transfer_terakhir
            ")
            ->orderByRaw('NVL(SUM(d.qty),0) DESC')
            ->get();
    }
}
