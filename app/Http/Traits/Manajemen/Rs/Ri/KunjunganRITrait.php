<?php

namespace App\Http\Traits\Manajemen\Rs\Ri;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Session;

/**
 * Shared logic untuk Laporan Kunjungan Rawat Inap (ri-bulanan & ri-tahunan).
 *
 * Konvensi:
 *   - Filter periode pakai **exit_date** (tanggal pulang) — sesuai konvensi
 *     BPJS reporting RI yang dihitung saat pasien discharge.
 *   - Pasien Kronis (klaim_id='KR') di-exclude (tidak relevan di RI tapi safety).
 *   - Status RI (rstxn_rihdrs.ri_status):
 *       I = Dirawat (sedang inap aktif — belum punya exit_date, jadi TIDAK ikut laporan ini)
 *       P = Pulang
 *       F = Batal → dikeluarkan dari semua hitungan (sama dengan RL 3.2)
 *     Status kosong = Dirawat (Daftar RI memakai NVL(ri_status,'I')).
 *     exit_date dan ri_status='P' ditulis BERSAMAAN oleh Kasir RI (cabang Lunas maupun Bon),
 *     dan batal-bayar mengosongkan keduanya. Jadi kunjungan ber-exit_date yang statusnya bukan P
 *     adalah data rusak (di Daftar RI masih tampil "Dirawat") → dikeluarkan dari hitungan.
 *     (Dulu trait ini mengira pulang = 'L' sehingga kolom "Selesai" selalu 0 — 'L' itu nilai
 *     status_pulang: L = Lunas, H = Bon/Hutang, bukan ri_status.)
 *   - LOS / lama dirawat = exit_date - entry_date (Oracle date arithmetic, NUMBER hari
 *     berpecahan — masuk 23.00 keluar 01.00 = 0,08 hari). Sama dengan RL 3.2.
 *   - Σ lama dirawat dibebankan SELURUHNYA ke periode tanggal PULANG. Ini pendekatan, bukan
 *     sensus harian: pasien yang masih dirawat belum terhitung, dan hari rawat lintas bulan
 *     jatuh ke bulan pulangnya. Karena itu komponen hitungan ditampilkan di layar.
 *   - Hari periode = hari yang SUDAH BERJALAN (lihat hariPeriodeBerjalan()), bukan panjang
 *     kalender penuh — supaya BOR/TOI tahun/bulan berjalan tidak terencerkan.
 *   - ALOS = Σ lama dirawat ÷ pasien keluar.
 *   - Parameter TT per bangsal ($parameterBangsal): TT bawaan = jumlah bed di master, bisa diubah,
 *     dan tiap bangsal bisa DIKELUARKAN dari hitungan (bed bayi, UGD). Bangsal yang dikeluarkan
 *     dibuang TT-nya SEKALIGUS pasien & hari rawatnya dari BOR/ALOS/TOI/BTO — membuang TT saja
 *     akan menaikkan BOR secara palsu. Jumlah kunjungan (Total/BPJS/UMUM) tetap semua pasien.
 *   - Data janggal DIKELUARKAN dari komponen indikator tetapi tetap dilaporkan sebagai peringatan
 *     (kondisiDataWajarSql): status bukan P, lama dirawat negatif, tanpa tanggal masuk, atau di atas
 *     $batasLosHari. Yang sah-tapi-patut-dilihat (LOS < 1 hari, kamar tanpa bangsal) hanya diperingatkan.
 *     Selalu berlaku: total = keluar_bor + luar_bangsal + anomali_dikeluarkan.
 *   - BPJS = klaim_status='BPJS' OR klaim_id='JM'.
 *   - Breakdown spesifik RI: per **Bangsal** (bukan poli/dokter).
 */
