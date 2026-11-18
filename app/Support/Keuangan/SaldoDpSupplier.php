<?php

namespace App\Support\Keuangan;

use Illuminate\Support\Facades\DB;

/**
 * Saldo DP (uang muka) supplier — SATU sumber rumus untuk Topup, Pembayaran Hutang, dan rekap.
 *
 *   saldo = saldo awal tahun terakhir yang ada  (TKTXN_SALDOAWALHUTANGS[NON].SAH_VALUE_TOP, ≤ tahun ini)
 *         + setor   sejak 1 Jan tahun itu        (IMTXN_CASHOUTHDRTOPUPS[NON].CASHOUT_VALUE)
 *         − terpakai sejak 1 Jan tahun itu       (IMTXN_CASHOUTHDRS[NON].CASHOUT_VALUE_TOPUP)
 *         + Σ SEMUA opname                        (TKTXN_SODPSUPPS[NON].SO_D − SO_K)
 *
 * Saldo awal dibuat tutup buku Forms 6i = saldo awal lalu + setor − pakai (koreksi di Forms,
 * mis. AAM 3.235.822, ikut terbawa). Tutup buku itu tidak mengenal tabel opname web, sehingga
 * opname dijumlah semua tahun. Tutup buku tahun berjalan belum dijalankan → saldo awal tahun
 * sebelumnya dipakai + transaksi sejak tahun itu. Tanpa saldo awal sama sekali → semua transaksi.
 *
 * Opname TIDAK dijurnal (Saldo Kas / Neraca tak membaca tabel opname).
 * Nama tabel ditulis literal per jenis (keputusan user: auditor membaca SELECT dengan jelas).
 */
final class SaldoDpSupplier
{
    public const JENIS_PBF = 'pbf';
    public const JENIS_NON_MEDIS = 'non-medis';

    private const TABEL = [
        self::JENIS_PBF => [
            'saldoAwal' => 'tktxn_saldoawalhutangs',
            'setor'     => 'imtxn_cashouthdrtopups',
            'pakai'     => 'imtxn_cashouthdrs',
            'opname'    => 'tktxn_sodpsupps',
            'seq'       => 'sodpsupp_seq',
        ],
        self::JENIS_NON_MEDIS => [
            'saldoAwal' => 'tktxn_saldoawalhutangsnon',
            'setor'     => 'imtxn_cashouthdrtopupsnon',
            'pakai'     => 'imtxn_cashouthdrsnon',
            'opname'    => 'tktxn_sodpsuppsnon',
            'seq'       => 'sodpsuppnon_seq',
        ],
    ];

    public static function tabel(string $jenis, string $peran): string
    {
        $peta = self::TABEL[$jenis] ?? null;
        if ($peta === null || !isset($peta[$peran])) {
            throw new \InvalidArgumentException("Jenis/peran tabel DP tidak dikenal: {$jenis}/{$peran}");
        }

        return $peta[$peran];
    }

    /**
     * Rincian saldo DP satu supplier.
     *
     * @return array{tahunAwal: ?string, awal: int, setor: int, pakai: int, opname: int, saldo: int, dari: string}
     *   dari = 'YYYY-MM-DD' batas transaksi yang dihitung (1 Jan tahun saldo awal, atau 1900-01-01)
     */
    public static function rincian(string $jenis, string $suppId, ?int $tahun = null): array
    {
        $tahun ??= (int) now()->year;
        $t = self::TABEL[$jenis] ?? self::TABEL[self::JENIS_PBF];

        $awalRow = DB::table($t['saldoAwal'])
            ->where('supp_id', $suppId)
            ->where('sah_year', '<=', (string) $tahun)
            ->orderByDesc('sah_year')
            ->first(['sah_year', DB::raw('NVL(sah_value_top,0) as nilai')]);

        $tahunAwal = $awalRow ? (string) $awalRow->sah_year : null;
        $dari = $tahunAwal ? $tahunAwal . '-01-01' : '1900-01-01';

        $setor = (float) DB::table($t['setor'])->where('supp_id', $suppId)
            ->whereRaw("cashout_date >= TO_DATE(?,'YYYY-MM-DD')", [$dari])->sum('cashout_value');
        $pakai = (float) DB::table($t['pakai'])->where('supp_id', $suppId)
            ->whereRaw("cashout_date >= TO_DATE(?,'YYYY-MM-DD')", [$dari])->sum('cashout_value_topup');
        $opname = (float) DB::table($t['opname'])->where('supp_id', $suppId)
            ->sum(DB::raw('NVL(so_d,0) - NVL(so_k,0)'));

        $awal = (int) round((float) ($awalRow->nilai ?? 0));
        $setor = (int) round($setor);
        $pakai = (int) round($pakai);
        $opname = (int) round($opname);

        return [
            'tahunAwal' => $tahunAwal,
            'awal' => $awal,
            'setor' => $setor,
            'pakai' => $pakai,
            'opname' => $opname,
            'saldo' => $awal + $setor - $pakai + $opname,
            'dari' => $dari,
        ];
    }

