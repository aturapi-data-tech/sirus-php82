<?php

namespace App\Http\Traits\Manajemen\Rs\Ri;

use App\Support\AdmisiPulangRI;
use App\Support\OracleLob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rekap Surveilans HAIs (Healthcare-Associated Infections) Rawat Inap.
 *
 * DUA SUMBER, keduanya di `datadaftarri_json`:
 *
 *   PEMBILANG (kasus) ← modul dokumen Surveilans HAIs
 *       key surveilansPlebitisRI / surveilansIskRI / surveilansVapRI / surveilansIloRI.
 *   PENYEBUT (hari pemakaian alat) ← Observasi RI tab "Alat Invasif"
 *       key observasi.alatInvasif.alatInvasifData.
 *
 * Sengaja dipisah: formulir surveilans hanya diisi saat ada dugaan infeksi, jadi kalau
 * penyebut ikut diambil dari situ maka pembilang & penyebut sama-sama berasal dari
 * pasien bermasalah saja dan rate-nya meledak. Entri Alat Invasif diisi perawat ruangan
 * untuk SETIAP pasien terpasang alat, sehingga penyebutnya utuh. Pembagian tugas ini
 * mengikuti materi IPCN: PJ pasien mencatat pemakaian alat, IPCN mengisi formulir kasus.
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
 *
 * IAD, ISK & VAP tambahan syarat alat terpasang "> 2 hari kalender"
 * (self::MIN_HARI_ALAT) — infeksi yang muncul pada hari ke-1/ke-2 bukan HAIs
 * menurut definisi HIPPII/NHSN. Plebitis & ILO tak memakai gate ini.
 */
trait SurveilansHaisTrait
{
    /** Tanda lokal pada area insersi → dipakai menetapkan kasus PLEBITIS. */
    private const TANDA_PLEBITIS = ['nyeri', 'merah', 'kalor', 'pus', 'bengkak'];

    /** Tanda sistemik → dipakai menetapkan kasus IAD (bersama kultur darah). */
    private const TANDA_IAD = ['suhuGt38', 'suhuLt37', 'menggigil', 'sistolikLt90', 'apnu', 'nadiGt100'];

    /** Tanda ILO pada pemantauan luka operasi hari ke-1 s/d 17. */
    private const TANDA_ILO = ['pus', 'drainase', 'perforasi', 'fistula'];

    /**
     * Minimal lama pemakaian alat sebelum infeksi boleh dihitung sebagai insiden.
     *
     * Definisi IAD, ISK (CAUTI) & VAP sama-sama mensyaratkan alat terpasang
     * "> 2 hari kalender". Hari pemasangan = hari ke-1, jadi ambang lolosnya
     * adalah hari ke-3 (3 hari kalender inklusif). Plebitis & ILO TIDAK memakai
     * gate ini — definisinya tak mensyaratkan lama pemakaian alat.
     */
    private const MIN_HARI_ALAT = 3;

    /** Nilai `jenisAkses` per baris pemasangan yang dihitung sebagai central line. */
    private const AKSES_SENTRAL = ['sentral', 'umbilikal'];

    /**
     * Peta jenis alat pada entri Observasi RI → field penyebut di rekap.
     * Kunci mengikuti App\Support\SurveilansHaisOptions::ALAT_INVASIF.
     */
    private const FIELD_HARI_ALAT = [
        'ivPerifer' => 'ivlHari',
        'cvcUmbilikal' => 'clHari',
        'kateterUrine' => 'ucHari',
        'ventilator' => 'ventHari',
    ];

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

            // caraMasuk/caraKeluar tidak ikut menghitung apa pun — dibawa ke tabel audit
            // kasus supaya IPCN bisa menyaring infeksi bawaan (pasien kiriman RS lain,
            // kandidat present-on-admission) dan melihat outcome kasusnya.
            $caraMasuk = AdmisiPulangRI::caraMasuk($dataDaftarRi);
            $caraKeluar = AdmisiPulangRI::caraKeluar($dataDaftarRi);

