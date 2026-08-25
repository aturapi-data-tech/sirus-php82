<?php
// Komponen Modal "Pengkajian Medis Rawat Jalan" — pakai-ulang & review pengkajian
// (Akreditasi PP 1.2 poin e). Dipisah dari daftar-rj supaya orchestrator tetap
// ramping (pola sama diagnosa-rj-actions / satu-sehat-rj-actions).
// Trigger dari parent: dispatch 'pelayanan-rj.pengkajian-medis.open' dengan rjNo.
//
// ATURANNYA: pengkajian medis yang dibuat <= 30 hari sebelum pasien menjalani
// prosedur di rawat jalan BOLEH dipakai lagi, asal ditinjau/diverifikasi dan
// diperbarui sesuai kondisi terkini. Lebih dari 30 hari, WAJIB pengkajian ulang.
//
// Siklus: Draft -> TTD dokter (mengunci) -> Buka Kunci (Gate dokumen.bukaKunci).
// Satu kunjungan = SATU baris review; dijaga indeks IDX_PKJ_REVIEW_PEMAKAI.
// Rancangan kolom & bentuk REVIEW_JSON: docs/ddl-pengkajian-medis-pp12.sql.
//
// BELUM ADA: cetak & viewer rekam medis.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
use App\Http\Traits\Txn\Pengkajian\PengkajianReviewTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use Barryvdh\DomPDF\Facade\Pdf;