    public static function saldo(string $jenis, string $suppId): int
    {
        return self::rincian($jenis, $suppId)['saldo'];
    }

    /**
     * Rekap saldo DP per supplier untuk SATU TAHUN (pola saldo kas): saldo awal 1 Jan → disetor,
     * terpakai, opname tahun itu → saldo akhir 31 Des (tahun berjalan: s.d. hari ini). Urut transaksi
     * terakhir (≤ akhir tahun) paling atas. $jenisSupplier: 'all' | 'medis' | 'nonmedis' (tanda master supplier).
     *
     *   saldo awal T = SAH_VALUE_TOP tahun terakhir ≤ T (tahun B) + setor − pakai dari 1 Jan B s.d. 31 Des T−1
     *                  + Σ opname sebelum 1 Jan T          (SAH tak memuat opname — lihat rincian())
     *   saldo akhir T = saldo awal T + setor T − pakai T + opname T
     * Tahun berjalan: saldo akhir = saldo() — sama dengan detail supplier & pilihan Saldo DP di Hutang.
     *
     * @return array<int, array{suppId: string, nama: string, awal: int, setor: int, pakai: int, opname: int,
     *   akhir: int, terakhir: ?string, terakhirUrut: string}>
     */
    public static function rekapTahun(string $jenis, int $tahun, string $cari = '', bool $semua = false, string $jenisSupplier = 'all'): array
    {
        $t = self::TABEL[$jenis] ?? self::TABEL[self::JENIS_PBF];
        $batasAkhir = ($tahun + 1) . '-01-01';

        // Saldo awal tahun B terakhir ≤ T per supplier.
        $dasar = [];
        foreach (DB::table($t['saldoAwal'])->where('sah_year', '<=', (string) $tahun)
            ->get(['supp_id', 'sah_year', DB::raw('NVL(sah_value_top,0) as nilai')]) as $row) {
            $id = (string) $row->supp_id;
            if (!isset($dasar[$id]) || $row->sah_year > $dasar[$id]['tahun']) {
                $dasar[$id] = ['tahun' => (string) $row->sah_year, 'nilai' => (float) $row->nilai];
            }
        }

        // Mutasi per supplier per tahun (≤ T): [supp][tahun] => [nilai, max tanggal]
        $mutasi = function (string $tabel, string $kolomNilai, string $kolomTanggal, string $syarat = '') use ($batasAkhir) {
            $hasil = [];
            $sql = "SELECT supp_id, TO_CHAR({$kolomTanggal},'YYYY') th, SUM({$kolomNilai}) n,
                           TO_CHAR(MAX({$kolomTanggal}),'YYYYMMDDHH24MISS') urut, TO_CHAR(MAX({$kolomTanggal}),'dd/mm/yyyy') tgl
                      FROM {$tabel}
                     WHERE {$kolomTanggal} < TO_DATE(?,'YYYY-MM-DD') {$syarat}
                     GROUP BY supp_id, TO_CHAR({$kolomTanggal},'YYYY')";
            foreach (DB::select($sql, [$batasAkhir]) as $row) {
                $hasil[(string) $row->supp_id][(string) $row->th] = [(float) $row->n, (string) $row->urut, (string) $row->tgl];
            }

            return $hasil;
        };
        $setor = $mutasi($t['setor'], 'NVL(cashout_value,0)', 'cashout_date');
        $pakai = $mutasi($t['pakai'], 'NVL(cashout_value_topup,0)', 'cashout_date', 'AND NVL(cashout_value_topup,0) > 0');
        $opname = $mutasi($t['opname'], 'NVL(so_d,0) - NVL(so_k,0)', 'so_date');

        $ids = array_unique(array_merge(array_keys($dasar), array_keys($setor), array_keys($pakai), array_keys($opname)));
        $master = DB::table('immst_suppliers')->whereIn('supp_id', $ids ?: ['-'])
            ->get(['supp_id', 'supp_name', 'medis', 'nonmedis'])->keyBy(fn ($r) => (string) $r->supp_id);
        $nama = $master->map(fn ($r) => $r->supp_name);
        // Filter jenis supplier (pilihan Semua/Medis/Non-Medis di LOV) — tanda di master supplier.
        if ($jenisSupplier === 'medis' || $jenisSupplier === 'nonmedis') {
            $ids = array_values(array_filter($ids, fn ($id) => (string) ($master[$id]->{$jenisSupplier} ?? '0') === '1'));
        }

        $th = (string) $tahun;
        $baris = [];
        foreach ($ids as $id) {
            $tahunDasar = $dasar[$id]['tahun'] ?? '0000';
            $awal = $dasar[$id]['nilai'] ?? 0.0;
            $terakhirUrut = '';
            $terakhir = null;
            $setorT = $pakaiT = $opnameT = 0.0;

            foreach (['setor' => $setor, 'pakai' => $pakai, 'opname' => $opname] as $jenisMutasi => $peta) {
                foreach ($peta[$id] ?? [] as $tahunMutasi => [$nilai, $urut, $tgl]) {
                    $tahunMutasi = (string) $tahunMutasi; // kunci array numerik jadi int di PHP
                    if ($urut > $terakhirUrut) {
                        $terakhirUrut = $urut;
                        $terakhir = $tgl;
                    }
                    $tanda = $jenisMutasi === 'pakai' ? -1 : 1;
                    if ($tahunMutasi === $th) {
                        match ($jenisMutasi) {
                            'setor' => $setorT += $nilai,
                            'pakai' => $pakaiT += $nilai,
                            'opname' => $opnameT += $nilai,
                        };
                    } elseif ($jenisMutasi === 'opname' || $tahunMutasi >= $tahunDasar) {
                        // setor/pakai sejak tahun saldo awal; opname semua tahun sebelum T
                        $awal += $tanda * $nilai;
                    }
                }
            }

            $akhir = $awal + $setorT - $pakaiT + $opnameT;
            if (!$semua && abs($awal) < 0.5 && abs($akhir) < 0.5 && abs($setorT) < 0.5 && abs($pakaiT) < 0.5 && abs($opnameT) < 0.5) {
                continue;
            }

            $baris[] = [
                'suppId' => $id,
                'nama' => (string) ($nama[$id] ?? $id),
                'awal' => (int) round($awal),
                'setor' => (int) round($setorT),
                'pakai' => (int) round($pakaiT),
                'opname' => (int) round($opnameT),
                'akhir' => (int) round($akhir),
                'terakhir' => $terakhir,
                'terakhirUrut' => $terakhirUrut,
            ];
        }

        $cari = mb_strtoupper(trim($cari));
        if ($cari !== '') {
            $baris = array_values(array_filter($baris, fn ($r) => str_contains(mb_strtoupper($r['nama']), $cari) || str_contains(mb_strtoupper($r['suppId']), $cari)));
        }
        usort($baris, fn ($a, $b) => [$b['terakhirUrut'], $b['akhir']] <=> [$a['terakhirUrut'], $a['akhir']]);

        return $baris;
    }

