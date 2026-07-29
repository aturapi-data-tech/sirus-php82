<?php

/**
 * Pelunasan Klaim BPJS & Kronis (bundel per bulan) — LIST.
 *
 * Alur: pilih BULAN → tab → semua transaksi belum lunas pada bulan & tab itu
 * tampil → centang → proses → seluruhnya dilunasi.
 *
 * Empat tab, dua sumbu pemisah:
 *   RJ / UGD / RI  → klaim_status = 'BPJS', dipisah per jalur karena klaim BPJS
 *                    diajukan & dibayar terpisah antar jalur.
 *   KRONIS         → klaim_status = 'KRONIS' (obat kronis BPJS), semua jalur —
 *                    tagihannya berdiri sendiri, jadi diberi tab sendiri.
 *
 * Ganti tab mengosongkan pilihan supaya satu proses pembayaran tidak pernah
 * mencampur jalur maupun jenis klaim.
 *
 * Pembayaran pasien umum ada di komponen terpisah:
 *   ⚡pembayaran-piutang-pasien.blade.php
 *
 * Baris & rumus sisa memakai PiutangPasienTrait — sama persis dengan laporan
 * monitoring piutang. LIST tidak menulis DB; tombol proses hanya kirim event.
 */

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use App\Http\Traits\Manajemen\Rs\Tu\PiutangPasienTrait;

