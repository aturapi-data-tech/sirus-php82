<?php

/**
 * Tutup Buku Hutang & DP Supplier — proses tahunan saldo awal per supplier.
 *
 * Menulis TKTXN_SALDOAWALHUTANGS[NON] tahun T+1 dari tahun T dengan rumus yang SAMA PERSIS
 * dengan tutup buku Forms 6i (App\Support\Keuangan\TutupBukuSupplier). Boleh dijalankan ulang:
 * baris yang sudah ada diperbarui, yang belum ada dibuat; angka lama dicatat di log.
 * Opname saldo DP tidak dimasukkan (tetap dihitung terpisah oleh SaldoDpSupplier).
 *
 * Khusus Admin. Tidak menyentuh saldo akun Neraca (tktxn_saldoawalakuns).
 */

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Support\Keuangan\SaldoDpSupplier;
use App\Support\Keuangan\TutupBukuSupplier;

new class extends Component {
    private const ROLE_TUTUP_BUKU = ['Admin'];

    public string $jenis = SaldoDpSupplier::JENIS_PBF;
    /** Tahun yang ditutup — menghasilkan saldo awal tahun berikutnya. */
    public int $tahun = 0;
    /** true = hanya supplier yang berubah / baru. */
    public bool $hanyaBerubah = false;
    public bool $konfirmasi = false;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(self::ROLE_TUTUP_BUKU), 403, 'Tutup buku supplier khusus Admin.');
        $this->tahun = (int) Carbon::now()->year - 1;
    }

    public function updatedJenis(): void
    {
        $this->jenis = $this->jenis === SaldoDpSupplier::JENIS_NON_MEDIS ? SaldoDpSupplier::JENIS_NON_MEDIS : SaldoDpSupplier::JENIS_PBF;
        $this->konfirmasi = false;
        unset($this->baris);
    }

    public function updatedTahun(): void
    {
        $this->tahun = max(2020, min((int) $this->tahun, (int) Carbon::now()->year));
        $this->konfirmasi = false;
        unset($this->baris);
    }

    /** Pratinjau per supplier + status terhadap baris tahun depan yang sudah ada. */
    #[Computed]
    public function baris(): array
    {
        return collect(TutupBukuSupplier::pratinjau($this->jenis, $this->tahun))->map(function ($row) {
            $row['status'] = !$row['ada']
                ? 'baru'
                : (TutupBukuSupplier::sama($row['hutangLama'], $row['hutangBaru']) && TutupBukuSupplier::sama($row['dpLama'], $row['dpBaru']) ? 'sama' : 'beda');

            return $row;
        })->all();
    }

    #[Computed]
    public function ringkasan(): array
    {
        $baris = collect($this->baris);

        return [
            'supplier' => $baris->count(),
            'hutangBaru' => $baris->sum('hutangBaru'),
            'dpBaru' => $baris->sum('dpBaru'),
            'sama' => $baris->where('status', 'sama')->count(),
            'beda' => $baris->where('status', 'beda')->count(),
            'baru' => $baris->where('status', 'baru')->count(),
        ];
    }

    public function siapkanProses(): void
    {
        if (!auth()->user()?->hasAnyRole(self::ROLE_TUTUP_BUKU)) {
            $this->dispatch('toast', type: 'error', message: 'Tutup buku supplier khusus Admin.');
            return;
        }
        if ($this->ringkasan['beda'] + $this->ringkasan['baru'] === 0) {
            $this->dispatch('toast', type: 'info', message: 'Saldo awal ' . ($this->tahun + 1) . ' sudah sesuai semua — tidak ada yang perlu ditulis.');
            return;
        }
        $this->konfirmasi = true;
    }

    public function batalProses(): void
    {
        $this->konfirmasi = false;
    }

    public function proses(): void
    {
        $this->konfirmasi = false;
        if (!auth()->user()?->hasAnyRole(self::ROLE_TUTUP_BUKU)) {
            $this->dispatch('toast', type: 'error', message: 'Tutup buku supplier khusus Admin.');
            return;
        }

        try {
            $hasil = TutupBukuSupplier::proses($this->jenis, $this->tahun);
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal tutup buku: ' . $e->getMessage());
            return;
        }

        // Angka lama dicatat sebelum hilang — satu-satunya jejak bila perlu dikembalikan.
        Log::info('Tutup buku hutang & DP supplier', [
            'jenis' => $this->jenis,
            'tahun_ditutup' => $this->tahun,
            'tahun_baru' => $hasil['tahunBaru'],
            'diubah' => $hasil['diubah'],
            'dibuat' => $hasil['dibuat'],
            'sama' => $hasil['sama'],
            'sebelum_hutang_dp' => $hasil['sebelum'],
            'user' => auth()->user()?->myuser_code,
        ]);

        unset($this->baris, $this->ringkasan);
        $this->dispatch('toast', type: 'success', message: "Saldo awal {$hasil['tahunBaru']} tersimpan: {$hasil['dibuat']} dibuat, {$hasil['diubah']} diperbarui, {$hasil['sama']} sudah sesuai.");
    }
};
?>

