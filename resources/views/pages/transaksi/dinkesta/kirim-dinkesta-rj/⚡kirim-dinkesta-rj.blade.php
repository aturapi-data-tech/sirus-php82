<?php
// resources/views/pages/transaksi/dinkesta/kirim-dinkesta-rj/kirim-dinkesta-rj.blade.php
// Halaman berdiri sendiri (route dinkesta.kirim-rj, grup menu Dinas Kesehatan Tulungagung) —
// kewajiban daerah, sengaja TIDAK dimasukkan ke Casemix.
//
// Kirim bulk diagnosis ICD-10 kunjungan RJ per bulan ke Portal Satu Data Kesehatan Tulungagung.
// Kirim satuan versi lama ada di sirus-lite (EmrRJ/PostSatuDataKesehatanRJ).
//
// TIDAK MENULIS JSON KUNJUNGAN. Status kirim per ICD ada di RSTXN_DINKESTA_KIRIMS
// (docs/ddl-dinkesta-kirim.sql); ICD-10 dibaca dari rstxn_rjdtls + rsmst_mstdiags.
// Satu-satunya sentuhan ke datadaftarpolirj_json: dbms_lob.instr (baca saja, di Oracle)
// untuk mengenali kunjungan yang sudah dikirim dari sirus-lite, supaya tidak dobel.

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Session;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\SatuDataKesehatan\SatuDataKesehatanTrait;

