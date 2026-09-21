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
 *   - Hari rawat dihitung dari SEGMEN KAMAR (rsmst_trfrooms, lihat segmenKamarQuery): tiap hari
 *     jatuh ke bangsal tempat pasien benar-benar dirawat, bukan seluruhnya ke kamar terakhir.
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
     * SEGMEN KAMAR — dasar hari rawat semua indikator.
     *
     * Satu baris per segmen kamar (rsmst_trfrooms: start_date → end_date) milik kunjungan yang
     * PULANG dalam periode. Pindah Kamar menutup segmen lama & membuka yang baru; Kasir menutup
     * segmen terakhir dengan tanggal pulang. Segmen yang masih terbuka ditutup dengan exit_date.
     * Kunjungan tanpa riwayat kamar sama sekali (data lama) diwakili SATU segmen: kamar di header,
     * entry_date → exit_date — supaya tidak lenyap dari hitungan.
     *
     * Dengan ini hari rawat jatuh ke bangsal tempat pasien BENAR-BENAR dirawat, bukan seluruhnya
     * ke bangsal kamar terakhir (dulu bangsal transit seperti ICU tampak kosong).
     * Populasinya tidak berubah: hanya kunjungan ber-exit_date dalam periode; syarat status P &
     * lama dirawat wajar tetap dikenakan pemanggil lewat kondisiDataWajarSql('s').
     */
    protected function segmenKamarQuery($start, $end)
    {
        $saring = fn($query) => $query
            ->whereBetween('h.exit_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->whereRaw("NVL(h.ri_status,'-') <> 'F'");

        $kolomHeader = 'h.rihdr_no, h.entry_date, h.exit_date, h.ri_status';

        $denganRiwayat = $saring(
            DB::table('rstxn_rihdrs as h')
                ->join('rsmst_trfrooms as t', 't.rihdr_no', '=', 'h.rihdr_no')
                ->selectRaw("{$kolomHeader}, t.room_id as seg_room_id, t.start_date as seg_mulai, NVL(t.end_date, h.exit_date) as seg_selesai, 1 as ada_riwayat")
        );

        $tanpaRiwayat = $saring(
            DB::table('rstxn_rihdrs as h')
                ->whereNotExists(fn($query) => $query->selectRaw('1')->from('rsmst_trfrooms as t')->whereColumn('t.rihdr_no', 'h.rihdr_no'))
                ->selectRaw("{$kolomHeader}, h.room_id as seg_room_id, h.entry_date as seg_mulai, h.exit_date as seg_selesai, 0 as ada_riwayat")
        );

        return $denganRiwayat->unionAll($tanpaRiwayat);
    }

    /** Segmen yang tanggalnya sah: punya tanggal mulai dan tidak berakhir sebelum mulai. */
    protected function kondisiSegmenSahSql(): string
    {
        return '(s.seg_mulai IS NOT NULL AND s.seg_selesai >= s.seg_mulai)';
    }

    /**
     * Aggregate per periode — group by ekspresi yang dikirim caller (memakai alias h).
     * Dua query digabung per periode:
     *   (1) header  : jumlah kunjungan & penggolongan data janggal (satu baris per kunjungan);
     *   (2) segmen  : hari rawat & pasien yang memakai bangsal yang dihitung.
     * Dipisah karena menggabung segmen ke query (1) akan menggandakan hitungan BPJS/UMUM.
     */
    protected function buildKunjunganRIAggregate($start, $end, string $groupSql)
    {
        $dataWajar = $this->kondisiDataWajarSql();

        $header = DB::table('rstxn_rihdrs as h')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->select([
                DB::raw("{$groupSql} as periode"),
                DB::raw("COUNT(DISTINCT h.rihdr_no) as total"),
                DB::raw("COUNT(DISTINCT h.reg_no) as pasien_unik"),
                DB::raw("SUM(CASE WHEN k.klaim_status='BPJS' OR h.klaim_id='JM' THEN 1 ELSE 0 END) as bpjs"),
                DB::raw("SUM(CASE WHEN (k.klaim_status IS NULL OR k.klaim_status<>'BPJS') AND h.klaim_id<>'JM' THEN 1 ELSE 0 END) as umum"),
                DB::raw("SUM(CASE WHEN h.ri_status='P' THEN 1 ELSE 0 END) as selesai"),
                // Punya exit_date tapi status bukan P (Pulang) — data rusak, bagian dari anomali_dikeluarkan
                DB::raw("SUM(CASE WHEN NVL(h.ri_status,'-')<>'P' THEN 1 ELSE 0 END) as status_lain"),
                // Kunjungan yang tanggal pulang & lama dirawatnya bisa dipercaya
                DB::raw("SUM(CASE WHEN {$dataWajar} THEN 1 ELSE 0 END) as wajar"),
                // Σ lama dirawat versi HEADER (exit − entry) — hanya pembanding uji silang terhadap Σ segmen
                DB::raw("SUM(CASE WHEN {$dataWajar} THEN h.exit_date - h.entry_date ELSE 0 END) as los_header"),
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

        $groupSegmen = str_replace('h.exit_date', 's.exit_date', $groupSql);
        $wajarSegmen = $this->kondisiDataWajarSql('s') . ' AND ' . $this->kondisiSegmenSahSql();
        $dihitung = "({$wajarSegmen} AND " . $this->kondisiBangsalDihitungSql() . ')';

        $segmen = DB::query()
            ->fromSub($this->segmenKamarQuery($start, $end), 's')
            ->leftJoin('rsmst_rooms as r', 'r.room_id', '=', 's.seg_room_id')
            ->select([
                DB::raw("{$groupSegmen} as periode"),
                // Pasien yang memakai bed di bangsal yang dihitung — penyebut ALOS / BTO / TOI
                DB::raw("COUNT(DISTINCT CASE WHEN {$dihitung} THEN s.rihdr_no END) as keluar_bor"),
                // Hari rawat di bangsal yang dihitung — pembilang BOR
                DB::raw("SUM(CASE WHEN {$dihitung} THEN s.seg_selesai - s.seg_mulai ELSE 0 END) as total_los"),
                // Hari rawat SEMUA bangsal — pembanding uji silang terhadap los_header
                DB::raw("SUM(CASE WHEN {$wajarSegmen} THEN s.seg_selesai - s.seg_mulai ELSE 0 END) as los_segmen"),
            ])
            ->groupBy(DB::raw($groupSegmen))
            ->get()
            ->keyBy('periode');

        return $header->map(function ($row) use ($segmen) {
            $rowSegmen = $segmen->get($row->periode);
            $wajar = (int) ($row->wajar ?? 0);
            $row->keluar_bor = (int) ($rowSegmen->keluar_bor ?? 0);
            $row->total_los = (float) ($rowSegmen->total_los ?? 0);
            $row->los_segmen = (float) ($rowSegmen->los_segmen ?? 0);
            // Tiap kunjungan jatuh tepat di satu kelompok: data janggal → luar bangsal → dihitung.
            $row->anomali_dikeluarkan = (int) $row->total - $wajar;
            $row->luar_bangsal = max(0, $wajar - $row->keluar_bor);

            return $row;
        });
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
     * Breakdown per bangsal — dua query digabung per bangsal_id:
     *   (1) header, menurut bangsal kamar TERAKHIR: pasien pulang dari bangsal itu, data janggal,
     *       dan kematian (NDR/GDR) — kematian dicatat di tempat pasien terakhir dirawat;
     *   (2) segmen kamar, menurut bangsal TEMPAT DIRAWAT: hari rawat & pasien yang dilayani
     *       (pulang + dipindahkan, sama dengan "pasien keluar ruangan" pada sensus harian).
     *
     * Deteksi "Meninggal" lewat JSON datadaftarri_json (path:
     *   perencanaan.tindakLanjut.tindakLanjutKode = '419099009' SNOMED-CT).
     * Pakai INSTR pattern karena Oracle DB di repo ini tidak support JSON_VALUE.
     */
    protected function bangsalBreakdownRI($start, $end)
    {
        $deathPattern = '"tindakLanjutKode":"419099009"';
        $dataWajar = $this->kondisiDataWajarSql();

        $header = DB::table('rstxn_rihdrs as h')
            ->leftJoin('rsmst_rooms as r', 'r.room_id', '=', 'h.room_id')
            ->leftJoin('rsmst_bangsals as b', 'b.bangsal_id', '=', 'r.bangsal_id')
            ->select([
                'r.bangsal_id',
                DB::raw('MAX(b.bangsal_name) as bangsal_name'),
                DB::raw('COUNT(DISTINCT h.rihdr_no) as total'),
                DB::raw("COUNT(DISTINCT CASE WHEN NOT {$dataWajar} THEN h.rihdr_no END) as anomali_dikeluarkan"),
                DB::raw("SUM(CASE WHEN {$dataWajar} AND h.exit_date - h.entry_date < 1 THEN 1 ELSE 0 END) as los_kurang_1"),
                DB::raw("SUM(CASE WHEN INSTR(h.datadaftarri_json, '{$deathPattern}') > 0 THEN 1 ELSE 0 END) as meninggal"),
                DB::raw("SUM(CASE WHEN INSTR(h.datadaftarri_json, '{$deathPattern}') > 0 AND NVL(h.exit_date - h.entry_date, 0) >= 2 THEN 1 ELSE 0 END) as meninggal48"),
            ])
            ->whereBetween('h.exit_date', [$start, $end])
            ->where('h.klaim_id', '!=', 'KR')
            ->whereNotNull('h.exit_date')
            ->whereRaw("NVL(h.ri_status,'-') <> 'F'")
            ->groupBy('r.bangsal_id')
            ->get()
            ->keyBy(fn($row) => (string) ($row->bangsal_id ?? ''));

        $wajarSegmen = $this->kondisiDataWajarSql('s') . ' AND ' . $this->kondisiSegmenSahSql();

        $segmen = DB::query()
            ->fromSub($this->segmenKamarQuery($start, $end), 's')
            ->leftJoin('rsmst_rooms as r', 'r.room_id', '=', 's.seg_room_id')
            ->leftJoin('rsmst_bangsals as b', 'b.bangsal_id', '=', 'r.bangsal_id')
            ->select([
                'r.bangsal_id',
                DB::raw('MAX(b.bangsal_name) as bangsal_name'),
                DB::raw("COUNT(DISTINCT CASE WHEN {$wajarSegmen} THEN s.rihdr_no END) as pasien_bangsal"),
                DB::raw("SUM(CASE WHEN {$wajarSegmen} THEN s.seg_selesai - s.seg_mulai ELSE 0 END) as total_los"),
            ])
            ->groupBy('r.bangsal_id')
            ->get()
            ->keyBy(fn($row) => (string) ($row->bangsal_id ?? ''));

        // Gabungan kunci: bangsal transit bisa hanya muncul di segmen (tak ada yang pulang dari sana).
        return $header->keys()->merge($segmen->keys())->unique()->values()
            ->map(function (string $bangsalId) use ($header, $segmen) {
                $rowHeader = $header->get($bangsalId);
                $rowSegmen = $segmen->get($bangsalId);

                return (object) [
                    'bangsal_id'          => $bangsalId === '' ? null : $bangsalId,
                    'bangsal_name'        => $rowHeader->bangsal_name ?? ($rowSegmen->bangsal_name ?? null),
                    'total'               => (int) ($rowHeader->total ?? 0),
                    'anomali_dikeluarkan' => (int) ($rowHeader->anomali_dikeluarkan ?? 0),
                    'los_kurang_1'        => (int) ($rowHeader->los_kurang_1 ?? 0),
                    'meninggal'           => (int) ($rowHeader->meninggal ?? 0),
                    'meninggal48'         => (int) ($rowHeader->meninggal48 ?? 0),
                    'pasien_bangsal'      => (int) ($rowSegmen->pasien_bangsal ?? 0),
                    'total_los'           => (float) ($rowSegmen->total_los ?? 0),
                ];
            })
            ->sortByDesc('total_los')
            ->values();
    }

    /**
     * Tambahkan indikator BOR/BTO/TOI/GDR/NDR ke tiap row breakdown bangsal.
     *
     * Formula (hari rawat & pasien dilayani dari SEGMEN KAMAR):
     *   BOR  = Σ hari rawat di bangsal / (TT × hari periode) × 100%
     *   ALOS = Σ hari rawat di bangsal / pasien dilayani bangsal
     *   BTO  = pasien dilayani bangsal / TT
     *   TOI  = (TT × hari − Σ hari rawat) / pasien dilayani bangsal
     *   GDR  = meninggal / pasien pulang dari bangsal × 1000 (per mille)
     *   NDR  = meninggal ≥ 48 jam / pasien pulang dari bangsal × 1000
     *
     * Bangsal tanpa TT atau yang dikeluarkan dari BOR → bor/bto/toi null ("—" di view).
     *
     * @param  iterable $rows       Hasil bangsalBreakdownRI()
     * @param  int      $totalDays  Hari periode yang sudah berjalan
     * @return array<int,array>     Array of array yang siap dirender
     */
    protected function enrichBangsalIndicators($rows, int $totalDays): array
    {
        $parameter = collect($this->parameterBangsal)->keyBy(fn($row) => (string) $row['bangsal_id']);
        $out = [];

        foreach ($rows as $row) {
            $total = (int) ($row->total ?? 0);
            $pasienBangsal = (int) ($row->pasien_bangsal ?? 0);
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
            $bto = $tt > 0 ? round($pasienBangsal / $tt, 2) : null;
            $toi = ($tt > 0 && $totalDays > 0 && $pasienBangsal > 0)
                ? round((($tt * $totalDays) - $totalLos) / $pasienBangsal, 1)
                : null;
            $gdr = $total > 0 ? round($meninggal / $total * 1000, 1) : 0.0;
            $ndr = $total > 0 ? round($meninggal48 / $total * 1000, 1) : 0.0;

            $out[] = [
                'bangsal_id'   => $row->bangsal_id,
                'bangsal_name' => $row->bangsal_name,
                // total = pasien PULANG dari bangsal ini (kamar terakhir) — penyebut NDR/GDR
                'total'        => $total,
                // pasien_bangsal = pasien yang DIRAWAT di bangsal ini (pulang + dipindahkan) — penyebut ALOS/BTO/TOI
                'pasien_bangsal' => $pasienBangsal,
                'anomali_dikeluarkan' => (int) ($row->anomali_dikeluarkan ?? 0),
                'alos'         => $pasienBangsal > 0 ? round($totalLos / $pasienBangsal, 1) : 0.0,
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
            'los_header'    => round((float) ($r->los_header ?? 0), 1),
            'los_segmen'    => round((float) ($r->los_segmen ?? 0), 1),
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
            'los_header'   => round($sum('los_header'), 1),
            'los_segmen'   => round($sum('los_segmen'), 1),
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

        // Mutu riwayat kamar: segmen bertanggal tak sah tidak menyumbang hari rawat; kunjungan tanpa
        // riwayat kamar diwakili satu segmen dari header (lihat segmenKamarQuery).
        $segmenSah = $this->kondisiSegmenSahSql();
        $riwayat = DB::query()
            ->fromSub($this->segmenKamarQuery($start, $end), 's')
            ->select([
                DB::raw("SUM(CASE WHEN s.ada_riwayat = 1 AND NOT {$segmenSah} THEN 1 ELSE 0 END) as segmen_janggal"),
                DB::raw('COUNT(DISTINCT CASE WHEN s.ada_riwayat = 0 THEN s.rihdr_no END) as tanpa_riwayat_kamar'),
                DB::raw('SUM(CASE WHEN s.ada_riwayat = 1 THEN 1 ELSE 0 END) as jumlah_segmen'),
            ])
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
            'segmen_janggal'  => (int) ($riwayat->segmen_janggal ?? 0),
            'tanpa_riwayat_kamar' => (int) ($riwayat->tanpa_riwayat_kamar ?? 0),
            'jumlah_segmen'   => (int) ($riwayat->jumlah_segmen ?? 0),
            'masih_dirawat'   => (int) ($dirawat->jumlah ?? 0),
            'masih_dirawat_hari' => round((float) ($dirawat->hari_rawat ?? 0), 1),
        ];
    }
}