<div>
    <x-page-title title="Tutup Buku Hutang & DP Supplier"
        subtitle="Proses tahunan saldo awal hutang & uang muka per supplier — rumus sama dengan tutup buku Forms" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 gap-4 px-6 pt-2 pb-6">

            {{-- Toolbar --}}
            <div class="flex flex-wrap items-end gap-4 p-4 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                <div>
                    <x-input-label value="Jenis Supplier" />
                    <x-tabs variant="pill" class="mt-1">
                        <x-tab :active="$jenis === 'pbf'" wire:click="$set('jenis', 'pbf')">PBF (Obat)</x-tab>
                        <x-tab :active="$jenis === 'non-medis'" wire:click="$set('jenis', 'non-medis')">Non-Medis</x-tab>
                    </x-tabs>
                </div>
                <div>
                    <x-input-label value="Tahun yang ditutup" />
                    <x-select-input wire:model.live="tahun" class="mt-1 w-36">
                        @for ($th = (int) now()->year; $th >= 2020; $th--)
                            <option value="{{ $th }}">{{ $th }}</option>
                        @endfor
                    </x-select-input>
                </div>
                <div class="pb-2 text-sm text-muted dark:text-gray-400">
                    → menghasilkan <strong class="text-ink dark:text-gray-100">saldo awal {{ $tahun + 1 }}</strong>
                </div>
                <div class="pb-1.5">
                    <x-toggle wire:model.live="hanyaBerubah" :trueValue="true" :falseValue="false" label="Hanya yang berubah / baru" />
                </div>
                <div class="ml-auto">
                    <x-primary-button type="button" wire:click="siapkanProses" class="h-11"
                        wire:loading.attr="disabled" wire:target="siapkanProses">
                        <x-ikon nama="proses" /> Proses Tutup Buku {{ $tahun }}
                    </x-primary-button>
                </div>
            </div>

            @if ($tahun >= (int) now()->year)
                <div class="px-4 py-2 text-sm border rounded-xl text-amber-800 bg-amber-50 border-amber-200 dark:text-amber-200 dark:bg-amber-900/20 dark:border-amber-700">
                    Tahun {{ $tahun }} belum berakhir — angka masih akan berubah. Boleh diproses, tetapi jalankan ulang setelah 31 Desember.
                </div>
            @endif

            {{-- Panduan --}}
            <div x-data="{ open: false }"
                class="overflow-hidden border shrink-0 rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
                <button type="button" @click="open = !open"
                    class="flex items-center justify-between w-full px-3 py-2 text-sm font-semibold text-blue-900 transition-colors hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Cara Pakai — Tutup Buku Hutang &amp; DP Supplier
                    </span>
                    <svg class="w-4 h-4 text-blue-600 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div x-show="open" x-collapse class="px-3 pb-3 text-xs text-blue-900 dark:text-blue-200">
                    <ul class="ml-4 space-y-1 list-disc">
                        <li>Jalankan setiap awal tahun: pilih tahun yang ditutup (mis. 2026) → saldo awal tahun berikutnya (2027) per supplier.</li>
                        <li><strong>Hutang</strong> awal baru = hutang awal + pembelian (nota Hutang/Lunas, setelah diskon, PPN, materai) − pembayaran (kas + DP).</li>
                        <li><strong>DP</strong> awal baru = DP awal + setor DP − DP terpakai. Opname saldo DP tidak dimasukkan — tetap dihitung terpisah.</li>
                        <li>Rumusnya sama persis dengan tutup buku Forms, jadi hasilnya sama walau dijalankan dari Forms atau dari sini.</li>
                        <li>Kolom <strong>Status</strong>: Sama = baris tahun depan sudah sesuai; Beda = sudah ada tapi angkanya lain (biasanya transaksi berubah setelah tutup buku); Baru = belum ada.</li>
                        <li>Boleh dijalankan ulang kapan saja (mis. setelah koreksi telat) — angka lama dicatat di log sebelum diperbarui.</li>
                        <li>Hanya saldo per supplier. Saldo akun Neraca tidak diubah oleh proses ini.</li>
                    </ul>
                </div>
            </div>

            {{-- Ringkasan --}}
            @php $rk = $this->ringkasan; @endphp
            <div class="flex flex-wrap items-baseline px-5 py-2.5 text-sm border gap-x-6 gap-y-1 border-hairline rounded-xl bg-canvas dark:bg-gray-900 dark:border-gray-700">
                <span class="text-muted dark:text-gray-400">Supplier <span class="ml-1 font-medium text-ink dark:text-gray-100">{{ $rk['supplier'] }}</span></span>
                <span class="text-muted dark:text-gray-400">Σ Hutang awal {{ $tahun + 1 }} <span class="ml-1 font-mono font-medium text-ink dark:text-gray-100">Rp {{ number_format($rk['hutangBaru'], 0, ',', '.') }}</span></span>
                <span class="text-muted dark:text-gray-400">Σ DP awal {{ $tahun + 1 }} <span class="ml-1 font-mono font-medium text-ink dark:text-gray-100">Rp {{ number_format($rk['dpBaru'], 0, ',', '.') }}</span></span>
                <span class="text-muted dark:text-gray-400">Sama <span class="ml-1 font-medium text-emerald-700 dark:text-emerald-400">{{ $rk['sama'] }}</span></span>
                <span class="text-muted dark:text-gray-400">Beda <span class="ml-1 font-medium text-rose-700 dark:text-rose-300">{{ $rk['beda'] }}</span></span>
                <span class="text-muted dark:text-gray-400">Baru <span class="ml-1 font-medium text-blue-700 dark:text-blue-300">{{ $rk['baru'] }}</span></span>
            </div>

            {{-- Tabel --}}
            <div class="flex flex-col flex-1 min-h-0 border shadow-sm bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-auto">
                    <table class="w-full min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-left text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-300">
                            <tr class="text-xs font-semibold tracking-wide uppercase">
                                <th rowspan="2" class="px-4 py-2 align-bottom">Supplier</th>
                                <th colspan="4" class="px-4 py-2 text-center border-l border-hairline dark:border-gray-700">Hutang</th>
                                <th colspan="4" class="px-4 py-2 text-center border-l border-hairline dark:border-gray-700">DP (Uang Muka)</th>
                                <th rowspan="2" class="px-4 py-2 text-center align-bottom border-l border-hairline dark:border-gray-700">Status</th>
                            </tr>
                            <tr class="text-xs font-semibold tracking-wide uppercase">
                                <th class="px-3 py-2 text-right border-l border-hairline dark:border-gray-700">Awal {{ $tahun }}</th>
                                <th class="px-3 py-2 text-right">+ Beli</th>
                                <th class="px-3 py-2 text-right">− Bayar</th>
                                <th class="px-3 py-2 text-right">Awal {{ $tahun + 1 }}</th>
                                <th class="px-3 py-2 text-right border-l border-hairline dark:border-gray-700">Awal {{ $tahun }}</th>
                                <th class="px-3 py-2 text-right">+ Setor</th>
                                <th class="px-3 py-2 text-right">− Pakai</th>
                                <th class="px-3 py-2 text-right">Awal {{ $tahun + 1 }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-body divide-hairline dark:divide-gray-700 dark:text-gray-200">
                            @forelse ($this->baris as $row)
                                @continue($hanyaBerubah && $row['status'] === 'sama')
                                <tr wire:key="tutup-buku-{{ $row['suppId'] }}" class="hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-2">
                                        <div class="font-medium text-ink dark:text-gray-100">{{ $row['nama'] }}</div>
                                        <div class="font-mono text-xs text-muted dark:text-gray-400">{{ $row['suppId'] }}</div>
                                    </td>
                                    <td class="px-3 py-2 font-mono text-right whitespace-nowrap border-l border-hairline dark:border-gray-700">{{ number_format($row['hutangAwal'], 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 font-mono text-right whitespace-nowrap">{{ number_format($row['beli'], 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 font-mono text-right whitespace-nowrap">{{ number_format($row['bayar'], 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 font-mono font-semibold text-right whitespace-nowrap">
                                        {{ number_format($row['hutangBaru'], 0, ',', '.') }}
                                        @if ($row['ada'] && !TutupBukuSupplier::sama($row['hutangLama'], $row['hutangBaru']))
                                            <div class="text-xs font-normal text-rose-700 dark:text-rose-300">tersimpan {{ number_format($row['hutangLama'], 0, ',', '.') }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 font-mono text-right whitespace-nowrap border-l border-hairline dark:border-gray-700">{{ number_format($row['dpAwal'], 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 font-mono text-right whitespace-nowrap">{{ number_format($row['setor'], 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 font-mono text-right whitespace-nowrap">{{ number_format($row['pakaiDp'], 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 font-mono font-semibold text-right whitespace-nowrap">
                                        {{ number_format($row['dpBaru'], 0, ',', '.') }}
                                        @if ($row['ada'] && !TutupBukuSupplier::sama($row['dpLama'], $row['dpBaru']))
                                            <div class="text-xs font-normal text-rose-700 dark:text-rose-300">tersimpan {{ number_format($row['dpLama'], 0, ',', '.') }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-center border-l border-hairline dark:border-gray-700">
                                        @if ($row['status'] === 'sama')
                                            <x-badge variant="success">Sama</x-badge>
                                        @elseif ($row['status'] === 'beda')
                                            <x-badge variant="danger">Beda</x-badge>
                                        @else
                                            <x-badge variant="info">Baru</x-badge>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-6 py-16 text-center text-muted dark:text-gray-400">Tidak ada supplier dengan saldo / transaksi di tahun {{ $tahun }}.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- KONFIRMASI --}}
    @if ($konfirmasi)
        <div class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-gray-900/50"
            x-data x-init="$nextTick(() => $refs.btnYaTutup?.focus())">
            <div class="w-full max-w-md overflow-hidden border shadow-xl bg-canvas rounded-2xl border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="px-5 py-4 border-b border-hairline dark:border-gray-700">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-100">Tutup Buku {{ $jenis === 'pbf' ? 'PBF' : 'Non-Medis' }} {{ $tahun }}</h3>
                    <p class="mt-0.5 text-sm text-muted dark:text-gray-400">Saldo awal {{ $tahun + 1 }} per supplier akan ditulis.</p>
                </div>
                <dl class="px-5 py-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-muted dark:text-gray-400">Baris baru dibuat</dt><dd class="font-medium text-ink dark:text-gray-100">{{ $rk['baru'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted dark:text-gray-400">Baris diperbarui (angka beda)</dt><dd class="font-medium text-rose-700 dark:text-rose-300">{{ $rk['beda'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted dark:text-gray-400">Sudah sesuai (dilewati)</dt><dd class="font-medium text-ink dark:text-gray-100">{{ $rk['sama'] }}</dd></div>
                    @if ($rk['beda'] > 0)
                        <p class="px-3 py-2 text-xs border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:text-amber-200 dark:bg-amber-900/20 dark:border-amber-700">
                            Baris yang beda akan DITIMPA dengan angka hitungan sekarang. Saldo hutang &amp; DP supplier tahun {{ $tahun + 1 }} ikut berubah. Angka lama dicatat di log.
                        </p>
                    @endif
                </dl>
                <div class="flex justify-end gap-2 px-5 py-4 border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                    <x-secondary-button type="button" wire:click="batalProses"><x-ikon nama="batal" /> Batal</x-secondary-button>
                    <x-primary-button type="button" wire:click="proses" x-ref="btnYaTutup" wire:loading.attr="disabled" wire:target="proses">
                        <span wire:loading.remove wire:target="proses" class="inline-flex items-center gap-2"><x-ikon nama="proses" /> Ya, proses</span>
                        <span wire:loading wire:target="proses"><x-loading /> Memproses...</span>
                    </x-primary-button>
                </div>
            </div>
        </div>
    @endif
</div>
