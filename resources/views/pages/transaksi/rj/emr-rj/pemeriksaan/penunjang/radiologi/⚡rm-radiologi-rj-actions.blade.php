<?php
// resources/views/pages/transaksi/rj/emr-rj/pemeriksaan/penunjang/radiologi/rm-radiologi-rj-actions.blade.php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Support\NomorRadiologi;
use App\Support\OrderRadiologiGanda;

new class extends Component {
    use WithPagination, WithRenderVersioningTrait, WithValidationToastTrait, EmrRJTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['radiologi-order-modal'];

    /* =======================
     | Props dari parent
     * ======================= */
    public string $rjNo = '';
    public bool $disabled = false;

    /* =======================
     | State Modal
     * ======================= */
    public string $searchItem = '';
    public array $selectedItems = []; // [ rad_id => [...item] ]
    public string $klinisDesc = ''; // Diagnosis/Keterangan Klinis — wajib diisi
    public string $cito = '0'; // '1' = CITO (didahulukan petugas radiologi), '0' = rutin

    // Pemeriksaan yang sudah pernah diorder di kunjungan ini. Terisi = petugas
    // SUDAH diperingatkan, jadi klik Kirim berikutnya diteruskan dengan sadar.
    // Direset tiap kali pilihan berubah supaya peringatannya tidak basi.
    public array $peringatanGanda = [];

    protected function rules(): array
    {
        return [
            'klinisDesc' => 'required|string',
        ];
    }

    protected function messages(): array
    {
        return [
            'klinisDesc.required' => 'Diagnosis/Keterangan Klinis harus diisi.',
        ];
    }

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(string $rjNo = '', bool $disabled = false): void
    {
        $this->rjNo = $rjNo;
        $this->disabled = $disabled;
        $this->registerAreas($this->renderAreas);
    }

    /* ===============================
     | OPEN / CLOSE MODAL
     =============================== */
    public function openModal(): void
    {
        if ($this->disabled) {
            return;
        }

        $this->selectedItems = [];
        $this->searchItem = '';
        $this->klinisDesc = '';
        $this->cito = '0';
        $this->peringatanGanda = [];
        $this->resetValidation();
        $this->resetPage();
        $this->incrementVersion('radiologi-order-modal');

        $this->dispatch('open-modal', name: "radiologi-order-rj-{$this->rjNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "radiologi-order-rj-{$this->rjNo}");
        $this->reset(['selectedItems', 'searchItem', 'klinisDesc', 'cito', 'peringatanGanda']);
    }

    /* ===============================
     | QUERY ITEM RADIOLOGI (paginated)
     =============================== */
    #[Computed]
    public function items()
    {
        $search = trim($this->searchItem);

        return DB::table('rsmst_radiologis')->select('rad_id', 'rad_desc', 'rad_price')->whereNotNull('rad_desc')->when($search, fn($query) => $query->whereRaw('UPPER(rad_desc) LIKE ?', ['%' . mb_strtoupper($search) . '%']))->orderBy('rad_desc', 'asc')->paginate(15);
    }

    /* ===============================
     | TOGGLE / REMOVE SELECTED ITEM
     =============================== */
    public function toggleItem(string $id, string $desc, ?float $price): void
    {
        $this->peringatanGanda = [];

        if (isset($this->selectedItems[$id])) {
            unset($this->selectedItems[$id]);
        } else {
            $this->selectedItems[$id] = [
                'rad_id' => $id,
                'rad_desc' => $desc,
                'rad_price' => $price,
            ];
        }
    }

    public function isSelected(string $id): bool
    {
        return isset($this->selectedItems[$id]);
    }

    public function removeSelected(string $id): void
    {
        $this->peringatanGanda = [];
        unset($this->selectedItems[$id]);
    }

    /* ===============================
     | KIRIM ORDER RADIOLOGI
     =============================== */
    public function kirimRadiologi(): void
    {
        // 1. Guard: tidak ada item dipilih
        if (empty($this->selectedItems)) {
            $this->dispatch('toast', type: 'warning', message: 'Pilih minimal satu item pemeriksaan.');
            return;
        }

        // 2. Guard: Diagnosis/Keterangan Klinis wajib diisi (rules + toast)
        $this->klinisDesc = trim($this->klinisDesc);
        $this->validateWithToast();

        // 3. Guard: pasien sudah pulang
        if ($this->checkRJStatus($this->rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, tidak dapat menambah pemeriksaan.');
            return;
        }

        // 4. Ambil reg_no & dr_id
        $rjData = $this->getRjData();
        if (!$rjData) {
            $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
            return;
        }

        // 5. Guard order ganda — PERINGATAN, bukan larangan: foto ulang & kontrol
        //    memang sah. Klik pertama menahan dan menampilkan apa yang sudah ada,
        //    klik kedua (peringatanGanda sudah terisi) diteruskan.
        $sudahDiperingatkan = !empty($this->peringatanGanda);
        if (!$sudahDiperingatkan) {
            $ganda = OrderRadiologiGanda::cari('RJ', $this->rjNo, array_keys($this->selectedItems));
            if (!empty($ganda)) {
                $this->peringatanGanda = OrderRadiologiGanda::kelompokkan($ganda);
                $this->dispatch('toast', type: 'warning', message: OrderRadiologiGanda::ringkas($ganda) . ' Klik "Tetap Kirim" bila memang foto ulang.');
                return;
            }
        }

        // Resolve nama dokter pengirim dari dr_id kunjungan RJ (dokter poli)
        $drPengirimName = DB::table('rsmst_doctors')->where('dr_id', $rjData->dr_id)->value('dr_name');

        try {
            DB::transaction(function () use ($drPengirimName, $sudahDiperingatkan) {
                $now = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

                // Lapis kedua: cek ulang DI DALAM transaksi. Cek di atas berjalan di
                // request terpisah, jadi dua klik/tab yang beradu masih bisa lolos.
                // Dilewati kalau petugas memang sudah memilih meneruskan.
                if (!$sudahDiperingatkan) {
                    $gandaKunci = OrderRadiologiGanda::cari('RJ', $this->rjNo, array_keys($this->selectedItems));
                    if (!empty($gandaKunci)) {
                        throw new \RuntimeException('Order Radiologi Ganda — ' . OrderRadiologiGanda::ringkas($gandaKunci));
                    }
                }

                foreach ($this->selectedItems as $item) {
                    $radDtlNo = DB::scalar('SELECT NVL(MAX(TO_NUMBER(rad_dtl)) + 1, 1) FROM rstxn_rjrads');

                    DB::table('rstxn_rjrads')->insert([
                        'rad_dtl' => $radDtlNo,
                        'rad_id' => $item['rad_id'],
                        'rj_no' => $this->rjNo,
                        'rad_price' => $item['rad_price'],
                        'dr_pengirim' => $drPengirimName,
                        'dr_radiologi' => 'dr. M.A. Budi Purwito, Sp.Rad.',
                        'klinis_desc' => trim($this->klinisDesc),
                        'cito_status' => $this->cito === '1' ? '1' : '0',
                        'waktu_entry' => DB::raw("TO_DATE('{$now}','dd/mm/yyyy hh24:mi:ss')"),
                        'radnum_no' => NomorRadiologi::generate(),
                    ]);
                }

                $this->appendAdminLogRJ((int) $this->rjNo, 'Order Radiologi' . ($this->cito === '1' ? ' [CITO]' : '') . ($sudahDiperingatkan ? ' [ULANG]' : '') . ' — ' . collect($this->selectedItems)->pluck('rad_desc')->implode(', '), 'MR');
            });

            $this->dispatch('radiologi-order-terkirim');
            $this->dispatch('toast', type: 'success', message: count($this->selectedItems) . ' item radiologi berhasil dikirim' . ($this->cito === '1' ? ' dengan status CITO.' : '.'));
            $this->closeModal();
        } catch (\RuntimeException $exception) {
            $this->dispatch('toast', type: 'error', message: $exception->getMessage());
        } catch (\Exception $exception) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengirim: ' . $exception->getMessage());
        }
    }

    /* ===============================
     | HELPERS
     =============================== */

    /**
     * Ambil reg_no & dr_id dari DB.
     */
    private function getRjData(): ?object
    {
        return DB::table('rstxn_rjhdrs')->select('reg_no', 'dr_id')->where('rj_no', $this->rjNo)->first();
    }
};
?>

<div>
    <div class="mb-3">
        {{-- Tombol trigger --}}
        <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled" wire:target="openModal"
            :disabled="$disabled">
            <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Order Radiologi
            </span>
            <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                <x-loading /> Memuat...
            </span>
        </x-primary-button>
    </div>

    {{-- Modal Order Radiologi --}}
    <x-modal name="radiologi-order-rj-{{ $rjNo }}" size="full" height="full"
        focusable>
        <div class="flex flex-col h-full"
            wire:key="{{ $this->renderKey('radiologi-order-modal', [$rjNo ?: 'empty']) }}">

            {{-- Modal Header --}}
            <div class="relative px-6 py-4 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.05]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-blue/10 dark:bg-brand-blue/15">
                            <svg class="w-5 h-5 text-brand-blue dark:text-brand-blue" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-ink dark:text-gray-100">
                                Order Pemeriksaan Radiologi
                            </h2>
                            <p class="text-sm text-muted">No. RJ: <span
                                    class="font-mono font-medium">{{ $rjNo }}</span></p>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- Display Pasien --}}
            <div class="border-b border-hairline dark:border-gray-700">
                <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                    wire:key="display-pasien-rj-{{ $rjNo }}" />
            </div>

            {{-- Body: dua kolom — KIRI pilih item, KANAN diagnosis + keranjang --}}
            <div class="flex flex-col flex-1 min-h-0 lg:flex-row">

                {{-- KIRI: Search + Item Grid --}}
                <div class="flex flex-col flex-1 min-h-0">

                    {{-- Search --}}
                    <div class="px-6 py-3 border-b border-hairline-soft dark:border-gray-700">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-muted-soft" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input type="text" wire:model.live.debounce.300ms="searchItem"
                                placeholder="Cari item pemeriksaan radiologi..."
                                class="w-full py-2 pl-10 pr-4 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-blue/30 focus:border-brand-blue dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100" />
                        </div>
                    </div>

                    {{-- Item Grid --}}
                    <div class="flex-1 p-5 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4">
                            @forelse ($this->items as $item)
                                @php $selected = $this->isSelected($item->rad_id); @endphp
                                <button type="button"
                                    wire:click="toggleItem('{{ $item->rad_id }}', '{{ addslashes($item->rad_desc) }}', {{ $item->rad_price ?? 'null' }})"
                                    class="relative flex flex-col items-center justify-center p-3 rounded-xl border-2 text-center transition-all
                                        {{ $selected
                                            ? 'border-brand-blue bg-brand-blue/10 text-brand-blue shadow-sm'
                                            : 'border-hairline bg-canvas hover:border-brand-blue/40 hover:bg-brand-blue/5 text-body dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300' }}">

                                    {{-- Checkmark --}}
                                    @if ($selected)
                                        <span
                                            class="absolute top-1.5 right-1.5 flex items-center justify-center w-4 h-4 bg-brand-blue rounded-full">
                                            <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                                    d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                    @endif

                                    {{-- Icon Xray --}}
                                    <svg class="w-6 h-6 mb-1.5 {{ $selected ? 'text-brand-blue' : 'text-gray-300 dark:text-gray-600' }}"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                                    </svg>

                                    <p class="text-sm font-medium leading-tight">{{ $item->rad_desc }}</p>

                                    @if ($item->rad_price)
                                        <p
                                            class="mt-1 text-[10px] {{ $selected ? 'text-brand-blue/70' : 'text-muted-soft' }}">
                                            {{ number_format($item->rad_price) }}
                                        </p>
                                    @endif
                                </button>
                            @empty
                                <div class="py-12 text-center text-muted-soft col-span-full">
                                    <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                                    </svg>
                                    <p class="text-base">Tidak ada item ditemukan</p>
                                </div>
                            @endforelse
                        </div>

                        {{-- Pagination --}}
                        @if ($this->items->hasPages())
                            <div class="mt-4">
                                {{ $this->items->links() }}
                            </div>
                        @endif
                    </div>
                </div>

                {{-- KANAN: Diagnosis + Keranjang item dipilih --}}
                <div
                    class="flex flex-col w-full min-h-0 border-t lg:w-96 shrink-0 lg:border-t-0 lg:border-l border-hairline dark:border-gray-700 bg-canvas dark:bg-gray-900">

                    {{-- Prioritas: CITO --}}
                    <div class="px-5 py-3 border-b border-hairline-soft dark:border-gray-700">
                        <x-input-label value="Prioritas Pemeriksaan" />
                        <div class="mt-1.5">
                            <x-toggle wire:model="cito" trueValue="1" falseValue="0" onColor="bg-error"
                                label="CITO — didahulukan" />
                        </div>
                        @if ($cito === '1')
                            <p class="mt-1.5 text-xs font-medium text-error-deep dark:text-red-300">
                                Order ditandai CITO — petugas radiologi akan mendahulukan pemeriksaan ini.
                            </p>
                        @endif
                    </div>

                    {{-- Diagnosis/Keterangan Klinis --}}
                    <div class="px-5 py-3 border-b border-hairline-soft dark:border-gray-700">
                        <x-input-label value="Diagnosis/Keterangan Klinis" required />
                        <textarea wire:model="klinisDesc" rows="2"
                            placeholder="Diagnosis kerja / keterangan klinis pasien..."
                            class="w-full mt-1 text-sm border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-brand-blue/30 focus:border-brand-blue dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100"></textarea>
                        <x-input-error :messages="$errors->get('klinisDesc')" class="mt-1" />
                    </div>

                    {{-- Header keranjang --}}
                    <div class="flex items-center justify-between px-5 pt-3 pb-1.5">
                        <p class="text-sm font-semibold text-ink dark:text-gray-100">Item Dipilih</p>
                        @if (!empty($selectedItems))
                            <span
                                class="px-2 py-0.5 text-xs font-semibold text-brand-blue bg-brand-blue/10 border border-brand-blue/30 rounded-full">
                                {{ count($selectedItems) }}
                            </span>
                        @endif
                    </div>

                    {{-- List item dipilih (keranjang) --}}
                    <div class="flex-1 px-5 pb-4 space-y-1.5 overflow-y-auto">
                        @forelse ($selectedItems as $radId => $itemDipilih)
                            <div
                                class="flex items-start justify-between gap-2 p-2.5 border rounded-lg border-brand-blue/20 bg-brand-blue/5">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium leading-tight text-brand-blue">
                                        {{ $itemDipilih['rad_desc'] }}</p>
                                    @if ($itemDipilih['rad_price'])
                                        <p class="mt-0.5 text-[11px] text-brand-blue/60">
                                            {{ number_format($itemDipilih['rad_price']) }}</p>
                                    @endif
                                </div>
                                <button type="button" wire:click="removeSelected('{{ $radId }}')"
                                    class="mt-0.5 shrink-0 text-muted-soft hover:text-red-500 transition-colors">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        @empty
                            <div
                                class="flex flex-col items-center justify-center h-full py-10 text-center text-muted-soft">
                                <svg class="w-10 h-10 mb-2 text-gray-300" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                                </svg>
                                <p class="text-sm font-medium">Belum ada item dipilih</p>
                                <p class="mt-0.5 text-xs text-muted-soft">Klik item di kiri untuk memilih</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Modal Footer --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                {{-- Peringatan order ganda — muncul setelah klik Kirim pertama.
                     Bukan larangan: tombolnya berubah jadi "Tetap Kirim". --}}
                @if (!empty($peringatanGanda))
                    <div
                        class="p-3 mb-3 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600">
                        <div class="flex items-start gap-2">
                            <svg class="w-4 h-4 mt-0.5 shrink-0 text-amber-600 dark:text-amber-400" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0l-7.1 12.25A2 2 0 004.99 19z" />
                            </svg>
                            <div class="text-sm text-amber-800 dark:text-amber-200">
                                <div class="font-semibold">Sudah pernah diorder di kunjungan ini</div>
                                <ul class="mt-1 space-y-0.5">
                                    @foreach ($peringatanGanda as $ganda)
                                        <li>
                                            &bull; {{ $ganda['rad_desc'] }}
                                            @if ($ganda['jumlah'] > 1)
                                                <span class="font-semibold">({{ $ganda['jumlah'] }}&times;)</span>
                                            @endif
                                            @if ($ganda['waktu'] !== '')
                                                <span class="text-amber-700 dark:text-amber-300">—
                                                    {{ $ganda['waktu'] }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                                <div class="mt-1.5 text-amber-700 dark:text-amber-300">
                                    Kalau ini memang foto ulang atau kontrol, lanjutkan dengan
                                    <span class="font-semibold">Tetap Kirim</span>. Kalau tidak, batalkan pilihannya.
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="flex items-center justify-between gap-3">

                    {{-- Kiri: info --}}
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($cito === '1')
                            <span
                                class="inline-flex items-center gap-1.5 px-3 py-1 text-base font-bold text-red-700 bg-red-50 border border-red-300 rounded-full dark:bg-red-900/25 dark:border-red-500 dark:text-red-200">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                CITO
                            </span>
                        @endif
                        @if (!empty($selectedItems))
                            <span
                                class="inline-flex items-center gap-1.5 px-3 py-1 text-base font-medium text-brand-blue bg-brand-blue/10 border border-brand-blue/30 rounded-full">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                                {{ count($selectedItems) }} item dipilih
                            </span>
                        @else
                            <span class="text-sm italic text-muted-soft">Klik item untuk memilih pemeriksaan</span>
                        @endif
                    </div>

                    {{-- Kanan: buttons --}}
                    <div class="flex items-center gap-3">
                        <x-secondary-button wire:click="closeModal">
                            Batal
                        </x-secondary-button>

                        @if (!empty($selectedItems))
                            <x-primary-button type="button" wire:click="kirimRadiologi" wire:loading.attr="disabled"
                                wire:target="kirimRadiologi">
                                <span wire:loading.remove wire:target="kirimRadiologi"
                                    class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                    </svg>
                                    {{ !empty($peringatanGanda) ? 'Tetap Kirim' : 'Kirim Order' }}
                                </span>
                                <span wire:loading wire:target="kirimRadiologi" class="flex items-center gap-1.5">
                                    <x-loading /> Mengirim...
                                </span>
                            </x-primary-button>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
