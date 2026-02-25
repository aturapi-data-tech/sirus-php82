<?php

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];

    // renderVersions
    public array $renderVersions = [];
    protected array $renderAreas = ['modal-pemeriksaan-rj'];

    /* ===============================
     | OPEN REKAM MEDIS PERAWAT - PEMERIKSAAN
     =============================== */
    public function openPemeriksaan(int $rjNo = null): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->resetForm();
        $this->resetValidation();
        // Ambil data kunjungan RJ
        $this->rjNo = $rjNo;
        $dataDaftarPoliRJ = $this->findDataRJ($rjNo);

        if (!$dataDaftarPoliRJ) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $dataDaftarPoliRJ;

        // Initialize pemeriksaan data if not exists
        if (!isset($this->dataDaftarPoliRJ['pemeriksaan'])) {
            $this->dataDaftarPoliRJ['pemeriksaan'] = $this->getDefaultPemeriksaan();
        }

        // ✅ Ambil data pasien dari master pasien (untuk alergi & riwayat penyakit)
        $pasienData = $this->findDataMasterPasien($dataDaftarPoliRJ['regNo']);

        // ✅ Isi alergi jika ada di data pasien
        if (isset($pasienData['pasien']['alergi'])) {
            // Masukkan ke struktur pemeriksaan
            $this->dataDaftarPoliRJ['pemeriksaan']['alergi']['alergi'] = $pasienData['pasien']['alergi'];
        }

        // ✅ Isi riwayat penyakit dahulu jika ada
        if (isset($pasienData['pasien']['riwayatPenyakitDahulu'])) {
            $this->dataDaftarPoliRJ['pemeriksaan']['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] = $pasienData['pasien']['riwayatPenyakitDahulu'];
        }

        // 🔥 INCREMENT: Refresh seluruh modal pemeriksaan
        $this->incrementVersion('modal-pemeriksaan-rj');

        // Cek status lock
        if ($this->checkEmrRJStatus($rjNo)) {
            $this->isFormLocked = true;
        }
    }

    /* ===============================
     | GET DEFAULT PEMERIKSAAN STRUCTURE
     =============================== */
    private function getDefaultPemeriksaan(): array
    {
        return [
            'umumTab' => 'Umum',
            'tandaVital' => [
                'keadaanUmum' => '',
                'tingkatKesadaran' => '',
                'tingkatKesadaranOptions' => [['tingkatKesadaran' => 'Sadar Baik / Alert'], ['tingkatKesadaran' => 'Berespon Dengan Kata-Kata / Voice'], ['tingkatKesadaran' => 'Hanya Beresponse Jika Dirangsang Nyeri / Pain'], ['tingkatKesadaran' => 'Pasien Tidak Sadar / Unresponsive'], ['tingkatKesadaran' => 'Gelisah Atau Bingung'], ['tingkatKesadaran' => 'Acute Confusional States']],
                'sistolik' => '',
                'distolik' => '',
                'frekuensiNafas' => '',
                'frekuensiNadi' => '',
                'suhu' => '',
                'spo2' => '',
                'gda' => '',
                'waktuPemeriksaan' => '',
            ],

            'nutrisi' => [
                'bb' => '',
                'tb' => '',
                'imt' => '',
                'lk' => '',
                'lila' => '',
            ],

            'fungsional' => [
                'alatBantu' => '',
                'prothesa' => '',
                'cacatTubuh' => '',
            ],

            'fisik' => '',

            'anatomi' => [
                'kepala' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'mata' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'telinga' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'hidung' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'rambut' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'bibir' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'gigiGeligi' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'lidah' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'langitLangit' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'leher' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'tenggorokan' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'tonsil' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'dada' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'payudarah' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'punggung' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'perut' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'genital' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'anus' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'lenganAtas' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'lenganBawah' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'jariTangan' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'kukuTangan' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'persendianTangan' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'tungkaiAtas' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'tungkaiBawah' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'jariKaki' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'kukuKaki' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'persendianKaki' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'faring' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
            ],

            'suspekAkibatKerja' => [
                'suspekAkibatKerja' => '',
                'keteranganSuspekAkibatKerja' => '',
                'suspekAkibatKerjaOptions' => [['suspekAkibatKerja' => 'Ya'], ['suspekAkibatKerja' => 'Tidak']],
            ],

            'FisikujiFungsi' => [
                'FisikujiFungsi' => '',
            ],

            'eeg' => [
                'hasilPemeriksaan' => '',
                'hasilPemeriksaanSebelumnya' => '',
                'mriKepala' => '',
                'hasilPerekaman' => '',
                'kesimpulan' => '',
                'saran' => '',
            ],

            'emg' => [
                'keluhanPasien' => '',
                'pengobatan' => '',
                'td' => '',
                'rr' => '',
                'hr' => '',
                's' => '',
                'gcs' => '',
                'fkl' => '',
                'nprs' => '',
                'rclRctl' => '',
                'nnCr' => '',
                'nnCrLain' => '',
                'motorik' => '',
                'pergerakan' => '',
                'kekuatan' => '',
                'extremitasSuperior' => '',
                'extremitasInferior' => '',
                'tonus' => '',
                'refleksFisiologi' => '',
                'refleksPatologis' => '',
                'sensorik' => '',
                'otonom' => '',
                'emcEmgFindings' => '',
                'impresion' => '',
            ],

            'ravenTest' => [
                'skoring' => '',
                'presentil' => '',
                'interpretasi' => '',
                'anjuran' => '',
            ],

            'penunjang' => '',
        ];
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rm-pemeriksaan-actions');
    }

    protected function rules(): array
    {
        $rules['dataDaftarPoliRJ.pemeriksaan.tandaVital.waktuPemeriksaan'] = 'date_format:d/m/Y H:i:s';
        return $rules;
    }

    protected function messages(): array
    {
        return [
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.waktuPemeriksaan.date_format' => ':attribute harus dalam format dd/mm/yyyy hh:mi:ss',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.waktuPemeriksaan' => 'Waktu Pemeriksaan',
        ];
    }

    /* ===============================
     | SAVE PEMERIKSAAN
     =============================== */
    #[On('save-rm-pemeriksaan-rj')]
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menyimpan data.');
            return;
        }

        $this->validate();

        try {
            DB::transaction(function () {
                // Whitelist field pemeriksaan yang boleh diupdate
                $allowedPemeriksaanFields = ['pemeriksaan'];

                // Untuk update, ambil data existing dari database
                $existingData = $this->findDataRJ($this->rjNo);

                // Ambil hanya field pemeriksaan yang diizinkan dari form
                $formPemeriksaan = array_intersect_key($this->dataDaftarPoliRJ ?? [], array_flip($allowedPemeriksaanFields));

                // Merge pemeriksaan data: existing diupdate dengan form data
                $mergedData = array_replace_recursive($existingData ?? [], $formPemeriksaan);

                // Update RJ with merged data
                $this->updateJsonRJ($this->rjNo, $mergedData);

                // Update pasien riwayat medis pasien data if needed
                $this->updateRiwayatMedisPasien();
            });

            $this->afterSave('Pemeriksaan berhasil disimpan.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    private function updateRiwayatMedisPasien(): void
    {
        $regNo = $this->dataDaftarPoliRJ['regNo'];

        // Ambil data pasien
        $pasienData = $this->findDataMasterPasien($regNo);

        $updated = false;

        // ✅ Update Alergi (text) - jika masih ada di struktur pemeriksaan
        if (isset($this->dataDaftarPoliRJ['pemeriksaan']['alergi']['alergi']) && !empty($this->dataDaftarPoliRJ['pemeriksaan']['alergi']['alergi'])) {
            $pasienData['pasien']['alergi'] = $this->dataDaftarPoliRJ['pemeriksaan']['alergi']['alergi'];
            $updated = true;
        }

        // ✅ Update Riwayat Penyakit Dahulu (text) - jika masih ada di struktur pemeriksaan
        if (isset($this->dataDaftarPoliRJ['pemeriksaan']['riwayatPenyakitDahulu']['riwayatPenyakitDahulu']) && !empty($this->dataDaftarPoliRJ['pemeriksaan']['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'])) {
            $pasienData['pasien']['riwayatPenyakitDahulu'] = $this->dataDaftarPoliRJ['pemeriksaan']['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'];
            $updated = true;
        }

        // ✅ Update jika ada perubahan
        if ($updated) {
            $pasienData['pasien']['regNo'] = $regNo;
            $this->updateJsonMasterPasien($regNo, $pasienData);
        }
    }

    /* ===============================
     | SET PERAWAT PEMERIKSA
     =============================== */
    public function setPerawatPemeriksa(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        if (auth()->user()->hasRole('Perawat')) {
            $this->dataDaftarPoliRJ['pemeriksaan']['tandaVital']['perawatPemeriksa'] = auth()->user()->myuser_name;
            $this->dataDaftarPoliRJ['pemeriksaan']['tandaVital']['perawatPemeriksaCode'] = auth()->user()->myuser_code;
            // 🔥 INCREMENT: Refresh untuk menampilkan perawat yang sudah di-set
            $this->incrementVersion('modal-pemeriksaan-rj');
        } else {
            $this->dispatch('toast', type: 'error', message: 'Hanya user dengan role Perawat yang dapat melakukan TTD-E.');
        }
    }

    /* ===============================
     | SET WAKTU PEMERIKSAAN
     =============================== */
    public function setWaktuPemeriksaan($time): void
    {
        if (!$this->isFormLocked) {
            $this->dataDaftarPoliRJ['pemeriksaan']['tandaVital']['waktuPemeriksaan'] = $time;

            // 🔥 INCREMENT: Refresh untuk menampilkan waktu yang sudah di-set
            $this->incrementVersion('modal-pemeriksaan-rj');
        }
    }

    /* ===============================
     | HITUNG IMT (Indeks Massa Tubuh)
     =============================== */
    public function hitungIMT(): void
    {
        $bb = $this->dataDaftarPoliRJ['pemeriksaan']['nutrisi']['bb'] ?? 0;
        $tb = $this->dataDaftarPoliRJ['pemeriksaan']['nutrisi']['tb'] ?? 0;

        if ($bb > 0 && $tb > 0) {
            $tbInMeter = $tb / 100;
            $imt = $bb / ($tbInMeter * $tbInMeter);
            $this->dataDaftarPoliRJ['pemeriksaan']['nutrisi']['imt'] = round($imt, 2);
        }
    }

    private function afterSave(string $message): void
    {
        // 🔥 INCREMENT: Refresh seluruh modal pemeriksaan
        $this->incrementVersion('modal-pemeriksaan-rj');

        $this->dispatch('toast', type: 'success', message: $message);
        $this->dispatch('refresh-after-rj.saved');
    }

    protected function resetForm(): void
    {
        $this->reset(['rjNo', 'dataDaftarPoliRJ']);
        $this->resetVersion();
        $this->isFormLocked = false;
    }

    public function mount()
    {
        $this->registerAreas(['modal-pemeriksaan-rj']);
        $this->openPemeriksaan($this->rjNo);
    }
};

?>

<div>
    {{-- CONTAINER UTAMA --}}
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-pemeriksaan-rj', [$rjNo ?? 'new']) }}">

        {{-- BODY --}}
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                {{-- jika pemeriksaan ada --}}
                @if (isset($dataDaftarPoliRJ['pemeriksaan']))
                    <div class="w-full mb-1">
                        <div class="grid grid-cols-1">
                            <div id="TransaksiRawatJalan" class="px-2">
                                <div id="TransaksiRawatJalan" x-data="{ activeTab: 'Umum' }">

                                    {{-- TAB NAVIGATION --}}
                                    <div class="px-2 border-b border-gray-200 dark:border-gray-700">
                                        <ul
                                            class="flex flex-wrap -mb-px text-xs font-medium text-center text-gray-500 dark:text-gray-400">

                                            {{-- UMUM TAB --}}
                                            <li class="mr-2">
                                                <label
                                                    class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    :class="activeTab === '{{ $dataDaftarPoliRJ['pemeriksaan']['umumTab'] ?? 'Umum' }}'
                                                        ?
                                                        'text-primary border-primary bg-gray-100' : ''"
                                                    @click="activeTab ='{{ $dataDaftarPoliRJ['pemeriksaan']['umumTab'] ?? 'Umum' }}'">
                                                    {{ $dataDaftarPoliRJ['pemeriksaan']['umumTab'] ?? 'Umum' }}
                                                </label>
                                            </li>

                                            {{-- FISIK TAB --}}
                                            <li class="mr-2">
                                                <label
                                                    class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    :class="activeTab === 'Fisik' ?
                                                        'text-primary border-primary bg-gray-100' : ''"
                                                    @click="activeTab ='Fisik'">
                                                    Fisik
                                                </label>
                                            </li>

                                            {{-- ANATOMI TAB --}}
                                            <li class="mr-2">
                                                <label
                                                    class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    :class="activeTab === 'Anatomi' ?
                                                        'text-primary border-primary bg-gray-100' : ''"
                                                    @click="activeTab ='Anatomi'">
                                                    Anatomi
                                                </label>
                                            </li>

                                            {{-- UJI FUNGSI TAB --}}
                                            <li class="mr-2">
                                                <label
                                                    class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    :class="activeTab === 'UjiFungsi' ?
                                                        'text-primary border-primary bg-gray-100' : ''"
                                                    @click="activeTab ='UjiFungsi'">
                                                    Uji Fungsi
                                                </label>
                                            </li>

                                            {{-- PENUNJANG TAB --}}
                                            <li class="mr-2">
                                                <label
                                                    class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    :class="activeTab === 'Penunjang' ?
                                                        'text-primary border-primary bg-gray-100' : ''"
                                                    @click="activeTab ='Penunjang'">
                                                    Penunjang
                                                </label>
                                            </li>

                                            {{-- PELAYANAN PENUNJANG TAB --}}
                                            <li class="mr-2">
                                                <label
                                                    class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    :class="activeTab === 'PenunjangHasil' ?
                                                        'text-primary border-primary bg-gray-100' : ''"
                                                    @click="activeTab ='PenunjangHasil'">
                                                    Pelayanan Penunjang
                                                </label>
                                            </li>
                                        </ul>
                                    </div>

                                    {{-- UMUM TAB CONTENT --}}
                                    {{-- UMUM TAB CONTENT --}}
                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        :class="{
                                            'active': activeTab === '{{ $dataDaftarPoliRJ['pemeriksaan']['umumTab'] ?? 'Umum' }}'
                                        }"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarPoliRJ['pemeriksaan']['umumTab'] ?? 'Umum' }}'">
                                        @include('pages.transaksi.rj.daftar-rj.rm.pemeriksaan.tabs.umum-tab')
                                    </div>

                                    {{-- FISIK TAB CONTENT --}}
                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        :class="{
                                            'active': activeTab === 'Fisik'
                                        }"
                                        x-show.transition.in.opacity.duration.600="activeTab === 'Fisik'">
                                        @include('pages.transaksi.rj.daftar-rj.rm.pemeriksaan.tabs.fisik-tab')
                                    </div>

                                    {{-- ANATOMI TAB CONTENT --}}
                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        :class="{
                                            'active': activeTab === 'Anatomi'
                                        }"
                                        x-show.transition.in.opacity.duration.600="activeTab === 'Anatomi'">
                                        @include('pages.transaksi.rj.daftar-rj.rm.pemeriksaan.tabs.anatomi-tab')
                                    </div>

                                    {{-- UJI FUNGSI TAB CONTENT --}}
                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        :class="{
                                            'active': activeTab === 'UjiFungsi'
                                        }"
                                        x-show.transition.in.opacity.duration.600="activeTab === 'UjiFungsi'">
                                        @include('pages.transaksi.rj.daftar-rj.rm.pemeriksaan.tabs.uji-fungsi-tab')
                                    </div>

                                    {{-- PENUNJANG TAB CONTENT --}}
                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        :class="{
                                            'active': activeTab === 'Penunjang'
                                        }"
                                        x-show.transition.in.opacity.duration.600="activeTab === 'Penunjang'">
                                        @include('pages.transaksi.rj.daftar-rj.rm.pemeriksaan.tabs.penunjang-tab')
                                    </div>

                                    {{-- PELAYANAN PENUNJANG TAB CONTENT --}}
                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        :class="{
                                            'active': activeTab === 'PenunjangHasil'
                                        }"
                                        x-show.transition.in.opacity.duration.600="activeTab === 'PenunjangHasil'">
                                        @include('pages.transaksi.rj.daftar-rj.rm.pemeriksaan.tabs.pelayanan-penunjang-tab')
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
