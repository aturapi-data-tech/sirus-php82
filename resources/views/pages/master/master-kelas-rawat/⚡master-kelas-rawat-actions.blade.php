<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode   = 'create';
    public int    $originalId = 0;
    public array  $renderVersions = [];
    protected array $renderAreas  = ['modal'];

    public array $form = [
        'class_id'   => '',
        'class_desc' => '',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    // ─── Open Create ──────────────────────────────────────────
    #[On('master.class.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = 0;
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-class-actions');
        $this->dispatch('focus-class-id');
    }

    // ─── Open Edit ────────────────────────────────────────────
    #[On('master.class.openEdit')]
    public function openEdit(int $classId): void
    {
        $row = DB::table('rsmst_class')->where('class_id', $classId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $classId;
        $this->form = [
            'class_id'   => (string) $row->class_id,
            'class_desc' => (string) ($row->class_desc ?? ''),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-class-actions');
        $this->dispatch('focus-class-desc');
    }

    // ─── Delete ───────────────────────────────────────────────
    #[On('master.class.requestDelete')]
    public function deleteClass(int $classId): void
    {
        try {
            $inUse = DB::table('rsmst_rooms')->where('class_id', $classId)->exists();
            if ($inUse) {
                $this->dispatch('toast', type: 'error', message: 'Kelas tidak bisa dihapus karena masih dipakai pada data kamar.');
                return;
            }

            $deleted = DB::table('rsmst_class')->where('class_id', $classId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data kelas tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Kelas berhasil dihapus.');
            $this->dispatch('master.class.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Kelas tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    // ─── Save ─────────────────────────────────────────────────
    public function save(): void
    {
        $this->validate([
            'form.class_id'   => $this->formMode === 'create'
                ? 'required|integer|unique:rsmst_class,class_id'
                : 'required|integer',
            'form.class_desc' => 'required|string|max:20',
        ], [], [
            'form.class_id'   => 'ID Kelas',
            'form.class_desc' => 'Nama Kelas',
        ]);

        $payload = [
            'class_desc' => $this->form['class_desc'],
        ];

        if ($this->formMode === 'create') {
            DB::table('rsmst_class')->insert(['class_id' => (int) $this->form['class_id'], ...$payload]);
        } else {
            DB::table('rsmst_class')->where('class_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data kelas berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.class.saved');
    }

    // ─── Close ────────────────────────────────────────────────
    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-class-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = ['class_id' => '', 'class_desc' => ''];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-class-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-class-actions"
            event="master.class.saved"
            label="Kelas Rawat"
            :wireKey="$this->renderKey('modal', [$formMode, $originalId])">

            {{-- HEADER --}}
            <x-modul-dokumen.header :judul="$formMode === 'edit' ? 'Ubah Data Kelas Rawat' : 'Tambah Data Kelas Rawat'"
                ikon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                tutupAlpine="tryClose()">
                Lengkapi data berikut lalu klik Simpan.
                <x-slot:badge>
                    <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                        {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                    </x-badge>
                </x-slot:badge>
            </x-modul-dokumen.header>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft dark:bg-gray-950/20" x-enter-chain>
                <div class="max-w-xl"
                     x-data
                     x-on:focus-class-id.window="$nextTick(() => setTimeout(() => $refs.inputClassId?.focus(), 150))"
                     x-on:focus-class-desc.window="$nextTick(() => setTimeout(() => $refs.inputClassDesc?.focus(), 150))">

                    <x-border-form title="Data Kelas Rawat">
                        <div class="space-y-5">
                            {{-- ID + Nama --}}
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label value="ID Kelas" />
                                    <x-text-input wire:model.live="form.class_id" x-ref="inputClassId"
                                        type="number" min="1"
                                        :disabled="$formMode === 'edit'"
                                        :error="$errors->has('form.class_id')"
                                        class="w-full mt-1"
                                        x-on:keydown.enter.prevent="$refs.inputClassDesc?.focus()" />
                                    <x-input-error :messages="$errors->get('form.class_id')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Nama Kelas" />
                                    <x-text-input wire:model.live="form.class_desc" x-ref="inputClassDesc"
                                        maxlength="20"
                                        :error="$errors->has('form.class_desc')"
                                        class="w-full mt-1 uppercase"
                                        x-on:keydown.enter.prevent="$wire.save()" />
                                    <x-input-error :messages="$errors->get('form.class_desc')" class="mt-1" />
                                </div>
                            </div>
                        </div>
                    </x-border-form>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-surface-soft border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-muted dark:text-gray-400">
                        <kbd class="px-1.5 py-0.5 text-xs font-semibold bg-surface-card border border-hairline rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                        <span class="mx-0.5">untuk berpindah field,</span>
                        <kbd class="px-1.5 py-0.5 text-xs font-semibold bg-surface-card border border-hairline rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                        <span class="hidden sm:inline"> di field terakhir untuk simpan</span>
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
