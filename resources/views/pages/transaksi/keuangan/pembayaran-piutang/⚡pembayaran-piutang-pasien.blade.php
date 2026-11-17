<?php

/**
 * Pembayaran Piutang Per Pasien (umumnya pasien UMUM) — LIST.
 *
 * Alur meniru Pembayaran Hutang PBF: pilih pasien → seluruh notanya yang belum
 * lunas (RJ/UGD/RI) tampil → centang → bayar. Nominal boleh lebih kecil dari
 * total; alokasinya FIFO (nota terlama dilunasi dulu, sisanya jadi cicilan).
 *
 * Pelunasan klaim BPJS per bulan ada di komponen terpisah:
 *   ⚡pembayaran-piutang-bpjs.blade.php
 *
 * Baris & rumus sisa memakai PiutangPasienTrait — sama persis dengan laporan
 * monitoring piutang. LIST tidak menulis DB; tombol proses hanya kirim event.
 */

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Http\Traits\Manajemen\Rs\Tu\PiutangPasienTrait;

new class extends Component {
    use PiutangPasienTrait;

    public ?string $regNo = null;
    public ?string $regName = null;
    public string $filterJalur = '';     // '' | RJ | UGD | RI
    public string $filterKlaim = '';     // '' | BPJS | UMUM | KRONIS | DOKEL

    /* Pilihan nota: "JALUR|NO" => sisa */
    public array $terpilih = [];

    /* ── LOV pasien ── */
    #[On('lov.selected.pasien-piutang')]
    public function onPasienSelected(string $target, ?array $payload): void
    {
        $this->regNo = $payload['reg_no'] ?? null;
        $this->regName = $payload['reg_name'] ?? null;
        $this->kosongkanPilihan();
    }

    public function gantiPasien(): void
    {
        $this->reset(['regNo', 'regName']);
        $this->kosongkanPilihan();
    }

    public function updatedFilterJalur(): void
    {
        $this->kosongkanPilihan();
    }

    public function updatedFilterKlaim(): void
    {
        $this->kosongkanPilihan();
    }

    public function resetFilters(): void
    {
        $this->reset(['regNo', 'regName', 'filterJalur', 'filterKlaim']);
        $this->kosongkanPilihan();
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

    /** Satu saklar: nyala = centang semua nota pasien, mati = kosongkan. */
    public function toggleSemua(): void
    {
        if ($this->semuaTercentang) {
            $this->kosongkanPilihan();
            return;
        }

        foreach ($this->rows as $row) {
            $this->terpilih[$row->jalur . '|' . $row->no_transaksi] = (int) $row->sisa;
        }

        unset($this->semuaTercentang);
    }

    public function kosongkanPilihan(): void
    {
        $this->terpilih = [];
        unset($this->semuaTercentang);
    }

    #[Computed]
    public function semuaTercentang(): bool
    {
        $jumlahBaris = $this->rows->count();

        if ($jumlahBaris === 0) {
            return false;
        }

        foreach ($this->rows as $row) {
            if (!array_key_exists($row->jalur . '|' . $row->no_transaksi, $this->terpilih)) {
                return false;
            }
        }

        return true;
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
            mode: 'pasien',
            judulKonteks: trim(($this->regName ?? '') . ' · ' . ($this->regNo ?? '')),
        );
    }

    #[On('piutang.paid')]
    public function refreshAfterPaid(): void
    {
        $this->kosongkanPilihan();
        unset($this->rows, $this->totalTerpilih, $this->semuaTercentang);
    }

    /* ── Data: seluruh piutang pasien terpilih ── */
    #[Computed]
    public function rows()
    {
        if (!$this->regNo) {
            return collect();
        }

        $baris = $this->piutangPerPasien($this->regNo, $this->filterJalur, $this->filterKlaim);
        $this->isiDokterRiLeveling($baris);

        return $baris;
    }

    #[Computed]
    public function totalSisaPasien(): int
    {
        return (int) $this->rows->sum('sisa');
    }
};
?>

