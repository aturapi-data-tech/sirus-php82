<?php
// resources/views/pages/master/master-automatic-stop-order/master-automatic-stop-order-produk-actions.blade.php
//
// FORM pemetaan obat → golongan Automatic Stop Order (RSMST_STOP_ORDER_PRODUCTS). Obat dipilih dari master
// obat lewat LOV product (mode tambah); di mode ubah kode obatnya terkunci — yang
// boleh berubah hanya golongan, catatan, dan status aktif. Pola LOV meniru
// master-obat-kronis. Setelah simpan, cache AutomaticStopOrderMaster dibersihkan.

use App\Support\AutomaticStopOrder\AutomaticStopOrderMaster;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode   = 'create';
    public string $originalId = '';
    public array  $renderVersions = [];
    protected array $renderAreas  = ['modal'];

    public array $form = [];
    public array $golonganOptions = [];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
        $this->form = $this->formKosong();
    }

    private function formKosong(): array
    {
        return [
            'product_id'    => '',
            'product_name'  => '',
            'golongan_id'        => '',
            'catatan'       => '',
            'active_status' => '1',
        ];
    }

    private function muatGolongan(): void
    {
        $this->golonganOptions = DB::table('rsmst_stop_order_golongans')
            ->select('golongan_id', 'golongan_nama', 'batas_hari', 'active_status')
            ->orderBy('urutan')->orderBy('golongan_id')
            ->get()
            ->map(fn($golongan) => ['golongan_id' => (string) (int) $golongan->golongan_id, 'golongan_nama' => (string) $golongan->golongan_nama, 'batas_hari' => (int) $golongan->batas_hari, 'active_status' => (string) $golongan->active_status])
            ->all();
    }

    #[On('master.automatic-stop-order.openCreateProduk')]
    public function openCreate(int $golonganId = 0): void
    {
        $this->resetForm();
        $this->muatGolongan();
        $this->formMode   = 'create';
        $this->originalId = '';
        if ($golonganId > 0) {
            $this->form['golongan_id'] = (string) $golonganId;
        }

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-automatic-stop-order-produk-actions');
    }

    #[On('master.automatic-stop-order.openEditProduk')]
    public function openEdit(string $productId): void
    {
        $row = DB::table('rsmst_stop_order_products as pemetaan')
            ->leftJoin('immst_products as produk', 'produk.product_id', '=', 'pemetaan.product_id')
            ->select('pemetaan.product_id', 'pemetaan.golongan_id', 'pemetaan.catatan', 'pemetaan.active_status', 'produk.product_name')
            ->where('pemetaan.product_id', $productId)
            ->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Pemetaan obat tidak ditemukan.');
            return;
        }

        $this->resetForm();
        $this->muatGolongan();
        $this->formMode   = 'edit';
        $this->originalId = $productId;
        $this->form = [
            'product_id'    => (string) $row->product_id,
            'product_name'  => (string) ($row->product_name ?? ''),
            'golongan_id'        => (string) (int) $row->golongan_id,
            'catatan'       => (string) ($row->catatan ?? ''),
            'active_status' => (string) $row->active_status,
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-automatic-stop-order-produk-actions');
    }

    #[On('master.automatic-stop-order.toggleActiveProduk')]
    public function toggleActive(string $productId): void
    {
        $sekarang = (string) DB::table('rsmst_stop_order_products')->where('product_id', $productId)->value('active_status');
        if ($sekarang === '') {
            $this->dispatch('toast', type: 'error', message: 'Pemetaan obat tidak ditemukan.');
            return;
        }
        $berikutnya = $sekarang === '1' ? '0' : '1';
        DB::table('rsmst_stop_order_products')->where('product_id', $productId)->update(['active_status' => $berikutnya]);
        AutomaticStopOrderMaster::flush();

        $this->dispatch('toast', type: 'success', message: $berikutnya === '1' ? 'Obat dipantau Automatic Stop Order lagi.' : 'Obat tidak dipantau Automatic Stop Order.');
        $this->dispatch('master.automatic-stop-order.saved');
    }

    #[On('master.automatic-stop-order.requestDeleteProduk')]
    public function deleteProduk(string $productId): void
    {
        $deleted = DB::table('rsmst_stop_order_products')->where('product_id', $productId)->delete();
        if ($deleted === 0) {
            $this->dispatch('toast', type: 'error', message: 'Pemetaan obat tidak ditemukan.');
            return;
        }
        AutomaticStopOrderMaster::flush();

        $this->dispatch('toast', type: 'success', message: 'Pemetaan obat berhasil dihapus.');
        $this->dispatch('master.automatic-stop-order.saved');
    }

    /** Listener LOV product — payload obat terpilih (pola master-obat-kronis). */
    #[On('lov.selected.master-automatic-stop-order-produk')]
    public function onProductSelected(string $target, array $payload): void
    {
        $this->form['product_id']   = (string) ($payload['product_id'] ?? '');
        $this->form['product_name'] = (string) ($payload['product_name'] ?? '');
        $this->resetValidation('form.product_id');
    }

    public function save(): void
    {
        $this->validate($this->rules(), $this->messages(), $this->validationAttributes());

        if ($this->formMode === 'create') {
            if (!DB::table('immst_products')->where('product_id', $this->form['product_id'])->exists()) {
                $this->addError('form.product_id', 'Obat tidak ditemukan di master obat.');
                return;
            }
            if (DB::table('rsmst_stop_order_products')->where('product_id', $this->form['product_id'])->exists()) {
                $this->addError('form.product_id', 'Obat sudah dipetakan. Ubah lewat daftar.');
                return;
            }
        }

        if (!DB::table('rsmst_stop_order_golongans')->where('golongan_id', (int) $this->form['golongan_id'])->exists()) {
            $this->addError('form.golongan_id', 'Golongan tidak ditemukan.');
            return;
        }

        $payload = [
            'golongan_id'        => (int) $this->form['golongan_id'],
            'catatan'       => trim($this->form['catatan']) === '' ? null : trim($this->form['catatan']),
            'active_status' => $this->form['active_status'] === '1' ? '1' : '0',
        ];

        if ($this->formMode === 'create') {
            DB::table('rsmst_stop_order_products')->insert(['product_id' => $this->form['product_id'], ...$payload]);
        } else {
            DB::table('rsmst_stop_order_products')->where('product_id', $this->originalId)->update($payload);
        }

        AutomaticStopOrderMaster::flush();

        $this->dispatch('toast', type: 'success', message: 'Pemetaan obat berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.automatic-stop-order.saved');
    }

    protected function rules(): array
    {
        return [
            'form.product_id' => 'required|string|max:30',
            'form.golongan_id'     => 'required|integer|min:1',
            'form.catatan'    => 'nullable|string|max:300',
        ];
    }

    protected function messages(): array
    {
        return [
            'form.product_id.required' => 'Obat wajib dipilih dari daftar.',
            'form.golongan_id.required'     => 'Golongan wajib dipilih.',
            'form.golongan_id.integer'      => 'Golongan wajib dipilih.',
            'form.golongan_id.min'          => 'Golongan wajib dipilih.',
            'form.catatan.max'         => 'Catatan maksimal 300 karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.product_id' => 'Obat',
            'form.golongan_id'     => 'Golongan',
            'form.catatan'    => 'Catatan',
        ];
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-automatic-stop-order-produk-actions');
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
    <x-modal name="master-automatic-stop-order-produk-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-automatic-stop-order-produk-actions"
            event="master.automatic-stop-order.saved"
            label="Pemetaan Obat Automatic Stop Order"
            :wireKey="$this->renderKey('modal', [$formMode, $originalId])">

            <div class="px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="ds-display-sm dark:text-gray-100">
                            {{ $formMode === 'edit' ? 'Ubah Pemetaan Obat Automatic Stop Order' : 'Petakan Obat ke Golongan Automatic Stop Order' }}
                        </h2>
                        <p class="mt-1 text-sm text-muted dark:text-gray-400">
                            Pilih obat dari master obat, lalu tentukan golongan Automatic Stop Order-nya. Obat yang tidak dipetakan diabaikan Automatic Stop Order.
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

            <div class="flex-1 px-4 py-4 bg-surface-soft dark:bg-gray-950/20" x-enter-chain>
                <x-border-form title="Pemetaan Obat">
                    <div class="space-y-4">
                        {{-- Pilih obat: LOV (mode tambah) atau readonly (mode ubah) --}}
                        @if ($formMode === 'create')
                            <div>
                                <livewire:lov.product.lov-product
                                    target="master-automatic-stop-order-produk"
                                    label="Obat (cari dari master obat)"
                                    placeholder="Ketik nama/kode/kandungan obat..."
                                    wire:key="lov-master-automatic-stop-order-produk-{{ $renderVersions['modal'] ?? 0 }}" />
                                <x-input-error :messages="$errors->get('form.product_id')" class="mt-1" />
                                @if ($form['product_id'] !== '')
                                    <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                                        Terpilih: <span class="font-mono">{{ $form['product_id'] }}</span>
                                        — {{ $form['product_name'] }}
                                    </p>
                                @endif
                            </div>
                        @else
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <x-input-label value="Kode Obat" />
                                    <x-text-input wire:model="form.product_id" disabled class="w-full mt-1 font-mono" />
                                </div>
                                <div class="sm:col-span-2">
                                    <x-input-label value="Nama Obat" />
                                    <x-text-input wire:model="form.product_name" disabled class="w-full mt-1" />
                                </div>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div class="sm:col-span-2">
                                <x-input-label value="Golongan Automatic Stop Order" />
                                <x-select-input wire:model="form.golongan_id" class="w-full mt-1" :error="$errors->has('form.golongan_id')">
                                    <option value="">&mdash; pilih golongan &mdash;</option>
                                    @foreach ($golonganOptions as $golongan)
                                        <option value="{{ $golongan['golongan_id'] }}">{{ $golongan['golongan_nama'] }} ({{ $golongan['batas_hari'] }} hari){{ $golongan['active_status'] === '1' ? '' : ' - nonaktif' }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('form.golongan_id')" class="mt-1" />
                            </div>
                            <div class="flex items-end">
                                <x-toggle wire:model.live="form.active_status" trueValue="1" falseValue="0" label="Dipantau Automatic Stop Order" />
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Catatan (opsional)" />
                            <x-text-input wire:model="form.catatan" maxlength="300"
                                placeholder="IV maksimal 120 mg/hari" :error="$errors->has('form.catatan')" class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.catatan')" class="mt-1" />
                        </div>
                    </div>
                </x-border-form>
            </div>

            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-surface-soft border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-muted dark:text-gray-400">
                        Satu obat hanya masuk satu golongan.
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