new class extends Component {
    use WithPagination, PiutangPasienTrait;

    public string $tab = 'RJ';           // RJ | UGD | RI (klaim BPJS) | KRONIS
    public string $filterBulan = '';     // mm/yyyy
    public int $itemsPerPage = 25;

    /* Pilihan nota: "JALUR|NO" => sisa */
    public array $terpilih = [];

    public function mount(): void
    {
        $this->filterBulan = Carbon::now()->format('m/Y');
    }

    public function updatedFilterBulan(): void { $this->kosongkanPilihan(); $this->resetPage(); }
    public function updatedItemsPerPage(): void { $this->resetPage(); }

    /** Ganti tab — pilihan dikosongkan supaya jalur/jenis klaim tak tercampur. */
    public function gantiTab(string $tab): void
    {
        $this->tab = in_array($tab, ['RJ', 'UGD', 'RI', 'KRONIS'], true) ? $tab : 'RJ';
        $this->kosongkanPilihan();
        $this->resetPage();
    }

    /** Tab KRONIS lintas jalur; tab lain dibatasi jalurnya. */
    protected function jalurAktif(): string
    {
        return $this->tab === 'KRONIS' ? '' : $this->tab;
    }

    /** Kategori klaim yang ditagihkan pada tab aktif. */
    protected function klaimAktif(): string
    {
        return $this->tab === 'KRONIS' ? 'KRONIS' : 'BPJS';
    }

    /** Label tab untuk judul & pesan. */
    protected function labelTab(): string
    {
        return [
            'RJ' => 'BPJS Rawat Jalan',
            'UGD' => 'BPJS UGD',
            'RI' => 'BPJS Rawat Inap',
            'KRONIS' => 'Obat Kronis',
        ][$this->tab] ?? $this->tab;
    }

    public function resetFilters(): void
    {
        $this->filterBulan = Carbon::now()->format('m/Y');
        $this->kosongkanPilihan();
        $this->resetPage();
    }

    /* ── Pilihan nota ── */
    public function toggle(string $jalur, string $noTransaksi, int $sisa): void
    {
        $kunci = $jalur . '|' . $noTransaksi;

        if (array_key_exists($kunci, $this->terpilih)) {
            unset($this->terpilih[$kunci]);
            return;
        }

        $this->terpilih[$kunci] = $sisa;
    }

    public function centangHalamanIni(): void
    {
        foreach ($this->barisAktif() as $row) {
            $this->terpilih[$row->jalur . '|' . $row->no_transaksi] = (int) $row->sisa;
        }
    }

    public function kosongkanPilihan(): void
    {
        $this->terpilih = [];
    }

    #[Computed]
    public function totalTerpilih(): int
    {
        return (int) array_sum($this->terpilih);
    }

    /* ── Kirim ke modal ── */
    public function prosesTerpilih(): void
    {
        if (empty($this->terpilih)) {
            $this->dispatch('toast', type: 'error', message: 'Centang dulu nota yang akan dibayar.');
            return;
        }

        $items = [];
        foreach (array_keys($this->terpilih) as $kunci) {
            [$jalur, $no] = explode('|', $kunci, 2);
            $items[] = ['jalur' => $jalur, 'no' => $no];
        }

        $this->dispatch(
            'piutang.openBayar',
            items: $items,
            mode: 'bundel',
            judulKonteks: 'Klaim ' . $this->labelTab() . ' ' . $this->filterBulan,
        );
    }

    #[On('piutang.paid')]
    public function refreshAfterPaid(): void
    {
        $this->piutangForgetSummary($this->awalBulan(), $this->akhirBulan(), $this->jalurAktif(), $this->klaimAktif(), '');
        $this->kosongkanPilihan();
        unset($this->summary, $this->rows, $this->totalTerpilih);
        $this->resetPage();
    }

    /* ── Rentang bulan filter ── */
    protected function awalBulan(): ?Carbon
    {
        try {
            return Carbon::createFromFormat('m/Y', $this->filterBulan)->startOfMonth();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function akhirBulan(): ?Carbon
    {
        return $this->awalBulan()?->copy()->endOfMonth();
    }

    /** Baris pada halaman yang sedang tampil. */
    protected function barisAktif()
    {
        return collect($this->rows->items());
    }

    /* ── Data: piutang pada bulan & tab terpilih ── */
    #[Computed]
    public function rows()
    {
        $page = Paginator::resolveCurrentPage();
        $perPage = $this->itemsPerPage;

        $barisHalaman = $this->piutangPageItems(
            $this->awalBulan(), $this->akhirBulan(),
            $this->jalurAktif(), $this->klaimAktif(), '',
            $perPage, $page,
        );

        $this->isiDokterRiLeveling($barisHalaman);

        return new LengthAwarePaginator(
            $barisHalaman, $this->summary['jumlah'], $perPage, $page, ['path' => request()->url()],
        );
    }

    #[Computed]
    public function summary(): array
    {
        return $this->piutangSummary(
            $this->awalBulan(), $this->akhirBulan(),
            $this->jalurAktif(), $this->klaimAktif(), '',
        );
    }

};
?>

<div>
    <x-page-title
        title="Pelunasan Klaim BPJS &amp; Kronis"
        subtitle="Lunasi tagihan belum lunas per bulan — tab BPJS RJ / UGD / RI dan Obat Kronis" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TAB JALUR --}}
            <div class="pt-2">
                <x-tabs>
                    <x-tab :active="$tab === 'RJ'" wire:click="gantiTab('RJ')">BPJS Rawat Jalan</x-tab>
                    <x-tab :active="$tab === 'UGD'" wire:click="gantiTab('UGD')">BPJS UGD</x-tab>
                    <x-tab :active="$tab === 'RI'" wire:click="gantiTab('RI')">BPJS Rawat Inap</x-tab>
                    <x-tab :active="$tab === 'KRONIS'" wire:click="gantiTab('KRONIS')">Obat Kronis</x-tab>
                </x-tabs>
            </div>

            {{-- CATATAN PENGEMBANGAN — panel biru standar, default tertutup --}}
            <details class="p-3 mt-2 text-sm border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800/40">
                <summary class="font-semibold text-blue-800 cursor-pointer dark:text-blue-300">
                    Catatan: fitur ini masih tahap awal — klik untuk detail yang belum tersedia
                </summary>
                <div class="mt-2 space-y-3 text-blue-900/80 dark:text-blue-200/80">
                    <p>
                        Layar ini baru menangani kasus paling lurus: nota tercentang dilunasi sebesar sisa
                        tagihannya. Hal-hal di bawah ini <strong>belum dikerjakan</strong> dan masih perlu
                        keputusan bersama sebelum dipakai untuk seluruh siklus klaim.
                    </p>

                    <ul class="space-y-1.5" style="list-style: disc; padding-left: 18px">
                        <li>
                            <strong>Selisih bayar klaim (untung / rugi) belum ada.</strong> Kalau BPJS membayar
                            lebih besar atau lebih kecil dari tagihan RS, sekarang tidak ada tempat mencatat
                            selisihnya. Sementara ini nota yang dibayar kurang jangan dicentang di sini —
                            selesaikan lewat layar per pasien yang nominalnya bebas.
                        </li>
                        <li>
                            <strong>Pembatalan / koreksi pembayaran belum ada.</strong> Kalau salah proses,
                            belum tersedia tombol batal khusus untuk pembayaran piutang; membatalkan lewat
                            Batal Transaksi di kasir bersifat merusak karena menghapus seluruh riwayat cicilan.
                        </li>
                        <li>
                            <strong>Pencatatan RI berbeda dengan RJ/UGD.</strong> Di rawat inap, satu pembayaran
                            bisa tercatat sebagai total lintas kwitansi (mis. kwitansi A Rp 10 + kwitansi B Rp 10
                            dibayar Rp 15 → mengurangi A dan B sekaligus), sedangkan RJ/UGD menempelkan
                            pembayaran per transaksi sehingga tidak bisa satu nominal lintas kwitansi. Saat ini
                            aplikasi memakai alokasi FIFO per nota untuk ketiga jalur — perlu diverifikasi apakah
                            sudah cocok dengan kebiasaan pencatatan RI.
                        </li>
                        <li>
                            <strong>Bundel klaim Obat Kronis belum dirumuskan.</strong> Tab Kronis baru
                            menampilkan &amp; melunasi tagihannya; skema bundelnya (nomor pengajuan, periode
                            klaim, perlakuan selisih bayar) masih menunggu keputusan.
                        </li>
                    </ul>

                    <p class="text-xs">
                        Yang sudah pasti aman: sisa tiap nota selalu dihitung ulang dari database saat proses,
                        pembayaran dicatat dengan tanggal pembayaran sebenarnya, dan setiap pelunasan masuk
                        Log Aktivitas transaksi terkait.
                    </p>
                </div>
            </details>

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 mt-2 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">

                    <div class="w-40">
                        <x-input-label value="Bulan Klaim" />
                        <x-text-input wire:model.live.debounce.500ms="filterBulan" placeholder="mm/yyyy"
                            class="block w-full mt-1" />
                    </div>

                    @php
                        $keteranganTab = $tab === 'KRONIS'
                            ? 'Menampilkan tagihan <strong>Obat Kronis</strong> (semua jalur)'
                            : 'Menampilkan piutang <strong>BPJS</strong> jalur <strong>' . ['RJ' => 'Rawat Jalan', 'UGD' => 'UGD', 'RI' => 'Rawat Inap'][$tab] . '</strong>';
                    @endphp
                    <div class="text-sm text-muted dark:text-gray-400">
                        {!! $keteranganTab !!} pada bulan {{ $filterBulan }}.
                    </div>

                    <div class="flex items-center gap-2 ml-auto">
                        <x-toolbar-refresh-reset :label="null" />

                        <div class="w-20">
                            <x-select-input wire:model.live="itemsPerPage" class="text-sm" title="Per halaman">
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                    </div>
                </div>
            </div>

            {{-- BAR PILIHAN --}}
            <div class="flex flex-wrap items-center gap-3 px-4 py-3 mt-4 border rounded-2xl bg-canvas border-hairline dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center gap-2 px-4 py-2 border rounded-xl whitespace-nowrap bg-brand-green/5 border-brand-green/25 dark:bg-brand-lime/10 dark:border-brand-lime/25">
                    <span class="text-[11px] font-semibold tracking-wide uppercase text-muted dark:text-gray-400">
                        Terpilih {{ count($terpilih) }} Nota
                    </span>
                    <svg class="w-6 h-6 shrink-0 text-brand-green dark:text-brand-lime" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 10h18M7 15h2m4 0h4M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z" />
                    </svg>
                    <span class="text-2xl font-bold leading-none text-brand-green dark:text-brand-lime">
                        Rp {{ number_format($this->totalTerpilih) }}
                    </span>
                </div>

                <div class="text-sm text-muted dark:text-gray-400">
                    Piutang {{ $tab }} {{ $filterBulan }}: Rp {{ number_format($this->summary['sisa'], 0, ',', '.') }}
                    · {{ number_format($this->summary['jumlah'], 0, ',', '.') }} transaksi
                </div>

                <div class="flex items-center gap-2 ml-auto">
                    <x-secondary-button type="button" wire:click="centangHalamanIni">
                        Centang semua di halaman ini
                    </x-secondary-button>
                    <x-secondary-button type="button" wire:click="kosongkanPilihan">Kosongkan</x-secondary-button>
                    <x-primary-button type="button" wire:click="prosesTerpilih"
                        wire:loading.attr="disabled" wire:target="prosesTerpilih">
                        Proses &amp; Lunaskan
                    </x-primary-button>
                </div>
            </div>

            {{-- TABEL --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="w-full min-w-full text-sm -mt-2 border-separate border-spacing-y-2">
                        <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                            <tr class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                <th class="px-4 py-3 w-24">Pilih</th>
                                <th class="px-5 py-3">Pasien</th>
                                <th class="px-4 py-3">Kunjungan</th>
                                <th class="px-4 py-3">Rincian Tagihan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                @php
                                    $kunci = $row->jalur . '|' . $row->no_transaksi;
                                    $dicentang = array_key_exists($kunci, $terpilih);
                                    $jalurLabel = ['RJ' => 'Rawat Jalan', 'UGD' => 'UGD', 'RI' => 'Rawat Inap'][$row->jalur] ?? $row->jalur;
                                    $jalurVariant = ['RJ' => 'info', 'UGD' => 'danger', 'RI' => 'purple'][$row->jalur] ?? 'gray';
                                @endphp
                                <tr wire:key="piutang-bayar-{{ $kunci }}"
                                    class="transition rounded-2xl shadow-sm ring-1 bg-canvas hover:shadow-md dark:bg-gray-900
                                        {{ $dicentang ? 'ring-brand-green/60 bg-brand-green/5 dark:ring-brand-lime/50' : 'ring-hairline hover:bg-surface-soft dark:ring-gray-700 dark:hover:bg-gray-800' }}">

                                    <td class="px-4 py-4 align-top rounded-l-2xl">
                                        <x-toggle :current="$dicentang ? '1' : '0'" trueValue="1" falseValue="0"
                                            :wireClick="'toggle(\'' . $row->jalur . '\', \'' . $row->no_transaksi . '\', ' . (int) $row->sisa . ')'"
                                            title="Pilih nota ini untuk dibayar" />
                                    </td>

                                    <td class="px-5 py-4 align-top">
                                        <x-list.identitas-pasien
                                            :regNo="$row->reg_no"
                                            :nama="$row->reg_name"
                                            :sex="$row->sex"
                                            :tglLahir="$row->birth_date"
                                            :alamat="$row->address" />
                                    </td>

                                    <td class="px-4 py-4 space-y-0.5 align-top">
                                        <x-badge :variant="$jalurVariant" class="whitespace-nowrap">{{ $jalurLabel }}</x-badge>

                                        @if (filled($row->unit))
                                            <div class="font-semibold leading-tight text-brand dark:text-emerald-400">{{ $row->unit }}</div>
                                        @endif

                                        <div class="text-sm leading-tight text-muted dark:text-gray-400">{{ $row->dokter }}</div>

                                        <div class="mt-0.5">
                                            <x-list.klaim-badge :status="$row->klaim_status" :desc="$row->klaim_desc" :id="$row->klaim_id" />
                                        </div>

                                        @if (filled($row->tgl_masuk))
                                            <div class="text-xs leading-tight text-muted dark:text-gray-500">Masuk: {{ $row->tgl_masuk }}</div>
                                            <div class="text-xs leading-tight text-muted dark:text-gray-500">Keluar: {{ $row->tgl }}</div>
                                        @else
                                            <div class="text-xs leading-tight text-muted dark:text-gray-500">{{ $row->tgl }}</div>
                                        @endif

                                        <div class="font-mono text-xs leading-tight text-muted-soft">No. {{ $row->no_transaksi }}</div>
                                    </td>

                                    <td class="px-4 py-4 align-top whitespace-nowrap rounded-r-2xl">
                                        <div class="space-y-1 text-sm min-w-[12rem]">
                                            <div class="flex justify-between gap-4">
                                                <span class="text-muted dark:text-gray-400">Total</span>
                                                <span class="font-medium text-body dark:text-gray-300">{{ number_format($row->total, 0, ',', '.') }}</span>
                                            </div>
                                            <div class="flex justify-between gap-4">
                                                <span class="text-muted dark:text-gray-400">Diskon</span>
                                                <span class="text-muted dark:text-gray-400">{{ number_format($row->diskon, 0, ',', '.') }}</span>
                                            </div>
                                            <div class="flex justify-between gap-4">
                                                <span class="text-muted dark:text-gray-400">Dibayar</span>
                                                <span class="text-body dark:text-gray-300">{{ number_format($row->bayar, 0, ',', '.') }}</span>
                                            </div>
                                            <div class="flex justify-between gap-4 pt-1.5 mt-1.5 border-t border-hairline dark:border-gray-700">
                                                <span class="font-semibold text-rose-700 dark:text-rose-300">Sisa Piutang</span>
                                                <span class="font-bold text-rose-700 dark:text-rose-300">Rp {{ number_format($row->sisa, 0, ',', '.') }}</span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-16 text-center text-muted dark:text-gray-400">
                                        <div class="flex flex-col items-center gap-2">
                                            <svg class="w-10 h-10 text-hairline" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span>Tidak ada piutang pada tab ini untuk bulan {{ $filterBulan }}.</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            {{-- Modal proses pembayaran --}}
            <livewire:pages::transaksi.keuangan.pembayaran-piutang.pembayaran-piutang-actions
                wire:key="pembayaran-piutang-actions" />

        </div>
    </div>
</div>
