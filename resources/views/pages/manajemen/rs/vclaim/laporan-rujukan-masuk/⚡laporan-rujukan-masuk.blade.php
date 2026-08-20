<?php
// resources/views/pages/manajemen/rs/vclaim/laporan-rujukan-masuk/laporan-rujukan-masuk.blade.php
//
// Evaluasi Rujukan MASUK — faskes lain yang mengirim pasien ke RS kita.
//
// ── Kenapa laporan ini TIDAK memanggil BPJS sama sekali ──
// VClaim tidak punya endpoint "rujukan masuk per periode". Yang tersedia cuma
// Rujukan/Peserta/{noKartu} dan Rujukan/RS/Peserta/{noKartu} — per pasien, bukan
// per rentang tanggal. Menariknya lewat BPJS berarti satu panggilan HTTP per
// kunjungan, dan itu tidak perlu: faskes perujuk sudah ikut tersimpan waktu SEP
// dibuat, di node sep.reqSep.request.t_sep.rujukan pada JSON kunjungan kita.
// Jadi laporan ini justru lebih ringan daripada saudaranya (rujukan keluar):
// sekali klik, tanpa batch "Lengkapi Detail", tanpa cache.
//
// ── Kenapa HANYA Rawat Jalan ──
// Cuma jalur RJ yang benar-benar merekam faskes perujuk LUAR: LOV rujukan mengisi
// ppkRujukan/ppkRujukanNama dari provPerujuk milik BPJS. UGD mematok
// ppkRujukan = kode RS kita untuk SEMUA pasien (vclaim-ugd-actions: "UGD: asal
// rujukan selalu RS (fixed)"), dan RI mengunci kolom PPK Asal Rujukan sebagai
// read-only dengan nilai yang sama karena SEP RI berangkat dari SPRI kita sendiri.
// Menyertakan keduanya hanya akan melahirkan baris "dirujuk oleh diri sendiri".
//
// ── Kenapa DBMS_LOB.SUBSTR, bukan membaca CLOB utuh ──
// JSON kunjungan RJ memuat seluruh EMR; satu bulan bisa ribuan baris. Menarik
// CLOB penuh ke PHP hanya untuk enam field berarti memindahkan ratusan MB.
// Oracle di sini juga tidak mendukung JSON_VALUE (ORA-00904), jadi jalan tengahnya:
// potong JENDELA kecil di sisi DB tepat di sekitar node rujukan, lalu urai di PHP.
// Ini berbeda dari larangan di App\Support\OracleLob — yang dilarang di sana adalah
// mematerialkan SELURUH JSON ke VARCHAR2 (pasti terpotong di 32767 byte).
//
// ── Kenapa hasil tarik DISIMPAN DI CACHE, bukan di properti publik ──
// Hampir setiap kunjungan BPJS rawat jalan datang membawa rujukan FKTP, jadi satu
// bulan bisa ribuan baris. Properti publik ikut diserialisasi ke snapshot Livewire
// dan dikirim BOLAK-BALIK tiap request — sekali coba langsung kena
// PayloadTooLargeException (1273 KB, batas 1024 KB), dan sebelum mentok pun tiap
// ketikan di kotak Cari akan memindahkan data sebesar itu dua kali. Karena itu yang
// tinggal di komponen hanya PERIODE yang sudah ditarik (satu string), sedangkan
// barisnya diambil dari cache server. Konsekuensi yang disengaja: tabel Rinci
// dipaginasi, sementara rekap & CSV tetap memakai seluruh baris periode.

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    /* -------------------------
     | Konstanta
     * ------------------------- */
    /**
     * Kode PPK RS kita di BPJS. Baris yang perujuknya kode ini BUKAN rujukan masuk:
     * itu kunjungan internal, kontrol, atau SEP lanjutan pasca rawat inap.
     */
    private const PPK_KITA = '0184R006';

    /**
     * Lebar jendela CLOB di sekitar node rujukan. Isinya hanya enam field pendek
     * (~230 karakter); 1200 memberi ruang untuk urutan key yang berbeda di data lama.
     * Batas keras VARCHAR2 pada SQL adalah 4000 — lihat pola serupa di
     * display/antrian-apotek-rj yang memotong per 4000.
     */
    private const LEBAR_JENDELA_RUJUKAN = 1200;

    /** Jendela untuk diagAwal — nilainya kode ICD pendek, 80 karakter lebih dari cukup. */
    private const LEBAR_JENDELA_DIAGNOSA = 80;

    /**
     * Umur cache hasil tarik. Cukup panjang untuk sekali sesi analisa (pindah tab,
     * ganti filter, ganti halaman, unduh CSV), cukup pendek supaya laporan tidak
     * menyajikan angka basi tanpa disadari. Tombol "Ambil Data" selalu menimpa
     * cache, jadi menyegarkan data tidak perlu menunggu kedaluwarsa.
     */
    private const CACHE_HASIL_MENIT = 60;

    /* -------------------------
     | Filter state
     | Semuanya skalar. Baris hasil tarik TIDAK boleh jadi properti publik —
     | lihat catatan payload di kepala berkas.
     * ------------------------- */
    #[Session(key: 'lapRujukanMasuk.tglMulai')]
    public string $tglMulai = '';

    #[Session(key: 'lapRujukanMasuk.tglAkhir')]
    public string $tglAkhir = '';

    public string $tab = 'rinci'; // rinci | faskes | poli | diagnosa

    public string $cariKeyword = '';
    public string $filterAsal = '';   // '' | 1 (FKTP) | 2 (RS lain)
    public int $itemsPerPage = 25;

    /**
     * Periode yang datanya BENAR-BENAR sudah ditarik, format "Y-m-d|Y-m-d".
     * Sengaja dipisah dari $tglMulai/$tglAkhir: kotak tanggal boleh diubah tanpa
     * layar diam-diam menarik ulang, dan string ini sekaligus jadi kunci cache.
     */
    public string $periodeTarik = '';

    public string $infoTarik = '';

    /* -------------------------
     | Lifecycle & aksi layar
     * ------------------------- */
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
        $this->tab = in_array($tab, ['rinci', 'faskes', 'poli', 'diagnosa'], true) ? $tab : 'rinci';
    }

    public function updatedCariKeyword(): void
    {
        $this->resetPage();
    }

    public function updatedFilterAsal(): void
    {
        $this->resetPage();
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    /* -------------------------
     | Reset filters
     * ------------------------- */
    /** Dipakai x-toolbar-refresh-reset. Periode dikembalikan ke bulan berjalan. */
    public function resetFilters(): void
    {
        $this->reset(['cariKeyword', 'filterAsal', 'tab', 'periodeTarik', 'infoTarik']);
        $hariIni = Carbon::now(config('app.timezone'));
        $this->tglMulai = $hariIni->copy()->startOfMonth()->format('d/m/Y');
        $this->tglAkhir = $hariIni->format('d/m/Y');
        $this->resetPage();
        $this->lupakanRekap();
    }

    /**
     * Buang cache #[Computed]. Livewire menahan hasil computed selama satu request,
     * jadi setelah $periodeTarik berubah di request yang SAMA (tarikData / reset)
     * baris & rekap lama akan ikut terbawa ke render kalau tidak dilupakan dulu.
     */
    private function lupakanRekap(): void
    {
        unset(
            $this->rujukanList,
            $this->kedaluwarsa,
            $this->barisTerfilter,
            $this->rows,
            $this->ringkasan,
            $this->rekapFaskesPoli,
            $this->rekapPoliDokter,
            $this->rekapDiagnosa,
        );
    }

    /* -------------------------
     | Tarik data
     * ------------------------- */
    public function tarikData(): void
    {
        $mulai = $this->keTanggal($this->tglMulai);
        $akhir = $this->keTanggal($this->tglAkhir);
        if (!$mulai || !$akhir) {
            $this->dispatch('toast', type: 'error', message: 'Tanggal harus dd/mm/yyyy.');
            return;
        }

        // Dinormalkan SEBELUM dibandingkan: createFromFormat('d/m/Y') menyisakan jam
        // saat ini, jadi membandingkan mentah-mentah membaca sisa jam, bukan tanggal.
        $mulai = $mulai->startOfDay();
        $akhir = $akhir->endOfDay();
        if ($mulai->gt($akhir)) {
            $this->dispatch('toast', type: 'error', message: 'Tanggal mulai melewati tanggal akhir.');
            return;
        }

        $periode = $mulai->format('Y-m-d') . '|' . $akhir->format('Y-m-d');

        try {
            $daftar = $this->lengkapiNama($this->ambilBarisRujukan($mulai, $akhir));
        } catch (\Throwable $e) {
            $this->periodeTarik = '';
            $this->lupakanRekap();
            $this->infoTarik = 'Gagal membaca data kunjungan: ' . $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $this->infoTarik);
            return;
        }

        // Tombol ini artinya "baca ulang dari DB", jadi hasil lama ditimpa.
        $this->simpanHasil($periode, $daftar);

        $this->periodeTarik = $periode;
        $this->resetPage();
        $this->lupakanRekap();

        $jumlah = count($daftar);
        $faskes = collect($daftar)->pluck('kodeFaskes')->filter()->unique()->count();
        $this->infoTarik = $jumlah === 0
            ? 'Tidak ada rujukan masuk pada periode ini.'
            : "{$jumlah} rujukan masuk dari {$faskes} faskes perujuk.";
    }

    private function kunciHasil(string $periode): string
    {
        return 'lapRujukanMasuk.hasil.' . $periode;
    }

    private function simpanHasil(string $periode, array $daftar): void
    {
        Cache::put($this->kunciHasil($periode), $daftar, now()->addMinutes(self::CACHE_HASIL_MENIT));
    }

    /**
     * Satu query, satu tabel. Dua jendela CLOB dipotong di sisi Oracle:
     * jendela rujukan di-anchor ke "asalRujukan" (key PERTAMA di node rujukan,
     * jadi ikut menarik ppkRujukan/ppkRujukanNama yang ada sesudahnya), dan
     * jendela diagnosa di-anchor sendiri ke "diagAwal" — bukan disatukan, karena
     * di antara keduanya ada field catatan yang panjangnya bebas.
     *
     * INSTR nol (anchor tak ketemu — kunjungan umum tanpa SEP) dibuat jadi 1 lewat
     * GREATEST supaya DBMS_LOB.SUBSTR tidak menolak offset 0; hasilnya potongan awal
     * JSON yang pasti gagal dicocokkan regex — jatuh ke nilai kosong, bukan error.
     *
     * Penyaringan "perujuk = diri sendiri" sengaja TIDAK dilakukan di SQL. INSTR yang
     * TIDAK ketemu harus membaca CLOB sampai habis, jadi menaruhnya di WHERE berarti
     * satu pembacaan penuh tambahan untuk setiap baris rujukan yang justru ingin kita
     * simpan. Membuangnya di PHP setelah jendela terurai jauh lebih murah: yang
     * dipindahkan ke aplikasi hanya potongan ~1 KB, bukan JSON EMR utuh.
     */
    private function ambilBarisRujukan(Carbon $mulai, Carbon $akhir): array
    {
        $kolom = 'h.datadaftarpolirj_json';
        $anchorRujukan = "INSTR({$kolom}, '\"asalRujukan\"')";
        $anchorDiagnosa = "INSTR({$kolom}, '\"diagAwal\"')";

        $jendelaRujukan = 'DBMS_LOB.SUBSTR(' . $kolom . ', ' . self::LEBAR_JENDELA_RUJUKAN
            . ', GREATEST(' . $anchorRujukan . ' - 150, 1)) as jendela_rujukan';
        $jendelaDiagnosa = 'DBMS_LOB.SUBSTR(' . $kolom . ', ' . self::LEBAR_JENDELA_DIAGNOSA
            . ', GREATEST(' . $anchorDiagnosa . ', 1)) as jendela_diagnosa';

        $barisList = DB::table('rstxn_rjhdrs as h')
            ->leftJoin('rsmst_polis as pl', 'pl.poli_id', '=', 'h.poli_id')
            ->leftJoin('rsmst_doctors as dr', 'dr.dr_id', '=', 'h.dr_id')
            ->leftJoin('rsmst_pasiens as ps', 'ps.reg_no', '=', 'h.reg_no')
            ->whereBetween('h.rj_date', [$mulai, $akhir])
            // Kunjungan obat kronis bukan kunjungan poli — polanya sama seperti
            // KunjunganRJTrait yang juga membuang klaim_id 'KR'.
            ->where('h.klaim_id', '!=', 'KR')
            // 'F' = batal. NULL/'A' (masih antrian) tetap dihitung: pasiennya sudah
            // datang membawa rujukan, itu yang dievaluasi.
            ->where(fn($subQuery) => $subQuery->whereNull('h.rj_status')->orWhere('h.rj_status', '!=', 'F'))
            ->orderBy('h.rj_date')
            ->orderBy('h.rj_no')
            ->select([
                'h.rj_no',
                'h.rj_date',
                'h.reg_no',
                'h.vno_sep',
                'pl.poli_desc',
                'dr.dr_name',
                'ps.reg_name',
                'ps.nokartu_bpjs',
                DB::raw($jendelaRujukan),
                DB::raw($jendelaDiagnosa),
            ])
            ->get();

        $hasil = [];
        foreach ($barisList as $baris) {
            $jendela = (string) ($baris->jendela_rujukan ?? '');
            $kodeFaskes = $this->nilaiJson($jendela, 'ppkRujukan');

            // Dua hal dibuang di sini: kunjungan tanpa node rujukan sama sekali
            // (pasien umum — jendelanya potongan awal JSON yang tak cocok regex), dan
            // kunjungan yang perujuknya RS kita sendiri (internal / kontrol / pasca
            // ranap). Perbandingannya case-insensitive + trim karena baris lama sempat
            // menyimpan kode dengan spasi ikutan.
            if ($kodeFaskes === '' || strcasecmp(trim($kodeFaskes), self::PPK_KITA) === 0) {
                continue;
            }

            $hasil[] = [
                'rjNo'          => (string) $baris->rj_no,
                'tglKunjungan'  => $baris->rj_date ? Carbon::parse($baris->rj_date)->format('d/m/Y') : '',
                'noSep'         => trim((string) ($baris->vno_sep ?? '')),
                'regNo'         => (string) ($baris->reg_no ?? ''),
                'namaPasien'    => trim((string) ($baris->reg_name ?? '')),
                'noKartu'       => trim((string) ($baris->nokartu_bpjs ?? '')),
                'poliTujuan'    => trim((string) ($baris->poli_desc ?? '')),
                'dokterTujuan'  => trim((string) ($baris->dr_name ?? '')),
                'asalRujukan'   => $this->nilaiJson($jendela, 'asalRujukan'),
                'kodeFaskes'    => trim($kodeFaskes),
                'namaFaskes'    => trim($this->nilaiJson($jendela, 'ppkRujukanNama')),
                'noRujukan'     => trim($this->nilaiJson($jendela, 'noRujukan')),
                'tglRujukan'    => $this->keTampilTanggal($this->nilaiJson($jendela, 'tglRujukan')),
                'kodeDiagnosa'  => trim($this->nilaiJson((string) ($baris->jendela_diagnosa ?? ''), 'diagAwal')),
                'namaDiagnosa'  => '',
            ];
        }

        return $hasil;
    }

    /**
     * Dua pelengkapan yang sama-sama butuh SELURUH baris lebih dulu:
     *
     * 1. Nama faskes — sebagian baris hanya menyimpan kodenya (rujukan yang
     *    nomornya diketik manual, bukan dipilih dari LOV). Kode yang sama di baris
     *    lain hampir selalu membawa nama, jadi namanya dipinjam dari sana.
     * 2. Nama diagnosa — reqSep cuma menyimpan kode ICD (diagAwal). Namanya
     *    dilookup sekali untuk semua kode, bukan per baris.
     */
    private function lengkapiNama(array $daftar): array
    {
        $petaNamaFaskes = [];
        foreach ($daftar as $baris) {
            if ($baris['namaFaskes'] !== '' && !isset($petaNamaFaskes[$baris['kodeFaskes']])) {
                $petaNamaFaskes[$baris['kodeFaskes']] = $baris['namaFaskes'];
            }
        }

        $petaNamaDiagnosa = $this->petaNamaDiagnosa(
            collect($daftar)->pluck('kodeDiagnosa')->filter()->unique()->values()->all()
        );

        foreach ($daftar as $index => $baris) {
            if ($baris['namaFaskes'] === '') {
                $daftar[$index]['namaFaskes'] = $petaNamaFaskes[$baris['kodeFaskes']] ?? '';
            }
            if ($baris['kodeDiagnosa'] !== '') {
                $daftar[$index]['namaDiagnosa'] = $petaNamaDiagnosa[$baris['kodeDiagnosa']] ?? '';
            }
        }

        return $daftar;
    }

    /**
     * Lookup nama ICD massal. Master punya 288 icdx kembar (baris seed E-Klaim +
     * baris legacy), jadi urutannya sengaja menaik pada valid_code lalu accpdx:
     * keyBy mengambil yang TERAKHIR, artinya baris terbaik yang menang.
     * Lihat skill diagnosa-flow §1.
     */
    private function petaNamaDiagnosa(array $kodeList): array
    {
        if (empty($kodeList)) {
            return [];
        }

        $peta = [];
        // Oracle membatasi IN pada 1000 nilai — potong per 500.
        foreach (array_chunk($kodeList, 500) as $potongan) {
            DB::table('rsmst_mstdiags')
                ->whereIn('icdx', $potongan)
                ->orderBy('valid_code')
                ->orderBy('accpdx')
                ->get(['icdx', 'diag_desc'])
                ->each(function ($baris) use (&$peta) {
                    $peta[(string) $baris->icdx] = (string) ($baris->diag_desc ?? '');
                });
        }

        return $peta;
    }

    /**
     * Ambil satu nilai string dari potongan JSON mentah.
     * Nilainya masih berbentuk literal JSON (mis. "\/" atau "é"), jadi
     * dikembalikan ke teks asli lewat json_decode — bukan sekadar dipotong regex.
     */
    private function nilaiJson(string $potongan, string $kunci): string
    {
        $kunciAman = preg_quote($kunci, '/');

        if (preg_match('/"' . $kunciAman . '"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $potongan, $tangkapan)) {
            // Nilainya masih literal JSON (mis. \/ atau \uXXXX) — kembalikan ke teks asli.
            $nilai = json_decode('"' . $tangkapan[1] . '"');

            return is_string($nilai) ? $nilai : $tangkapan[1];
        }

        // Cadangan untuk baris lama yang sempat menyimpan angka tanpa tanda kutip
        // (mis. "asalRujukan":1). Tanpa ini nilainya terbaca kosong dan barisnya
        // masuk kelompok "Tidak diketahui" padahal datanya ada.
        if (preg_match('/"' . $kunciAman . '"\s*:\s*([0-9]+)/', $potongan, $tangkapan)) {
            return $tangkapan[1];
        }

        return '';
    }

    /* -------------------------
     | Computed — baris, halaman & rekap
     * ------------------------- */
    /**
     * Seluruh baris periode yang sedang dibuka, dibaca dari cache server.
     *
     * Cache::get, BUKAN Cache::remember: memulihkan sendiri berarti menjalankan
     * pemindaian CLOB sebulan penuh di tengah render — tanpa ada yang menekan
     * tombol apa pun, dan berisiko habis waktu. Kalau cache habis, layar bilang
     * terus terang lewat $this->kedaluwarsa dan menyuruh menekan Ambil Data lagi.
     * Aturan yang sama dipakai laporan rujukan keluar.
     */
    #[Computed]
    public function rujukanList(): array
    {
        if ($this->periodeTarik === '') {
            return [];
        }

        return Cache::get($this->kunciHasil($this->periodeTarik), []);
    }

    /** Sudah pernah ditarik, tapi hasilnya sudah lewat umur cache. */
    #[Computed]
    public function kedaluwarsa(): bool
    {
        return $this->periodeTarik !== '' && Cache::missing($this->kunciHasil($this->periodeTarik));
    }

    /**
     * Baris setelah filter asal & kata kunci — sebelum dipotong per halaman.
     * Rekap, kartu ringkas, dan CSV berangkat dari sini (bukan dari $this->rows)
     * supaya angkanya tidak ikut berubah saat pengguna pindah halaman.
     */
    #[Computed]
    public function barisTerfilter(): array
    {
        $keyword = mb_strtolower(trim($this->cariKeyword));

        return collect($this->rujukanList)
            ->filter(fn($baris) => $this->filterAsal === '' || $baris['asalRujukan'] === $this->filterAsal)
            ->filter(function ($baris) use ($keyword) {
                if ($keyword === '') {
                    return true;
                }
                $gabungan = mb_strtolower(implode(' ', [
                    $baris['noRujukan'], $baris['noSep'], $baris['noKartu'], $baris['namaPasien'],
                    $baris['regNo'], $baris['kodeFaskes'], $baris['namaFaskes'],
                    $baris['poliTujuan'], $baris['dokterTujuan'],
                    $baris['kodeDiagnosa'], $baris['namaDiagnosa'],
                ]));
                return str_contains($gabungan, $keyword);
            })
            ->values()
            ->all();
    }

    /**
     * Potongan halaman untuk tabel Rinci. Dinamai rows() mengikuti idiom layar
     * daftar di repo ini (rows()->links() di pelayanan-rj), dan hanya tabel Rinci
     * yang memakainya — rekap tetap membaca seluruh baris.
     */
    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $semua = $this->barisTerfilter;
        $halaman = Paginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            array_slice($semua, ($halaman - 1) * $this->itemsPerPage, $this->itemsPerPage),
            count($semua),
            $this->itemsPerPage,
            $halaman,
            ['path' => request()->url()],
        );
    }

    /** Label faskes = nama bila ada, kalau tidak kodenya — supaya grup tak pernah kosong. */
    private function labelFaskes(array $baris): string
    {
        $nama = trim((string) $baris['namaFaskes']);
        if ($nama !== '') {
            return $nama;
        }
        $kode = trim((string) $baris['kodeFaskes']);
        return $kode !== '' ? $kode : '(tanpa identitas)';
    }

    /** Label diagnosa = kode + nama, supaya tidak ambigu antar ICD yang mirip. */
    private function labelDiagnosa(array $baris): string
    {
        $kode = trim((string) $baris['kodeDiagnosa']);
        if ($kode === '') {
            return '(tanpa diagnosa rujukan)';
        }
        $nama = trim((string) $baris['namaDiagnosa']);
        return $nama !== '' ? "{$kode} — {$nama}" : $kode;
    }

    public function namaAsalRujukan(string $asal): string
    {
        return match ($asal) {
            '1' => 'FKTP',
            '2' => 'RS Lain',
            default => 'Tidak diketahui',
        };
    }

    /**
     * Faskes perujuk BESERTA poli yang dituju di RS kita — satu rangkaian, bukan
     * dua daftar terpisah. Yang dievaluasi memang pasangannya: puskesmas A rutin
     * mengirim ke poli apa, dan apakah itu sesuai kompetensi yang kita punya.
     */
    #[Computed]
    public function rekapFaskesPoli(): array
    {
        return collect($this->barisTerfilter)
            ->groupBy(fn($baris) => $this->labelFaskes($baris))
            ->map(fn($grup, $namaFaskes) => [
                'nama'   => $namaFaskes,
                'kode'   => trim((string) ($grup->first()['kodeFaskes'] ?? '')),
                'asal'   => $this->namaAsalRujukan((string) ($grup->first()['asalRujukan'] ?? '')),
                'jumlah' => $grup->count(),
                'poli'   => $grup
                    ->groupBy(fn($baris) => trim((string) $baris['poliTujuan']) ?: '(poli tidak terisi)')
                    ->map(fn($subGrup, $namaPoli) => [
                        'nama'         => $namaPoli,
                        'jumlah'       => $subGrup->count(),
                        'diagnosaList' => $subGrup
                            ->map(fn($baris) => trim((string) $baris['kodeDiagnosa']))
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

    /**
     * Poli penerima BESERTA dokternya. Sisi cermin dari rekap faskes: menunjukkan
     * beban rujukan masuk mendarat di poli & dokter mana.
     */
    #[Computed]
    public function rekapPoliDokter(): array
    {
        return collect($this->barisTerfilter)
            ->groupBy(fn($baris) => trim((string) $baris['poliTujuan']) ?: '(poli tidak terisi)')
            ->map(fn($grup, $namaPoli) => [
                'nama'   => $namaPoli,
                'jumlah' => $grup->count(),
                'dokter' => $grup
                    ->groupBy(fn($baris) => trim((string) $baris['dokterTujuan']) ?: '(tidak diketahui)')
                    ->map(fn($subGrup, $namaDokter) => [
                        'nama'       => $namaDokter,
                        'jumlah'     => $subGrup->count(),
                        'faskesList' => $subGrup
                            ->map(fn($baris) => $this->labelFaskes($baris))
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

    /** Rekap diagnosa lintas faskes — kode + nama sekaligus supaya tidak ambigu. */
    #[Computed]
    public function rekapDiagnosa(): array
    {
        return collect($this->barisTerfilter)
            ->groupBy(fn($baris) => $this->labelDiagnosa($baris))
            ->map(fn($grup, $nama) => [
                'nama'       => $nama,
                'jumlah'     => $grup->count(),
                'faskesList' => $grup
                    ->map(fn($baris) => $this->labelFaskes($baris))
                    ->filter()->unique()->sort()->implode(', '),
            ])
            ->sortByDesc('jumlah')
            ->values()
            ->all();
    }

    #[Computed]
    public function ringkasan(): array
    {
        $baris = collect($this->barisTerfilter);

        return [
            'total'         => $baris->count(),
            'fktp'          => $baris->where('asalRujukan', '1')->count(),
            'rsLain'        => $baris->where('asalRujukan', '2')->count(),
            'faskesUnik'    => $baris->pluck('kodeFaskes')->filter()->unique()->count(),
            'pasienUnik'    => $baris->pluck('regNo')->filter()->unique()->count(),
            'tanpaNama'     => $baris->filter(fn($item) => trim((string) $item['namaFaskes']) === '')->count(),
            'tanpaDiagnosa' => $baris->filter(fn($item) => trim((string) $item['kodeDiagnosa']) === '')->count(),
        ];
    }

    /* -------------------------
     | Ekspor CSV
     * ------------------------- */
    /**
     * Satu berkas berisi EMPAT bagian: rincian + tiga rekap yang sama persis dengan
     * yang tampil di layar. Dipisah baris kosong + baris judul, bukan berkas
     * terpisah — supaya sekali unduh langsung siap dilampirkan ke laporan evaluasi.
     */
    public function eksporCsv()
    {
        $baris = $this->barisTerfilter;
        if (empty($baris)) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada data untuk diekspor.');
            return null;
        }

        $faskes = $this->rekapFaskesPoli;
        $poli = $this->rekapPoliDokter;
        $diagnosa = $this->rekapDiagnosa;
        $ringkasan = $this->ringkasan;
        $periode = $this->tglMulai . ' s/d ' . $this->tglAkhir;

        $namaBerkas = 'rujukan-masuk-' . str_replace('/', '', $this->tglMulai)
            . '-sd-' . str_replace('/', '', $this->tglAkhir) . '.csv';

        return response()->streamDownload(function () use ($baris, $faskes, $poli, $diagnosa, $ringkasan, $periode) {
            $keluaran = fopen('php://output', 'w');
            // BOM UTF-8 — tanpa ini Excel membaca berkas sebagai ANSI dan nama
            // pasien beraksen jadi rusak.
            fwrite($keluaran, "\xEF\xBB\xBF");

            $tulis = fn(array $kolom) => fputcsv($keluaran, $kolom, ';');
            $kosong = fn() => fputcsv($keluaran, [], ';');
            // Desimal koma + ribuan titik: berkas ini dibaca di Excel berlokal Indonesia.
            $persen = fn($bagian, $penyebut) => $penyebut > 0 ? number_format($bagian / $penyebut * 100, 1, ',', '.') : '0,0';

            /* ── KEPALA ── */
            $tulis(['LAPORAN EVALUASI RUJUKAN MASUK RS']);
            $tulis(['Periode', $periode]);
            $tulis(['Asal rujukan', $this->filterAsal === '1' ? 'FKTP' : ($this->filterAsal === '2' ? 'RS Lain' : 'Semua')]);
            if ($this->cariKeyword !== '') {
                $tulis(['Kata kunci', $this->cariKeyword]);
            }
            $tulis(['Total rujukan masuk', $ringkasan['total']]);
            $tulis(['Dari FKTP / Dari RS lain', $ringkasan['fktp'] . ' / ' . $ringkasan['rsLain']]);
            $tulis(['Faskes perujuk', $ringkasan['faskesUnik']]);
            $tulis(['Pasien unik', $ringkasan['pasienUnik']]);
            $tulis(['Tanpa nama faskes', $ringkasan['tanpaNama']]);
            $tulis(['Tanpa diagnosa rujukan', $ringkasan['tanpaDiagnosa']]);
            $tulis(['Sumber', 'JSON kunjungan RJ (sep.reqSep.request.t_sep.rujukan) — tanpa panggilan BPJS']);
            $kosong();

            /* ── 1. RINCIAN ── */
            $tulis(['1. RINCIAN RUJUKAN MASUK']);
            $tulis([
                'Tgl Kunjungan', 'No Kunjungan', 'No SEP', 'No RM', 'Nama Pasien', 'No Kartu',
                'Kode Faskes Perujuk', 'Faskes Perujuk', 'Asal', 'No Rujukan', 'Tgl Rujukan',
                'Poli Tujuan', 'Dokter', 'Kode Diagnosa', 'Diagnosa Rujukan',
            ]);
            foreach ($baris as $item) {
                $tulis([
                    $item['tglKunjungan'],
                    $item['rjNo'],
                    $item['noSep'],
                    $item['regNo'],
                    $item['namaPasien'],
                    $item['noKartu'],
                    $item['kodeFaskes'],
                    $item['namaFaskes'],
                    $this->namaAsalRujukan($item['asalRujukan']),
                    $item['noRujukan'],
                    $item['tglRujukan'],
                    $item['poliTujuan'],
                    $item['dokterTujuan'],
                    $item['kodeDiagnosa'],
                    $item['namaDiagnosa'],
                ]);
            }
            $kosong();

            /* ── 2. REKAP FASKES PERUJUK & POLI TUJUAN ── */
            $totalFaskes = collect($faskes)->sum('jumlah');
            $tulis(['2. REKAP FASKES PERUJUK & POLI TUJUAN']);
            $tulis(['Faskes Perujuk', 'Kode PPK', 'Asal', 'Poli Tujuan', 'Diagnosa Rujukan', 'Jumlah', '% thd Total', '% thd Faskes']);
            foreach ($faskes as $satuFaskes) {
                $tulis([$satuFaskes['nama'], $satuFaskes['kode'], $satuFaskes['asal'], '', '', $satuFaskes['jumlah'], $persen($satuFaskes['jumlah'], $totalFaskes), '']);
                foreach ($satuFaskes['poli'] as $satuPoli) {
                    $tulis(['', '', '', $satuPoli['nama'], $satuPoli['diagnosaList'], $satuPoli['jumlah'], '', $persen($satuPoli['jumlah'], $satuFaskes['jumlah'])]);
                }
            }
            $tulis(['TOTAL', '', '', '', '', $totalFaskes, '100,0', '']);
            $kosong();

            /* ── 3. REKAP POLI PENERIMA & DOKTER ── */
            $totalPoli = collect($poli)->sum('jumlah');
            $tulis(['3. REKAP POLI PENERIMA & DOKTER']);
            $tulis(['Poli Penerima', 'Dokter', 'Faskes Perujuk', 'Jumlah', '% thd Total', '% thd Poli']);
            foreach ($poli as $satuPoli) {
                $tulis([$satuPoli['nama'], '', '', $satuPoli['jumlah'], $persen($satuPoli['jumlah'], $totalPoli), '']);
                foreach ($satuPoli['dokter'] as $satuDokter) {
                    $tulis(['', $satuDokter['nama'], $satuDokter['faskesList'], $satuDokter['jumlah'], '', $persen($satuDokter['jumlah'], $satuPoli['jumlah'])]);
                }
            }
            $tulis(['TOTAL', '', '', $totalPoli, '100,0', '']);
            $kosong();

            /* ── 4. REKAP DIAGNOSA RUJUKAN (lintas faskes) ── */
            $totalDiagnosa = collect($diagnosa)->sum('jumlah');
            $tulis(['4. REKAP DIAGNOSA RUJUKAN (lintas faskes)']);
            $tulis(['Diagnosa Rujukan', 'Faskes Perujuk', 'Jumlah', '% thd Total']);
            foreach ($diagnosa as $item) {
                $tulis([$item['nama'], $item['faskesList'], $item['jumlah'], $persen($item['jumlah'], $totalDiagnosa)]);
            }
            $tulis(['TOTAL', '', $totalDiagnosa, '100,0']);

            fclose($keluaran);
        }, $namaBerkas, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* -------------------------
     | Helper tanggal
     * ------------------------- */
    /** dd/mm/yyyy (input kita) -> Carbon; null bila formatnya tidak sah. */
    private function keTanggal(string $tanggal): ?Carbon
    {
        try {
            return Carbon::createFromFormat('d/m/Y', trim($tanggal));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * tglRujukan di reqSep sudah yyyy-MM-dd (dikonversi buildSEPRequest sebelum
     * dikirim ke BPJS), tapi data lama sempat tersimpan dd/mm/yyyy — terima keduanya.
     */
    private function keTampilTanggal(string $tanggal): string
    {
        $tanggal = trim($tanggal);
        if ($tanggal === '') {
            return '';
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
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

<div>
    <x-page-title title="Evaluasi Rujukan Masuk RS"
        subtitle="Rujukan masuk Rawat Jalan — faskes perujuk, poli penerima, dan diagnosa rujukan" />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-canvas dark:bg-gray-800">
        <div class="px-6 pt-4 pb-8 space-y-6">

            {{-- TOOLBAR --}}
            <div class="flex flex-wrap items-end gap-2.5 pb-2 lg:flex-nowrap">

                {{-- FILTER PERIODE — dua tanggal disatukan sebagai satu konsep,
                     keduanya selalu diisi berpasangan jadi tak perlu dua label --}}
                <div class="shrink-0">
                    <x-input-label value="Periode" />
                    {{-- Lebar dipasang di DIV pembungkus, bukan di <x-text-input>: komponen itu
                         sudah membawa `w-full` di kelas dasarnya dan selalu menang di CSS hasil
                         build, sehingga lebar yang ditempel lewat atribut akan diabaikan. --}}
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

                {{-- FILTER ASAL — asalRujukan: 1=FKTP, 2=RS lain --}}
                <div class="shrink-0">
                    <x-input-label value="Asal" />
                    <x-select-input wire:model.live="filterAsal" class="mt-1 w-36" title="Asal rujukan">
                        <option value="">Semua asal</option>
                        <option value="1">FKTP</option>
                        <option value="2">RS Lain</option>
                    </x-select-input>
                </div>

                {{-- SEARCH — flex-1, isi sisa ruang setelah filter lain --}}
                <div class="flex-1 min-w-0">
                    <x-input-label value="Cari" />
                    <div class="relative mt-1">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <x-text-input type="text" wire:model.live.debounce.300ms="cariKeyword" class="block w-full pl-10"
                            placeholder="Cari Nama / No RM / SEP / Faskes / Poli / Dokter / Diagnosa..." />
                    </div>
                </div>

                {{-- RIGHT ACTIONS --}}
                <div class="flex items-end gap-2 ml-auto shrink-0">
                    <x-primary-button type="button" wire:click="tarikData" wire:loading.attr="disabled"
                        wire:target="tarikData" title="Baca rujukan masuk dari data kunjungan kita sendiri — tanpa panggilan BPJS">
                        {{-- Ikon basis data, bukan awan-unduh seperti laporan rujukan keluar:
                             menegaskan datanya dibaca dari DB kita, bukan ditarik dari BPJS. --}}
                        <span wire:loading.remove wire:target="tarikData" class="inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                            </svg>
                            Ambil Data
                        </span>
                        <span wire:loading wire:target="tarikData" class="inline-flex items-center gap-1.5">
                            <x-loading /> Mengambil...
                        </span>
                    </x-primary-button>

                    @if ($periodeTarik !== '' && !$this->kedaluwarsa)
                        <x-secondary-button type="button" wire:click="eksporCsv" wire:loading.attr="disabled"
                            wire:target="eksporCsv" title="Unduh CSV — rincian + rekap Faskes Perujuk &amp; Poli, Poli Penerima &amp; Dokter, dan Diagnosa, mengikuti filter yang sedang aktif">
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

                    <div class="w-20">
                        <x-select-input wire:model.live="itemsPerPage" class="text-sm" title="Baris per halaman (tabel Rinci)">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </x-select-input>
                    </div>
                </div>
            </div>

            {{-- PANDUAN — panel biru-info standar, default TERTUTUP --}}
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
                        Berbeda dengan <strong>Evaluasi Rujukan Keluar</strong> yang menarik daftar dari BPJS,
                        laporan ini <strong>tidak memanggil BPJS sama sekali</strong>. VClaim tidak menyediakan
                        endpoint &ldquo;rujukan masuk per periode&rdquo; &mdash; yang ada hanya pencarian per nomor
                        kartu peserta. Untungnya faskes perujuk sudah ikut tersimpan saat SEP dibuat, pada node
                        <span class="font-mono text-xs">sep.reqSep.request.t_sep.rujukan</span> di JSON kunjungan kita.
                        Karena itu sekali klik <strong>Ambil Data</strong> langsung lengkap, tanpa penarikan bertahap.
                    </p>
                    <p>
                        <strong>Isi laporan hanya Rawat Jalan.</strong> Cuma jalur RJ yang merekam faskes perujuk luar:
                        LOV rujukan mengisi kode &amp; nama PPK dari data BPJS. UGD mematok PPK perujuk = RS kita
                        sendiri untuk semua pasien (aturan SEP gawat darurat), dan di Rawat Inap kolom PPK Asal Rujukan
                        terkunci pada nilai yang sama karena SEP-nya berangkat dari SPRI kita. Menyertakan keduanya
                        hanya akan melahirkan baris &ldquo;dirujuk oleh diri sendiri&rdquo;.
                    </p>
                    <p>
                        <strong>Yang dibuang:</strong> kunjungan berstatus batal, kunjungan obat kronis, dan semua
                        baris yang PPK perujuknya adalah RS kita sendiri &mdash; itu kunjungan internal, kontrol,
                        atau SEP lanjutan pasca rawat inap, bukan rujukan masuk.
                    </p>
                    <p>
                        <strong>Asal</strong> dibaca dari <span class="font-mono text-xs">asalRujukan</span>:
                        1 = FKTP (puskesmas / klinik / dokter keluarga), 2 = RS lain.
                        <strong>Diagnosa rujukan</strong> berasal dari <span class="font-mono text-xs">diagAwal</span>
                        pada SEP &mdash; kode ICD yang dituliskan faskes perujuk; namanya dilihat dari master diagnosa.
                        Baris <strong>(tanpa diagnosa rujukan)</strong> berarti kolom itu kosong saat SEP dibuat.
                    </p>
                    <p>
                        <strong>Pasien umum tidak muncul.</strong> Tanpa SEP tidak ada node rujukan sama sekali, jadi
                        rujukan masuk pasien non-BPJS belum terekam di mana pun.
                    </p>
                </div>
            </div>

            @if ($periodeTarik === '')
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
            @elseif ($this->kedaluwarsa)
                {{-- Hasil tarik hanya bertahan selama umur cache. Tanpa spanduk ini
                     layar akan tampak melaporkan "nol rujukan masuk" — bohong yang
                     mahal untuk laporan evaluasi. --}}
                <div class="p-8 text-center border rounded-2xl bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-700">
                    <svg class="w-10 h-10 mx-auto text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="mt-3 text-sm font-semibold text-amber-800 dark:text-amber-200">Hasil tarik sudah kedaluwarsa</p>
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                        Data periode {{ $tglMulai }} &ndash; {{ $tglAkhir }} sudah dilepas dari penyimpanan sementara.
                        Tekan <strong>Ambil Data</strong> untuk membacanya lagi.
                    </p>
                </div>

            @else

                {{-- KARTU RINGKAS — collapsible, default tutup --}}
                <div class="bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900"
                    x-data="{ open: false }">

                    <button type="button" @click="open = !open"
                        class="flex items-center w-full gap-3 px-4 py-3 text-left transition-colors rounded-2xl
                               hover:bg-surface-soft dark:hover:bg-gray-800
                               focus:outline-none focus:ring-1 focus:ring-gray-300">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold text-body dark:text-gray-200">
                                Ringkasan Rujukan Masuk {{ $tglMulai }} &ndash; {{ $tglAkhir }}
                            </div>
                            <div class="text-xs text-muted dark:text-gray-400">
                                {{ number_format($this->ringkasan['total']) }} rujukan
                                · {{ number_format($this->ringkasan['faskesUnik']) }} faskes perujuk
                                @if ($this->ringkasan['tanpaDiagnosa'] > 0)
                                    · {{ number_format($this->ringkasan['tanpaDiagnosa']) }} tanpa diagnosa
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
                                ['Rujukan Masuk', $this->ringkasan['total'], 'rujukan', 'border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700'],
                                ['Dari FKTP', $this->ringkasan['fktp'], 'rujukan', 'border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700'],
                                ['Dari RS Lain', $this->ringkasan['rsLain'], 'rujukan', 'border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700'],
                                ['Faskes Perujuk', $this->ringkasan['faskesUnik'], 'faskes', 'border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700'],
                                ['Pasien Unik', $this->ringkasan['pasienUnik'], 'pasien', 'border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700'],
                                ['Tanpa Diagnosa', $this->ringkasan['tanpaDiagnosa'], 'rujukan', $this->ringkasan['tanpaDiagnosa'] > 0 ? 'border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700' : 'border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700'],
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
                        @if ($this->ringkasan['tanpaNama'] > 0)
                            <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                {{ number_format($this->ringkasan['tanpaNama']) }} baris hanya menyimpan kode faskes tanpa
                                namanya &mdash; nomor rujukannya diketik manual, bukan dipilih dari daftar BPJS.
                            </p>
                        @endif
                    </div>
                </div>

                {{-- TAB --}}
                <x-tabs variant="underline">
                    <x-tab :active="$tab === 'rinci'" wire:click="setTab('rinci')">Rinci</x-tab>
                    <x-tab :active="$tab === 'faskes'" wire:click="setTab('faskes')">Faskes Perujuk &amp; Poli</x-tab>
                    <x-tab :active="$tab === 'poli'" wire:click="setTab('poli')">Poli Penerima &amp; Dokter</x-tab>
                    <x-tab :active="$tab === 'diagnosa'" wire:click="setTab('diagnosa')">Per Diagnosa</x-tab>
                </x-tabs>

                {{-- TAB RINCI --}}
                @if ($tab === 'rinci')
                    <div class="bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
                            <h3 class="text-sm font-semibold tracking-wider uppercase text-muted dark:text-gray-400">
                                Rincian Rujukan Masuk &mdash; {{ number_format(count($this->barisTerfilter)) }} baris
                            </h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-surface-soft dark:bg-gray-800 text-muted dark:text-gray-400">
                                    <tr>
                                        <th class="px-3 py-2 text-left border-b border-hairline dark:border-gray-700">Kunjungan</th>
                                        <th class="px-3 py-2 text-left border-b border-hairline dark:border-gray-700">Pasien</th>
                                        <th class="px-3 py-2 text-left border-b border-l border-hairline dark:border-gray-700">Faskes Perujuk</th>
                                        <th class="px-3 py-2 text-left border-b border-l border-hairline dark:border-gray-700">Diterima Di</th>
                                        <th class="px-3 py-2 text-left border-b border-l border-hairline dark:border-gray-700">Diagnosa Rujukan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-hairline-soft dark:divide-gray-700">
                                    @forelse ($this->rows as $row)
                                        <tr class="hover:bg-surface-soft dark:hover:bg-gray-800/50" wire:key="rjm-{{ $row['rjNo'] }}">
                                            <td class="px-3 py-2 align-top">
                                                <span class="font-mono text-xs font-semibold text-ink dark:text-gray-100">{{ $row['rjNo'] }}</span>
                                                <span class="block text-xs text-muted dark:text-gray-400">{{ $row['tglKunjungan'] }}</span>
                                                <span class="block font-mono text-xs text-muted dark:text-gray-400">SEP {{ $row['noSep'] ?: '—' }}</span>
                                            </td>
                                            <td class="px-3 py-2 align-top">
                                                <span class="font-medium text-ink dark:text-gray-100">{{ $row['namaPasien'] ?: '—' }}</span>
                                                <span class="block font-mono text-xs text-muted dark:text-gray-400">RM {{ $row['regNo'] }}</span>
                                                <span class="block font-mono text-xs text-muted dark:text-gray-400">{{ $row['noKartu'] ?: '—' }}</span>
                                            </td>
                                            <td class="px-3 py-2 align-top border-l border-hairline dark:border-gray-700">
                                                <span class="font-medium text-ink dark:text-gray-100">{{ $row['namaFaskes'] ?: '(nama tidak tersimpan)' }}</span>
                                                <span class="block font-mono text-xs text-muted dark:text-gray-400">{{ $row['kodeFaskes'] }}</span>
                                                <x-badge :variant="$row['asalRujukan'] === '1' ? 'info' : 'gray'" class="mt-1">
                                                    {{ $this->namaAsalRujukan($row['asalRujukan']) }}
                                                </x-badge>
                                                @if (filled($row['noRujukan']))
                                                    <span class="block mt-1 font-mono text-xs text-muted dark:text-gray-400">
                                                        {{ $row['noRujukan'] }}{{ filled($row['tglRujukan']) ? ' · ' . $row['tglRujukan'] : '' }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 align-top border-l border-hairline dark:border-gray-700">
                                                <span class="text-body dark:text-gray-300">{{ $row['poliTujuan'] ?: '—' }}</span>
                                                <span class="block text-xs font-medium text-ink dark:text-gray-100">{{ $row['dokterTujuan'] ?: '—' }}</span>
                                            </td>
                                            <td class="px-3 py-2 align-top border-l border-hairline dark:border-gray-700">
                                                @if (filled($row['kodeDiagnosa']))
                                                    <span class="font-mono font-semibold text-ink dark:text-gray-100">{{ $row['kodeDiagnosa'] }}</span>
                                                    <span class="block text-xs text-muted dark:text-gray-400">{{ $row['namaDiagnosa'] ?: '(tidak ada di master diagnosa)' }}</span>
                                                @else
                                                    <span class="text-xs italic text-muted-soft">tidak diisi</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-3 py-10 text-center text-muted-soft">Tidak ada rujukan masuk pada periode / filter ini.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- PAGINATION — hanya tabel Rinci; rekap & CSV tetap memakai seluruh baris --}}
                        @if ($this->rows->hasPages())
                            <div class="px-4 py-3 border-t border-hairline dark:border-gray-700">
                                {{ $this->rows->links() }}
                            </div>
                        @endif
                    </div>

                {{-- TAB FASKES — faskes perujuk sebagai induk, poli yang dituju sebagai anak --}}
                @elseif ($tab === 'faskes')
                    @php
                        $dataFaskes = $this->rekapFaskesPoli;
                        $totalFaskes = collect($dataFaskes)->sum('jumlah');
                    @endphp
                    <div class="bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-hairline dark:border-gray-700">
                            <h3 class="text-sm font-semibold tracking-wider uppercase text-muted dark:text-gray-400">
                                Faskes Perujuk &amp; Poli yang Dituju
                            </h3>
                            <span class="text-xs text-muted dark:text-gray-400">
                                % baris faskes terhadap seluruh rujukan · % baris poli terhadap faskes itu saja
                            </span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-surface-soft dark:bg-gray-800 text-muted dark:text-gray-400">
                                    <tr>
                                        <th class="px-3 py-2 text-left border-b border-hairline dark:border-gray-700">Faskes Perujuk / Poli Tujuan</th>
                                        <th class="px-3 py-2 text-left border-b border-l border-hairline dark:border-gray-700">Diagnosa Rujukan</th>
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
                                                <span class="ml-1 text-xs font-normal text-muted dark:text-gray-400">&middot; {{ $faskes['asal'] }}</span>
                                            </td>
                                            <td class="px-3 py-2 border-l border-hairline dark:border-gray-700"></td>
                                            <td class="px-3 py-2 font-semibold text-center border-l border-hairline dark:border-gray-700 text-ink dark:text-gray-100">{{ number_format($faskes['jumlah']) }}</td>
                                            <td class="px-3 py-2 font-semibold text-center text-ink dark:text-gray-100">
                                                {{ $totalFaskes > 0 ? number_format($faskes['jumlah'] / $totalFaskes * 100, 1) : '0,0' }}
                                            </td>
                                        </tr>
                                        @foreach ($faskes['poli'] as $poli)
                                            <tr class="hover:bg-surface-soft dark:hover:bg-gray-800/50">
                                                <td class="py-1.5 pl-8 pr-3 text-body dark:text-gray-300">{{ $poli['nama'] }}</td>
                                                <td class="px-3 py-1.5 text-xs border-l border-hairline dark:border-gray-700 text-muted dark:text-gray-400">{{ $poli['diagnosaList'] ?: '—' }}</td>
                                                <td class="px-3 py-1.5 text-center border-l border-hairline dark:border-gray-700 text-body dark:text-gray-300">{{ number_format($poli['jumlah']) }}</td>
                                                <td class="px-3 py-1.5 text-center text-muted dark:text-gray-400">
                                                    {{ $faskes['jumlah'] > 0 ? number_format($poli['jumlah'] / $faskes['jumlah'] * 100, 1) : '0,0' }}
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
                                            <td class="px-3 py-2 text-ink dark:text-gray-100" colspan="2">TOTAL RUJUKAN MASUK</td>
                                            <td class="px-3 py-2 text-center border-l border-hairline dark:border-gray-700 text-ink dark:text-gray-100">{{ number_format($totalFaskes) }}</td>
                                            <td class="px-3 py-2 text-center text-ink dark:text-gray-100">100,0</td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>

                {{-- TAB POLI — poli penerima sebagai induk, dokter yang menerima sebagai anak --}}
                @elseif ($tab === 'poli')
                    @php
                        $dataPoli = $this->rekapPoliDokter;
                        $totalPoli = collect($dataPoli)->sum('jumlah');
                    @endphp
                    <div class="bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-hairline dark:border-gray-700">
                            <h3 class="text-sm font-semibold tracking-wider uppercase text-muted dark:text-gray-400">
                                Poli Penerima &amp; Dokter
                            </h3>
                            <span class="text-xs text-muted dark:text-gray-400">
                                % baris poli terhadap seluruh rujukan · % baris dokter terhadap poli itu saja
                            </span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-surface-soft dark:bg-gray-800 text-muted dark:text-gray-400">
                                    <tr>
                                        <th class="px-3 py-2 text-left border-b border-hairline dark:border-gray-700">Poli Penerima / Dokter</th>
                                        <th class="px-3 py-2 text-left border-b border-l border-hairline dark:border-gray-700">Faskes Perujuk</th>
                                        <th class="w-24 px-3 py-2 text-center border-b border-l border-hairline dark:border-gray-700">Jumlah</th>
                                        <th class="w-24 px-3 py-2 text-center border-b border-hairline dark:border-gray-700">%</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-hairline-soft dark:divide-gray-700">
                                    @forelse ($dataPoli as $poli)
                                        <tr class="bg-surface-soft/60 dark:bg-gray-800/60" wire:key="poli-{{ $poli['nama'] }}">
                                            <td class="px-3 py-2 font-semibold text-ink dark:text-gray-100">{{ $poli['nama'] }}</td>
                                            <td class="px-3 py-2 border-l border-hairline dark:border-gray-700"></td>
                                            <td class="px-3 py-2 font-semibold text-center border-l border-hairline dark:border-gray-700 text-ink dark:text-gray-100">{{ number_format($poli['jumlah']) }}</td>
                                            <td class="px-3 py-2 font-semibold text-center text-ink dark:text-gray-100">
                                                {{ $totalPoli > 0 ? number_format($poli['jumlah'] / $totalPoli * 100, 1) : '0,0' }}
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
                                @if ($totalPoli > 0)
                                    <tfoot class="font-semibold bg-surface-soft dark:bg-gray-800">
                                        <tr>
                                            <td class="px-3 py-2 text-ink dark:text-gray-100" colspan="2">TOTAL RUJUKAN MASUK</td>
                                            <td class="px-3 py-2 text-center border-l border-hairline dark:border-gray-700 text-ink dark:text-gray-100">{{ number_format($totalPoli) }}</td>
                                            <td class="px-3 py-2 text-center text-ink dark:text-gray-100">100,0</td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>

                {{-- TAB DIAGNOSA --}}
                @else
                    @php
                        $dataDiagnosa = $this->rekapDiagnosa;
                        $totalDiagnosa = collect($dataDiagnosa)->sum('jumlah');
                    @endphp
                    <div class="bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
                            <h3 class="text-sm font-semibold tracking-wider uppercase text-muted dark:text-gray-400">
                                Rekap per Diagnosa Rujukan &mdash; {{ number_format(count($dataDiagnosa)) }} kelompok
                            </h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-surface-soft dark:bg-gray-800 text-muted dark:text-gray-400">
                                    <tr>
                                        <th class="w-12 px-3 py-2 text-right border-b border-hairline dark:border-gray-700">#</th>
                                        <th class="px-3 py-2 text-left border-b border-hairline dark:border-gray-700">Diagnosa Rujukan</th>
                                        <th class="px-3 py-2 text-left border-b border-l border-hairline dark:border-gray-700">Faskes Perujuk</th>
                                        <th class="w-28 px-3 py-2 text-center border-b border-l border-hairline dark:border-gray-700">Jumlah</th>
                                        <th class="w-28 px-3 py-2 text-center border-b border-hairline dark:border-gray-700">%</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-hairline-soft dark:divide-gray-700">
                                    @forelse ($dataDiagnosa as $nomor => $item)
                                        <tr class="hover:bg-surface-soft dark:hover:bg-gray-800/50">
                                            <td class="px-3 py-2 text-right text-muted">{{ $nomor + 1 }}</td>
                                            <td class="px-3 py-2 font-medium text-ink dark:text-gray-100">{{ $item['nama'] }}</td>
                                            <td class="px-3 py-2 text-xs border-l border-hairline dark:border-gray-700 text-muted dark:text-gray-400">{{ $item['faskesList'] ?: '—' }}</td>
                                            <td class="px-3 py-2 text-center border-l border-hairline dark:border-gray-700 text-body dark:text-gray-300">{{ number_format($item['jumlah']) }}</td>
                                            <td class="px-3 py-2 font-semibold text-center text-body dark:text-gray-300">
                                                {{ $totalDiagnosa > 0 ? number_format($item['jumlah'] / $totalDiagnosa * 100, 1) : '0,0' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-3 py-10 text-center text-muted-soft">Belum ada data.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if ($totalDiagnosa > 0)
                                    <tfoot class="font-semibold bg-surface-soft dark:bg-gray-800">
                                        <tr>
                                            <td class="px-3 py-2"></td>
                                            <td class="px-3 py-2 text-ink dark:text-gray-100" colspan="2">TOTAL</td>
                                            <td class="px-3 py-2 text-center border-l border-hairline dark:border-gray-700 text-ink dark:text-gray-100">{{ number_format($totalDiagnosa) }}</td>
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
