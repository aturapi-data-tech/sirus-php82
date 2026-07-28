<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    /* Satu modal dipakai dua mode: entri baru & koreksi baris terpilih. */
    public ?string $editingNo = null;

    public array $formEntry = [
        'accId' => '',
        'accName' => '',
        'accIdKas' => '',
        'accNameKas' => '',
        'tucashkDate' => '',
        'tucashkDesc' => '',
        'tucashkNominal' => '',
    ];

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        $this->formEntry['tucashkDate'] = Carbon::now()->format('d/m/Y H:i:s');
    }

    /* ===============================
     | LOV
     =============================== */
    #[On('lov.selected.akun-ci-tu')]
    public function onAkunCiSelected(string $target, ?array $payload): void
    {
        $this->formEntry['accId'] = $payload['acc_id'] ?? '';
        $this->formEntry['accName'] = $payload['acc_name'] ?? '';
        $this->dispatch('focus-input-ket-ci');
    }

    #[On('lov.selected.akun-kas-tu')]
    public function onAkunKasSelected(string $target, ?array $payload): void
    {
        $this->formEntry['accIdKas'] = $payload['acc_id'] ?? '';
        $this->formEntry['accNameKas'] = $payload['acc_name'] ?? '';
        $this->dispatch('focus-btn-simpan-ci');
    }

    /* ===============================
     | VALIDASI
     =============================== */
    protected function aturan(): array
    {
        return [
            'formEntry.accId' => 'bail|required|string',
            'formEntry.accIdKas' => 'bail|required|string',
            'formEntry.tucashkDate' => 'bail|required|date_format:d/m/Y H:i:s',
            'formEntry.tucashkDesc' => 'bail|required|string|min:3|max:100',
            'formEntry.tucashkNominal' => 'bail|required|integer|min:1',
        ];
    }

    protected function pesanAturan(): array
    {
        return [
            'formEntry.accId.required' => 'Akun penerimaan wajib dipilih.',
            'formEntry.accIdKas.required' => 'Akun kas wajib dipilih.',
            'formEntry.tucashkDate.required' => 'Tanggal wajib diisi.',
            'formEntry.tucashkDate.date_format' => 'Format tanggal harus dd/mm/yyyy hh:mm:ss.',
            'formEntry.tucashkDesc.required' => 'Keterangan wajib diisi.',
            'formEntry.tucashkDesc.min' => 'Keterangan minimal 3 karakter.',
            'formEntry.tucashkDesc.max' => 'Keterangan maksimal 100 karakter.',
            'formEntry.tucashkNominal.required' => 'Nominal wajib diisi.',
            'formEntry.tucashkNominal.integer' => 'Nominal harus berupa angka.',
            'formEntry.tucashkNominal.min' => 'Nominal minimal Rp 1.',
        ];
    }

    /* ===============================
     | GUARD — akun kas & identitas petugas
     =============================== */
    protected function akunKasBelumTerdaftar(): bool
    {
        return DB::table('acmst_accounts as a')
            ->join('acmst_kases as b', 'a.acc_id', '=', 'b.acc_id')
            ->where('b.co', '1')
            ->whereIn('a.acc_id', function ($q) {
                $q->select('acc_id')->from('user_kas')->where('user_id', auth()->id());
            })
            ->count() === 0;
    }

    protected function shiftSekarang(): string
    {
        $shift = DB::table('rstxn_shiftctls')
            ->select('shift')
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->whereRaw('? BETWEEN shift_start AND shift_end', [Carbon::now()->format('H:i:s')])
            ->first();

        return (string) ($shift?->shift ?? 1);
    }

    /** Kembalikan emp_id petugas, atau null bila profil user belum lengkap. */
    protected function petugasSiap(): ?string
    {
        if ($this->akunKasBelumTerdaftar()) {
            $this->dispatch('toast', type: 'error', message: 'Akun kas Anda belum terkonfigurasi. Hubungi administrator.');
            return null;
        }

        $empId = auth()->user()->emp_id ?? null;
        if (!$empId) {
            $this->dispatch('toast', type: 'error', message: 'EMP ID belum diisi di profil user. Hubungi administrator.');
            return null;
        }

        return (string) $empId;
    }

    protected function bolehKoreksi(?string $status): bool
    {
        return $status !== 'L' || auth()->user()->hasAnyRole(['Admin', 'Tu']);
    }

    /* ===============================
     | BUKA / TUTUP MODAL
     =============================== */
    #[On('penerimaan-kas.openCreate')]
    public function openCreate(): void
    {
        $this->editingNo = null;
        $this->reset(['formEntry']);
        $this->formEntry['tucashkDate'] = Carbon::now()->format('d/m/Y H:i:s');
        $this->resetValidation();
        $this->incrementVersion('modal');

        $this->dispatch('open-modal', name: 'penerimaan-kas-tu-actions');
        $this->dispatch('focus-input-tanggal-ci');
    }

    public function closeModal(): void
    {
        $this->editingNo = null;
        $this->reset(['formEntry']);
        $this->formEntry['tucashkDate'] = Carbon::now()->format('d/m/Y H:i:s');
        $this->resetValidation();
        $this->resetVersion();

        $this->dispatch('close-modal', name: 'penerimaan-kas-tu-actions');
    }

    /* ===============================
     | SIMPAN — Enter di kolom Nominal
     =============================== */
    public function simpan(): void
    {
        $this->validate($this->aturan(), $this->pesanAturan());

        $empId = $this->petugasSiap();
        if (!$empId) {
            return;
        }

        if ($this->editingNo) {
            $this->simpanKoreksi($empId);
            return;
        }

        $shift = $this->shiftSekarang();
        $tanggal = $this->formEntry['tucashkDate'];

        try {
            DB::transaction(function () use ($empId, $shift, $tanggal) {
                $nextNo = DB::selectOne('SELECT tucashk_seq.NEXTVAL AS val FROM dual')->val;

                DB::table('rstxn_tucashds')->insert([
                    'tucashk_no' => $nextNo,
                    'tucashk_date' => DB::raw("to_date('{$tanggal}','dd/mm/yyyy hh24:mi:ss')"),
                    'tucashk_desc' => $this->formEntry['tucashkDesc'],
                    'tucashk_nominal' => (int) $this->formEntry['tucashkNominal'],
                    'acc_id' => $this->formEntry['accId'],
                    'acc_id_kas' => $this->formEntry['accIdKas'],
                    'emp_id' => $empId,
                    'shift' => $shift,
                    'tucashk_status' => 'L',
                ]);
            });

            $this->lanjutEntriBerikutnya();
            $this->dispatch('penerimaan-kas.saved');
            $this->dispatch('toast', type: 'success', message: 'Penerimaan kas berhasil disimpan.');
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    protected function simpanKoreksi(string $empId): void
    {
        $row = DB::table('rstxn_tucashds')->where('tucashk_no', $this->editingNo)->first();

        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan / sudah dihapus.');
            $this->closeModal();
            return;
        }

        if (!$this->bolehKoreksi($row->tucashk_status)) {
            $this->dispatch('toast', type: 'warning', message: 'Transaksi sudah diposting — hanya Admin/TU yang dapat mengoreksi.');
            $this->closeModal();
            return;
        }

        $shift = $this->shiftSekarang();
        $tanggal = $this->formEntry['tucashkDate'];

        try {
            DB::transaction(function () use ($empId, $shift, $tanggal) {
                DB::table('rstxn_tucashds')
                    ->where('tucashk_no', $this->editingNo)
                    ->update([
                        'tucashk_date' => DB::raw("to_date('{$tanggal}','dd/mm/yyyy hh24:mi:ss')"),
                        'tucashk_desc' => $this->formEntry['tucashkDesc'],
                        'tucashk_nominal' => (int) $this->formEntry['tucashkNominal'],
                        'acc_id' => $this->formEntry['accId'],
                        'acc_id_kas' => $this->formEntry['accIdKas'],
                        'emp_id' => $empId,
                        'shift' => $shift,
                        'tucashk_status' => 'L',
                    ]);
            });

            $this->closeModal();
            $this->dispatch('penerimaan-kas.saved');
            $this->dispatch('toast', type: 'success', message: 'Penerimaan kas berhasil diperbarui.');
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal memperbarui: ' . $e->getMessage());
        }
    }

    /* ===============================
     | KOREKSI — dipanggil dari daftar
     =============================== */
    #[On('penerimaan-kas.openEdit')]
    public function bukaKoreksi(string $tucashkNo): void
    {
        $row = DB::table('rstxn_tucashds')->where('tucashk_no', $tucashkNo)->first();

        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan.');
            return;
        }

        if (!$this->bolehKoreksi($row->tucashk_status)) {
            $this->dispatch('toast', type: 'warning', message: 'Transaksi sudah diposting — hanya Admin/TU yang dapat mengoreksi.');
            return;
        }

        $this->editingNo = (string) $row->tucashk_no;
        $this->formEntry = [
            'accId' => (string) ($row->acc_id ?? ''),
            'accName' => '',
            'accIdKas' => (string) ($row->acc_id_kas ?? ''),
            'accNameKas' => '',
            'tucashkDate' => $row->tucashk_date ? Carbon::parse($row->tucashk_date)->format('d/m/Y H:i:s') : '',
            'tucashkDesc' => (string) ($row->tucashk_desc ?? ''),
            'tucashkNominal' => (int) ($row->tucashk_nominal ?? 0),
        ];

        $this->resetValidation();
        $this->incrementVersion('modal');

        $this->dispatch('open-modal', name: 'penerimaan-kas-tu-actions');
        $this->dispatch('focus-input-ket-ci');
    }

    /** Akun tetap dipertahankan supaya entri berikutnya cepat; keterangan & nominal dikosongkan. */
    protected function lanjutEntriBerikutnya(): void
    {
        $this->formEntry['tucashkDate'] = Carbon::now()->format('d/m/Y H:i:s');
        $this->formEntry['tucashkDesc'] = '';
        $this->formEntry['tucashkNominal'] = '';
        $this->resetValidation();
        $this->dispatch('focus-input-ket-ci');
    }

    /** Kosongkan form tanpa menutup modal — kembali ke mode entri baru. */
    public function resetFormEntry(): void
    {
        $this->editingNo = null;
        $this->reset(['formEntry']);
        $this->formEntry['tucashkDate'] = Carbon::now()->format('d/m/Y H:i:s');
        $this->resetValidation();
        $this->incrementVersion('modal');
        $this->dispatch('focus-input-tanggal-ci');
    }

    public function setTanggalSekarang(): void
    {
        $this->formEntry['tucashkDate'] = Carbon::now()->format('d/m/Y H:i:s');
    }

    /* ===============================
     | HAPUS — dipanggil dari daftar
     =============================== */
    #[On('penerimaan-kas.requestDelete')]
    public function hapusDariDaftar(string $tucashkNo): void
    {
        if (!auth()->user()->hasAnyRole(['Admin', 'Tu'])) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Admin dan TU yang dapat membatalkan transaksi.');
            return;
        }

        try {
            $deleted = DB::table('rstxn_tucashds')->where('tucashk_no', $tucashkNo)->delete();

            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
                return;
            }

            if ($this->editingNo === $tucashkNo) {
                $this->closeModal();
            }

            $this->dispatch('penerimaan-kas.saved');
            $this->dispatch('toast', type: 'success', message: 'Transaksi berhasil dihapus.');
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }
};
?>

