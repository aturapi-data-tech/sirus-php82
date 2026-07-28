<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode = 'create';
    public string $originalId = '';
    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    public array $form = [
        'loinc_code' => '',
        'display' => '',
        'display_id' => '',
        'component' => '',
        'loinc_class' => '',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    // ─── Open Create ──────────────────────────────────────────────────────────
    #[On('master.loinc.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode = 'create';
        $this->originalId = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-loinc-actions');
        $this->dispatch('focus-loinc-code');
    }

    // ─── Open Edit ────────────────────────────────────────────────────────────
    #[On('master.loinc.openEdit')]
    public function openEdit(string $loincCode): void
    {
        $row = DB::table('rsmst_loinc_codes')->where('loinc_code', $loincCode)->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data LOINC tidak ditemukan.');
            return;
        }

        $this->resetForm();
        $this->formMode = 'edit';
        $this->originalId = (string) $row->loinc_code;
        $this->form = [
            'loinc_code' => (string) $row->loinc_code,
            'display' => (string) ($row->display ?? ''),
            'display_id' => (string) ($row->display_id ?? ''),
            'component' => (string) ($row->component ?? ''),
            'loinc_class' => (string) ($row->loinc_class ?? ''),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-loinc-actions');
        $this->dispatch('focus-loinc-display-id');
    }

    // ─── Delete ───────────────────────────────────────────────────────────────
    #[On('master.loinc.requestDelete')]
    public function deleteLoinc(string $loincCode): void
    {
        try {
            $deleted = DB::table('rsmst_loinc_codes')->where('loinc_code', $loincCode)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data LOINC tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Kode LOINC berhasil dihapus.');
            $this->dispatch('master.loinc.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Kode LOINC tidak bisa dihapus karena masih dipakai di item pemeriksaan.');
                return;
            }
            throw $e;
        }
    }

    // ─── Save ─────────────────────────────────────────────────────────────────
    public function save(): void
    {
        $rules = [
            'form.loinc_code' => $this->formMode === 'create'
                ? 'required|string|max:20|unique:rsmst_loinc_codes,loinc_code'
                : 'required|string|max:20',
            'form.display' => 'required|string|max:500',
            'form.display_id' => 'nullable|string|max:500',
            'form.component' => 'nullable|string|max:200',
            'form.loinc_class' => 'nullable|string|max:100',
        ];

        $messages = [
            'form.loinc_code.required' => 'Kode LOINC wajib diisi.',
            'form.loinc_code.max' => 'Kode LOINC maksimal 20 karakter.',
            'form.loinc_code.unique' => 'Kode LOINC sudah terdaftar.',
            'form.display.required' => 'Display (nama resmi LOINC) wajib diisi.',
            'form.display.max' => 'Display maksimal 500 karakter.',
            'form.display_id.max' => 'Nama Indonesia maksimal 500 karakter.',
            'form.component.max' => 'Komponen maksimal 200 karakter.',
            'form.loinc_class.max' => 'Kelas LOINC maksimal 100 karakter.',
        ];

        $attributes = [
            'form.loinc_code' => 'Kode LOINC',
            'form.display' => 'Display (LOINC)',
            'form.display_id' => 'Nama Indonesia',
            'form.component' => 'Komponen',
            'form.loinc_class' => 'Kelas LOINC',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'display' => trim($this->form['display']),
            'display_id' => trim($this->form['display_id']) ?: null,
            'component' => trim($this->form['component']) ?: null,
            'loinc_class' => trim($this->form['loinc_class']) ?: null,
        ];

        if ($this->formMode === 'create') {
            DB::table('rsmst_loinc_codes')->insert([
                'loinc_code' => trim($this->form['loinc_code']),
                ...$payload,
                'created_at' => now(),
            ]);
        } else {
            DB::table('rsmst_loinc_codes')->where('loinc_code', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Kode LOINC berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.loinc.saved');
    }

    // ─── Close ────────────────────────────────────────────────────────────────
    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-loinc-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = [
            'loinc_code' => '',
            'display' => '',
            'display_id' => '',
            'component' => '',
            'loinc_class' => '',
        ];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-loinc-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-loinc-actions"
            event="master.loinc.saved"
            label="Kode LOINC"
            :wireKey="$this->renderKey('modal', [$formMode, $originalId])">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 bg-surface-soft">
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="Logo" class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="Logo" class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="ds-display-sm dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Kode LOINC' : 'Tambah Kode LOINC' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    Kode LOINC dipakai LOV pemeriksaan lab & pengiriman Observation ke SATUSEHAT.
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
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft dark:bg-gray-950/20" x-enter-chain
                 x-data
                 x-on:focus-loinc-code.window="$nextTick(() => setTimeout(() => $refs.inputLoincCode?.focus(), 150))"
                 x-on:focus-loinc-display-id.window="$nextTick(() => setTimeout(() => $refs.inputDisplayId?.focus(), 150))">

                <x-border-form title="Data Kode LOINC" class="max-w-4xl">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="Kode LOINC" :required="true" />
                                <x-text-input wire:model.live="form.loinc_code" x-ref="inputLoincCode"
                                    maxlength="20" placeholder="contoh: 718-7"
                                    :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.loinc_code')"
                                    class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputDisplayId?.focus()" />
                                <x-input-error :messages="$errors->get('form.loinc_code')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Nama Indonesia" />
                                <x-text-input wire:model.live="form.display_id" x-ref="inputDisplayId"
                                    maxlength="500" placeholder="contoh: Hemoglobin"
                                    :error="$errors->has('form.display_id')"
                                    class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputDisplay?.focus()" />
                                <x-input-error :messages="$errors->get('form.display_id')" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Display (nama resmi LOINC)" :required="true" />
                            <x-text-input wire:model.live="form.display" x-ref="inputDisplay"
                                maxlength="500" placeholder="contoh: Hemoglobin [Mass/volume] in Blood"
                                :error="$errors->has('form.display')"
                                class="w-full mt-1"
                                x-on:keydown.enter.prevent="$refs.inputComponent?.focus()" />
                            <x-input-error :messages="$errors->get('form.display')" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label value="Komponen" />
                                <x-text-input wire:model.live="form.component" x-ref="inputComponent"
                                    maxlength="200" placeholder="contoh: Hemoglobin"
                                    :error="$errors->has('form.component')"
                                    class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputLoincClass?.focus()" />
                                <x-input-error :messages="$errors->get('form.component')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Kelas LOINC" />
                                <x-text-input wire:model.live="form.loinc_class" x-ref="inputLoincClass"
                                    maxlength="100" placeholder="contoh: HEM/BC, CHEM, UA"
                                    :error="$errors->has('form.loinc_class')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$wire.save()" />
                                <x-input-error :messages="$errors->get('form.loinc_class')" class="mt-1" />
                            </div>
                        </div>
                    </div>
                </x-border-form>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-surface-soft border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-muted dark:text-gray-400">
                        <kbd class="px-1.5 py-0.5 text-xs font-semibold bg-surface-card border border-hairline rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                        <span class="mx-0.5">di field terakhir untuk simpan</span>
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" x-on:click="tryClose()">Batal</x-secondary-button>
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
