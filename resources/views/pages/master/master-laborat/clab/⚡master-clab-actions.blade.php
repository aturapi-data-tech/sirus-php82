<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode      = 'create';
    public array  $renderVersions = [];
    protected array $renderAreas = ['modal'];

    public array $formClab = [
        'clab_id'   => '',
        'clab_desc' => '',
        'app_seq'   => '',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    // =========================================================
    // CLAB
    // =========================================================

    #[On('master.laborat.openCreateClab')]
    public function openCreateClab(): void
    {
        $this->resetAll();
        $this->formMode = 'create';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-laborat-clab');
        $this->dispatch('focus-clab-id');
    }

    #[On('master.laborat.openEditClab')]
    public function openEditClab(string $clabId): void
    {
        $row = DB::table('lbmst_clabs')->where('clab_id', $clabId)->first();
        if (! $row) {
            return;
        }

        $this->resetAll();
        $this->formMode = 'edit';
        $this->formClab = [
            'clab_id'   => (string) $row->clab_id,
            'clab_desc' => (string) ($row->clab_desc ?? ''),
            'app_seq'   => (string) ($row->app_seq ?? ''),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-laborat-clab');
        $this->dispatch('focus-clab-desc');
    }

    #[On('master.laborat.deleteClab')]
    public function deleteClab(string $clabId): void
    {
        try {
            $hasItems = DB::table('lbmst_clabitems')->where('clab_id', $clabId)->exists();
            if ($hasItems) {
                $this->dispatch('toast', type: 'error', message: 'Kategori tidak bisa dihapus karena masih memiliki item pemeriksaan.');
                return;
            }

            $deleted = DB::table('lbmst_clabs')->where('clab_id', $clabId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data kategori lab tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Kategori lab berhasil dihapus.');
            $this->dispatch('master.laborat.saved', entity: 'clab');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Kategori tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $this->validate(
            [
                'formClab.clab_id'   => $this->formMode === 'create' ? 'required|string|max:5|unique:lbmst_clabs,clab_id' : 'required|string|max:5',
                'formClab.clab_desc' => 'required|string|max:50',
                'formClab.app_seq'   => 'nullable|integer|min:0',
            ],
            [],
            [
                'formClab.clab_id'   => 'ID Kategori',
                'formClab.clab_desc' => 'Nama Kategori',
                'formClab.app_seq'   => 'Urutan',
            ],
        );

        $payload = [
            'clab_desc' => $this->formClab['clab_desc'],
            'app_seq'   => $this->formClab['app_seq'] !== '' ? (int) $this->formClab['app_seq'] : null,
        ];

        if ($this->formMode === 'create') {
            DB::table('lbmst_clabs')->insert(['clab_id' => $this->formClab['clab_id'], ...$payload]);
        } else {
            DB::table('lbmst_clabs')->where('clab_id', $this->formClab['clab_id'])->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data kategori lab berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.laborat.saved', entity: 'clab');
    }

    public function closeModal(): void
    {
        $this->resetAll();
        $this->dispatch('close-modal', name: 'master-laborat-clab');
        $this->resetVersion();
    }

    private function resetAll(): void
    {
        $this->formClab = ['clab_id' => '', 'clab_desc' => '', 'app_seq' => ''];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-laborat-clab" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-laborat-clab"
            event="master.laborat.saved"
            label="Pemeriksaan Lab"
            :wireKey="$this->renderKey('modal', [$formMode])"
            wrapperClass="flex flex-col min-h-0">

            {{-- HEADER --}}
            <x-modul-dokumen.header
                :judul="$formMode === 'edit' ? 'Ubah Kategori Lab' : 'Tambah Kategori Lab'"
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
                <x-border-form title="Data Kategori Lab" class="max-w-xl"
                    x-data
                    x-on:focus-clab-id.window="$nextTick(() => setTimeout(() => $refs.inputClabId?.focus(), 150))"
                    x-on:focus-clab-desc.window="$nextTick(() => setTimeout(() => $refs.inputClabDesc?.focus(), 150))">
                    <div class="space-y-5">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label value="ID Kategori" />
                                <x-text-input wire:model.live="formClab.clab_id" x-ref="inputClabId"
                                    :disabled="$formMode === 'edit'" maxlength="5" :error="$errors->has('formClab.clab_id')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$refs.inputClabDesc?.focus()" />
                                <x-input-error :messages="$errors->get('formClab.clab_id')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Urutan (Seq)" />
                                <x-text-input-number wire:model="formClab.app_seq"
                                    :error="$errors->has('formClab.app_seq')" class="mt-1"
                                    x-on:keydown.enter.prevent="$el.blur(); $wire.save()" />
                                <x-input-error :messages="$errors->get('formClab.app_seq')" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Nama Kategori" />
                            <x-text-input wire:model.live="formClab.clab_desc" x-ref="inputClabDesc"
                                maxlength="50" :error="$errors->has('formClab.clab_desc')" class="w-full mt-1"
                                x-on:keydown.enter.prevent="$wire.save()" />
                            <x-input-error :messages="$errors->get('formClab.clab_desc')" class="mt-1" />
                        </div>
                    </div>
                </x-border-form>
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