trait KunjunganRITrait
{
    /**
     * Parameter TT per bangsal — daftar berindeks (BUKAN peta ber-kunci bangsal_id: kunci mirip
     * angka diurutkan ulang oleh JS Livewire). Tiap baris:
     *   bangsal_id ('' = kamar tanpa bangsal), bangsal_name, tt_db (jumlah bed di master),
     *   tt (yang dipakai, bisa diubah), dihitung (ikut BOR atau tidak).
     * Disimpan di sesi & dipakai bersama tab Tahunan dan Multi-Tahun.
     */
    #[Session(key: 'laporan-kunjungan-ri-parameter-bangsal')]
    public array $parameterBangsal = [];

    /**
     * Batas lama dirawat yang masih dianggap wajar (hari). Di atas ini hampir pasti tanggal pulang
     * terlambat diisi → dikeluarkan dari hitungan. Tanpa tipe: kotak isian yang dikosongkan
     * mengirim '' dan properti bertipe int akan melempar TypeError.
     */
    #[Session(key: 'laporan-kunjungan-ri-batas-los')]
    public $batasLosHari = 60;

    public const BATAS_LOS_BAWAAN = 60;

    public function batasLos(): int
    {
        $batas = (int) $this->batasLosHari;

        return $batas >= 1 && $batas <= 3650 ? $batas : self::BATAS_LOS_BAWAAN;
    }

    public function resetBatasLos(): void
    {
        $this->batasLosHari = self::BATAS_LOS_BAWAAN;
    }

    /**
     * Potongan SQL "tanggal pulang & lama dirawat kunjungan ini bisa dipercaya":
     * status P (satu-satunya status yang sah bersama exit_date), punya tanggal masuk, lama dirawat
     * tidak negatif dan tidak melewati batas. Batas disisipkan sebagai angka bulat.
     */
    protected function kondisiDataWajarSql(string $alias = 'h'): string
    {
        $batas = $this->batasLos();

        return "(NVL({$alias}.ri_status,'-') = 'P' AND {$alias}.entry_date IS NOT NULL AND {$alias}.exit_date >= {$alias}.entry_date AND {$alias}.exit_date - {$alias}.entry_date <= {$batas})";
    }

    /** Jumlah bed per bangsal dari master, termasuk kelompok kamar tanpa bangsal (bangsal_id ''). */
    protected function daftarBangsalTT(): array
    {
        return DB::table('rsmst_beds as bd')
            ->join('rsmst_rooms as r', 'r.room_id', '=', 'bd.room_id')
            ->leftJoin('rsmst_bangsals as b', 'b.bangsal_id', '=', 'r.bangsal_id')
            ->select('r.bangsal_id', DB::raw('MAX(b.bangsal_name) as bangsal_name'), DB::raw('COUNT(*) as tt'))
            ->groupBy('r.bangsal_id')
            ->orderBy(DB::raw('MAX(b.bangsal_name)'))
            ->get()
            ->map(fn($row) => [
                'bangsal_id'   => (string) ($row->bangsal_id ?? ''),
                'bangsal_name' => (string) ($row->bangsal_name ?? '(Tanpa Bangsal)'),
                'tt_db'        => (int) $row->tt,
            ])
            ->all();
    }

    /**
     * Susun $parameterBangsal dari master, lalu tempelkan setelan tersimpan (tt & dihitung)
     * menurut bangsal_id. Bangsal baru ikut bawaan DB; bangsal yang sudah tak ada terbuang.
     */
    protected function muatParameterBangsal(): void
    {
        $tersimpan = collect($this->parameterBangsal)->keyBy(fn($row) => (string) ($row['bangsal_id'] ?? ''));

        $this->parameterBangsal = collect($this->daftarBangsalTT())
            ->map(function (array $row) use ($tersimpan) {
                $lama = $tersimpan->get($row['bangsal_id']);

                return $row + [
                    'tt'       => $lama !== null ? max(0, (int) ($lama['tt'] ?? $row['tt_db'])) : $row['tt_db'],
                    'dihitung' => $lama !== null ? (bool) ($lama['dihitung'] ?? true) : true,
                ];
            })
            ->values()
            ->all();

        $this->selaraskanKapasitasTT();
    }