new class extends Component {
    use EmrRJTrait, WithValidationToastTrait, PengkajianReviewTrait, MasterPasienTrait;

    public ?int $rjNo = null;
    public ?string $regNo = null;
    public string $tglKunjungan = '';

    /** Pengkajian terakhir sebelum kunjungan ini; kosong bila tak ada. */
    public array $sebelumnya = [];

    /** Riwayat pengkajian pasien (RJ + RI), terbaru dulu — dibatasi BATAS_RIWAYAT. */
    public array $riwayat = [];

    /** Jumlah SEBENARNYA sebelum dipotong; dipakai memberi tahu yang tak tampil. */
    public int $riwayatTotal = 0;

    /** Review yang sudah pernah dibuat untuk pasien ini (isi panel Riwayat Pengkajian). */
    public array $riwayatReview = [];
    public int $riwayatReviewTotal = 0;

    /**
     * false = tabel RSTXN_PENGKAJIAN_REVIEWS belum ada di database.
     * Pembacaan riwayat tak lagi butuh DDL apa pun (memakai erm_status & rj_date yang
     * sudah ada), tapi MENYIMPAN review tetap butuh tabelnya. Diperiksa supaya layarnya
     * menjelaskan keadaan, bukan meledak dengan ORA-00942.
     */
    public bool $siapDipakai = false;

    /* ===============================
     | FORMULIR REVIEW
     =============================== */

    public array $form = [
        'tglHariIni' => '',             // dd/mm/yyyy — tanggal acuan hitung usia, bisa dikoreksi
        'sumberJenis' => 'RJ',          // 'RJ' | 'RI' | 'LUAR'
        'sumberNo' => '',
        'sumberDeskripsi' => '',
        'tglPengkajian' => '',         // dd/mm/yyyy — tanggal pengkajian yang ditinjau, bisa dikoreksi petugas
        'adaPerubahan' => 'T',          // 'Y' | 'T'
        'perubahanDesc' => '',
        'tindakanTinjau' => false,
        'tindakanVerifikasi' => false,
        'tindakanUlang' => false,
        'reviewCatatan' => '',
        // TTD = stempel petugas yang LOGIN. Key-nya diberi akhiran PengkajianReview,
        // bukan `ttd/ttdCode/ttdDate` polos seperti konvensi x-signature.ttd-petugas:
        // JSON ini bisa menyimpan tanda tangan lebih dari satu peran kelak, dan
        // `ttd` polos akan langsung ambigu begitu itu terjadi. Nama PROP komponennya tetap
        // :ttd/:code/:date — itu miliknya, bukan milik JSON ini.
        // ttdPengkajianReviewCode dipakai me-resolve users.myuser_ttd_image saat cetak.
        'ttdPengkajianReview' => '',
        'ttdPengkajianReviewCode' => '',
        'ttdPengkajianReviewDate' => '',
    ];

    /** Baris review milik kunjungan ini; kosong bila belum ada. */
    public array $reviewTersimpan = [];

    /** true = sudah di-TTD, formulir jadi baca-saja. */
    public bool $isFormLocked = false;

    #[On('pelayanan-rj.pengkajian-medis.open')]
    public function open(int $rjNo): void
    {
        $this->reset(['regNo', 'tglKunjungan', 'sebelumnya', 'riwayat']);
        $this->rjNo = $rjNo;

        $header = DB::table('rstxn_rjhdrs')
            ->where('rj_no', $rjNo)
            ->select('reg_no', DB::raw("to_char(rj_date,'dd/mm/yyyy hh24:mi') as rj_date"))
            ->first();

        $this->regNo = $header->reg_no ?? null;
        $this->tglKunjungan = $header->rj_date ?? '';

        $this->siapDipakai = $this->checkPengkajianReviewTable();
        $this->hitungRiwayat($rjNo);
        $this->muatReview($rjNo);
        // muatReview bisa memulihkan tglHariIni dari review tersimpan; kalau acuannya
        // ternyata bukan hari ini, riwayatnya dihitung ulang supaya usia di panel
        // kanan sama dengan yang dipakai bagian 2.
        $this->hitungRiwayat($rjNo);

        $this->dispatch('open-modal', name: 'pengkajian-medis-rj');
    }

    /* ===============================
     | MUAT / SIMPAN REVIEW
     =============================== */

    private function hitungRiwayat(int $rjNo): void
    {
        [$this->riwayat, $this->riwayatTotal] = $this->findRiwayatPengkajian(
            $this->regNo ?? '',
            'RJ',
            $rjNo,
            $this->form['tglHariIni'] ?? null
        );
        $this->sebelumnya = $this->riwayat[0] ?? [];
    }

    /**
     * Buka rekam medis kunjungan yang MEMAKAI review ini, lewat viewer yang sudah
     * ada — komponennya di-mount di Pelayanan RJ, bukan di sini.
     *
     * Hanya RJ: pendengar RI (`cetak-rekam-medis-ri.open`) belum dipasang di
     * halaman ini, jadi tombolnya pun cuma muncul untuk baris RJ.
     */
    public function lihatRekamMedisPemakai(int $rjNoPemakai): void
    {
        $this->dispatch('cetak-rekam-medis.open', rjNo: $rjNoPemakai);
    }

    /**
     * Samakan sumberNo dengan pilihan sumber + tanggal pengkajian yang terlihat.
     *
     * sumberNo tak punya input di layar — ia cuma terisi saat prasetel. Akibatnya,
     * begitu petugas mengubah "Sumber Pengkajian" atau "Tgl pengkajian sebelumnya",
     * nomornya tertinggal (atau kosong) dan review tersimpan dengan sumber.no = null
     * TANPA gejala apa pun di layar. Review #19 di basis data kena persis itu.
     *
     * Nomornya dicari dari riwayat: baris yang jenis DAN tanggalnya cocok. Kalau tak
     * ada yang cocok, sengaja DIKOSONGKAN — nomor yang salah lebih berbahaya daripada
     * nomor yang tak ada, sebab dipakai membuka rekam medis.
     */
    private function sinkronSumberNo(): void
    {
        if ($this->form['sumberJenis'] === 'LUAR') {
            $this->form['sumberNo'] = '';

            return;
        }

        $cocok = collect($this->riwayat)->first(
            fn (array $baris) => $baris['sumberJenis'] === $this->form['sumberJenis']
                && $baris['tgl'] === $this->form['tglPengkajian']
        );

        $this->form['sumberNo'] = (string) ($cocok['sumberNo'] ?? '');
    }

    /** Ganti jenis sumber → nomornya ikut, jangan tertinggal di jenis sebelumnya. */
    public function updatedFormSumberJenis(): void
    {
        $this->sinkronSumberNo();
    }

    /** Ganti tanggal pengkajian → nomor kunjungannya ikut pindah. */
    public function updatedFormTglPengkajian(): void
    {
        $this->sinkronSumberNo();
    }

    /**
     * Buka rekam medis kunjungan SUMBER — pengkajian yang ditinjau, bukan kunjungan
     * yang memakainya. Viewer-nya sama, nomornya beda.
     */
    public function lihatPengkajianSumber(int $rjNoSumber): void
    {
        $this->dispatch('cetak-rekam-medis.open', rjNo: $rjNoSumber);
    }

    /**
     * Cetak satu baris review jadi PDF — pola modul dokumen: rakit $data di sini,
     * Pdf::loadView ke blade cetak terpusat, lalu streamDownload.
     *
     * TTD dokternya diambil ulang dari master (myuser_code → myuser_ttd_image),
     * bukan dari gambar yang disimpan di record: yang tersimpan cuma NAMA + KODE,
     * dan gambarnya bisa berubah di master tanpa membatalkan tanda tangannya.
     */
    public function cetak(int $reviewNo)
    {
        $baris = DB::table('rstxn_pengkajian_reviews')->where('review_no', $reviewNo)->first();

        if (! $baris) {
            $this->dispatch('toast', type: 'error', message: 'Data review tidak ditemukan.');

            return null;
        }

        try {
            $review = $this->readJsonPengkajianReview($baris);
            $identitasRs = DB::table('rsmst_identitases')
                ->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')
                ->first();

            $pasienData = $this->findDataMasterPasien((string) $baris->reg_no);
            $pasien = $pasienData['pasien'] ?? [];

            if (! empty($pasien['tglLahir'])) {
                try {
                    $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                        ->diff(Carbon::now(config('app.timezone')))
                        ->format('%y Thn, %m Bln %d Hr');
                } catch (\Throwable) {
                    $pasien['thn'] = '-';
                }
            }

            $ttdPetugasPath = null;
            $kodeTtd = $review['form']['ttdPengkajianReviewCode'] ?? null;

            if (filled($kodeTtd)) {
                $berkasTtd = DB::table('users')->where('myuser_code', $kodeTtd)->value('myuser_ttd_image');

                if (! empty($berkasTtd) && file_exists(public_path('storage/' . $berkasTtd))) {
                    $ttdPetugasPath = public_path('storage/' . $berkasTtd);
                }
            }

            $data = array_merge($pasien, [
                'review' => $review,
                'identitasRs' => $identitasRs,
                'ttdPetugasPath' => $ttdPetugasPath,
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);

            $pdf = Pdf::loadView(
                'pages.components.modul-dokumen.rj.pengkajian-review.cetak-pengkajian-review-rj-print',
                ['data' => $data]
            )->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak review pengkajian medis.');

            return response()->streamDownload(
                fn () => print $pdf->output(),
                'review-pengkajian-medis-' . ($baris->reg_no ?? '') . '-' . $reviewNo . '.pdf'
            );
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $exception->getMessage());

            return null;
        }
    }

    /**
     * Buka kunci SATU baris review dari panel Riwayat — versi ber-nomor dari
     * bukaKunci() yang cuma bisa menyentuh review kunjungan yang sedang dibuka.
     *
     * Barisnya DIPERTAHANKAN, hanya TTD-nya dicabut: jejak bahwa pemeriksaan 30
     * hari pernah dilakukan tidak boleh hilang.
     *
     * Audit log ditulis ke EMR kunjungan PEMAKAI (dari JSON), bukan ke kunjungan
     * yang kebetulan sedang dibuka — kalau tidak, jejaknya nyasar ke rekam medis
     * pasien yang salah.
     */
    public function bukaKunciReview(int $reviewNo): void
    {
        if (! auth()->user()?->can('dokumen.bukaKunci')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak membuka kunci.');

            return;
        }

        try {
            DB::transaction(function () use ($reviewNo) {
                $this->lockPengkajianReviewRow($reviewNo);

                $baris = DB::table('rstxn_pengkajian_reviews')->where('review_no', $reviewNo)->first();
                $isi = $this->readJsonPengkajianReview($baris);

                $isi['review']['terkunci'] = false;
                $isi['form']['ttdPengkajianReview'] = '';
                $isi['form']['ttdPengkajianReviewCode'] = '';
                $isi['form']['ttdPengkajianReviewDate'] = '';

                DB::table('rstxn_pengkajian_reviews')
                    ->where('review_no', $reviewNo)
                    ->update(['review_json' => json_encode($isi, self::JSON_FLAGS_PENGKAJIAN)]);

                $rjPemakai = (int) ($isi['pemakai']['no'] ?? 0);

                if (($isi['pemakai']['jenis'] ?? '') === 'RJ' && $rjPemakai > 0) {
                    $this->appendAdminLogRJ(
                        $rjPemakai,
                        'Buka kunci Review Pengkajian Medis #' . $reviewNo . ' — oleh '
                            . (auth()->user()->myuser_name ?? auth()->user()->name ?? '-'),
                        'MR'
                    );
                }
            });
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuka kunci: ' . $exception->getMessage());

            return;
        }

        $this->muatReview((int) $this->rjNo);
        $this->dispatch('toast', type: 'info', message: 'Kunci review dibuka.');
    }

    /**
     * Hapus satu baris review. Hanya boleh saat TIDAK terkunci — dokumen yang sudah
     * ditandatangani harus dibuka kuncinya lebih dulu, dan itu pun tercatat di audit.
     */
    public function hapusReview(int $reviewNo): void
    {
        if (! auth()->user()?->can('dokumen.hapus')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang menghapus entri.');

            return;
        }

        try {
            DB::transaction(function () use ($reviewNo) {
                $this->lockPengkajianReviewRow($reviewNo);

                $baris = DB::table('rstxn_pengkajian_reviews')->where('review_no', $reviewNo)->first();
                $isi = $this->readJsonPengkajianReview($baris);

                if (! empty($isi['review']['terkunci'])) {
                    throw new \RuntimeException('Review masih terkunci — buka kuncinya dulu.');
                }

                $rjPemakai = (int) ($isi['pemakai']['no'] ?? 0);

                DB::table('rstxn_pengkajian_reviews')->where('review_no', $reviewNo)->delete();

                if (($isi['pemakai']['jenis'] ?? '') === 'RJ' && $rjPemakai > 0) {
                    $this->appendAdminLogRJ(
                        $rjPemakai,
                        'Hapus Review Pengkajian Medis #' . $reviewNo . ' — oleh '
                            . (auth()->user()->myuser_name ?? auth()->user()->name ?? '-'),
                        'MR'
                    );
                }
            });
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $exception->getMessage());

            return;
        }

        $this->muatReview((int) $this->rjNo);
        $this->dispatch('toast', type: 'success', message: 'Review pengkajian dihapus.');
    }

    /** Acuan digeser petugas → usia & keputusan ikut berubah seketika. */
    public function updatedFormTglHariIni(): void
    {
        if (filled($this->rjNo)) {
            $this->hitungRiwayat((int) $this->rjNo);
        }
    }

    private function muatReview(int $rjNo): void
    {
        $this->reset(['form', 'reviewTersimpan']);
        $this->isFormLocked = false;

        // Panel Riwayat Pengkajian ikut disegarkan di sini supaya baris yang baru
        // disimpan/dibuka kuncinya langsung tampak, tanpa menutup modal dulu.
        [$this->riwayatReview, $this->riwayatReviewTotal] =
            $this->findRiwayatReviewPengkajian($this->regNo ?? '', 'RJ', $rjNo);

        // REG_NO satu-satunya kolom penyaring; kunjungan pemakainya ada di dalam JSON,
        // jadi pencocokannya di PHP. Aman karena satu pasien cuma punya segelintir
        // review — bukan pemindaian seluruh tabel.
        [$baris, $isi] = $this->findDataPengkajianReview($this->regNo ?? '', 'RJ', $rjNo);

        if ($baris) {
            $this->reviewTersimpan = [
                'reviewNo' => (string) $baris->review_no,
                'keputusan' => (string) ($isi['keputusan'] ?? ''),
                'reviewDate' => (string) ($isi['reviewDate'] ?? ''),
                'reviewDr' => (string) ($isi['review']['drDesc'] ?? ''),
            ];
            $this->form = array_replace($this->form, $isi['form'] ?? []);
            // Status kunci ada di JSON, bukan kolom: TTD & dokter penanda tangan tak
            // pernah dipakai menyaring lewat SQL, jadi tak perlu jadi kolom datar
            // (lihat docs/ddl-pengkajian-medis-pp12.sql).
            $this->isFormLocked = (bool) ($isi['review']['terkunci'] ?? false);

            return;
        }

        // Belum ada review — prasetel dari pengkajian sebelumnya supaya petugas tak
        // mengetik ulang apa yang sudah diketahui sistem.
        $this->form['tglHariIni'] = $this->form['tglHariIni'] ?: now()->format('d/m/Y');

        if (blank($this->sebelumnya)) {
            // Tak ada pengkajian internal yang bisa ditinjau. Satu-satunya sumber yang
            // masuk akal adalah dari LUAR RS; kalau memang tak ada, layar mengarahkan
            // membuat pengkajian baru di EMR RJ, bukan menyimpan review kosong.
            $this->form['sumberJenis'] = 'LUAR';

            return;
        }

        if (filled($this->sebelumnya)) {
            $this->form['sumberJenis'] = $this->sebelumnya['sumberJenis'];
            $this->form['sumberNo'] = $this->sebelumnya['sumberNo'];
            $this->form['sumberDeskripsi'] = $this->sebelumnya['unit'];
            // Dokter pengkajinya TIDAK direkam di sini: yang bertanggung jawab atas
            // dokumen ini adalah penanda tangan review. Siapa yang dulu melakukan
            // pengkajian tetap terbaca di panel Riwayat Pengkajian Pasien, tanpa perlu
            // disalin jadi isian yang bisa keliru.
            $this->form['tglPengkajian'] = $this->sebelumnya['tgl'];
            // Centang mengikuti keputusan: <=30 hari tinjau+verifikasi, >30 hari ulang.
            $berlaku = $this->sebelumnya['masihBerlaku'];
            $this->form['tindakanTinjau'] = $berlaku;
            $this->form['tindakanVerifikasi'] = $berlaku;
            $this->form['tindakanUlang'] = !$berlaku;
        }
    }

    /**
     * Keputusan dihitung dari TANGGAL DI FORMULIR, bukan dipilih petugas.
     * Tanggalnya sendiri boleh dikoreksi — tapi hasil <=30/>30 tetap turunan
     * tanggal, jadi ambangnya tak bisa diakali langsung.
     */
    public function keputusan(): string
    {
        return $this->calculateKeputusanPengkajian($this->form['tglPengkajian'] ?? null, $this->form['tglHariIni'] ?? null);
    }

    public function usiaHariTerpakai(): ?int
    {
        return $this->calculateUsiaPengkajian($this->form['tglPengkajian'] ?? null, $this->form['tglHariIni'] ?? null);
    }

    protected function rules(): array
    {
        return [
            'form.sumberJenis' => 'required|in:RJ,RI,LUAR',
            'form.tglHariIni' => ['required', 'date_format:d/m/Y', function ($atribut, $nilai, $gagal) {
                $acuan = $this->parseTanggalPengkajian($nilai);
                $pengkajian = $this->parseTanggalPengkajian($this->form['tglPengkajian'] ?? null);

                if ($acuan && $pengkajian && $acuan->startOfDay()->lt($pengkajian->startOfDay())) {
                    $gagal('Tanggal hari ini tidak boleh lebih awal dari tanggal pengkajian ('
                        . $pengkajian->format('d/m/Y') . ') — usianya jadi negatif.');
                }
            }],
            'form.tglPengkajian' => ['required', 'date_format:d/m/Y', function ($atribut, $nilai, $gagal) {
                $pengkajian = $this->parseTanggalPengkajian($nilai);

                if ($pengkajian && $pengkajian->startOfDay()->gt(now()->startOfDay())) {
                    $gagal('Tanggal pengkajian tidak boleh di masa depan.');
                }
            }],
            'form.sumberDeskripsi' => 'required_if:form.sumberJenis,LUAR|nullable|string|max:200',
            'form.adaPerubahan' => 'required|in:Y,T',
            'form.perubahanDesc' => 'required_if:form.adaPerubahan,Y|nullable|string|max:1000',
            'form.reviewCatatan' => 'required|string|max:2000',
        ];
    }

    protected function messages(): array
    {
        return [
            'form.tglHariIni.required' => 'Tanggal acuan wajib diisi.',
            'form.tglHariIni.date_format' => 'Tanggal acuan harus berformat dd/mm/yyyy.',
            'form.tglPengkajian.required' => 'Tanggal pengkajian yang ditinjau wajib diisi.',
            'form.tglPengkajian.date_format' => 'Tanggal pengkajian harus berformat dd/mm/yyyy.',
            'form.sumberDeskripsi.required_if' => 'Nama faskes asal pengkajian wajib diisi.',
            'form.perubahanDesc.required_if' => 'Keterangan perubahan wajib diisi bila kondisi pasien berubah bermakna.',
            'form.reviewCatatan.required' => 'Catatan review wajib diisi.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.sumberJenis' => 'Sumber pengkajian',
            'form.tglHariIni' => 'Tanggal acuan',
            'form.tglPengkajian' => 'Tanggal pengkajian',
            'form.sumberDeskripsi' => 'Keterangan faskes',
            'form.adaPerubahan' => 'Perubahan kondisi',
            'form.perubahanDesc' => 'Keterangan perubahan',
            'form.reviewCatatan' => 'Catatan review',
        ];
    }

    public function simpanDraft(): void
    {
        $this->simpan(false);
    }

    /**
     * TTD = aksi TERAKHIR yang sekaligus MENGUNCI (pola modul dokumen).
     * Yang menandatangani adalah SIAPA PUN yang sedang login — stempelnya diambil
     * dari user aktif, bukan digambar tangan.
     */
    public function ttdDokter(): void
    {
        // Stempel TTD-nya TIDAK dipasang di sini. Dulu begitu, dan akibatnya: saat
        // validasi gagal, formulir batal tersimpan TAPI stempelnya sudah menempel di
        // layar — kartu TTD tampil, tombolnya hilang, dan petugas tak bisa mengulang.
        // Sekarang pemasangannya di dalam simpan(), sesudah formulirnya terbukti sah.
        $this->simpan(true);
    }

    private function simpan(bool $kunci): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Review sudah terkunci.');

            return;
        }
        if (!$this->siapDipakai || blank($this->rjNo)) {
            return;
        }

        // Jaring pengaman: nomor sumber disamakan lagi tepat sebelum payload dibentuk,
        // supaya tak bergantung pada hook updated yang bisa terlewat (mis. nilai diubah
        // dari kode, bukan dari layar).
        $this->sinkronSumberNo();
        // regNo dibaca dari header kunjungan saat open(); kalau kosong berarti
        // kunjungannya tak ketemu — hentikan dengan pesan, jangan sampai jadi
        // ORA-01400 di lapisan bawah.
        if (blank($this->regNo)) {
            $this->dispatch('toast', type: 'error', message: 'Nomor RM pasien tidak ditemukan untuk kunjungan ini.');

            return;
        }

        $this->validateWithToast();

        // Baru distempel SESUDAH validasi lolos — lihat catatan di ttdDokter().
        if ($kunci) {
            $this->form['ttdPengkajianReview'] = auth()->user()->myuser_name ?? auth()->user()->name ?? '';
            $this->form['ttdPengkajianReviewCode'] = auth()->user()->myuser_code ?? '';
            $this->form['ttdPengkajianReviewDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        }

        // Satu sumber tanggal untuk semua jenis sumber — isian formulir, yang untuk
        // sumber internal sudah diprasetel dari data dan boleh dikoreksi petugas.
        $tglPengkajian = $this->parseTanggalPengkajian($this->form['tglPengkajian']);

        if ($tglPengkajian === null) {
            $this->dispatch('toast', type: 'error', message: 'Isi dulu tanggal pengkajian yang ditinjau (dd/mm/yyyy).');

            return;
        }

        $keputusan = $this->keputusan();
        $drReview = auth()->user()->myuser_name ?? auth()->user()->name ?? '';
        $sekarang = Carbon::now(config('app.timezone'));

        $isiJson = [
            'pemakai' => ['jenis' => 'RJ', 'no' => (int) $this->rjNo],
            'sumber' => [
                'jenis' => $this->form['sumberJenis'],
                'no' => $this->form['sumberJenis'] === 'LUAR' ? null : ($this->form['sumberNo'] ?: null),
                'deskripsi' => $this->form['sumberDeskripsi'],
            ],
            'tglPengkajian' => $tglPengkajian->format('d/m/Y'),
            // Tanggalnya ikut acuan yang dipakai menghitung (bisa digeser petugas),
            // jamnya tetap jam sungguhan supaya urutan kejadian tak dikarang.
            'reviewDate' => ($this->form['tglHariIni'] ?: $sekarang->format('d/m/Y')) . ' ' . $sekarang->format('H:i:s'),
            'keputusan' => $keputusan,
            'form' => $this->form,
            'usiaHariSaatReview' => $this->usiaHariTerpakai(),
            'review' => [
                'drId' => auth()->user()->myuser_code ?? '',
                'drDesc' => $drReview,
                'terkunci' => $kunci,
                'waktu' => $sekarang->format('d/m/Y H:i:s'),
            ],
            // Jejak pembuat ikut JSON, bukan kolom: tak pernah dipakai menyaring
            // maupun mengurutkan lewat SQL.
            'dibuat' => $isiLama['dibuat'] ?? [
                'oleh' => $drReview,
                'waktu' => $sekarang->format('d/m/Y H:i:s'),
            ],
        ];

        try {
            DB::transaction(function () use ($isiJson) {
                $this->updateJsonPengkajianReview($this->regNo ?? '', 'RJ', (int) $this->rjNo, $isiJson);

                $this->appendAdminLogRJ(
                    (int) $this->rjNo,
                    ($isiJson['review']['terkunci'] ? 'Kunci (TTD Dokter)' : 'Simpan Draft')
                        . ' Review Pengkajian Medis — keputusan ' . $isiJson['keputusan'],
                    'MR'
                );
            });
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $exception->getMessage());

            return;
        }

        $this->muatReview((int) $this->rjNo);
        $this->dispatch('toast', type: 'success', message: $kunci
            ? 'Review pengkajian ditandatangani dan terkunci.'
            : 'Draft review pengkajian tersimpan.');
    }

    public function bukaKunci(): void
    {
        if (!auth()->user()?->can('dokumen.bukaKunci')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak membuka kunci.');

            return;
        }
        if (blank($this->reviewTersimpan)) {
            return;
        }

        DB::transaction(function () {
            // Mencabut TTD saja — barisnya DIPERTAHANKAN supaya jejak bahwa pemeriksaan
            // 30 hari pernah dilakukan tidak hilang.
            $this->lockPengkajianReviewRow((int) $this->reviewTersimpan['reviewNo']);

            $baris = DB::table('rstxn_pengkajian_reviews')
                ->where('review_no', $this->reviewTersimpan['reviewNo'])->first();
            $isi = $this->readJsonPengkajianReview($baris);
            $isi['review']['terkunci'] = false;
            $isi['form']['ttdPengkajianReview'] = '';
            $isi['form']['ttdPengkajianReviewCode'] = '';
            $isi['form']['ttdPengkajianReviewDate'] = '';

            DB::table('rstxn_pengkajian_reviews')
                ->where('review_no', $this->reviewTersimpan['reviewNo'])
                ->update(['review_json' => json_encode($isi, self::JSON_FLAGS_PENGKAJIAN)]);

            $this->appendAdminLogRJ(
                (int) $this->rjNo,
                'Buka kunci Review Pengkajian Medis — oleh ' . (auth()->user()->myuser_name ?? auth()->user()->name ?? '-'),
                'MR'
            );
        });

        $this->muatReview((int) $this->rjNo);
        $this->dispatch('toast', type: 'info', message: 'Kunci review dibuka.');
    }
};
?>

