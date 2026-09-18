<?php
// resources/views/pages/transaksi/ugd/emr-ugd/perencanaan/rm-perencanaan-ugd-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
use Illuminate\Validation\ValidationException;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    /**
     * IRISAN dokumen: cabang `perencanaan` — sekaligus model form
     * (jalur validasi & wire:model kini `perencanaan.*`).
     */
    public array $perencanaan = [];

    /** Skalar identitas dokter (guard TTD-E & partial Petugas Medis). */
    public string $drId = '';
    public string $drDesc = '';

    /**
     * Cuplikan prasyarat TTD-E dokter: tujuh nilai milik cabang LAIN (pemeriksaan &
     * anamnesa) yang divalidasi sebelum dokter menandatangani. Bentuknya sengaja BERSARANG
     * seperti dokumen aslinya supaya jalur aturan validasi tetap sah.
     *
     * CATATAN (perilaku lama dipertahankan): cuplikan diambil saat open(), sedangkan
     * pemeriksaan-ugd & anamnesa-ugd adalah SAUDARA yang mengedit dokumen yang sama —
     * nilainya bisa basi. Sama seperti perencanaan-rj (85deee0f).
     */
    public array $prasyaratTtd = [];

    /** Penanda kunjungan sudah dimuat lewat open(). */
    public bool $dokumenTermuat = false;

    /** Dokumen dibaca sebagai variabel LOKAL; hanya irisan + skalar + cuplikan disimpan. */
    private function muatDariDokumen(array $data): void
    {
        $this->perencanaan = $data['perencanaan'] ?? [];
        $this->drId = (string) ($data['drId'] ?? '');
        $this->drDesc = (string) ($data['drDesc'] ?? '');
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

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-perencanaan-ugd'];

    // Untuk modal E-Resep
    public string $isOpenModeEresepRJ = 'insert';
    public string $activeTabRacikanNonRacikan = 'NonRacikan';
    public array $EmrMenuRacikanNonRacikan = [['ermMenuId' => 'NonRacikan', 'ermMenuName' => 'NonRacikan'], ['ermMenuId' => 'Racikan', 'ermMenuName' => 'Racikan']];

    /* ===============================
     | MOUNT
     =============================== */
    /**
     * rjNo datang lewat PROP dari induk EMR UGD (seksi lahir di dalam @if($rjNo)),
     * bukan lagi lewat event open-rm-*: satu kali baca CLOB, tidak ada race urutan event.
     * Handler #[On] tetap dipertahankan untuk pemanggil dari luar modal.
     */
    public function mount(?int $rjNo = null): void
    {
        $this->registerAreas(['modal-perencanaan-ugd']);

        if (filled($rjNo)) {
            $this->openPerencanaan($rjNo);
        }
    }

    public function rendering(): void
    {
        $default = $this->getDefaultPerencanaan();
        $this->perencanaan = array_replace_recursive($default, $this->perencanaan);

        // Daftar Tindak Lanjut selalu ikut default. Data UGD lama menyimpan opsi
        // "PRB" di JSON-nya, dan array_replace_recursive menggabungkan array
        // berindeks per posisi sehingga opsi itu ikut terbawa. PRB adalah program
        // khusus Rawat Jalan, jadi tidak lagi ditawarkan di UGD -- kecuali rekam
        // lama yang nilainya memang sudah "PRB", supaya nilainya tetap terbaca
        // dan tidak terhapus diam-diam saat disimpan ulang.
        $tindakLanjutOptions = $default['tindakLanjut']['tindakLanjutOptions'];

        if (($this->perencanaan['tindakLanjut']['tindakLanjut'] ?? '') === 'PRB') {
            $tindakLanjutOptions[] = ['tindakLanjut' => 'PRB'];
        }

        $this->perencanaan['tindakLanjut']['tindakLanjutOptions'] = $tindakLanjutOptions;
    }

    /* ===============================
     | OPEN
     =============================== */
    #[On('open-rm-perencanaan-ugd')]
    public function openPerencanaan($rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->rjNo = $rjNo;
        $this->resetForm();
        $this->resetValidation();

        $data = $this->findDataUGD($rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        $this->muatDariDokumen($data);
        $this->perencanaan = $this->perencanaan ?: $this->getDefaultPerencanaan();

        $this->incrementVersion('modal-perencanaan-ugd');

        if ($this->checkEmrUGDStatus($rjNo)) {
            $this->isFormLocked = true;
        }
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rm-perencanaan-ugd-actions');
    }

    /* ===============================
     | VALIDATION
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
     | SAVE
     =============================== */
    #[On('save-rm-perencanaan-ugd')]
    public function save(bool $silent = false): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menyimpan data.');
            return;
        }

        $this->validateWithToast();

        try {
            DB::transaction(function () {
                // 1. Lock row dulu
                $this->lockUGDRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataUGD($this->rjNo) ?? [];

                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
                }

                // Tangkap status sebelum overwrite (untuk verb log Buat/Update)
                $isBaru = empty($data['perencanaan']);

                // 3. Patch hanya key perencanaan
                $data['perencanaan'] = $this->perencanaan;

                $this->updateJsonUGD($this->rjNo, $data);
                $this->muatDariDokumen($data);

                // Audit log
                $this->appendAdminLogUGD((int) $this->rjNo, ($isBaru ? 'Buat' : 'Update') . ' Perencanaan UGD — waktu pemeriksaan ' . ($data['perencanaan']['pengkajianMedis']['waktuPemeriksaan'] ?? '-'), 'MR');
            });

            // 4. Notify — di luar transaksi
            $this->afterSave('Perencanaan berhasil disimpan.', $silent);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | SET DOKTER PEMERIKSA (TTD)
     |
     | erm_status update + JSON update harus atomik.
     | Tidak memanggil save() — menggunakan transaksi sendiri.
     =============================== */
    public function setDrPemeriksa(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $myUserCodeActive = auth()->user()->myuser_code;
        $myUserNameActive = auth()->user()->myuser_name;

        if (!auth()->user()->hasRole('Dokter')) {
            $this->dispatch('toast', type: 'error', message: "Anda tidak dapat melakukan TTD-E karena User Role {$myUserNameActive} Bukan Dokter");
            return;
        }

        if ($this->drId != $myUserCodeActive) {
            $this->dispatch('toast', type: 'error', message: "Anda tidak dapat melakukan TTD-E karena Bukan Pasien {$myUserNameActive}");
            return;
        }

        // Validasi kelengkapan data sebelum TTD
        try {
            $this->validateBeforeDrPemeriksa();
        } catch (ValidationException) {
            // Pesan error sudah di-dispatch di validateBeforeDrPemeriksa()
            return;
        }

        $drDesc = $this->drDesc ?: 'Dokter Pemeriksa';

        // Set property lokal
        $this->perencanaan['pengkajianMedis']['drPemeriksa'] = $drDesc;

        if (empty($this->perencanaan['pengkajianMedis']['waktuPemeriksaan'])) {
            $this->perencanaan['pengkajianMedis']['waktuPemeriksaan'] = Carbon::now()->format('d/m/Y H:i:s');
        }

        if (empty($this->perencanaan['pengkajianMedis']['selesaiPemeriksaan'])) {
            $this->perencanaan['pengkajianMedis']['selesaiPemeriksaan'] = Carbon::now()->format('d/m/Y H:i:s');
        }

        try {
            DB::transaction(function () use ($drDesc) {
                // 1. Lock row dulu — erm_status + JSON harus atomik
                $this->lockUGDRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataUGD($this->rjNo) ?? [];

                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
                }

                // 3. Update erm_status di header
                DB::table('rstxn_ugdhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update(['erm_status' => 'L']);

                // 4. Patch JSON dengan perencanaan terbaru + ermStatus
                $data['perencanaan'] = $this->perencanaan;
                $data['ermStatus'] = 'L';

                $this->updateJsonUGD($this->rjNo, $data);
                $this->muatDariDokumen($data);

                // 5. Audit log
                $this->appendAdminLogUGD((int) $this->rjNo, 'TTD-E Dokter Pemeriksa UGD — ' . $drDesc . ' (' . ($data['perencanaan']['pengkajianMedis']['waktuPemeriksaan'] ?? '-') . ')', 'MR');
            });

            $this->afterSave('TTD-E berhasil.');
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
     * meski hari ini checkEmrUGDStatus() memang sengaja selalu false.
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
                $this->lockUGDRow($this->rjNo);

                $drSebelumnya = $this->perencanaan['pengkajianMedis']['drPemeriksa'];

                // Baca data terkini SESUDAH lock — pola UGD (findDataUGD + updateJsonUGD),
                // bukan syncPerencanaanJson() seperti RJ.
                $data = $this->findDataUGD($this->rjNo) ?? [];

                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, buka kunci dibatalkan.');
                }

                $this->perencanaan['pengkajianMedis']['drPemeriksa'] = '';
                $this->perencanaan['pengkajianMedis']['selesaiPemeriksaan'] = '';

                DB::table('rstxn_ugdhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update(['erm_status' => 'A']);

                $data['perencanaan'] = $this->perencanaan;
                $data['ermStatus'] = 'A';

                $this->updateJsonUGD($this->rjNo, $data);
                $this->muatDariDokumen($data);

                $this->appendAdminLogUGD((int) $this->rjNo, 'Buka Kunci TTD-E Dokter Pemeriksa — stempel ' . $drSebelumnya . ' dicabut oleh ' . (auth()->user()->myuser_name ?? '-'), 'MR');
            });

            $this->afterSave('Kunci TTD-E dibuka — dokter bisa TTD ulang.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuka kunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | OPEN MODAL E-RESEP UGD
     =============================== */
    public function openModalEresepUGD(): void
    {
        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor kunjungan tidak ditemukan.');
            return;
        }

        $this->dispatch('emr-ugd.eresep.open', rjNo: $this->rjNo);
        $this->dispatch('open-eresep-non-racikan-ugd', rjNo: $this->rjNo);
        $this->dispatch('open-eresep-racikan-ugd', rjNo: $this->rjNo);
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
     | DEFAULT STRUCTURE
     =============================== */
    private function getDefaultPerencanaan(): array
    {
        return [
            'pengkajianMedisTab' => 'Petugas Medis',
            'pengkajianMedis' => [
                'waktuPemeriksaan' => '',
                'selesaiPemeriksaan' => '',
                'drPemeriksa' => '',
            ],

            'tindakLanjutTab' => 'Tindak Lanjut',
            'tindakLanjut' => [
                'tindakLanjut' => '',
                'keteranganTindakLanjut' => '',
                // 'Meninggal' DIKEMBALIKAN: opsi ini pernah ada (daftar lama: MRS, KRS,
                // APS, Rujuk, Meninggal, Lain-lain) lalu hilang saat daftar diubah, padahal
                // 97 record terlanjur memakainya. Akibatnya kematian UGD tak bisa dicatat
                // lagi di SOAP, dan RL 3.3 (Mati IGD & DOA) kehilangan sumbernya —
                // kolom rstxn_ugdhdrs.death_on_igd_status tak pernah ditulis 'Y' oleh
                // siapa pun. RL33Trait sekarang membaca kematian dari nilai ini.
                'tindakLanjutOptions' => [['tindakLanjut' => 'MRS'], ['tindakLanjut' => 'Kontrol'], ['tindakLanjut' => 'Rujuk'], ['tindakLanjut' => 'Perawatan Selesai'], ['tindakLanjut' => 'Meninggal'], ['tindakLanjut' => 'Lain-lain']],
            ],

            'terapiTab' => 'Terapi',
            'terapi' => ['terapi' => ''],
        ];
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function afterSave(string $message, bool $silent = false): void
    {
        $this->incrementVersion('modal-perencanaan-ugd');
        $this->dispatch('refresh-after-ugd.saved');

        // Silent saat save-all (mis. tombol E-Resep) → cegah toast bertumpuk.
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
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-perencanaan-ugd', [$rjNo ?? 'new']) }}">
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-6 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                @if ($dokumenTermuat)
                    <div class="w-full">
                        <div x-data="{ activeTab: '{{ $perencanaan['pengkajianMedisTab'] ?? 'Petugas Medis' }}' }" class="w-full">

                            {{-- TAB NAVIGATION --}}
                            <x-scrollable-tabs class="w-full px-2 mb-2 border-b border-hairline dark:border-gray-700">
                                <div class="flex flex-nowrap w-full gap-2 -mb-px">

                                    <x-tab variant="underline"
                                        active-expr="activeTab === '{{ $perencanaan['pengkajianMedisTab'] ?? 'Petugas Medis' }}'"
                                        x-on:click="activeTab = '{{ $perencanaan['pengkajianMedisTab'] ?? 'Petugas Medis' }}'">
                                        {{ $perencanaan['pengkajianMedisTab'] ?? 'Petugas Medis' }}
                                    </x-tab>

                                    <x-tab variant="underline"
                                        active-expr="activeTab === '{{ $perencanaan['tindakLanjutTab'] ?? 'Tindak Lanjut' }}'"
                                        x-on:click="activeTab = '{{ $perencanaan['tindakLanjutTab'] ?? 'Tindak Lanjut' }}'">
                                        {{ $perencanaan['tindakLanjutTab'] ?? 'Tindak Lanjut' }}
                                    </x-tab>

                                </div>
                            </x-scrollable-tabs>

                            {{-- TAB CONTENTS --}}
                            <div class="w-full p-4">

                                {{-- PETUGAS MEDIS --}}
                                <div class="w-full"
                                    x-show.transition.in.opacity.duration.600="activeTab === '{{ $perencanaan['pengkajianMedisTab'] ?? 'Petugas Medis' }}'">
                                    @include('pages.transaksi.ugd.emr-ugd.perencanaan.tabs.petugas-medis-tab')
                                </div>

                                {{-- TINDAK LANJUT --}}
                                <div class="w-full"
                                    x-show.transition.in.opacity.duration.600="activeTab === '{{ $perencanaan['tindakLanjutTab'] ?? 'Tindak Lanjut' }}'">
                                    @include('pages.transaksi.ugd.emr-ugd.perencanaan.tabs.tindak-lanjut-tab')
                                </div>

                            </div>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>

    {{-- Eresep UGD --}}
    <livewire:pages::transaksi.ugd.eresep-ugd.eresep-ugd :rjNo="$rjNo" wire:key="eresep-ugd-{{ $rjNo }}" />
</div>