    /** Σ TT bangsal yang dihitung = TT total bawaan. */
    public function ttBangsalDihitung(): int
    {
        return (int) collect($this->parameterBangsal)->where('dihitung', true)->sum('tt');
    }

    /** TT total mengikuti parameter bangsal; penimpaan manual TT total gugur tiap parameter berubah. */
    protected function selaraskanKapasitasTT(): void
    {
        $this->defaultKapasitasTT = $this->ttBangsalDihitung();
        $this->kapasitasTT = $this->defaultKapasitasTT;
    }

    /** Hook Livewire: kotak TT bangsal diubah ("{indeks}.tt"). */
    public function updatedParameterBangsal($nilai, $kunci): void
    {
        foreach ($this->parameterBangsal as $indeks => $row) {
            $this->parameterBangsal[$indeks]['tt'] = max(0, (int) ($row['tt'] ?? 0));
            $this->parameterBangsal[$indeks]['dihitung'] = (bool) ($row['dihitung'] ?? true);
        }
        $this->selaraskanKapasitasTT();
    }

    public function toggleBangsalDihitung(int $indeks): void
    {
        if (!isset($this->parameterBangsal[$indeks])) {
            return;
        }
        $this->parameterBangsal[$indeks]['dihitung'] = !($this->parameterBangsal[$indeks]['dihitung'] ?? true);
        $this->selaraskanKapasitasTT();
    }

    /** Kembalikan semua parameter ke master: TT = jumlah bed, semua bangsal dihitung. */
    public function resetParameterBangsal(): void
    {
        $this->parameterBangsal = [];
        $this->muatParameterBangsal();
    }

    public function parameterBangsalDiubah(): bool
    {
        return collect($this->parameterBangsal)->contains(fn($row) => (int) $row['tt'] !== (int) $row['tt_db'] || !$row['dihitung']);
    }

    /**
     * Potongan SQL "kunjungan ini ikut hitungan BOR" menurut bangsal kamarnya.
     * bangsal_id DISISIPKAN sebagai literal (bukan binding): potongan ini dipakai di dalam
     * SELECT, dan binding di SELECT menggeser urutan binding WHERE. Nilainya berasal dari master
     * dan tetap disaring ketat di sini.
     */
    protected function kondisiBangsalDihitungSql(string $kolom = 'r.bangsal_id'): string
    {
        $dikeluarkan = collect($this->parameterBangsal)->where('dihitung', false)->pluck('bangsal_id')->map(fn($id) => (string) $id);
        if ($dikeluarkan->isEmpty()) {
            return '(1=1)';
        }

        $tanpaBangsalDikeluarkan = $dikeluarkan->contains('');
        $literal = $dikeluarkan
            ->filter(fn($id) => $id !== '' && preg_match('/^[A-Za-z0-9_.\-]+$/', $id))
            ->map(fn($id) => "'" . $id . "'")
            ->implode(',');

        $syarat = [];
        if ($literal !== '') {
            // NOT IN terhadap NULL = NULL (bukan benar) → kamar tanpa bangsal harus dijaga eksplisit.
            $syarat[] = $tanpaBangsalDikeluarkan ? "{$kolom} NOT IN ({$literal})" : "({$kolom} IS NULL OR {$kolom} NOT IN ({$literal}))";
        }
        if ($tanpaBangsalDikeluarkan) {
            $syarat[] = "{$kolom} IS NOT NULL";
        }

        return $syarat === [] ? '(1=1)' : '(' . implode(' AND ', $syarat) . ')';
    }

