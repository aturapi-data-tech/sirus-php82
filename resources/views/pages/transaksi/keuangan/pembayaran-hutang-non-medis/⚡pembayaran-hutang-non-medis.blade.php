<?php

/**
 * Pembayaran Hutang Non-Medis (Supplier).
 *
 * Equivalent dgn Oracle Forms procedure post_transaksi_angsuran (sirus):
 *   - Pilih supplier
 *   - List nota non-medis dengan rcv_status='H' (hutang)
 *   - Centang nota mana yg akan dilunasi via toggle (check_boxstatus='1')
 *   - Klik "Proses Pembayaran" → modal:
 *       * 1x INSERT IMTXN_CASHOUTHDRS (master cashout)
 *       * Per rcv yg dicentang (FIFO oldest first):
 *           - INSERT IMTXN_RECEIVEPAYMENTS
 *           - INSERT IMTXN_CASHOUTDTLS
 *           - UPDATE IMTXN_RECEIVEHDRS rcv_status='L' (kalau lunas penuh)
 */

use Livewire\Component;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Authorize;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Support\Keuangan\SaldoDpSupplier;

new class extends Component {
    #[Locked] // kunci record — tak boleh diubah dari klien (celah ID)
    public ?string $suppId = null;
    public ?array $supplier = null;

    /* ── LOV Listener ── */
    #[On('lov.selected.hutang-non-medis-supplier')]
    public function onSupplierSelected(string $target, ?array $payload): void
    {
        $this->suppId   = $payload['supp_id'] ?? null;
        $this->supplier = $payload;
    }

    public function clearSupplier(): void
    {
        $this->reset(['suppId', 'supplier']);
        unset($this->rcvList, $this->summary, $this->saldoDp);
    }

    #[On('hutang-non-medis.paid')]
    public function refreshAfterPaid(): void
    {
        // Auto-clear supplier setelah bayar sukses → user balik ke fresh state.
        // Kalau ada cicilan tersisa, user tinggal search supplier yg sama lagi.
        $this->reset(['suppId', 'supplier']);
        unset($this->rcvList, $this->summary, $this->saldoDp);
    }

    /** Hapus salah bayar di modal → angka daftar disegarkan, supplier tetap. */
    #[On('hutang-non-medis.refresh')]
    public function segarkanDaftar(): void
    {
        unset($this->rcvList, $this->summary, $this->saldoDp);
    }

    /* ── List rcv hutang utk supplier terpilih ── */
    #[Computed]
    public function rcvList()
    {
        if (!$this->suppId) return collect();

        $headers = DB::table('imtxn_receivehdrsnon')
            ->select([
                'rcv_no',
                'supp_id',
                DB::raw("to_char(rcv_date,'dd/mm/yyyy') as rcv_date_display"),
                'rcv_date',
                DB::raw("to_char(due_date,'dd/mm/yyyy') as due_date_display"),
                'rcv_desc',
                DB::raw('NVL(rcv_diskon,0) as rcv_diskon'),
                DB::raw('NVL(rcv_ppn,0) as rcv_ppn'),
                DB::raw("NVL(rcv_ppn_status,'1') as rcv_ppn_status"),
                DB::raw('NVL(rcv_materai,0) as rcv_materai'),
                'rcv_status',
                'check_boxstatus',
                'vcount',
            ])
            ->where('supp_id', $this->suppId)
            ->where('rcv_status', 'H')
            // urut: yg dipilih dulu (vcount asc), lalu yg belum dipilih (NULL last) urut tanggal beli
            ->orderByRaw('vcount NULLS LAST')
            ->orderBy('rcv_date')
            ->orderBy('rcv_no')
            ->get();

        if ($headers->isEmpty()) return collect();

        $rcvNos = $headers->pluck('rcv_no')->all();

        // Total per rcv (sum detail dgn diskon kaskade)
        $totalDetailMap = DB::table('imtxn_receivedtlsnon')
            ->select('rcv_no', DB::raw("
                NVL(SUM(
                    (NVL(qty,0)*NVL(cost_price,0))
                    - ((NVL(qty,0)*NVL(cost_price,0)) * NVL(dtl_persen,0)/100)
                    - NVL(dtl_diskon,0)
                    - (((NVL(qty,0)*NVL(cost_price,0))
                        - ((NVL(qty,0)*NVL(cost_price,0)) * NVL(dtl_persen,0)/100)
                        - NVL(dtl_diskon,0))
                      * NVL(dtl_persen1,0)/100)
                    - NVL(dtl_diskon1,0)
                ),0) as total_detail"))
            ->whereIn('rcv_no', $rcvNos)
            ->groupBy('rcv_no')
            ->pluck('total_detail', 'rcv_no');

        // Total titipan (rcvpayments) per rcv
        $titipanMap = DB::table('imtxn_receivepaymentsnon')
            ->select('rcv_no', DB::raw('NVL(SUM(rcvp_value),0) as titipan'))
            ->whereIn('rcv_no', $rcvNos)
            ->groupBy('rcv_no')
            ->pluck('titipan', 'rcv_no');

        $autoFinalize = []; // rcv_no yg sisanya 0 → auto-finalize ke 'L'

        $rows = $headers->map(function ($r) use ($totalDetailMap, $titipanMap, &$autoFinalize) {
            $totalDetail   = (float) ($totalDetailMap[$r->rcv_no] ?? 0);
            $setelahDiskon = $totalDetail - (float) $r->rcv_diskon;
            $ppn           = ((string) $r->rcv_ppn_status) === '1'
                ? ($setelahDiskon * (float) $r->rcv_ppn) / 100
                : 0;
            // Cast ke int rupiah supaya konsisten dgn save logic & cegah sub-1 rupiah nyangkut di 'H'.
            $grandTotal = (int) round($setelahDiskon + $ppn + (float) $r->rcv_materai);
            $titipan    = (int) round((float) ($titipanMap[$r->rcv_no] ?? 0));
            $sisa       = $grandTotal - $titipan;

            if ($sisa <= 0) {
                $autoFinalize[] = $r->rcv_no;
            }

            return (object) [
                'rcv_no'           => $r->rcv_no,
                'rcv_date_display' => $r->rcv_date_display,
                'due_date_display' => $r->due_date_display,
                'rcv_desc'         => $r->rcv_desc,
                'total'            => $grandTotal,
                'titipan'          => $titipan,
                'sisa'             => $sisa,
                'check_boxstatus'  => (string) ($r->check_boxstatus ?? ''),
                'is_checked'       => ((string) ($r->check_boxstatus ?? '')) === '1',
                'vcount'           => $r->vcount !== null ? (int) $r->vcount : null,
            ];
        });

        // Auto-finalize: nota dgn sisa ≤ 0 di-promote ke 'L' & dikeluarkan dari list
        if (!empty($autoFinalize)) {
            DB::table('imtxn_receivehdrsnon')
                ->whereIn('rcv_no', $autoFinalize)
                ->update([
                    'rcv_status'      => 'L',
                    'check_boxstatus' => '0',
                    'vcount'          => null,
                ]);
            $rows = $rows->reject(fn ($r) => in_array($r->rcv_no, $autoFinalize));
        }

        return $rows->values();
    }

    /* ── Toggle check_boxstatus per row ──
     * Mirror Oracle Forms: ON  → check_boxstatus='1' + vcount=NVL(MAX(vcount)+1,1)
     *                      OFF → check_boxstatus='0' + vcount=NULL
     * vcount = urutan klik user, jadi user bisa pilih NOTA MANA YG DILUNASI DULUAN.
     */
    // Pilihan nota disimpan ke DB (check_boxstatus/vcount) = bagian alur bayar → hak Tambah.
    #[Authorize('keuangan.pembayaran-hutang-non-medis.tambah')]
    public function toggleCheckBox(int $rcvNo): void
    {
        $row = DB::table('imtxn_receivehdrsnon')
            ->where('rcv_no', $rcvNo)
            ->where('supp_id', $this->suppId)
            ->where('rcv_status', 'H')
            ->first();

        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Nota tidak valid.');
            return;
        }

        $isCurrentlyChecked = ((string) ($row->check_boxstatus ?? '')) === '1';

        if (!$isCurrentlyChecked) {
            // ON → vcount = max(vcount)+1 di antara yg dichecklist utk supplier ini
            $nextVcount = (int) DB::table('imtxn_receivehdrsnon')
                ->where('supp_id', $this->suppId)
                ->where('check_boxstatus', '1')
                ->max('vcount');
            $nextVcount = $nextVcount + 1;

            DB::table('imtxn_receivehdrsnon')
                ->where('rcv_no', $rcvNo)
                ->update(['check_boxstatus' => '1', 'vcount' => $nextVcount]);
        } else {
            // OFF → reset
            DB::table('imtxn_receivehdrsnon')
                ->where('rcv_no', $rcvNo)
                ->update(['check_boxstatus' => '0', 'vcount' => null]);
        }

        unset($this->rcvList, $this->summary, $this->saldoDp);
    }

    /**
     * Toggle all: kalau semua sudah dipilih → unset semua.
     * Kalau ada yg belum → set semua sesuai urutan tanggal beli (FIFO default),
     * vcount auto-increment 1..N, supaya alokasi tetap jalan saat user nggak peduli urutan.
     */
    // Pilihan nota disimpan ke DB (check_boxstatus/vcount) = bagian alur bayar → hak Tambah.
    #[Authorize('keuangan.pembayaran-hutang-non-medis.tambah')]
    public function toggleCheckBoxAll(): void
    {
        if (!$this->suppId) return;

        $allChecked = $this->rcvList->isNotEmpty() && $this->rcvList->every(fn ($r) => $r->is_checked);

        if ($allChecked) {
            // Reset semua
            DB::table('imtxn_receivehdrsnon')
                ->where('supp_id', $this->suppId)
                ->where('rcv_status', 'H')
                ->update(['check_boxstatus' => '0', 'vcount' => null]);
        } else {
            // Set semua, vcount sesuai urut tanggal beli
            $rcvs = DB::table('imtxn_receivehdrsnon')
                ->select('rcv_no')
                ->where('supp_id', $this->suppId)
                ->where('rcv_status', 'H')
                ->orderBy('rcv_date')
                ->orderBy('rcv_no')
                ->pluck('rcv_no');

            DB::transaction(function () use ($rcvs) {
                $i = 1;
                foreach ($rcvs as $rcvNo) {
                    DB::table('imtxn_receivehdrsnon')
                        ->where('rcv_no', $rcvNo)
                        ->update(['check_boxstatus' => '1', 'vcount' => $i++]);
                }
            });
        }

        unset($this->rcvList, $this->summary, $this->saldoDp);
    }

    #[Computed]
    public function summary(): array
    {
        $list = $this->rcvList;
        $checked = $list->where('is_checked', true);
        return [
            'jumlah'         => $list->count(),
            'jumlah_checked' => $checked->count(),
            'tottotal'       => (float) $list->sum('total'),
            'tottitipan'     => (float) $list->sum('titipan'),
            'totsisa'        => (float) $list->sum('sisa'),
            'sisa_checked'   => (float) $checked->sum('sisa'),
        ];
    }

    /** Saldo DP supplier — satu rumus (SaldoDpSupplier: saldo awal + setor − terpakai ± opname). */
    #[Computed]
    public function saldoDp(): int
    {
        return $this->suppId ? SaldoDpSupplier::saldo(SaldoDpSupplier::JENIS_NON_MEDIS, $this->suppId) : 0;
    }

    /* ── Trigger ke modal ── */
    public function openProsesBayar(): void
    {
        if (!$this->suppId) {
            $this->dispatch('toast', type: 'error', message: 'Pilih supplier terlebih dahulu.');
            return;
        }
        if ($this->summary['jumlah'] === 0) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada hutang untuk supplier ini.');
            return;
        }
        if ($this->summary['jumlah_checked'] === 0) {
            $this->dispatch('toast', type: 'warning', message: 'Centang minimal 1 nota yang akan dibayar.');
            return;
        }
        if ($this->summary['sisa_checked'] <= 0) {
            $this->dispatch('toast', type: 'warning', message: 'Sisa hutang nota terpilih sudah Rp 0.');
            return;
        }

        $this->dispatch('hutang-non-medis.openProsesBayar',
            suppId: $this->suppId,
            suppName: $this->supplier['supp_name'] ?? '',
            sisaChecked: $this->summary['sisa_checked'],
            jumlah: $this->summary['jumlah_checked'],
        );
    }
};
?>

