<?php

/**
 * Tutup Buku Tahunan — satu menu, tiga tab (hak keuangan.tutup-buku di Role Control):
 *   - Saldo Akun (Kas & Neraca)  → TKTXN_SALDOAWALAKUNS        (⚡tutup-buku-akun)
 *   - Stok                       → TKTXN_SALDOAWALSTOCKS[NON]   (⚡tutup-buku-stok)
 *   - Hutang & DP Supplier       → TKTXN_SALDOAWALHUTANGS[NON]  (keuangan/tutup-buku-supplier)
 * Ketiganya meniru rumus tutup buku Forms 6i yang dibuktikan dari data (lihat kelas TutupBuku*).
 */

use Livewire\Component;

new class extends Component {
    public string $tab = 'akun';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('keuangan.tutup-buku.lihat'), 403, 'Anda tidak memiliki akses ke Tutup Buku.');
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['akun', 'stok', 'supplier'], true)) {
            $this->tab = $tab;
        }
    }
};
?>

<div>
    <x-page-title title="Tutup Buku Tahunan"
        subtitle="Saldo awal tahun berikutnya — akun (Kas & Neraca), stok, serta hutang & DP supplier" />

    <div class="px-6 pt-2 pb-6 space-y-4 bg-surface-soft dark:bg-gray-800 min-h-[calc(100vh-5rem)]">
        <x-tabs variant="underline" class="flex-wrap">
            <x-tab :active="$tab === 'akun'" color="emerald" wire:click="setTab('akun')">Saldo Akun (Kas &amp; Neraca)</x-tab>
            <x-tab :active="$tab === 'stok'" color="blue" wire:click="setTab('stok')">Stok</x-tab>
            <x-tab :active="$tab === 'supplier'" color="purple" wire:click="setTab('supplier')">Hutang &amp; DP Supplier</x-tab>
        </x-tabs>

        {{-- Panduan --}}
        <div x-data="{ open: false }" class="overflow-hidden border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
            <button type="button" @click="open = !open"
                class="flex items-center justify-between w-full px-3 py-2 text-sm font-semibold text-blue-900 transition-colors hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Cara Pakai — Tutup Buku Tahunan
                </span>
                <svg class="w-4 h-4 text-blue-600 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </button>
            <div x-show="open" x-collapse class="px-3 pb-3 text-xs text-blue-900 dark:text-blue-200">
                <ul class="ml-4 space-y-1 list-disc">
                    <li>Jalankan awal Januari untuk tahun yang baru berakhir, urutan bebas: Saldo Akun, Stok, Hutang &amp; DP Supplier.</li>
                    <li>Rumus ketiganya sama dengan tutup buku Forms 6i (dibuktikan dari data 2023–2026), jadi hasilnya tidak bentrok bila sebagian masih ditutup dari Forms.</li>
                    <li><strong>Saldo Akun</strong>: akun neraca = saldo awal + debit − kredit setahun (akun K: + kredit − debit). <strong>L01 Laba (Rugi)</strong> menjadi penyeimbang sehingga Σ D − Σ K sama dengan tahun sebelumnya. KAS tahun depan = saldo akhir Cek Saldo Kas 31 Desember.</li>
                    <li><strong>Stok</strong>: semua produk master, saldo awal + mutasi kartu stok setahun (gudang, apotek, non-medis). HPP baris baru = harga beli master saat diproses; HPP baris yang sudah ada dipertahankan.</li>
                    <li><strong>Status</strong>: Sama = baris tahun depan sudah sesuai · Beda = sudah ada tapi lain (koreksi manual / transaksi berubah setelah tutup buku) · Baru = belum ada.</li>
                    <li>Default baris <em>Beda</em> TIDAK ditimpa (Saldo Akun &amp; Stok) — nyalakan "Timpa" hanya bila yakin hitungan ulang yang benar. Angka lama dicatat di log.</li>
                    <li>Catatan: jurnal web memuat kas non-medis yang tidak ada di Forms, sehingga saldo akun hasil web bisa berbeda dari Forms sebesar transaksi itu.</li>
                </ul>
            </div>
        </div>

        @if ($tab === 'akun')
            <livewire:pages::transaksi.keuangan.tutup-buku.tutup-buku-akun wire:key="tb-akun" />
        @elseif ($tab === 'stok')
            <livewire:pages::transaksi.keuangan.tutup-buku.tutup-buku-stok wire:key="tb-stok" />
        @else
            <livewire:pages::transaksi.keuangan.tutup-buku-supplier.tutup-buku-supplier wire:key="tb-supplier" />
        @endif
    </div>
</div>
