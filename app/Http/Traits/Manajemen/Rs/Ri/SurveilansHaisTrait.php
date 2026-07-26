<?php

namespace App\Http\Traits\Manajemen\Rs\Ri;

use App\Support\OracleLob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rekap Surveilans HAIs (Healthcare-Associated Infections) Rawat Inap.
 *
 * Sumber data = entri modul dokumen Surveilans HAIs di `datadaftarri_json`
 * (key surveilansPlebitisRI / surveilansIskRI / surveilansVapRI / surveilansIloRI).
 *
 * Rumus insiden rate mengikuti Pedoman Surveilans PPI Kemenkes 2011 / materi
 * IPCN (HIPPII) — numerator = jumlah kasus, denominator = jumlah hari pemakaian alat:
 *
 *   IAD      = jumlah IAD      / jumlah hari pemasangan CVL (central/umbilikal) x 1000
 *   Plebitis = jumlah plebitis / jumlah hari pemasangan IV line perifer         x 1000
 *   ISK      = jumlah ISK      / jumlah hari pemakaian kateter urine            x 1000
 *   VAP      = jumlah VAP      / jumlah hari pemakaian ventilator               x 1000
 *   ILO      = jumlah ILO      / jumlah operasi                                 x 100
 *              (ILO memakai basis 100 operasi — konvensi IDO, tak ada di materi IPCN)
 *
 * CATATAN PENTING — penentuan kasus:
 * Formulir surveilans TIDAK punya kolom "kasus HAIs: ya/tidak" (penetapan kasus
 * secara resmi = gejala klinis + penunjang + diagnosis DPJP). Di sini kasus
 * DITURUNKAN dari tanda yang dicentang IPCLN pada formulir, memakai aturan di
 * konstanta di bawah. Aturan ini sengaja dibuat eksplisit & ditampilkan di
 * halaman laporan supaya IPCN bisa memverifikasi / meminta penyesuaian.
 */
trait SurveilansHaisTrait
{
    /** Tanda lokal pada area insersi → dipakai menetapkan kasus PLEBITIS. */
    private const TANDA_PLEBITIS = ['nyeri', 'merah', 'kalor', 'pus', 'bengkak'];

    /** Tanda sistemik → dipakai menetapkan kasus IAD (bersama kultur darah). */
    private const TANDA_IAD = ['suhuGt38', 'suhuLt37', 'menggigil', 'sistolikLt90', 'apnu', 'nadiGt100'];

    /** Tanda ILO pada pemantauan luka operasi hari ke-1 s/d 17. */
    private const TANDA_ILO = ['pus', 'drainase', 'perforasi', 'fistula'];

    /** Format tanggal baku repo pada JSON EMR. */
    private const FORMAT_TANGGAL = 'd/m/Y H:i:s';

