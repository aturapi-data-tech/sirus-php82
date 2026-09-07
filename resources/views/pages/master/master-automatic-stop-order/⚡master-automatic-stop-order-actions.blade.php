<?php
// resources/views/pages/master/master-automatic-stop-order/master-automatic-stop-order-actions.blade.php
//
// FORM golongan Automatic Stop Order (RSMST_STOP_ORDER_GOLONGANS): tambah / ubah / aktif-nonaktif / hapus.
// Hapus ditolak bila masih ada obat yang dipetakan ke golongan itu (FK) — pengguna
// harus memindahkan / menghapus pemetaannya dulu, supaya tidak ada obat yang
// diam-diam kehilangan pantauan Automatic Stop Order. Setelah simpan, cache AutomaticStopOrderMaster dibersihkan.

use App\Support\AutomaticStopOrder\AutomaticStopOrderMaster;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode   = 'create';
    public int    $originalId = 0;
    public array  $renderVersions = [];
    protected array $renderAreas  = ['modal'];

    public array $form = [];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
        $this->form = $this->formKosong();
    }

    private function formKosong(): array
    {
        return [
            'golongan_kode'      => '',
            'golongan_nama'      => '',
            'batas_hari'    => '',
            'batas_minimal_hari' => '',
            'keterangan'    => '',
            'urutan'        => '',
            'active_status' => '1',
        ];
    }

    #[On('master.automatic-stop-order.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = 0;
        $this->form['urutan'] = (string) ((int) DB::table('rsmst_stop_order_golongans')->max('urutan') + 1);

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-automatic-stop-order-actions');
        $this->dispatch('focus-automatic-stop-order-golongan-kode');
    }

    #[On('master.automatic-stop-order.openEdit')]
    public function openEdit(int $golonganId): void
    {
        $row = DB::table('rsmst_stop_order_golongans')->where('golongan_id', $golonganId)->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Golongan tidak ditemukan.');
            return;
        }

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $golonganId;
        $this->form = [
            'golongan_kode'      => (string) $row->golongan_kode,
            'golongan_nama'      => (string) $row->golongan_nama,
            'batas_hari'    => (string) $row->batas_hari,
            'batas_minimal_hari' => $row->batas_minimal_hari === null ? '' : (string) $row->batas_minimal_hari,
            'keterangan'    => (string) ($row->keterangan ?? ''),
            'urutan'        => (string) $row->urutan,
            'active_status' => (string) $row->active_status,
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-automatic-stop-order-actions');
        $this->dispatch('focus-automatic-stop-order-golongan-nama');
    }

    #[On('master.automatic-stop-order.toggleActive')]
    public function toggleActive(int $golonganId): void
    {
        $sekarang = (string) DB::table('rsmst_stop_order_golongans')->where('golongan_id', $golonganId)->value('active_status');
        if ($sekarang === '') {
            $this->dispatch('toast', type: 'error', message: 'Golongan tidak ditemukan.');
            return;
        }
        $berikutnya = $sekarang === '1' ? '0' : '1';
        DB::table('rsmst_stop_order_golongans')->where('golongan_id', $golonganId)->update(['active_status' => $berikutnya]);
        AutomaticStopOrderMaster::flush();

        $this->dispatch('toast', type: 'success', message: $berikutnya === '1' ? 'Golongan diaktifkan.' : 'Golongan dinonaktifkan — obat di dalamnya tidak dipantau Automatic Stop Order.');
        $this->dispatch('master.automatic-stop-order.saved');
    }

    #[On('master.automatic-stop-order.requestDelete')]
    public function deleteGolongan(int $golonganId): void
    {
        $jumlahObat = DB::table('rsmst_stop_order_products')->where('golongan_id', $golonganId)->count();
        if ($jumlahObat > 0) {
            $this->dispatch('toast', type: 'error', message: "Golongan masih dipakai {$jumlahObat} obat. Pindahkan / hapus pemetaannya dulu.");
            return;
        }

        try {
            $deleted = DB::table('rsmst_stop_order_golongans')->where('golongan_id', $golonganId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Golongan tidak ditemukan.');
                return;
            }
            AutomaticStopOrderMaster::flush();

            $this->dispatch('toast', type: 'success', message: 'Golongan Automatic Stop Order berhasil dihapus.');
            $this->dispatch('master.automatic-stop-order.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Golongan tidak bisa dihapus karena masih dipakai.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $this->validate($this->rules(), $this->messages(), $this->validationAttributes());

        $kode = mb_strtoupper(trim($this->form['golongan_kode']));
        $kembar = DB::table('rsmst_stop_order_golongans')
            ->whereRaw('UPPER(golongan_kode) = ?', [$kode])
            ->when($this->formMode === 'edit', fn($q) => $q->where('golongan_id', '<>', $this->originalId))
            ->exists();
        if ($kembar) {
            $this->addError('form.golongan_kode', 'Kode golongan ini sudah dipakai.');
            return;
        }

        $batasMinimal = trim((string) $this->form['batas_minimal_hari']) === '' ? null : (int) $this->form['batas_minimal_hari'];
        if ($batasMinimal !== null && $batasMinimal > (int) $this->form['batas_hari']) {
            $this->addError('form.batas_minimal_hari', 'Batas minimal tidak boleh melebihi batas stop.');
            return;
        }

        $payload = [
            'golongan_kode'      => $kode,
            'golongan_nama'      => trim($this->form['golongan_nama']),
            'batas_hari'    => (int) $this->form['batas_hari'],
            'batas_minimal_hari' => $batasMinimal,
            'keterangan'    => trim($this->form['keterangan']) === '' ? null : trim($this->form['keterangan']),
            'urutan'        => (int) $this->form['urutan'],
            'active_status' => $this->form['active_status'] === '1' ? '1' : '0',
        ];

        DB::transaction(function () use ($payload) {
            if ($this->formMode === 'create') {
                DB::table('rsmst_stop_order_golongans')->insert(['golongan_id' => AutomaticStopOrderMaster::idBaru('rsmst_stop_order_golongans', 'golongan_id'), ...$payload]);
            } else {
                DB::table('rsmst_stop_order_golongans')->where('golongan_id', $this->originalId)->update($payload);
            }
        });

        AutomaticStopOrderMaster::flush();

        $this->dispatch('toast', type: 'success', message: 'Golongan Automatic Stop Order berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.automatic-stop-order.saved');
    }

    protected function rules(): array
    {
        return [
            'form.golongan_kode'   => 'required|string|max:30|regex:/^[A-Za-z][A-Za-z0-9_]*$/',
            'form.golongan_nama'   => 'required|string|max:150',
            'form.batas_hari' => 'required|integer|min:1|max:365',
            'form.batas_minimal_hari' => 'nullable|integer|min:1|max:365',
            'form.keterangan' => 'nullable|string|max:600',
            'form.urutan'     => 'required|integer|min:0|max:999',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'integer'  => ':attribute harus bilangan bulat.',
            'max'      => ':attribute terlalu panjang / besar.',
            'min'      => ':attribute terlalu kecil.',
            'form.golongan_kode.regex' => 'Kode huruf/angka/garis bawah tanpa spasi (mis. KETOROLAK).',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.golongan_kode'   => 'Kode golongan',
            'form.golongan_nama'   => 'Nama golongan',
            'form.batas_hari' => 'Batas stop (hari)',
            'form.batas_minimal_hari' => 'Batas minimal (hari)',
            'form.keterangan' => 'Keterangan',
            'form.urutan'     => 'Urutan',
        ];
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-automatic-stop-order-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = $this->formKosong();
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-automatic-stop-order-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-automatic-stop-order-actions"
            event="master.automatic-stop-order.saved"
            label="Golongan Automatic Stop Order"
            :wireKey="$this->renderKey('modal', [$formMode, $originalId])">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 bg-surface-soft">
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <h2 class="ds-display-sm dark:text-gray-100">
                            {{ $formMode === 'edit' ? 'Ubah Golongan Automatic Stop Order' : 'Tambah Golongan Automatic Stop Order' }}
                        </h2>
                        <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                            Golongan obat beserta batas hari order berhenti otomatis.
                        </p>
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
            <div class="flex-1 px-4 py-4 space-y-4 bg-surface-soft dark:bg-gray-950/20" x-enter-chain
                 x-data
                 x-on:focus-automatic-stop-order-golongan-kode.window="$nextTick(() => setTimeout(() => $refs.inputGolKode?.focus(), 150))"
                 x-on:focus-automatic-stop-order-golongan-nama.window="$nextTick(() => setTimeout(() => $refs.inputGolNama?.focus(), 150))">

                <x-border-form title="Golongan">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
                        <div class="sm:col-span-2">
                            <x-input-label value="Kode" />
                            <x-text-input wire:model="form.golongan_kode" x-ref="inputGolKode" maxlength="30"
                                placeholder="KETOROLAK" :error="$errors->has('form.golongan_kode')" class="w-full mt-1 font-mono uppercase" />
                            <x-input-error :messages="$errors->get('form.golongan_kode')" class="mt-1" />
                        </div>
                        <div class="sm:col-span-3">
                            <x-input-label value="Nama golongan" />
                            <x-text-input wire:model="form.golongan_nama" x-ref="inputGolNama" maxlength="150"
                                placeholder="Ketorolak (oral dan parenteral)" :error="$errors->has('form.golongan_nama')" class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.golongan_nama')" class="mt-1" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label value="Batas stop (hari)" />
                            <x-text-input wire:model="form.batas_hari" type="number" min="1" max="365"
                                :error="$errors->has('form.batas_hari')" class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.batas_hari')" class="mt-1" />
                            <p class="mt-1 text-xs text-muted-soft">Order berhenti otomatis setelah hari ke-N.</p>
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label value="Batas minimal (hari)" />
                            <x-text-input wire:model="form.batas_minimal_hari" type="number" min="1" max="365" placeholder="kosong = tidak ada"
                                :error="$errors->has('form.batas_minimal_hari')" class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.batas_minimal_hari')" class="mt-1" />
                            <p class="mt-1 text-xs text-muted-soft">Lama pemberian minimal sebelum boleh dikaji / dihentikan.</p>
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label value="Urutan" />
                            <x-text-input wire:model="form.urutan" type="number" min="0" max="999"
                                :error="$errors->has('form.urutan')" class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.urutan')" class="mt-1" />
                        </div>
                        <div class="flex items-end sm:col-span-1">
                            <x-toggle wire:model.live="form.active_status" trueValue="1" falseValue="0" label="Aktif" />
                        </div>
                        <div class="sm:col-span-12">
                            <x-input-label value="Keterangan" />
                            <x-textarea wire:model="form.keterangan" :rows="3" maxlength="600"
                                placeholder="Syarat lanjut, dosis maksimal, catatan kebijakan..."
                                :error="$errors->has('form.keterangan')" class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.keterangan')" class="mt-1" />
                            <p class="mt-1 text-xs text-muted-soft">Huruf biasa saja (<span class="font-mono">&lt;= 120 mg</span>); simbol matematika tidak bisa disimpan Oracle.</p>
                        </div>
                    </div>
                </x-border-form>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-surface-soft border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-muted dark:text-gray-400">
                        Setelah simpan, cache master dibersihkan.
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
