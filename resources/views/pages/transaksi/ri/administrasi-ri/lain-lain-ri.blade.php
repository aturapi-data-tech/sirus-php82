<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-lain-lain-ri'];

    public bool $isFormLocked = false;
    public ?int $riHdrNo = null;
    public array $dataDaftarRI = [];

    public array $formEntry = [
        'lainId'    => '',
        'lainDesc'  => '',
        'lainPrice' => '',
    ];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);

        if ($this->riHdrNo) {
            $this->findData($this->riHdrNo);
        } else {
            $this->dataDaftarRI['RiLainLain'] = [];
        }
    }

    private function findData(int $riHdrNo): void
    {
        $rows = DB::table('rstxn_riothers')
            ->join('rsmst_others', 'rstxn_riothers.other_id', '=', 'rsmst_others.other_id')
            ->select(
                DB::raw("to_char(other_date, 'dd/mm/yyyy hh24:mi:ss') as other_date"),
                'rstxn_riothers.other_id',
                'rsmst_others.other_desc',
                'rstxn_riothers.other_price',
                'rstxn_riothers.other_no',
            )
            ->where('rstxn_riothers.rihdr_no', $riHdrNo)
            ->orderBy('other_date')
            ->get();

        $this->dataDaftarRI['RiLainLain'] = $rows->map(fn($r) => (array) $r)->toArray();
    }

    /* ===============================
     | LOV SELECTED — LAIN-LAIN
     =============================== */
    #[On('lov.selected.lain-lain-ri')]
    public function onLainLainSelected(?array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        if (!$payload) {
            $this->formEntry['lainId']    = '';
            $this->formEntry['lainDesc']  = '';
            $this->formEntry['lainPrice'] = '';
            return;
        }

        $this->formEntry['lainId']    = $payload['other_id'];
        $this->formEntry['lainDesc']  = $payload['other_desc'];
        $this->formEntry['lainPrice'] = $payload['other_price'];

        $this->dispatch('focus-input-lain-price');
    }

    /* ===============================
     | INSERT
     =============================== */
    public function insertLainLain(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        $this->validate(
            [
                'formEntry.lainId'    => 'bail|required|exists:rsmst_others,other_id',
                'formEntry.lainPrice' => 'bail|required|numeric|min:0',
            ],
            [
                'formEntry.lainId.required'    => 'Item lain-lain wajib dipilih.',
                'formEntry.lainId.exists'      => 'Item tidak valid.',
                'formEntry.lainPrice.required' => 'Tarif wajib diisi.',
                'formEntry.lainPrice.numeric'  => 'Tarif harus berupa angka.',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $last = DB::table('rstxn_riothers')
                    ->select(DB::raw("nvl(max(other_no)+1,1) as other_no_max"))
                    ->first();

                DB::table('rstxn_riothers')->insert([
                    'other_no'    => $last->other_no_max,
                    'rihdr_no'    => $this->riHdrNo,
                    'other_id'    => $this->formEntry['lainId'],
                    'other_date'  => DB::raw("sysdate"),
                    'other_price' => $this->formEntry['lainPrice'],
                ]);
                $this->appendAdminLog($this->riHdrNo, 'Tambah Lain-Lain: ' . $this->formEntry['lainDesc']);
            });

            $this->resetFormEntry();
            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('toast', type: 'success', message: 'Item berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE
     =============================== */
    public function removeLainLain(int $otherNo): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        try {
            DB::transaction(function () use ($otherNo) {
                $this->lockRIRow($this->riHdrNo);
                DB::table('rstxn_riothers')->where('other_no', $otherNo)->delete();
                $this->appendAdminLog($this->riHdrNo, 'Hapus Lain-Lain #' . $otherNo);
            });

            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('toast', type: 'success', message: 'Item berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function resetFormEntry(): void
    {
        $this->reset(['formEntry']);
        $this->resetValidation();
        $this->incrementVersion('modal-lain-lain-ri');
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-lain-lain-ri', [$riHdrNo ?? 'new']) }}">

    @if ($isFormLocked)
        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Pasien sudah pulang — transaksi terkunci.
        </div>
    @endif

    @if (!$isFormLocked)
        <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40"
            x-data
            x-on:focus-input-lain-price.window="$nextTick(() => $refs.inputLainPrice?.focus())">

            @if (empty($formEntry['lainId']))
                <div class="w-80">
                    <livewire:lov.lain-lain.lov-lain-lain target="lain-lain-ri" label="Lain-Lain"
                        placeholder="Ketik kode/nama..."
                        wire:key="lov-lain-{{ $riHdrNo }}-{{ $renderVersions['modal-lain-lain-ri'] ?? 0 }}" />
                </div>
            @else
                <div class="flex items-end gap-3">
                    <div class="w-28">
                        <x-input-label value="Kode" class="mb-1" />
                        <x-text-input wire:model="formEntry.lainId" disabled class="w-full text-sm" />
                    </div>
                    <div class="flex-1">
                        <x-input-label value="Keterangan" class="mb-1" />
                        <x-text-input wire:model="formEntry.lainDesc" disabled class="w-full text-sm" />
                    </div>
                    <div class="w-36">
                        <x-input-label value="Tarif" class="mb-1" />
                        <x-text-input wire:model="formEntry.lainPrice" placeholder="Tarif" class="w-full text-sm"
                            x-ref="inputLainPrice"
                            x-on:keyup.enter="$wire.insertLainLain()" />
                        @error('formEntry.lainPrice')
                            <x-input-error :messages="$message" class="mt-1" />
                        @enderror
                    </div>
                    <div class="flex gap-2 pb-0.5">
                        <button type="button" wire:click.prevent="insertLainLain" wire:loading.attr="disabled"
                            wire:target="insertLainLain"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold text-white bg-brand-green hover:bg-brand-green/90 disabled:opacity-60 dark:bg-brand-lime dark:text-gray-900 transition shadow-sm">
                            <span wire:loading.remove wire:target="insertLainLain">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </span>
                            <span wire:loading wire:target="insertLainLain"><x-loading class="w-4 h-4" /></span>
                            Tambah
                        </button>
                        <button type="button" wire:click.prevent="resetFormEntry"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Batal
                        </button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Daftar Lain-Lain</h3>
            <x-badge variant="gray">{{ count($dataDaftarRI['RiLainLain'] ?? []) }} item</x-badge>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Keterangan</th>
                        <th class="px-4 py-3 text-right">Tarif</th>
                        @if (!$isFormLocked) <th class="w-20 px-4 py-3 text-center">Hapus</th> @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($dataDaftarRI['RiLainLain'] ?? [] as $item)
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">{{ $item['other_date'] ?? '-' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ $item['other_id'] }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-200 whitespace-nowrap">{{ $item['other_desc'] }}</td>
                            <td class="px-4 py-3 font-semibold text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                Rp {{ number_format($item['other_price'] ?? 0) }}
                            </td>
                            @if (!$isFormLocked)
                                <td class="px-4 py-3 text-center">
                                    <button type="button"
                                        wire:click.prevent="removeLainLain({{ $item['other_no'] }})"
                                        wire:confirm="Hapus item ini?" wire:loading.attr="disabled"
                                        wire:target="removeLainLain({{ $item['other_no'] }})"
                                        class="inline-flex items-center justify-center w-8 h-8 text-red-500 transition rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isFormLocked ? 4 : 5 }}"
                                class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                Belum ada item lain-lain
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (!empty($dataDaftarRI['RiLainLain']))
                    <tfoot class="border-t border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-gray-900 dark:text-white">
                                Rp {{ number_format(collect($dataDaftarRI['RiLainLain'])->sum('other_price')) }}
                            </td>
                            @if (!$isFormLocked) <td></td> @endif
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