    /**
     * Rekap satu tahun penuh: 12 baris bulan + total + daftar kasus (untuk audit).
     *
     * @return array{bulan: array, total: array, kasus: array, jumlahRecord: int}
     */
    protected function rekapSurveilansHais(int $tahun): array
    {
        $awalTahun = Carbon::create($tahun, 1, 1, 0, 0, 0, config('app.timezone'))->startOfDay();
        $akhirTahun = Carbon::create($tahun, 12, 31, 0, 0, 0, config('app.timezone'))->endOfDay();

        $hitunganBulan = [];
        for ($bulanKe = 1; $bulanKe <= 12; $bulanKe++) {
            $hitunganBulan[$bulanKe] = [
                'plebitisKasus' => 0, 'ivlHari' => 0,
                'iadKasus' => 0, 'clHari' => 0,
                'iskKasus' => 0, 'ucHari' => 0,
                'vapKasus' => 0, 'ventHari' => 0,
                'iloKasus' => 0, 'operasi' => 0,
            ];
        }

        $kasusList = [];
        $recordList = $this->ambilRecordSurveilans($awalTahun, $akhirTahun);

        foreach ($recordList as $record) {
            $json = OracleLob::read($record->datadaftarri_json ?? null, 'rstxn_rihdrs', 'rihdr_no', $record->rihdr_no, 'datadaftarri_json');
            if ($json === '') {
                continue;
            }

            try {
                $dataDaftarRi = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                continue;
            }
            if (!is_array($dataDaftarRi)) {
                continue;
            }

            $pasien = [
                'rihdrNo' => $record->rihdr_no,
                'regNo' => $record->reg_no,
                'regName' => $record->reg_name,
                'ruang' => $record->room_name,
            ];

            $this->kumpulkanPlebitis($dataDaftarRi['surveilansPlebitisRI'] ?? [], $tahun, $pasien, $hitunganBulan, $kasusList);
            $this->kumpulkanIsk($dataDaftarRi['surveilansIskRI'] ?? [], $tahun, $pasien, $hitunganBulan, $kasusList);
            $this->kumpulkanVap($dataDaftarRi['surveilansVapRI'] ?? [], $tahun, $pasien, $hitunganBulan, $kasusList);
            $this->kumpulkanIlo($dataDaftarRi['surveilansIloRI'] ?? [], $tahun, $pasien, $hitunganBulan, $kasusList);
        }

        // Susun baris bulan + rate-nya
        $barisBulanList = [];
        $total = array_fill_keys(array_keys($hitunganBulan[1]), 0);
        for ($bulanKe = 1; $bulanKe <= 12; $bulanKe++) {
            foreach ($hitunganBulan[$bulanKe] as $field => $nilai) {
                $total[$field] += $nilai;
            }
            $barisBulanList[] = array_merge($hitunganBulan[$bulanKe], [
                'bulan' => $bulanKe,
                'label' => Carbon::create($tahun, $bulanKe, 1)->translatedFormat('F'),
            ], $this->hitungSemuaRate($hitunganBulan[$bulanKe]));
        }

        $total = array_merge($total, $this->hitungSemuaRate($total));

        usort($kasusList, fn($kiri, $kanan) => [$kiri['bulan'], $kiri['jenis']] <=> [$kanan['bulan'], $kanan['jenis']]);

        return [
            'bulan' => $barisBulanList,
            'total' => $total,
            'kasus' => $kasusList,
            'jumlahRecord' => count($recordList),
        ];
    }

    /**
     * Record RI yang JSON-nya memuat modul surveilans & episodenya bersinggungan
     * dengan tahun laporan. INSTR dipakai karena Oracle di sini tak mendukung
     * JSON_VALUE (lihat skill oracle-quirks §2).
     */
    private function ambilRecordSurveilans(Carbon $awalTahun, Carbon $akhirTahun): array
    {
        return DB::table('rstxn_rihdrs')
            ->select(['rihdr_no', 'reg_no', 'reg_name', 'room_name', 'datadaftarri_json'])
            ->whereRaw("INSTR(datadaftarri_json, 'surveilans') > 0")
            ->where('entry_date', '<=', $akhirTahun)
            ->where(function ($subQuery) use ($awalTahun) {
                $subQuery->whereNull('exit_date')->orWhere('exit_date', '>=', $awalTahun);
            })
            ->orderBy('rihdr_no')
            ->get()
            ->all();
    }

    /* ═══════════════ PENGUMPULAN PER MODUL ═══════════════ */

