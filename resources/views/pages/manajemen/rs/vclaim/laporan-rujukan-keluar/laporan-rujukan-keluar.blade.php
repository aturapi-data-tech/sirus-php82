<?php
// resources/views/pages/manajemen/rs/vclaim/laporan-rujukan-keluar/laporan-rujukan-keluar.blade.php
//
// Evaluasi Rujukan Keluar RS — sumber data BPJS VClaim, dilengkapi data kita sendiri.
//
// Kenapa dua sumber:
//   BPJS  Rujukan/Keluar/List  -> daftar rujukan (noRujukan, tgl, noSep, pasien, faskes tujuan)
//   BPJS  Rujukan/Keluar/{no}  -> DIAGNOSA rujukan & POLI TUJUAN (tidak ada di daftar)
//   Kita  rstxn_rjhdrs/rihdrs  -> POLI PERUJUK & DOKTER PERUJUK (tidak ada di BPJS sama sekali)
// Jembatannya kolom vno_sep: noSep dari BPJS = vno_sep di tabel kunjungan kita.
//
// Detail diambil BERTAHAP, bukan sekaligus: satu periode bisa ratusan rujukan
// (Juli 2026 = 279) dan tiap detail = satu panggilan HTTP ke BPJS. Sekali jalan
// akan kena timeout PHP jauh sebelum selesai. Hasil detail di-cache 30 hari
// karena isinya tidak berubah setelah rujukan terbit.

use Livewire\Component;
use Livewire\Attributes\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\BPJS\VclaimTrait;

