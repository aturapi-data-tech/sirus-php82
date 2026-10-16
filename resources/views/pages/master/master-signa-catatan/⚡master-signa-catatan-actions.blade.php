<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode      = 'create';
    public string $originalCatatan = '';
    public array  $renderVersions = [];
    protected array $renderAreas  = ['modal'];

    public array $form = [
        'catatan'       => '',
        'active_status' => '1',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.signa-catatan.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode        = 'create';
        $this->originalCatatan = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-signa-catatan-actions');
    }

    #[On('master.signa-catatan.openEdit')]
    public function openEdit(string $catatan): void
    {
        $row = DB::table('rsmst_signa_catatans')
            ->where('catatan', $catatan)
            ->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data catatan tidak ditemukan.');
            return;
        }

        $this->resetForm();
        $this->formMode        = 'edit';
        $this->originalCatatan = (string) $row->catatan;
        $this->form = [
            'catatan'       => (string) $row->catatan,
            'active_status' => (string) ($row->active_status ?? '1'),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-signa-catatan-actions');
    }

    #[On('master.signa-catatan.requestDelete')]
    public function deleteCatatan(string $catatan): void
    {
        $deleted = DB::table('rsmst_signa_catatans')->where('catatan', $catatan)->delete();
        if ($deleted === 0) {
            $this->dispatch('toast', type: 'error', message: 'Data catatan tidak ditemukan.');
            return;
        }
        $this->dispatch('toast', type: 'success', message: 'Catatan berhasil dihapus.');
        $this->dispatch('master.signa-catatan.saved');
    }

    public function save(): void
    {
        $rules = [
            'form.catatan' => [
                'required', 'string', 'max:255',
                $this->formMode === 'create'
                    ? Rule::unique('rsmst_signa_catatans', 'catatan')
                    : Rule::unique('rsmst_signa_catatans', 'catatan')->ignore($this->originalCatatan, 'catatan'),
            ],
            'form.active_status' => 'required|in:0,1',
        ];

        $messages = [
            'form.catatan.required' => 'Teks catatan wajib diisi.',
            'form.catatan.max'      => 'Teks catatan maksimal 255 karakter.',
            'form.catatan.unique'   => 'Catatan ini sudah terdaftar.',
            'form.active_status.in' => 'Status tidak valid.',
        ];

        $attributes = [
            'form.catatan'       => 'Catatan',
            'form.active_status' => 'Status',
        ];

        $this->validate($rules, $messages, $attributes);

        $catatan       = trim($this->form['catatan']);
        $activeStatus  = $this->form['active_status'] === '0' ? '0' : '1';

        if ($this->formMode === 'create') {
            DB::table('rsmst_signa_catatans')->insert([
                'catatan'       => $catatan,
                'active_status' => $activeStatus,
            ]);
        } else {
            DB::table('rsmst_signa_catatans')
                ->where('catatan', $this->originalCatatan)
                ->update([
                    'catatan'       => $catatan,
                    'active_status' => $activeStatus,
                ]);
        }

        $this->dispatch('toast', type: 'success', message: 'Catatan berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.signa-catatan.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-signa-catatan-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = [
            'catatan'       => '',
            'active_status' => '1',
        ];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-signa-catatan-actions" size="md" focusable>
        <x-dirty-modal-content
            name="master-signa-catatan-actions"
            event="master.signa-catatan.saved"
            label="Catatan Khusus Signa"
            :wireKey="$this->renderKey('modal', [$formMode, $originalCatatan])">

            <x-modul-dokumen.header
                :judul="$formMode === 'edit' ? 'Ubah Catatan Khusus Signa' : 'Tambah Catatan Khusus Signa'"
                ikon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                tutupAlpine="tryClose()">
                LOV catatan khusus signa untuk e-resep (RJ/UGD/RI).
                <x-slot:badge>
                    <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                        {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                    </x-badge>
                </x-slot:badge>
            </x-modul-dokumen.header>

            <div class="flex-1 px-4 py-4 bg-surface-soft dark:bg-gray-950/20" x-enter-chain>
                <x-border-form title="Data Catatan">
                    <div class="space-y-4">
                        <div>
                            <x-input-label for="form.catatan" value="Catatan" />
                            <x-text-input id="form.catatan" type="text"
                                wire:model.live="form.catatan"
                                maxlength="255"
                                placeholder="cth: Habiskan, Untuk obat luar, Sesudah makan..."
                                class="w-full mt-1" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Maks 255 karakter. Teks ini yang akan muncul di dropdown e-resep.
                            </p>
                            <x-input-error :messages="$errors->get('form.catatan')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label value="Status" />
                            <div class="mt-2">
                                <x-toggle wire:model.live="form.active_status" trueValue="1" falseValue="0">
                                    {{ $form['active_status'] === '1' ? 'Aktif' : 'Nonaktif' }}
                                </x-toggle>
                            </div>
                            <x-input-error :messages="$errors->get('form.active_status')" class="mt-1" />
                        </div>
                    </div>
                </x-border-form>
            </div>

            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-surface-soft border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="tryClose()">Batal</x-secondary-button>
                    <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                        <span wire:loading.remove>Simpan</span>
                        <span wire:loading>Saving...</span>
                    </x-primary-button>
                </div>
            </div>

        </x-dirty-modal-content>
    </x-modal>
</div>