    private function kumpulkanPlebitis(array $entriList, int $tahun, array $pasien, array &$hitunganBulan, array &$kasusList): void
    {
        foreach ($entriList as $entri) {
            if (!is_array($entri)) {
                continue;
            }

            // Kateter sentral/umbilikal → hari CVL (basis IAD); selain itu → hari IV line perifer.
            $kateterSentral = ($entri['kateterVCentral'] ?? '') === 'Ya' || ($entri['kateterUmbilikal'] ?? '') === 'Ya';
            $fieldHari = $kateterSentral ? 'clHari' : 'ivlHari';

            $adaTandaLokal = false;
            $adaTandaSistemik = false;
            $bulanKasus = null;

            foreach ($entri['pemasangan'] ?? [] as $barisPemasangan) {
                if (!is_array($barisPemasangan)) {
                    continue;
                }
                $this->alokasiHari($barisPemasangan['tglMulai'] ?? null, $barisPemasangan['tglSelesai'] ?? null, $tahun, $hitunganBulan, $fieldHari);

                $tanda = $barisPemasangan['tanda'] ?? [];
                $tandaLokal = $this->adaTanda($tanda, self::TANDA_PLEBITIS);
                $tandaSistemik = $this->adaTanda($tanda, self::TANDA_IAD);
                if ($tandaLokal || $tandaSistemik) {
                    $bulanKasus ??= $this->bulanDari($barisPemasangan['tglMulai'] ?? null, $tahun);
                }
                $adaTandaLokal = $adaTandaLokal || $tandaLokal;
                $adaTandaSistemik = $adaTandaSistemik || $tandaSistemik;
            }

            $bulanKasus ??= $this->bulanDari($entri['tanggal'] ?? null, $tahun);
            if ($bulanKasus === null) {
                continue;
            }

            // IAD = kateter sentral + tanda sistemik + kultur darah dilakukan (kriteria HIPPII).
            if ($kateterSentral && $adaTandaSistemik && ($entri['kulturDarah'] ?? '') === 'Ya') {
                $hitunganBulan[$bulanKasus]['iadKasus']++;
                $kasusList[] = $this->barisKasus('IAD', $bulanKasus, $pasien, $entri['tanggal'] ?? '', 'Kateter sentral + tanda sistemik + kultur darah');
            }

            // Plebitis = tanda lokal pada area insersi kateter perifer.
            if (!$kateterSentral && $adaTandaLokal) {
                $hitunganBulan[$bulanKasus]['plebitisKasus']++;
                $kasusList[] = $this->barisKasus('Plebitis', $bulanKasus, $pasien, $entri['tanggal'] ?? '', 'Tanda lokal: nyeri/merah/kalor/pus/bengkak');
            }
        }
    }

    private function kumpulkanIsk(array $entriList, int $tahun, array $pasien, array &$hitunganBulan, array &$kasusList): void
    {
        foreach ($entriList as $entri) {
            if (!is_array($entri)) {
                continue;
            }

            $adaTandaIsk = false;
            $bulanKasus = null;

            foreach ($entri['pemasangan'] ?? [] as $barisPemasangan) {
                if (!is_array($barisPemasangan)) {
                    continue;
                }
                $this->alokasiHari($barisPemasangan['tglMulai'] ?? null, $barisPemasangan['tglSelesai'] ?? null, $tahun, $hitunganBulan, 'ucHari');

                if ($this->adaTanda($barisPemasangan['tanda'] ?? [], null)) {
                    $adaTandaIsk = true;
                    $bulanKasus ??= $this->bulanDari($barisPemasangan['tglMulai'] ?? null, $tahun);
                }
            }

            $bulanKasus ??= $this->bulanDari($entri['tanggal'] ?? null, $tahun);
            if ($bulanKasus === null || !$adaTandaIsk) {
                continue;
            }

            // ISK = tanda klinis + biakan urin dilakukan (kriteria CAUTI butuh kultur ≥10^5).
            if (($entri['biakanUrin'] ?? '') === 'Ya') {
                $hitunganBulan[$bulanKasus]['iskKasus']++;
                $kasusList[] = $this->barisKasus('ISK', $bulanKasus, $pasien, $entri['tanggal'] ?? '', 'Tanda klinis ISK + biakan urin');
            }
        }
    }

