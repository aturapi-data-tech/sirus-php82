<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    // Administrasi bersifat READ-ONLY — pembuatan transaksi, tarif, transfer dan
    // pembatalannya dilakukan di modul Penunjang → Kamar Operasi. Di sini hanya tampil.
    //
    // Baris di rstxn_rjoks adalah hasil "Trf Biaya-RJ": satu baris per pos tarif,
    // ok_desc-nya berasal dari KamarOperasiTarif::POS. Sengaja tabel sendiri, bukan
    // dititipkan ke Lain-Lain, supaya di jurnal pendapatan operasi tidak menyamar
    // sebagai pendapatan lain-lain.
    public ?int $rjNo = null;
    public array $rjOk = [];

    public function mount(): void
    {
        if ($this->rjNo) {
            $this->findData($this->rjNo);
        }
    }

    #[On('administrasi-rj.updated')]
    public function onAdministrasiUpdated(): void
    {
        if ($this->rjNo) {
            $this->findData($this->rjNo);
        }
    }

    private function findData(int $rjNo): void
    {
        $this->rjOk = DB::table('rstxn_rjoks')
            ->select('ok_no', 'ok_desc', 'ok_price', 'ok_reg')
            ->where('rj_no', $rjNo)
            ->orderBy('ok_reg')
            ->orderBy('ok_no')
            ->get()
            ->map(
                fn($baris) => [
                    'okNo' => (int) $baris->ok_no,
                    'okDesc' => $baris->ok_desc,
                    'okPrice' => (int) $baris->ok_price,
                    'okReg' => (int) $baris->ok_reg,
                ],
            )
            ->toArray();
    }
};
?>

{{-- Tab baca-saja: panel dibuat fokusable supaya rantai Enter antar-tab tidak putus di sini. --}}
<div class="space-y-4 outline-none" tabindex="-1" x-data
    x-on:focus-panel-kamar-operasi-rj.window="$nextTick(() => setTimeout(() => $el.focus(), 150))"
    x-on:keydown.enter="$dispatch('administrasi-rj-goto-tab', { tab: 'LainLain', focus: 'focus-lov-lainlain-rj' })">
    {{-- TABEL DATA (read-only) --}}
    <div class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-hairline dark:border-gray-700">
            <h3 class="text-sm font-semibold text-body dark:text-gray-300">Daftar Kamar Operasi</h3>
            <x-badge variant="gray">{{ count($rjOk) }} pos</x-badge>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead
                    class="text-sm font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300 bg-surface-soft dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">No Txn</th>
                        <th class="px-4 py-3">Keterangan</th>
                        <th class="px-4 py-3 text-right">Tarif Operasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                    @forelse ($rjOk as $item)
                        <tr wire:key="ok-row-{{ $item['okNo'] }}"
                            class="transition hover:bg-surface-soft dark:hover:bg-gray-800/40">
                            <td class="px-4 py-1.5 font-mono text-muted">{{ $item['okReg'] }}</td>
                            <td class="px-4 py-1.5">
                                <span class="text-ink dark:text-gray-200">{{ $item['okDesc'] }}</span>
                            </td>
                            <td class="px-4 py-1.5 whitespace-nowrap">
                                <span class="block font-semibold text-right text-ink dark:text-gray-200">
                                    Rp {{ number_format($item['okPrice']) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3"
                                class="px-4 py-10 text-sm text-center text-muted-soft dark:text-gray-600">
                                Belum ada data kamar operasi
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if (!empty($rjOk))
                    <tfoot class="border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="2" class="px-4 py-3 text-sm font-semibold text-muted dark:text-gray-400">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-ink dark:text-white">
                                Rp {{ number_format(collect($rjOk)->sum('okPrice')) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
