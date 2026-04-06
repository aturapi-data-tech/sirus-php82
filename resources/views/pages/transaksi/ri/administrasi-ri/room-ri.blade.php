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
    protected array $renderAreas = ['modal-room-ri'];

    public bool $isFormLocked = false;
    public ?int $riHdrNo = null;
    public array $dataDaftarRI = [];

    public array $formEntry = [
        'roomStartDate'  => '',
        'roomId'         => '',
        'roomName'       => '',
        'roomBedNo'      => '',
        'roomPrice'      => '',
        'perawatanPrice' => '',
        'commonService'  => '',
        'roomDay'        => '1',
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
            $this->dataDaftarRI['RiRoom'] = [];
        }
    }

    private function findData(int $riHdrNo): void
    {
        $rows = DB::table('rsmst_trfrooms')
            ->select(
                DB::raw("to_char(start_date, 'dd/mm/yyyy hh24:mi:ss') as start_date"),
                DB::raw("to_char(end_date, 'dd/mm/yyyy hh24:mi:ss') as end_date"),
                'room_id', 'bed_no', 'room_price', 'perawatan_price', 'common_service',
                DB::raw("ROUND(nvl(day, nvl(end_date,sysdate+1)-nvl(start_date,sysdate))) as day"),
                'trfr_no',
            )
            ->where('rihdr_no', $riHdrNo)
            ->orderByDesc('start_date')
            ->get();

        $this->dataDaftarRI['RiRoom'] = $rows->map(fn($r) => (array) $r)->toArray();
    }

    /* ===============================
     | LOV SELECTED — ROOM
     =============================== */
    #[On('lov.selected.room-ri')]
    public function onRoomSelected(?array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        if (!$payload) {
            $this->formEntry['roomId']   = '';
            $this->formEntry['roomName'] = '';
            $this->formEntry['roomBedNo'] = '';
            return;
        }

        $this->formEntry['roomId']    = $payload['room_id'];
        $this->formEntry['roomName']  = $payload['room_name'];
        $this->formEntry['roomBedNo'] = $payload['bed_no'] ?? '';

        // Auto-isi harga dari master
        $room = DB::table('rsmst_rooms')
            ->select('room_price', 'perawatan_price', 'common_service')
            ->where('room_id', $payload['room_id'])
            ->first();

        $this->formEntry['roomPrice']      = $room->room_price      ?? 0;
        $this->formEntry['perawatanPrice'] = $room->perawatan_price ?? 0;
        $this->formEntry['commonService']  = $room->common_service  ?? 0;

        if (empty($this->formEntry['roomStartDate'])) {
            $this->formEntry['roomStartDate'] = $this->nowFormatted();
        }

        $this->dispatch('focus-input-room-day');
    }

    /* ===============================
     | INSERT ROOM
     =============================== */
    public function insertRoom(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        $this->validate(
            [
                'formEntry.roomStartDate'  => 'bail|required|date_format:d/m/Y H:i:s',
                'formEntry.roomId'         => 'bail|required|exists:rsmst_rooms,room_id',
                'formEntry.roomBedNo'      => 'bail|required',
                'formEntry.roomPrice'      => 'bail|required|numeric|min:0',
                'formEntry.perawatanPrice' => 'bail|required|numeric|min:0',
                'formEntry.commonService'  => 'bail|required|numeric|min:0',
                'formEntry.roomDay'        => 'bail|required|numeric|min:1',
            ],
            [
                'formEntry.roomStartDate.required'  => 'Tanggal masuk wajib diisi.',
                'formEntry.roomStartDate.date_format' => 'Format tanggal: dd/mm/yyyy hh24:mi:ss.',
                'formEntry.roomId.required'         => 'Kamar wajib dipilih.',
                'formEntry.roomId.exists'           => 'Kamar tidak valid.',
                'formEntry.roomBedNo.required'      => 'Nomor bed wajib diisi.',
                'formEntry.roomPrice.required'      => 'Tarif kamar wajib diisi.',
                'formEntry.roomDay.required'        => 'Jumlah hari wajib diisi.',
                'formEntry.roomDay.min'             => 'Minimal 1 hari.',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                // Update end_date kamar sebelumnya (yang belum punya end_date)
                $lastRoom = DB::table('rsmst_trfrooms')
                    ->select('trfr_no')
                    ->where('rihdr_no', $this->riHdrNo)
                    ->whereNull('end_date')
                    ->orderByDesc('trfr_no')
                    ->first();

                if ($lastRoom) {
                    // Hitung hari sejak start_date terakhir
                    $longDay = DB::table('rsmst_trfrooms')
                        ->select(DB::raw("ROUND(TO_DATE('" . $this->formEntry['roomStartDate'] . "','dd/mm/yyyy hh24:mi:ss') - start_date) as day"))
                        ->where('trfr_no', $lastRoom->trfr_no)
                        ->first();

                    DB::table('rsmst_trfrooms')
                        ->where('rihdr_no', $this->riHdrNo)
                        ->where('trfr_no', $lastRoom->trfr_no)
                        ->update([
                            'end_date' => DB::raw("to_date('" . $this->formEntry['roomStartDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
                            'day'      => $longDay->day ?? 1,
                        ]);
                }

                $last = DB::table('rsmst_trfrooms')
                    ->select(DB::raw("nvl(max(trfr_no)+1,1) as trfr_no_max"))
                    ->first();

                DB::table('rsmst_trfrooms')->insert([
                    'trfr_no'         => $last->trfr_no_max,
                    'rihdr_no'        => $this->riHdrNo,
                    'room_id'         => $this->formEntry['roomId'],
                    'bed_no'          => $this->formEntry['roomBedNo'],
                    'start_date'      => DB::raw("to_date('" . $this->formEntry['roomStartDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
                    'room_price'      => $this->formEntry['roomPrice'],
                    'perawatan_price' => $this->formEntry['perawatanPrice'],
                    'common_service'  => $this->formEntry['commonService'],
                    'day'             => $this->formEntry['roomDay'],
                ]);

                // Update bed_no di header RI
                DB::table('rstxn_rihdrs')
                    ->where('rihdr_no', $this->riHdrNo)
                    ->update([
                        'room_id' => $this->formEntry['roomId'],
                        'bed_no'  => $this->formEntry['roomBedNo'],
                    ]);
                $this->appendAdminLog($this->riHdrNo, 'Tambah Kamar: ' . $this->formEntry['roomName'] . ' Bed ' . $this->formEntry['roomBedNo']);
            });

            $this->resetFormEntry();
            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('toast', type: 'success', message: 'Kamar berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE ROOM
     =============================== */
    public function removeRoom(int $trfrNo): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        try {
            DB::transaction(function () use ($trfrNo) {
                $this->lockRIRow($this->riHdrNo);
                DB::table('rsmst_trfrooms')->where('trfr_no', $trfrNo)->delete();
                $this->appendAdminLog($this->riHdrNo, 'Hapus Kamar #' . $trfrNo);
            });

            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('toast', type: 'success', message: 'Data kamar berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function resetFormEntry(): void
    {
        $this->reset(['formEntry']);
        $this->formEntry['roomDay']      = '1';
        $this->formEntry['roomStartDate'] = $this->nowFormatted();
        $this->resetValidation();
        $this->incrementVersion('modal-room-ri');
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-room-ri', [$riHdrNo ?? 'new']) }}">

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
            x-on:focus-input-room-day.window="$nextTick(() => $refs.inputRoomDay?.focus())">

            @if (empty($formEntry['roomId']))
                <div class="w-64">
                    <livewire:lov.room.lov-room target="room-ri" label="Kamar / Bed"
                        placeholder="Ketik kode/nama kamar..."
                        wire:key="lov-room-{{ $riHdrNo }}-{{ $renderVersions['modal-room-ri'] ?? 0 }}" />
                </div>
            @else
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div>
                        <x-input-label value="Kamar" class="mb-1" />
                        <x-text-input wire:model="formEntry.roomName" disabled class="w-full text-sm" />
                    </div>
                    <div>
                        <x-input-label value="Bed No" class="mb-1" />
                        <x-text-input wire:model="formEntry.roomBedNo" class="w-full text-sm"
                            placeholder="Nomor bed" />
                        @error('formEntry.roomBedNo') <x-input-error :messages="$message" class="mt-1" /> @enderror
                    </div>
                    <div>
                        <x-input-label value="Tanggal Masuk" class="mb-1" />
                        <x-text-input wire:model="formEntry.roomStartDate" placeholder="dd/mm/yyyy hh:mm:ss" class="w-full text-sm" />
                        @error('formEntry.roomStartDate') <x-input-error :messages="$message" class="mt-1" /> @enderror
                    </div>
                    <div>
                        <x-input-label value="Hari" class="mb-1" />
                        <x-text-input wire:model="formEntry.roomDay" placeholder="Hari" class="w-full text-sm"
                            x-ref="inputRoomDay"
                            x-on:keyup.enter="$wire.insertRoom()" />
                        @error('formEntry.roomDay') <x-input-error :messages="$message" class="mt-1" /> @enderror
                    </div>
                    <div>
                        <x-input-label value="Tarif Kamar/Hari" class="mb-1" />
                        <x-text-input wire:model="formEntry.roomPrice" class="w-full text-sm" />
                        @error('formEntry.roomPrice') <x-input-error :messages="$message" class="mt-1" /> @enderror
                    </div>
                    <div>
                        <x-input-label value="Perawatan/Hari" class="mb-1" />
                        <x-text-input wire:model="formEntry.perawatanPrice" class="w-full text-sm" />
                        @error('formEntry.perawatanPrice') <x-input-error :messages="$message" class="mt-1" /> @enderror
                    </div>
                    <div>
                        <x-input-label value="Common Service/Hari" class="mb-1" />
                        <x-text-input wire:model="formEntry.commonService" class="w-full text-sm" />
                        @error('formEntry.commonService') <x-input-error :messages="$message" class="mt-1" /> @enderror
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="button" wire:click.prevent="insertRoom" wire:loading.attr="disabled"
                            wire:target="insertRoom"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold text-white bg-brand-green hover:bg-brand-green/90 disabled:opacity-60 dark:bg-brand-lime dark:text-gray-900 transition shadow-sm">
                            <span wire:loading.remove wire:target="insertRoom">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </span>
                            <span wire:loading wire:target="insertRoom"><x-loading class="w-4 h-4" /></span>
                            Tambah
                        </button>
                        <button type="button" wire:click.prevent="resetFormEntry"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
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
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Riwayat Kamar</h3>
            <x-badge variant="gray">{{ count($dataDaftarRI['RiRoom'] ?? []) }} kamar</x-badge>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Kamar</th>
                        <th class="px-4 py-3">Bed</th>
                        <th class="px-4 py-3">Mulai</th>
                        <th class="px-4 py-3">Selesai</th>
                        <th class="px-4 py-3 text-right">Hari</th>
                        <th class="px-4 py-3 text-right">Kamar/Hr</th>
                        <th class="px-4 py-3 text-right">Perawatan/Hr</th>
                        <th class="px-4 py-3 text-right">CS/Hr</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                        @if (!$isFormLocked) <th class="w-20 px-4 py-3 text-center">Hapus</th> @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($dataDaftarRI['RiRoom'] ?? [] as $item)
                        @php
                            $day      = (int) ($item['day'] ?? 1);
                            $subtotal = ($item['room_price'] ?? 0) * $day
                                      + ($item['perawatan_price'] ?? 0) * $day
                                      + ($item['common_service'] ?? 0) * $day;
                        @endphp
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ $item['room_id'] }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $item['bed_no'] ?? '-' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">{{ $item['start_date'] ?? '-' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">{{ $item['end_date'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ $day }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($item['room_price'] ?? 0) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($item['perawatan_price'] ?? 0) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($item['common_service'] ?? 0) }}</td>
                            <td class="px-4 py-3 font-semibold text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                Rp {{ number_format($subtotal) }}
                            </td>
                            @if (!$isFormLocked)
                                <td class="px-4 py-3 text-center">
                                    <button type="button"
                                        wire:click.prevent="removeRoom({{ $item['trfr_no'] }})"
                                        wire:confirm="Hapus data kamar ini?" wire:loading.attr="disabled"
                                        wire:target="removeRoom({{ $item['trfr_no'] }})"
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
                            <td colspan="{{ $isFormLocked ? 9 : 10 }}"
                                class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                Belum ada data kamar
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (!empty($dataDaftarRI['RiRoom']))
                    <tfoot class="border-t border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="8" class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-gray-900 dark:text-white">
                                Rp {{ number_format(collect($dataDaftarRI['RiRoom'])->sum(function ($r) {
                                    $d = (int)($r['day'] ?? 1);
                                    return (($r['room_price'] ?? 0) + ($r['perawatan_price'] ?? 0) + ($r['common_service'] ?? 0)) * $d;
                                })) }}
                            </td>
                            @if (!$isFormLocked) <td></td> @endif
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
