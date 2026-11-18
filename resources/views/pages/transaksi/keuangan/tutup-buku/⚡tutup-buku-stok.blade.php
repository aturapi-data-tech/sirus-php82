<?php

/**
 * Tutup Buku — Saldo Stok (gudang & apotek medis, gudang non-medis) → TKTXN_SALDOAWALSTOCKS[NON] T+1.
 * Rumus = tutup buku Forms (App\Support\Keuangan\TutupBukuStok): saldo awal + mutasi ledger setahun,
 * semua produk master; HPP baris baru = harga beli master sekarang, baris yang ada dipertahankan.
 * ±2.800 produk → tabel hanya menampilkan yang berubah/baru secara default (maks. 500 baris).
 */

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Support\Keuangan\TutupBukuStok;

new class extends Component {
    private const ROLE_TUTUP_BUKU = ['Admin'];
    private const MAKS_BARIS = 500;

    public string $jenis = TutupBukuStok::JENIS_MEDIS;
    public int $tahun = 0;
    public bool $hanyaBerubah = true;
    public bool $timpa = false;
    public string $cari = '';
    public bool $konfirmasi = false;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(self::ROLE_TUTUP_BUKU), 403, 'Tutup buku khusus Admin.');
        $this->tahun = (int) Carbon::now()->year - 1;
    }

    public function updatedJenis(): void
    {
        $this->jenis = $this->jenis === TutupBukuStok::JENIS_NON_MEDIS ? TutupBukuStok::JENIS_NON_MEDIS : TutupBukuStok::JENIS_MEDIS;
        $this->konfirmasi = false;
        unset($this->semua);
    }

    public function updatedTahun(): void
    {
        $this->tahun = max(2020, min((int) $this->tahun, (int) Carbon::now()->year));
        $this->konfirmasi = false;
        unset($this->semua);
    }

    #[Computed]
    public function semua(): array
    {
        return TutupBukuStok::pratinjau($this->jenis, $this->tahun);
    }

    #[Computed]
    public function ringkasan(): array
    {
        $b = collect($this->semua);

        return [
            'produk' => $b->count(),
            'sama' => $b->where('status', 'sama')->count(),
            'beda' => $b->where('status', 'beda')->count(),
            'baru' => $b->where('status', 'baru')->count(),
            'gudang' => $b->sum('whBaru'),
            'apotek' => $b->sum('aptBaru'),
            'nilaiGudang' => $b->sum(fn ($r) => $r['whBaru'] * $r['hpp']),
            'nilaiApotek' => $b->sum(fn ($r) => $r['aptBaru'] * $r['hpp']),
        ];
    }

    /** Baris yang ditampilkan (filter + batas) — data lengkap tetap dipakai saat proses. */
    public function tampil(): array
    {
        $cari = mb_strtoupper(trim($this->cari));

        return collect($this->semua)
            ->when($this->hanyaBerubah, fn ($c) => $c->where('status', '!=', 'sama'))
            ->when($cari !== '', fn ($c) => $c->filter(fn ($r) => str_contains(mb_strtoupper($r['nama']), $cari) || str_contains(mb_strtoupper($r['productId']), $cari)))
            ->take(self::MAKS_BARIS)->values()->all();
    }

    public function siapkanProses(): void
    {
        if (!auth()->user()?->hasAnyRole(self::ROLE_TUTUP_BUKU)) {
            $this->dispatch('toast', type: 'error', message: 'Tutup buku khusus Admin.');
            return;
        }
        $rk = $this->ringkasan;
        if ($rk['baru'] === 0 && ($rk['beda'] === 0 || !$this->timpa)) {
            $this->dispatch('toast', type: 'info', message: 'Tidak ada yang perlu ditulis' . ($rk['beda'] > 0 ? ' (produk beda dilewati — nyalakan "Timpa" bila memang ingin menghitung ulang).' : '.'));
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
            $this->dispatch('toast', type: 'error', message: 'Tutup buku khusus Admin.');
            return;
        }

        try {
            $hasil = TutupBukuStok::proses($this->jenis, $this->tahun, $this->timpa);
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal tutup buku stok: ' . $e->getMessage());
            return;
        }

        Log::info('Tutup buku saldo stok', ['jenis' => $this->jenis, 'tahun_ditutup' => $this->tahun, 'timpa' => $this->timpa, 'user' => auth()->user()?->myuser_code] + $hasil);
        unset($this->semua, $this->ringkasan);
        $this->dispatch('toast', type: 'success', message: "Saldo awal stok {$hasil['tahunBaru']}: {$hasil['dibuat']} dibuat, {$hasil['diubah']} diperbarui, {$hasil['sama']} sesuai, {$hasil['dilewati']} dilewati.");
    }
};
?>