            $pasien = [
                'rihdrNo' => $record->rihdr_no,
                'regNo' => $record->reg_no,
                'regName' => $record->reg_name,
                'ruang' => $record->room_name,
                'caraMasuk' => $caraMasuk,
                'caraKeluar' => $caraKeluar,
                // Pasien kiriman RS lain = kandidat infeksi bawaan (present on admission),
                // yang menurut definisi HAIs harus dikeluarkan dari hitungan — perlu
                // pemeriksaan manual IPCN, jadi ditandai alih-alih dibuang otomatis.
                'kirimanRsLain' => str_contains(strtoupper($caraMasuk), 'RUMAH SAKIT LAIN'),
                'meninggal' => $caraKeluar === 'Meninggal',
            ];

            // Penyebut dulu — sumbernya terpisah dari formulir surveilans (lihat docblock kelas).
            $this->kumpulkanHariAlat($dataDaftarRi['observasi']['alatInvasif']['alatInvasifData'] ?? [], $tahun, $hitunganBulan);

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
        // rstxn_rihdrs hanya menyimpan room_id & reg_no — nama ruang dan nama pasien
        // wajib di-join (lihat rsmst_rooms / rsmst_pasiens).
        return DB::table('rstxn_rihdrs as h')
            ->leftJoin('rsmst_rooms as r', 'r.room_id', '=', 'h.room_id')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->select([
                'h.rihdr_no',
                'h.reg_no',
                'p.reg_name',
                'r.room_name',
                'h.datadaftarri_json',
            ])
            // Pembilang datang dari key surveilans*, penyebut dari alatInvasif — record
            // yang cuma punya salah satunya tetap harus ikut terbaca.
            ->where(function ($subQuery) {
                $subQuery->whereRaw("INSTR(h.datadaftarri_json, 'surveilans') > 0")
                    ->orWhereRaw("INSTR(h.datadaftarri_json, 'alatInvasif') > 0");
            })
            ->where('h.entry_date', '<=', $akhirTahun)
            ->where(function ($subQuery) use ($awalTahun) {
                $subQuery->whereNull('h.exit_date')->orWhere('h.exit_date', '>=', $awalTahun);
            })
            ->orderBy('h.rihdr_no')
            ->get()
            ->all();
    }

    /* ═══════════════ PENYEBUT: HARI PEMAKAIAN ALAT ═══════════════ */

    /**
     * Hari pemakaian alat dari Observasi RI → tab "Alat Invasif".
     * Satu pasien boleh punya banyak baris (alat berbeda / pemasangan ulang);
     * semuanya diakumulasi dan dipecah ke bulan yang dilaluinya.
     */
    private function kumpulkanHariAlat(array $alatList, int $tahun, array &$hitunganBulan): void
    {
        foreach ($alatList as $baris) {
            if (!is_array($baris)) {
                continue;
            }

            $fieldHari = self::FIELD_HARI_ALAT[$baris['jenisAlat'] ?? ''] ?? null;
            if ($fieldHari === null) {
                continue;
            }

            $this->alokasiHari($baris['tanggalWaktuMulai'] ?? null, $baris['tanggalWaktuSelesai'] ?? null, $tahun, $hitunganBulan, $fieldHari);
        }
    }

    /* ═══════════════ PEMBILANG: PENGUMPULAN PER MODUL ═══════════════ */

    private function kumpulkanPlebitis(array $entriList, int $tahun, array $pasien, array &$hitunganBulan, array &$kasusList): void
    {
        foreach ($entriList as $entri) {
            if (!is_array($entri)) {
                continue;
            }

            // Satu entri bisa memuat kateter sentral DAN perifer sekaligus, jadi
            // hari alat maupun kasusnya dipilah PER BARIS pemasangan. Maksimal satu
            // kasus per jenis per entri (baris ganda umumnya = pemasangan ulang
            // akses yang sama pada episode rawat yang sama).
            $iadBulan = null;
            $plebitisBulan = null;

            foreach ($entri['pemasangan'] ?? [] as $barisPemasangan) {
                if (!is_array($barisPemasangan)) {
                    continue;
                }

                $tglMulai = $barisPemasangan['tglMulai'] ?? null;
                $tglSelesai = $barisPemasangan['tglSelesai'] ?? null;
                // Jenis akses menentukan baris ini kandidat IAD atau plebitis. Tanggalnya
                // dipakai untuk gate lama pemasangan & bulan kasus saja — hari alat
                // (penyebut) diambil dari entri Alat Invasif, bukan dari sini.
                $sentral = $this->barisKateterSentral($barisPemasangan, $entri);

                $tanda = $barisPemasangan['tanda'] ?? [];

                // IAD = kateter sentral terpasang >2 hari kalender + tanda sistemik + kultur darah.
                if (
                    $sentral
                    && $this->adaTanda($tanda, self::TANDA_IAD)
                    && $this->alatCukupLama($tglMulai, $tglSelesai)
                    && $this->adaHasilKultur($entri['kulturDarahHasil'] ?? [])
                ) {
                    $iadBulan ??= $this->bulanDari($tglMulai, $tahun) ?? $this->bulanDari($entri['tanggal'] ?? null, $tahun);
                }

                // Plebitis = tanda lokal pada area insersi kateter perifer (tanpa gate lama pemakaian).
                if (!$sentral && $this->adaTanda($tanda, self::TANDA_PLEBITIS)) {
                    $plebitisBulan ??= $this->bulanDari($tglMulai, $tahun) ?? $this->bulanDari($entri['tanggal'] ?? null, $tahun);
                }
            }

            if ($iadBulan !== null) {
                $hitunganBulan[$iadBulan]['iadKasus']++;
                $kasusList[] = $this->barisKasus('IAD', $iadBulan, $pasien, $entri['tanggal'] ?? '', 'Kateter sentral >2 hari + tanda sistemik + ada hasil kultur darah');
            }

            if ($plebitisBulan !== null) {
                $hitunganBulan[$plebitisBulan]['plebitisKasus']++;
                $kasusList[] = $this->barisKasus('Plebitis', $plebitisBulan, $pasien, $entri['tanggal'] ?? '', 'Tanda lokal: nyeri/merah/kalor/pus/bengkak');
            }
        }
    }

    /**
     * Baris pemasangan ini akses sentral/umbilikal atau perifer?
     *
     * Sumber utama = `jenisAkses` per baris. Entri lama (sebelum kolom itu ada)
     * jatuh ke flag tingkat entri; bila di situ perifer DAN sentral sama-sama
     * "Ya" barisnya tak bisa dipilah, jadi diperlakukan perifer — hari IV line
     * tetap utuh, dan IAD tidak dihitung dari data yang ambigu.
     */
    private function barisKateterSentral(array $barisPemasangan, array $entri): bool
    {
        $jenisAkses = strtolower(trim((string) ($barisPemasangan['jenisAkses'] ?? '')));
        if ($jenisAkses !== '') {
            return in_array($jenisAkses, self::AKSES_SENTRAL, true);
        }

        $perifer = ($entri['kateterPerifer'] ?? '') === 'Ya';
        $sentral = ($entri['kateterVCentral'] ?? '') === 'Ya' || ($entri['kateterUmbilikal'] ?? '') === 'Ya';

        return $sentral && !$perifer;
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
                $tglMulai = $barisPemasangan['tglMulai'] ?? null;
                $tglSelesai = $barisPemasangan['tglSelesai'] ?? null;

                // Kriteria CAUTI: kateter sudah terpasang >2 hari kalender saat spesimen diambil.
                if ($this->adaTanda($barisPemasangan['tanda'] ?? [], null) && $this->alatCukupLama($tglMulai, $tglSelesai)) {
                    $adaTandaIsk = true;
                    $bulanKasus ??= $this->bulanDari($tglMulai, $tahun);
                }
            }

            $bulanKasus ??= $this->bulanDari($entri['tanggal'] ?? null, $tahun);
            if ($bulanKasus === null || !$adaTandaIsk) {
                continue;
            }

            // ISK = tanda klinis + ada hasil biakan urin (kriteria CAUTI butuh kultur ≥10^5).
            if ($this->adaHasilKultur($entri['biakanUrinHasil'] ?? [])) {
                $hitunganBulan[$bulanKasus]['iskKasus']++;
                $kasusList[] = $this->barisKasus('ISK', $bulanKasus, $pasien, $entri['tanggal'] ?? '', 'Tanda klinis ISK + ada hasil biakan urin');
            }
        }
    }

    private function kumpulkanVap(array $entriList, int $tahun, array $pasien, array &$hitunganBulan, array &$kasusList): void
    {
        foreach ($entriList as $entri) {
            if (!is_array($entri)) {
                continue;
            }

            $tglPasang = $entri['tglPasang'] ?? null;
            $tglLepas = $entri['tglLepas'] ?? null;

            $bulanKasus = $this->bulanDari($entri['tglPasang'] ?? null, $tahun)
                ?? $this->bulanDari($entri['tanggal'] ?? null, $tahun);
            if ($bulanKasus === null) {
                continue;
            }

            // VAP = ventilator terpasang >2 hari kalender + minimal 2 dari
            // (demam, sekresi purulen, gambaran foto toraks).
            $jumlahTandaVap = 0;
            $jumlahTandaVap += ($entri['demam'] ?? '') === 'Ya' ? 1 : 0;
            $jumlahTandaVap += ($entri['sekresiPurulen'] ?? '') === 'Ya' ? 1 : 0;
            $jumlahTandaVap += $this->adaTanda($entri['fotoToraks'] ?? [], null) ? 1 : 0;

            if (($entri['ventilator'] ?? '') === 'Ya' && $jumlahTandaVap >= 2 && $this->alatCukupLama($tglPasang, $tglLepas)) {
                $hitunganBulan[$bulanKasus]['vapKasus']++;
                $kasusList[] = $this->barisKasus('VAP', $bulanKasus, $pasien, $entri['tanggal'] ?? '', 'Ventilator >2 hari + ≥2 tanda (demam/sekresi purulen/foto toraks)');
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

    /**
     * Lama pemakaian alat dalam hari kalender inklusif (hari pasang = hari ke-1).
     * Tanggal lepas kosong = masih terpasang → dihitung s/d hari ini.
     * Berbeda dari alokasiHari(): TIDAK dipotong batas tahun laporan, karena yang
     * dinilai lama pakai alat pada pasien, bukan irisannya dengan periode laporan.
     */
    private function lamaHariAlat(?string $teksMulai, ?string $teksSelesai): ?int
    {
        $awal = $this->parseTanggal($teksMulai);
        if (!$awal) {
            return null;
        }

        $zonaWaktu = config('app.timezone');
        $awal = $awal->copy()->setTimezone($zonaWaktu)->startOfDay();
        $akhir = ($this->parseTanggal($teksSelesai) ?? Carbon::now($zonaWaktu))->copy()->setTimezone($zonaWaktu)->startOfDay();
        if ($akhir->lessThan($awal)) {
            return null;
        }

        return (int) round($awal->diffInDays($akhir)) + 1;
    }

    /** Alat sudah terpasang "> 2 hari kalender" (lihat MIN_HARI_ALAT)? */
    private function alatCukupLama(?string $teksMulai, ?string $teksSelesai): bool
    {
        $lama = $this->lamaHariAlat($teksMulai, $teksSelesai);

        return $lama !== null && $lama >= self::MIN_HARI_ALAT;
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

    /**
     * Kultur dianggap DILAKUKAN bila daftar hasilnya memuat minimal satu baris terisi.
     * Formulir tak lagi punya toggle "Kultur Dilakukan" — ada-tidaknya hasil sudah
     * menjawabnya, jadi tak ada dua sumber yang bisa saling bertentangan.
     */
    private function adaHasilKultur(mixed $hasilList): bool
    {
        if (!is_array($hasilList)) {
            return false;
        }

        foreach ($hasilList as $baris) {
            if (is_array($baris) && (filled($baris['tgl'] ?? null) || filled($baris['hasil'] ?? null))) {
                return true;
            }
        }

        return false;
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
