<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Carbon\Carbon;

use App\Http\Traits\Concerns\WithRenderVersioningTrait;

/**
 * Modal CRUD `hrmst_employees`.
 *
 * Sengaja hanya kolom identitas + unit kerja + status. Kolom gaji/payroll
 * (basic_saleri, grade, indexs, ptkp, jamsostek, rs_persen, jm_status, dll.)
 * masih diolah sistem Oracle Dev 6i — tidak disentuh di sini, dan saat update
 * tidak ikut ditulis supaya nilainya tetap.
 */
new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode = 'create'; // create|edit
    public string $originalEmpId = '';
    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    /** Pilihan dropdown (tabel referensi kecil, <20 baris). */
    public array $unitKerjaOptions = [];
    public array $jabatanOptions = [];
    public array $pendidikanOptions = [];
    public array $bisnisUnitOptions = [];

    public array $formKaryawanHr = [];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
        $this->resetFormFields();

        $this->unitKerjaOptions = DB::table('hrmst_workunits')->orderBy('wu_id')->get(['wu_id', 'wu_desc'])
            ->map(fn($row) => ['id' => (string) $row->wu_id, 'desc' => (string) $row->wu_desc])->all();
        $this->jabatanOptions = DB::table('hrmst_workstructures')->orderByRaw('to_number(ws_id)')->get(['ws_id', 'ws_desc'])
            ->map(fn($row) => ['id' => (string) $row->ws_id, 'desc' => (string) $row->ws_desc])->all();
        $this->pendidikanOptions = DB::table('rsmst_educations')->orderBy('edu_id')->get(['edu_id', 'edu_desc'])
            ->map(fn($row) => ['id' => (string) $row->edu_id, 'desc' => (string) $row->edu_desc])->all();
        $this->bisnisUnitOptions = DB::table('hrmst_bisnisunits')->orderByRaw('to_number(bu_id)')->get(['bu_id', 'bu_desc'])
            ->map(fn($row) => ['id' => (string) $row->bu_id, 'desc' => (string) $row->bu_desc])->all();
    }

    #[On('master.karyawan-hr.openCreate')]
    public function openCreate(): void
    {
        $this->resetFormFields();
        $this->formMode = 'create';
        $this->originalEmpId = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-karyawan-hr-actions');
        $this->dispatch('focus-karyawan-hr-emp-id');
    }

    #[On('master.karyawan-hr.openEdit')]
    public function openEdit(string $empId): void
    {
        $row = DB::table('hrmst_employees')->where('emp_id', $empId)->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data karyawan tidak ditemukan.');
            return;
        }

        $this->resetFormFields();
        $this->formMode = 'edit';
        $this->originalEmpId = $empId;
        $this->fillFormFromRow($row);

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-karyawan-hr-actions');
        $this->dispatch('focus-karyawan-hr-name');
    }

    protected function resetFormFields(): void
    {
        $this->formKaryawanHr = [
            'empId' => '',
            'name' => '',
            'sex' => '',
            'birthPlace' => null,
            'birthDate' => null,
            'address' => null,
            'startDate' => null,
            'wuId' => '',
            'wsId' => '',
            'eduId' => '',
            'buId' => '',
            'statusEd' => 'E',
            'omlopStatus' => 'N',
        ];

        $this->resetValidation();
    }

    protected function fillFormFromRow(object $row): void
    {
        $this->formKaryawanHr = [
            'empId' => (string) $row->emp_id,
            'name' => (string) ($row->name ?? ''),
            'sex' => in_array($row->sex, ['L', 'P'], true) ? $row->sex : '',
            'birthPlace' => $row->birth_place,
            'birthDate' => $this->formatTanggal($row->birth_date),
            'address' => $row->address,
            'startDate' => $this->formatTanggal($row->start_date),
            'wuId' => (string) ($row->wu_id ?? ''),
            'wsId' => (string) ($row->ws_id ?? ''),
            'eduId' => (string) ($row->edu_id ?? ''),
            'buId' => (string) ($row->bu_id ?? ''),
            // Hanya 'E' yang aktif; null/'D' → non-aktif (sama dengan filter LOV crew OK).
            'statusEd' => (string) $row->status_ed === 'E' ? 'E' : 'D',
            'omlopStatus' => (string) $row->omlop_status === 'Y' ? 'Y' : 'N',
        ];
    }

    private function formatTanggal(?string $tanggal): ?string
    {
        return empty($tanggal) ? null : Carbon::parse($tanggal)->format('d/m/Y');
    }

    /** Tanggal sudah lolos date_format:d/m/Y — aman dirakit ke to_date. */
    private function tanggalOracle(?string $tanggal)
    {
        return empty($tanggal) ? null : DB::raw("to_date('{$tanggal}', 'dd/mm/yyyy')");
    }

    protected function rules(): array
    {
        return [
            'formKaryawanHr.empId' => $this->formMode === 'create'
                ? ['required', 'string', 'max:25', 'regex:/^[A-Za-z0-9.\-]+$/', 'unique:hrmst_employees,emp_id']
                : ['required', 'string'],
            'formKaryawanHr.name' => 'required|string|max:50',
            'formKaryawanHr.sex' => 'required|in:L,P',
            'formKaryawanHr.birthPlace' => 'nullable|string|max:50',
            'formKaryawanHr.birthDate' => 'nullable|date_format:d/m/Y',
            'formKaryawanHr.address' => 'nullable|string|max:50',
            'formKaryawanHr.startDate' => 'nullable|date_format:d/m/Y',
            'formKaryawanHr.wuId' => 'required|exists:hrmst_workunits,wu_id',
            'formKaryawanHr.wsId' => 'required|exists:hrmst_workstructures,ws_id',
            'formKaryawanHr.eduId' => 'required|exists:rsmst_educations,edu_id',
            'formKaryawanHr.buId' => 'nullable|exists:hrmst_bisnisunits,bu_id',
            'formKaryawanHr.statusEd' => 'required|in:E,D',
            'formKaryawanHr.omlopStatus' => 'required|in:Y,N',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'max' => ':attribute maksimal :max karakter.',
            'unique' => ':attribute sudah digunakan.',
            'exists' => ':attribute tidak ada di master.',
            'in' => ':attribute tidak valid.',
            'date_format' => ':attribute harus berformat dd/mm/yyyy.',
            'formKaryawanHr.empId.regex' => ':attribute hanya boleh huruf, angka, titik, atau strip.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'formKaryawanHr.empId' => 'Emp ID',
            'formKaryawanHr.name' => 'Nama',
            'formKaryawanHr.sex' => 'Jenis Kelamin',
            'formKaryawanHr.birthPlace' => 'Tempat Lahir',
            'formKaryawanHr.birthDate' => 'Tanggal Lahir',
            'formKaryawanHr.address' => 'Alamat',
            'formKaryawanHr.startDate' => 'Tanggal Mulai Kerja',
            'formKaryawanHr.wuId' => 'Unit Kerja',
            'formKaryawanHr.wsId' => 'Jabatan',
            'formKaryawanHr.eduId' => 'Pendidikan',
            'formKaryawanHr.buId' => 'Bisnis Unit',
            'formKaryawanHr.statusEd' => 'Status Aktif',
            'formKaryawanHr.omlopStatus' => 'Petugas OK',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $form = $this->formKaryawanHr;
        $payload = [
            'name' => mb_strtoupper(trim($form['name'])),
            'sex' => $form['sex'],
            'birth_place' => $form['birthPlace'] ?: null,
            'birth_date' => $this->tanggalOracle($form['birthDate']),
            'address' => $form['address'] ?: null,
            'start_date' => $this->tanggalOracle($form['startDate']),
            'wu_id' => $form['wuId'],
            'ws_id' => $form['wsId'],
            'edu_id' => (int) $form['eduId'],
            'bu_id' => $form['buId'] !== '' ? $form['buId'] : null,
            'status_ed' => $form['statusEd'],
            'omlop_status' => $form['omlopStatus'],
        ];

        if ($this->formMode === 'create') {
            DB::table('hrmst_employees')->insert(['emp_id' => trim($form['empId']), ...$payload]);
        }

        if ($this->formMode === 'edit') {
            DB::table('hrmst_employees')->where('emp_id', $this->originalEmpId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data karyawan berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.karyawan-hr.saved');
    }

    public function closeModal(): void
    {
        $this->resetFormFields();
        $this->dispatch('close-modal', name: 'master-karyawan-hr-actions');
        $this->resetVersion();
    }

    #[On('master.karyawan-hr.requestDelete')]
    public function deleteFromGrid(string $empId): void
    {
        $isUsedInOk = DB::table('rstxn_oks')
            ->where(function ($subQuery) use ($empId) {
                $subQuery->where('emp_id_asistopr', $empId)
                    ->orWhere('emp_id_asistanes', $empId)
                    ->orWhere('emp_id_instrument', $empId)
                    ->orWhere('emp_id_changeanesdoc', $empId);
            })
            ->exists() || DB::table('rstxn_okomlops')->where('emp_id', $empId)->exists();

        if ($isUsedInOk) {
            $this->dispatch('toast', type: 'error', message: 'Karyawan tidak bisa dihapus karena sudah tercatat sebagai crew Kamar Operasi. Non-aktifkan saja.');
            return;
        }

        try {
            $deleted = DB::table('hrmst_employees')->where('emp_id', $empId)->delete();
        } catch (QueryException $e) {
            // FK dari tabel gaji/jasa (hrmst_empsals, hrmst_empjms, hrtxn_salhdrs, ...)
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Karyawan tidak bisa dihapus karena masih dipakai di data gaji/jasa. Non-aktifkan saja.');
                return;
            }

            throw $e;
        }

        if ($deleted === 0) {
            $this->dispatch('toast', type: 'error', message: 'Data karyawan tidak ditemukan.');
            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Data karyawan berhasil dihapus.');
        $this->dispatch('master.karyawan-hr.saved');
    }
};
?>

<div>
    <x-modal name="master-karyawan-hr-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-karyawan-hr-actions"
            event="master.karyawan-hr.saved"
            label="Karyawan HR"
            :wireKey="$this->renderKey('modal', [$formMode, $originalEmpId])">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 bg-surface-soft">
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>

                            <div>
                                <h2 class="ds-display-sm dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Data Karyawan HR' : 'Tambah Data Karyawan HR' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    Tandai "Petugas OK" untuk karyawan yang bertugas di Kamar Operasi. Data gaji tetap diolah di sistem lama.
                                </p>
                            </div>
                        </div>

                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" x-on:click="tryClose()">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft dark:bg-gray-950/20" x-enter-chain>
                <div class="grid max-w-5xl grid-cols-1 gap-4 lg:grid-cols-2"
                    x-data
                    x-on:focus-karyawan-hr-emp-id.window="$nextTick(() => setTimeout(() => $refs.inputEmpId?.focus(), 150))"
                    x-on:focus-karyawan-hr-name.window="$nextTick(() => setTimeout(() => $refs.inputName?.focus(), 150))">

                    <x-border-form title="Identitas">
                        <div class="space-y-4">
                            <div>
                                <x-input-label value="Emp ID" />
                                <x-text-input wire:model.live="formKaryawanHr.empId" x-ref="inputEmpId"
                                    :disabled="$formMode === 'edit'" :error="$errors->has('formKaryawanHr.empId')"
                                    class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('formKaryawanHr.empId')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label value="Nama" />
                                <x-text-input wire:model.live="formKaryawanHr.name" x-ref="inputName"
                                    :error="$errors->has('formKaryawanHr.name')" class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('formKaryawanHr.name')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label value="Jenis Kelamin" />
                                <x-select-input wire:model.live="formKaryawanHr.sex" :error="$errors->has('formKaryawanHr.sex')" class="mt-1">
                                    <option value="">— pilih —</option>
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </x-select-input>
                                <x-input-error :messages="$errors->get('formKaryawanHr.sex')" class="mt-1" />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <x-input-label value="Tempat Lahir" />
                                    <x-text-input wire:model.live="formKaryawanHr.birthPlace"
                                        :error="$errors->has('formKaryawanHr.birthPlace')" class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('formKaryawanHr.birthPlace')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Tanggal Lahir" />
                                    <x-text-input wire:model.live="formKaryawanHr.birthDate" placeholder="dd/mm/yyyy"
                                        :error="$errors->has('formKaryawanHr.birthDate')" class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('formKaryawanHr.birthDate')" class="mt-1" />
                                </div>
                            </div>

                            <div>
                                <x-input-label value="Alamat" />
                                <x-text-input wire:model.live="formKaryawanHr.address"
                                    :error="$errors->has('formKaryawanHr.address')" class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('formKaryawanHr.address')" class="mt-1" />
                            </div>
                        </div>
                    </x-border-form>

                    <x-border-form title="Kepegawaian">
                        <div class="space-y-4">
                            <div>
                                <x-input-label value="Unit Kerja" />
                                <x-select-input wire:model.live="formKaryawanHr.wuId" :error="$errors->has('formKaryawanHr.wuId')" class="mt-1">
                                    <option value="">— pilih —</option>
                                    @foreach ($unitKerjaOptions as $option)
                                        <option value="{{ $option['id'] }}">{{ $option['desc'] }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('formKaryawanHr.wuId')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label value="Jabatan" />
                                <x-select-input wire:model.live="formKaryawanHr.wsId" :error="$errors->has('formKaryawanHr.wsId')" class="mt-1">
                                    <option value="">— pilih —</option>
                                    @foreach ($jabatanOptions as $option)
                                        <option value="{{ $option['id'] }}">{{ $option['desc'] }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('formKaryawanHr.wsId')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label value="Pendidikan" />
                                <x-select-input wire:model.live="formKaryawanHr.eduId" :error="$errors->has('formKaryawanHr.eduId')" class="mt-1">
                                    <option value="">— pilih —</option>
                                    @foreach ($pendidikanOptions as $option)
                                        <option value="{{ $option['id'] }}">{{ $option['desc'] }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('formKaryawanHr.eduId')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label value="Bisnis Unit (opsional)" />
                                <x-select-input wire:model.live="formKaryawanHr.buId" :error="$errors->has('formKaryawanHr.buId')" class="mt-1">
                                    <option value="">— tidak ada —</option>
                                    @foreach ($bisnisUnitOptions as $option)
                                        <option value="{{ $option['id'] }}">{{ $option['desc'] }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('formKaryawanHr.buId')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label value="Tanggal Mulai Kerja" />
                                <x-text-input wire:model.live="formKaryawanHr.startDate" placeholder="dd/mm/yyyy"
                                    :error="$errors->has('formKaryawanHr.startDate')" class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('formKaryawanHr.startDate')" class="mt-1" />
                            </div>

                            <div class="flex flex-wrap gap-6 pt-2">
                                <x-toggle wire:model.live="formKaryawanHr.omlopStatus" trueValue="Y" falseValue="N">
                                    Petugas OK
                                </x-toggle>
                                <x-toggle wire:model.live="formKaryawanHr.statusEd" trueValue="E" falseValue="D">
                                    Status Aktif
                                </x-toggle>
                            </div>
                        </div>
                    </x-border-form>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-surface-soft border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="tryClose()">
                        Batal
                    </x-secondary-button>

                    <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                        <span wire:loading.remove>Simpan</span>
                        <span wire:loading>Menyimpan...</span>
                    </x-primary-button>
                </div>
            </div>

        </x-dirty-modal-content>
    </x-modal>
</div>