    /**
     * Aggregate query — group by ekspresi yang dikirim caller.
     */
    protected function buildKunjunganRIAggregate($start, $end, string $groupSql)
    {
        $bangsalDihitung = $this->kondisiBangsalDihitungSql();
        $dataWajar = $this->kondisiDataWajarSql();
        $dihitung = "({$bangsalDihitung} AND {$dataWajar})";

        return DB::table('rstxn_rihdrs as h')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->leftJoin('rsmst_rooms as r', 'r.room_id', '=', 'h.room_id')
            ->select([
                DB::raw("{$groupSql} as periode"),
                DB::raw("COUNT(DISTINCT h.rihdr_no) as total"),
                DB::raw("COUNT(DISTINCT h.reg_no) as pasien_unik"),
                DB::raw("SUM(CASE WHEN k.klaim_status='BPJS' OR h.klaim_id='JM' THEN 1 ELSE 0 END) as bpjs"),
                DB::raw("SUM(CASE WHEN (k.klaim_status IS NULL OR k.klaim_status<>'BPJS') AND h.klaim_id<>'JM' THEN 1 ELSE 0 END) as umum"),
                DB::raw("SUM(CASE WHEN h.ri_status='P' THEN 1 ELSE 0 END) as selesai"),
                // Punya exit_date tapi status bukan P (Pulang) — data rusak, bagian dari anomali_dikeluarkan
                DB::raw("SUM(CASE WHEN NVL(h.ri_status,'-')<>'P' THEN 1 ELSE 0 END) as status_lain"),
                // Komponen indikator — HANYA kunjungan di bangsal yang dihitung (lihat $parameterBangsal)
                // DAN lama dirawatnya bisa dipercaya. Oracle date subtraction returns days as NUMBER.
                DB::raw("COUNT(DISTINCT CASE WHEN {$dihitung} THEN h.rihdr_no END) as keluar_bor"),
                DB::raw("COUNT(DISTINCT CASE WHEN NOT {$bangsalDihitung} THEN h.rihdr_no END) as luar_bangsal"),
                DB::raw("COUNT(DISTINCT CASE WHEN {$bangsalDihitung} AND NOT {$dataWajar} THEN h.rihdr_no END) as anomali_dikeluarkan"),
                DB::raw("SUM(CASE WHEN {$dihitung} THEN NVL(h.exit_date - h.entry_date, 0) ELSE 0 END) as total_los"),
                DB::raw("SUM(CASE WHEN {$dataWajar} AND h.exit_date - h.entry_date < 1 THEN 1 ELSE 0 END) as los_kurang_1"),
            ])
            ->whereBetween('h.exit_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->whereNotNull('h.exit_date')
            ->whereRaw("NVL(h.ri_status,'-') <> 'F'")
            ->groupBy(DB::raw($groupSql))
            ->orderBy(DB::raw($groupSql))
            ->get()
            ->keyBy('periode');
    }

    protected function pasienUnikGlobalRI($start, $end): int
    {
        return DB::table('rstxn_rihdrs')
            ->whereBetween('exit_date', [$start, $end])
            ->whereNotNull('exit_date')
            ->where('klaim_id', '!=', 'KR')
            ->whereRaw("NVL(ri_status,'-') <> 'F'")
            ->distinct()
            ->count('reg_no');
    }

    /**
     * Breakdown per bangsal — semua bangsal, sorted desc by total.
     * Plus ALOS, total LOS, jumlah pasien meninggal (untuk GDR/NDR).
     *
     * Catatan: bangsal_id BUKAN kolom langsung di rstxn_rihdrs. Lookup via:
     *   rstxn_rihdrs.room_id → rsmst_rooms.bangsal_id → rsmst_bangsals.bangsal_name
     *
     * Deteksi "Meninggal" lewat JSON datadaftarri_json (path:
     *   perencanaan.tindakLanjut.tindakLanjutKode = '419099009' SNOMED-CT).
     * Pakai INSTR pattern karena Oracle DB di repo ini tidak support JSON_VALUE.
     */
    protected function bangsalBreakdownRI($start, $end)
    {
        $deathPattern = '"tindakLanjutKode":"419099009"';
        $dataWajar = $this->kondisiDataWajarSql();

        return DB::table('rstxn_rihdrs as h')
            ->leftJoin('rsmst_rooms as r', 'r.room_id', '=', 'h.room_id')
            ->leftJoin('rsmst_bangsals as b', 'b.bangsal_id', '=', 'r.bangsal_id')
            ->select([
                'r.bangsal_id',
                DB::raw('MAX(b.bangsal_name) as bangsal_name'),
                DB::raw('COUNT(DISTINCT h.rihdr_no) as total'),
                // Komponen indikator: hanya kunjungan yang lama dirawatnya bisa dipercaya
                DB::raw("COUNT(DISTINCT CASE WHEN {$dataWajar} THEN h.rihdr_no END) as keluar_dihitung"),
                DB::raw("COUNT(DISTINCT CASE WHEN NOT {$dataWajar} THEN h.rihdr_no END) as anomali_dikeluarkan"),
                DB::raw("SUM(CASE WHEN {$dataWajar} THEN h.exit_date - h.entry_date ELSE 0 END) as total_los"),
                DB::raw("SUM(CASE WHEN {$dataWajar} AND h.exit_date - h.entry_date < 1 THEN 1 ELSE 0 END) as los_kurang_1"),
                DB::raw("SUM(CASE WHEN INSTR(h.datadaftarri_json, '{$deathPattern}') > 0 THEN 1 ELSE 0 END) as meninggal"),
                DB::raw("SUM(CASE WHEN INSTR(h.datadaftarri_json, '{$deathPattern}') > 0 AND NVL(h.exit_date - h.entry_date, 0) >= 2 THEN 1 ELSE 0 END) as meninggal48"),
            ])
            ->whereBetween('h.exit_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->whereNotNull('h.exit_date')
            ->whereRaw("NVL(h.ri_status,'-') <> 'F'")
            ->groupBy('r.bangsal_id')
            ->orderByDesc('total')
            ->get();
    }

    /**
     * Tambahkan indikator BOR/BTO/TOI/GDR/NDR ke tiap row breakdown bangsal.
     *
     * Formula:
     *   BOR = Σ LOS / (TT × hari periode) × 100%
     *   BTO = Σ pulang / TT
     *   TOI = (TT × hari − Σ LOS) / Σ pulang
     *   GDR = meninggal / Σ pulang × 1000 (per mille)
     *   NDR = meninggal_LOS≥48h / Σ pulang × 1000
     *
     * Bangsal tanpa data TT (bangsal_id NULL atau tidak ada bed) → bor/bto/toi
     * di-set null (akan ditampilkan "—" oleh view).
     *
     * @param  iterable $rows       Hasil bangsalBreakdownRI() — Collection of stdClass
     * @param  int      $totalDays  Total hari di periode laporan
     * @return array<int,array>     Array of array yang siap dirender
     */
    protected function enrichBangsalIndicators($rows, int $totalDays): array
    {
        $parameter = collect($this->parameterBangsal)->keyBy(fn($row) => (string) $row['bangsal_id']);
        $out = [];

        foreach ($rows as $row) {
            $total = (int) ($row->total ?? 0);
            $keluarDihitung = (int) ($row->keluar_dihitung ?? 0);
            $totalLos = (float) ($row->total_los ?? 0);
            $meninggal = (int) ($row->meninggal ?? 0);
            $meninggal48 = (int) ($row->meninggal48 ?? 0);
            $parameterRow = $parameter->get((string) ($row->bangsal_id ?? ''));
            $dihitung = (bool) ($parameterRow['dihitung'] ?? true);
            // Bangsal yang dikeluarkan dari BOR: TT dianggap 0 → BOR/BTO/TOI tampil "—".
            $tt = $dihitung ? (int) ($parameterRow['tt'] ?? 0) : 0;

            $bor = ($tt > 0 && $totalDays > 0)
                ? round($totalLos / ($tt * $totalDays) * 100, 1)
                : null;
            $bto = $tt > 0 ? round($keluarDihitung / $tt, 2) : null;
            $toi = ($tt > 0 && $totalDays > 0 && $keluarDihitung > 0)
                ? round((($tt * $totalDays) - $totalLos) / $keluarDihitung, 1)
                : null;
            $gdr = $total > 0 ? round($meninggal / $total * 1000, 1) : 0.0;
            $ndr = $total > 0 ? round($meninggal48 / $total * 1000, 1) : 0.0;

            $out[] = [
                'bangsal_id'   => $row->bangsal_id,
                'bangsal_name' => $row->bangsal_name,
                'total'        => $total,
                // keluar_dihitung = total dikurangi data janggal; penyebut BTO/TOI/ALOS (NDR/GDR tetap memakai total)
                'keluar_dihitung' => $keluarDihitung,
                'anomali_dikeluarkan' => (int) ($row->anomali_dikeluarkan ?? 0),
                'alos'         => $keluarDihitung > 0 ? round($totalLos / $keluarDihitung, 1) : 0.0,
                'total_los'    => round($totalLos, 1),
                'los_kurang_1' => (int) ($row->los_kurang_1 ?? 0),
                'tt'           => $tt,
                'tt_db'        => (int) ($parameterRow['tt_db'] ?? 0),
                'dihitung'     => $dihitung,
                'hari_tersedia' => $tt * $totalDays,
                'meninggal'    => $meninggal,
                'meninggal48'  => $meninggal48,
                'bor'          => $bor,
                'bto'          => $bto,
                'toi'          => $toi,
                'gdr'          => $gdr,
                'ndr'          => $ndr,
            ];
        }

        return $out;
    }

    protected function fillKunjunganRow(?object $r, string $label, string $short): array
    {
        $total = (int) ($r->total ?? 0);
        $keluarBor = (int) ($r->keluar_bor ?? 0);
        $totalLos = (float) ($r->total_los ?? 0);

        return [
            'periode_label' => $label,
            'periode_short' => $short,
            'total'         => $total,
            'pasien_unik'   => (int) ($r->pasien_unik ?? 0),
            'bpjs'          => (int) ($r->bpjs ?? 0),
            'umum'          => (int) ($r->umum ?? 0),
            'selesai'       => (int) ($r->selesai ?? 0),
            'status_lain'   => (int) ($r->status_lain ?? 0),
            'los_kurang_1'  => (int) ($r->los_kurang_1 ?? 0),
            // keluar_bor & total_los = hanya bangsal yang dihitung; sama dengan total bila tak ada yang dikeluarkan
            'keluar_bor'    => $keluarBor,
            'luar_bangsal'  => (int) ($r->luar_bangsal ?? 0),
            'anomali_dikeluarkan' => (int) ($r->anomali_dikeluarkan ?? 0),
            'total_los'     => round($totalLos, 1),
            // ALOS per periode = total_los / keluar_bor (weighted)
            'alos'          => $keluarBor > 0 ? round($totalLos / $keluarBor, 1) : 0.0,
        ];
    }

    protected function totalsKunjungan(array $rows): array
    {
        $sum = fn(string $k) => array_sum(array_column($rows, $k));
        $totalCount = $sum('total');
        $keluarBor = $sum('keluar_bor');
        $totalLos = $sum('total_los');

        return [
            'total'        => $totalCount,
            'pasien_unik'  => $sum('pasien_unik'),
            'bpjs'         => $sum('bpjs'),
            'umum'         => $sum('umum'),
            'selesai'      => $sum('selesai'),
            'status_lain'  => $sum('status_lain'),
            'los_kurang_1' => $sum('los_kurang_1'),
            'keluar_bor'   => $keluarBor,
            'luar_bangsal' => $sum('luar_bangsal'),
            'anomali_dikeluarkan' => $sum('anomali_dikeluarkan'),
            'total_los'    => round($totalLos, 1),
            // ALOS global = weighted (total_los / keluar_bor)
            'alos'         => $keluarBor > 0 ? round($totalLos / $keluarBor, 1) : 0.0,
        ];
    }

    protected function chartDataKunjungan(array $rows): array
    {
        return [
            'labels'    => array_column($rows, 'periode_label'),
            'bpjs'      => array_column($rows, 'bpjs'),
            'umum'      => array_column($rows, 'umum'),
            'total'     => array_column($rows, 'total'),
            'alos'      => array_column($rows, 'alos'),
            'selesai'   => array_column($rows, 'selesai'),
        ];
    }

    protected function bulanLabel(int $m): string
    {
        return [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ][$m] ?? (string) $m;
    }

    /**
     * Enrich rows dengan BOR / BTO / TOI per periode.
     *
     * Formula (Kemenkes / standar Indonesia):
     *   BOR = (Σ hari rawat / (TT × hari periode)) × 100%
     *   BTO = jumlah pasien pulang / TT (rate, kali)
     *   TOI = ((TT × hari periode) − Σ hari rawat) / jumlah pasien pulang (hari)
     *
     * @param  array  $rows           Output dari fillKunjunganRow (sudah punya total_los & total)
     * @param  int    $tt             Kapasitas tempat tidur
     * @param  callable $daysGetter   Function(row) => jumlah hari di periode row tsb
     */
    protected function enrichWithBORTOIBTO(array $rows, int $tt, callable $daysGetter): array
    {
        foreach ($rows as &$r) {
            $days = (int) $daysGetter($r);
            $totalLos = (float) $r['total_los'];
            $total = (int) $r['keluar_bor'];

            $r['days_in_period'] = $days;
            $r['hari_tersedia'] = $tt * $days;

            if ($tt > 0 && $days > 0) {
                $r['bor'] = round($totalLos / ($tt * $days) * 100, 1);
                $r['bto'] = round($total / $tt, 2);
                $r['toi'] = $total > 0
                    ? round((($tt * $days) - $totalLos) / $total, 1)
                    : null;
            } else {
                // Periode belum berjalan (hari = 0) atau TT kosong → tidak ada yang bisa dihitung.
                $r['bor'] = null;
                $r['bto'] = null;
                $r['toi'] = null;
            }
        }
        unset($r);
        return $rows;
    }

    /**
     * Hitung BOR/BTO/TOI agregat dari array rows (sudah enrichWithBORTOIBTO).
     */
    protected function totalBORTOIBTO(array $rows, int $tt): array
    {
        $totalLos = array_sum(array_column($rows, 'total_los'));
        $totalPasien = array_sum(array_column($rows, 'keluar_bor'));
        $totalDays = array_sum(array_column($rows, 'days_in_period'));

        $bor = ($tt > 0 && $totalDays > 0) ? round($totalLos / ($tt * $totalDays) * 100, 1) : null;
        $bto = ($tt > 0 && $totalDays > 0) ? round($totalPasien / $tt, 2) : null;
        $toi = ($totalPasien > 0 && $tt > 0 && $totalDays > 0)
            ? round((($tt * $totalDays) - $totalLos) / $totalPasien, 1)
            : null;

        return ['bor' => $bor, 'bto' => $bto, 'toi' => $toi, 'days_total' => $totalDays, 'hari_tersedia' => $tt * $totalDays];
    }

    /**
     * Jumlah hari periode yang SUDAH BERJALAN sampai hari ini (inklusif).
     *   - periode lampau  → panjang kalender penuh
     *   - periode berjalan → dari awal periode s/d hari ini
     *   - periode mendatang → 0 (indikator ditampilkan "—")
     * Tanpa ini BOR/TOI tahun berjalan dibagi 365 hari padahal baru sebagian yang lewat.
     */
    protected function hariPeriodeBerjalan(Carbon $start, Carbon $end): int
    {
        $hariIni = Carbon::now(config('app.timezone'))->endOfDay();
        $awal = $start->copy()->startOfDay();
        if ($awal->greaterThan($hariIni)) {
            return 0;
        }
        $akhir = $end->copy()->endOfDay()->min($hariIni);

        return (int) $awal->diffInDays($akhir->copy()->startOfDay()) + 1;
    }

    /**
     * Hitungan data janggal dalam periode — supaya angka indikator yang aneh bisa dilacak
     * ke sumbernya. Satu baris agregat; TIDAK mengubah hitungan indikator.
     */
    protected function anomaliDataRI($start, $end): array
    {
        $batas = $this->batasLos();

        $row = DB::table('rstxn_rihdrs as h')
            ->leftJoin('rsmst_rooms as r', 'r.room_id', '=', 'h.room_id')
            ->select([
                DB::raw("SUM(CASE WHEN NVL(h.ri_status,'-')='F' THEN 1 ELSE 0 END) as batal_berexit"),
                DB::raw("SUM(CASE WHEN NVL(h.ri_status,'-') NOT IN ('P','F') THEN 1 ELSE 0 END) as status_lain"),
                DB::raw("SUM(CASE WHEN NVL(h.ri_status,'-')<>'F' AND h.entry_date IS NULL THEN 1 ELSE 0 END) as tanpa_tgl_masuk"),
                DB::raw("SUM(CASE WHEN NVL(h.ri_status,'-')<>'F' AND h.exit_date < h.entry_date THEN 1 ELSE 0 END) as los_negatif"),
                DB::raw("SUM(CASE WHEN NVL(h.ri_status,'-')<>'F' AND h.exit_date >= h.entry_date AND h.exit_date - h.entry_date < 1 THEN 1 ELSE 0 END) as los_kurang_1"),
                DB::raw("SUM(CASE WHEN NVL(h.ri_status,'-')<>'F' AND h.exit_date - h.entry_date > 30 AND h.exit_date - h.entry_date <= {$batas} THEN 1 ELSE 0 END) as los_lebih_30"),
                DB::raw("SUM(CASE WHEN NVL(h.ri_status,'-')<>'F' AND h.exit_date - h.entry_date > {$batas} THEN 1 ELSE 0 END) as los_lebih_batas"),
                DB::raw("SUM(CASE WHEN NVL(h.ri_status,'-')<>'F' AND r.bangsal_id IS NULL THEN 1 ELSE 0 END) as tanpa_bangsal"),
            ])
            ->whereBetween('h.exit_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->first();

        // Masih dirawat = belum punya exit_date → belum masuk hitungan mana pun di laporan ini.
        $dirawat = DB::table('rstxn_rihdrs')
            ->select([
                DB::raw('COUNT(*) as jumlah'),
                DB::raw('SUM(NVL(SYSDATE - entry_date, 0)) as hari_rawat'),
            ])
            ->whereNull('exit_date')
            ->whereRaw("NVL(ri_status,'I') = 'I'")
            ->where('klaim_id', '!=', 'KR')
            ->first();

        return [
            'batal_berexit'   => (int) ($row->batal_berexit ?? 0),
            'status_lain'     => (int) ($row->status_lain ?? 0),
            'tanpa_tgl_masuk' => (int) ($row->tanpa_tgl_masuk ?? 0),
            'los_negatif'     => (int) ($row->los_negatif ?? 0),
            'los_kurang_1'    => (int) ($row->los_kurang_1 ?? 0),
            'los_lebih_30'    => (int) ($row->los_lebih_30 ?? 0),
            'los_lebih_batas' => (int) ($row->los_lebih_batas ?? 0),
            'batas_los'       => $batas,
            'tanpa_bangsal'   => (int) ($row->tanpa_bangsal ?? 0),
            'masih_dirawat'   => (int) ($dirawat->jumlah ?? 0),
            'masih_dirawat_hari' => round((float) ($dirawat->hari_rawat ?? 0), 1),
        ];
    }
}