<div>
    <x-page-title
        title="Pembayaran Hutang Non-Medis"
        subtitle="Pelunasan / angsuran hutang ke supplier barang non-medis (ATK, alat tulis, dll)" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- DUA KOLOM (pola Pembayaran Piutang Per Pasien): KIRI = supplier + ringkasan + Proses;
                 KANAN = daftar nota hutang supplier terpilih. --}}
            <div class="flex flex-col flex-1 min-h-0 gap-4 mt-2 lg:flex-row">

                {{-- KOLOM KIRI --}}
                <div class="flex flex-col gap-5 p-5 overflow-y-auto border shadow-sm shrink-0 lg:w-96 bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                    <div>
                        @if ($suppId)
                            <div class="flex items-start justify-between gap-2">
                                <x-input-label value="Supplier" />
                                <x-secondary-button type="button" wire:click="clearSupplier"
                                    title="Ganti supplier / mulai transaksi baru"><x-ikon nama="ubah" /> Ganti</x-secondary-button>
                            </div>
                            <div class="px-4 py-3 mt-2 border rounded-xl border-hairline bg-surface-soft dark:bg-gray-800 dark:border-gray-700">
                                <div class="font-semibold text-ink dark:text-gray-100">{{ $supplier['supp_name'] ?? '-' }}</div>
                                <div class="font-mono text-xs text-muted dark:text-gray-400">Kode {{ $suppId }}</div>
                                @if (filled($supplier['phone'] ?? null))
                                    <div class="text-xs text-muted dark:text-gray-400">Telp {{ $supplier['phone'] }}</div>
                                @endif
                                @if (filled($supplier['address'] ?? null))
                                    <div class="text-xs text-muted dark:text-gray-400">{{ $supplier['address'] }}</div>
                                @endif
                            </div>
                        @else
                            <livewire:lov.supplier.lov-supplier
                                target="hutang-non-medis-supplier"
                                label="Cari Supplier"
                                jenisSupplier="nonmedis"
                                placeholder="Ketik kode / nama / telp supplier..."
                                wire:key="lov-supplier-hutang-non-medis" />
                        @endif
                    </div>

                    {{-- Ringkasan + Proses: langsung di bawah supplier supaya tak terpotong layar --}}
                    <div class="pt-4 space-y-3 border-t shrink-0 border-hairline dark:border-gray-700">
                        @if ($suppId)
                            <div class="space-y-1.5 text-sm">
                                <div class="flex justify-between gap-3">
                                    <span class="text-muted dark:text-gray-400">Jumlah nota</span>
                                    <span class="font-medium text-ink dark:text-gray-100">{{ $this->summary['jumlah'] }}</span>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <span class="text-muted dark:text-gray-400">Total tagihan</span>
                                    <span class="font-medium text-body dark:text-gray-300">Rp {{ number_format($this->summary['tottotal'], 0, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <span class="text-muted dark:text-gray-400">Sudah dibayar (titipan)</span>
                                    <span class="font-medium text-emerald-700 dark:text-emerald-400">Rp {{ number_format($this->summary['tottitipan'], 0, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <span class="text-muted dark:text-gray-400">Saldo DP (uang muka)</span>
                                    <span class="font-medium text-brand-green dark:text-brand-lime">Rp {{ number_format($this->saldoDp, 0, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between gap-3 pt-1.5 border-t border-hairline dark:border-gray-700">
                                    <span class="font-semibold text-rose-700 dark:text-rose-300">Sisa hutang</span>
                                    <span class="font-bold text-rose-700 dark:text-rose-300">Rp {{ number_format($this->summary['totsisa'], 0, ',', '.') }}</span>
                                </div>
                            </div>
                        @endif

                        <div class="px-4 py-3 border rounded-xl bg-brand-green/5 border-brand-green/25 dark:bg-brand-lime/10 dark:border-brand-lime/25">
                            <div class="text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">
                                Terpilih {{ $this->summary['jumlah_checked'] }} Nota
                            </div>
                            <div class="mt-1 text-2xl font-bold leading-none text-brand-green dark:text-brand-lime">
                                Rp {{ number_format($this->summary['sisa_checked'], 0, ',', '.') }}
                            </div>
                        </div>

                        <x-primary-button type="button" wire:click="openProsesBayar" class="justify-center w-full h-11"
                            wire:loading.attr="disabled" wire:target="openProsesBayar">
                            <x-ikon nama="proses" />
                            Proses Pembayaran
                        </x-primary-button>

                        {{-- PANDUAN (biru-info, collapsible, default tertutup) --}}
                        <div x-data="{ open: false }"
                            class="overflow-hidden border shrink-0 rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
                            <button type="button" @click="open = !open"
                                class="flex items-center justify-between w-full px-3 py-2 text-sm font-semibold text-blue-900 transition-colors hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Cara Pakai — Pembayaran Hutang
                                </span>
                                <svg class="w-4 h-4 text-blue-600 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div x-show="open" x-collapse class="px-3 pb-3 text-xs text-blue-900 dark:text-blue-200">
                                <ul class="ml-4 space-y-1 list-disc">
                                    <li>Pilih supplier → nota hutangnya tampil di kanan. Centang nota dengan saklar; <strong>urutan klik = urutan bayar</strong> (angka di samping saklar).</li>
                                    <li>Klik <strong>Proses Pembayaran</strong> → pilih <strong>Sumber Dana</strong>: Kas/Transfer (pilih akun kas) atau <strong>Saldo DP</strong> (uang muka dari menu Topup Supplier Non-Medis). Saldo DP dipakai sendirian — kalau kurang, sisanya dibayar kas di transaksi berikutnya.</li>
                                    <li>Nominal boleh lebih kecil dari sisa (angsuran); nota terakhir menjadi cicilan. Nominal melebihi sisa / saldo DP langsung merah.</li>
                                    <li>Shift terisi otomatis dari jam Tanggal Pembayaran — periksa sebelum proses. Dialog konfirmasi menampilkan nota mana yang LUNAS.</li>
                                    <li>Di modal, klik baris nota untuk melihat <strong>riwayat pembayarannya</strong>.</li>
                                    <li><strong>Hapus salah bayar</strong> (sesuai hak di Role Control, tahun berjalan): menghapus satu pembayaran UTUH, termasuk nota lain yang ikut dibayar di transaksi itu. Kas (atau saldo DP) kembali, nota kembali menjadi hutang. Angsuran awal tidak dihapus di sini — batalkan posting di menu Barang Non-Medis dari Supplier.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- KOLOM KANAN — daftar nota hutang --}}
                <div class="flex flex-col flex-1 min-w-0 min-h-0 border shadow-sm bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    @php $allChecked = $this->rcvList->isNotEmpty() && $this->rcvList->every(fn ($r) => $r->is_checked); @endphp
                    <div class="flex items-center justify-between gap-3 px-5 py-3 border-b border-hairline dark:border-gray-700">
                        <div class="text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-300">
                            Nota Hutang
                            @if ($suppId)
                                <span class="font-normal normal-case">({{ $this->summary['jumlah_checked'] }} dari {{ $this->summary['jumlah'] }} dipilih)</span>
                            @endif
                        </div>
                        @if ($suppId)
                            <x-toggle :current="$allChecked ? '1' : '0'" trueValue="1" falseValue="0"
                                wireClick="toggleCheckBoxAll" label="Centang semua nota"
                                title="Nyala = centang semua nota (urut tanggal beli), mati = kosongkan pilihan" />
                        @endif
                    </div>

                    <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto">
                        <table class="w-full min-w-full text-sm">
                            <thead class="sticky top-0 z-10 text-left text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-300">
                                <tr class="text-xs font-semibold tracking-wide uppercase">
                                    <th class="w-28 px-4 py-3">Pilih</th>
                                    <th class="px-4 py-3">No Nota</th>
                                    <th class="px-4 py-3">Tgl Beli</th>
                                    <th class="px-4 py-3">Jatuh Tempo</th>
                                    <th class="px-4 py-3">Keterangan</th>
                                    <th class="px-4 py-3 text-right">Total</th>
                                    <th class="px-4 py-3 text-right">Titipan</th>
                                    <th class="px-4 py-3 text-right">Sisa</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-body divide-hairline dark:divide-gray-700 dark:text-gray-200">
                                @forelse ($this->rcvList as $row)
                                    <tr wire:key="hutang-non-medis-{{ $row->rcv_no }}"
                                        class="transition {{ $row->is_checked ? 'bg-brand-green/5 dark:bg-brand-lime/10' : 'hover:bg-surface-soft dark:hover:bg-gray-800/60' }}">
                                        <td class="px-4 py-2.5">
                                            <div class="flex items-center gap-2">
                                                <x-toggle :current="$row->is_checked ? '1' : '0'" trueValue="1" falseValue="0"
                                                    wireClick="toggleCheckBox({{ $row->rcv_no }})"
                                                    title="Pilih nota ini untuk dibayar" />
                                                @if ($row->is_checked && $row->vcount !== null)
                                                    <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white rounded-full bg-brand-green dark:bg-brand-lime dark:text-gray-900"
                                                        title="Urutan bayar">{{ $row->vcount }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-2.5 font-mono whitespace-nowrap">{{ $row->rcv_no }}</td>
                                        <td class="px-4 py-2.5 whitespace-nowrap">{{ $row->rcv_date_display }}</td>
                                        <td class="px-4 py-2.5 whitespace-nowrap">
                                            @if ($row->due_date_display)
                                                <span class="text-amber-700 dark:text-amber-400">{{ $row->due_date_display }}</span>
                                            @else
                                                <span class="text-muted-soft">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5">{{ $row->rcv_desc ?? '-' }}</td>
                                        <td class="px-4 py-2.5 font-mono text-right whitespace-nowrap">{{ number_format($row->total, 0, ',', '.') }}</td>
                                        <td class="px-4 py-2.5 font-mono text-right text-emerald-700 dark:text-emerald-400 whitespace-nowrap">{{ number_format($row->titipan, 0, ',', '.') }}</td>
                                        <td class="px-4 py-2.5 font-mono font-bold text-right text-rose-700 dark:text-rose-300 whitespace-nowrap">{{ number_format($row->sisa, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-6 py-16 text-center text-muted dark:text-gray-400">
                                            <div class="flex flex-col items-center gap-2">
                                                <svg class="w-10 h-10 text-hairline" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>{{ $suppId ? 'Supplier ini tidak punya nota hutang.' : 'Pilih supplier dulu untuk melihat daftar hutangnya.' }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if ($this->rcvList->isNotEmpty())
                                <tfoot class="sticky bottom-0 z-10 font-semibold border-t text-body bg-surface-soft border-hairline dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700">
                                    <tr>
                                        <td colspan="5" class="px-4 py-3 text-right">TOTAL</td>
                                        <td class="px-4 py-3 font-mono text-right whitespace-nowrap">{{ number_format($this->summary['tottotal'], 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 font-mono text-right text-emerald-700 dark:text-emerald-400 whitespace-nowrap">{{ number_format($this->summary['tottitipan'], 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 font-mono text-right text-rose-700 dark:text-rose-300 whitespace-nowrap">{{ number_format($this->summary['totsisa'], 0, ',', '.') }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>

            {{-- Modal proses bayar --}}
            <livewire:pages::transaksi.keuangan.pembayaran-hutang-non-medis.pembayaran-hutang-non-medis-actions
                wire:key="pembayaran-hutang-non-medis-actions" />
        </div>
    </div>
</div>
