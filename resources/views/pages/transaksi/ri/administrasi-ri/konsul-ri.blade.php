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
    protected array $renderAreas = ['modal-konsul-ri'];

    public bool $isFormLocked = false;
    public ?int $riHdrNo = null;
    public array $dataDaftarRI = [];

    public array $formEntry = [
        'konsulDate'  => '',
        'drId'        => '',
        'drName'      => '',
        'konsulPrice' => '',
    ];

    private function nowFormatted(): string
    {
        return Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);

        if ($this->riHdrNo) {
            $this->findData($this->riHdrNo);
        } else {
            $this->dataDaftarRI['RiKonsul'] = [];
        }
    }

    private function findData(int $riHdrNo): void
    {
        $rows = DB::table('rstxn_rikonsuls')
            ->join('rsmst_doctors', 'rstxn_rikonsuls.dr_id', '=', 'rsmst_doctors.dr_id')
            ->select(
                DB::raw("to_char(konsul_date, 'dd/mm/yyyy hh24:mi:ss') as konsul_date"),
                'rstxn_rikonsuls.dr_id',
                'rsmst_doctors.dr_name',
                'rstxn_rikonsuls.konsul_price',
                'rstxn_rikonsuls.konsul_no',
            )
            ->where('rstxn_rikonsuls.rihdr_no', $riHdrNo)
            ->orderBy('konsul_date')
            ->get();

        $this->dataDaftarRI['RiKonsul'] = $rows->map(fn($r) => (array) $r)->toArray();
    }

    /* ===============================
     | LOV SELECTED — DOKTER
     =============================== */
    #[On('lov.selected.dokter-konsul-ri')]
    public function onDokterSelected(?array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        if (!$payload) {
            $this->formEntry['drId']   = '';
            $this->formEntry['drName'] = '';
            return;
        }

        $this->formEntry['drId']   = $payload['dr_id'];
        $this->formEntry['drName'] = $payload['dr_name'];

        if (empty($this->formEntry['konsulDate'])) {
            $this->formEntry['konsulDate'] = $this->nowFormatted();
        }

        // Auto-harga konsul
        if (empty($this->formEntry['konsulPrice'])) {
            $classId = DB::table('rstxn_rihdrs')
                ->join('rsmst_rooms', 'rstxn_rihdrs.room_id', '=', 'rsmst_rooms.room_id')
                ->where('rihdr_no', $this->riHdrNo)
                ->value('class_id');

            if ($classId) {
                $riData      = $this->findDataRI($this->riHdrNo);
                $klaimStatus = DB::table('rsmst_klaimtypes')
                    ->where('klaim_id', $riData['klaimId'] ?? '')
                    ->value('klaim_status') ?? 'UMUM';

                $col   = $klaimStatus === 'BPJS' ? 'konsul_price_bpjs' : 'konsul_price';
                $price = DB::table('rsmst_docvisits')
                    ->where('dr_id', $payload['dr_id'])
                    ->where('class_id', $classId)
                    ->value($col);

                $this->formEntry['konsulPrice'] = $price ?? 0;
            }
        }

        $this->dispatch('focus-input-konsul-price');
    }

    /* ===============================
     | INSERT
     =============================== */
    public function insertKonsul(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        $this->validate(
            [
                'formEntry.konsulDate'  => 'bail|required|date_format:d/m/Y H:i:s',
                'formEntry.drId'        => 'bail|required|exists:rsmst_doctors,dr_id',
                'formEntry.konsulPrice' => 'bail|required|numeric|min:0',
            ],
            [
                'formEntry.konsulDate.required'    => 'Tanggal konsul wajib diisi.',
                'formEntry.konsulDate.date_format' => 'Format tanggal: dd/mm/yyyy hh24:mi:ss.',
                'formEntry.drId.required'          => 'Dokter wajib dipilih.',
                'formEntry.drId.exists'            => 'Dokter tidak valid.',
                'formEntry.konsulPrice.required'   => 'Tarif konsul wajib diisi.',
                'formEntry.konsulPrice.numeric'    => 'Tarif harus berupa angka.',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $last = DB::table('rstxn_rikonsuls')
                    ->select(DB::raw("nvl(max(konsul_no)+1,1) as konsul_no_max"))
                    ->first();

                DB::table('rstxn_rikonsuls')->insert([
                    'konsul_no'    => $last->konsul_no_max,
                    'rihdr_no'     => $this->riHdrNo,
                    'dr_id'        => $this->formEntry['drId'],
                    'konsul_date'  => DB::raw("to_date('" . $this->formEntry['konsulDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
                    'konsul_price' => $this->formEntry['konsulPrice'],
                ]);
                $this->appendAdminLog($this->riHdrNo, 'Tambah Konsul: Dr. ' . $this->formEntry['drName']);
            });

            $this->resetFormEntry();
            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('toast', type: 'success', message: 'Konsultasi berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE
     =============================== */
    public function removeKonsul(int $konsulNo): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        try {
            DB::transaction(function () use ($konsulNo) {
                $this->lockRIRow($this->riHdrNo);
                DB::table('rstxn_rikonsuls')->where('konsul_no', $konsulNo)->delete();
                $this->appendAdminLog($this->riHdrNo, 'Hapus Konsul #' . $konsulNo);
            });

            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('toast', type: 'success', message: 'Konsultasi berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function resetFormEntry(): void
    {
        $this->reset(['formEntry']);
        $this->formEntry['konsulDate'] = $this->nowFormatted();
        $this->resetValidation();
        $this->incrementVersion('modal-konsul-ri');
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-konsul-ri', [$riHdrNo ?? 'new']) }}">

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
            x-on:focus-input-konsul-price.window="$nextTick(() => $refs.inputKonsulPrice?.focus())">

            @if (empty($formEntry['drId']))
                <div class="w-64">
                    <livewire:lov.dokter.lov-dokter target="dokter-konsul-ri" label="Dokter Konsul"
                        placeholder="Ketik kode/nama dokter..."
                        wire:key="lov-dokter-konsul-{{ $riHdrNo }}-{{ $renderVersions['modal-konsul-ri'] ?? 0 }}" />
                </div>
            @else
                <div class="flex items-end gap-3">
                    <div class="w-48">
                        <x-input-label value="Dokter" class="mb-1" />
                        <x-text-input wire:model="formEntry.drName" disabled class="w-full text-sm" />
                    </div>
                    <div class="flex-1">
                        <x-input-label value="Tanggal Konsul" class="mb-1" />
                        <x-text-input wire:model="formEntry.konsulDate" placeholder="dd/mm/yyyy hh:mm:ss" class="w-full text-sm"
                            x-on:keyup.enter="$wire.insertKonsul()" />
                        @error('formEntry.konsulDate')
                            <x-input-error :messages="$message" class="mt-1" />
                        @enderror
                    </div>
                    <div class="w-40">
                        <x-input-label value="Tarif" class="mb-1" />
                        <x-text-input wire:model="formEntry.konsulPrice" placeholder="Tarif" class="w-full text-sm"
                            x-ref="inputKonsulPrice"
                            x-on:keyup.enter="$wire.insertKonsul()" />
                        @error('formEntry.konsulPrice')
                            <x-input-error :messages="$message" class="mt-1" />
                        @enderror
                    </div>
                    <div class="flex gap-2 pb-0.5">
                        <button type="button" wire:click.prevent="insertKonsul" wire:loading.attr="disabled"
                            wire:target="insertKonsul"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold text-white bg-brand-green hover:bg-brand-green/90 disabled:opacity-60 dark:bg-brand-lime dark:text-gray-900 transition shadow-sm">
                            <span wire:loading.remove wire:target="insertKonsul">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </span>
                            <span wire:loading wire:target="insertKonsul"><x-loading class="w-4 h-4" /></span>
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
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Daftar Konsultasi</h3>
            <x-badge variant="gray">{{ count($dataDaftarRI['RiKonsul'] ?? []) }} item</x-badge>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Dokter</th>
                        <th class="px-4 py-3 text-right">Tarif</th>
                        @if (!$isFormLocked) <th class="w-20 px-4 py-3 text-center">Hapus</th> @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($dataDaftarRI['RiKonsul'] ?? [] as $item)
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">{{ $item['konsul_date'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-200 whitespace-nowrap">{{ $item['dr_name'] ?? '-' }}</td>
                            <td class="px-4 py-3 font-semibold text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                Rp {{ number_format($item['konsul_price'] ?? 0) }}
                            </td>
                            @if (!$isFormLocked)
                                <td class="px-4 py-3 text-center">
                                    <button type="button"
                                        wire:click.prevent="removeKonsul({{ $item['konsul_no'] }})"
                                        wire:confirm="Hapus konsultasi ini?" wire:loading.attr="disabled"
                                        wire:target="removeKonsul({{ $item['konsul_no'] }})"
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
                            <td colspan="{{ $isFormLocked ? 3 : 4 }}"
                                class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                Belum ada konsultasi
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (!empty($dataDaftarRI['RiKonsul']))
                    <tfoot class="border-t border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="2" class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-gray-900 dark:text-white">
                                Rp {{ number_format(collect($dataDaftarRI['RiKonsul'])->sum('konsul_price')) }}
                            </td>
                            @if (!$isFormLocked) <td></td> @endif
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
