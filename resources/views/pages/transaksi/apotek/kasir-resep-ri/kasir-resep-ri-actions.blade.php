<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-kasir-resep-ri'];

    /* ── State ── */
    public ?int $slsNo = null;
    public bool $isLoaded = false;
    public bool $isFormLocked = false;

    public ?string $regNo = null;
    public ?string $regName = null;
    public ?string $sex = null;
    public ?string $birthDate = null;
    public ?int $rihdrNo = null;
    public ?string $riStatus = null;
    public ?string $klaimId = null;
    public ?string $klaimDesc = null;
    public ?string $drId = null;
    public ?string $drName = null;
    public ?string $slsDateDisplay = null;
    public ?string $status = null;

    public array $items = [];

    public int $subtotal = 0;
    public int $actePrice = 3000;
    public int $totalAll = 0;

    public ?int $bayar = null;
    public int $kembalian = 0;
    public int $kekurangan = 0;

    public ?string $accId = null;
    public ?string $accName = null;

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
    }

    /* ===============================
     | OPEN MODAL
     =============================== */
    #[On('kasir-resep-ri.open')]
    public function open(int $slsNo): void
    {
        $this->resetForm();
        $this->slsNo = $slsNo;
        $this->loadData();

        if (!$this->isLoaded) {
            return;
        }

        $this->incrementVersion('modal-kasir-resep-ri');
        $this->dispatch('open-modal', name: 'kasir-resep-ri');
    }

    /* ===============================
     | LOAD DATA
     =============================== */
    private function loadData(): void
    {
        $hdr = DB::table('imtxn_slshdrs as s')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 's.reg_no')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 's.dr_id')
            ->join('rstxn_rihdrs as r', 'r.rihdr_no', '=', 's.rihdr_no')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'r.klaim_id')
            ->leftJoin('acmst_accounts as a', 'a.acc_id', '=', 's.acc_id')
            ->select(
                's.sls_no',
                DB::raw("to_char(s.sls_date,'dd/mm/yyyy hh24:mi:ss') as sls_date_display"),
                's.status',
                's.rihdr_no',
                's.reg_no',
                's.dr_id',
                's.acc_id',
                'a.acc_name',
                's.acte_price',
                's.sls_total',
                's.sls_bayar',
                's.sls_bon',
                'p.reg_name',
                'p.sex',
                DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"),
                'd.dr_name',
                'r.ri_status',
                'r.klaim_id',
                'k.klaim_desc',
            )
            ->where('s.sls_no', $slsNo = $this->slsNo)
            ->first();

        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Data resep tidak ditemukan.');
            $this->isLoaded = false;
            return;
        }

        $this->regNo = $hdr->reg_no;
        $this->regName = $hdr->reg_name;
        $this->sex = $hdr->sex;
        $this->birthDate = $hdr->birth_date;
        $this->rihdrNo = (int) $hdr->rihdr_no;
        $this->riStatus = $hdr->ri_status;
        $this->klaimId = $hdr->klaim_id;
        $this->klaimDesc = $hdr->klaim_desc;
        $this->drId = $hdr->dr_id;
        $this->drName = $hdr->dr_name;
        $this->slsDateDisplay = $hdr->sls_date_display;
        $this->status = $hdr->status ?: 'A';
        $this->actePrice = (int) ($hdr->acte_price ?? 3000);
        $this->accId = $hdr->acc_id;
        $this->accName = $hdr->acc_name ?: $hdr->acc_id;

        $this->items = DB::table('imtxn_slsdtls as dtl')
            ->leftJoin('immst_products as p', 'p.product_id', '=', 'dtl.product_id')
            ->select(
                'dtl.sls_dtl',
                'dtl.product_id',
                DB::raw("nvl(p.product_name,dtl.product_id) as product_name"),
                'dtl.qty',
                'dtl.sales_price',
                'dtl.resep_carapakai',
                'dtl.resep_takar',
                'dtl.resep_kapsul',
                'dtl.resep_ket',
            )
            ->where('dtl.sls_no', $slsNo)
            ->orderBy('dtl.sls_dtl')
            ->get()
            ->map(function ($r) {
                $r->qty = (int) ($r->qty ?? 0);
                $r->sales_price = (int) ($r->sales_price ?? 0);
                $r->subtotal_item = $r->qty * $r->sales_price;
                return (array) $r;
            })
            ->toArray();

        $this->subtotal = (int) collect($this->items)->sum('subtotal_item');
        $this->totalAll = $this->subtotal + $this->actePrice;

        $this->isFormLocked = $this->status === 'L';

        $this->bayar = $this->isFormLocked ? (int) ($hdr->sls_bayar ?? 0) : null;

        $this->recalc();
        $this->isLoaded = true;
    }

    /* ===============================
     | REAKTIF
     =============================== */
    public function updatedActePrice(): void
    {
        if (!auth()->user()->hasAnyRole(['Admin', 'Tu'])) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Admin / TU yang dapat mengubah embalase.');
            $this->loadData();
            return;
        }
        $this->actePrice = max(0, (int) $this->actePrice);
        $this->totalAll = $this->subtotal + $this->actePrice;
        $this->recalc();
    }

    public function updatedBayar(): void
    {
        $this->recalc();
    }

    private function recalc(): void
    {
        $bayar = (int) ($this->bayar ?? 0);
        $this->kembalian = $bayar >= $this->totalAll ? $bayar - $this->totalAll : 0;
        $this->kekurangan = $bayar < $this->totalAll ? $this->totalAll - $bayar : 0;
    }

    /* ===============================
     | LOV KAS
     =============================== */
    #[On('lov.selected.kas-kasir-ri')]
    public function onKasSelected(?array $payload = null): void
    {
        $this->accId = $payload['acc_id'] ?? null;
        $this->accName = $payload['acc_name'] ?? null;
        $this->resetErrorBag('accId');
        $this->dispatch('focus-input-bayar-ri');
    }

    /* ===============================
     | VALIDASI
     =============================== */
    protected function rules(): array
    {
        return [
            'accId' => ['required', 'string'],
            'bayar' => ['required', 'integer', 'min:0'],
            'actePrice' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'accId.required' => 'Akun kas belum dipilih.',
            'bayar.required' => 'Nominal bayar belum diisi.',
            'bayar.min' => 'Nominal bayar tidak valid.',
        ];
    }

    /* ===============================
     | POST TRANSAKSI
     =============================== */
    public function postTransaksi(): void
    {
        if (!auth()->user()->hasAnyRole(['Apoteker', 'Admin', 'Tu'])) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses untuk memproses kasir resep.');
            return;
        }

        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi sudah diproses.');
            return;
        }

        $cekAkunKas = DB::table('user_kas')->where('user_id', auth()->id())->count();
        if ($cekAkunKas === 0) {
            $this->dispatch('toast', type: 'error', message: 'Akun kas anda belum terkonfigurasi. Hubungi administrator.');
            return;
        }

        if (strtoupper($this->riStatus ?? '') === 'P') {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi tidak dapat diproses.');
            return;
        }

        $this->validate();

        $bayar = (int) $this->bayar;
        $totalAll = $this->totalAll;
        $isBon = $bayar < $totalAll;

        try {
            DB::transaction(function () use ($bayar, $totalAll, $isBon) {
                // Lock row
                DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)->lockForUpdate()->first();

                $current = DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)->first();
                if (!$current) {
                    throw new \RuntimeException('Data resep tidak ditemukan.');
                }
                if (strtoupper($current->status ?? 'A') === 'L') {
                    throw new \RuntimeException('Data sudah diproses oleh user lain.');
                }

                // Hitung shift sekarang
                $shift =
                    DB::table('rstxn_shiftctls')
                        ->select('shift')
                        ->whereRaw("to_char(sysdate,'HH24:MI:SS') between shift_start and shift_end")
                        ->value('shift') ?? ($current->shift ?? 1);

                DB::table('imtxn_slshdrs')
                    ->where('sls_no', $this->slsNo)
                    ->update([
                        'status' => 'L',
                        'sls_total' => $totalAll,
                        'sls_bayar' => $bayar,
                        'sls_bon' => $isBon ? $bayar : null,
                        'acc_id' => $this->accId,
                        'acte_price' => $this->actePrice,
                        'shift' => $shift,
                        'waktu_selesai_pelayanan' => DB::raw('sysdate'),
                    ]);

                if ($isBon) {
                    $maxBonNo = (int) DB::table('rstxn_ribonobats')->select(DB::raw('nvl(max(ribon_no)+1,1) as m'))->value('m');

                    DB::table('rstxn_ribonobats')->insert([
                        'ribon_no' => $maxBonNo,
                        'ribon_desc' => 'BR TGL: ' . ($current->sls_date ? Carbon::parse($current->sls_date)->format('d/m/Y') : '-') . ' NO BR: ' . $this->slsNo,
                        'ribon_date' => $current->sls_date,
                        'ribon_price' => $totalAll - $bayar,
                        'rihdr_no' => $this->rihdrNo,
                        'sls_no' => $this->slsNo,
                    ]);
                }
            });

            $this->isFormLocked = true;
            $this->status = 'L';
            $this->incrementVersion('modal-kasir-resep-ri');

            $msg = $isBon
                ? 'Transaksi tersimpan. Sisa Rp ' . number_format($totalAll - $bayar) . ' masuk Bon Inap.'
                : 'Transaksi LUNAS tersimpan.';

            $this->dispatch('toast', type: 'success', message: $msg);
            $this->dispatch('refresh-after-kasir-ri.saved');
            $this->dispatch('cetak-kwitansi-ri-obat.open', slsNo: $this->slsNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal memproses: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BATAL TRANSAKSI
     =============================== */
    public function batalTransaksi(): void
    {
        if (!auth()->user()->hasAnyRole(['Apoteker', 'Admin', 'Tu'])) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses untuk membatalkan transaksi.');
            return;
        }

        if ($this->status !== 'L') {
            $this->dispatch('toast', type: 'error', message: 'Transaksi belum diproses, tidak perlu dibatalkan.');
            return;
        }

        if (strtoupper($this->riStatus ?? '') === 'P') {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi tidak dapat dibatalkan.');
            return;
        }

        try {
            DB::transaction(function () {
                DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)->lockForUpdate()->first();

                $current = DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)->first();
                if (!$current) {
                    throw new \RuntimeException('Data resep tidak ditemukan.');
                }
                if (strtoupper($current->status ?? 'A') !== 'L') {
                    throw new \RuntimeException('Transaksi sudah dalam status belum diproses.');
                }

                DB::table('imtxn_slshdrs')
                    ->where('sls_no', $this->slsNo)
                    ->update([
                        'status' => 'A',
                        'sls_bayar' => null,
                        'sls_bon' => null,
                        'acc_id' => null,
                        'waktu_selesai_pelayanan' => null,
                    ]);

                DB::table('rstxn_ribonobats')->where('sls_no', $this->slsNo)->delete();
            });

            $this->status = 'A';
            $this->isFormLocked = false;
            $this->bayar = null;
            $this->accId = null;
            $this->accName = null;
            $this->recalc();
            $this->incrementVersion('modal-kasir-resep-ri');

            $this->dispatch('toast', type: 'success', message: 'Transaksi berhasil dibatalkan.');
            $this->dispatch('refresh-after-kasir-ri.saved');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membatalkan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK
     =============================== */
    public function cetakKwitansi(): void
    {
        $this->dispatch('cetak-kwitansi-ri-obat.open', slsNo: $this->slsNo);
    }

    /* ===============================
     | CLOSE
     =============================== */
    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'kasir-resep-ri');
        $this->resetForm();
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function resetForm(): void
    {
        $this->reset([
            'slsNo', 'isLoaded', 'isFormLocked',
            'regNo', 'regName', 'sex', 'birthDate',
            'rihdrNo', 'riStatus', 'klaimId', 'klaimDesc',
            'drId', 'drName', 'slsDateDisplay', 'status',
            'items', 'bayar', 'accId', 'accName',
        ]);
        $this->actePrice = 3000;
        $this->subtotal = 0;
        $this->totalAll = 0;
        $this->kembalian = 0;
        $this->kekurangan = 0;
        $this->resetVersion();
        $this->resetErrorBag();
    }

    /* ===============================
     | UMUR
     =============================== */
    #[Computed]
    public function umurFormat(): string
    {
        if (!$this->birthDate) {
            return '-';
        }
        try {
            $diff = Carbon::createFromFormat('d/m/Y', $this->birthDate)->diff(now());
            return "{$diff->y} Thn {$diff->m} Bln {$diff->d} Hr";
        } catch (\Exception $e) {
            return '-';
        }
    }

    #[Computed]
    public function canEditEmbalase(): bool
    {
        return !$this->isFormLocked && auth()->user()->hasAnyRole(['Admin', 'Tu']);
    }
};
?>