new class extends Component {
    /** Berapa detail yang ditarik per klik "Lengkapi Detail". */
    private const DETAIL_PER_BATCH = 25;

    private const CACHE_HARI = 30;

    #[Session(key: 'lapRujukanKeluar.tglMulai')]
    public string $tglMulai = '';

    #[Session(key: 'lapRujukanKeluar.tglAkhir')]
    public string $tglAkhir = '';

    public string $tab = 'rinci'; // rinci | poli | dokter | faskes | diagnosa

    public string $cariKeyword = '';
    public string $filterJenis = '';   // '' | 1 (ranap) | 2 (rajal)

    /** Baris hasil gabungan BPJS + data kita. */
    public array $rujukanList = [];

    public string $infoTarik = '';
    public bool $sudahTarik = false;

    public function mount(): void
    {
        $hariIni = Carbon::now(config('app.timezone'));
        if ($this->tglMulai === '') {
            $this->tglMulai = $hariIni->copy()->startOfMonth()->format('d/m/Y');
        }
        if ($this->tglAkhir === '') {
            $this->tglAkhir = $hariIni->format('d/m/Y');
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['rinci', 'perujuk', 'faskes', 'diagnosa'], true) ? $tab : 'rinci';
    }

    /** Dipakai x-toolbar-refresh-reset. Periode dikembalikan ke bulan berjalan. */
    public function resetFilters(): void
    {
        $hariIni = Carbon::now(config('app.timezone'));
        $this->tglMulai = $hariIni->copy()->startOfMonth()->format('d/m/Y');
        $this->tglAkhir = $hariIni->format('d/m/Y');
        $this->cariKeyword = '';
        $this->filterJenis = '';
        $this->tab = 'rinci';
        $this->rujukanList = [];
        $this->sudahTarik = false;
        $this->infoTarik = '';
    }

    /* ===============================
     | LANGKAH 1 — TARIK DAFTAR
     =============================== */
    public function tarikDaftar(): void
    {
        $mulai = $this->keTanggalBpjs($this->tglMulai);
        $akhir = $this->keTanggalBpjs($this->tglAkhir);
        if (!$mulai || !$akhir) {
            $this->dispatch('toast', type: 'error', message: 'Tanggal harus dd/mm/yyyy.');
            return;
        }
        if ($mulai > $akhir) {
            $this->dispatch('toast', type: 'error', message: 'Tanggal mulai melewati tanggal akhir.');
            return;
        }

        try {
            $respon = VclaimTrait::rujukan_keluar_list_rs($mulai, $akhir)->getOriginalContent();
        } catch (\Throwable $e) {
            $this->rujukanList = [];
            $this->infoTarik = 'Gagal menghubungi BPJS: ' . $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $this->infoTarik);
            return;
        }

        if ((string) ($respon['metadata']['code'] ?? '') !== '200') {
            $this->rujukanList = [];
            $this->infoTarik = 'BPJS menolak: ' . ($respon['metadata']['message'] ?? 'tanpa keterangan');
            $this->dispatch('toast', type: 'error', message: $this->infoTarik);
            return;
        }

        $daftar = collect($respon['response']['list'] ?? [])
            ->map(fn($item) => [
                'noRujukan'      => (string) ($item['noRujukan'] ?? ''),
                'tglRujukan'     => $this->keTampilTanggal((string) ($item['tglRujukan'] ?? '')),
                'jnsPelayanan'   => (string) ($item['jnsPelayanan'] ?? ''),
                'noSep'          => (string) ($item['noSep'] ?? ''),
                'noKartu'        => (string) ($item['noKartu'] ?? ''),
                'nama'           => (string) ($item['nama'] ?? ''),
                'ppkDirujuk'     => (string) ($item['ppkDirujuk'] ?? ''),
                'namaPpkDirujuk' => (string) ($item['namaPpkDirujuk'] ?? ''),
                // Diisi lengkapiDetail()
                'diagRujukan'     => null,
                'namaDiagRujukan' => null,
                'poliRujukan'     => null,
                'namaPoliRujukan' => null,
                'tipeRujukan'     => null,
                'namaTipeRujukan' => null,
                'catatan'         => null,
                // Diisi lekatkanDataKita()
                'sumber'        => '',
                'noKunjungan'   => '',
                'poliPerujuk'   => '',
                'dokterPerujuk' => '',
            ])
            ->filter(fn($item) => $item['noRujukan'] !== '')
            ->values()
            ->all();

        $this->rujukanList = $this->lekatkanDataKita($daftar);
        $this->rujukanList = $this->ambilDetailTerCache($this->rujukanList);
        $this->sudahTarik = true;

        $tanpaPasangan = collect($this->rujukanList)->where('sumber', '')->count();
        $this->infoTarik = count($this->rujukanList) . ' rujukan keluar ditemukan.'
            . ($tanpaPasangan > 0 ? " {$tanpaPasangan} di antaranya belum ketemu kunjungannya di data kita (SEP tidak cocok)." : '');
    }

    /* ===============================
     | LANGKAH 2 — LENGKAPI DETAIL (bertahap)
     =============================== */
    public function lengkapiDetail(): void
    {
        $belum = collect($this->rujukanList)
            ->filter(fn($baris) => $baris['diagRujukan'] === null)
            ->take(self::DETAIL_PER_BATCH)
            ->keys()
            ->all();

        if (empty($belum)) {
            $this->dispatch('toast', type: 'info', message: 'Semua detail sudah lengkap.');
            return;
        }

        $berhasil = 0;
        $gagal = 0;
        foreach ($belum as $index) {
            $detail = $this->ambilDetail($this->rujukanList[$index]['noRujukan']);
            if ($detail === null) {
                $gagal++;
                continue;
            }
            $this->rujukanList[$index] = array_replace($this->rujukanList[$index], $detail);
            $berhasil++;
        }

        $sisa = collect($this->rujukanList)->filter(fn($baris) => $baris['diagRujukan'] === null)->count();
        $this->infoTarik = "Detail terisi {$berhasil} baris"
            . ($gagal > 0 ? ", {$gagal} gagal (BPJS tidak merespons — coba lagi)" : '')
            . ($sisa > 0 ? ", sisa {$sisa} baris." : ', semua lengkap.');
        $this->dispatch('toast', type: $gagal > 0 ? 'warning' : 'success', message: $this->infoTarik);
    }

    /** Detail rujukan tidak berubah setelah terbit → aman di-cache. */
    private function ambilDetail(string $noRujukan): ?array
    {
        $kunci = 'lapRujukanKeluar.detail.' . $noRujukan;
        $tersimpan = Cache::get($kunci);
        if (is_array($tersimpan)) {
            return $tersimpan;
        }

        try {
            $respon = VclaimTrait::rujukan_keluar_detail_by_no_rujukan($noRujukan)->getOriginalContent();
        } catch (\Throwable) {
            return null;
        }
        if ((string) ($respon['metadata']['code'] ?? '') !== '200') {
            return null;
        }

        $rujukan = $respon['response']['rujukan'] ?? [];
        $detail = [
            'diagRujukan'     => (string) ($rujukan['diagRujukan'] ?? ''),
            'namaDiagRujukan' => (string) ($rujukan['namaDiagRujukan'] ?? ''),
            'poliRujukan'     => (string) ($rujukan['poliRujukan'] ?? ''),
            'namaPoliRujukan' => (string) ($rujukan['namaPoliRujukan'] ?? ''),
            'tipeRujukan'     => (string) ($rujukan['tipeRujukan'] ?? ''),
            'namaTipeRujukan' => (string) ($rujukan['namaTipeRujukan'] ?? ''),
            'catatan'         => (string) ($rujukan['catatan'] ?? ''),
        ];

        Cache::put($kunci, $detail, now()->addDays(self::CACHE_HARI));
        return $detail;
    }

    /** Isi detail yang kebetulan sudah ada di cache — gratis, tanpa panggilan BPJS. */
    private function ambilDetailTerCache(array $daftar): array
    {
        foreach ($daftar as $index => $baris) {
            $tersimpan = Cache::get('lapRujukanKeluar.detail.' . $baris['noRujukan']);
            if (is_array($tersimpan)) {
                $daftar[$index] = array_replace($baris, $tersimpan);
            }
        }
        return $daftar;
    }

    /* ===============================
     | POLI & DOKTER PERUJUK — dari data kita
     =============================== */
    /**
     * BPJS tidak menyimpan poli/dokter perujuk sama sekali, dan node
     * rujukanAntarRS milik kita pun tidak mencatatnya. Satu-satunya jalan:
     * telusuri kunjungan asal lewat noSep (= kolom vno_sep di sisi kita),
     * lalu ambil dokter kunjungan tersebut sebagai dokter perujuk.
     *
     * TIGA tabel, bukan dua: UGD punya tabel sendiri (rstxn_ugdhdrs) yang
     * TIDAK termuat di rstxn_rjhdrs. Nomor rj_no antara keduanya bisa
     * bertabrakan, jadi pencocokan wajib lewat vno_sep — bukan rj_no.
     * RI tidak punya poli, yang relevan di sana DPJP; polinya diberi label
     * "(Rawat Inap)" agar tidak terbaca sebagai data hilang.
     */
    private function lekatkanDataKita(array $daftar): array
    {
        $sepList = collect($daftar)->pluck('noSep')
            ->filter(fn($sep) => trim((string) $sep) !== '')
            ->map(fn($sep) => strtoupper(trim($sep)))
            ->unique()->values();

        if ($sepList->isEmpty()) {
            return $daftar;
        }

        $petaRJ = [];
        $petaUGD = [];
        $petaRI = [];
        // Oracle membatasi IN pada 1000 nilai — potong per 500.
        foreach ($sepList->chunk(500) as $potongan) {
            $nilai = $potongan->all();

            DB::table('rstxn_rjhdrs as h')
                ->leftJoin('rsmst_polis as p', 'p.poli_id', '=', 'h.poli_id')
                ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
                ->whereIn(DB::raw('UPPER(TRIM(h.vno_sep))'), $nilai)
                ->get(['h.rj_no', 'h.vno_sep', 'p.poli_desc', 'd.dr_name'])
                ->each(function ($baris) use (&$petaRJ) {
                    $petaRJ[strtoupper(trim((string) $baris->vno_sep))] = [
                        'sumber'        => 'RJ',
                        'noKunjungan'   => (string) $baris->rj_no,
                        'poliPerujuk'   => (string) ($baris->poli_desc ?? ''),
                        'dokterPerujuk' => (string) ($baris->dr_name ?? ''),
                    ];
                });

            DB::table('rstxn_ugdhdrs as h')
                ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
                ->whereIn(DB::raw('UPPER(TRIM(h.vno_sep))'), $nilai)
                ->get(['h.rj_no', 'h.vno_sep', 'd.dr_name'])
                ->each(function ($baris) use (&$petaUGD) {
                    $petaUGD[strtoupper(trim((string) $baris->vno_sep))] = [
                        'sumber'        => 'UGD',
                        'noKunjungan'   => (string) $baris->rj_no,
                        'poliPerujuk'   => '(UGD)',
                        'dokterPerujuk' => (string) ($baris->dr_name ?? ''),
                    ];
                });

            DB::table('rstxn_rihdrs as h')
                ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
                ->whereIn(DB::raw('UPPER(TRIM(h.vno_sep))'), $nilai)
                ->get(['h.rihdr_no', 'h.vno_sep', 'd.dr_name'])
                ->each(function ($baris) use (&$petaRI) {
                    $petaRI[strtoupper(trim((string) $baris->vno_sep))] = [
                        'sumber'        => 'RI',
                        'noKunjungan'   => (string) $baris->rihdr_no,
                        'poliPerujuk'   => '(Rawat Inap)',
                        'dokterPerujuk' => (string) ($baris->dr_name ?? ''),
                    ];
                });
        }

        foreach ($daftar as $index => $baris) {
            $sep = strtoupper(trim($baris['noSep']));
            $cocok = $petaRJ[$sep] ?? ($petaUGD[$sep] ?? ($petaRI[$sep] ?? null));
            if ($cocok) {
                $daftar[$index] = array_replace($baris, $cocok);
            }
        }

        return $daftar;
    }

    /* ===============================
     | TAMPIL & REKAP
     =============================== */
    public function barisTampil(): array
    {
        $keyword = mb_strtolower(trim($this->cariKeyword));

        return collect($this->rujukanList)
            ->filter(fn($baris) => $this->filterJenis === '' || $baris['jnsPelayanan'] === $this->filterJenis)
            ->filter(function ($baris) use ($keyword) {
                if ($keyword === '') {
                    return true;
                }
                $gabungan = mb_strtolower(implode(' ', [
                    $baris['noRujukan'], $baris['noSep'], $baris['noKartu'], $baris['nama'],
                    $baris['namaPpkDirujuk'], $baris['poliPerujuk'], $baris['dokterPerujuk'],
                    (string) $baris['diagRujukan'], (string) $baris['namaDiagRujukan'],
                ]));
                return str_contains($gabungan, $keyword);
            })
            ->values()
            ->all();
    }

    /**
     * Poli perujuk BESERTA dokter yang merujuk dari poli itu — satu rangkaian,
     * bukan dua daftar terpisah. Yang dievaluasi memang pasangannya: seorang
     * dokter bisa praktik di lebih dari satu poli, jadi angka per dokter tanpa
     * konteks polinya menyesatkan.
     */
    public function rekapPoliDokter(): array
    {
        return collect($this->barisTampil())
            ->groupBy(fn($baris) => trim((string) $baris['poliPerujuk']) ?: '(SEP tak ketemu)')
            ->map(fn($grup, $namaPoli) => [
                'nama'   => $namaPoli,
                'jumlah' => $grup->count(),
                'dokter' => $grup
                    ->groupBy(fn($baris) => trim((string) $baris['dokterPerujuk']) ?: '(tidak diketahui)')
                    ->map(fn($sub, $namaDokter) => [
                        'nama'       => $namaDokter,
                        'jumlah'     => $sub->count(),
                        'faskesList' => $sub->pluck('namaPpkDirujuk')
                            ->map(fn($faskes) => trim((string) $faskes))
                            ->filter()->unique()->sort()->implode(', '),
                    ])
                    ->sortByDesc('jumlah')
                    ->values()
                    ->all(),
            ])
            ->sortByDesc('jumlah')
            ->values()
            ->all();
    }

    /** Label diagnosa = kode + nama, supaya tidak ambigu antar ICD yang mirip. */
    private function labelDiagnosa(array $baris): string
    {
        $kode = trim((string) ($baris['diagRujukan'] ?? ''));
        if ($kode === '') {
            return '(detail belum diambil)';
        }
        $nama = trim((string) ($baris['namaDiagRujukan'] ?? ''));
        return $nama !== '' ? "{$kode} — {$nama}" : $kode;
    }

    /** Rekap diagnosa memakai kode + nama sekaligus supaya tidak ambigu. */
    public function rekapDiagnosa(): array
    {
        return collect($this->barisTampil())
            ->groupBy(fn($baris) => $this->labelDiagnosa($baris))
            ->map(fn($grup, $nama) => ['nama' => $nama, 'jumlah' => $grup->count()])
            ->sortByDesc('jumlah')
            ->values()
            ->all();
    }

    /**
     * Faskes tujuan BESERTA diagnosa yang dirujuk ke sana — bukan dua daftar
     * terpisah. Yang dievaluasi memang pasangannya: RS mana menerima kasus apa,
     * karena itulah yang menunjukkan pola rujukan sesuai/tidak sesuai kompetensi.
     */
    public function rekapFaskesDiagnosa(): array
    {
        return collect($this->barisTampil())
            ->groupBy(fn($baris) => trim((string) $baris['namaPpkDirujuk']) ?: '(tanpa nama)')
            ->map(fn($grup, $namaFaskes) => [
                'nama'     => $namaFaskes,
                'kode'     => trim((string) ($grup->first()['ppkDirujuk'] ?? '')),
                'jumlah'   => $grup->count(),
                'diagnosa' => $grup
                    ->groupBy(fn($baris) => $this->labelDiagnosa($baris))
                    ->map(fn($sub, $labelDiagnosa) => [
                        'nama'      => $labelDiagnosa,
                        'jumlah'    => $sub->count(),
                        'poliList'  => $sub->pluck('namaPoliRujukan')
                            ->map(fn($poli) => trim((string) $poli))
                            ->filter()->unique()->sort()->implode(', '),
                    ])
                    ->sortByDesc('jumlah')
                    ->values()
                    ->all(),
            ])
            ->sortByDesc('jumlah')
            ->values()
            ->all();
    }

    public function ringkasan(): array
    {
        $baris = collect($this->barisTampil());
        return [
            'total'         => $baris->count(),
            'rajal'         => $baris->where('jnsPelayanan', '2')->count(),
            'ranap'         => $baris->where('jnsPelayanan', '1')->count(),
            'faskesTujuan'  => $baris->pluck('namaPpkDirujuk')->filter()->unique()->count(),
            'tanpaPasangan' => $baris->where('sumber', '')->count(),
            'detailBelum'   => $baris->filter(fn($b) => $b['diagRujukan'] === null)->count(),
        ];
    }

    /* ===============================
     | EKSPOR CSV
     =============================== */
    /**
     * Satu berkas berisi EMPAT bagian: rincian + tiga rekap yang sama persis
     * dengan yang tampil di layar. Dipisah baris kosong + baris judul, bukan
     * berkas terpisah — supaya sekali unduh langsung siap dilampirkan ke laporan
     * evaluasi tanpa harus menggabungkan sendiri.
     */
    public function eksporCsv()
    {
        $baris = $this->barisTampil();
        if (empty($baris)) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada data untuk diekspor.');
            return null;
        }

        $perujuk = $this->rekapPoliDokter();
        $faskes = $this->rekapFaskesDiagnosa();
        $diagnosa = $this->rekapDiagnosa();
        $ringkasan = $this->ringkasan();
        $periode = $this->tglMulai . ' s/d ' . $this->tglAkhir;

        $namaBerkas = 'rujukan-keluar-' . str_replace('/', '', $this->tglMulai)
            . '-sd-' . str_replace('/', '', $this->tglAkhir) . '.csv';

        return response()->streamDownload(function () use ($baris, $perujuk, $faskes, $diagnosa, $ringkasan, $periode) {
            $keluaran = fopen('php://output', 'w');
            // BOM UTF-8 — tanpa ini Excel membaca berkas sebagai ANSI dan nama
            // pasien beraksen jadi rusak.
            fwrite($keluaran, "\xEF\xBB\xBF");

            $tulis = fn(array $kolom) => fputcsv($keluaran, $kolom, ';');
            $kosong = fn() => fputcsv($keluaran, [], ';');
            // Desimal koma + ribuan titik: berkas ini dibaca di Excel berlokal Indonesia.
            $persen = fn($bagian, $penyebut) => $penyebut > 0 ? number_format($bagian / $penyebut * 100, 1, ',', '.') : '0,0';

            /* ── KEPALA ── */
            $tulis(['LAPORAN EVALUASI RUJUKAN KELUAR RS']);
            $tulis(['Periode', $periode]);
            $tulis(['Jenis pelayanan', $this->filterJenis === '1' ? 'Rawat Inap' : ($this->filterJenis === '2' ? 'Rawat Jalan' : 'Semua')]);
            if ($this->cariKeyword !== '') {
                $tulis(['Kata kunci', $this->cariKeyword]);
            }
            $tulis(['Total rujukan', $ringkasan['total']]);
            $tulis(['Rawat jalan / Rawat inap', $ringkasan['rajal'] . ' / ' . $ringkasan['ranap']]);
            $tulis(['Faskes tujuan', $ringkasan['faskesTujuan']]);
            $tulis(['SEP tak ketemu di data kita', $ringkasan['tanpaPasangan']]);
            $tulis(['Detail BPJS belum diambil', $ringkasan['detailBelum']]);
            $kosong();

            /* ── 1. RINCIAN ── */
            $tulis(['1. RINCIAN RUJUKAN']);
            $tulis([
                'No Rujukan', 'Tgl Rujukan', 'Jenis', 'No SEP', 'No Kartu', 'Nama Pasien',
                'Sumber', 'No Kunjungan', 'Poli Perujuk', 'Dokter Perujuk',
                'Kode Faskes Tujuan', 'Faskes Tujuan', 'Poli Tujuan',
                'Kode Diagnosa', 'Diagnosa Rujukan', 'Tipe Rujukan', 'Catatan',
            ]);
            foreach ($baris as $item) {
                $tulis([
                    $item['noRujukan'],
                    $item['tglRujukan'],
                    $item['jnsPelayanan'] === '1' ? 'Rawat Inap' : ($item['jnsPelayanan'] === '2' ? 'Rawat Jalan' : $item['jnsPelayanan']),
                    $item['noSep'],
                    $item['noKartu'],
                    $item['nama'],
                    $item['sumber'] ?: 'tidak ketemu',
                    $item['noKunjungan'],
                    $item['poliPerujuk'],
                    $item['dokterPerujuk'],
                    $item['ppkDirujuk'],
                    $item['namaPpkDirujuk'],
                    trim(($item['poliRujukan'] ?? '') . ' ' . ($item['namaPoliRujukan'] ?? '')),
                    $item['diagRujukan'] ?? '',
                    $item['namaDiagRujukan'] ?? '',
                    $item['namaTipeRujukan'] ?? '',
                    $item['catatan'] ?? '',
                ]);
            }
            $kosong();

            /* ── 2. REKAP POLI & DOKTER PERUJUK ── */
            $totalPerujuk = collect($perujuk)->sum('jumlah');
            $tulis(['2. REKAP POLI & DOKTER PERUJUK']);
            $tulis(['Poli Perujuk', 'Dokter Perujuk', 'Faskes Tujuan', 'Jumlah', '% thd Total', '% thd Poli']);
            foreach ($perujuk as $poli) {
                $tulis([$poli['nama'], '', '', $poli['jumlah'], $persen($poli['jumlah'], $totalPerujuk), '']);
                foreach ($poli['dokter'] as $dokter) {
                    $tulis(['', $dokter['nama'], $dokter['faskesList'], $dokter['jumlah'], '', $persen($dokter['jumlah'], $poli['jumlah'])]);
                }
            }
            $tulis(['TOTAL', '', '', $totalPerujuk, '100,0', '']);
            $kosong();

            /* ── 3. REKAP FASKES TUJUAN & DIAGNOSA ── */
            $totalFaskes = collect($faskes)->sum('jumlah');
            $tulis(['3. REKAP FASKES TUJUAN & DIAGNOSA']);
            $tulis(['Faskes Tujuan', 'Kode PPK', 'Diagnosa Rujukan', 'Poli Tujuan', 'Jumlah', '% thd Total', '% thd Faskes']);
            foreach ($faskes as $satuFaskes) {
                $tulis([$satuFaskes['nama'], $satuFaskes['kode'], '', '', $satuFaskes['jumlah'], $persen($satuFaskes['jumlah'], $totalFaskes), '']);
                foreach ($satuFaskes['diagnosa'] as $satuDiagnosa) {
                    $tulis(['', '', $satuDiagnosa['nama'], $satuDiagnosa['poliList'], $satuDiagnosa['jumlah'], '', $persen($satuDiagnosa['jumlah'], $satuFaskes['jumlah'])]);
                }
            }
            $tulis(['TOTAL', '', '', '', $totalFaskes, '100,0', '']);
            $kosong();

            /* ── 4. REKAP DIAGNOSA (lintas faskes) ── */
            $totalDiagnosa = collect($diagnosa)->sum('jumlah');
            $tulis(['4. REKAP DIAGNOSA RUJUKAN (lintas faskes)']);
            $tulis(['Diagnosa Rujukan', 'Jumlah', '% thd Total']);
            foreach ($diagnosa as $item) {
                $tulis([$item['nama'], $item['jumlah'], $persen($item['jumlah'], $totalDiagnosa)]);
            }
            $tulis(['TOTAL', $totalDiagnosa, '100,0']);

            fclose($keluaran);
        }, $namaBerkas, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* ===============================
     | HELPER TANGGAL
     =============================== */
    /** dd/mm/yyyy (input kita) -> yyyy-MM-dd (yang diminta BPJS). */
    private function keTanggalBpjs(string $tanggal): ?string
    {
        try {
            return Carbon::createFromFormat('d/m/Y', trim($tanggal))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Daftar mengirim dd-mm-yyyy sedangkan detail mengirim yyyy-MM-dd
     * (terpantau 12/08/2026) — terima keduanya, keluarkan dd/mm/yyyy.
     */
    private function keTampilTanggal(string $tanggal): string
    {
        $tanggal = trim($tanggal);
        foreach (['d-m-Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $tanggal)->format('d/m/Y');
            } catch (\Throwable) {
                // coba format berikutnya
            }
        }
        return $tanggal;
    }
};
?>

@php
    // Hanya pemetaan display ringan — perhitungan ada di class
    // (skill naming-conventions §2: logika jangan ditaruh di @php template).
    $ringkasan = $sudahTarik ? $this->ringkasan() : [];
    $barisList = $sudahTarik ? $this->barisTampil() : [];
@endphp

<div>
    <x-page-title title="Evaluasi Rujukan Keluar RS"
        subtitle="Rujukan keluar (VClaim) — poli & dokter perujuk, faskes tujuan, dan diagnosa rujukan" />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-canvas dark:bg-gray-800">
        <div class="px-6 pt-4 pb-8 space-y-6">

            {{-- ── FILTER ── --}}
            {{-- Dua tanggal disatukan sebagai "Periode": keduanya satu konsep dan
                 selalu diisi berpasangan, jadi tak perlu dua label terpisah. --}}
            {{-- lg:flex-nowrap + Cari yang boleh menyusut (min-w-0): sesudah "Ambil Data"
                 muncul dua tombol tambahan, dan tanpa ini barisnya pecah ke baris kedua.
                 Di bawah lg tetap boleh wrap — layar sempit memang tak muat. --}}
            <div class="flex flex-wrap items-end gap-2.5 pb-2 lg:flex-nowrap">
                <div class="shrink-0">
                    <x-input-label value="Periode" />
                    {{-- Lebar dipasang di DIV pembungkus, bukan di <x-text-input>.
                         Komponen itu sudah membawa `w-full` di kelas dasarnya, dan di CSS
                         hasil build `.w-full` berada SETELAH `.w-36`, jadi lebar yang
                         ditempel lewat atribut selalu kalah dan kotaknya memuai.
                         Kedua kotak kini simetris: sama-sama ber-ikon, sama-sama pl-9,
                         sama-sama w-40 (pl-9 memakan 36px, sisanya pas untuk dd/mm/yyyy). --}}
                    <div class="flex items-center gap-1.5 mt-1">
                        @foreach ([['tglMulai', 'Tanggal mulai'], ['tglAkhir', 'Tanggal akhir']] as $nomorKolom => [$modelTanggal, $judulKolom])
                            @if ($nomorKolom > 0)
                                <span class="text-muted dark:text-gray-400">&ndash;</span>
                            @endif
                            <div class="relative w-40 shrink-0">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-2.5 pointer-events-none">
                                    <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <x-text-input type="text" wire:model="{{ $modelTanggal }}" class="block pl-9 font-mono"
                                    placeholder="dd/mm/yyyy" maxlength="10" :title="$judulKolom" />
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="shrink-0">
                    <x-input-label value="Jenis" />
                    <x-select-input wire:model.live="filterJenis" class="mt-1 w-36" title="Jenis pelayanan">
                        <option value="">Semua jenis</option>
                        <option value="2">Rawat Jalan</option>
                        <option value="1">Rawat Inap</option>
                    </x-select-input>
                </div>
                <div class="flex-1 min-w-0">
                    <x-input-label value="Cari" />
                    <x-text-input type="text" wire:model.live.debounce.300ms="cariKeyword" class="w-full mt-1"
                        placeholder="Nama, SEP, faskes, poli, dokter, diagnosa..." />
                </div>

                {{-- Aksi dikelompokkan di kanan supaya tidak berselang-seling dengan isian --}}
                <div class="flex items-end gap-2 ml-auto shrink-0">
                    <x-primary-button type="button" wire:click="tarikDaftar" wire:loading.attr="disabled"
                        wire:target="tarikDaftar" title="Tarik daftar rujukan keluar dari BPJS VClaim">
                        {{-- Ikon awan-unduh: menegaskan datanya ditarik dari luar (BPJS),
                             bukan dibaca dari database kita. --}}
                        <span wire:loading.remove wire:target="tarikDaftar" class="inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6H16a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                            </svg>
                            Ambil Data
                        </span>
                        <span wire:loading wire:target="tarikDaftar" class="inline-flex items-center gap-1.5">
                            <x-loading /> Mengambil...
                        </span>
                    </x-primary-button>

                    @if (($ringkasan['detailBelum'] ?? 0) > 0)
                        {{-- Diagnosa & poli tujuan hanya ada di endpoint detail BPJS (1 panggilan
                             per rujukan), jadi ditarik bertahap. Penjelasan panjang cukup di title
                             — dulu memakan satu spanduk penuh di badan halaman. --}}
                        <x-warning-button type="button" wire:click="lengkapiDetail" wire:loading.attr="disabled"
                            wire:target="lengkapiDetail"
                            title="{{ $ringkasan['detailBelum'] }} baris belum punya Diagnosa & Poli Tujuan. Keduanya hanya ada di endpoint detail BPJS — satu panggilan per rujukan, jadi diambil bertahap 25 baris tiap klik. Hasilnya disimpan 30 hari, penarikan berikutnya langsung terisi.">
                            <span wire:loading.remove wire:target="lengkapiDetail" class="inline-flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Detail ({{ number_format($ringkasan['detailBelum']) }})
                            </span>
                            <span wire:loading wire:target="lengkapiDetail" class="inline-flex items-center gap-1.5">
                                <x-loading /> Detail...
                            </span>
                        </x-warning-button>
                    @endif

                    @if ($sudahTarik)
                        <x-secondary-button type="button" wire:click="eksporCsv" wire:loading.attr="disabled"
                            wire:target="eksporCsv" title="Unduh CSV — rincian + rekap Poli &amp; Dokter Perujuk, Faskes Tujuan &amp; Diagnosa, dan Diagnosa, mengikuti filter yang sedang aktif">
                            <span wire:loading.remove wire:target="eksporCsv" class="inline-flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                CSV
                            </span>
                            <span wire:loading wire:target="eksporCsv" class="inline-flex items-center gap-1.5">
                                <x-loading /> CSV...
                            </span>
                        </x-secondary-button>
                    @endif

                    <x-toolbar-refresh-reset :label="null" />
                </div>
            </div>

            {{-- PANDUAN — gaya biru-info standar, default TERTUTUP.
                 Lihat memory project_panduan_panel_blue_info_standard.
                 Ditaruh di luar cabang $sudahTarik supaya penjelasannya tetap bisa
                 dibuka setelah data tampil, tanpa menyita ruang tabel. --}}
            <div x-data="{ buka: false }"
                class="overflow-hidden border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
                <button type="button" x-on:click="buka = !buka"
                    class="flex items-center justify-between w-full px-4 py-2.5 text-sm font-semibold text-blue-900 transition-colors hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30">
                    <span class="flex items-center min-w-0 gap-2">
                        <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="truncate">Panduan: dari mana angka-angka di laporan ini berasal</span>
                    </span>
                    <svg class="w-4 h-4 ml-2 text-blue-600 transition-transform shrink-0" x-bind:class="buka && 'rotate-180'"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="buka" x-cloak class="px-4 pb-4 space-y-3 text-sm text-blue-900 dark:text-blue-100">
                    <p>
                        Tentukan periode lalu tekan <strong>Ambil Data</strong>. Daftar rujukan ditarik langsung
                        dari BPJS VClaim, lalu dijodohkan dengan kunjungan RJ / UGD / RI kita lewat nomor SEP.
                    </p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-blue-800 dark:text-blue-200">
                                <tr>
                                    <th class="py-1 pr-3 font-semibold">Kolom</th>
                                    <th class="py-1 pr-3 font-semibold">Sumber</th>
                                    <th class="py-1 font-semibold">Catatan</th>
                                </tr>
                            </thead>
                            <tbody class="align-top">
                                <tr>
                                    <td class="py-1 pr-3">Rujukan, Pasien, Faskes Tujuan</td>
                                    <td class="py-1 pr-3">BPJS <span class="font-mono text-xs">Rujukan/Keluar/List</span></td>
                                    <td class="py-1">Satu panggilan untuk seluruh periode.</td>
                                </tr>
                                <tr>
                                    <td class="py-1 pr-3">Diagnosa Rujukan &amp; Poli Tujuan</td>
                                    <td class="py-1 pr-3">BPJS <span class="font-mono text-xs">Rujukan/Keluar/{noRujukan}</span></td>
                                    <td class="py-1">Satu panggilan <em>per rujukan</em> — karena itu diambil bertahap 25 baris
                                        lewat tombol <strong>Lengkapi Detail</strong>. Hasilnya disimpan 30 hari.</td>
                                </tr>
                                <tr>
                                    <td class="py-1 pr-3">Poli &amp; Dokter Perujuk</td>
                                    <td class="py-1 pr-3">Data kita (RJ / UGD / RI)</td>
                                    <td class="py-1">BPJS tidak menyimpannya sama sekali. Ditelusuri lewat nomor SEP
                                        (kolom <span class="font-mono text-xs">vno_sep</span>), lalu diambil dokter kunjungan
                                        tersebut. RI memakai DPJP.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p>
                        Baris bertanda <strong>SEP tak ketemu</strong> berarti nomor SEP-nya tidak ada di data kunjungan kita,
                        sehingga poli &amp; dokter perujuk tidak bisa ditentukan — rujukannya sendiri tetap sah di BPJS.
                    </p>
                </div>
            </div>

            @if (!$sudahTarik)
                <div class="p-8 text-center bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <svg class="w-10 h-10 mx-auto text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 17v-6h13M9 17H4a1 1 0 01-1-1V6a1 1 0 011-1h5m0 12v-6m0 0V5m0 6h13M22 11V6a1 1 0 00-1-1h-8" />
                    </svg>
                    <p class="mt-3 text-sm text-muted dark:text-gray-400">
                        Belum ada data. Tentukan periode lalu tekan <strong>Ambil Data</strong>.
                    </p>
                    @if ($infoTarik !== '')
                        <p class="mt-3 text-sm text-error-deep dark:text-red-300">{{ $infoTarik }}</p>
                    @endif
                </div>
            @else

                {{-- ── KARTU RINGKAS — collapsible, default tutup ── --}}
                <div class="bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900"
                    x-data="{ open: false }">

                    <button type="button" @click="open = !open"
                        class="flex items-center w-full gap-3 px-4 py-3 text-left transition-colors rounded-2xl
                               hover:bg-surface-soft dark:hover:bg-gray-800
                               focus:outline-none focus:ring-1 focus:ring-gray-300">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold text-body dark:text-gray-200">
                                Ringkasan Rujukan Keluar {{ $tglMulai }} &ndash; {{ $tglAkhir }}
                            </div>
                            <div class="text-xs text-muted dark:text-gray-400">
                                {{ number_format($ringkasan['total'] ?? 0) }} rujukan
                                · {{ number_format($ringkasan['faskesTujuan'] ?? 0) }} faskes tujuan
                                @if (($ringkasan['tanpaPasangan'] ?? 0) > 0)
                                    · {{ number_format($ringkasan['tanpaPasangan']) }} SEP tak ketemu
                                @endif
                            </div>
                        </div>
                        <span class="hidden text-xs sm:inline text-muted dark:text-gray-400">
                            <span x-text="open ? 'Sembunyikan' : 'Lihat detail'"></span>
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200 text-muted-soft shrink-0"
                            :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-cloak x-show="open"
                        class="px-4 pb-4 border-t border-hairline dark:border-gray-700"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0">
                        <div class="grid grid-cols-1 gap-3 mt-3 sm:grid-cols-3 lg:grid-cols-6">
                            @foreach ([
                                ['Total Rujukan', $ringkasan['total'] ?? 0, 'rujukan', 'border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700'],
                                ['Rawat Jalan', $ringkasan['rajal'] ?? 0, 'rujukan', 'border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700'],
                                ['Rawat Inap', $ringkasan['ranap'] ?? 0, 'rujukan', 'border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700'],
                                ['Faskes Tujuan', $ringkasan['faskesTujuan'] ?? 0, 'RS', 'border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700'],
                                ['SEP Tak Ketemu', $ringkasan['tanpaPasangan'] ?? 0, 'rujukan', ($ringkasan['tanpaPasangan'] ?? 0) > 0 ? 'border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700' : 'border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700'],
                                ['Detail Belum Diambil', $ringkasan['detailBelum'] ?? 0, 'rujukan', ($ringkasan['detailBelum'] ?? 0) > 0 ? 'border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700' : 'border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700'],
                            ] as [$labelKartu, $nilaiKartu, $satuanKartu, $kelasKartu])
                                <div class="p-4 border rounded-xl {{ $kelasKartu }}">
                                    <div class="text-xs font-semibold uppercase text-muted dark:text-gray-300">{{ $labelKartu }}</div>
                                    <div class="mt-1 text-3xl font-bold text-ink dark:text-gray-100">
                                        {{ number_format($nilaiKartu) }}<span class="ml-1 text-base font-medium text-muted">{{ $satuanKartu }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if ($infoTarik !== '')
                            <p class="mt-3 text-xs text-muted dark:text-gray-400">{{ $infoTarik }}</p>
                        @endif
                    </div>
                </div>

                {{-- ── TAB ── --}}
                <x-tabs variant="underline">
                    <x-tab :active="$tab === 'rinci'" wire:click="setTab('rinci')">Rinci</x-tab>
                    <x-tab :active="$tab === 'perujuk'" wire:click="setTab('perujuk')">Poli &amp; Dokter Perujuk</x-tab>
                    <x-tab :active="$tab === 'faskes'" wire:click="setTab('faskes')">Faskes Tujuan &amp; Diagnosa</x-tab>
                    <x-tab :active="$tab === 'diagnosa'" wire:click="setTab('diagnosa')">Per Diagnosa</x-tab>
                </x-tabs>

                @if ($tab === 'rinci')
                    <div class="bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
                            <h3 class="text-sm font-semibold tracking-wider uppercase text-muted dark:text-gray-400">
                                Rincian Rujukan &mdash; {{ number_format(count($barisList)) }} baris
                            </h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-surface-soft dark:bg-gray-800 text-muted dark:text-gray-400">
                                    <tr>
                                        <th class="px-3 py-2 text-left border-b border-hairline dark:border-gray-700">Rujukan</th>
                                        <th class="px-3 py-2 text-left border-b border-hairline dark:border-gray-700">Pasien</th>
                                        <th class="px-3 py-2 text-left border-b border-l border-hairline dark:border-gray-700">Perujuk (data kita)</th>
                                        <th class="px-3 py-2 text-left border-b border-l border-hairline dark:border-gray-700">Tujuan</th>
                                        <th class="px-3 py-2 text-left border-b border-l border-hairline dark:border-gray-700">Diagnosa Rujukan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-hairline-soft dark:divide-gray-700">
                                    @forelse ($barisList as $baris)
                                        <tr class="hover:bg-surface-soft dark:hover:bg-gray-800/50" wire:key="rjk-{{ $baris['noRujukan'] }}">
                                            <td class="px-3 py-2 align-top">
                                                <span class="font-mono text-xs font-semibold text-ink dark:text-gray-100">{{ $baris['noRujukan'] }}</span>
                                                <span class="block text-xs text-muted dark:text-gray-400">
                                                    {{ $baris['tglRujukan'] }} ·
                                                    {{ $baris['jnsPelayanan'] === '1' ? 'Ranap' : ($baris['jnsPelayanan'] === '2' ? 'Rajal' : $baris['jnsPelayanan']) }}
                                                </span>
                                                @if (filled($baris['namaTipeRujukan']))
                                                    <x-badge variant="gray" class="mt-1">{{ $baris['namaTipeRujukan'] }}</x-badge>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 align-top">
                                                <span class="font-medium text-ink dark:text-gray-100">{{ $baris['nama'] ?: '—' }}</span>
                                                <span class="block font-mono text-xs text-muted dark:text-gray-400">{{ $baris['noKartu'] }}</span>
                                                <span class="block font-mono text-xs text-muted dark:text-gray-400">SEP {{ $baris['noSep'] }}</span>
                                            </td>
                                            <td class="px-3 py-2 align-top border-l border-hairline dark:border-gray-700">
                                                @if ($baris['sumber'] === '')
                                                    <x-badge variant="warning">SEP tak ketemu</x-badge>
                                                    <span class="block mt-1 text-xs text-muted dark:text-gray-400">Poli &amp; dokter perujuk tak dapat ditentukan</span>
                                                @else
                                                    <span class="text-body dark:text-gray-300">{{ $baris['poliPerujuk'] ?: '—' }}</span>
                                                    <span class="block text-xs font-medium text-ink dark:text-gray-100">{{ $baris['dokterPerujuk'] ?: '—' }}</span>
                                                    <span class="block text-xs text-muted dark:text-gray-400">{{ $baris['sumber'] }} {{ $baris['noKunjungan'] }}</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 align-top border-l border-hairline dark:border-gray-700">
                                                <span class="text-body dark:text-gray-300">{{ $baris['namaPpkDirujuk'] ?: '—' }}</span>
                                                <span class="block font-mono text-xs text-muted dark:text-gray-400">{{ $baris['ppkDirujuk'] }}</span>
                                                @if (filled($baris['namaPoliRujukan']))
                                                    <span class="block text-xs text-muted dark:text-gray-400">Poli: {{ $baris['namaPoliRujukan'] }} ({{ $baris['poliRujukan'] }})</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 align-top border-l border-hairline dark:border-gray-700">
                                                @if ($baris['diagRujukan'] === null)
                                                    <span class="text-xs italic text-muted-soft">belum diambil</span>
                                                @else
                                                    <span class="font-mono font-semibold text-ink dark:text-gray-100">{{ $baris['diagRujukan'] ?: '—' }}</span>
                                                    <span class="block text-xs text-muted dark:text-gray-400">{{ $baris['namaDiagRujukan'] ?: '—' }}</span>
                                                    @if (filled($baris['catatan']))
                                                        <span class="block text-xs italic text-muted-soft">{{ $baris['catatan'] }}</span>
                                                    @endif
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-3 py-10 text-center text-muted-soft">Tidak ada rujukan pada periode / filter ini.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                @elseif ($tab === 'perujuk')
                    {{-- Poli perujuk sebagai induk, dokter yang merujuk dari poli itu sebagai anak --}}
                    @php
                        $dataPerujuk = $this->rekapPoliDokter();
                        $totalPerujuk = collect($dataPerujuk)->sum('jumlah');
                    @endphp
                    <div class="bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-hairline dark:border-gray-700">
                            <h3 class="text-sm font-semibold tracking-wider uppercase text-muted dark:text-gray-400">
                                Poli &amp; Dokter Perujuk
                            </h3>
                            <span class="text-xs text-muted dark:text-gray-400">
                                % baris poli terhadap seluruh rujukan · % baris dokter terhadap poli itu saja
                            </span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-surface-soft dark:bg-gray-800 text-muted dark:text-gray-400">
                                    <tr>
                                        <th class="px-3 py-2 text-left border-b border-hairline dark:border-gray-700">Poli Perujuk / Dokter Perujuk</th>
                                        <th class="px-3 py-2 text-left border-b border-l border-hairline dark:border-gray-700">Faskes Tujuan</th>
                                        <th class="w-24 px-3 py-2 text-center border-b border-l border-hairline dark:border-gray-700">Jumlah</th>
                                        <th class="w-24 px-3 py-2 text-center border-b border-hairline dark:border-gray-700">%</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-hairline-soft dark:divide-gray-700">
                                    @forelse ($dataPerujuk as $poli)
                                        <tr class="bg-surface-soft/60 dark:bg-gray-800/60" wire:key="poli-{{ $poli['nama'] }}">
                                            <td class="px-3 py-2 font-semibold text-ink dark:text-gray-100">{{ $poli['nama'] }}</td>
                                            <td class="px-3 py-2 border-l border-hairline dark:border-gray-700"></td>
                                            <td class="px-3 py-2 font-semibold text-center border-l border-hairline dark:border-gray-700 text-ink dark:text-gray-100">{{ number_format($poli['jumlah']) }}</td>
                                            <td class="px-3 py-2 font-semibold text-center text-ink dark:text-gray-100">
                                                {{ $totalPerujuk > 0 ? number_format($poli['jumlah'] / $totalPerujuk * 100, 1) : '0,0' }}
                                            </td>
                                        </tr>
                                        @foreach ($poli['dokter'] as $dokter)
                                            <tr class="hover:bg-surface-soft dark:hover:bg-gray-800/50">
                                                <td class="py-1.5 pl-8 pr-3 text-body dark:text-gray-300">{{ $dokter['nama'] }}</td>
                                                <td class="px-3 py-1.5 text-xs border-l border-hairline dark:border-gray-700 text-muted dark:text-gray-400">{{ $dokter['faskesList'] ?: '—' }}</td>
                                                <td class="px-3 py-1.5 text-center border-l border-hairline dark:border-gray-700 text-body dark:text-gray-300">{{ number_format($dokter['jumlah']) }}</td>
                                                <td class="px-3 py-1.5 text-center text-muted dark:text-gray-400">
                                                    {{ $poli['jumlah'] > 0 ? number_format($dokter['jumlah'] / $poli['jumlah'] * 100, 1) : '0,0' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-3 py-10 text-center text-muted-soft">Belum ada data.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if ($totalPerujuk > 0)
                                    <tfoot class="font-semibold bg-surface-soft dark:bg-gray-800">
                                        <tr>
                                            <td class="px-3 py-2 text-ink dark:text-gray-100" colspan="2">TOTAL RUJUKAN</td>
                                            <td class="px-3 py-2 text-center border-l border-hairline dark:border-gray-700 text-ink dark:text-gray-100">{{ number_format($totalPerujuk) }}</td>
                                            <td class="px-3 py-2 text-center text-ink dark:text-gray-100">100,0</td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>

                @elseif ($tab === 'faskes')
                    {{-- Faskes tujuan sebagai induk, diagnosa yang dirujuk ke sana sebagai anak --}}
                    @php
                        $dataFaskes = $this->rekapFaskesDiagnosa();
                        $totalFaskes = collect($dataFaskes)->sum('jumlah');
                    @endphp
                    <div class="bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-hairline dark:border-gray-700">
                            <h3 class="text-sm font-semibold tracking-wider uppercase text-muted dark:text-gray-400">
                                Faskes Tujuan &amp; Diagnosa yang Dirujuk
                            </h3>
                            <span class="text-xs text-muted dark:text-gray-400">
                                % baris faskes terhadap seluruh rujukan · % baris diagnosa terhadap faskes itu saja
                            </span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-surface-soft dark:bg-gray-800 text-muted dark:text-gray-400">
                                    <tr>
                                        <th class="px-3 py-2 text-left border-b border-hairline dark:border-gray-700">Faskes Tujuan / Diagnosa Rujukan</th>
                                        <th class="px-3 py-2 text-left border-b border-l border-hairline dark:border-gray-700">Poli Tujuan</th>
                                        <th class="w-24 px-3 py-2 text-center border-b border-l border-hairline dark:border-gray-700">Jumlah</th>
                                        <th class="w-24 px-3 py-2 text-center border-b border-hairline dark:border-gray-700">%</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-hairline-soft dark:divide-gray-700">
                                    @forelse ($dataFaskes as $faskes)
                                        <tr class="bg-surface-soft/60 dark:bg-gray-800/60"
                                            wire:key="faskes-{{ $faskes['kode'] ?: $faskes['nama'] }}">
                                            <td class="px-3 py-2 font-semibold text-ink dark:text-gray-100">
                                                {{ $faskes['nama'] }}
                                                @if (filled($faskes['kode']))
                                                    <span class="ml-1 font-mono text-xs font-normal text-muted dark:text-gray-400">{{ $faskes['kode'] }}</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 border-l border-hairline dark:border-gray-700"></td>
                                            <td class="px-3 py-2 font-semibold text-center border-l border-hairline dark:border-gray-700 text-ink dark:text-gray-100">{{ number_format($faskes['jumlah']) }}</td>
                                            <td class="px-3 py-2 font-semibold text-center text-ink dark:text-gray-100">
                                                {{ $totalFaskes > 0 ? number_format($faskes['jumlah'] / $totalFaskes * 100, 1) : '0,0' }}
                                            </td>
                                        </tr>
                                        @foreach ($faskes['diagnosa'] as $diagnosa)
                                            <tr class="hover:bg-surface-soft dark:hover:bg-gray-800/50">
                                                <td class="py-1.5 pl-8 pr-3 text-body dark:text-gray-300">{{ $diagnosa['nama'] }}</td>
                                                <td class="px-3 py-1.5 text-xs border-l border-hairline dark:border-gray-700 text-muted dark:text-gray-400">{{ $diagnosa['poliList'] ?: '—' }}</td>
                                                <td class="px-3 py-1.5 text-center border-l border-hairline dark:border-gray-700 text-body dark:text-gray-300">{{ number_format($diagnosa['jumlah']) }}</td>
                                                <td class="px-3 py-1.5 text-center text-muted dark:text-gray-400">
                                                    {{ $faskes['jumlah'] > 0 ? number_format($diagnosa['jumlah'] / $faskes['jumlah'] * 100, 1) : '0,0' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-3 py-10 text-center text-muted-soft">Belum ada data.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if ($totalFaskes > 0)
                                    <tfoot class="font-semibold bg-surface-soft dark:bg-gray-800">
                                        <tr>
                                            <td class="px-3 py-2 text-ink dark:text-gray-100" colspan="2">TOTAL RUJUKAN</td>
                                            <td class="px-3 py-2 text-center border-l border-hairline dark:border-gray-700 text-ink dark:text-gray-100">{{ number_format($totalFaskes) }}</td>
                                            <td class="px-3 py-2 text-center text-ink dark:text-gray-100">100,0</td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>

                @else
                    @php
                        $judulRekap = 'Diagnosa Rujukan';
                        $dataRekap = $this->rekapDiagnosa();
                        $totalRekap = collect($dataRekap)->sum('jumlah');
                    @endphp
                    <div class="bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
                            <h3 class="text-sm font-semibold tracking-wider uppercase text-muted dark:text-gray-400">
                                Rekap per {{ $judulRekap }} &mdash; {{ number_format(count($dataRekap)) }} kelompok
                            </h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-surface-soft dark:bg-gray-800 text-muted dark:text-gray-400">
                                    <tr>
                                        <th class="w-12 px-3 py-2 text-right border-b border-hairline dark:border-gray-700">#</th>
                                        <th class="px-3 py-2 text-left border-b border-hairline dark:border-gray-700">{{ $judulRekap }}</th>
                                        <th class="w-28 px-3 py-2 text-center border-b border-l border-hairline dark:border-gray-700">Jumlah</th>
                                        <th class="w-28 px-3 py-2 text-center border-b border-hairline dark:border-gray-700">%</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-hairline-soft dark:divide-gray-700">
                                    @forelse ($dataRekap as $nomor => $item)
                                        <tr class="hover:bg-surface-soft dark:hover:bg-gray-800/50">
                                            <td class="px-3 py-2 text-right text-muted">{{ $nomor + 1 }}</td>
                                            <td class="px-3 py-2 font-medium text-ink dark:text-gray-100">{{ $item['nama'] }}</td>
                                            <td class="px-3 py-2 text-center border-l border-hairline dark:border-gray-700 text-body dark:text-gray-300">{{ number_format($item['jumlah']) }}</td>
                                            <td class="px-3 py-2 font-semibold text-center text-body dark:text-gray-300">
                                                {{ $totalRekap > 0 ? number_format($item['jumlah'] / $totalRekap * 100, 1) : '0,0' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-3 py-10 text-center text-muted-soft">Belum ada data.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if ($totalRekap > 0)
                                    <tfoot class="font-semibold bg-surface-soft dark:bg-gray-800">
                                        <tr>
                                            <td class="px-3 py-2"></td>
                                            <td class="px-3 py-2 text-ink dark:text-gray-100">TOTAL</td>
                                            <td class="px-3 py-2 text-center border-l border-hairline dark:border-gray-700 text-ink dark:text-gray-100">{{ number_format($totalRekap) }}</td>
                                            <td class="px-3 py-2 text-center text-ink dark:text-gray-100">100,0</td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
