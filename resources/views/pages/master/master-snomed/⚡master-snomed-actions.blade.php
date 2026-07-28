<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    /** Value set yang dipakai aplikasi (LOV keluhan, prosedur, alergi). */
    public const VALUE_SETS = ['condition-code', 'procedure-code', 'substance-code'];

    public string $formMode = 'create';
    public string $originalId = '';
    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    public array $form = [
        'snomed_code' => '',
        'display_en' => '',
        'display_id' => '',
        'value_set' => 'condition-code',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[Computed]
    public function valueSetOptions(): array
    {
        return self::VALUE_SETS;
    }

    // ─── Open Create ──────────────────────────────────────────────────────────
    #[On('master.snomed.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode = 'create';
        $this->originalId = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-snomed-actions');
        $this->dispatch('focus-snomed-code');
    }

    // ─── Open Edit ────────────────────────────────────────────────────────────
    #[On('master.snomed.openEdit')]
    public function openEdit(string $snomedCode): void
    {
        $row = DB::table('rsmst_snomed_codes')->where('snomed_code', $snomedCode)->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data SNOMED tidak ditemukan.');
            return;
        }

        $this->resetForm();
        $this->formMode = 'edit';
        $this->originalId = (string) $row->snomed_code;
        $this->form = [
            'snomed_code' => (string) $row->snomed_code,
            'display_en' => (string) ($row->display_en ?? ''),
            'display_id' => (string) ($row->display_id ?? ''),
            'value_set' => (string) ($row->value_set ?? 'condition-code'),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-snomed-actions');
        $this->dispatch('focus-snomed-display-id');
    }

    // ─── Delete ───────────────────────────────────────────────────────────────
    #[On('master.snomed.requestDelete')]
    public function deleteSnomed(string $snomedCode): void
    {
        try {
            $deleted = DB::table('rsmst_snomed_codes')->where('snomed_code', $snomedCode)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data SNOMED tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Kode SNOMED berhasil dihapus.');
            $this->dispatch('master.snomed.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Kode SNOMED tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    // ─── Save ─────────────────────────────────────────────────────────────────
    public function save(): void
    {
        $rules = [
            'form.snomed_code' => $this->formMode === 'create'
                ? 'required|string|max:20|unique:rsmst_snomed_codes,snomed_code'
                : 'required|string|max:20',
            'form.display_en' => 'required|string|max:500',
            'form.display_id' => 'nullable|string|max:500',
            'form.value_set' => 'required|string|max:50|in:' . implode(',', self::VALUE_SETS),
        ];

        $messages = [
            'form.snomed_code.required' => 'Kode SNOMED wajib diisi.',
            'form.snomed_code.max' => 'Kode SNOMED maksimal 20 karakter.',
            'form.snomed_code.unique' => 'Kode SNOMED sudah terdaftar.',
            'form.display_en.required' => 'Display (istilah resmi SNOMED) wajib diisi.',
            'form.display_en.max' => 'Display maksimal 500 karakter.',
            'form.display_id.max' => 'Istilah Indonesia maksimal 500 karakter.',
            'form.value_set.required' => 'Value set wajib dipilih.',
            'form.value_set.in' => 'Value set tidak dikenal.',
        ];

        $attributes = [
            'form.snomed_code' => 'Kode SNOMED',
            'form.display_en' => 'Display (SNOMED)',
            'form.display_id' => 'Istilah Indonesia',
            'form.value_set' => 'Value set',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'display_en' => trim($this->form['display_en']),
            'display_id' => trim($this->form['display_id']) ?: null,
            'value_set' => $this->form['value_set'],
        ];

        if ($this->formMode === 'create') {
            DB::table('rsmst_snomed_codes')->insert([
                'snomed_code' => trim($this->form['snomed_code']),
                ...$payload,
                'created_at' => now(),
            ]);
        } else {
            DB::table('rsmst_snomed_codes')->where('snomed_code', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Kode SNOMED berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.snomed.saved');
    }

    // ─── Close ────────────────────────────────────────────────────────────────
    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-snomed-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = [
            'snomed_code' => '',
            'display_en' => '',
            'display_id' => '',
            'value_set' => 'condition-code',
        ];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-snomed-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-snomed-actions"
            event="master.snomed.saved"
            label="Kode SNOMED"
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
                                    {{ $formMode === 'edit' ? 'Ubah Kode SNOMED' : 'Tambah Kode SNOMED' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    Kode SNOMED CT dipakai LOV keluhan/prosedur/alergi & pengiriman SATUSEHAT.
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
                 x-on:focus-snomed-code.window="$nextTick(() => setTimeout(() => $refs.inputSnomedCode?.focus(), 150))"
                 x-on:focus-snomed-display-id.window="$nextTick(() => setTimeout(() => $refs.inputDisplayId?.focus(), 150))">

                <x-border-form title="Data Kode SNOMED CT" class="max-w-4xl">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="Kode SNOMED" :required="true" />
                                <x-text-input wire:model.live="form.snomed_code" x-ref="inputSnomedCode"
                                    maxlength="20" placeholder="contoh: 386661006"
                                    :disabled="$formMode === 'edit'"
                                    :error="$errors->has('form.snomed_code')"
                                    class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputDisplayId?.focus()" />
                                <x-input-error :messages="$errors->get('form.snomed_code')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Istilah Indonesia" />
                                <x-text-input wire:model.live="form.display_id" x-ref="inputDisplayId"
                                    maxlength="500" placeholder="contoh: Demam"
                                    :error="$errors->has('form.display_id')"
                                    class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputDisplayEn?.focus()" />
                                <x-input-error :messages="$errors->get('form.display_id')" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Display (istilah resmi SNOMED)" :required="true" />
                            <x-text-input wire:model.live="form.display_en" x-ref="inputDisplayEn"
                                maxlength="500" placeholder="contoh: Fever"
                                :error="$errors->has('form.display_en')"
                                class="w-full mt-1"
                                x-on:keydown.enter.prevent="$refs.inputValueSet?.focus()" />
                            <x-input-error :messages="$errors->get('form.display_en')" class="mt-1" />
                        </div>

                        <div class="sm:w-1/2">
                            <x-input-label value="Value Set" :required="true" />
                            <x-select-input wire:model.live="form.value_set" x-ref="inputValueSet"
                                class="w-full mt-1"
                                x-on:keydown.enter.prevent="$wire.save()">
                                @foreach ($this->valueSetOptions as $vs)
                                    <option value="{{ $vs }}">{{ $vs }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('form.value_set')" class="mt-1" />
                            <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                <strong>condition-code</strong> = keluhan/diagnosis ·
                                <strong>procedure-code</strong> = tindakan ·
                                <strong>substance-code</strong> = zat/alergen.
                            </p>
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