new class extends Component {
    use WithPagination, SatuDataKesehatanTrait;

    private const TABEL_KIRIM = 'rstxn_dinkesta_kirims';
    private const JALUR = 'RJ';
    private const KEY_JSON_LAMA = 'satuDataKesehatanTulungagung';
    private const MENIT_TERTAHAN = 15; // status P lebih lama dari ini = proses mati di tengah → tidak pasti
    private const ROLE_PENGIRIM = ['Admin', 'Casemix', 'Tu'];

    #[Session(key: 'kirim-dinkesta-rj-searchKeyword')]
    public string $searchKeyword = '';
    #[Session(key: 'kirim-dinkesta-rj-filterBulan')]
    public string $filterBulan = ''; // m/Y
    #[Session(key: 'kirim-dinkesta-rj-filterKlaim')]
    public string $filterKlaim = ''; // '' | BPJS | UMUM — DINKESTA butuh semua pasien
    #[Session(key: 'kirim-dinkesta-rj-filterDokter')]
    public string $filterDokter = '';
    #[Session(key: 'kirim-dinkesta-rj-itemsPerPage')]
    public int $itemsPerPage = 25;

    public function mount(): void
    {
        $this->filterBulan = $this->filterBulan ?: Carbon::now()->format('m/Y');
    }

    public function updated($property): void
    {
        if (in_array($property, ['searchKeyword', 'filterBulan', 'filterKlaim', 'filterDokter', 'itemsPerPage'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterKlaim', 'filterDokter']);
        $this->filterBulan = Carbon::now()->format('m/Y');
        $this->resetPage();
    }

    #[Computed]
    public function tabelKirimTersedia(): bool
    {
        try {
            DB::table(self::TABEL_KIRIM)->limit(1)->exists();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /* -------------------------
     | Query daftar — hanya kunjungan Selesai (rj_status L), tanpa CLOB
     * ------------------------- */
    private function rentangBulan(): array
    {
        try {
            $awalBulan = Carbon::createFromFormat('m/Y', trim($this->filterBulan))->startOfMonth();
        } catch (\Exception $e) {
            $awalBulan = Carbon::now()->startOfMonth();
        }
        return [$awalBulan, (clone $awalBulan)->endOfMonth()];
    }

    private function baseQuery()
    {
        [$awal, $akhir] = $this->rentangBulan();

        $query = DB::table('rstxn_rjhdrs as h')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_polis as po', 'po.poli_id', '=', 'h.poli_id')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
            ->leftJoin('rsmst_desas as ds', 'ds.des_id', '=', 'p.des_id')
            ->leftJoin('rsmst_kecamatans as kc', 'kc.kec_id', '=', 'p.kec_id')
            ->leftJoin('rsmst_kabupatens as kb', 'kb.kab_id', '=', 'p.kab_id')
            ->whereBetween('h.rj_date', [$awal, $akhir])
            ->where('h.rj_status', 'L');

        if ($this->filterKlaim === 'BPJS') {
            $query->where(function ($subQuery) {
                $subQuery->where('k.klaim_status', 'BPJS')->orWhere('h.klaim_id', 'JM');
            });
        } elseif ($this->filterKlaim === 'UMUM') {
            $query->where(function ($subQuery) {
                $subQuery->where(function ($kondisi) {
                    $kondisi->where('k.klaim_status', '!=', 'BPJS')->orWhereNull('k.klaim_status');
                })->where('h.klaim_id', '!=', 'JM');
            });
        }

        if ($this->filterDokter !== '') {
            $query->where('h.dr_id', $this->filterDokter);
        }

        $search = trim($this->searchKeyword);
        if ($search !== '' && mb_strlen($search) >= 2) {
            $keyword = mb_strtoupper($search);
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->orWhere(DB::raw('UPPER(h.rj_no)'), 'like', "%{$keyword}%")
                    ->orWhere(DB::raw('UPPER(h.reg_no)'), 'like', "%{$keyword}%")
                    ->orWhere(DB::raw('UPPER(p.reg_name)'), 'like', "%{$keyword}%")
                    ->orWhere(DB::raw('UPPER(p.nik_bpjs)'), 'like', "%{$keyword}%");
            });
        }

        return $query;
    }

    #[Computed]
    public function rows()
    {
        $paginator = $this->baseQuery()
            ->select([
                'h.rj_no', 'h.reg_no', 'h.klaim_id',
                DB::raw("to_char(h.rj_date,'dd/mm/yyyy hh24:mi:ss') as rj_date_display"),
                'p.reg_name', 'p.sex', 'p.address', 'p.nik_bpjs',
                DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"),
                'ds.des_name', 'kc.kec_name', 'kb.kab_name',
                'po.poli_desc', 'd.dr_name', 'k.klaim_desc', 'k.klaim_status',
            ])
            ->orderBy('h.rj_date', 'desc')
            ->orderBy('h.rj_no', 'desc')
            ->paginate($this->itemsPerPage);

        $rjNoHalaman = $paginator->getCollection()->pluck('rj_no')->map(fn($rjNo) => (int) $rjNo)->all();
        $icdPerRjNo = $this->icdPerKunjungan($rjNoHalaman);
        $statusPerRjNo = $this->statusKirimPerKunjungan($rjNoHalaman);
        $rjNoAplikasiLama = $this->terkirimAplikasiLama($rjNoHalaman);

        $paginator->setCollection(
            $paginator->getCollection()->map(function ($row) use ($icdPerRjNo, $statusPerRjNo, $rjNoAplikasiLama) {
                $rjNo = (int) $row->rj_no;
                $row->icd_list = $icdPerRjNo[$rjNo] ?? [];
                $row->kekurangan = $this->kekuranganData($row, $row->icd_list);
                $row->terkirim_aplikasi_lama = in_array($rjNo, $rjNoAplikasiLama, true);

                $statusIcdList = [];
                foreach ($row->icd_list as $icd) {
                    $statusIcdList[$icd] = $this->statusEfektif($statusPerRjNo[$rjNo][$icd] ?? null);
                }
                $row->status_icd = $statusIcdList;
                $row->status_kirim = $this->ringkasStatusKunjungan($statusIcdList, $row->terkirim_aplikasi_lama);
                $row->info_kirim = $this->infoKirimTerakhir($statusPerRjNo[$rjNo] ?? []);

                return $row;
            })
        );

        return $paginator;
    }

    #[Computed]
    public function jumlahKunjungan(): int
    {
        return $this->baseQuery()->count();
    }

    #[Computed]
    public function dokterList()
    {
        [$awal, $akhir] = $this->rentangBulan();
        return DB::table('rstxn_rjhdrs as h')
            ->join('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->whereBetween('h.rj_date', [$awal, $akhir])
            ->where('h.rj_status', 'L')
            ->groupBy('h.dr_id')
            ->selectRaw('h.dr_id, MAX(d.dr_name) as dr_name')
            ->orderBy('dr_name')
            ->get();
    }

    /* -------------------------
     | Sumber data (tabel, bukan JSON)
     * ------------------------- */
    /** @return array<int rj_no, string[] icdx> — ICD-10 unik per kunjungan dari rstxn_rjdtls. */
    private function icdPerKunjungan(array $rjNoList): array
    {
        if (empty($rjNoList)) {
            return [];
        }
        $icdPerRjNo = [];
        DB::table('rstxn_rjdtls as dtl')
            ->join('rsmst_mstdiags as m', 'm.diag_id', '=', 'dtl.diag_id')
            ->whereIn('dtl.rj_no', $rjNoList)
            ->whereNotNull('m.icdx')
            ->select('dtl.rj_no', 'm.icdx')
            ->distinct()
            ->get()
            ->each(function ($row) use (&$icdPerRjNo) {
                $icd = trim((string) $row->icdx);
                if ($icd !== '') {
                    $icdPerRjNo[(int) $row->rj_no][] = $icd;
                }
            });
        return $icdPerRjNo;
    }

    /** @return array<int rj_no, array<string icdx, object>> */
    private function statusKirimPerKunjungan(array $rjNoList): array
    {
        if (empty($rjNoList) || !$this->tabelKirimTersedia) {
            return [];
        }
        $statusPerRjNo = [];
        DB::table(self::TABEL_KIRIM)
            ->where('status_rjri', self::JALUR)
            ->whereIn('ref_no', $rjNoList)
            ->select(['ref_no', 'icdx', 'kirim_status', 'kirim_date', 'kirim_user', 'http_code', 'respon'])
            ->get()
            ->each(function ($row) use (&$statusPerRjNo) {
                $statusPerRjNo[(int) $row->ref_no][(string) $row->icdx] = $row;
            });
        return $statusPerRjNo;
    }

    /** rj_no yang sudah dikirim dari sirus-lite (penanda di JSON) — dicek di Oracle, CLOB tak diambil. */
    private function terkirimAplikasiLama(array $rjNoList): array
    {
        if (empty($rjNoList)) {
            return [];
        }
        return DB::table('rstxn_rjhdrs')
            ->whereIn('rj_no', $rjNoList)
            ->whereRaw('dbms_lob.instr(datadaftarpolirj_json, ?) > 0', ['"' . self::KEY_JSON_LAMA . '"'])
            ->pluck('rj_no')
            ->map(fn($rjNo) => (int) $rjNo)
            ->all();
    }

    /** S | G | T | P | B (belum ada baris). P yang tertahan lama dianggap T. */
    private function statusEfektif(?object $rowKirim): string
    {
        if ($rowKirim === null) {
            return 'B';
        }
        if ($rowKirim->kirim_status === 'P' && Carbon::parse($rowKirim->kirim_date)->lt(Carbon::now()->subMinutes(self::MENIT_TERTAHAN))) {
            return 'T';
        }
        return (string) $rowKirim->kirim_status;
    }

    private function ringkasStatusKunjungan(array $statusIcdList, bool $terkirimAplikasiLama): string
    {
        if ($terkirimAplikasiLama) {
            return 'lama';
        }
        if (empty($statusIcdList)) {
            return 'belum';
        }
        $statusList = array_values($statusIcdList);
        if (count(array_filter($statusList, fn($status) => $status === 'S')) === count($statusList)) {
            return 'terkirim';
        }
        if (array_intersect($statusList, ['T'])) {
            return 'tidak_pasti';
        }
        if (array_intersect($statusList, ['P'])) {
            return 'proses';
        }
        if (array_intersect($statusList, ['S'])) {
            return 'sebagian';
        }
        if (array_intersect($statusList, ['G'])) {
            return 'gagal';
        }
        return 'belum';
    }

    private function infoKirimTerakhir(array $rowKirimList): string
    {
        $terakhir = collect($rowKirimList)->sortByDesc('kirim_date')->first();
        if (!$terakhir) {
            return '';
        }
        $teks = Carbon::parse($terakhir->kirim_date)->format('d/m/Y H:i') . ' ' . ($terakhir->kirim_user ?? '');
        if (in_array($terakhir->kirim_status, ['G', 'T'], true) && !empty($terakhir->respon)) {
            $teks .= ' — ' . mb_strimwidth((string) $terakhir->respon, 0, 120, '...');
        }
        return trim($teks);
    }

    /** Master wilayah lama diawali garis bawah ("_TULUNGAGUNG", "_JAWA TIMUR") — dibuang sebelum dikirim. */
    private function bersihkanTeks($teks): string
    {
        return trim(ltrim(trim((string) $teks), '_'));
    }

    private function kekuranganData($row, array $icdList): array
    {
        $kekurangan = [];
        if (!preg_match('/^\d{16}$/', trim((string) ($row->nik_bpjs ?? '')))) {
            $kekurangan[] = 'NIK 16 digit';
        }
        if (!in_array(trim((string) ($row->sex ?? '')), ['L', 'P'], true)) {
            $kekurangan[] = 'Jenis kelamin';
        }
        if (trim((string) ($row->birth_date ?? $row->tgl_lahir_kirim ?? '')) === '') {
            $kekurangan[] = 'Tgl lahir';
        }
        if ($this->bersihkanTeks($row->kab_name ?? '') === '') {
            $kekurangan[] = 'Kabupaten';
        }
        if (trim((string) ($row->address ?? '')) === '') {
            $kekurangan[] = 'Alamat';
        }
        if (trim((string) ($row->des_name ?? '')) === '') {
            $kekurangan[] = 'Desa';
        }
        if (trim((string) ($row->kec_name ?? '')) === '') {
            $kekurangan[] = 'Kecamatan';
        }
        if (empty($icdList)) {
            $kekurangan[] = 'Diagnosis';
        }
        return $kekurangan;
    }

    /* -------------------------
     | Kirim — klaim baris dulu (PK = anti-dobel), baru POST
     * ------------------------- */
    private function bolehKirim(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(self::ROLE_PENGIRIM);
    }

    /**
     * Klaim satu ICD untuk dikirim: INSERT baris P, atau UPDATE ke P bila statusnya G
     * (atau T / P tertahan, hanya saat kirim ulang manual). UPDATE bersyarat di Oracle
     * atomik — dua proses bersamaan, hanya satu yang mendapat 1 baris.
     */
    private function klaimIcd(int $rjNo, string $icd, bool $izinkanTidakPasti): bool
    {
        $kunci = ['status_rjri' => self::JALUR, 'ref_no' => $rjNo, 'icdx' => $icd];
        $nilaiProses = [
            'kirim_status' => 'P',
            'kirim_date' => Carbon::now(),
            'kirim_user' => mb_substr((string) (auth()->user()?->myuser_name ?? ''), 0, 50),
            'http_code' => null,
            'respon' => null,
        ];

        try {
            DB::table(self::TABEL_KIRIM)->insert($kunci + $nilaiProses);
            return true;
        } catch (\Throwable $e) {
            if (!str_contains($e->getMessage(), 'ORA-00001')) {
                throw $e;
            }
        }

        $batasTertahan = Carbon::now()->subMinutes(self::MENIT_TERTAHAN);
        return DB::table(self::TABEL_KIRIM)
            ->where($kunci)
            ->where(function ($subQuery) use ($izinkanTidakPasti, $batasTertahan) {
                $subQuery->where('kirim_status', 'G');
                if ($izinkanTidakPasti) {
                    $subQuery->orWhere('kirim_status', 'T')
                        ->orWhere(fn($kondisi) => $kondisi->where('kirim_status', 'P')->where('kirim_date', '<', $batasTertahan));
                }
            })
            ->update($nilaiProses) === 1;
    }

    private function catatHasilIcd(int $rjNo, string $icd, string $status, int $httpCode, string $respon): void
    {
        // Oracle Latin-1: karakter di luar ASCII cetak diganti '?' supaya tak jadi sampah / ORA-12899.
        $responAman = substr(preg_replace('/[^\x20-\x7E]/', '?', $respon), 0, 2000);

        DB::table(self::TABEL_KIRIM)
            ->where(['status_rjri' => self::JALUR, 'ref_no' => $rjNo, 'icdx' => $icd])
            ->update([
                'kirim_status' => $status,
                'kirim_date' => Carbon::now(),
                'http_code' => $httpCode,
                'respon' => $responAman,
            ]);
    }

    /**
     * Kirim satu kunjungan: tiap ICD-10 = satu POST, tiap ICD punya baris sendiri.
     * $izinkanTidakPasti = true hanya dari tombol "Kirim ulang" per baris (sesudah petugas
     * mengecek portal); bulk tak pernah mengirim ulang ICD berstatus T.
     *
     * @return array{rjNo:int, nama:string, status:string, pesan:string}
     */
    private function kirimKunjungan(int $rjNo, bool $izinkanTidakPasti = false): array
    {
        $kunjungan = DB::table('rstxn_rjhdrs as h')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_desas as ds', 'ds.des_id', '=', 'p.des_id')
            ->leftJoin('rsmst_kecamatans as kc', 'kc.kec_id', '=', 'p.kec_id')
            ->leftJoin('rsmst_kabupatens as kb', 'kb.kab_id', '=', 'p.kab_id')
            ->leftJoin('rsmst_propinsis as pr', 'pr.prop_id', '=', 'p.prop_id')
            ->where('h.rj_no', $rjNo)
            ->select([
                'h.rj_no', 'h.rj_status', DB::raw("to_char(h.rj_date,'dd-mm-yyyy') as tanggal_kirim"),
                'p.reg_name', 'p.nik_bpjs', 'p.sex', 'p.address',
                DB::raw("to_char(p.birth_date,'dd-mm-yyyy') as tgl_lahir_kirim"),
                'ds.des_name', 'kc.kec_name', 'kb.kab_name', 'pr.prop_name',
            ])
            ->first();

        if (!$kunjungan) {
            return ['rjNo' => $rjNo, 'nama' => '-', 'status' => 'gagal', 'pesan' => 'Kunjungan tidak ditemukan'];
        }
        $hasilDasar = ['rjNo' => $rjNo, 'nama' => (string) $kunjungan->reg_name];

        if ($kunjungan->rj_status !== 'L') {
            return $hasilDasar + ['status' => 'gagal', 'pesan' => 'Kunjungan belum Selesai'];
        }
        if (in_array($rjNo, $this->terkirimAplikasiLama([$rjNo]), true)) {
            return $hasilDasar + ['status' => 'sudah', 'pesan' => 'Sudah dikirim dari aplikasi lama'];
        }

        $icdList = $this->icdPerKunjungan([$rjNo])[$rjNo] ?? [];
        $kekurangan = $this->kekuranganData($kunjungan, $icdList);
        if (!empty($kekurangan)) {
            return $hasilDasar + ['status' => 'gagal', 'pesan' => 'Data kosong: ' . implode(', ', $kekurangan)];
        }

        $statusAwal = $this->statusKirimPerKunjungan([$rjNo])[$rjNo] ?? [];
        $jumlahBaruTerkirim = 0;
        $icdTidakPasti = [];
        $pesanGagal = [];

        foreach ($icdList as $icd) {
            $status = $this->statusEfektif($statusAwal[$icd] ?? null);
            if ($status === 'S') {
                continue;
            }
            if (in_array($status, ['T', 'P'], true) && !$izinkanTidakPasti) {
                $icdTidakPasti[] = $icd;
                continue;
            }
            if (!$this->klaimIcd($rjNo, $icd, $izinkanTidakPasti)) {
                continue; // sudah S / sedang dikirim proses lain sejak dibaca
            }

            // Nilai kunjungan/kasus "Baru" mengikuti kirim satuan versi lama (sirus-lite).
            // Field mengikuti dokumentasi portal (menu API Service). Tanggal & tgl_lahir dd-mm-yyyy
            // — format ini diterima portal pada uji 10/11/2026 ("Tanggal= 2026-01-01").
            $respon = self::kirimSatuDataKesehatan([
                'tanggal' => $kunjungan->tanggal_kirim,
                'nik' => trim((string) $kunjungan->nik_bpjs),
                'nama' => $this->bersihkanTeks($kunjungan->reg_name),
                'tgl_lahir' => (string) $kunjungan->tgl_lahir_kirim,
                'jeniskelamin' => trim((string) $kunjungan->sex),
                'alamat' => $this->bersihkanTeks($kunjungan->address),
                'desa' => $this->bersihkanTeks($kunjungan->des_name),
                'kecamatan' => $this->bersihkanTeks($kunjungan->kec_name),
                'kabupaten' => $this->bersihkanTeks($kunjungan->kab_name),
                'propinsi' => $this->bersihkanTeks($kunjungan->prop_name),
                'icdx' => $icd,
                'kunjungan' => 'Baru',
                'kasus' => 'Baru',
            ]);

            // Sukses/gagal ditentukan dari isi "pesan" (lihat tafsirBalasanSatuDataKesehatan) —
            // portal membalas HTTP 200 juga untuk data yang ditolak.
            if ($respon['hasil'] === 'berhasil') {
                $this->catatHasilIcd($rjNo, $icd, 'S', $respon['code'], $respon['pesan']);
                $jumlahBaruTerkirim++;
            } elseif ($respon['hasil'] === 'tidak_pasti') {
                $this->catatHasilIcd($rjNo, $icd, 'T', $respon['code'], $respon['pesan']);
                $icdTidakPasti[] = $icd;
            } else {
                $this->catatHasilIcd($rjNo, $icd, 'G', $respon['code'], $respon['pesan']);
                $pesanGagal[] = "{$icd}: {$respon['pesan']}";
            }
        }

        if (!empty($pesanGagal)) {
            return $hasilDasar + ['status' => 'gagal', 'pesan' => implode('; ', $pesanGagal)];
        }
        if (!empty($icdTidakPasti)) {
            return $hasilDasar + ['status' => 'tidak_pasti', 'pesan' => 'Belum pasti masuk portal (cek dulu): ' . implode(', ', $icdTidakPasti)];
        }
        if ($jumlahBaruTerkirim > 0) {
            return $hasilDasar + ['status' => 'terkirim', 'pesan' => "{$jumlahBaruTerkirim} ICD-10 terkirim"];
        }
        return $hasilDasar + ['status' => 'sudah', 'pesan' => 'Semua ICD-10 sudah terkirim'];
    }

    private function siapKirim(): bool
    {
        if (!$this->bolehKirim()) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Admin/Casemix/TU yang boleh mengirim ke DINKESTA.');
            return false;
        }
        if (!$this->tabelKirimTersedia) {
            $this->dispatch('toast', type: 'error', message: 'Tabel RSTXN_DINKESTA_KIRIMS belum dibuat (docs/ddl-dinkesta-kirim.sql).');
            return false;
        }
        return true;
    }

    public function kirimSatu(int $rjNo, bool $izinkanTidakPasti = false): void
    {
        if (!$this->siapKirim()) {
            return;
        }

        try {
            $hasil = $this->kirimKunjungan($rjNo, $izinkanTidakPasti);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal kirim: ' . $e->getMessage());
            return;
        }

        $tipeToast = ['terkirim' => 'success', 'sudah' => 'info', 'tidak_pasti' => 'warning', 'gagal' => 'error'][$hasil['status']] ?? 'info';
        $this->dispatch('toast', type: $tipeToast, message: "{$hasil['nama']} — {$hasil['pesan']}");
    }

    /**
     * Antrean kirim bulk: kunjungan sesuai filter yang masih punya ICD tanpa baris S/T/P.
     * Kunjungan dari aplikasi lama tetap masuk antrean lalu dilewati di kirimKunjungan().
     */
    #[Renderless]
    public function daftarRjNoKirim(): array
    {
        if (!$this->siapKirim()) {
            return [];
        }

        return $this->baseQuery()
            ->whereExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('rstxn_rjdtls as dtl')
                    ->join('rsmst_mstdiags as m', 'm.diag_id', '=', 'dtl.diag_id')
                    ->whereColumn('dtl.rj_no', 'h.rj_no')
                    ->whereNotNull('m.icdx')
                    ->whereNotExists(function ($kirimQuery) {
                        $kirimQuery->select(DB::raw(1))
                            ->from(self::TABEL_KIRIM . ' as kirim')
                            ->where('kirim.status_rjri', self::JALUR)
                            ->whereColumn('kirim.ref_no', 'dtl.rj_no')
                            ->whereColumn('kirim.icdx', 'm.icdx')
                            ->whereIn('kirim.kirim_status', ['S', 'T', 'P']);
                    });
            })
            ->orderBy('h.rj_date')
            ->pluck('h.rj_no')
            ->map(fn($rjNo) => (int) $rjNo)
            ->all();
    }

    /** Satu batch kecil dari antrean bulk; browser memanggil berulang sampai antrean habis. */
    #[Renderless]
    public function kirimBatch(array $rjNoList): array
    {
        if (!$this->bolehKirim() || !$this->tabelKirimTersedia) {
            return [];
        }

        $hasilList = [];
        foreach (array_slice($rjNoList, 0, 10) as $rjNo) {
            try {
                $hasilList[] = $this->kirimKunjungan((int) $rjNo);
            } catch (\Throwable $e) {
                $hasilList[] = ['rjNo' => (int) $rjNo, 'nama' => '-', 'status' => 'gagal', 'pesan' => $e->getMessage()];
            }
        }
        return $hasilList;
    }
};
?>

<div>
    <x-page-title title="Kirim DINKESTA — Rawat Jalan"
        subtitle="Kirim bulk diagnosis ICD-10 kunjungan RJ Selesai per bulan ke Portal Satu Data Kesehatan Tulungagung." />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800"
        x-data="{
            tahap: 'siap',
            antrean: [],
            total: 0,
            diproses: 0,
            terkirim: 0,
            sudah: 0,
            tidakPastiList: [],
            gagalList: [],
            dimintaBerhenti: false,
            async siapkan() {
                this.tahap = 'memuat';
                this.antrean = await $wire.daftarRjNoKirim();
                this.total = this.antrean.length;
                this.tahap = 'konfirmasi';
            },
            async mulai() {
                this.tahap = 'berjalan';
                this.diproses = 0; this.terkirim = 0; this.sudah = 0;
                this.tidakPastiList = []; this.gagalList = [];
                this.dimintaBerhenti = false;
                const ukuranBatch = 5;
                for (let posisi = 0; posisi < this.antrean.length; posisi += ukuranBatch) {
                    if (this.dimintaBerhenti) break;
                    let hasilList = [];
                    try {
                        hasilList = await $wire.kirimBatch(this.antrean.slice(posisi, posisi + ukuranBatch));
                    } catch (e) {
                        this.gagalList.push({ rjNo: '-', nama: '-', pesan: 'Batch gagal: ' + e });
                    }
                    (hasilList || []).forEach((hasil) => {
                        if (hasil.status === 'terkirim') this.terkirim++;
                        else if (hasil.status === 'sudah') this.sudah++;
                        else if (hasil.status === 'tidak_pasti') this.tidakPastiList.push(hasil);
                        else this.gagalList.push(hasil);
                    });
                    this.diproses = Math.min(posisi + ukuranBatch, this.total);
                }
                this.tahap = 'selesai';
                $wire.$refresh();
            },
            tutup() { this.tahap = 'siap'; this.antrean = []; },
        }">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            @if (!$this->tabelKirimTersedia)
                <div class="p-3 mb-2 text-sm text-red-800 border border-red-200 rounded-xl bg-red-50 dark:bg-red-900/20 dark:border-red-800 dark:text-red-200">
                    Tabel <b>RSTXN_DINKESTA_KIRIMS</b> belum dibuat di database ini — jalankan
                    <span class="font-mono">docs/ddl-dinkesta-kirim.sql</span>. Daftar tetap bisa dilihat, pengiriman dinonaktifkan.
                </div>
            @endif

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full mt-1"
                            placeholder="Cari No RJ / No RM / Nama / NIK..." />
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Bulan" />
                        <x-text-input type="text" wire:model.live.debounce.500ms="filterBulan"
                            class="block w-full mt-1 sm:w-32" placeholder="mm/yyyy" maxlength="7" />
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Klaim" />
                        <x-select-input wire:model.live="filterKlaim" class="w-full mt-1 sm:w-32">
                            <option value="">Semua</option>
                            <option value="BPJS">BPJS</option>
                            <option value="UMUM">UMUM</option>
                        </x-select-input>
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Dokter" />
                        <x-select-input wire:model.live="filterDokter" class="w-full mt-1 sm:w-48">
                            <option value="">Semua Dokter</option>
                            @foreach ($this->dokterList as $dokter)
                                <option value="{{ $dokter->dr_id }}">{{ $dokter->dr_name }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    <div class="flex items-center gap-2 ml-auto">
                        <x-toolbar-refresh-reset :label="null" />
                        <div class="w-24">
                            <x-select-input wire:model.live="itemsPerPage">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                        @hasanyrole('Admin|Casemix|Tu')
                            @if ($this->tabelKirimTersedia)
                                <x-primary-button type="button" x-on:click="siapkan()" x-bind:disabled="tahap !== 'siap'"
                                    title="Kirim semua kunjungan sesuai filter bulan, klaim, dokter & pencarian (bukan hanya halaman ini)">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                                    </svg>
                                    Kirim Bulk {{ $filterBulan }}
                                </x-primary-button>
                            @endif
                        @endhasanyrole
                    </div>
                </div>

                {{-- PANEL BULK --}}
                <div x-show="tahap !== 'siap'" x-cloak
                    class="p-4 mt-3 text-sm border rounded-xl border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700">
                    <div x-show="tahap === 'memuat'" class="text-muted">Menghitung kunjungan yang perlu dikirim…</div>

                    <div x-show="tahap === 'konfirmasi'" class="flex flex-wrap items-center gap-3">
                        <span class="text-ink dark:text-gray-200">
                            <b x-text="total"></b> kunjungan masih punya ICD-10 yang belum terkirim.
                            ICD yang sudah sukses atau berstatus tidak pasti tidak dikirim ulang.
                        </span>
                        <x-primary-button type="button" x-on:click="mulai()" x-bind:disabled="total === 0">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
                            </svg>
                            Mulai Kirim
                        </x-primary-button>
                        <x-secondary-button type="button" x-on:click="tutup()">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Batal
                        </x-secondary-button>
                    </div>

                    <div x-show="tahap === 'berjalan' || tahap === 'selesai'" class="space-y-2">
                        <div class="flex flex-wrap items-center gap-4">
                            <span class="font-semibold text-ink dark:text-gray-200"
                                x-text="(tahap === 'berjalan' ? 'Mengirim… ' : 'Selesai — ') + diproses + ' / ' + total"></span>
                            <span class="text-emerald-700 dark:text-emerald-400">Terkirim: <b x-text="terkirim"></b></span>
                            <span class="text-muted">Sudah sebelumnya: <b x-text="sudah"></b></span>
                            <span class="text-amber-700 dark:text-amber-400">Tidak pasti: <b x-text="tidakPastiList.length"></b></span>
                            <span class="text-red-700 dark:text-red-400">Gagal: <b x-text="gagalList.length"></b></span>
                            {{-- Berhenti setelah kunjungan yang sedang diproses selesai; ICD yang sudah terkirim tetap tercatat. --}}
                            <x-danger-button type="button" x-show="tahap === 'berjalan'" x-on:click="dimintaBerhenti = true"
                                x-bind:disabled="dimintaBerhenti">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 7.5A2.25 2.25 0 017.5 5.25h9a2.25 2.25 0 012.25 2.25v9a2.25 2.25 0 01-2.25 2.25h-9a2.25 2.25 0 01-2.25-2.25v-9z" />
                                </svg>
                                <span x-text="dimintaBerhenti ? 'Menghentikan…' : 'Hentikan Kirim'"></span>
                            </x-danger-button>
                            <x-secondary-button type="button" x-show="tahap === 'selesai'" x-on:click="tutup()">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                Tutup
                            </x-secondary-button>
                        </div>
                        <div class="w-full h-2 overflow-hidden rounded-full bg-surface-soft dark:bg-gray-700">
                            <div class="h-2 transition-all bg-brand dark:bg-brand-lime" x-bind:style="'width:' + (total ? (diproses / total * 100) : 0) + '%'"></div>
                        </div>
                        <div x-show="tidakPastiList.length || gagalList.length" class="overflow-y-auto text-xs max-h-40">
                            <template x-for="(hasil, indeks) in tidakPastiList" x-bind:key="'tp' + indeks">
                                <div class="py-0.5 text-amber-700 dark:text-amber-400">
                                    <span class="font-mono" x-text="hasil.rjNo"></span> ·
                                    <span x-text="hasil.nama"></span> — <span x-text="hasil.pesan"></span>
                                </div>
                            </template>
                            <template x-for="(hasil, indeks) in gagalList" x-bind:key="'g' + indeks">
                                <div class="py-0.5 text-red-700 dark:text-red-400">
                                    <span class="font-mono" x-text="hasil.rjNo"></span> ·
                                    <span x-text="hasil.nama"></span> — <span x-text="hasil.pesan"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TABEL --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="px-6 py-2 text-xs border-b text-muted border-hairline dark:border-gray-700 dark:text-gray-400">
                    {{ number_format($this->jumlahKunjungan, 0, ',', '.') }} kunjungan RJ Selesai pada {{ $filterBulan }}
                    · ICD hijau = terkirim, merah = gagal (dikirim ulang oleh bulk), kuning = tidak pasti (cek portal dulu, kirim ulang manual)
                </div>
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                            <tr class="text-sm font-semibold tracking-wide text-left uppercase text-muted dark:text-gray-300">
                                <th class="px-6 py-3">Pasien</th>
                                <th class="px-6 py-3">Kunjungan</th>
                                <th class="px-6 py-3">Data DINKESTA</th>
                                <th class="px-6 py-3">Status Kirim</th>
                                <th class="px-6 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline dark:divide-gray-700">
                            @forelse ($this->rows as $row)
                                <tr class="transition hover:bg-surface-soft dark:hover:bg-gray-800/50" wire:key="kirim-dinkesta-rj-{{ $row->rj_no }}">
                                    <td class="px-6 py-4 align-top">
                                        <x-list.identitas-pasien :regNo="$row->reg_no" :nama="$row->reg_name" :sex="$row->sex"
                                            :tglLahir="$row->birth_date" :alamat="$row->address" :collapseUmur="false" />
                                    </td>
                                    <td class="px-6 py-4 space-y-1 align-top">
                                        <div class="text-sm text-body dark:text-gray-400">{{ $row->rj_date_display }}</div>
                                        <div class="text-sm font-semibold text-brand dark:text-emerald-400">{{ $row->poli_desc ?? '-' }}</div>
                                        <div class="text-sm text-muted dark:text-gray-400">{{ $row->dr_name ?? '-' }}</div>
                                        <x-list.klaim-badge :status="$row->klaim_status" :desc="$row->klaim_desc" :id="$row->klaim_id" />
                                    </td>
                                    <td class="px-6 py-4 space-y-1 text-xs align-top text-muted dark:text-gray-400">
                                        <div>NIK: <span class="font-mono text-ink dark:text-gray-200">{{ $row->nik_bpjs ?: '-' }}</span></div>
                                        <div>Desa: {{ $row->des_name ?: '-' }} · Kec: {{ $row->kec_name ?: '-' }}</div>
                                        <div class="flex flex-wrap items-center gap-1">
                                            <span>ICD-10:</span>
                                            @forelse ($row->status_icd as $icd => $statusIcd)
                                                <x-badge :variant="['S' => 'success', 'G' => 'danger', 'T' => 'warning', 'P' => 'warning'][$statusIcd] ?? 'gray'">{{ $icd }}</x-badge>
                                            @empty
                                                <span>-</span>
                                            @endforelse
                                        </div>
                                        @if (!empty($row->kekurangan))
                                            <x-badge variant="danger">Kosong: {{ implode(', ', $row->kekurangan) }}</x-badge>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 space-y-1 align-top">
                                        @switch($row->status_kirim)
                                            @case('lama')
                                                <x-badge variant="success">Terkirim (aplikasi lama)</x-badge>
                                                @break
                                            @case('terkirim')
                                                <x-badge variant="success">Terkirim</x-badge>
                                                @break
                                            @case('sebagian')
                                                <x-badge variant="warning">Sebagian</x-badge>
                                                @break
                                            @case('tidak_pasti')
                                                <x-badge variant="warning">Tidak pasti — cek portal</x-badge>
                                                @break
                                            @case('proses')
                                                <x-badge variant="info">Sedang dikirim</x-badge>
                                                @break
                                            @case('gagal')
                                                <x-badge variant="danger">Gagal</x-badge>
                                                @break
                                            @default
                                                <x-badge variant="gray">Belum</x-badge>
                                        @endswitch
                                        @if ($row->info_kirim !== '')
                                            <div class="text-xs text-muted dark:text-gray-400">{{ $row->info_kirim }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-center align-top">
                                        @hasanyrole('Admin|Casemix|Tu')
                                            @if ($this->tabelKirimTersedia && in_array($row->status_kirim, ['belum', 'gagal', 'sebagian'], true) && !empty($row->status_icd))
                                                <x-outline-button type="button" wire:click="kirimSatu({{ $row->rj_no }})"
                                                    wire:loading.attr="disabled" wire:target="kirimSatu" class="whitespace-nowrap">
                                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                                                    </svg>
                                                    <span wire:loading.remove wire:target="kirimSatu">Kirim</span>
                                                    <span wire:loading wire:target="kirimSatu">Mengirim…</span>
                                                </x-outline-button>
                                            @elseif ($this->tabelKirimTersedia && $row->status_kirim === 'tidak_pasti')
                                                <x-confirm-button variant="warning-soft" :action="'kirimSatu(' . $row->rj_no . ', true)'"
                                                    title="Kirim Ulang ke DINKESTA"
                                                    message="Status ICD kuning tidak pasti: data mungkin SUDAH masuk portal DINKESTA. Kirim ulang hanya bila sudah dicek di portal dan memang belum ada."
                                                    confirmText="Ya, kirim ulang" cancelText="Batal">
                                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.993m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                                    </svg>
                                                    Kirim Ulang
                                                </x-confirm-button>
                                            @endif
                                        @endhasanyrole
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-16 text-center text-muted dark:text-gray-400">Belum ada kunjungan RJ Selesai pada bulan ini</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

        </div>
    </div>
</div>