<div>
    <x-page-title
        title="Pembayaran Piutang Per Pasien"
        subtitle="Pelunasan / angsuran nota pasien yang belum lunas — RJ, UGD & RI" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- DUA KOLOM (pola Daftar RJ actions): KIRI = pasien + filter + ringkasan + Proses;
                 KANAN = daftar nota pasien (tanpa kolom Pasien — halaman ini memang per 1 pasien). --}}
            @php $pasienInfo = $this->rows->first(); @endphp
            <div class="flex flex-col flex-1 min-h-0 gap-4 mt-2 lg:flex-row">

                {{-- KOLOM KIRI --}}
                <div class="flex flex-col gap-5 p-5 overflow-y-auto border shadow-sm shrink-0 lg:w-96 bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                    <div>
                        @if ($regNo)
                            <div class="flex items-start justify-between gap-2">
                                <x-input-label value="Pasien" />
                                <x-secondary-button type="button" wire:click="gantiPasien"><x-ikon nama="ubah" /> Ganti</x-secondary-button>
                            </div>
                            <div class="px-4 py-3 mt-2 border rounded-xl border-hairline bg-surface-soft dark:bg-gray-800 dark:border-gray-700">
                                @if ($pasienInfo)
                                    <x-list.identitas-pasien
                                        :regNo="$pasienInfo->reg_no"
                                        :nama="$pasienInfo->reg_name"
                                        :sex="$pasienInfo->sex"
                                        :tglLahir="$pasienInfo->birth_date"
                                        :alamat="$pasienInfo->address" />
                                @else
                                    <div class="font-semibold text-ink dark:text-gray-100">{{ $regName }}</div>
                                    <div class="font-mono text-xs text-muted dark:text-gray-400">No. RM {{ $regNo }}</div>
                                @endif
                            </div>
                        @else
                            <livewire:lov.pasien.lov-pasien target="pasien-piutang" label="Cari Pasien"
                                placeholder="Ketik No RM / Nama / NIK / No BPJS..."
                                wire:key="lov-pasien-piutang" />
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-input-label value="Jalur" />
                            <x-select-input wire:model.live="filterJalur" class="w-full mt-1 h-11">
                                <option value="">Semua</option>
                                <option value="RJ">Rawat Jalan</option>
                                <option value="UGD">UGD</option>
                                <option value="RI">Rawat Inap</option>
                            </x-select-input>
                        </div>
                        <div>
                            <x-input-label value="Klaim" />
                            <x-select-input wire:model.live="filterKlaim" class="w-full mt-1 h-11">
                                <option value="">Semua</option>
                                <option value="BPJS">BPJS</option>
                                <option value="UMUM">UMUM</option>
                                <option value="KRONIS">KRONIS</option>
                                <option value="DOKEL">DOKEL</option>
                            </x-select-input>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <x-toolbar-refresh-reset :label="null" />
                    </div>

                    {{-- Ringkasan + Proses: langsung di bawah filter supaya tak terpotong layar --}}
                    <div class="pt-4 space-y-3 border-t border-hairline dark:border-gray-700">
                        @if ($regNo)
                            <div class="flex justify-between gap-3 text-sm">
                                <span class="text-muted dark:text-gray-400">Total piutang ({{ $this->rows->count() }} nota)</span>
                                <span class="font-semibold text-rose-700 dark:text-rose-300">Rp {{ number_format($this->totalSisaPasien, 0, ',', '.') }}</span>
                            </div>
                        @endif

                        <div class="px-4 py-3 border rounded-xl bg-brand-green/5 border-brand-green/25 dark:bg-brand-lime/10 dark:border-brand-lime/25">
                            <div class="text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">
                                Terpilih {{ count($terpilih) }} Nota
                            </div>
                            <div class="mt-1 text-2xl font-bold leading-none text-brand-green dark:text-brand-lime">
                                Rp {{ number_format($this->totalTerpilih, 0, ',', '.') }}
                            </div>
                        </div>

                        <x-primary-button type="button" wire:click="prosesTerpilih" class="justify-center w-full h-11"
                            wire:loading.attr="disabled" wire:target="prosesTerpilih">
                            <x-ikon nama="proses" />
                            Proses Pembayaran
                        </x-primary-button>
                    </div>
                </div>

                {{-- KOLOM KANAN — daftar nota --}}
                <div class="flex flex-col flex-1 min-w-0 min-h-0 border shadow-sm bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-3 px-5 py-3 border-b border-hairline dark:border-gray-700">
                        <div class="text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-300">Nota Belum Lunas</div>
                        <x-toggle :current="$this->semuaTercentang ? '1' : '0'" trueValue="1" falseValue="0"
                            wireClick="toggleSemua" label="Centang semua nota"
                            title="Nyala = centang semua nota pasien, mati = kosongkan pilihan" />
                    </div>

                    <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto">
                        <table class="w-full min-w-full text-sm border-separate border-spacing-y-2 px-3">
                            <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                                <tr class="text-sm font-semibold tracking-wide text-left uppercase text-muted dark:text-gray-300">
                                    <th class="w-24 px-4 py-3 rounded-l-xl">Pilih</th>
                                    <th class="px-4 py-3">Kunjungan</th>
                                    <th class="px-4 py-3 rounded-r-xl">Rincian Tagihan</th>
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
                                    <tr wire:key="piutang-pasien-{{ $kunci }}"
                                        class="transition rounded-2xl shadow-sm ring-1 bg-canvas hover:shadow-md dark:bg-gray-900
                                            {{ $dicentang ? 'ring-brand-green/60 bg-brand-green/5 dark:ring-brand-lime/50' : 'ring-hairline hover:bg-surface-soft dark:ring-gray-700 dark:hover:bg-gray-800' }}">

                                        <td class="px-4 py-4 align-top rounded-l-2xl">
                                            <x-toggle :current="$dicentang ? '1' : '0'" trueValue="1" falseValue="0"
                                                :wireClick="'toggle(\'' . $row->jalur . '\', \'' . $row->no_transaksi . '\', ' . (int) $row->sisa . ')'"
                                                title="Pilih nota ini untuk dibayar" />
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
                                        <td colspan="3" class="px-6 py-16 text-center text-muted dark:text-gray-400">
                                            <div class="flex flex-col items-center gap-2">
                                                <svg class="w-10 h-10 text-hairline" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>
                                                    {{ $regNo ? 'Pasien ini tidak punya piutang.' : 'Pilih pasien dulu untuk melihat notanya.' }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Modal proses pembayaran --}}
            <livewire:pages::transaksi.keuangan.pembayaran-piutang.pembayaran-piutang-actions
                wire:key="pembayaran-piutang-actions" />

        </div>
    </div>
</div>