<div>
    <x-modal name="penerimaan-kas-tu-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$editingNo ?? 'new']) }}"
            x-data
            x-on:focus-input-tanggal-ci.window="$nextTick(() => setTimeout(() => $refs.inputTanggal?.focus(), 150))"
            x-on:focus-input-ket-ci.window="$nextTick(() => setTimeout(() => { $refs.inputKet?.focus(); $refs.inputKet?.select(); }, 150))"
            x-on:focus-btn-simpan-ci.window="$nextTick(() => setTimeout(() => $refs.btnSimpan?.focus(), 150))">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah" class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah" class="hidden w-6 h-6 dark:block" />
                            </div>

                            <div>
                                <h2 class="text-2xl font-semibold text-ink dark:text-gray-100">
                                    {{ $editingNo ? "Koreksi Penerimaan Kas #{$editingNo}" : 'Entri Penerimaan Kas' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    Catat penerimaan kas (Cash-In) di luar transaksi pelayanan RS.
                                </p>
                            </div>
                        </div>

                        <div class="mt-3">
                            <x-badge :variant="$editingNo ? 'warning' : 'success'">
                                {{ $editingNo ? 'Mode: Koreksi' : 'Mode: Entri' }}
                            </x-badge>
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeModal">
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
            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20">
                <x-border-form title="Data Penerimaan Kas (Cash-In)" class="max-w-3xl">
                    <div class="space-y-4">
                        {{-- 1 & 2. Tanggal + Akun Penerimaan — sejajar --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-12 items-start">
                            <div class="sm:col-span-5">
                                <x-input-label value="Tanggal" :required="true" />
                                <div class="flex items-center gap-2 mt-1">
                                    <x-text-input type="text" wire:model="formEntry.tucashkDate"
                                        placeholder="dd/mm/yyyy hh:mm:ss" class="w-full text-sm" x-ref="inputTanggal"
                                        :error="$errors->has('formEntry.tucashkDate')"
                                        x-on:keydown.enter.prevent="($refs.lovCiWrapper?.querySelector('input:not([disabled])') || $refs.inputKet)?.focus()" />
                                    <x-now-button wire:click.prevent="setTanggalSekarang" />
                                </div>
                                <x-input-error :messages="$errors->get('formEntry.tucashkDate')" class="mt-1" />
                            </div>

                            <div class="sm:col-span-7" x-ref="lovCiWrapper">
                                <livewire:lov.akun-ci.lov-akun-ci target="akun-ci-tu" label="Akun Penerimaan (CI)"
                                    :initialAccId="$formEntry['accId'] ?: null" :error="$errors->has('formEntry.accId')"
                                    wire:key="lov-ci-entry-{{ $editingNo ?? 'new' }}-{{ $renderVersions['modal'] ?? 0 }}" />
                                <x-input-error :messages="$errors->get('formEntry.accId')" class="mt-1" />
                            </div>
                        </div>

                        {{-- 3 & 4. Keterangan + Nominal — sejajar --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-12 items-start">
                            <div class="sm:col-span-8">
                                <x-input-label value="Keterangan" :required="true" />
                                <x-text-input type="text" wire:model="formEntry.tucashkDesc"
                                    placeholder="Keterangan penerimaan kas" class="w-full mt-1 text-sm"
                                    x-ref="inputKet" :error="$errors->has('formEntry.tucashkDesc')" x-on:keydown.enter.prevent="$refs.inputNominal?.focus()" />
                                <x-input-error :messages="$errors->get('formEntry.tucashkDesc')" class="mt-1" />
                            </div>

                            <div class="sm:col-span-4">
                                <x-input-label value="Nominal (Rp)" :required="true" />
                                <div class="mt-1">
                                    <x-text-input-number wire:model="formEntry.tucashkNominal" placeholder="0"
                                        class="text-sm" x-ref="inputNominal"
                                        :error="$errors->has('formEntry.tucashkNominal')"
                                        x-on:keydown.enter.prevent="$el.blur(); ($refs.lovKasWrapper?.querySelector('input:not([disabled])') || $refs.btnSimpan)?.focus()" />
                                </div>
                                <x-input-error :messages="$errors->get('formEntry.tucashkNominal')" class="mt-1" />
                            </div>
                        </div>

                        {{-- 5. Akun Kas --}}
                        <div x-ref="lovKasWrapper">
                            <livewire:lov.kas.lov-kas target="akun-kas-tu" tipe="" label="Akun Kas"
                                :initialAccId="$formEntry['accIdKas'] ?: null" :error="$errors->has('formEntry.accIdKas')"
                                wire:key="lov-kas-entry-{{ $editingNo ?? 'new' }}-{{ $renderVersions['modal'] ?? 0 }}" />
                            <x-input-error :messages="$errors->get('formEntry.accIdKas')" class="mt-1" />
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 mt-5">
                        <p class="text-xs text-muted dark:text-gray-400">
                            Urutan <span class="px-1.5 py-0.5 font-semibold rounded border border-hairline bg-canvas text-body dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">Enter</span>:
                            Tanggal → Akun Penerimaan → Keterangan → Nominal → Akun Kas → tombol Simpan.
                            Setelah tersimpan, modal tetap terbuka dan kedua akun dipertahankan supaya entri
                            berikutnya bisa langsung diketik.
                        </p>

                        <x-secondary-button type="button" wire:click.prevent="resetFormEntry"
                            class="px-3 py-1.5 text-xs" title="Kembalikan form ke kondisi awal (kosong)">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                            </svg>
                            Kosongkan Form
                        </x-secondary-button>
                    </div>
                </x-border-form>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-muted dark:text-gray-400">
                        Status transaksi otomatis <strong>Posted (L)</strong> saat disimpan.
                    </div>

                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">
                            {{ $editingNo ? 'Batal' : 'Tutup' }}
                        </x-secondary-button>

                        <x-primary-button type="button" wire:click="simpan" wire:loading.attr="disabled"
                            wire:target="simpan" x-ref="btnSimpan">
                            <span wire:loading.remove wire:target="simpan">
                                {{ $editingNo ? 'Simpan Koreksi' : 'Simpan & Posting' }}
                            </span>
                            <span wire:loading wire:target="simpan"><x-loading /> Menyimpan...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>
        </div>
    </x-modal>
</div>
