<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use Carbon\Carbon;

new class extends Component {
    public string $formMode = 'create'; // create|edit

    // Data transaksi RJ
    public array $dataDaftarPoliRJ = [
        'rjNo' => '',
        'rjDate' => '',
        'regNo' => '',
        'regName' => '',
        'noBooking' => '',
        'noAntrian' => '',
        'klaimId' => '',
        'poliId' => '',
        'poliDesc' => '',
        'drId' => '',
        'drName' => '',
        'shift' => 'PAGI',
        'txnStatus' => '1',
        'rjStatus' => '1',
        'ermStatus' => '0',
        'passStatus' => 'N',
        'cekLab' => '0',
        'slCodeFrom' => 'MANUAL',
        'kunjunganInternalStatus' => '0',
        'sep' => [
            'noSep' => '',
            'tglSep' => '',
            'ppkPelayanan' => '',
            'jnsPelayanan' => '2',
        ],
    ];

    // Status push BPJS
    public string $HttpGetBpjsStatus = '';
    public ?string $HttpGetBpjsJson = null;

    // Master data for dropdowns
    public array $poliList = [];
    public array $dokterList = [];
    public array $klaimList = [];

    #[On('daftar-rj.openCreate')]
    public function openCreate(): void
    {
        $this->resetFormFields();
        $this->formMode = 'create';
        $this->loadMasterData();
        $this->generateRjNo();
        $this->dataDaftarPoliRJ['rjDate'] = now()->format('d/m/Y H:i:s');
        $this->resetValidation();

        $this->dispatch('open-modal', name: 'daftar-rj-actions');
    }

    #[On('daftar-rj.openEdit')]
    public function openEdit(string $rjNo): void
    {
        $data = DB::table('rstxn_rjhdrs')->select('rj_no', DB::raw("to_char(rj_date,'dd/mm/yyyy hh24:mi:ss') as rj_date"), 'reg_no', DB::raw('(select reg_name from rsmst_pasiens where reg_no = rstxn_rjhdrs.reg_no) as reg_name'), 'nobooking as noBooking', 'no_antrian as noAntrian', 'klaim_id as klaimId', 'poli_id as poliId', DB::raw('(select poli_desc from rsmst_polis where poli_id = rstxn_rjhdrs.poli_id) as poliDesc'), 'dr_id as drId', DB::raw('(select dr_name from rsmst_doctors where dr_id = rstxn_rjhdrs.dr_id) as drName'), 'shift', 'txn_status as txnStatus', 'rj_status as rjStatus', 'erm_status as ermStatus', 'pass_status as passStatus', 'cek_lab as cekLab', 'sl_codefrom as slCodeFrom', 'kunjungan_internal_status as kunjunganInternalStatus', 'vno_sep as noSep')->where('rj_no', $rjNo)->first();

        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan.');
            return;
        }

        $this->resetFormFields();
        $this->loadMasterData();
        $this->formMode = 'edit';

        $this->dataDaftarPoliRJ = [
            'rjNo' => $data->rj_no ?? '',
            'rjDate' => $data->rj_date ?? now()->format('d/m/Y H:i:s'),
            'regNo' => $data->reg_no ?? '',
            'regName' => $data->reg_name ?? '',
            'noBooking' => $data->noBooking ?? '',
            'noAntrian' => $data->noAntrian ?? '',
            'klaimId' => $data->klaimId ?? '',
            'poliId' => $data->poliId ?? '',
            'poliDesc' => $data->poliDesc ?? '',
            'drId' => $data->drId ?? '',
            'drName' => $data->drName ?? '',
            'shift' => $data->shift ?? 'PAGI',
            'txnStatus' => $data->txnStatus ?? '1',
            'rjStatus' => $data->rjStatus ?? '1',
            'ermStatus' => $data->ermStatus ?? '0',
            'passStatus' => $data->passStatus ?? 'N',
            'cekLab' => $data->cekLab ?? '0',
            'slCodeFrom' => $data->slCodeFrom ?? 'MANUAL',
            'kunjunganInternalStatus' => $data->kunjunganInternalStatus ?? '0',
            'sep' => [
                'noSep' => $data->noSep ?? '',
                'tglSep' => '',
                'ppkPelayanan' => '',
                'jnsPelayanan' => '2',
            ],
        ];

        $this->resetValidation();
        $this->dispatch('open-modal', name: 'daftar-rj-actions');
    }

    #[On('lov.selected')]
    public function handleLovSelected($target, $payload): void
    {
        if ($target === 'pasien_rawat_jalan') {
            $this->dataDaftarPoliRJ['regNo'] = $payload['reg_no'] ?? '';
            $this->dataDaftarPoliRJ['regName'] = $payload['reg_name'] ?? '';
            $this->dataDaftarPoliRJ['noBooking'] = $payload['nobooking'] ?? '';

            if (!empty($payload['vno_sep'])) {
                $this->dataDaftarPoliRJ['sep']['noSep'] = $payload['vno_sep'];
            }

            $this->generateNoAntrian();
        }
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'daftar-rj-actions');
    }

    protected function resetFormFields(): void
    {
        $this->dataDaftarPoliRJ = [
            'rjNo' => '',
            'rjDate' => now()->format('d/m/Y H:i:s'),
            'regNo' => '',
            'regName' => '',
            'noBooking' => '',
            'noAntrian' => '',
            'klaimId' => '',
            'poliId' => '',
            'poliDesc' => '',
            'drId' => '',
            'drName' => '',
            'shift' => 'PAGI',
            'txnStatus' => '1',
            'rjStatus' => '1',
            'ermStatus' => '0',
            'passStatus' => 'N',
            'cekLab' => '0',
            'slCodeFrom' => 'MANUAL',
            'kunjunganInternalStatus' => '0',
            'sep' => [
                'noSep' => '',
                'tglSep' => '',
                'ppkPelayanan' => '',
                'jnsPelayanan' => '2',
            ],
        ];

        $this->HttpGetBpjsStatus = '';
        $this->HttpGetBpjsJson = null;
    }

    protected function loadMasterData(): void
    {
        // Load poli
        $this->poliList = DB::table('rsmst_polis')
            ->select('poli_id', 'poli_desc', 'spesialis_status')
            ->where('active_status', '1')
            ->orderBy('poli_desc')
            ->get()
            ->map(
                fn($item) => [
                    'id' => $item->poli_id,
                    'name' => $item->poli_desc,
                    'is_specialist' => $item->spesialis_status,
                ],
            )
            ->toArray();

        // Load dokter
        $this->dokterList = DB::table('rsmst_doctors')
            ->select('dr_id', 'dr_name')
            ->where('active_status', '1')
            ->orderBy('dr_name')
            ->get()
            ->map(
                fn($item) => [
                    'id' => $item->dr_id,
                    'name' => $item->dr_name,
                ],
            )
            ->toArray();

        // Load klaim
        $this->klaimList = DB::table('rsmst_klaims')
            ->select('klaim_id', 'klaim_name')
            ->where('active_status', '1')
            ->orderBy('klaim_name')
            ->get()
            ->map(
                fn($item) => [
                    'id' => $item->klaim_id,
                    'name' => $item->klaim_name,
                ],
            )
            ->toArray();
    }

    protected function generateRjNo(): void
    {
        $date = now();
        $year = $date->format('y');
        $month = $date->format('m');

        $lastRj = DB::table('rstxn_rjhdrs')->whereYear('rj_date', $date->year)->whereMonth('rj_date', $date->month)->orderBy('rj_no', 'desc')->first();

        if ($lastRj) {
            $lastNumber = (int) substr($lastRj->rj_no, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        $this->dataDaftarPoliRJ['rjNo'] = "RJ{$year}{$month}{$newNumber}";
    }

    public function updatedDataDaftarPoliRJPoliId(): void
    {
        $this->generateNoAntrian();

        $poli = collect($this->poliList)->firstWhere('id', $this->dataDaftarPoliRJ['poliId']);
        $this->dataDaftarPoliRJ['poliDesc'] = $poli['name'] ?? '';
    }

    protected function generateNoAntrian(): void
    {
        if (empty($this->dataDaftarPoliRJ['poliId']) || empty($this->dataDaftarPoliRJ['rjDate'])) {
            return;
        }

        try {
            $date = Carbon::createFromFormat('d/m/Y H:i:s', $this->dataDaftarPoliRJ['rjDate']);

            $lastAntrian = DB::table('rstxn_rjhdrs')->whereDate('rj_date', $date)->where('poli_id', $this->dataDaftarPoliRJ['poliId'])->orderBy('no_antrian', 'desc')->first();

            if ($lastAntrian) {
                $newNumber = str_pad((int) $lastAntrian->no_antrian + 1, 3, '0', STR_PAD_LEFT);
            } else {
                $newNumber = '001';
            }

            $this->dataDaftarPoliRJ['noAntrian'] = $newNumber;
        } catch (\Exception $e) {
            // Jika error parsing date, set default
            $this->dataDaftarPoliRJ['noAntrian'] = '001';
        }
    }

    protected function rules(): array
    {
        return [
            'dataDaftarPoliRJ.rjNo' => ['required', 'string', 'max:20', Rule::unique('rstxn_rjhdrs', 'rj_no')->ignore($this->dataDaftarPoliRJ['rjNo'] ?? null, 'rj_no')],
            'dataDaftarPoliRJ.rjDate' => ['required', 'string'],
            'dataDaftarPoliRJ.regNo' => ['required', 'string', 'max:20'],
            'dataDaftarPoliRJ.regName' => ['required', 'string', 'max:255'],
            'dataDaftarPoliRJ.poliId' => ['required', 'string'],
            'dataDaftarPoliRJ.drId' => ['required', 'string'],
            'dataDaftarPoliRJ.klaimId' => ['required', 'string'],
            'dataDaftarPoliRJ.shift' => ['required', 'string', Rule::in(['PAGI', 'SIANG', 'SORE', 'MALAM'])],
            'dataDaftarPoliRJ.rjStatus' => ['required', 'string', Rule::in(['0', '1', '2', '3', '4', '5'])],
            'dataDaftarPoliRJ.sep.noSep' => ['nullable', 'string', 'max:30'],
        ];
    }

    protected function messages(): array
    {
        return [
            'dataDaftarPoliRJ.rjNo.required' => 'Nomor Rawat Jalan wajib diisi.',
            'dataDaftarPoliRJ.rjNo.unique' => 'Nomor Rawat Jalan sudah digunakan.',
            'dataDaftarPoliRJ.regNo.required' => 'Nomor Rekam Medis wajib diisi.',
            'dataDaftarPoliRJ.regName.required' => 'Nama Pasien wajib diisi.',
            'dataDaftarPoliRJ.poliId.required' => 'Poliklinik wajib dipilih.',
            'dataDaftarPoliRJ.drId.required' => 'Dokter wajib dipilih.',
            'dataDaftarPoliRJ.klaimId.required' => 'Jenis Klaim wajib dipilih.',
            'dataDaftarPoliRJ.shift.required' => 'Shift wajib dipilih.',
            'dataDaftarPoliRJ.rjStatus.required' => 'Status Rawat Jalan wajib dipilih.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'dataDaftarPoliRJ.rjNo' => 'No. Rawat Jalan',
            'dataDaftarPoliRJ.rjDate' => 'Tanggal',
            'dataDaftarPoliRJ.regNo' => 'No. RM',
            'dataDaftarPoliRJ.regName' => 'Nama Pasien',
            'dataDaftarPoliRJ.poliId' => 'Poliklinik',
            'dataDaftarPoliRJ.drId' => 'Dokter',
            'dataDaftarPoliRJ.klaimId' => 'Jenis Klaim',
            'dataDaftarPoliRJ.shift' => 'Shift',
            'dataDaftarPoliRJ.sep.noSep' => 'No. SEP',
        ];
    }

    private function insertDataRJ(): void
    {
        DB::table('rstxn_rjhdrs')->insert([
            'rj_no' => $this->dataDaftarPoliRJ['rjNo'],
            'rj_date' => DB::raw("to_date('" . $this->dataDaftarPoliRJ['rjDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
            'reg_no' => $this->dataDaftarPoliRJ['regNo'],
            'nobooking' => $this->dataDaftarPoliRJ['noBooking'] ?? '',
            'no_antrian' => $this->dataDaftarPoliRJ['noAntrian'],
            'klaim_id' => $this->dataDaftarPoliRJ['klaimId'],
            'poli_id' => $this->dataDaftarPoliRJ['poliId'],
            'dr_id' => $this->dataDaftarPoliRJ['drId'],
            'shift' => $this->dataDaftarPoliRJ['shift'],
            'txn_status' => $this->dataDaftarPoliRJ['txnStatus'] ?? '1',
            'rj_status' => $this->dataDaftarPoliRJ['rjStatus'],
            'erm_status' => $this->dataDaftarPoliRJ['ermStatus'] ?? '0',
            'pass_status' => $this->dataDaftarPoliRJ['passStatus'] ?? 'N',
            'cek_lab' => $this->dataDaftarPoliRJ['cekLab'] ?? '0',
            'sl_codefrom' => $this->dataDaftarPoliRJ['slCodeFrom'] ?? 'MANUAL',
            'kunjungan_internal_status' => $this->dataDaftarPoliRJ['kunjunganInternalStatus'] ?? '0',
            'push_antrian_bpjs_status' => $this->HttpGetBpjsStatus,
            'push_antrian_bpjs_json' => $this->HttpGetBpjsJson,
            'datadaftarpolirj_json' => json_encode($this->dataDaftarPoliRJ, JSON_UNESCAPED_UNICODE),
            'waktu_masuk_pelayanan' => DB::raw("to_date('" . $this->dataDaftarPoliRJ['rjDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
            'vno_sep' => $this->dataDaftarPoliRJ['sep']['noSep'] ?? '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->updateJsonRJ($this->dataDaftarPoliRJ['rjNo'], $this->dataDaftarPoliRJ);
    }

    private function updateDataRJ(): void
    {
        DB::table('rstxn_rjhdrs')
            ->where('rj_no', $this->dataDaftarPoliRJ['rjNo'])
            ->update([
                'rj_date' => DB::raw("to_date('" . $this->dataDaftarPoliRJ['rjDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
                'reg_no' => $this->dataDaftarPoliRJ['regNo'],
                'nobooking' => $this->dataDaftarPoliRJ['noBooking'] ?? '',
                'no_antrian' => $this->dataDaftarPoliRJ['noAntrian'],
                'klaim_id' => $this->dataDaftarPoliRJ['klaimId'],
                'poli_id' => $this->dataDaftarPoliRJ['poliId'],
                'dr_id' => $this->dataDaftarPoliRJ['drId'],
                'shift' => $this->dataDaftarPoliRJ['shift'],
                'txn_status' => $this->dataDaftarPoliRJ['txnStatus'] ?? '1',
                'rj_status' => $this->dataDaftarPoliRJ['rjStatus'],
                'erm_status' => $this->dataDaftarPoliRJ['ermStatus'] ?? '0',
                'pass_status' => $this->dataDaftarPoliRJ['passStatus'] ?? 'N',
                'cek_lab' => $this->dataDaftarPoliRJ['cekLab'] ?? '0',
                'sl_codefrom' => $this->dataDaftarPoliRJ['slCodeFrom'] ?? 'MANUAL',
                'kunjungan_internal_status' => $this->dataDaftarPoliRJ['kunjunganInternalStatus'] ?? '0',
                'push_antrian_bpjs_status' => $this->HttpGetBpjsStatus,
                'push_antrian_bpjs_json' => $this->HttpGetBpjsJson,
                'datadaftarpolirj_json' => json_encode($this->dataDaftarPoliRJ, JSON_UNESCAPED_UNICODE),
                'waktu_masuk_pelayanan' => DB::raw("to_date('" . $this->dataDaftarPoliRJ['rjDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
                'vno_sep' => $this->dataDaftarPoliRJ['sep']['noSep'] ?? '',
                'updated_at' => now(),
            ]);

        $this->updateJsonRJ($this->dataDaftarPoliRJ['rjNo'], $this->dataDaftarPoliRJ);
    }

    private function updateJsonRJ(string $rjNo, array $data): void
    {
        DB::table('rstxn_rjhdrs')
            ->where('rj_no', $rjNo)
            ->update([
                'datadaftarpolirj_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ]);
    }

    #[On('daftar-rj.requestDelete')]
    public function deleteDataRJ(string $rjNo): void
    {
        try {
            // Check if has lab transactions
            $hasLab = DB::table('lbtxn_checkuphdrs')->where('ref_no', $rjNo)->where('status_rjri', 'RJ')->exists();

            if ($hasLab) {
                $this->dispatch('toast', type: 'error', message: 'Tidak dapat dihapus karena sudah memiliki pemeriksaan Laboratorium.');
                return;
            }

            // Check if has radiology transactions
            $hasRad = DB::table('rstxn_rjrads')->where('rj_no', $rjNo)->exists();

            if ($hasRad) {
                $this->dispatch('toast', type: 'error', message: 'Tidak dapat dihapus karena sudah memiliki pemeriksaan Radiologi.');
                return;
            }

            // Check if has pharmacy transactions
            $hasFarmasi = DB::table('fasmst_resep_hdrs')->where('ref_no', $rjNo)->where('ref_type', 'RJ')->exists();

            if ($hasFarmasi) {
                $this->dispatch('toast', type: 'error', message: 'Tidak dapat dihapus karena sudah memiliki resep farmasi.');
                return;
            }

            DB::table('rstxn_rjhdrs')->where('rj_no', $rjNo)->delete();

            $this->dispatch('toast', type: 'success', message: 'Data Rawat Jalan berhasil dihapus.');
            $this->dispatch('daftar-rj.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Data tidak bisa dihapus karena masih memiliki relasi dengan data lain.');
            } else {
                $this->dispatch('toast', type: 'error', message: 'Gagal menghapus data: ' . $e->getMessage());
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $this->validate();

        try {
            DB::beginTransaction();

            if ($this->formMode === 'create') {
                $this->insertDataRJ();
                $message = 'Data Rawat Jalan berhasil disimpan.';
            } else {
                $this->updateDataRJ();
                $message = 'Data Rawat Jalan berhasil diperbarui.';
            }

            DB::commit();

            $this->dispatch('toast', type: 'success', message: $message);
            $this->closeModal();
            $this->dispatch('daftar-rj.saved');
        } catch (QueryException $e) {
            DB::rollBack();

            if (str_contains($e->getMessage(), 'ORA-00001')) {
                $this->addError('dataDaftarPoliRJ.rjNo', 'Nomor Rawat Jalan sudah digunakan.');
            } else {
                $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan data: ' . $e->getMessage());
            }
            throw $e;
        }
    }
};
?>


<div>
    <x-modal name="daftar-rj-actions" size="4xl" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="daftar-rj-actions-{{ $formMode }}-{{ $dataDaftarPoliRJ['rjNo'] ?? 'new' }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <svg class="w-6 h-6 text-brand-green dark:text-brand-lime" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'create' ? 'Pendaftaran Rawat Jalan' : 'Edit Pendaftaran Rawat Jalan' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $formMode === 'create' ? 'Isi form untuk mendaftarkan pasien' : 'Perbarui data pendaftaran pasien' }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'create' ? 'success' : 'warning'">
                                {{ $formMode === 'create' ? 'Mode: Tambah' : 'Mode: Edit' }}
                            </x-badge>
                            @if ($formMode === 'edit')
                                <span class="ml-2 text-xs text-gray-500">
                                    RJ No: {{ $dataDaftarPoliRJ['rjNo'] }}
                                </span>
                            @endif
                        </div>
                    </div>

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
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-4xl mx-auto space-y-6">

                    {{-- LOV Pasien --}}
                    <div
                        class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="p-5">
                            <h3 class="mb-4 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <span class="inline-block w-1.5 h-1.5 mr-2 bg-blue-500 rounded-full"></span>
                                Data Pasien
                            </h3>

                            <livewire:rawat-jalan.lov-pasien.lov-pasien target="pasien_rawat_jalan" label="Cari Pasien"
                                placeholder="Ketik No RM / Nama Pasien..." :initial-reg-no="$formMode === 'edit' ? $dataDaftarPoliRJ['regNo'] : null"
                                wire:key="lov-pasien-{{ $formMode }}-{{ $dataDaftarPoliRJ['rjNo'] ?? 'new' }}" />

                            <div class="grid grid-cols-2 gap-4 mt-4">
                                <div>
                                    <x-input-label value="No. RM" />
                                    <x-text-input wire:model="dataDaftarPoliRJ.regNo"
                                        class="w-full mt-1 bg-gray-100 dark:bg-gray-800" readonly disabled />
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.regNo')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Nama Pasien" />
                                    <x-text-input wire:model="dataDaftarPoliRJ.regName"
                                        class="w-full mt-1 bg-gray-100 dark:bg-gray-800" readonly disabled />
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.regName')" class="mt-1" />
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Data Pendaftaran --}}
                    <div
                        class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="p-5 space-y-5">
                            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                <span class="inline-block w-1.5 h-1.5 mr-2 bg-green-500 rounded-full"></span>
                                Data Pendaftaran
                            </h3>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                {{-- No RJ --}}
                                <div>
                                    <x-input-label value="No. Rawat Jalan" />
                                    <x-text-input wire:model.defer="dataDaftarPoliRJ.rjNo"
                                        class="w-full mt-1 bg-gray-100 dark:bg-gray-800" :disabled="$formMode === 'edit'" readonly />
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.rjNo')" class="mt-1" />
                                </div>

                                {{-- Tanggal --}}
                                <div>
                                    <x-input-label value="Tanggal Daftar" />
                                    <div class="relative mt-1">
                                        <div
                                            class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <x-text-input wire:model.defer="dataDaftarPoliRJ.rjDate"
                                            class="block w-full pl-10" x-mask="99/99/9999 99:99:99"
                                            placeholder="dd/mm/yyyy hh:mm:ss" />
                                    </div>
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.rjDate')" class="mt-1" />
                                </div>

                                {{-- No Antrian --}}
                                <div>
                                    <x-input-label value="No. Antrian" />
                                    <x-text-input wire:model="dataDaftarPoliRJ.noAntrian"
                                        class="w-full mt-1 font-mono font-bold bg-yellow-50 dark:bg-yellow-900/30"
                                        readonly />
                                    <p class="mt-1 text-xs text-gray-500">
                                        Otomatis berdasarkan poli & tanggal
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {{-- Poli --}}
                                <div>
                                    <x-input-label value="Poliklinik" required />
                                    <x-select-input wire:model.live="dataDaftarPoliRJ.poliId" class="w-full mt-1"
                                        :error="$errors->has('dataDaftarPoliRJ.poliId')">
                                        <option value="">-- Pilih Poliklinik --</option>
                                        @foreach ($poliList as $poli)
                                            <option value="{{ $poli['id'] }}">
                                                {{ $poli['name'] }}
                                                @if ($poli['is_specialist'] == '1')
                                                    (Spesialis)
                                                @endif
                                            </option>
                                        @endforeach
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.poliId')" class="mt-1" />
                                </div>

                                {{-- Dokter --}}
                                <div>
                                    <x-input-label value="Dokter" required />
                                    <x-select-input wire:model.defer="dataDaftarPoliRJ.drId" class="w-full mt-1"
                                        :error="$errors->has('dataDaftarPoliRJ.drId')">
                                        <option value="">-- Pilih Dokter --</option>
                                        @foreach ($dokterList as $dokter)
                                            <option value="{{ $dokter['id'] }}">{{ $dokter['name'] }}</option>
                                        @endforeach
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.drId')" class="mt-1" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                {{-- Klaim --}}
                                <div>
                                    <x-input-label value="Jenis Klaim" required />
                                    <x-select-input wire:model.defer="dataDaftarPoliRJ.klaimId" class="w-full mt-1"
                                        :error="$errors->has('dataDaftarPoliRJ.klaimId')">
                                        <option value="">-- Pilih Klaim --</option>
                                        @foreach ($klaimList as $klaim)
                                            <option value="{{ $klaim['id'] }}">{{ $klaim['name'] }}</option>
                                        @endforeach
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.klaimId')" class="mt-1" />
                                </div>

                                {{-- Shift --}}
                                <div>
                                    <x-input-label value="Shift" required />
                                    <x-select-input wire:model.defer="dataDaftarPoliRJ.shift" class="w-full mt-1">
                                        <option value="PAGI">PAGI</option>
                                        <option value="SIANG">SIANG</option>
                                        <option value="SORE">SORE</option>
                                        <option value="MALAM">MALAM</option>
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.shift')" class="mt-1" />
                                </div>

                                {{-- Status --}}
                                <div>
                                    <x-input-label value="Status" />
                                    <x-select-input wire:model.defer="dataDaftarPoliRJ.rjStatus" class="w-full mt-1">
                                        <option value="1">DAFTAR</option>
                                        <option value="2">ANTRIAN</option>
                                        <option value="3">DILAYANI</option>
                                        <option value="4">SELESAI</option>
                                        <option value="5">TIDAK HADIR</option>
                                        <option value="0">BATAL</option>
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.rjStatus')" class="mt-1" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {{-- No Booking --}}
                                <div>
                                    <x-input-label value="No. Booking" />
                                    <x-text-input wire:model.defer="dataDaftarPoliRJ.noBooking" class="w-full mt-1"
                                        placeholder="Opsional" />
                                </div>

                                {{-- SEP --}}
                                <div>
                                    <x-input-label value="No. SEP" />
                                    <x-text-input wire:model.defer="dataDaftarPoliRJ.sep.noSep"
                                        class="w-full mt-1 font-mono" placeholder="Nomor SEP BPJS" />
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.sep.noSep')" class="mt-1" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        <span class="font-medium text-red-500">*</span> Wajib diisi
                    </div>

                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">
                            Batal
                        </x-secondary-button>

                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove>
                                {{ $formMode === 'create' ? 'Simpan' : 'Update' }}
                            </span>
                            <span wire:loading>
                                <svg class="inline w-4 h-4 mr-2 animate-spin" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                        stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                Processing...
                            </span>
                        </x-primary-button>
                    </div>
                </div>
            </div>
        </div>
    </x-modal>
</div>