<div>
    <x-modal name="kasir-resep-ri" size="full" height="full" focusable>
        <div wire:key="{{ $this->renderKey('modal-kasir-resep-ri', [$slsNo ?? 'new']) }}"
            x-data
            x-on:focus-input-bayar-ri.window="$nextTick(() => document.querySelector('[x-ref=inputBayarRi]')?.focus())">

            {{-- HEADER --}}
            <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="min-w-0">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Kasir Resep Rawat Inap
                    </h3>
                    @if ($isLoaded)
                        <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">
                            <span class="font-semibold">{{ $regName }}</span>
                            <span class="text-gray-400">·</span>
                            {{ $regNo }}
                            <span class="text-gray-400">·</span>
                            {{ $sex === 'L' ? 'Laki-Laki' : ($sex === 'P' ? 'Perempuan' : '-') }}
                            ({{ $this->umurFormat }})
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            No SLS <span class="font-mono font-semibold">{{ $slsNo }}</span>
                            <span class="text-gray-300 mx-1">|</span>
                            No RI <span class="font-mono">{{ $rihdrNo }}</span>
                            <span class="text-gray-300 mx-1">|</span>
                            Dokter: {{ $drName ?? '-' }}
                            <span class="text-gray-300 mx-1">|</span>
                            Tgl: {{ $slsDateDisplay }}
                        </p>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <x-badge :variant="$klaimId === 'JM' ? 'brand' : ($klaimId === 'UM' ? 'success' : 'alternative')">
                                {{ $klaimId === 'UM' ? 'UMUM' : ($klaimId === 'JM' ? 'BPJS' : ($klaimDesc ?? 'Asuransi Lain')) }}
                            </x-badge>
                            @if (strtoupper($riStatus ?? '') === 'P')
                                <x-badge variant="gray">Pasien Sudah Pulang</x-badge>
                            @elseif (strtoupper($riStatus ?? '') === 'A')
                                <x-badge variant="brand">Pasien Dirawat</x-badge>
                            @endif
                            @if ($status === 'L')
                                <x-badge variant="success">Sudah Diproses</x-badge>
                            @else
                                <x-badge variant="warning">Belum Diproses</x-badge>
                            @endif
                        </div>
                    @endif
                </div>
                <button wire:click="closeModal"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            @if (!$isLoaded)
                <div class="px-6 py-12 text-center text-gray-400">Memuat data...</div>
            @else
                {{-- BODY --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 divide-y lg:divide-y-0 lg:divide-x divide-gray-200 dark:divide-gray-700">

                    {{-- ══════════════ KIRI: DETAIL OBAT (2/3) ══════════════ --}}
                    <div class="flex flex-col lg:col-span-2 px-6 py-4 max-h-[calc(100vh-280px)] overflow-y-auto">

                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            Detail Obat ({{ count($items) }} item)
                        </h4>

                        <div class="overflow-hidden border border-gray-200 rounded-xl dark:border-gray-700">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800/50 text-xs uppercase text-gray-500">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Nama Obat</th>
                                        <th class="px-3 py-2 text-center w-16">Qty</th>
                                        <th class="px-3 py-2 text-center w-32">Signa / Cara</th>
                                        <th class="px-3 py-2 text-right w-28">Harga</th>
                                        <th class="px-3 py-2 text-right w-28">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @forelse ($items as $item)
                                        <tr>
                                            <td class="px-3 py-2 text-gray-800 dark:text-gray-200">
                                                <div class="font-medium uppercase">{{ $item['product_name'] ?? '-' }}</div>
                                                @if (!empty($item['resep_ket']))
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ $item['resep_ket'] }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-center font-mono">
                                                {{ $item['qty'] }} {{ $item['resep_takar'] ?? '' }}
                                            </td>
                                            <td class="px-3 py-2 text-center text-xs text-gray-600">
                                                S{{ $item['resep_carapakai'] ?? '-' }}dd{{ $item['resep_kapsul'] ?? '-' }}
                                            </td>
                                            <td class="px-3 py-2 text-right font-mono text-gray-700 dark:text-gray-300">
                                                {{ number_format($item['sales_price']) }}
                                            </td>
                                            <td class="px-3 py-2 text-right font-mono font-semibold text-gray-900 dark:text-gray-100">
                                                {{ number_format($item['subtotal_item']) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-3 py-8 text-center text-gray-400">
                                                Tidak ada item obat
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700">
                                    <tr>
                                        <td colspan="4" class="px-3 py-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">
                                            Subtotal
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono font-bold text-gray-900 dark:text-white">
                                            Rp {{ number_format($subtotal) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                    </div>

                    {{-- ══════════════ KANAN: KASIR (1/3) ══════════════ --}}
                    <div class="flex flex-col px-6 py-4 max-h-[calc(100vh-280px)] overflow-y-auto bg-gray-50/50 dark:bg-gray-800/20">

                        {{-- LOCKED BANNER --}}
                        @if ($isFormLocked)
                            <div class="flex items-center gap-2 px-3 py-2 mb-3 text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg dark:bg-emerald-900/20 dark:border-emerald-700 dark:text-emerald-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Transaksi sudah diproses.
                            </div>
                        @endif

                        {{-- RINGKASAN BIAYA --}}
                        <div class="space-y-2 mb-4">
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-gray-500 dark:text-gray-400">Subtotal Obat</span>
                                <span class="font-mono text-sm text-gray-800 dark:text-gray-200">
                                    Rp {{ number_format($subtotal) }}
                                </span>
                            </div>

                            <div class="flex justify-between items-center gap-2">
                                <span class="text-xs text-gray-500 dark:text-gray-400 shrink-0">
                                    Embalase
                                    @if ($this->canEditEmbalase)
                                        <span class="text-[10px] text-amber-600">(editable)</span>
                                    @endif
                                </span>
                                @if ($this->canEditEmbalase)
                                    <x-text-input wire:model.live.debounce.300ms="actePrice" type="number" min="0"
                                        class="w-32 text-right font-mono text-sm py-1" />
                                @else
                                    <span class="font-mono text-sm text-gray-800 dark:text-gray-200">
                                        Rp {{ number_format($actePrice) }}
                                    </span>
                                @endif
                            </div>

                            <div class="flex justify-between items-center pt-2 border-t border-gray-200 dark:border-gray-700">
                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Total</span>
                                <span class="font-mono text-lg font-bold text-gray-900 dark:text-white">
                                    Rp {{ number_format($totalAll) }}
                                </span>
                            </div>
                        </div>

                        {{-- FORM KASIR --}}
                        @if (!$isFormLocked)
                            @if (strtoupper($riStatus ?? '') === 'P')
                                <div class="px-3 py-2 mb-3 text-xs text-rose-700 bg-rose-50 border border-rose-200 rounded-lg dark:bg-rose-900/20 dark:border-rose-700 dark:text-rose-300">
                                    Pasien sudah pulang. Transaksi tidak dapat diproses.
                                </div>
                            @else
                                <div class="space-y-3">

                                    {{-- LOV Akun Kas --}}
                                    <div>
                                        <livewire:lov.kas.lov-kas
                                            target="kas-kasir-ri"
                                            tipe="ri"
                                            label="Akun Kas / Cara Bayar"
                                            :initialAccId="$accId"
                                            wire:key="lov-kas-kasir-ri-{{ $slsNo }}-{{ $renderVersions['modal-kasir-resep-ri'] ?? 0 }}" />
                                        <x-input-error :messages="$errors->get('accId')" class="mt-1" />
                                    </div>

                                    {{-- Bayar --}}
                                    <div>
                                        <x-input-label value="Nominal Bayar (Rp)" class="mb-1" />
                                        <x-text-input type="number" wire:model.live="bayar"
                                            placeholder="0" min="0"
                                            class="w-full font-mono text-right text-base"
                                            x-ref="inputBayarRi"
                                            x-on:keyup.enter="$wire.postTransaksi()" />
                                        <x-input-error :messages="$errors->get('bayar')" class="mt-1" />
                                    </div>

                                    {{-- Kembalian / Kurang --}}
                                    @if ((int) ($bayar ?? 0) >= $totalAll && $totalAll > 0)
                                        <div class="px-3 py-2 rounded-lg border border-emerald-200 dark:border-emerald-800/40 bg-emerald-50 dark:bg-emerald-900/10">
                                            <p class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Kembalian</p>
                                            <p class="font-mono text-lg font-bold text-emerald-700 dark:text-emerald-300">
                                                Rp {{ number_format($kembalian) }}
                                            </p>
                                        </div>
                                    @elseif ((int) ($bayar ?? 0) > 0 && (int) ($bayar ?? 0) < $totalAll)
                                        <div class="px-3 py-2 rounded-lg border border-amber-200 dark:border-amber-800/40 bg-amber-50 dark:bg-amber-900/10">
                                            <p class="text-xs font-medium text-amber-600 dark:text-amber-400">Sisa → Bon Inap</p>
                                            <p class="font-mono text-lg font-bold text-amber-700 dark:text-amber-300">
                                                Rp {{ number_format($kekurangan) }}
                                            </p>
                                        </div>
                                    @endif

                                    {{-- Status preview --}}
                                    @if ((int) ($bayar ?? 0) >= $totalAll && $totalAll > 0)
                                        <div class="flex items-center gap-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Akan diproses sebagai LUNAS
                                        </div>
                                    @elseif ((int) ($bayar ?? 0) >= 0 && (int) ($bayar ?? 0) < $totalAll && $bayar !== null)
                                        <div class="flex items-center gap-1.5 text-xs font-semibold text-amber-600 dark:text-amber-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Akan diproses sebagai BON (sisa masuk Bon Inap)
                                        </div>
                                    @endif

                                </div>
                            @endif
                        @else
                            {{-- Mode lihat --}}
                            <div class="space-y-2">
                                <div class="flex justify-between text-xs">
                                    <span class="text-gray-500">Cara Bayar</span>
                                    <span class="font-mono text-gray-700 dark:text-gray-300">
                                        {{ $accName ?? $accId ?? '-' }}
                                    </span>
                                </div>
                                <div class="flex justify-between text-xs">
                                    <span class="text-gray-500">Dibayar</span>
                                    <span class="font-mono font-semibold text-emerald-700 dark:text-emerald-300">
                                        Rp {{ number_format((int) ($bayar ?? 0)) }}
                                    </span>
                                </div>
                                @if ($totalAll > (int) ($bayar ?? 0))
                                    <div class="flex justify-between text-xs">
                                        <span class="text-gray-500">Bon Inap</span>
                                        <span class="font-mono font-semibold text-amber-700 dark:text-amber-300">
                                            Rp {{ number_format($totalAll - (int) ($bayar ?? 0)) }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        @endif

                    </div>

                </div>

                {{-- FOOTER --}}
                <div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900">
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>

                    <div class="flex gap-2">
                        @if ($isFormLocked)
                            @hasanyrole('Apoteker|Admin|Tu')
                                @if (strtoupper($riStatus ?? '') !== 'P')
                                    <x-confirm-button variant="danger" :action="'batalTransaksi()'"
                                        title="Batal Transaksi"
                                        message="Yakin ingin membatalkan transaksi ini? Bon Inap (jika ada) juga akan dihapus."
                                        confirmText="Ya, batalkan" cancelText="Batal">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        Batal Transaksi
                                    </x-confirm-button>
                                @endif
                            @endhasanyrole

                            <x-info-button wire:click="cetakKwitansi" wire:loading.attr="disabled" wire:target="cetakKwitansi">
                                <span wire:loading.remove wire:target="cetakKwitansi" class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                    </svg>
                                    Cetak Kwitansi
                                </span>
                                <span wire:loading wire:target="cetakKwitansi" class="flex items-center gap-1">
                                    <x-loading /> Menyiapkan...
                                </span>
                            </x-info-button>
                        @else
                            @if (strtoupper($riStatus ?? '') !== 'P')
                                @hasanyrole('Apoteker|Admin|Tu')
                                    <x-primary-button wire:click="postTransaksi" wire:loading.attr="disabled" wire:target="postTransaksi">
                                        <span wire:loading.remove wire:target="postTransaksi">Post Transaksi</span>
                                        <span wire:loading wire:target="postTransaksi"><x-loading /></span>
                                    </x-primary-button>
                                @endhasanyrole
                            @endif
                        @endif
                    </div>
                </div>
            @endif

        </div>
    </x-modal>

    {{-- Komponen cetak kwitansi --}}
    <livewire:pages::components.modul-dokumen.r-i.kwitansi.cetak-kwitansi-ri-obat
        wire:key="cetak-kwitansi-ri-obat" />
</div>