<div>
    <x-modal name="pengkajian-medis-rj" size="full" height="full" focusable>
        <div class="flex flex-col h-full">

            {{-- ══ HEADER ══ --}}
            <div class="px-6 py-4 border-b border-hairline dark:border-gray-700">
                @if (!empty($rjNo))
                    <div class="flex items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                                wire:key="pengkajian-medis-rj-display-pasien-{{ $rjNo }}" />
                        </div>
                        <x-icon-button color="gray" type="button"
                            x-on:click="$dispatch('close-modal', { name: 'pengkajian-medis-rj' })" class="shrink-0">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </x-icon-button>
                    </div>
                @endif
                <h2 class="mt-2 text-base font-semibold text-ink dark:text-gray-100">
                    Pengkajian Medis Rawat Jalan
                </h2>
                <p class="text-sm text-muted dark:text-gray-400">
                    Meninjau pengkajian medis sebelumnya untuk dipakai ulang
                </p>
            </div>

            <div class="flex-1 px-6 py-4 overflow-y-auto">

                @if (!$siapDipakai)
                    {{-- Kolom stempelnya belum ada di database environment ini. --}}
                    <div
                        class="px-4 py-3 text-sm border rounded-2xl bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200">
                        <p class="font-semibold">Belum bisa dipakai &mdash; DDL belum dijalankan.</p>
                        <p class="mt-1">
                            Tabel <span class="font-mono">RSTXN_PENGKAJIAN_REVIEWS</span> belum ada di
                            environment ini, jadi hasil review tak punya tempat disimpan.
                            Jalankan <span class="font-mono">docs/ddl-pengkajian-medis-pp12.sql</span>
                            lebih dulu.
                        </p>
                    </div>
                @else
                    @php
                        $terkunci = $isFormLocked;
                        $adaSebelumnya = filled($sebelumnya);
                        $berlaku = $sebelumnya['masihBerlaku'] ?? false;
                        $usia = $this->usiaHariTerpakai();
                        $keputusan = $this->keputusan();
                    @endphp

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">

                        {{-- ═══════════ KOLOM KIRI: LIMA BAGIAN ═══════════ --}}
                        <div class="space-y-4 md:col-span-2">

                            {{-- ── 1. PENGKAJIAN MEDIS SEBELUMNYA ── --}}
                            {{-- 1 & 2 bersanding: keduanya soal pengkajian yang ditinjau. --}}
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <x-border-form :title="__('1. Pengkajian Medis Sebelumnya')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
                                    <div class="px-4 py-3 space-y-3">
                                        @if ($adaSebelumnya)
                                            <p class="text-sm text-muted dark:text-gray-400">
                                                Sistem menemukan pengkajian medis sebelumnya untuk pasien ini &mdash;
                                                tak perlu ditanyakan ulang.
                                            </p>
                                        @else
                                            <div class="px-3 py-2 text-sm border rounded-xl bg-surface-soft border-hairline dark:bg-gray-800 dark:border-gray-700">
                                                <span class="font-semibold text-ink dark:text-gray-200">Tidak ada pengkajian sebelumnya di RS ini.</span>
                                                <span class="text-muted dark:text-gray-400">
                                                    Pilih sumber <strong>Luar RS</strong> bila pasien dikaji di faskes lain,
                                                    atau buat pengkajian baru lewat EMR Rawat Jalan.
                                                </span>
                                            </div>
                                        @endif

                                        {{-- Tanpa grid: kartu ini cuma separuh kolom kiri, kalau isinya
                                             dibagi tiga lagi labelnya patah-patah. Ditumpuk saja, tiap
                                             kendali dapat lebar penuh. --}}
                                        <div class="space-y-3">
                                            <div>
                                                <x-input-label value="Sumber Pengkajian *" class="mb-1" />
                                                {{-- Radio, bukan dropdown: ketiga pilihannya harus kelihatan
                                                     sekaligus supaya petugas tahu "Luar RS" itu ada tanpa
                                                     membuka daftar dulu. Pola sama dengan bagian 3. --}}
                                                <div class="flex flex-col gap-1">
                                                    <x-radio-button wire:model.live="form.sumberJenis" value="RJ" :disabled="$terkunci"
                                                        label="Rawat Jalan (RS ini)" />
                                                    <x-radio-button wire:model.live="form.sumberJenis" value="RI" :disabled="$terkunci"
                                                        label="Rawat Inap (RS ini)" />
                                                    <x-radio-button wire:model.live="form.sumberJenis" value="LUAR" :disabled="$terkunci"
                                                        label="Luar RS" />
                                                </div>
                                                <x-input-error :messages="$errors->get('form.sumberJenis')" class="mt-1" />
                                            </div>

                                            @if ($form['sumberJenis'] === 'LUAR')
                                                <div>
                                                    <x-input-label value="Nama Faskes *" class="mb-1" />
                                                    <x-text-input wire:model.live="form.sumberDeskripsi" placeholder="cth: Poli Bedah RS X"
                                                        :error="$errors->has('form.sumberDeskripsi')" :disabled="$terkunci" class="w-full" />
                                                    <x-input-error :messages="$errors->get('form.sumberDeskripsi')" class="mt-1" />
                                                </div>
                                            @endif
                                        </div>

                                        @if ($form['sumberJenis'] === 'LUAR')
                                            <div class="px-3 py-2 text-sm border rounded-xl bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200">
                                                Pengkajian dari luar RS <strong>tidak bisa diverifikasi sistem</strong> &mdash;
                                                usia harinya dihitung dari tanggal yang diisi petugas.
                                            </div>
                                        @elseif (!$adaSebelumnya)
                                            <div class="px-3 py-2 text-sm border rounded-xl bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200">
                                                Sistem tak menemukan pengkajian di RS ini &mdash; kemungkinan dokternya
                                                belum menandatangani (TTD-E) sehingga kunjungannya tak tertandai.
                                                Tanggal &amp; dokternya isi manual di bawah.
                                            </div>
                                        @endif
                                    </div>
                                </x-border-form>

                            {{-- ── 2. USIA & KEPUTUSAN ── --}}
                                <x-border-form :title="__('2. Usia Pengkajian (Otomatis) & Keputusan')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
                                    <div class="space-y-4 px-4 py-3">
                                        <div class="space-y-2">
                                            <div>
                                                <x-input-label value="Tanggal hari ini *" class="mb-1" />
                                                <x-text-input wire:model.live="form.tglHariIni" placeholder="dd/mm/yyyy"
                                                    :error="$errors->has('form.tglHariIni')" :disabled="$terkunci" class="w-full" />
                                                <x-input-error :messages="$errors->get('form.tglHariIni')" class="mt-1" />
                                                @if (($form['tglHariIni'] ?? '') !== now()->format('d/m/Y'))
                                                    <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">
                                                        Bukan tanggal hari ini ({{ now()->format('d/m/Y') }}) &mdash; seluruh
                                                        usia dihitung terhadap tanggal ini.
                                                    </p>
                                                @endif
                                            </div>
                                            <div>
                                                <x-input-label value="Tgl pengkajian sebelumnya *" class="mb-1" />
                                                <x-text-input wire:model.live="form.tglPengkajian" placeholder="dd/mm/yyyy"
                                                    :error="$errors->has('form.tglPengkajian')" :disabled="$terkunci" class="w-full" />
                                                <x-input-error :messages="$errors->get('form.tglPengkajian')" class="mt-1" />
                                                @if (filled($sebelumnya) && ($form['tglPengkajian'] ?? '') !== ($sebelumnya['tgl'] ?? ''))
                                                    <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">
                                                        Berbeda dari data sistem ({{ $sebelumnya['tgl'] }}) &mdash; usia &amp;
                                                        keputusan mengikuti tanggal yang kamu isi.
                                                    </p>
                                                @endif
                                            </div>
                                            <div
                                                class="px-3 py-2 text-center border rounded-xl {{ $usia === null ? 'bg-surface-soft border-hairline dark:bg-gray-800 dark:border-gray-700' : ($keputusan === 'REVIEW' ? 'bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800' : 'bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800') }}">
                                                <div class="text-2xl font-bold {{ $usia === null ? 'text-muted' : ($keputusan === 'REVIEW' ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300') }}">
                                                    {{ $usia === null ? '—' : $usia . ' hari' }}
                                                </div>
                                                <div class="text-xs text-muted dark:text-gray-400">usia pengkajian</div>
                                            </div>
                                        </div>

                                        <div class="md:col-span-2">
                                            <x-input-label value="Keputusan (ditentukan sistem)" class="mb-1" />
                                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                <div class="px-3 py-2 text-sm border rounded-xl {{ $keputusan === 'REVIEW' && $usia !== null ? 'bg-emerald-50 border-emerald-300 dark:bg-emerald-900/20 dark:border-emerald-700' : 'border-hairline opacity-60 dark:border-gray-700' }}">
                                                    <div class="font-semibold text-ink dark:text-gray-200">&le; 30 hari</div>
                                                    <p class="text-muted dark:text-gray-400">
                                                        Pengkajian sebelumnya ditinjau/diverifikasi dan diperbarui sesuai
                                                        kondisi pasien saat ini.
                                                    </p>
                                                </div>
                                                <div class="px-3 py-2 text-sm border rounded-xl {{ $keputusan === 'ULANG' && $usia !== null ? 'bg-red-50 border-red-300 dark:bg-red-900/20 dark:border-red-700' : 'border-hairline opacity-60 dark:border-gray-700' }}">
                                                    <div class="font-semibold text-ink dark:text-gray-200">&gt; 30 hari</div>
                                                    <p class="text-muted dark:text-gray-400">
                                                        Wajib dilakukan pengkajian medis ulang sebelum tindakan/prosedur
                                                        di layanan rawat jalan.
                                                    </p>
                                                </div>
                                            </div>
                                            <p class="mt-2 text-xs text-muted dark:text-gray-400">
                                                Keputusan dihitung dari selisih tanggal &mdash; sengaja tidak bisa dipilih
                                                petugas, supaya ambang 30 hari tak kehilangan artinya.
                                            </p>
                                        </div>
                                    </div>
                                </x-border-form>
                            </div>

                            {{-- ── 3. KONDISI PASIEN SAAT INI ── --}}
                            {{-- 3 & 4 bersanding: keduanya soal kondisi & tindakan hari ini. --}}
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <x-border-form :title="__('3. Kondisi Pasien Saat Ini')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
                                    <div class="space-y-4 px-4 py-3">
                                        <div>
                                            <x-input-label value="Ada perubahan kondisi klinis yang bermakna? *" class="mb-1" />
                                            <div class="flex flex-col gap-1">
                                                <x-radio-button wire:model.live="form.adaPerubahan" value="T" :disabled="$terkunci"
                                                    label="Tidak ada perubahan bermakna" />
                                                <x-radio-button wire:model.live="form.adaPerubahan" value="Y" :disabled="$terkunci"
                                                    label="Ada perubahan bermakna" />
                                            </div>
                                            <x-input-error :messages="$errors->get('form.adaPerubahan')" class="mt-1" />
                                        </div>
                                        {{-- Hanya muncul saat memang ada perubahan — kotak nonaktif yang selalu
                                             terlihat cuma jadi kebisingan di layar yang sudah padat. --}}
                                        @if ($form['adaPerubahan'] === 'Y')
                                            <div class="md:col-span-2">
                                                <x-input-label value="Sebutkan perubahannya *" class="mb-1" />
                                                <x-textarea wire:model.live="form.perubahanDesc" rows="4" :disabled="$terkunci"
                                                    :error="$errors->has('form.perubahanDesc')"
                                                    placeholder="cth: nyeri lebih sering saat berjalan jauh, sebelumnya tak ada nyeri..." class="w-full" />
                                                <x-input-error :messages="$errors->get('form.perubahanDesc')" class="mt-1" />
                                            </div>
                                        @endif
                                    </div>
                                </x-border-form>

                            {{-- ── 4. TINDAKAN YANG DILAKUKAN SAAT INI ── --}}
                                <x-border-form :title="__('4. Tindakan yang Dilakukan Saat Ini')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
                                    <div class="flex flex-col gap-3 px-4 py-3">
                                        <x-toggle wire:model.live="form.tindakanTinjau" :trueValue="true" :falseValue="false"
                                            :disabled="$terkunci" label="Meninjau hasil pengkajian sebelumnya" />
                                        <x-toggle wire:model.live="form.tindakanVerifikasi" :trueValue="true" :falseValue="false"
                                            :disabled="$terkunci" label="Verifikasi & update sesuai kondisi terkini" />
                                        <x-toggle wire:model.live="form.tindakanUlang" :trueValue="true" :falseValue="false"
                                            :disabled="$terkunci" label="Pengkajian medis ulang (> 30 hari)" />
                                    </div>
                                </x-border-form>
                            </div>

                            {{-- ── 5. DOKUMENTASI REVIEW / VERIFIKASI ── --}}
                            <x-border-form :title="__('5. Dokumentasi Review / Verifikasi')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
                                <div class="space-y-3">
                                    @if ($terkunci)
                                        <div><x-badge variant="success">Terkunci</x-badge></div>
                                    @elseif (filled($reviewTersimpan))
                                        <div><x-badge variant="warning">Draft</x-badge></div>
                                    @endif
                                    <div>
                                        <x-input-label value="Catatan Review / Update *" class="mb-1" />
                                        <x-textarea wire:model.live="form.reviewCatatan" rows="3" :disabled="$terkunci"
                                            :error="$errors->has('form.reviewCatatan')"
                                            placeholder="cth: Pengkajian sebelumnya ditinjau dan diverifikasi. Pasien stabil, tanda vital dalam batas normal..." class="w-full" />
                                        <x-input-error :messages="$errors->get('form.reviewCatatan')" class="mt-1" />
                                    </div>

                                    {{-- TTD paling bawah — urutan baku modul dokumen: isi dulu, tanda tangan
                                         terakhir. Komponen baku repo (dipakai 62 berkas). allowClear=false:
                                         sekali ditandatangani tak bisa diganti dari sini — pencabutannya lewat
                                         Buka Kunci yang ber-Gate & tercatat di audit log. --}}
                                    <div class="pt-2 border-t border-hairline dark:border-gray-700">
                                        <x-signature.ttd-petugas :framed="false" :allowClear="false"
                                            :ttd="$form['ttdPengkajianReview']" :code="$form['ttdPengkajianReviewCode']"
                                            :date="$form['ttdPengkajianReviewDate']"
                                            :locked="$terkunci" sign="ttdDokter"
                                            nameLabel="Dokter Penanggung Jawab" dateLabel="Tanggal / Jam TTD"
                                            signLabel="TTD & Kunci"
                                            emptyText="Belum ditandatangani — terisi dari pengguna yang sedang login." />
                                    </div>

                                    <div class="flex flex-wrap items-center justify-end gap-2 pt-2 border-t border-hairline dark:border-gray-700">
                                        @if ($terkunci)
                                            @can('dokumen.bukaKunci')
                                                <x-confirm-button action="bukaKunci()" color="amber"
                                                    title="Buka Kunci Review"
                                                    message="Buka kunci review pengkajian ini? Tanda tangan akan dicabut.">Buka Kunci</x-confirm-button>
                                            @endcan
                                        @else
                                            <x-secondary-button type="button" wire:click="simpanDraft">Simpan Draft</x-secondary-button>
                                        @endif
                                    </div>
                                </div>
                            </x-border-form>

                            {{-- ── DASAR ACUAN ── --}}
                            <div class="px-4 py-3 text-xs border rounded-2xl bg-surface-soft border-hairline text-muted dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400">
                                <span class="font-semibold text-ink dark:text-gray-300">Dasar Acuan:</span>
                                Standar Akreditasi Kemenkes (Elemen Penilaian PP 1.2 poin e) &mdash; pengkajian medis
                                yang dilakukan sebelum masuk rawat inap atau sebelum menjalani prosedur di layanan
                                rawat jalan dalam waktu kurang atau sama dengan 30 (tiga puluh) hari sebelumnya.
                                Jika lebih dari 30 hari, maka harus dilakukan pengkajian ulang.
                            </div>
                        </div>

                        {{-- ═══════════ KOLOM KANAN ═══════════ --}}
                        <div class="space-y-4">

                            {{-- RIWAYAT PENGKAJIAN — review yang SUDAH pernah dibuat.
                                 Sumbernya RSTXN_PENGKAJIAN_REVIEWS lewat REG_NO. Beda dari
                                 panel di bawahnya: yang ini "siapa pernah meninjau apa",
                                 yang di bawah "kunjungan apa saja yang pernah ada". --}}
                            <x-border-form :title="__('Riwayat Pengkajian')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
                                <div class="px-3 py-3 space-y-2 overflow-y-auto max-h-96">
                                    @forelse ($riwayatReview as $review)
                                        @php
                                            $ulang = $review['keputusan'] === 'ULANG';
                                            $garis = $ulang
                                                ? 'border-l-red-400 dark:border-l-red-500'
                                                : 'border-l-emerald-400 dark:border-l-emerald-500';
                                        @endphp

                                        <div class="py-3 pl-3 pr-3 border border-l-4 border-hairline {{ $garis }} rounded-xl text-ink dark:border-gray-700">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                {{-- reviewDate = kapan peninjauannya dilakukan; beda dari
                                                     tglPengkajian di bawah (kapan pengkajiannya dibuat). --}}
                                                <div class="flex items-baseline gap-2">
                                                    <span class="text-xs text-muted dark:text-gray-400">Tgl Review</span>
                                                    <span class="font-mono text-sm font-semibold text-ink dark:text-gray-200">{{ $review['tglReview'] ?: '-' }}</span>
                                                </div>
                                                <div class="flex flex-wrap items-center gap-1">
                                                    <x-badge :variant="$ulang ? 'danger' : 'success'">
                                                        {{ $ulang ? 'Pengkajian Ulang' : 'Review / Verifikasi' }}
                                                    </x-badge>
                                                    @if ($review['terkunci'])
                                                        <x-badge variant="success">Terkunci</x-badge>
                                                    @else
                                                        <x-badge variant="warning">Draft</x-badge>
                                                    @endif
                                                    @if ($review['iniKunjunganIni'])
                                                        <x-badge variant="info">Kunjungan ini</x-badge>
                                                    @endif
                                                </div>
                                            </div>

                                            <dl class="mt-2 space-y-0.5 text-sm">
                                                <div class="flex gap-2">
                                                    <dt class="shrink-0 w-28 text-muted dark:text-gray-400">Pengkajian</dt>
                                                    <dd class="text-ink dark:text-gray-200">
                                                        {{ $review['tglPengkajian'] ?: '-' }}
                                                        @if (!is_null($review['usiaHari']))
                                                            <span class="text-muted dark:text-gray-400">&middot; usia {{ $review['usiaHari'] }} hari</span>
                                                        @endif
                                                    </dd>
                                                </div>
                                                <div class="flex gap-2">
                                                    <dt class="shrink-0 w-28 text-muted dark:text-gray-400">Sumber</dt>
                                                    <dd class="text-ink dark:text-gray-200">
                                                        @if ($review['sumberJenis'] === 'LUAR')
                                                            Luar RS{{ filled($review['sumberDeskripsi']) ? ' — ' . $review['sumberDeskripsi'] : '' }}
                                                        @elseif (filled($review['sumberNo']))
                                                            <span class="font-mono">{{ $review['sumberJenis'] }} {{ $review['sumberNo'] }}</span>
                                                        @else
                                                            {{ $review['sumberJenis'] ?: '-' }}
                                                        @endif
                                                    </dd>
                                                </div>
                                                <div class="flex gap-2">
                                                    <dt class="shrink-0 w-28 text-muted dark:text-gray-400">Dipakai di</dt>
                                                    <dd class="font-mono text-ink dark:text-gray-200">{{ $review['pemakaiJenis'] }} {{ $review['pemakaiNo'] }}</dd>
                                                </div>
                                                @if ($review['tindakan'] !== [])
                                                    <div class="flex gap-2">
                                                        <dt class="shrink-0 w-28 text-muted dark:text-gray-400">Tindakan</dt>
                                                        <dd class="text-ink dark:text-gray-200">
                                                            <ul class="list-disc list-inside">
                                                                @foreach ($review['tindakan'] as $tindakan)
                                                                    <li>{{ $tindakan }}</li>
                                                                @endforeach
                                                            </ul>
                                                        </dd>
                                                    </div>
                                                @endif
                                                @if ($review['adaPerubahan'] === 'Y')
                                                    <div class="flex gap-2">
                                                        <dt class="shrink-0 w-28 text-muted dark:text-gray-400">Perubahan</dt>
                                                        <dd class="text-ink dark:text-gray-200">{{ $review['perubahanDesc'] ?: 'Ada perubahan bermakna' }}</dd>
                                                    </div>
                                                @endif
                                                @if (filled($review['catatan']))
                                                    <div class="flex gap-2">
                                                        <dt class="shrink-0 w-28 text-muted dark:text-gray-400">Catatan</dt>
                                                        <dd class="text-ink dark:text-gray-200">{{ $review['catatan'] }}</dd>
                                                    </div>
                                                @endif
                                                {{-- Penanda tangan paling bawah — urutan baku modul dokumen:
                                                     isinya dulu, tanda tangannya menutup. --}}
                                                <div class="flex gap-2 pt-2 mt-2 border-t border-hairline dark:border-gray-700">
                                                    <dt class="shrink-0 w-28 text-muted dark:text-gray-400">Ditinjau oleh</dt>
                                                    <dd class="text-ink dark:text-gray-200">{{ $review['ttdNama'] ?: 'Belum ditandatangani' }}</dd>
                                                </div>
                                            </dl>

                                            {{-- BARIS AKSI — mengikuti pola CPPT (rm-cppt-ri-actions):
                                                 satu baris x-outline-button ikon, warna menandai makna aksi,
                                                 judul hover sebagai penjelas, wire:confirm untuk yang berisiko.
                                                   biru    = buka rekam medis        indigo = buka pengkajian sumber
                                                   amber   = cetak                   abu    = batalkan/buka kunci
                                                   merah   = hapus                                                  --}}
                                            <div class="flex flex-wrap items-center w-full gap-1.5 mt-3">
                                                @if ($review['pemakaiJenis'] === 'RJ' && $review['pemakaiNo'] > 0)
                                                    <x-outline-button type="button"
                                                        wire:click="lihatRekamMedisPemakai({{ (int) $review['pemakaiNo'] }})"
                                                        wire:loading.attr="disabled"
                                                        class="!text-blue-600 !bg-blue-50 !border-blue-200 hover:!bg-blue-100 hover:!text-blue-700 hover:!border-blue-300 dark:!text-blue-400 dark:!bg-blue-900/20 dark:!border-blue-800/30 dark:hover:!bg-blue-900/30 dark:hover:!text-blue-300"
                                                        title="Rekam medis kunjungan yang memakai review ini">
                                                        <span class="inline-flex items-center gap-1">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                            </svg>
                                                            <span class="text-sm font-semibold">Rekam Medis</span>
                                                        </span>
                                                    </x-outline-button>
                                                @endif

                                                {{-- Kunjungan SUMBER — isi pengkajian yang ditinjau. Hanya muncul
                                                     kalau nomornya terekam; review lama bisa tersimpan tanpa nomor. --}}
                                                @if ($review['sumberJenis'] === 'RJ' && filled($review['sumberNo']))
                                                    <x-outline-button type="button"
                                                        wire:click="lihatPengkajianSumber({{ (int) $review['sumberNo'] }})"
                                                        wire:loading.attr="disabled"
                                                        class="!text-indigo-600 !bg-indigo-50 !border-indigo-200 hover:!bg-indigo-100 hover:!text-indigo-700 hover:!border-indigo-300 dark:!text-indigo-400 dark:!bg-indigo-900/20 dark:!border-indigo-800/30 dark:hover:!bg-indigo-900/30 dark:hover:!text-indigo-300"
                                                        title="Isi pengkajian yang ditinjau">
                                                        <span class="inline-flex items-center gap-1">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                            </svg>
                                                            <span class="text-sm font-semibold">Isi Pengkajian</span>
                                                        </span>
                                                    </x-outline-button>
                                                @endif

                                                <x-outline-button type="button"
                                                    wire:click="cetak({{ (int) $review['reviewNo'] }})"
                                                    wire:loading.attr="disabled" wire:target="cetak({{ (int) $review['reviewNo'] }})"
                                                    class="!text-amber-600 !bg-amber-50 !border-amber-200 hover:!bg-amber-100 hover:!text-amber-700 hover:!border-amber-300 dark:!text-amber-400 dark:!bg-amber-900/20 dark:!border-amber-800/30 dark:hover:!bg-amber-900/30 dark:hover:!text-amber-300"
                                                    title="Cetak review pengkajian">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                    </svg>
                                                </x-outline-button>

                                                @if ($review['terkunci'])
                                                    @can('dokumen.bukaKunci')
                                                        <x-outline-button type="button"
                                                            wire:click="bukaKunciReview({{ (int) $review['reviewNo'] }})"
                                                            wire:confirm="Buka kunci review ini? TTD dokter akan dicabut & review kembali menjadi draft. Barisnya tetap tersimpan."
                                                            wire:loading.attr="disabled"
                                                            class="!text-muted !bg-surface-soft !border-hairline hover:!bg-surface-soft hover:!text-body hover:!border-gray-300 dark:!text-muted-soft dark:!bg-gray-800/40 dark:!border-gray-700 dark:hover:!bg-gray-800/60"
                                                            title="Buka kunci review">
                                                            <span class="inline-flex items-center gap-1">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                        d="M8 11V7a4 4 0 118 0m-8 4h10a2 2 0 012 2v5a2 2 0 01-2 2H8a2 2 0 01-2-2v-5a2 2 0 012-2z" />
                                                                </svg>
                                                                <span class="text-sm font-semibold">Buka Kunci</span>
                                                            </span>
                                                        </x-outline-button>
                                                    @endcan
                                                @else
                                                    @can('dokumen.hapus')
                                                        <x-outline-button type="button"
                                                            wire:click="hapusReview({{ (int) $review['reviewNo'] }})"
                                                            wire:confirm="Yakin hapus review pengkajian ini?"
                                                            wire:loading.attr="disabled"
                                                            class="ml-auto !text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                            title="Hapus review">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </x-outline-button>
                                                    @endcan
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <p class="py-2 text-sm text-muted dark:text-gray-400">Belum pernah ada review pengkajian untuk pasien ini.</p>
                                    @endforelse

                                    @if ($riwayatReviewTotal > count($riwayatReview))
                                        <p class="py-1 text-xs text-muted dark:text-gray-400">
                                            Menampilkan {{ count($riwayatReview) }} terbaru dari {{ $riwayatReviewTotal }} review.
                                        </p>
                                    @endif
                                </div>
                            </x-border-form>

                            {{-- REKAM MEDIS PASIEN — DINONAKTIFKAN SEMENTARA atas permintaan user.

                                 Isinya komponen bersama `rekam-medis-display` (daftar kunjungan pasien
                                 + pencarian + filter + paginasi). Dimatikan dulu karena panel Riwayat
                                 Pengkajian di atas sudah punya tombol "Rekam Medis" per baris.

                                 KALAU DIHIDUPKAN LAGI: buang mount `cetak-rekam-medis-open` di
                                 pelayanan-rj.blade.php, sebab komponen ini memasangnya sendiri dan
                                 dua pendengar `cetak-rekam-medis.open` akan sama-sama membuka modal
                                 bernama `preview-rekam-medis`.

                                 Komentar di bawah sengaja TANPA {{ }} bersarang — komentar Blade tak
                                 bisa bersarang, penutup pertama akan mengakhiri seluruh blok.

                            <x-border-form :title="__('Rekam Medis Pasien')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
                                <div class="px-2 py-2">
                                    <livewire:pages::components.rekam-medis.rekam-medis-display.rekam-medis-display
                                        :regNo="$regNo ?? ''" :rjNoRefCopyTo="$rjNo ?? 0"
                                        wire:key="pengkajian-medis-rj-riwayat-{{ $regNo ?? 'new' }}" />
                                </div>
                            </x-border-form>
                            --}}

                            {{-- KETERANGAN SISTEM --}}
                            <section class="px-4 py-3 space-y-2 text-sm border rounded-2xl bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-900/10 dark:border-amber-800 dark:text-amber-200">
                                <div class="text-xs font-semibold tracking-wide uppercase">Keterangan Sistem</div>
                                <p>
                                    Usia pengkajian = selisih <strong>Tanggal hari ini</strong> dan
                                    <strong>Tgl pengkajian sebelumnya</strong>. Keduanya bisa diedit, jadi
                                    usianya mengikuti tanggal yang tertulis di bagian 2 &mdash; bukan selalu
                                    tanggal hari ini.
                                </p>
                                <p>
                                    Dari usia itu sistem menetapkan keputusan: &le; 30 hari &rarr;
                                    review/verifikasi, &gt; 30 hari &rarr; wajib pengkajian ulang.
                                    Keputusannya tak bisa dipilih petugas.
                                </p>
                                <p>
                                    Centang di bagian 4 <strong>diprasetel</strong> mengikuti keputusan itu,
                                    tapi tetap boleh diubah &mdash; yang tersimpan adalah centang yang terlihat.
                                </p>
                                <p>
                                    Tanggal hari ini tak boleh lebih awal dari tanggal pengkajian, dan tanggal
                                    pengkajian tak boleh di masa depan.
                                </p>
                                <p>
                                    Nomor kunjungan sumber dicocokkan otomatis dari jenis + tanggal pengkajian.
                                    Bila tak ada kunjungan yang cocok, sumbernya tersimpan tanpa nomor dan
                                    tombol <strong>Isi Pengkajian</strong> tak muncul di riwayat.
                                </p>
                                <p>
                                    Tanda tangan dokter mengunci review. Buka kunci mencabut tanda tangannya,
                                    tapi barisnya tetap tersimpan sebagai jejak.
                                </p>
                            </section>
                        </div>
                    </div>
                @endif
            </div>

            {{-- ══ FOOTER ══ --}}
            <div
                class="sticky bottom-0 z-10 flex items-center justify-end gap-2 px-6 py-3 border-t border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700">
                <x-secondary-button type="button"
                    x-on:click="$dispatch('close-modal', { name: 'pengkajian-medis-rj' })">Tutup</x-secondary-button>
            </div>
        </div>
    </x-modal>
</div>
