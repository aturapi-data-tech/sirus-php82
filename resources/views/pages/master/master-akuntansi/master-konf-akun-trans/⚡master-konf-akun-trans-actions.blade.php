<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode   = 'create';
    public string $originalId = '';
    public array  $renderVersions = [];
    protected array $renderAreas  = ['modal'];

    public array $form = [
        'conf_id'   => '',
        'conf_desc' => '',
        'acc_id'    => '',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.konf-akun-trans.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-konf-akun-trans-actions');
    }

    #[On('master.konf-akun-trans.openEdit')]
    public function openEdit(string $confId): void
    {
        $row = DB::table('tkacc_confacctxns')->where('conf_id', $confId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $confId;
        $this->form = [
            'conf_id'   => (string) $row->conf_id,
            'conf_desc' => (string) ($row->conf_desc ?? ''),
            'acc_id'    => (string) ($row->acc_id ?? ''),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-konf-akun-trans-actions');
    }

    #[On('lov.selected.konf-akun-trans-acc')]
    public function onAkunSelected(string $target, ?array $payload): void
    {
        $this->form['acc_id'] = (string) ($payload['acc_id'] ?? '');
    }

    #[On('master.konf-akun-trans.requestDelete')]
    public function deleteKonf(string $confId): void
    {
        try {
            $deleted = DB::table('tkacc_confacctxns')->where('conf_id', $confId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Konfigurasi tidak ditemukan.');
                return;
            }
            $this->dispatch('toast', type: 'success', message: 'Konfigurasi berhasil dihapus.');
            $this->dispatch('master.konf-akun-trans.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error',
                    message: 'Konfigurasi masih dipakai oleh transaksi.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $rules = [
            'form.conf_id' => $this->formMode === 'create'
                ? 'required|string|max:240|regex:/^[A-Z0-9_.-]+$/|unique:tkacc_confacctxns,conf_id'
                : 'required|string',
            'form.conf_desc' => 'nullable|string|max:240',
            'form.acc_id'    => 'required|string|max:25|exists:acmst_accounts,acc_id',
        ];

        $messages = [
            'form.conf_id.required' => 'CONF ID wajib diisi.',
            'form.conf_id.regex'    => 'CONF ID hanya boleh huruf besar/angka/underscore/dot/dash.',
            'form.conf_id.unique'   => 'CONF ID sudah digunakan.',
            'form.acc_id.required'  => 'Akun wajib dipilih.',
            'form.acc_id.exists'    => 'Akun tidak valid.',
        ];

        $attributes = [
            'form.conf_id'   => 'CONF ID',
            'form.conf_desc' => 'Deskripsi',
            'form.acc_id'    => 'Akun',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'conf_desc' => $this->form['conf_desc'] !== ''
                ? mb_strtoupper($this->form['conf_desc']) : null,
            'acc_id'    => $this->form['acc_id'],
        ];

        if ($this->formMode === 'create') {
            DB::table('tkacc_confacctxns')->insert([
                'conf_id' => mb_strtoupper($this->form['conf_id']),
                ...$payload,
            ]);
        } else {
            DB::table('tkacc_confacctxns')->where('conf_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Konfigurasi berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.konf-akun-trans.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-konf-akun-trans-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = ['conf_id' => '', 'conf_desc' => '', 'acc_id' => ''];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-konf-akun-trans-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-konf-akun-trans-actions"
            event="master.konf-akun-trans.saved"
            label="Konfigurasi Akun Transaksi"
            :wireKey="$this->renderKey('modal', [$formMode, $originalId])">

            <x-modul-dokumen.header :judul="$formMode === 'edit' ? 'Ubah Konfigurasi Akun' : 'Tambah Konfigurasi Akun'"
                ikon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                tutupAlpine="tryClose()">
                Mapping <em>CONF_ID</em> → <em>ACC_ID</em>. Dipakai transaksi untuk menentukan akun default.
                <x-slot:badge>
                    <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                        {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                    </x-badge>
                </x-slot:badge>
            </x-modul-dokumen.header>

            <div class="flex-1 px-4 py-4 bg-surface-soft dark:bg-gray-950/20" x-enter-chain>
                <x-border-form title="Data Konfigurasi">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="CONF ID" :required="true" />
                                <x-text-input wire:model.live="form.conf_id"
                                    maxlength="240" :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.conf_id')"
                                    class="w-full mt-1 uppercase" />
                                <x-input-error :messages="$errors->get('form.conf_id')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Deskripsi" />
                                <x-text-input wire:model.live="form.conf_desc"
                                    maxlength="240"
                                    :error="$errors->has('form.conf_desc')"
                                    class="w-full mt-1 uppercase" />
                                <x-input-error :messages="$errors->get('form.conf_desc')" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <livewire:lov.akun.lov-akun
                                target="konf-akun-trans-acc"
                                label="Akun"
                                placeholder="Cari akun (kode/nama)..."
                                :initialAccId="$form['acc_id'] ?? null"
                                wire:key="lov-konf-akun-{{ $originalId ?? 'new' }}-{{ $renderVersions['modal'] ?? 0 }}" />
                            <x-input-error :messages="$errors->get('form.acc_id')" class="mt-1" />
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
