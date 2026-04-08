<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\BPJS\AplicaresTrait;
use App\Http\Traits\SIRS\SirsTrait;

new class extends Component {
    use WithRenderVersioningTrait, AplicaresTrait, SirsTrait;

    public string $formMode   = 'create';
    public int    $originalId = 0;
    public array  $renderVersions = [];
    protected array $renderAreas  = ['modal'];

    public array $form = [
        'class_id'        => '',
        'class_desc'      => '',
        'aplic_kodekelas' => '',
        'sirs_id_tt'      => '',
        'id_t_tt'         => '',
    ];

    // ─── State lookup API ──────────────────────────────────────
    public array  $aplicList      = [];   // hasil GET referensiKamar
    public array  $sirsList       = [];   // hasil GET sirsRefTempaTidur
    public bool   $loadingAplic   = false;
    public bool   $loadingSirs    = false;
    public string $aplicError     = '';
    public string $sirsError      = '';

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    // ─── Fetch Aplicares ref/kelas ────────────────────────────
    public function fetchAplic(): void
    {
        $this->aplicList  = [];
        $this->aplicError = '';
        $this->loadingAplic = true;

        try {
            $res  = $this->referensiKamar()->getOriginalContent();
            $list = $res['list'] ?? $res['data'] ?? $res ?? [];

            if (is_array($list) && !empty($list)) {
                $this->aplicList = array_values($list);
            } else {
                $this->aplicError = 'Respons kosong dari Aplicares.';
            }
        } catch (\Throwable $e) {
            $this->aplicError = $e->getMessage();
        }

        $this->loadingAplic = false;
    }

    public function pilihAplic(string $kode): void
    {
        $this->form['aplic_kodekelas'] = $kode;
        $this->aplicList = [];
    }

    // ─── Fetch SIRS referensi tempat tidur ────────────────────
    public function fetchSirs(): void
    {
        $this->sirsList  = [];
        $this->sirsError = '';
        $this->loadingSirs = true;

        try {
            $res  = $this->sirsRefTempaTidur()->getOriginalContent();
            $list = $res['data'] ?? $res ?? [];

            if (is_array($list) && !empty($list)) {
                $this->sirsList = array_values($list);
            } else {
                $this->sirsError = 'Respons kosong dari SIRS.';
            }
        } catch (\Throwable $e) {
            $this->sirsError = $e->getMessage();
        }

        $this->loadingSirs = false;
    }

    public function pilihSirs(string $idTt): void
    {
        $this->form['sirs_id_tt'] = $idTt;
        $this->sirsList = [];
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
            'class_id'        => (string) $row->class_id,
            'class_desc'      => (string) ($row->class_desc ?? ''),
            'aplic_kodekelas' => (string) ($row->aplic_kodekelas ?? ''),
            'sirs_id_tt'      => (string) ($row->sirs_id_tt ?? ''),
            'id_t_tt'         => (string) ($row->id_t_tt ?? ''),
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
            'form.class_id'        => $this->formMode === 'create'
                ? 'required|integer|unique:rsmst_class,class_id'
                : 'required|integer',
            'form.class_desc'      => 'required|string|max:20',
            'form.aplic_kodekelas' => 'nullable|string|max:5',
            'form.sirs_id_tt'      => 'nullable|string|max:5',
            'form.id_t_tt'         => 'nullable|string|max:20',
        ], [], [
            'form.class_id'        => 'ID Kelas',
            'form.class_desc'      => 'Nama Kelas',
            'form.aplic_kodekelas' => 'Kode Aplicares',
            'form.sirs_id_tt'      => 'SIRS id_tt',
            'form.id_t_tt'         => 'SIRS id_t_tt',
        ]);

        $payload = [
            'class_desc'      => $this->form['class_desc'],
            'aplic_kodekelas' => $this->form['aplic_kodekelas'] ?: null,
            'sirs_id_tt'      => $this->form['sirs_id_tt'] ?: null,
            'id_t_tt'         => $this->form['id_t_tt'] ?: null,
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
        $this->form         = ['class_id'=>'','class_desc'=>'','aplic_kodekelas'=>'','sirs_id_tt'=>'','id_t_tt'=>''];
        $this->aplicList    = [];
        $this->sirsList     = [];
        $this->aplicError   = '';
        $this->sirsError    = '';
        $this->loadingAplic = false;
        $this->loadingSirs  = false;
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-class-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
             wire:key="{{ $this->renderKey('modal', [$formMode, $originalId]) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                     style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;"></div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="Logo" class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="Logo" class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Data Kelas Rawat' : 'Tambah Data Kelas Rawat' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Lengkapi informasi kelas & mapping sistem eksternal.
                                </p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>
                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-xl"
                     x-data
                     x-on:focus-class-id.window="$nextTick(() => setTimeout(() => $refs.inputClassId?.focus(), 150))"
                     x-on:focus-class-desc.window="$nextTick(() => setTimeout(() => $refs.inputClassDesc?.focus(), 150))">

                    <div class="p-5 space-y-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

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
                                    x-on:keydown.enter.prevent="$refs.inputAplic?.focus()" />
                                <x-input-error :messages="$errors->get('form.class_desc')" class="mt-1" />
                            </div>
                        </div>

                        {{-- Separator --}}
                        <div class="border-t border-gray-100 dark:border-gray-700 pt-4">
                            <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-3">
                                Mapping Sistem Eksternal
                            </p>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                                {{-- Aplicares --}}
                                <div>
                                    <x-input-label value="Kode Aplicares BPJS" />
                                    <div class="flex gap-2 mt-1">
                                        <x-text-input wire:model.live="form.aplic_kodekelas" x-ref="inputAplic"
                                            maxlength="5"
                                            :error="$errors->has('form.aplic_kodekelas')"
                                            class="w-full uppercase"
                                            placeholder="KL1, KL2, VIP …" />
                                        <button type="button" wire:click="fetchAplic"
                                                wire:loading.attr="disabled" wire:target="fetchAplic"
                                                class="shrink-0 inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-blue-300 dark:border-blue-600
                                                       text-blue-600 dark:text-blue-400 text-xs font-medium hover:bg-blue-50 dark:hover:bg-blue-900/20 transition
                                                       disabled:opacity-50">
                                            <svg wire:loading wire:target="fetchAplic" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                            </svg>
                                            <svg wire:loading.remove wire:target="fetchAplic" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                            </svg>
                                            Cek API
                                        </button>
                                    </div>

                                    {{-- Error --}}
                                    @if ($aplicError)
                                        <p class="mt-1 text-xs text-red-500">{{ $aplicError }}</p>
                                    @endif

                                    {{-- Dropdown hasil --}}
                                    @if (!empty($aplicList))
                                        <div class="mt-2 rounded-lg border border-blue-200 dark:border-blue-700 bg-white dark:bg-gray-800 shadow-sm divide-y divide-gray-100 dark:divide-gray-700 max-h-40 overflow-y-auto">
                                            @foreach ($aplicList as $item)
                                                <button type="button"
                                                        wire:click="pilihAplic('{{ $item['kodeKelas'] ?? $item['kode'] ?? $item['kelas'] ?? '' }}')"
                                                        class="w-full flex items-center justify-between px-3 py-2 text-xs text-left hover:bg-blue-50 dark:hover:bg-blue-900/20 transition">
                                                    <span class="font-mono font-bold text-blue-700 dark:text-blue-300">
                                                        {{ $item['kodeKelas'] ?? $item['kode'] ?? $item['kelas'] ?? '-' }}
                                                    </span>
                                                    <span class="text-gray-500 dark:text-gray-400">
                                                        {{ $item['namaKelas'] ?? $item['nama'] ?? '' }}
                                                    </span>
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif

                                    <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                                        Kode kelas yang dikirim ke Aplicares saat update TT.
                                    </p>
                                    <x-input-error :messages="$errors->get('form.aplic_kodekelas')" class="mt-1" />
                                </div>

                                {{-- SIRS id_tt referensi --}}
                                <div>
                                    <x-input-label value="SIRS id_tt (referensi)" />
                                    <div class="flex gap-2 mt-1">
                                        <x-text-input wire:model.live="form.sirs_id_tt" x-ref="inputSirsIdTt"
                                            maxlength="5"
                                            :error="$errors->has('form.sirs_id_tt')"
                                            class="w-full"
                                            placeholder="1, 2, 3 …" />
                                        <button type="button" wire:click="fetchSirs"
                                                wire:loading.attr="disabled" wire:target="fetchSirs"
                                                class="shrink-0 inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-green-300 dark:border-green-600
                                                       text-green-600 dark:text-green-400 text-xs font-medium hover:bg-green-50 dark:hover:bg-green-900/20 transition
                                                       disabled:opacity-50">
                                            <svg wire:loading wire:target="fetchSirs" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                            </svg>
                                            <svg wire:loading.remove wire:target="fetchSirs" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                            </svg>
                                            Cek API
                                        </button>
                                    </div>

                                    {{-- Error --}}
                                    @if ($sirsError)
                                        <p class="mt-1 text-xs text-red-500">{{ $sirsError }}</p>
                                    @endif

                                    {{-- Dropdown hasil --}}
                                    @if (!empty($sirsList))
                                        <div class="mt-2 rounded-lg border border-green-200 dark:border-green-700 bg-white dark:bg-gray-800 shadow-sm divide-y divide-gray-100 dark:divide-gray-700 max-h-40 overflow-y-auto">
                                            @foreach ($sirsList as $item)
                                                <button type="button"
                                                        wire:click="pilihSirs('{{ $item['id_tt'] ?? $item['kode'] ?? '' }}')"
                                                        class="w-full flex items-center justify-between px-3 py-2 text-xs text-left hover:bg-green-50 dark:hover:bg-green-900/20 transition">
                                                    <span class="font-mono font-bold text-green-700 dark:text-green-300">
                                                        {{ $item['id_tt'] ?? $item['kode'] ?? '-' }}
                                                    </span>
                                                    <span class="text-gray-500 dark:text-gray-400">
                                                        {{ $item['nama'] ?? $item['keterangan'] ?? $item['nm_tt'] ?? '' }}
                                                    </span>
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif

                                    <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                                        Dari referensi <code class="font-mono">GET /Referensi/tempat_tidur</code>.
                                    </p>
                                    <x-input-error :messages="$errors->get('form.sirs_id_tt')" class="mt-1" />
                                </div>
                            </div>

                            {{-- SIRS id_t_tt (record transaksi) --}}
                            <div class="mt-4">
                                <x-input-label value="SIRS id_t_tt (record transaksi)" />
                                <x-text-input wire:model.live="form.id_t_tt" x-ref="inputIdTTt"
                                    maxlength="20"
                                    :error="$errors->has('form.id_t_tt')"
                                    class="w-full mt-1 font-mono"
                                    placeholder="Diisi otomatis saat sync TT …"
                                    x-on:keydown.enter.prevent="$wire.save()" />
                                <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                                    Record ID dari <code class="font-mono">GET /Fasyankes</code> — diisi otomatis saat Ambil Data Existing SIRS.
                                </p>
                                <x-input-error :messages="$errors->get('form.id_t_tt')" class="mt-1" />
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        <kbd class="px-1.5 py-0.5 text-xs font-semibold bg-gray-100 border border-gray-300 rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                        <span class="mx-0.5">untuk berpindah field,</span>
                        <kbd class="px-1.5 py-0.5 text-xs font-semibold bg-gray-100 border border-gray-300 rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                        <span class="hidden sm:inline"> di field terakhir untuk simpan</span>
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>
                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove>Simpan</span>
                            <span wire:loading>Saving...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