    private function kumpulkanVap(array $entriList, int $tahun, array $pasien, array &$hitunganBulan, array &$kasusList): void
    {
        foreach ($entriList as $entri) {
            if (!is_array($entri)) {
                continue;
            }

            $this->alokasiHari($entri['tglPasang'] ?? null, $entri['tglLepas'] ?? null, $tahun, $hitunganBulan, 'ventHari');

            $bulanKasus = $this->bulanDari($entri['tglPasang'] ?? null, $tahun)
                ?? $this->bulanDari($entri['tanggal'] ?? null, $tahun);
            if ($bulanKasus === null) {
                continue;
            }

            // VAP = ventilator + minimal 2 dari (demam, sekresi purulen, gambaran foto toraks).
            $jumlahTandaVap = 0;
            $jumlahTandaVap += ($entri['demam'] ?? '') === 'Ya' ? 1 : 0;
            $jumlahTandaVap += ($entri['sekresiPurulen'] ?? '') === 'Ya' ? 1 : 0;
            $jumlahTandaVap += $this->adaTanda($entri['fotoToraks'] ?? [], null) ? 1 : 0;

            if (($entri['ventilator'] ?? '') === 'Ya' && $jumlahTandaVap >= 2) {
                $hitunganBulan[$bulanKasus]['vapKasus']++;
                $kasusList[] = $this->barisKasus('VAP', $bulanKasus, $pasien, $entri['tanggal'] ?? '', 'Ventilator + ≥2 tanda (demam/sekresi purulen/foto toraks)');
            }
        }
    }

    private function kumpulkanIlo(array $entriList, int $tahun, array $pasien, array &$hitunganBulan, array &$kasusList): void
    {
        foreach ($entriList as $entri) {
            if (!is_array($entri)) {
                continue;
            }

            $bulanOperasi = $this->bulanDari($entri['tanggalOperasi'] ?? null, $tahun)
                ?? $this->bulanDari($entri['tanggal'] ?? null, $tahun);
            if ($bulanOperasi === null) {
                continue;
            }

            // Denominator ILO = jumlah operasi (bukan hari alat).
            if (($entri['operasi'] ?? '') === 'Ya' || filled($entri['tanggalOperasi'] ?? null)) {
                $hitunganBulan[$bulanOperasi]['operasi']++;
            }

            // ILO = tanda infeksi luka (pus/drainase/perforasi/fistula) pada pemantauan hari ke-1..17.
            $adaTandaIlo = false;
            foreach ($entri['pemantauan'] ?? [] as $pemantauanHari) {
                if (is_array($pemantauanHari) && $this->adaTanda($pemantauanHari, self::TANDA_ILO)) {
                    $adaTandaIlo = true;
                    break;
                }
            }

            if ($adaTandaIlo) {
                $hitunganBulan[$bulanOperasi]['iloKasus']++;
                $kasusList[] = $this->barisKasus('ILO', $bulanOperasi, $pasien, $entri['tanggalOperasi'] ?? ($entri['tanggal'] ?? ''), 'Pemantauan luka: pus/drainase/perforasi/fistula');
            }
        }
    }

    /* ═══════════════ HELPER ═══════════════ */

