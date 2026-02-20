<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\BPJS\VclaimTrait;

new class extends Component {
    use VclaimTrait;

    // Data dari parent
    public ?string $rjNo = null;
    public ?string $regNo = null;
    public ?string $drId = null;
    public ?string $drDesc = null;
    public ?string $poliId = null;
    public ?string $poliDesc = null;
    public ?string $kdpolibpjs = null;
    public ?string $kunjunganId = null;
    public ?string $kontrol12 = null;
    public ?string $internal12 = null;
    public bool $postInap = false;
    public ?string $noReferensi = null;

    // State
    public string $formMode = 'create'; // create|edit
    public bool $isFormLocked = false;
    public bool $showRujukanLov = false;
    public string $searchRujukan = '';
    public array $dataRujukan = [];
    public array $selectedRujukan = [];
    public string $activeTab = 'pasien';
    public array $dataPasien = [];

    // SEP Form - Struktur sesuai format BPJS
    public array $SEPForm = [
        'noKartu' => '',
        'tglSep' => '',
        'ppkPelayanan' => '0184R006',
        'jnsPelayanan' => '2',
        'klsRawat' => [
            'klsRawatHak' => '',
            'klsRawatNaik' => '',
            'pembiayaan' => '',
            'penanggungJawab' => '',
        ],
        'noMR' => '',
        'rujukan' => [
            'asalRujukan' => '',
            'tglRujukan' => '',
            'noRujukan' => '',
            'ppkRujukan' => '',
        ],
        'catatan' => '',
        'diagAwal' => '',
        'poli' => [
            'tujuan' => '',
            'eksekutif' => '0',
        ],
        'cob' => [
            'cob' => '0',
        ],
        'katarak' => [
            'katarak' => '0',
        ],
        'jaminan' => [
            'lakaLantas' => '0',
            'noLP' => '',
            'penjamin' => [
                'tglKejadian' => '',
                'keterangan' => '',
                'suplesi' => [
                    'suplesi' => '0',
                    'noSepSuplesi' => '',
                    'lokasiLaka' => [
                        'kdPropinsi' => '',
                        'kdKabupaten' => '',
                        'kdKecamatan' => '',
                    ],
                ],
            ],
        ],
        'tujuanKunj' => '0',
        'flagProcedure' => '',
        'kdPenunjang' => '',
        'assesmentPel' => '',
        'skdp' => [
            'noSurat' => '',
            'kodeDPJP' => '',
        ],
        'dpjpLayan' => '',
        'noTelp' => '',
        'user' => 'sirus App',
    ];

    // Data SEP yang sudah terbentuk (dikirim ke parent)
    public array $sepData = [
        'noSep' => '',
        'reqSep' => [],
        'resSep' => [],
    ];

    // Options dropdown
    public array $tujuanKunjOptions = [['id' => '0', 'name' => 'Normal'], ['id' => '1', 'name' => 'Prosedur'], ['id' => '2', 'name' => 'Konsul Dokter']];

    public array $flagProcedureOptions = [['id' => '', 'name' => 'Pilih...'], ['id' => '0', 'name' => 'Prosedur Tidak Berkelanjutan'], ['id' => '1', 'name' => 'Prosedur dan Terapi Berkelanjutan']];

    public array $kdPenunjangOptions = [['id' => '', 'name' => 'Pilih...'], ['id' => '1', 'name' => 'Radioterapi'], ['id' => '2', 'name' => 'Kemoterapi'], ['id' => '3', 'name' => 'Rehabilitasi Medik'], ['id' => '4', 'name' => 'Rehabilitasi Psikososial'], ['id' => '5', 'name' => 'Transfusi Darah'], ['id' => '6', 'name' => 'Pelayanan Gigi'], ['id' => '7', 'name' => 'Laboratorium'], ['id' => '8', 'name' => 'USG'], ['id' => '9', 'name' => 'Farmasi'], ['id' => '10', 'name' => 'Lain-Lain'], ['id' => '11', 'name' => 'MRI'], ['id' => '12', 'name' => 'HEMODIALISA']];

    public array $assesmentPelOptions = [['id' => '', 'name' => 'Pilih...'], ['id' => '1', 'name' => 'Poli spesialis tidak tersedia pada hari sebelumnya'], ['id' => '2', 'name' => 'Jam Poli telah berakhir pada hari sebelumnya'], ['id' => '3', 'name' => 'Dokter Spesialis yang dimaksud tidak praktek pada hari sebelumnya'], ['id' => '4', 'name' => 'Atas Instruksi RS'], ['id' => '5', 'name' => 'Tujuan Kontrol']];

    /**
     * Handle event dari parent
     */
    #[On('open-vclaim-modal')]
    public function handleOpenVclaimModal($rjNo = null, $regNo = null, $drId = null, $drDesc = null, $poliId = null, $poliDesc = null, $kdpolibpjs = null, $kunjunganId = null, $kontrol12 = null, $internal12 = null, $postInap = false, $noReferensi = null)
    {
        // Set semua data dari parent
        $this->rjNo = $rjNo;
        $this->regNo = $regNo;
        $this->drId = $drId;
        $this->drDesc = $drDesc;
        $this->poliId = $poliId;
        $this->poliDesc = $poliDesc;
        $this->kdpolibpjs = $kdpolibpjs;
        $this->kunjunganId = $kunjunganId;
        $this->kontrol12 = $kontrol12;
        $this->internal12 = $internal12;
        $this->postInap = $postInap;
        $this->noReferensi = $noReferensi;

        // Set form mode
        $this->formMode = $rjNo ? 'edit' : 'create';

        // Load data pasien
        $this->loadDataPasien($regNo);

        // Cek apakah sudah ada SEP (dari parent nanti)
        // Untuk sekarang, kita asumsikan create mode

        // Buka modal
        $this->dispatch('open-modal', name: 'vclaim-rj-actions');
    }

    /**
     * Load data pasien dari database
     */
    private function loadDataPasien($regNo)
    {
        $data = DB::table('rsmst_pasiens')->where('reg_no', $regNo)->first();

        if ($data) {
            $this->dataPasien = [
                'pasien' => [
                    'identitas' => [
                        'idbpjs' => $data->nokartu_bpjs ?? '',
                        'nik' => $data->nik_bpjs ?? '',
                    ],
                    'kontak' => [
                        'nomerTelponSelulerPasien' => $data->phone ?? '',
                    ],
                    'regNo' => $data->reg_no,
                    'regName' => $data->reg_name,
                ],
            ];

            // Set default nilai SEP Form dari data pasien
            $this->SEPForm['noKartu'] = $data->nokartu_bpjs ?? '';
            $this->SEPForm['noMR'] = $data->reg_no;
            $this->SEPForm['noTelp'] = $data->phone ?? '';

            // Set dpjpLayan dari data dokter
            $this->SEPForm['dpjpLayan'] = $this->getKdDrBpjs($this->drId);

            // Set poli tujuan
            $this->SEPForm['poli']['tujuan'] = $this->kdpolibpjs ?? '';

            // Set asal rujukan berdasarkan jenis kunjungan
            $this->SEPForm['rujukan']['asalRujukan'] = $this->getAsalRujukan();
        }
    }

    /**
     * Get kode dokter BPJS
     */
    private function getKdDrBpjs($drId)
    {
        if (!$drId) {
            return '';
        }

        return DB::table('rsmst_doctors')->where('dr_id', $drId)->value('kd_dr_bpjs') ?? '';
    }

    /**
     * Get asal rujukan berdasarkan jenis kunjungan
     */
    private function getAsalRujukan()
    {
        switch ($this->kunjunganId) {
            case '1':
                return '1';
            case '2':
                return $this->internal12 ?? '1';
            case '3':
                return $this->postInap ? '2' : $this->kontrol12 ?? '1';
            case '4':
                return '2';
            default:
                return '1';
        }
    }

    /**
     * Cari rujukan peserta
     */
    public function cariRujukan()
    {
        $idBpjs = $this->dataPasien['pasien']['identitas']['idbpjs'] ?? '';

        if (empty($idBpjs)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Nomor BPJS tidak ditemukan']);
            return;
        }

        $this->showRujukanLov = true;
        $this->dataRujukan = [];
        $this->searchRujukan = '';

        // Panggil API sesuai jenis kunjungan
        switch ($this->kunjunganId) {
            case '1': // Rujukan FKTP
                $this->cariRujukanFKTP($idBpjs);
                break;
            case '2': // Rujukan Internal
                $this->internal12 == '1' ? $this->cariRujukanFKTP($idBpjs) : $this->cariRujukanFKTL($idBpjs);
                break;
            case '3': // Kontrol
                if ($this->postInap) {
                    $this->cariDataPeserta($idBpjs);
                } else {
                    $this->kontrol12 == '1' ? $this->cariRujukanFKTP($idBpjs) : $this->cariRujukanFKTL($idBpjs);
                }
                break;
            case '4': // Rujukan Antar RS
                $this->cariRujukanFKTL($idBpjs);
                break;
            default:
                $this->cariRujukanFKTP($idBpjs);
                break;
        }
    }

    private function cariRujukanFKTP($idBpjs)
    {
        $response = VclaimTrait::rujukan_peserta($idBpjs)->getOriginalContent();

        if ($response['metadata']['code'] == 200) {
            $this->dataRujukan = $response['response']['rujukan'] ?? [];
            if (empty($this->dataRujukan)) {
                $this->dispatch('notify', ['type' => 'warning', 'message' => 'Tidak ada data rujukan FKTP']);
            }
        } else {
            $this->dispatch('notify', ['type' => 'error', 'message' => $response['metadata']['message'] ?? 'Gagal']);
        }
    }

    private function cariRujukanFKTL($idBpjs)
    {
        $response = VclaimTrait::rujukan_rs_peserta($idBpjs)->getOriginalContent();

        if ($response['metadata']['code'] == 200) {
            $this->dataRujukan = $response['response']['rujukan'] ?? [];
            if (empty($this->dataRujukan)) {
                $this->dispatch('notify', ['type' => 'warning', 'message' => 'Tidak ada data rujukan FKTL']);
            }
        } else {
            $this->dispatch('notify', ['type' => 'error', 'message' => $response['metadata']['message'] ?? 'Gagal']);
        }
    }

    private function cariDataPeserta($idBpjs)
    {
        $tglSep = Carbon::now()->format('Y-m-d');
        $response = VclaimTrait::peserta_nomorkartu($idBpjs, $tglSep)->getOriginalContent();

        if ($response['metadata']['code'] == 200) {
            $peserta = $response['response']['peserta'] ?? [];
            if (!empty($peserta)) {
                $this->setSEPFormPostInap($peserta);
                $this->showRujukanLov = false;
            }
        } else {
            $this->dispatch('notify', ['type' => 'error', 'message' => $response['metadata']['message'] ?? 'Gagal']);
        }
    }

    public function pilihRujukan($index)
    {
        $rujukan = $this->dataRujukan[$index];
        $this->selectedRujukan = $rujukan;
        $this->setSEPFormFromRujukan($rujukan);
        $this->showRujukanLov = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Rujukan dipilih']);
    }

    private function setSEPFormFromRujukan($rujukan)
    {
        $peserta = $rujukan['peserta'] ?? [];

        $this->SEPForm = array_merge($this->SEPForm, [
            'noKartu' => $peserta['noKartu'] ?? $this->SEPForm['noKartu'],
            'noMR' => $peserta['mr']['noMR'] ?? $this->SEPForm['noMR'],
            'rujukan' => [
                'asalRujukan' => $this->getAsalRujukan(),
                'tglRujukan' => Carbon::parse($rujukan['tglKunjungan'])->format('Y-m-d'),
                'noRujukan' => $rujukan['noKunjungan'] ?? '',
                'ppkRujukan' => $rujukan['provPerujuk']['kode'] ?? '',
            ],
            'diagAwal' => $rujukan['diagnosa']['kode'] ?? '',
            'poli' => [
                'tujuan' => $rujukan['poliRujukan']['kode'] ?? $this->SEPForm['poli']['tujuan'],
                'eksekutif' => '0',
            ],
            'klsRawat' => [
                'klsRawatHak' => $peserta['hakKelas']['kode'] ?? '3',
            ],
            'noTelp' => $peserta['mr']['noTelepon'] ?? $this->SEPForm['noTelp'],
        ]);

        // Set skdp untuk kontrol
        if ($this->kunjunganId == '3' && !$this->postInap) {
            $this->SEPForm['skdp'] = [
                'noSurat' => $rujukan['noKunjungan'] ?? '',
                'kodeDPJP' => $this->SEPForm['dpjpLayan'] ?? '',
            ];
        }
    }

    private function setSEPFormPostInap($peserta)
    {
        $this->SEPForm = array_merge($this->SEPForm, [
            'noKartu' => $peserta['noKartu'] ?? $this->SEPForm['noKartu'],
            'noMR' => $peserta['mr']['noMR'] ?? $this->SEPForm['noMR'],
            'rujukan' => [
                'asalRujukan' => '2',
                'tglRujukan' => Carbon::now()->format('Y-m-d'),
                'noRujukan' => '',
                'ppkRujukan' => '0184R006',
            ],
            'klsRawat' => [
                'klsRawatHak' => $peserta['hakKelas']['kode'] ?? '3',
            ],
            'noTelp' => $peserta['mr']['noTelepon'] ?? $this->SEPForm['noTelp'],
        ]);
    }

    public function updatedSEPFormTujuanKunj($value)
    {
        if ($value == '0') {
            $this->SEPForm['flagProcedure'] = '';
            $this->SEPForm['kdPenunjang'] = '';
        }
        if ($value != '2') {
            $this->SEPForm['assesmentPel'] = '';
        }
    }

    /**
     * Generate SEP - hasilnya dikirim ke parent
     */
    public function generateSEP()
    {
        // Jika sudah locked (sudah ada SEP), tidak bisa generate ulang
        if ($this->isFormLocked) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'SEP sudah terbentuk, tidak dapat diubah']);
            return;
        }

        // Validasi
        $this->validateSEPForm();

        // Build request
        $request = $this->buildSEPRequest();

        // Panggil API
        $response = VclaimTrait::sep_insert($request)->getOriginalContent();

        if ($response['metadata']['code'] == 200) {
            // Simpan data SEP
            $this->sepData = [
                'noSep' => $response['response']['sep']['noSep'] ?? '',
                'reqSep' => $request,
                'resSep' => $response['response']['sep'] ?? [],
            ];

            // Lock form
            $this->isFormLocked = true;

            // Kirim ke parent component
            $this->dispatch('sep-generated', sepData: $this->sepData);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'SEP berhasil: ' . ($this->sepData['noSep'] ?? ''),
            ]);

            $this->showRujukanLov = false;
        } else {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Gagal: ' . ($response['metadata']['message'] ?? 'Unknown'),
            ]);
        }
    }

    /**
     * Validasi form SEP
     */
    private function validateSEPForm()
    {
        $rules = [
            'SEPForm.noKartu' => 'required',
            'SEPForm.tglSep' => 'required|date',
            'SEPForm.noMR' => 'required',
            'SEPForm.diagAwal' => 'required',
            'SEPForm.poli.tujuan' => 'required',
            'SEPForm.dpjpLayan' => 'required',
        ];

        if ($this->kunjunganId == '3' && !$this->postInap) {
            $rules['SEPForm.skdp.noSurat'] = 'required';
            $rules['SEPForm.skdp.kodeDPJP'] = 'required';
        }

        $messages = [
            'SEPForm.noKartu.required' => 'Nomor Kartu BPJS harus diisi',
            'SEPForm.tglSep.required' => 'Tanggal SEP harus diisi',
            'SEPForm.diagAwal.required' => 'Diagnosa awal harus diisi',
            'SEPForm.poli.tujuan.required' => 'Poli tujuan harus diisi',
            'SEPForm.dpjpLayan.required' => 'DPJP harus diisi',
        ];

        $this->validate($rules, $messages);
    }

    /**
     * Build request SEP sesuai format BPJS
     */
    private function buildSEPRequest()
    {
        $request = [
            'request' => [
                't_sep' => [
                    'noKartu' => $this->SEPForm['noKartu'],
                    'tglSep' => $this->SEPForm['tglSep'],
                    'ppkPelayanan' => $this->SEPForm['ppkPelayanan'],
                    'jnsPelayanan' => $this->SEPForm['jnsPelayanan'],
                    'klsRawat' => [
                        'klsRawatHak' => $this->SEPForm['klsRawat']['klsRawatHak'],
                        'klsRawatNaik' => $this->SEPForm['klsRawat']['klsRawatNaik'] ?? '',
                        'pembiayaan' => $this->SEPForm['klsRawat']['pembiayaan'] ?? '',
                        'penanggungJawab' => $this->SEPForm['klsRawat']['penanggungJawab'] ?? '',
                    ],
                    'noMR' => $this->SEPForm['noMR'],
                    'rujukan' => [
                        'asalRujukan' => $this->SEPForm['rujukan']['asalRujukan'],
                        'tglRujukan' => $this->SEPForm['rujukan']['tglRujukan'],
                        'noRujukan' => $this->SEPForm['rujukan']['noRujukan'],
                        'ppkRujukan' => $this->SEPForm['rujukan']['ppkRujukan'],
                    ],
                    'catatan' => $this->SEPForm['catatan'] ?: '-',
                    'diagAwal' => $this->SEPForm['diagAwal'],
                    'poli' => [
                        'tujuan' => $this->SEPForm['poli']['tujuan'],
                        'eksekutif' => $this->SEPForm['poli']['eksekutif'],
                    ],
                    'cob' => ['cob' => $this->SEPForm['cob']['cob']],
                    'katarak' => ['katarak' => $this->SEPForm['katarak']['katarak']],
                    'jaminan' => $this->buildJaminan(),
                    'tujuanKunj' => $this->SEPForm['tujuanKunj'],
                    'flagProcedure' => $this->SEPForm['tujuanKunj'] == '0' ? '' : $this->SEPForm['flagProcedure'],
                    'kdPenunjang' => $this->SEPForm['tujuanKunj'] == '0' ? '' : $this->SEPForm['kdPenunjang'],
                    'assesmentPel' => $this->SEPForm['assesmentPel'],
                    'skdp' => [
                        'noSurat' => $this->SEPForm['skdp']['noSurat'],
                        'kodeDPJP' => $this->SEPForm['skdp']['kodeDPJP'],
                    ],
                    'dpjpLayan' => $this->SEPForm['dpjpLayan'],
                    'noTelp' => $this->SEPForm['noTelp'],
                    'user' => $this->SEPForm['user'],
                ],
            ],
        ];

        // Hapus skdp jika bukan kontrol
        if ($this->kunjunganId != '3') {
            unset($request['request']['t_sep']['skdp']);
        }

        return $request;
    }

    private function buildJaminan()
    {
        if ($this->SEPForm['jaminan']['lakaLantas'] == '0') {
            return [
                'lakaLantas' => '0',
                'penjamin' => ['suplesi' => ['suplesi' => '0']],
            ];
        }

        return $this->SEPForm['jaminan'];
    }

    /**
     * Reset form
     */
    private function resetForm()
    {
        $this->reset('SEPForm', 'selectedRujukan', 'showRujukanLov', 'searchRujukan', 'dataRujukan');
        $this->SEPForm['tglSep'] = Carbon::now()->format('Y-m-d');
        $this->activeTab = 'pasien';
        $this->isFormLocked = false;
    }

    /**
     * Close modal
     */
    #[On('close-vclaim-modal')]
    public function closeModal()
    {
        $this->dispatch('close-modal', name: 'vclaim-rj-actions');
        $this->resetForm();
    }

    public function mount()
    {
        $this->SEPForm['tglSep'] = Carbon::now()->format('Y-m-d');
    }
};
?>