<div class="space-y-4">
    <div class="flex flex-wrap items-end gap-4 p-4 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div>
            <x-input-label value="Jenis" />
            <x-tabs variant="pill" class="mt-1">
                <x-tab :active="$jenis === 'medis'" wire:click="$set('jenis', 'medis')">Medis (Gudang + Apotek)</x-tab>
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
        <div class="pb-2 text-sm text-muted dark:text-gray-400">→ saldo awal stok <strong class="text-ink dark:text-gray-100">{{ $tahun + 1 }}</strong></div>
        <div>
            <x-input-label value="Cari produk" />
            <x-text-input type="text" wire:model.live.debounce.400ms="cari" placeholder="Nama / kode..." class="w-56 mt-1 text-sm" />
        </div>
        <div class="pb-1.5"><x-toggle wire:model.live="hanyaBerubah" :trueValue="true" :falseValue="false" label="Hanya yang berubah / baru" /></div>
        <div class="pb-1.5">
            <x-toggle wire:model.live="timpa" :trueValue="true" :falseValue="false" label="Timpa produk yang beda"
                title="Mati (default) = baris tahun depan yang sudah ada tidak diubah. Nyala = qty dihitung ulang & ditimpa (HPP tetap)." />
        </div>
        <div class="ml-auto">
            <x-primary-button type="button" wire:click="siapkanProses" class="h-11">
                <x-ikon nama="proses" /> Proses Tutup Buku {{ $tahun }}
            </x-primary-button>
        </div>
    </div>

    @if ($tahun >= (int) now()->year)
        <div class="px-4 py-2 text-sm border rounded-xl text-amber-800 bg-amber-50 border-amber-200 dark:text-amber-200 dark:bg-amber-900/20 dark:border-amber-700">
            Tahun {{ $tahun }} belum berakhir — qty masih akan berubah, dan HPP baris baru diambil dari harga master SAAT INI. Sebaiknya diproses awal Januari.
        </div>
    @endif

    @php $rk = $this->ringkasan; $tampil = $this->tampil(); @endphp
    <div class="flex flex-wrap items-baseline px-5 py-2.5 text-sm border gap-x-6 gap-y-1 border-hairline rounded-xl bg-canvas dark:bg-gray-900 dark:border-gray-700">
        <span class="text-muted dark:text-gray-400">Produk <span class="ml-1 font-medium text-ink dark:text-gray-100">{{ number_format($rk['produk'], 0, ',', '.') }}</span></span>
        <span class="text-muted dark:text-gray-400">Nilai gudang awal {{ $tahun + 1 }} <span class="ml-1 font-mono font-medium text-ink dark:text-gray-100">Rp {{ number_format($rk['nilaiGudang'], 0, ',', '.') }}</span></span>
        @if ($jenis === 'medis')
            <span class="text-muted dark:text-gray-400">Nilai apotek <span class="ml-1 font-mono font-medium text-ink dark:text-gray-100">Rp {{ number_format($rk['nilaiApotek'], 0, ',', '.') }}</span></span>
        @endif
        <span class="text-muted dark:text-gray-400">Sama <span class="ml-1 font-medium text-emerald-700 dark:text-emerald-400">{{ $rk['sama'] }}</span></span>
        <span class="text-muted dark:text-gray-400">Beda <span class="ml-1 font-medium text-rose-700 dark:text-rose-300">{{ $rk['beda'] }}</span></span>
        <span class="text-muted dark:text-gray-400">Baru <span class="ml-1 font-medium text-blue-700 dark:text-blue-300">{{ $rk['baru'] }}</span></span>
    </div>

    <div class="overflow-auto border shadow-sm max-h-[60vh] bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <table class="w-full min-w-full text-sm">
            <thead class="sticky top-0 z-10 text-left text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-300">
                <tr class="text-xs font-semibold tracking-wide uppercase">
                    <th rowspan="2" class="px-4 py-2 align-bottom">Produk</th>
                    <th colspan="3" class="px-3 py-2 text-center border-l border-hairline dark:border-gray-700">Gudang</th>
                    @if ($jenis === 'medis')
                        <th colspan="3" class="px-3 py-2 text-center border-l border-hairline dark:border-gray-700">Apotek</th>
                    @endif
                    <th rowspan="2" class="px-3 py-2 text-right align-bottom border-l border-hairline dark:border-gray-700">HPP</th>
                    <th rowspan="2" class="px-3 py-2 text-center align-bottom">Status</th>
                </tr>
                <tr class="text-xs font-semibold tracking-wide uppercase">
                    <th class="px-3 py-2 text-right border-l border-hairline dark:border-gray-700">Awal</th>
                    <th class="px-3 py-2 text-right">± Mutasi</th>
                    <th class="px-3 py-2 text-right">Awal {{ $tahun + 1 }}</th>
                    @if ($jenis === 'medis')
                        <th class="px-3 py-2 text-right border-l border-hairline dark:border-gray-700">Awal</th>
                        <th class="px-3 py-2 text-right">± Mutasi</th>
                        <th class="px-3 py-2 text-right">Awal {{ $tahun + 1 }}</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y text-body divide-hairline dark:divide-gray-700 dark:text-gray-200">
                @forelse ($tampil as $row)
                    <tr wire:key="tb-stok-{{ $row['productId'] }}" class="hover:bg-surface-soft dark:hover:bg-gray-800/60">
                        <td class="px-4 py-2">
                            <div class="text-ink dark:text-gray-100">{{ $row['nama'] }}</div>
                            <div class="font-mono text-xs text-muted dark:text-gray-400">{{ $row['productId'] }}{{ $row['aktif'] ? '' : ' · nonaktif' }}</div>
                        </td>
                        <td class="px-3 py-2 font-mono text-right border-l border-hairline dark:border-gray-700">{{ number_format($row['whAwal'], 0, ',', '.') }}</td>
                        <td class="px-3 py-2 font-mono text-right">{{ number_format($row['whMutasi'], 0, ',', '.') }}</td>
                        <td class="px-3 py-2 font-mono font-semibold text-right">
                            {{ number_format($row['whBaru'], 0, ',', '.') }}
                            @if ($row['status'] === 'beda' && !TutupBukuStok::sama($row['whLama'], $row['whBaru']))
                                <div class="text-xs font-normal text-rose-700 dark:text-rose-300">tersimpan {{ number_format($row['whLama'], 0, ',', '.') }}</div>
                            @endif
                        </td>
                        @if ($jenis === 'medis')
                            <td class="px-3 py-2 font-mono text-right border-l border-hairline dark:border-gray-700">{{ number_format($row['aptAwal'], 0, ',', '.') }}</td>
                            <td class="px-3 py-2 font-mono text-right">{{ number_format($row['aptMutasi'], 0, ',', '.') }}</td>
                            <td class="px-3 py-2 font-mono font-semibold text-right">
                                {{ number_format($row['aptBaru'], 0, ',', '.') }}
                                @if ($row['status'] === 'beda' && !TutupBukuStok::sama($row['aptLama'], $row['aptBaru']))
                                    <div class="text-xs font-normal text-rose-700 dark:text-rose-300">tersimpan {{ number_format($row['aptLama'], 0, ',', '.') }}</div>
                                @endif
                            </td>
                        @endif
                        <td class="px-3 py-2 font-mono text-right border-l border-hairline dark:border-gray-700">{{ number_format($row['hpp'], 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-center">
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
                    <tr><td colspan="{{ $jenis === 'medis' ? 9 : 6 }}" class="px-6 py-12 text-center text-muted dark:text-gray-400">
                        {{ $hanyaBerubah ? 'Semua produk sudah sesuai dengan saldo awal ' . ($tahun + 1) . '.' : 'Tidak ada produk.' }}
                    </td></tr>
                @endforelse
            </tbody>
        </table>
        @if (count($tampil) >= 500)
            <div class="px-4 py-2 text-xs text-muted dark:text-gray-400">Ditampilkan 500 baris pertama — pakai pencarian untuk produk lain. Proses tetap mencakup semua produk.</div>
        @endif
    </div>

    @if ($konfirmasi)
        <div class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-gray-900/50" x-data x-init="$nextTick(() => $refs.btnYaStok?.focus())">
            <div class="w-full max-w-md overflow-hidden border shadow-xl bg-canvas rounded-2xl border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="px-5 py-4 border-b border-hairline dark:border-gray-700">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-100">Tutup Buku Stok {{ $jenis === 'medis' ? 'Medis' : 'Non-Medis' }} {{ $tahun }}</h3>
                    <p class="mt-0.5 text-sm text-muted dark:text-gray-400">Saldo awal stok {{ $tahun + 1 }} akan ditulis.</p>
                </div>
                <dl class="px-5 py-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-muted dark:text-gray-400">Baris baru (HPP = harga master sekarang)</dt><dd class="font-medium text-ink dark:text-gray-100">{{ $rk['baru'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted dark:text-gray-400">Produk beda</dt><dd class="font-medium text-rose-700 dark:text-rose-300">{{ $rk['beda'] }} — {{ $timpa ? 'qty DITIMPA, HPP tetap' : 'dilewati' }}</dd></div>
                </dl>
                <div class="flex justify-end gap-2 px-5 py-4 border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                    <x-secondary-button type="button" wire:click="batalProses"><x-ikon nama="batal" /> Batal</x-secondary-button>
                    <x-primary-button type="button" wire:click="proses" x-ref="btnYaStok" wire:loading.attr="disabled" wire:target="proses">
                        <span wire:loading.remove wire:target="proses" class="inline-flex items-center gap-2"><x-ikon nama="proses" /> Ya, proses</span>
                        <span wire:loading wire:target="proses"><x-loading /> Memproses...</span>
                    </x-primary-button>
                </div>
            </div>
        </div>
    @endif
</div>
