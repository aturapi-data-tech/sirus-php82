<?php

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    /**
     * IRISAN dokumen: cabang `perencanaan` — sekaligus model form
     * (jalur validasi & wire:model kini `perencanaan.*`).
     */
    public array $perencanaan = [];

    /** Skalar identitas dokter (dipakai guard TTD-E & partial Petugas Medis). */
    public string $drId = '';
    public string $drDesc = '';

    /**
     * Key tingkat-atas yang MEMANG dikelola komponen ini. null = tidak ada di dokumen
     * saat dimuat, sehingga tidak ikut dipatch — menjaga perilaku isset() yang lama.
     *
     * statusPRB SENGAJA tidak dipegang di sini: nilainya ARRAY (statusPRB.penanggungJawab.*)
     * milik E-Resep, dan komponen ini tak pernah mengubahnya. Dulu ia diketik ?string lalu
     * di-cast → "Array to string conversion" begitu PRB pernah di-toggle; menyalin baliknya
     * pun hanya berisiko menimpa toggle E-Resep dengan salinan basi.
     */
    public ?string $ermStatus = null;

    /**
     * Cuplikan prasyarat TTD-E dokter: tujuh nilai milik cabang LAIN (pemeriksaan & anamnesa)
     * yang divalidasi sebelum dokter menandatangani. Dulu dibaca dari salinan dokumen penuh;
     * kini hanya tujuh nilai itu yang ditahan, dengan bentuk bersarang yang sama supaya jalur
     * aturan validasi tetap sah.
     *
     * CATATAN (perilaku lama dipertahankan): cuplikan ini diambil saat openPerencanaan(),
     * sedangkan komponen pemeriksaan/anamnesa adalah SAUDARA yang mengedit dokumen yang sama —
     * jadi nilainya bisa basi. Membacanya segar dari DB lebih benar, tapi itu perubahan
     * perilaku, bukan bagian dari perapian properti ini.
     */
    public array $prasyaratTtd = [];

    /** Penanda kunjungan sudah dimuat lewat openPerencanaan(). */
    public bool $dokumenTermuat = false;

    // renderVersions
    public array $renderVersions = [];
    protected array $renderAreas = ['modal-perencanaan-rj'];

    // Untuk modal E-Resep
    public string $isOpenModeEresepRJ = 'insert';
    public string $activeTabRacikanNonRacikan = 'NonRacikan';
    public array $EmrMenuRacikanNonRacikan = [['ermMenuId' => 'NonRacikan', 'ermMenuName' => 'NonRacikan'], ['ermMenuId' => 'Racikan', 'ermMenuName' => 'Racikan']];

    /* ===============================
     | MOUNT
     =============================== */
    /**
     * rjNo datang lewat PROP dari emr-rj (seksi lahir di dalam @if($rjNo)), bukan lagi lewat
     * event open-rm-perencanaan-rj: satu kali baca CLOB, tidak ada race urutan event.
     * Handler #[On] tetap dipertahankan untuk pemanggil dari luar modal.
     */
    public function mount(?int $rjNo = null): void
    {
        $this->registerAreas(['modal-perencanaan-rj']);

        if (filled($rjNo)) {
            $this->openPerencanaan($rjNo);
        }
    }

    public function rendering(): void
    {
        $default = $this->getDefaultPerencanaan();
        $this->perencanaan = array_replace_recursive($default, $this->perencanaan);
    }
    /** Dokumen dibaca sebagai variabel LOKAL; hanya irisan + skalar + cuplikan yang disimpan. */
    private function muatDariDokumen(array $data): void
    {
        $this->perencanaan = $data['perencanaan'] ?? [];
        $this->drId = (string) ($data['drId'] ?? '');
        $this->drDesc = (string) ($data['drDesc'] ?? '');
        $this->ermStatus = array_key_exists('ermStatus', $data) ? (string) $data['ermStatus'] : null;
        $this->prasyaratTtd = [
            'pemeriksaan' => [
                'tandaVital' => [
                    'frekuensiNadi'  => $data['pemeriksaan']['tandaVital']['frekuensiNadi'] ?? null,
                    'frekuensiNafas' => $data['pemeriksaan']['tandaVital']['frekuensiNafas'] ?? null,
                    'suhu'           => $data['pemeriksaan']['tandaVital']['suhu'] ?? null,
                ],
                'nutrisi' => [
                    'bb'  => $data['pemeriksaan']['nutrisi']['bb'] ?? null,
                    'tb'  => $data['pemeriksaan']['nutrisi']['tb'] ?? null,
                    'imt' => $data['pemeriksaan']['nutrisi']['imt'] ?? null,
                ],
            ],
            'anamnesa' => [
                'pengkajianPerawatan' => [
                    'jamDatang' => $data['anamnesa']['pengkajianPerawatan']['jamDatang'] ?? null,
                ],
            ],
        ];
        $this->dokumenTermuat = true;
    }


    /* ===============================
     | OPEN REKAM MEDIS - PERENCANAAN
     =============================== */
    #[On('open-rm-perencanaan-rj')]
    public function openPerencanaan($rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->rjNo = $rjNo;

        $this->resetForm();
        $this->resetValidation();

        $data = $this->findDataRJ($rjNo);

        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return;
        }

        $this->muatDariDokumen($data);

        // Initialize perencanaan data jika belum ada
        $this->perencanaan = $this->perencanaan ?: $this->getDefaultPerencanaan();

        // 🔥 INCREMENT: Refresh seluruh modal perencanaan
        $this->incrementVersion('modal-perencanaan-rj');

        // Cek status lock
        if ($this->checkEmrRJStatus($rjNo)) {
            $this->isFormLocked = true;
        }
    }

    /* ===============================
     | GET DEFAULT PERENCANAAN STRUCTURE
     =============================== */
    private function getDefaultPerencanaan(): array
    {
        return [
            'pengkajianMedisTab' => 'Petugas Medis',
            'pengkajianMedis' => [
                'waktuPemeriksaan' => '',
                'selesaiPemeriksaan' => '',
                'drPemeriksa' => '',
                // Terisi {nama, kode, role} bila TTD-E dilakukan perwakilan (setDrPemeriksaPerwakilan).
                'diwakilkanOleh' => [],
            ],

            'tindakLanjutTab' => 'Tindak Lanjut',
            'tindakLanjut' => [
                'tindakLanjut' => '',
                'keteranganTindakLanjut' => '',
                'tindakLanjutOptions' => [['tindakLanjut' => 'MRS'], ['tindakLanjut' => 'Kontrol'], ['tindakLanjut' => 'Rujuk'], ['tindakLanjut' => 'Perawatan Selesai'], ['tindakLanjut' => 'PRB'], ['tindakLanjut' => 'Lain-lain']],
            ],

            // Kondisi Saat Meninggalkan Fasyankes (Playbook RJ bab 25) — label pilihan; kode
            // SNOMED-nya tetap dari App\Support\Terminologi\KondisiPulang (tak perlu coding).
            'kondisiPulang' => [
                'kondisiPulang' => '',
            ],

            // Prognosis (Playbook RJ bab 22) — label pilihan + keterangan; kode SNOMED-nya tetap
            // dari App\Support\Terminologi\Prognosis (tak perlu coding).
            'prognosis' => [
                'prognosis' => '',
                'keteranganPrognosis' => '',
            ],

            'terapiTab' => 'Terapi',
            'terapi' => [
                'terapi' => '',
            ],

            // 'rawatInapTab' => 'Rawat Inap',
            // 'rawatInap' => [
            //     'noRef' => '',
            //     'tanggal' => '', //dd/mm/yyyy
            //     'keterangan' => '',
            // ],

            // 'dischargePlanningTab' => 'Discharge Planning', // TIDAK DIPAKAI
            // 'dischargePlanning' => [                         // TIDAK DIPAKAI
            //     'pelayananBerkelanjutan' => [
            //         'pelayananBerkelanjutan' => 'Tidak Ada',
            //         'pelayananBerkelanjutanOption' => [
            //             ['pelayananBerkelanjutan' => 'Tidak Ada'],
            //             ['pelayananBerkelanjutan' => 'Ada']
            //         ],
            //     ],
            //     'pelayananBerkelanjutanOpsi' => [
            //         'rawatLuka' => [],
            //         'dm' => [],
            //         'ppok' => [],
            //         'hivAids' => [],
            //         'dmTerapiInsulin' => [],
            //         'ckd' => [],
            //         'tb' => [],
            //         'stroke' => [],
            //         'kemoterapi' => [],
            //     ],
            //     'penggunaanAlatBantu' => [
            //         'penggunaanAlatBantu' => 'Tidak Ada',
            //         'penggunaanAlatBantuOption' => [
            //             ['penggunaanAlatBantu' => 'Tidak Ada'],
            //             ['penggunaanAlatBantu' => 'Ada']
            //         ],
            //     ],
            //     'penggunaanAlatBantuOpsi' => [
            //         'kateterUrin' => [],
            //         'ngt' => [],
            //         'traechotomy' => [],
            //         'colostomy' => [],
            //     ],
            // ],
        ];
    }

    /* ===============================
     | SYNC JSON — private helper
     | Dipanggil dari dalam transaksi yang sudah ada lockRJRow()-nya.
     | Tidak membungkus transaction/lock sendiri untuk menghindari nested.
     =============================== */
    private function syncPerencanaanJson(): void
    {
        $data = $this->findDataRJ($this->rjNo) ?? [];

        if (empty($data)) {
            throw new \RuntimeException('Data RJ tidak ditemukan, simpan dibatalkan.');
        }

        // Set hanya key milik komponen ini — key lain tidak tersentuh
        $data['perencanaan'] = $this->perencanaan;

        // ermStatus dikelola dari setDrPemeriksa
        if ($this->ermStatus !== null) {
            $data['ermStatus'] = $this->ermStatus;
        }

        $this->updateJsonRJ($this->rjNo, $data);
        $this->muatDariDokumen($data);
    }

    /* ===============================
     | SAVE — standalone via #[On] event (tombol simpan manual)
     =============================== */
    #[On('save-rm-perencanaan-rj')]
    public function save(bool $silent = false): void
    {
        // 1. Read-only guard — selalu dengan toast
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menyimpan data.');
            return;
        }

        // 2. Guard: properti lokal belum ter-load
        if (!$this->dokumenTermuat) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan, silakan buka ulang form.');
            return;
        }

        // 3. Validasi Livewire rules
        $this->validateWithToast();

        try {
            DB::transaction(function () {
                // 4. Lock row di DB (SELECT FOR UPDATE) — cegah race condition
                $this->lockRJRow($this->rjNo);

                // Tangkap status baru/lama sebelum sync (key perencanaan belum ada saat pertama disimpan)
                $dbData = $this->findDataRJ($this->rjNo) ?? [];
                $isBaru = empty($dbData['perencanaan']);

                // 5. Sync JSON via helper
                $this->syncPerencanaanJson();

                // 6. Audit log
                $this->appendAdminLogRJ((int) $this->rjNo, ($isBaru ? 'Buat' : 'Update') . ' Perencanaan RJ — waktu pemeriksaan ' . ($this->perencanaan['pengkajianMedis']['waktuPemeriksaan'] ?? '-'), 'MR');
            });

            $this->afterSave('Perencanaan berhasil disimpan.', $silent);
        } catch (\RuntimeException $e) {
            // lockRJRow() / syncPerencanaanJson() throws RuntimeException
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | VALIDASI SEBELUM DOKTER TTD
     =============================== */
    private function validateBeforeDrPemeriksa(): void
    {
        try {
            $this->validateWithToast(
                [
                    'prasyaratTtd.pemeriksaan.tandaVital.frekuensiNadi' => 'required|numeric',
                    'prasyaratTtd.pemeriksaan.tandaVital.frekuensiNafas' => 'required|numeric',
                    'prasyaratTtd.pemeriksaan.tandaVital.suhu' => 'required|numeric',
                    'prasyaratTtd.pemeriksaan.nutrisi.bb' => 'required|numeric',
                    'prasyaratTtd.pemeriksaan.nutrisi.tb' => 'required|numeric',
                    'prasyaratTtd.pemeriksaan.nutrisi.imt' => 'required|numeric',
                    'prasyaratTtd.anamnesa.pengkajianPerawatan.jamDatang' => 'required|date_format:d/m/Y H:i:s',
                ],
                [
                    'required' => ':attribute wajib diisi.',
                    'numeric' => ':attribute harus berupa angka.',
                    'date_format' => ':attribute harus dalam format dd/mm/yyyy hh:mi:ss.',
                ],
                [
                    'prasyaratTtd.pemeriksaan.tandaVital.frekuensiNadi' => 'Frekuensi Nadi',
                    'prasyaratTtd.pemeriksaan.tandaVital.frekuensiNafas' => 'Frekuensi Nafas',
                    'prasyaratTtd.pemeriksaan.tandaVital.suhu' => 'Suhu',
                    'prasyaratTtd.pemeriksaan.nutrisi.bb' => 'Berat Badan',
                    'prasyaratTtd.pemeriksaan.nutrisi.tb' => 'Tinggi Badan',
                    'prasyaratTtd.pemeriksaan.nutrisi.imt' => 'Indeks Massa Tubuh',
                    'prasyaratTtd.anamnesa.pengkajianPerawatan.jamDatang' => 'Waktu Datang',
                ],
            );
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak dapat melakukan TTD-E karena data pemeriksaan belum lengkap.');
            throw $e;
        }
    }

    /* ===============================
     | SET DOKTER PEMERIKSA (TTD)
     =============================== */
    public function setDrPemeriksa(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $myUserCodeActive = auth()->user()->myuser_code;
        $myUserNameActive = auth()->user()->myuser_name;

        // Validasi data pemeriksaan sudah lengkap sebelum masuk lock
        try {
            $this->validateBeforeDrPemeriksa();
        } catch (ValidationException $e) {
            // Pesan sudah di-dispatch di dalam validateBeforeDrPemeriksa()
            return;
        }

        if (!auth()->user()->hasRole('Dokter')) {
            $this->dispatch('toast', type: 'error', message: "Anda tidak dapat melakukan TTD-E karena User Role {$myUserNameActive} Bukan Dokter.");
            return;
        }

        if ($this->drId !== $myUserCodeActive) {
            $this->dispatch('toast', type: 'error', message: "Anda tidak dapat melakukan TTD-E karena Bukan Pasien {$myUserNameActive}.");
            return;
        }

        $this->stempelDrPemeriksa(null);
    }

    /**
     * Perwakilan (manager ke atas, Gate dokumen.ttdPerwakilan): boleh TTD-E atas nama
     * dokter kunjungan bila dirinya BUKAN dokter kunjungan itu. Pola sama dengan
     * Resume Medis RI — stempel tetap atas nama dokter, penekan tombol tercatat.
     */
    public function bolehTtdPerwakilan(): bool
    {
        $kodeSaya = trim((string) (auth()->user()->myuser_code ?? ''));

        return $this->drId !== '' && $kodeSaya !== $this->drId && auth()->user()->can('dokumen.ttdPerwakilan');
    }

    public function setDrPemeriksaPerwakilan(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        // Guard SERVER — wire:click memanggil method publik, guard blade saja bisa ditembus.
        if (!$this->bolehTtdPerwakilan()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak TTD-E atas nama dokter pemeriksa.');
            return;
        }

        try {
            $this->validateBeforeDrPemeriksa();
        } catch (ValidationException $e) {
            return;
        }

        $user = auth()->user();
        $this->stempelDrPemeriksa([
            'nama' => (string) ($user->myuser_name ?? $user->name ?? ''),
            'kode' => (string) ($user->myuser_code ?? ''),
            'role' => $user->getRoleNames()->implode(', '),
        ]);
    }

    /**
     * Tulis stempel TTD-E Dokter Pemeriksa + kunci EMR. Stempel SELALU atas nama dokter
     * kunjungan (drDesc/drId); bila $diwakilkanOleh terisi, penekan tombol dicatat di
     * `pengkajianMedis.diwakilkanOleh` dan di log.
     */
    private function stempelDrPemeriksa(?array $diwakilkanOleh): void
    {
        try {
            DB::transaction(function () use ($diwakilkanOleh) {
                // 1. Lock row dulu — update erm_status + JSON harus atomik dalam satu transaksi
                $this->lockRJRow($this->rjNo);

                $drDesc = $this->drDesc ?: 'Dokter Pemeriksa';
                $sekarang = Carbon::now()->format('d/m/Y H:i:s');

                // 2. Set data perencanaan
                $this->perencanaan['pengkajianMedis']['drPemeriksa'] = $drDesc;
                $this->perencanaan['pengkajianMedis']['diwakilkanOleh'] = $diwakilkanOleh ?? [];

                // Auto-isi waktu jika belum diisi. Bawaannya '' (bukan null), jadi pakai
                // blank() — `??=` tidak pernah mengisi string kosong.
                if (blank($this->perencanaan['pengkajianMedis']['waktuPemeriksaan'] ?? '')) {
                    $this->perencanaan['pengkajianMedis']['waktuPemeriksaan'] = $sekarang;
                }
                if (blank($this->perencanaan['pengkajianMedis']['selesaiPemeriksaan'] ?? '')) {
                    $this->perencanaan['pengkajianMedis']['selesaiPemeriksaan'] = $sekarang;
                }

                // 3. Update erm_status di header — dalam satu transaksi dengan JSON update
                $this->ermStatus = 'L';
                DB::table('rstxn_rjhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update(['erm_status' => 'L']);

                // 4. Sync JSON — row sudah di-lock, tidak perlu lock/transaction lagi
                $this->syncPerencanaanJson();

                // 5. Audit log
                $this->appendAdminLogRJ((int) $this->rjNo, 'TTD-E Dokter Pemeriksa (kunci EMR) — ' . $drDesc
                    . ($diwakilkanOleh ? ' (DIWAKILKAN oleh ' . $diwakilkanOleh['nama'] . ' [' . $diwakilkanOleh['role'] . '])' : '')
                    . ' @ ' . ($this->perencanaan['pengkajianMedis']['waktuPemeriksaan'] ?? '-'), 'MR');
            });

            $this->afterSave($diwakilkanOleh
                ? 'TTD-E atas nama ' . ($this->drDesc ?: 'dokter pemeriksa') . ' berhasil (perwakilan tercatat di log).'
                : 'TTD-E berhasil.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal TTD-E: ' . $e->getMessage());
        }
    }


    /* ===============================
     | BUKA KUNCI TTD-E DOKTER PEMERIKSA
     =============================== */
    /**
     * Cabut stempel TTD-E dokter supaya kunjungan ini bisa di-TTD ulang.
     *
     * x-signature.ttd-petugas hanya merender tombol TTD selama namanya masih
     * kosong ($signed = !empty($ttd)), jadi begitu ter-TTD tombolnya hilang dan
     * salah TTD tak punya jalan pulang. Ini padanan "Buka Kunci" modul dokumen:
     * yang dicabut HANYA stempel petugas, sedangkan waktu pemeriksaan DIPERTAHANKAN
     * karena itu data klinis yang bisa saja diketik sendiri, bukan cap tanda tangan.
     *
     * erm_status dikembalikan ke 'A' supaya kolom penanda kunci tidak berbohong,
     * meski hari ini checkEmrRJStatus() memang sengaja selalu false.
     */
    public function bukaKunciTtdPemeriksa(): void
    {
        // Guard SERVER — guard blade saja bisa ditembus, wire:click memanggil method publik.
        if (! auth()->user()?->can('dokumen.bukaKunci')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak membuka kunci TTD-E.');

            return;
        }

        if (blank($this->rjNo)) {
            return;
        }

        if (blank($this->perencanaan['pengkajianMedis']['drPemeriksa'] ?? '')) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada TTD-E yang perlu dibuka.');

            return;
        }

        try {
            DB::transaction(function () {
                $this->lockRJRow($this->rjNo);

                $drSebelumnya = $this->perencanaan['pengkajianMedis']['drPemeriksa'];

                $this->perencanaan['pengkajianMedis']['drPemeriksa'] = '';
                $this->perencanaan['pengkajianMedis']['selesaiPemeriksaan'] = '';
                $this->perencanaan['pengkajianMedis']['diwakilkanOleh'] = [];

                $this->ermStatus = 'A';
                DB::table('rstxn_rjhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update(['erm_status' => 'A']);

                $this->syncPerencanaanJson();

                $this->appendAdminLogRJ((int) $this->rjNo, 'Buka Kunci TTD-E Dokter Pemeriksa — stempel ' . $drSebelumnya . ' dicabut oleh ' . (auth()->user()->myuser_name ?? '-'), 'MR');
            });

            $this->afterSave('Kunci TTD-E dibuka — dokter bisa TTD ulang.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuka kunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | OPEN MODAL E-RESEP
     =============================== */
    public function openModalEresepRJ(): void
    {
        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor kunjungan tidak ditemukan.');
            return;
        }

        $this->dispatch('emr-rj.eresep.open', rjNo: $this->rjNo);
        $this->dispatch('open-eresep-non-racikan-rj', rjNo: $this->rjNo);
        $this->dispatch('open-eresep-racikan-rj', rjNo: $this->rjNo);
    }

    /* ===============================
     | VALIDATION RULES
     =============================== */
    protected function rules(): array
    {
        return [
            'perencanaan.pengkajianMedis.waktuPemeriksaan' => 'nullable|date_format:d/m/Y H:i:s',
            'perencanaan.pengkajianMedis.selesaiPemeriksaan' => 'nullable|date_format:d/m/Y H:i:s',
            'perencanaan.rawatInap.tanggal' => 'nullable|date_format:d/m/Y',
        ];
    }

    protected function messages(): array
    {
        return [
            'perencanaan.pengkajianMedis.waktuPemeriksaan.date_format' => ':attribute harus dalam format dd/mm/yyyy hh:mi:ss',
            'perencanaan.pengkajianMedis.selesaiPemeriksaan.date_format' => ':attribute harus dalam format dd/mm/yyyy hh:mi:ss',
            'perencanaan.rawatInap.tanggal.date_format' => ':attribute harus dalam format dd/mm/yyyy',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'perencanaan.pengkajianMedis.waktuPemeriksaan' => 'Waktu Pemeriksaan',
            'perencanaan.pengkajianMedis.selesaiPemeriksaan' => 'Selesai Pemeriksaan',
            'perencanaan.rawatInap.tanggal' => 'Tanggal Rawat Inap',
        ];
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rm-perencanaan-actions');
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function afterSave(string $message, bool $silent = false): void
    {
        $this->incrementVersion('modal-perencanaan-rj');
        $this->dispatch('refresh-after-rj.saved');

        // Silent saat dipanggil oleh save-all (mis. Simpan SKDP) → cegah toast bertumpuk.
        if (! $silent) {
            $this->dispatch('toast', type: 'success', message: $message);
        }
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
    }
};

?>

<div>
    {{-- CONTAINER UTAMA --}}
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-perencanaan-rj', [$rjNo ?? 'new']) }}">

        {{-- BODY --}}
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-6 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                {{-- jika perencanaan ada --}}
                @if (!empty($perencanaan))
                    {{-- Petugas Medis & Tindak Lanjut ditampilkan atas-bawah (tanpa tab) --}}
                    <div class="w-full space-y-6">
                        @include('pages.transaksi.rj.emr-rj.perencanaan.tabs.petugas-medis-tab')

                        @include('pages.transaksi.rj.emr-rj.perencanaan.tabs.tindak-lanjut-tab')
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Eresep RJ --}}
    <livewire:pages::transaksi.rj.eresep-rj.eresep-rj :rjNo="$rjNo" wire:key="eresep-rj-{{ $rjNo }}" />
</div>