<div>
    <x-modal name="vclaim-rj-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="vclaim-rj-actions-{{ $formMode }}-{{ $initialRjNo ?? 'new' }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                {{-- Background pattern --}}
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            {{-- Icon --}}
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>

                            {{-- Title & subtitle --}}
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Data SEP' : 'Buat SEP Baru' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Kelola data SEP (Surat Eligibilitas Peserta) BPJS.
                                </p>
                            </div>
                        </div>

                        {{-- Badge mode --}}
                        <div class="flex gap-2 mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit SEP' : 'Mode: Buat SEP' }}
                            </x-badge>
                            @if ($isFormLocked)
                                <x-badge variant="danger">
                                    Read Only
                                </x-badge>
                            @endif
                        </div>
                    </div>

                    {{-- Close button --}}
                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">

                {{-- Tabs Navigation --}}
                <div class="mb-4 border-b border-gray-200 dark:border-gray-700">
                    <nav class="flex space-x-8" aria-label="Tabs">
                        <button type="button" @click="$wire.set('activeTab', 'pasien')"
                            class="px-1 py-2 text-sm font-medium border-b-2 {{ $activeTab === 'pasien' ? 'border-brand-green text-brand-green dark:border-brand-lime dark:text-brand-lime' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' }}">
                            Data Pasien
                        </button>
                        <button type="button" @click="$wire.set('activeTab', 'rujukan')"
                            class="px-1 py-2 text-sm font-medium border-b-2 {{ $activeTab === 'rujukan' ? 'border-brand-green text-brand-green dark:border-brand-lime dark:text-brand-lime' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' }}">
                            Data Rujukan & SEP
                        </button>
                    </nav>
                </div>

                {{-- Tab Content --}}
                @if ($activeTab === 'pasien')
                    {{-- Data Pasien --}}
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div class="p-4 bg-white rounded-lg shadow dark:bg-gray-800">
                            <h3 class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">Informasi Pasien</h3>

                            <div class="space-y-3">
                                <div>
                                    <span class="text-xs text-gray-500">No. RM</span>
                                    <p class="font-medium">{{ $dataPasien['pasien']['regNo'] ?? '-' }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Nama Pasien</span>
                                    <p class="font-medium">{{ $dataPasien['pasien']['regName'] ?? '-' }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">No. BPJS</span>
                                    <p class="font-medium">{{ $dataPasien['pasien']['identitas']['idbpjs'] ?? '-' }}
                                    </p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">No. Telepon</span>
                                    <p class="font-medium">
                                        {{ $dataPasien['pasien']['kontak']['nomerTelponSelulerPasien'] ?? '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @elseif($activeTab === 'rujukan')
                    {{-- Tombol Cari Rujukan --}}
                    <div class="mb-4">
                        <x-secondary-button type="button" wire:click="cariRujukan" class="gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linecap="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Cari Rujukan BPJS
                        </x-secondary-button>
                    </div>

                    {{-- LOV Rujukan --}}
                    @if ($showRujukanLov)
                        <div
                            class="mb-4 overflow-hidden bg-white border rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Pilih Rujukan
                                </h3>
                            </div>
                            <div class="p-4">
                                <x-text-input type="text" wire:model.live="searchRujukan"
                                    placeholder="Cari rujukan..." class="w-full mb-3" />

                                <div class="space-y-2 overflow-y-auto max-h-60">
                                    @forelse($dataRujukan as $index => $rujukan)
                                        <div wire:key="rujukan-{{ $index }}"
                                            class="p-3 border rounded cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                            wire:click="pilihRujukan({{ $index }})">
                                            <div class="flex justify-between">
                                                <div>
                                                    <span class="text-xs font-medium text-gray-500">No Rujukan:</span>
                                                    <span
                                                        class="ml-1 text-sm">{{ $rujukan['noKunjungan'] ?? '-' }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-xs font-medium text-gray-500">Tgl:</span>
                                                    <span
                                                        class="ml-1 text-sm">{{ Carbon::parse($rujukan['tglKunjungan'])->format('d/m/Y') }}</span>
                                                </div>
                                            </div>
                                            <div class="mt-1">
                                                <span class="text-xs font-medium text-gray-500">Asal Rujukan:</span>
                                                <span
                                                    class="ml-1 text-sm">{{ $rujukan['provPerujuk']['nama'] ?? '-' }}</span>
                                            </div>
                                            <div class="mt-1">
                                                <span class="text-xs font-medium text-gray-500">Poli Tujuan:</span>
                                                <span
                                                    class="ml-1 text-sm">{{ $rujukan['poliRujukan']['nama'] ?? '-' }}</span>
                                            </div>
                                            <div class="mt-1">
                                                <span class="text-xs font-medium text-gray-500">Diagnosa:</span>
                                                <span
                                                    class="ml-1 text-sm">{{ $rujukan['diagnosa']['nama'] ?? '-' }}</span>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="py-4 text-sm text-center text-gray-500">Tidak ada data rujukan</p>
                                    @endforelse
                                </div>
                            </div>
                            <div class="p-3 bg-gray-50 dark:bg-gray-900/50">
                                <x-secondary-button type="button" wire:click="$set('showRujukanLov', false)"
                                    class="justify-center w-full">
                                    Tutup
                                </x-secondary-button>
                            </div>
                        </div>
                    @endif

                    {{-- Form SEP --}}
                    @if (
                        !empty($selectedRujukan) ||
                            (($this->JenisKunjungan['JenisKunjunganId'] ?? '1') == '3' && ($dataDaftarPoliRJ['postInap'] ?? false)))
                        <div class="space-y-4">
                            <div class="p-4 bg-white rounded-lg shadow dark:bg-gray-800">
                                <h3 class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">Informasi SEP
                                </h3>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    {{-- No Kartu --}}
                                    <div>
                                        <x-input-label value="No. Kartu BPJS" required />
                                        <x-text-input wire:model="SEPForm.noKartu" class="w-full" :error="$errors->has('SEPForm.noKartu')" />
                                        <x-input-error :messages="$errors->get('SEPForm.noKartu')" class="mt-1" />
                                    </div>

                                    {{-- Tgl SEP --}}
                                    <div>
                                        <x-input-label value="Tanggal SEP" required />
                                        <x-text-input type="date" wire:model="SEPForm.tglSep" class="w-full"
                                            :error="$errors->has('SEPForm.tglSep')" />
                                        <x-input-error :messages="$errors->get('SEPForm.tglSep')" class="mt-1" />
                                    </div>

                                    {{-- No MR --}}
                                    <div>
                                        <x-input-label value="No. MR" required />
                                        <x-text-input wire:model="SEPForm.noMR" class="w-full" :error="$errors->has('SEPForm.noMR')" />
                                        <x-input-error :messages="$errors->get('SEPForm.noMR')" class="mt-1" />
                                    </div>

                                    {{-- Kelas Rawat Hak --}}
                                    <div>
                                        <x-input-label value="Kelas Rawat Hak" required />
                                        <x-select-input wire:model="SEPForm.klsRawat.klsRawatHak" class="w-full">
                                            <option value="">Pilih Kelas</option>
                                            <option value="1">Kelas 1</option>
                                            <option value="2">Kelas 2</option>
                                            <option value="3">Kelas 3</option>
                                        </x-select-input>
                                    </div>

                                    {{-- Diagnosa Awal --}}
                                    <div class="md:col-span-2">
                                        <x-input-label value="Diagnosa Awal (ICD 10)" required />
                                        <div class="flex gap-2">
                                            <x-text-input wire:model="SEPForm.diagAwal" class="flex-1"
                                                placeholder="Kode ICD 10" :error="$errors->has('SEPForm.diagAwal')" />
                                            <x-secondary-button type="button" wire:click="cariDiagnosa"
                                                class="whitespace-nowrap">
                                                Cari
                                            </x-secondary-button>
                                        </div>
                                        <x-input-error :messages="$errors->get('SEPForm.diagAwal')" class="mt-1" />
                                    </div>

                                    {{-- Poli Tujuan --}}
                                    <div class="md:col-span-2">
                                        <x-input-label value="Poli Tujuan" required />
                                        <div class="flex gap-2">
                                            <x-text-input wire:model="SEPForm.poli.tujuan" class="flex-1"
                                                placeholder="Kode Poli" :error="$errors->has('SEPForm.poli.tujuan')" />
                                            <x-secondary-button type="button" wire:click="cariPoli"
                                                class="whitespace-nowrap">
                                                Cari
                                            </x-secondary-button>
                                        </div>
                                        <x-input-error :messages="$errors->get('SEPForm.poli.tujuan')" class="mt-1" />
                                    </div>

                                    {{-- DPJP Layan --}}
                                    <div class="md:col-span-2">
                                        <x-input-label value="DPJP" required />
                                        <div class="flex gap-2">
                                            <x-text-input wire:model="SEPForm.dpjpLayan" class="flex-1"
                                                placeholder="Kode DPJP" :error="$errors->has('SEPForm.dpjpLayan')" />
                                            <x-secondary-button type="button" wire:click="cariDPJP"
                                                class="whitespace-nowrap">
                                                Cari
                                            </x-secondary-button>
                                        </div>
                                        <x-input-error :messages="$errors->get('SEPForm.dpjpLayan')" class="mt-1" />
                                    </div>

                                    {{-- Tujuan Kunjungan --}}
                                    <div>
                                        <x-input-label value="Tujuan Kunjungan" />
                                        <x-select-input wire:model.live="SEPForm.tujuanKunj" class="w-full">
                                            @foreach ($tujuanKunjOptions as $option)
                                                <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                                            @endforeach
                                        </x-select-input>
                                    </div>

                                    {{-- Flag Procedure (tampil jika tujuanKunj != 0) --}}
                                    @if ($SEPForm['tujuanKunj'] != '0')
                                        <div>
                                            <x-input-label value="Flag Procedure" />
                                            <x-select-input wire:model="SEPForm.flagProcedure" class="w-full">
                                                @foreach ($flagProcedureOptions as $option)
                                                    <option value="{{ $option['id'] }}">{{ $option['name'] }}
                                                    </option>
                                                @endforeach
                                            </x-select-input>
                                        </div>

                                        <div>
                                            <x-input-label value="Kode Penunjang" />
                                            <x-select-input wire:model="SEPForm.kdPenunjang" class="w-full">
                                                @foreach ($kdPenunjangOptions as $option)
                                                    <option value="{{ $option['id'] }}">{{ $option['name'] }}
                                                    </option>
                                                @endforeach
                                            </x-select-input>
                                        </div>
                                    @endif

                                    {{-- Assesment Pel (tampil jika tujuanKunj == 2) --}}
                                    @if ($SEPForm['tujuanKunj'] == '2')
                                        <div class="md:col-span-2">
                                            <x-input-label value="Assesment Pelayanan" />
                                            <x-select-input wire:model="SEPForm.assesmentPel" class="w-full">
                                                @foreach ($assesmentPelOptions as $option)
                                                    <option value="{{ $option['id'] }}">{{ $option['name'] }}
                                                    </option>
                                                @endforeach
                                            </x-select-input>
                                        </div>
                                    @endif

                                    {{-- SKDP untuk Kontrol --}}
                                    @if (($JenisKunjungan['JenisKunjunganId'] ?? '1') == '3' && !($dataDaftarPoliRJ['postInap'] ?? false))
                                        <div class="pt-3 mt-2 border-t md:col-span-2">
                                            <h4 class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Data
                                                Kontrol</h4>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <x-input-label value="No. Surat Kontrol" required />
                                                    <x-text-input wire:model="SEPForm.skdp.noSurat" class="w-full" />
                                                    <x-input-error :messages="$errors->get('SEPForm.skdp.noSurat')" class="mt-1" />
                                                </div>
                                                <div>
                                                    <x-input-label value="Kode DPJP Kontrol" required />
                                                    <x-text-input wire:model="SEPForm.skdp.kodeDPJP" class="w-full" />
                                                    <x-input-error :messages="$errors->get('SEPForm.skdp.kodeDPJP')" class="mt-1" />
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                {{-- Tombol Generate SEP --}}
                                <div class="flex justify-end mt-4">
                                    <x-primary-button type="button" wire:click="generateSEP"
                                        wire:loading.attr="disabled">
                                        <span wire:loading.remove>Generate SEP</span>
                                        <span wire:loading>Memproses...</span>
                                    </x-primary-button>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Tampilkan SEP yang sudah dibuat --}}
                    @if (!empty($dataDaftarPoliRJ['sep']['noSep']))
                        <div
                            class="p-4 mt-4 border border-green-200 rounded-lg bg-green-50 dark:bg-green-900/20 dark:border-green-800">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-medium text-green-700 dark:text-green-300">No. SEP</span>
                                    <p class="text-lg font-semibold text-green-800 dark:text-green-200">
                                        {{ $dataDaftarPoliRJ['sep']['noSep'] }}
                                    </p>
                                </div>
                                <div class="flex gap-2">
                                    <x-secondary-button type="button" wire:click="cetakSEP" size="sm">
                                        Cetak SEP
                                    </x-secondary-button>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-between gap-3">
                    <x-secondary-button type="button" wire:click="closeModal">
                        Batal
                    </x-secondary-button>

                    <div class="flex gap-2">
                        <x-secondary-button type="button" wire:click="$set('activeTab', 'pasien')"
                            wire:loading.attr="disabled">
                            Sebelumnya
                        </x-secondary-button>

                        @if ($activeTab === 'pasien')
                            <x-primary-button type="button" wire:click="$set('activeTab', 'rujukan')">
                                Selanjutnya
                            </x-primary-button>
                        @elseif($activeTab === 'rujukan')
                            <x-primary-button type="button" wire:click="generateSEP" wire:loading.attr="disabled">
                                <span wire:loading.remove>Simpan SEP</span>
                                <span wire:loading>Memproses...</span>
                            </x-primary-button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </x-modal>
</div>