    /**
     * Bagi lama pemakaian alat ke bulan-bulan yang dilaluinya (hari kalender).
     * Tanggal akhir kosong = masih terpasang → dihitung s/d hari ini (atau akhir tahun).
     */
    private function alokasiHari(?string $teksMulai, ?string $teksSelesai, int $tahun, array &$hitunganBulan, string $field): void
    {
        $awal = $this->parseTanggal($teksMulai);
        if (!$awal) {
            return;
        }

        // Semua perbandingan dilakukan pada AWAL HARI di timezone aplikasi.
        // Wajib: Carbon 3 mengembalikan diffInDays PECAHAN, dan Carbon::create()
        // tanpa timezone eksplisit bisa beda zona dgn tanggal hasil parse JSON —
        // dua-duanya bikin jumlah hari alat meleset (mis. 1,25 hari).
        $zonaWaktu = config('app.timezone');
        $awal = $awal->copy()->setTimezone($zonaWaktu)->startOfDay();

        $batasTahun = Carbon::create($tahun, 12, 31, 0, 0, 0, $zonaWaktu)->startOfDay();
        $akhir = ($this->parseTanggal($teksSelesai) ?? Carbon::now($zonaWaktu))->copy()->setTimezone($zonaWaktu)->startOfDay();
        if ($akhir->greaterThan($batasTahun)) {
            $akhir = $batasTahun->copy();
        }
        if ($akhir->lessThan($awal)) {
            return;
        }

        for ($bulanKe = 1; $bulanKe <= 12; $bulanKe++) {
            $awalBulan = Carbon::create($tahun, $bulanKe, 1, 0, 0, 0, $zonaWaktu)->startOfDay();
            $akhirBulan = $awalBulan->copy()->endOfMonth()->startOfDay();

            $mulaiIrisan = $awal->greaterThan($awalBulan) ? $awal : $awalBulan;
            $akhirIrisan = $akhir->lessThan($akhirBulan) ? $akhir : $akhirBulan;
            if ($akhirIrisan->lessThan($mulaiIrisan)) {
                continue;
            }

            // +1 karena hari pertama & hari terakhir sama-sama dihitung sebagai hari pemakaian.
            $hitunganBulan[$bulanKe][$field] += (int) round($mulaiIrisan->diffInDays($akhirIrisan)) + 1;
        }
    }

    /** Bulan (1-12) dari tanggal JSON; null bila di luar tahun laporan / tak valid. */
    private function bulanDari(?string $teksTanggal, int $tahun): ?int
    {
        $tanggal = $this->parseTanggal($teksTanggal);
        if (!$tanggal || $tanggal->year !== $tahun) {
            return null;
        }

        return (int) $tanggal->month;
    }

    private function parseTanggal(?string $teksTanggal): ?Carbon
    {
        if (!filled($teksTanggal)) {
            return null;
        }

        try {
            return Carbon::createFromFormat(self::FORMAT_TANGGAL, $teksTanggal, config('app.timezone'));
        } catch (\Throwable) {
            try {
                return Carbon::createFromFormat('d/m/Y', substr((string) $teksTanggal, 0, 10), config('app.timezone'))->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }
    }

    /** Ada minimal satu flag true; $daftarKey null = cek semua key pada array. */
    private function adaTanda(mixed $tanda, ?array $daftarKey): bool
    {
        if (!is_array($tanda)) {
            return false;
        }

        foreach ($daftarKey ?? array_keys($tanda) as $key) {
            if (!empty($tanda[$key])) {
                return true;
            }
        }

        return false;
    }

    private function barisKasus(string $jenis, int $bulanKe, array $pasien, string $tanggal, string $dasar): array
    {
        return array_merge($pasien, [
            'jenis' => $jenis,
            'bulan' => $bulanKe,
            'bulanLabel' => Carbon::create(2000, $bulanKe, 1)->translatedFormat('F'),
            'tanggal' => $tanggal ?: '-',
            'dasar' => $dasar,
        ]);
    }

    /** Rate kelima indikator sekaligus dari satu baris hitungan (bulan atau total). */
    private function hitungSemuaRate(array $hitungan): array
    {
        return [
            'plebitisRate' => $this->hitungRate($hitungan['plebitisKasus'], $hitungan['ivlHari'], 1000),
            'iadRate' => $this->hitungRate($hitungan['iadKasus'], $hitungan['clHari'], 1000),
            'iskRate' => $this->hitungRate($hitungan['iskKasus'], $hitungan['ucHari'], 1000),
            'vapRate' => $this->hitungRate($hitungan['vapKasus'], $hitungan['ventHari'], 1000),
            'iloRate' => $this->hitungRate($hitungan['iloKasus'], $hitungan['operasi'], 100),
        ];
    }

    private function hitungRate(int $numerator, int $denominator, int $basis): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        return round($numerator / $denominator * $basis, 2);
    }
}
