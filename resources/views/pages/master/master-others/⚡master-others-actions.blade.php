<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode = 'create'; // create|edit
    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    public ?string $otherId = null;
    public string $otherDesc = '';
    public ?string $otherPrice = null;
    public string $activeStatus = '1'; // default active

       public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.others.openCreate')]
    public function openCreate(): void
    {
        $this->resetFormFields();
        $this->formMode = 'create';
        $this->resetValidation();
        $this->incrementVersion('modal');

        $this->dispatch('open-modal', name: 'master-others-actions');
    }

    #[On('master.others.openEdit')]
    public function openEdit(string $otherId): void
    {
        $row = DB::table('rsmst_others')->where('other_id', $otherId)->first();
        if (!$row) {
            return;
        }

        $this->resetFormFields();
        $this->formMode = 'edit';
        $this->fillFormFromRow($row);
        $this->resetValidation();
        $this->incrementVersion('modal');

        $this->dispatch('open-modal', name: 'master-others-actions');
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'master-others-actions');
    }

    protected function resetFormFields(): void
    {
        $this->reset(['otherId', 'otherDesc', 'otherPrice', 'activeStatus']);
        $this->activeStatus = '1';
        $this->resetVersion();
    }

    protected function fillFormFromRow(object $row): void
    {
        $this->otherId = (string) $row->other_id;
        $this->otherDesc = (string) ($row->other_desc ?? '');
        $this->otherPrice = $row->other_price;
        $this->activeStatus = (string) ($row->active_status ?? '1');
    }

    protected function rules(): array
    {
        return [
            'otherId' => ['required', 'numeric', $this->formMode === 'create' ? Rule::unique('rsmst_others', 'other_id') : Rule::unique('rsmst_others', 'other_id')->ignore($this->otherId, 'other_id')],
            'otherDesc' => ['required', 'string', 'max:255'],
            'otherPrice' => ['required', 'numeric', 'min:0'],
            'activeStatus' => ['required', Rule::in(['0', '1'])],
        ];
    }

    protected function messages(): array
    {
        return [
            'otherId.required' => ':attribute wajib diisi.',
            'otherId.numeric' => ':attribute harus berupa angka.',
            'otherId.unique' => ':attribute sudah digunakan, silakan pilih ID lain.',

            'otherDesc.required' => ':attribute wajib diisi.',
            'otherDesc.max' => ':attribute maksimal :max karakter.',

            'otherPrice.required' => ':attribute wajib diisi.',
            'otherPrice.numeric' => ':attribute harus berupa angka.',
            'otherPrice.min' => ':attribute tidak boleh kurang dari 0.',

            'activeStatus.required' => ':attribute wajib dipilih.',
            'activeStatus.in' => ':attribute tidak valid.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'otherId' => 'ID Lain-lain',
            'otherDesc' => 'Nama Lain-lain',
            'otherPrice' => 'Harga',
            'activeStatus' => 'Status Aktif',
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        $payload = [
            'other_desc' => $data['otherDesc'],
            'other_price' => $data['otherPrice'],
            'active_status' => $data['activeStatus'],
        ];

        if ($this->formMode === 'create') {
            DB::table('rsmst_others')->insert([
                'other_id' => $data['otherId'],
                ...$payload,
            ]);
        } else {
            DB::table('rsmst_others')->where('other_id', $data['otherId'])->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data lain-lain berhasil disimpan.');
        $this->closeModal();

        $this->dispatch('master.others.saved');
    }

    #[On('master.others.toggleActive')]
    public function toggleActive(string $otherId): void
    {
        $cur = (string) DB::table('rsmst_others')->where('other_id', $otherId)->value('active_status');
        $next = $cur === '1' ? '0' : '1';
        DB::table('rsmst_others')->where('other_id', $otherId)->update(['active_status' => $next]);
        $this->dispatch('toast', type: 'success',
            message: 'Status → ' . ($next === '1' ? 'Aktif' : 'Tidak Aktif'));
        $this->dispatch('master.others.saved');
    }

    #[On('master.others.requestDelete')]
    public function deleteFromGrid(string $otherId): void
    {
        try {
            // Cek apakah data sudah dipakai di tabel transaksi
            $isUsed = DB::table('rstxn_rjhdrs')->where('other_id', $otherId)->exists();

            if ($isUsed) {
                $this->dispatch('toast', type: 'error', message: 'Data lain-lain sudah dipakai pada transaksi Rawat Jalan.');
                return;
            }

            $deleted = DB::table('rsmst_others')->where('other_id', $otherId)->delete();

            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data lain-lain tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Data lain-lain berhasil dihapus.');
            $this->dispatch('master.others.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Data tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }

            throw $e;
        }
    }
};
?>

<div>
    <x-modal name="master-others-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-others-actions"
            event="master.others.saved"
            label="Lain-Lain"
            :wireKey="$this->renderKey('modal', [$formMode, $otherId ?? 'new'])">

            {{-- HEADER --}}
            <x-modul-dokumen.header
                :judul="$formMode === 'edit' ? 'Ubah Data Lain-lain' : 'Tambah Data Lain-lain'"
                ikon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                tutupAlpine="tryClose()">
                Lengkapi informasi lain-lain untuk kebutuhan aplikasi.
                <x-slot:badge>
                    <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                        {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                    </x-badge>
                </x-slot:badge>
            </x-modul-dokumen.header>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft dark:bg-gray-950/20" x-enter-chain>
                <div class="max-w-4xl">
                    <x-border-form title="Informasi Dasar">
                        <div class="space-y-5">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {{-- Other ID --}}
                                <div>
                                    <x-input-label value="ID Lain-lain" />
                                    <x-text-input wire:model.live="otherId" :disabled="$formMode === 'edit'"
                                        :error="$errors->has('otherId')" class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('otherId')" class="mt-1" />
                                </div>

                                {{-- Status Aktif --}}
                                <div>
                                    <x-input-label value="Status Aktif" />
                                    <x-select-input wire:model.live="activeStatus"
                                        :error="$errors->has('activeStatus')" class="w-full mt-1">
                                        <option value="1">Aktif</option>
                                        <option value="0">Tidak Aktif</option>
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('activeStatus')" class="mt-1" />
                                </div>
                            </div>

                            {{-- Nama Lain-lain --}}
                            <div>
                                <x-input-label value="Nama Lain-lain" />
                                <x-text-input wire:model.live="otherDesc" :error="$errors->has('otherDesc')"
                                    class="w-full mt-1" placeholder="Contoh: Administrasi, Ambulans, dll" />
                                <x-input-error :messages="$errors->get('otherDesc')" class="mt-1" />
                            </div>

                            {{-- Harga --}}
                            <div>
                                <x-input-label value="Harga" />
                                <x-text-input-number wire:model="otherPrice" :error="$errors->has('otherPrice')"
                                    class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('otherPrice')" class="mt-1" />
                            </div>
                        </div>
                    </x-border-form>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-surface-soft border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-muted dark:text-gray-400">
                        Pastikan data sudah benar sebelum menyimpan.
                    </div>

                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" x-on:click="tryClose()">
                            Batal
                        </x-secondary-button>

                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove>Simpan</span>
                            <span wire:loading>Saving...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </x-dirty-modal-content>
    </x-modal>
</div>