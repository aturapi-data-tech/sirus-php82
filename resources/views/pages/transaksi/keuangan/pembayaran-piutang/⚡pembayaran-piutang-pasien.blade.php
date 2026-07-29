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

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 mt-2 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">

                    <div class="w-full sm:w-80 sm:shrink-0">
                        @if ($regNo)
                            <x-input-label value="Pasien" />
                            <div class="flex items-center gap-2 mt-1">
                                <div class="flex-1 min-w-0 px-3 py-2 text-sm border rounded-lg border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700">
                                    <div class="font-semibold leading-tight truncate text-ink dark:text-gray-100">{{ $regName }}</div>
                                    <div class="font-mono text-xs leading-tight text-muted">No. RM {{ $regNo }}</div>
                                </div>
                                <x-secondary-button type="button" wire:click="gantiPasien">Ganti</x-secondary-button>
                            </div>
                        @else
                            <livewire:lov.pasien.lov-pasien target="pasien-piutang" label="Cari Pasien"
                                placeholder="Ketik No RM / Nama / NIK / No BPJS..."
                                wire:key="lov-pasien-piutang" />
                        @endif
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Jalur" />
                        <x-select-input wire:model.live="filterJalur" class="w-full mt-1 sm:w-32">
                            <option value="">Semua</option>
                            <option value="RJ">Rawat Jalan</option>
                            <option value="UGD">UGD</option>
                            <option value="RI">Rawat Inap</option>
                        </x-select-input>
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Klaim" />
                        <x-select-input wire:model.live="filterKlaim" class="w-full mt-1 sm:w-32">
                            <option value="">Semua</option>
                            <option value="BPJS">BPJS</option>
                            <option value="UMUM">UMUM</option>
                            <option value="KRONIS">KRONIS</option>
                            <option value="DOKEL">DOKEL</option>
                        </x-select-input>
                    </div>

                    {{-- Ringkasan pilihan + saklar centang semua --}}
                    <div class="flex items-center gap-2 px-3 py-2 border rounded-xl whitespace-nowrap bg-brand-green/5 border-brand-green/25 dark:bg-brand-lime/10 dark:border-brand-lime/25">
                        <span class="text-[11px] font-semibold tracking-wide uppercase text-muted dark:text-gray-400">
                            Terpilih {{ count($terpilih) }} Nota
                        </span>
                        <svg class="w-5 h-5 shrink-0 text-brand-green dark:text-brand-lime" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 10h18M7 15h2m4 0h4M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z" />
                        </svg>
                        <span class="text-xl font-bold leading-none text-brand-green dark:text-brand-lime">
                            Rp {{ number_format($this->totalTerpilih) }}
                        </span>
                    </div>

                    <div class="pb-1.5">
                        <x-toggle :current="$this->semuaTercentang ? '1' : '0'" trueValue="1" falseValue="0"
                            wireClick="toggleSemua" label="Centang semua nota"
                            title="Nyala = centang semua nota pasien, mati = kosongkan pilihan" />
                    </div>

                    <div class="flex items-center gap-2 ml-auto">
                        <x-toolbar-refresh-reset :label="null" />
                        <x-primary-button type="button" wire:click="prosesTerpilih"
                            wire:loading.attr="disabled" wire:target="prosesTerpilih">
                            Proses Pembayaran
                        </x-primary-button>
                    </div>
                </div>

                @if ($regNo)
                    <div class="mt-2 text-sm text-muted dark:text-gray-400">
                        Total piutang pasien ini: Rp {{ number_format($this->totalSisaPasien, 0, ',', '.') }}
                        · {{ $this->rows->count() }} nota
                    </div>
                @endif
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
                                <tr wire:key="piutang-pasien-{{ $kunci }}"
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

            {{-- Modal proses pembayaran --}}
            <livewire:pages::transaksi.keuangan.pembayaran-piutang.pembayaran-piutang-actions
                wire:key="pembayaran-piutang-actions" />

        </div>
    </div>
</div>