    /**
     * Kartu DP satu supplier satu tahun (pola kartu stok): saldo awal 1 Jan + mutasi bertanggal
     * (setor = masuk, dipakai bayar nota = keluar, opname = masuk/keluar) dengan saldo berjalan.
     * Saldo awal & akhir = rekapTahun() (satu rumus).
     *
     * @return array{awal: int, masuk: int, keluar: int, akhir: int, mutasi: array<int, array{tgl: string, jenis: string,
     *   no: int, ket: string, info: string, masuk: int, keluar: int, saldo: int}>}
     */
    public static function kartu(string $jenis, string $suppId, int $tahun): array
    {
        $t = self::TABEL[$jenis] ?? self::TABEL[self::JENIS_PBF];
        $baris = collect(self::rekapTahun($jenis, $tahun, '', true))->firstWhere('suppId', $suppId);
        $awal = (int) ($baris['awal'] ?? 0);
        $dari = "{$tahun}-01-01";
        $sampai = ($tahun + 1) . '-01-01';
        $rentang = fn (string $kolom) => ["{$kolom} >= TO_DATE(?,'YYYY-MM-DD') AND {$kolom} < TO_DATE(?,'YYYY-MM-DD')", [$dari, $sampai]];

        [$w, $b] = $rentang('t.cashout_date');
        $setor = DB::table("{$t['setor']} as t")->leftJoin('acmst_accounts as a', 'a.acc_id', '=', 't.acc_id')
            ->where('t.supp_id', $suppId)->whereRaw($w, $b)
            ->get(['t.cashout_no', 't.cashout_desc', 't.acc_id', 'a.acc_name', 't.shift', DB::raw('NVL(t.cashout_value,0) as n'),
                DB::raw("to_char(t.cashout_date,'dd/mm/yyyy hh24:mi:ss') as tgl"), DB::raw("to_char(t.cashout_date,'yyyymmddhh24miss') as urut")])
            ->map(fn ($r) => ['urut' => $r->urut, 'tgl' => $r->tgl, 'jenis' => 'setor', 'no' => (int) $r->cashout_no,
                'ket' => (string) ($r->cashout_desc ?? 'Setor DP'),
                'info' => trim(($r->acc_name ?? '') . ' (' . $r->acc_id . ') · shift ' . ($r->shift ?? '-')),
                'masuk' => (int) round((float) $r->n), 'keluar' => 0]);

        [$w, $b] = $rentang('c.cashout_date');
        $pakaiRows = DB::table("{$t['pakai']} as c")->where('c.supp_id', $suppId)->whereRaw('NVL(c.cashout_value_topup,0) > 0')->whereRaw($w, $b)
            ->get(['c.cashout_no', 'c.shift', DB::raw('NVL(c.cashout_value_topup,0) as n'),
                DB::raw("to_char(c.cashout_date,'dd/mm/yyyy hh24:mi:ss') as tgl"), DB::raw("to_char(c.cashout_date,'yyyymmddhh24miss') as urut")]);
        $tabelDtl = $t['pakai'] === 'imtxn_cashouthdrsnon' ? 'imtxn_cashoutdtlsnon' : 'imtxn_cashoutdtls';
        $notaPerKas = DB::table($tabelDtl)->whereIn('cashout_no', $pakaiRows->pluck('cashout_no')->all() ?: [-1])
            ->get(['cashout_no', 'rcv_no'])->groupBy('cashout_no')->map(fn ($x) => $x->pluck('rcv_no')->implode(', '));
        $pakai = $pakaiRows->map(fn ($r) => ['urut' => $r->urut, 'tgl' => $r->tgl, 'jenis' => 'pakai', 'no' => (int) $r->cashout_no,
            'ket' => 'Bayar nota ' . ($notaPerKas[$r->cashout_no] ?? '-'), 'info' => 'shift ' . ($r->shift ?? '-'),
            'masuk' => 0, 'keluar' => (int) round((float) $r->n)]);

        [$w, $b] = $rentang('o.so_date');
        $opname = DB::table("{$t['opname']} as o")->leftJoin('immst_employers as e', 'e.emp_id', '=', 'o.emp_id')
            ->where('o.supp_id', $suppId)->whereRaw($w, $b)
            ->get(['o.so_no', 'o.so_desc', 'o.saldo_sistem', 'o.saldo_fisik', 'e.emp_name', 'o.user_code', DB::raw('NVL(o.so_d,0) as d'), DB::raw('NVL(o.so_k,0) as k'),
                DB::raw("to_char(o.so_date,'dd/mm/yyyy hh24:mi:ss') as tgl"), DB::raw("to_char(o.so_date,'yyyymmddhh24miss') as urut")])
            ->map(fn ($r) => ['urut' => $r->urut, 'tgl' => $r->tgl, 'jenis' => 'opname', 'no' => (int) $r->so_no,
                'ket' => (string) ($r->so_desc ?? 'Opname'),
                'info' => 'Sistem Rp ' . number_format((float) $r->saldo_sistem, 0, ',', '.') . ' → sebenarnya Rp ' . number_format((float) $r->saldo_fisik, 0, ',', '.')
                    . ' · ' . ($r->emp_name ?: $r->user_code ?: '-'),
                'masuk' => (int) round((float) $r->d), 'keluar' => (int) round((float) $r->k)]);

        $saldo = $awal;
        $mutasi = $setor->concat($pakai)->concat($opname)->sortBy([['urut', 'asc'], ['no', 'asc']])->values()
            ->map(function ($r) use (&$saldo) {
                $saldo += $r['masuk'] - $r['keluar'];
                unset($r['urut']);

                return $r + ['saldo' => $saldo];
            })->all();

        $masuk = (int) array_sum(array_column($mutasi, 'masuk'));
        $keluar = (int) array_sum(array_column($mutasi, 'keluar'));

        return ['awal' => $awal, 'masuk' => $masuk, 'keluar' => $keluar, 'akhir' => $awal + $masuk - $keluar, 'mutasi' => $mutasi];
    }

}
