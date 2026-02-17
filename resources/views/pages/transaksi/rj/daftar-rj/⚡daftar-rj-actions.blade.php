<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

new class extends Component {
    public string $formMode = 'create'; // create|edit
    public ?string $rjNo = null;

    public ?string $keterangan = null;
    public ?string $tindakLanjut = null;
    public ?string $tglKontrol = null;

    /* ===============================
     | OPEN CREATE
     =============================== */
    #[On('daftar-rj.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode = 'create';
        $this->resetValidation();

        $this->dispatch('open-modal', name: 'rj-actions');
    }

    /* ===============================
     | OPEN EDIT
     =============================== */
    #[On('daftar-rj.openEdit')]
    public function openEdit(string $rjNo): void
    {
        $row = DB::table('rstxn_rjhdrs')->where('rj_no', $rjNo)->first();

        if (!$row) {
            return;
        }

        $this->resetForm();
        $this->formMode = 'edit';
        $this->rjNo = $row->rj_no;
        $this->keterangan = $row->keterangan ?? null;
        $this->tindakLanjut = $row->tindak_lanjut ?? null;
        $this->tglKontrol = $row->tgl_kontrol ?? null;

        $this->resetValidation();

        $this->dispatch('open-modal', name: 'rj-actions');
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'rj-actions');
    }

    protected function resetForm(): void
    {
        $this->reset(['rjNo', 'keterangan', 'tindakLanjut', 'tglKontrol']);
    }

    protected function rules(): array
    {
        return [
            'tindakLanjut' => ['nullable', 'string', 'max:255'],
            'tglKontrol' => ['nullable', 'date'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    /* ===============================
     | SAVE
     =============================== */
    public function save(): void
    {
        $data = $this->validate();

        if ($this->formMode === 'edit' && $this->rjNo) {
            DB::table('rstxn_rjhdrs')
                ->where('rj_no', $this->rjNo)
                ->update([
                    'tindak_lanjut' => $data['tindakLanjut'],
                    'tgl_kontrol' => $data['tglKontrol'],
                    'keterangan' => $data['keterangan'],
                ]);
        }

        $this->dispatch('toast', type: 'success', message: 'Data Rawat Jalan berhasil disimpan.');
        $this->closeModal();

        $this->dispatch('daftar-rj.saved');
    }

    /* ===============================
     | DELETE
     =============================== */
    #[On('daftar-rj.requestDelete')]
    public function deleteFromGrid(string $rjNo): void
    {
        try {
            $deleted = DB::table('rstxn_rjhdrs')->where('rj_no', $rjNo)->delete();

            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Data berhasil dihapus.');
            $this->dispatch('daftar-rj.saved');
        } catch (QueryException $e) {
            throw $e;
        }
    }
};
?>


<div>
    <x-modal name="rj-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="rj-actions-{{ $formMode }}-{{ $rjNo ?? 'new' }}">

            {{-- HEADER dengan background pattern --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

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
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Data Rawat Jalan' : 'Tambah Data Rawat Jalan' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Kelola data pendaftaran dan pelayanan pasien rawat jalan.
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>

                            @if ($rjNo)
                                <x-badge variant="brand">
                                    No RJ: {{ $rjNo }}
                                </x-badge>
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

            {{-- BODY dengan layout grid 2 kolom --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <div class="grid grid-cols-2 gap-4">

                    {{-- KOLOM KIRI: Data Pasien & Pendaftaran --}}
                    <div class="space-y-4">
                        {{-- Informasi Pasien (jika dalam mode edit) --}}
                        @if ($formMode === 'edit' && $pasienInfo)
                            <div
                                class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                                <div class="p-5">
                                    <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                        Informasi Pasien
                                    </h3>
                                    <div class="grid grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <span class="text-gray-500">No RM:</span>
                                            <span class="ml-2 font-medium text-gray-900 dark:text-white">
                                                {{ $pasienInfo['reg_no'] ?? '-' }}
                                            </span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Nama:</span>
                                            <span class="ml-2 font-medium text-gray-900 dark:text-white">
                                                {{ $pasienInfo['reg_name'] ?? '-' }}
                                            </span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Poli:</span>
                                            <span class="ml-2 font-medium text-gray-900 dark:text-white">
                                                {{ $pasienInfo['poli_desc'] ?? '-' }}
                                            </span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Dokter:</span>
                                            <span class="ml-2 font-medium text-gray-900 dark:text-white">
                                                {{ $pasienInfo['dr_name'] ?? '-' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- PLACEHOLDER: Form Pencarian Pasien --}}
                        <div
                            class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                            <div class="p-5">
                                {{-- Pasien --}}
                                <div>
                                    @if ($this->formMode === 'create')
                                        <livewire:lov.pasien.lov-pasien target="rjFormPasien" />
                                    @else
                                        <livewire:lov.pasien.lov-pasien target="rjFormPasien" :initialRegNo="$regNo" />
                                    @endif

                                    <x-input-error :messages="$errors->get('regNo')" class="mt-1" />
                                </div>
                            </div>
                        </div>

                        {{-- PLACEHOLDER: Data Pasien --}}
                        <div
                            class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                            <div class="p-5">
                                <div
                                    class="flex items-center justify-center h-48 border-2 border-gray-200 border-dashed rounded-xl dark:border-gray-700">
                                    <p class="text-sm text-gray-400 dark:text-gray-500">
                                        👤 Data Pasien (Coming Soon)
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- KOLOM KANAN: Data Transaksi & Layanan --}}
                    <div class="space-y-4">
                        {{-- PLACEHOLDER: Jenis Klaim & Kunjungan --}}
                        <div
                            class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                            <div class="p-5">
                                <div
                                    class="flex items-center justify-center h-24 border-2 border-gray-200 border-dashed rounded-xl dark:border-gray-700">
                                    <p class="text-sm text-gray-400 dark:text-gray-500">
                                        💰 Jenis Klaim & Kunjungan (Coming Soon)
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- PLACEHOLDER: No Referensi & SEP --}}
                        <div
                            class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                            <div class="p-5">
                                <div
                                    class="flex items-center justify-center h-24 border-2 border-gray-200 border-dashed rounded-xl dark:border-gray-700">
                                    <p class="text-sm text-gray-400 dark:text-gray-500">
                                        📋 No Referensi & SEP (Coming Soon)
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- PLACEHOLDER: Dokter & Poli --}}
                        <div
                            class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                            <div class="p-5">
                                <div
                                    class="flex items-center justify-center h-24 border-2 border-gray-200 border-dashed rounded-xl dark:border-gray-700">
                                    <p class="text-sm text-gray-400 dark:text-gray-500">
                                        👨‍⚕️ Dokter & Poli (Coming Soon)
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- PLACEHOLDER: Tindak Lanjut (yang sudah ada) --}}
                        <div
                            class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                            <div class="p-5 space-y-4">
                                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    Tindak Lanjut
                                </h3>

                                {{-- Form Fields yang sudah ada --}}
                                <div class="space-y-4">
                                    {{-- Tindak Lanjut --}}
                                    <div>
                                        <x-input-label value="Tindak Lanjut" />
                                        <x-select-input wire:model.defer="tindakLanjut" :error="$errors->has('tindakLanjut')"
                                            class="w-full mt-1">
                                            <option value="">Pilih Tindak Lanjut</option>
                                            <option value="Kontrol Ulang">Kontrol Ulang</option>
                                            <option value="Rujuk ke Spesialis">Rujuk ke Spesialis</option>
                                            <option value="Rawat Inap">Rawat Inap</option>
                                            <option value="Selesai">Selesai</option>
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('tindakLanjut')" class="mt-1" />
                                    </div>

                                    {{-- Tanggal Kontrol --}}
                                    <div>
                                        <x-input-label value="Tanggal Kontrol" />
                                        <x-text-input type="date" wire:model.defer="tglKontrol" :error="$errors->has('tglKontrol')"
                                            class="w-full mt-1" />
                                        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                            Tanggal kontrol berikutnya (jika ada).
                                        </p>
                                        <x-input-error :messages="$errors->get('tglKontrol')" class="mt-1" />
                                    </div>

                                    {{-- Keterangan --}}
                                    <div>
                                        <x-input-label value="Keterangan" />
                                        <textarea wire:model.defer="keterangan"
                                            class="w-full mt-1 border-gray-300 rounded-lg dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100"
                                            rows="4" :error="$errors->has('keterangan')"></textarea>
                                        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                            Catatan tambahan untuk tindak lanjut pasien.
                                        </p>
                                        <x-input-error :messages="$errors->get('keterangan')" class="mt-1" />
                                    </div>
                                </div>

                                {{-- Informasi Tambahan --}}
                                <div class="pt-4 mt-4 border-t border-gray-200 dark:border-gray-700">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        <p class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Data tindak lanjut akan tersimpan di JSON pasien.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- FOOTER dengan tombol aksi --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            Pastikan data sudah benar sebelum menyimpan.
                        </span>
                    </div>

                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">
                            Batal
                        </x-secondary-button>

                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove>Simpan</span>
                            <span wire:loading>
                                <svg class="inline w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                        stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                Menyimpan...
                            </span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
