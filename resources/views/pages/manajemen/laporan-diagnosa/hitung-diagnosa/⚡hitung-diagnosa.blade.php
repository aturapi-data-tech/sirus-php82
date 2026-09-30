<?php

use App\Http\Traits\Manajemen\Rs\HitungDiagnosaTrait;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    use HitungDiagnosaTrait;

    /** Payload LOV diagnosa terpilih (diag_id, diag_desc, icdx, ...), null = belum dipilih. */
    public ?array $diagnosaTerpilih = null;

    // Periode ala Casemix (Daftar Bulanan): mode Bulanan (mm/yyyy) atau Tahunan (yyyy).
    public string $filterMode = 'bulanan'; // 'bulanan' | 'tahunan'

    // Versi diagnosa yang dihitung: emr (tabel dtl) | idrg | inacbg (coder klaim di Casemix).
    public string $filterSumber = 'emr';
    public string $filterBulan = ''; // format m/Y — dipakai mode bulanan
    public string $filterTahun = ''; // format Y — dipakai mode tahunan

    public function mount(): void
    {
        $hariIni = Carbon::now(config('app.timezone'));
        $this->filterBulan = $hariIni->format('m/Y');
        $this->filterTahun = $hariIni->format('Y');
    }

    #[On('lov.selected.laporanDiagnosaHitung')]
    public function onDiagnosaDipilih(string $target, array $payload): void
    {
        $this->diagnosaTerpilih = $payload;
    }

    #[On('lov.cleared.laporanDiagnosaHitung')]
    public function onDiagnosaDikosongkan(string $target): void
    {
        $this->diagnosaTerpilih = null;
    }

    /** Kode yang dihitung: icdx; baris master tanpa icdx jatuh ke diag_id. */
    private function kodeDihitung(): string
    {
        $diagnosa = $this->diagnosaTerpilih ?? [];

        return trim((string) (($diagnosa['icdx'] ?? '') ?: ($diagnosa['diag_id'] ?? '')));
    }

    /** [awal, akhir] periode; isian kosong/ngawur jatuh ke bulan/tahun berjalan. */
    public function periode(): array
    {
        $hariIni = Carbon::now(config('app.timezone'));

        if ($this->filterMode === 'tahunan') {
            $awal = $this->tahunAman($this->filterTahun) ?? $hariIni->copy()->startOfYear();

            return [$awal->copy()->startOfYear(), $awal->copy()->endOfYear()];
        }

        $awal = $this->bulanAman($this->filterBulan) ?? $hariIni->copy()->startOfMonth();

        return [$awal->copy()->startOfMonth(), $awal->copy()->endOfMonth()];
    }

    /** mm/yyyy (bulan boleh 1 digit) → awal bulan, null bila tidak valid. */
    public function bulanAman(string $nilai): ?Carbon
    {
        if (!preg_match('#^(\d{1,2})/(\d{4})$#', trim($nilai), $bagian)) {
            return null;
        }
        [, $bulan, $tahun] = array_map('intval', $bagian);
        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > 2099) {
            return null;
        }

        return Carbon::create($tahun, $bulan, 1, 0, 0, 0, config('app.timezone'));
    }

    /** yyyy → awal tahun, null bila tidak valid. */
    public function tahunAman(string $nilai): ?Carbon
    {
        if (!preg_match('#^\d{4}$#', trim($nilai))) {
            return null;
        }
        $tahun = (int) trim($nilai);
        if ($tahun < 2000 || $tahun > 2099) {
            return null;
        }

        return Carbon::create($tahun, 1, 1, 0, 0, 0, config('app.timezone'));
    }

    #[Computed]
    public function hasilHitung(): ?array
    {
        $kode = $this->kodeDihitung();
        if ($kode === '') {
            return null;
        }

        [$dari, $sampai] = $this->periode();

        // Nilai di luar daftar (properti publik bisa diubah dari klien) jatuh ke EMR.
        return $this->hitungDiagnosa($kode, $dari, $sampai, $this->sumberAman());
    }

    private function sumberAman(): string
    {
        return array_key_exists($this->filterSumber, self::SUMBER_DIAGNOSA) ? $this->filterSumber : 'emr';
    }

    /**
     * Klik baris jalur di tabel → modal daftar pasien di hitung-diagnosa-actions.
     * Semua parameter hitungan ikut dikirim supaya daftar = kunjungan yang sama
     * persis dengan angka di tabel.
     */
    public function bukaDaftar(string $jalur): void
    {
        $kode = $this->kodeDihitung();
        if (!in_array($jalur, ['RJ', 'UGD', 'RI'], true) || $kode === '') {
            return;
        }

        [$dari, $sampai] = $this->periode();
        $this->dispatch(
            'hitung-diagnosa.openDaftar',
            jalur: $jalur,
            kode: $kode,
            deskripsi: (string) ($this->diagnosaTerpilih['diag_desc'] ?? ''),
            sumber: $this->sumberAman(),
            tanggalDari: $dari->format('Y-m-d'),
            tanggalSampai: $sampai->format('Y-m-d'),
        );
    }
};
?>

<div>
    @php
        $hasil = $this->hasilHitung;
        $angka = fn($nilai) => number_format((float) $nilai);
        $kodeTerpilih = ($diagnosaTerpilih['icdx'] ?? '') ?: ($diagnosaTerpilih['diag_id'] ?? '');
        // Rincian hanya berarti bila ada sub-kode selain kode terpilih (mis. E11 → E11.0–E11.9).
        $adaRincianKode = $hasil !== null && collect($hasil['perKode'])->contains(fn($row) => $row['icdx'] !== $kodeTerpilih);
        $varianJalur = ['RJ' => 'info', 'UGD' => 'danger', 'RI' => 'purple'];
        // Kolom Primer hanya untuk sumber klaim — tabel dtl EMR tidak menyimpan kategori.
        $adaPrimer = $hasil !== null && $hasil['total']['primer'] !== null;
    @endphp

    <x-page-title title="Hitung Jumlah Diagnosa"
        subtitle="Jumlah kunjungan & pasien per diagnosa ICD-10 di Rawat Jalan, UGD, dan Rawat Inap pada periode tertentu" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-start gap-3">

                    <div class="w-full sm:flex-1">
                        <livewire:lov.diagnosa.lov-diagnosa label="Diagnosa (ICD-10)" target="laporanDiagnosaHitung"
                            :blockHeader="false" :blockIm="false" :blockNonPrimary="false"
                            wire:key="lov-diagnosa-laporan-hitung" />
                    </div>

                    {{-- SUMBER DIAGNOSA — EMR (tabel dtl) atau versi klaim coder di Casemix --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Sumber Diagnosa" />
                        <x-select-input wire:model.live="filterSumber" class="w-full mt-1 sm:w-48">
                            @foreach ($this::SUMBER_DIAGNOSA as $nilaiSumber => $labelSumber)
                                <option value="{{ $nilaiSumber }}">{{ $labelSumber }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    {{-- MODE PERIODE: Bulanan / Tahunan — pola Casemix (Daftar Bulanan) --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Mode" />
                        <div class="inline-flex mt-1 overflow-hidden border border-gray-300 rounded-lg dark:border-gray-600">
                            <button type="button" wire:click="$set('filterMode', 'bulanan')"
                                class="px-3 py-1.5 text-xs font-medium transition-colors
                                    {{ $filterMode === 'bulanan' ? 'bg-brand text-white dark:bg-brand-lime dark:text-gray-900' : 'bg-canvas text-muted hover:bg-surface-soft dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                                Bulanan
                            </button>
                            <button type="button" wire:click="$set('filterMode', 'tahunan')"
                                class="px-3 py-1.5 text-xs font-medium transition-colors border-l border-gray-300 dark:border-gray-600
                                    {{ $filterMode === 'tahunan' ? 'bg-brand text-white dark:bg-brand-lime dark:text-gray-900' : 'bg-canvas text-muted hover:bg-surface-soft dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                                Tahunan
                            </button>
                        </div>
                    </div>

                    {{-- FILTER BULAN (mode bulanan) atau TAHUN (mode tahunan) --}}
                    @if ($filterMode === 'tahunan')
                        @php $tahunSalah = trim($filterTahun) !== '' && $this->tahunAman($filterTahun) === null; @endphp
                        <div class="w-full sm:w-auto">
                            <x-input-label value="Tahun" />
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <x-text-input type="text" wire:model.live.debounce.500ms="filterTahun" :error="$tahunSalah"
                                    class="block w-full pl-10 sm:w-32" placeholder="yyyy" maxlength="4" />
                            </div>
                            @if ($tahunSalah)
                                <p class="mt-1 text-xs text-error">Format yyyy</p>
                            @endif
                        </div>
                    @else
                        @php $bulanSalah = trim($filterBulan) !== '' && $this->bulanAman($filterBulan) === null; @endphp
                        <div class="w-full sm:w-auto">
                            <x-input-label value="Bulan" />
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <x-text-input type="text" wire:model.live.debounce.500ms="filterBulan" :error="$bulanSalah"
                                    class="block w-full pl-10 sm:w-40" placeholder="mm/yyyy" maxlength="7" />
                            </div>
                            @if ($bulanSalah)
                                <p class="mt-1 text-xs text-error">Format mm/yyyy</p>
                            @endif
                        </div>
                    @endif

                </div>
            </div>

            {{-- TABEL --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-base -mt-3 border-separate border-spacing-y-3">
                        <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                            <tr
                                class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                <th class="px-6 py-3 min-w-[200px]">Jalur</th>
                                <th class="px-6 py-3 text-right" title="Jumlah kunjungan/admisi yang punya diagnosa ini">Kunjungan</th>
                                @if ($adaPrimer)
                                    <th class="px-6 py-3 text-right" title="Kunjungan yang diagnosa ini dikoding sebagai diagnosa utama (Primary)">Primer</th>
                                @endif
                                <th class="px-6 py-3 text-right" title="Jumlah pasien berbeda (No. RM)">Pasien Unik</th>
                                <th class="px-6 py-3 text-right">Laki-laki</th>
                                <th class="px-6 py-3 text-right">Perempuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($hasil === null)
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-muted dark:text-gray-400">
                                        Belum ada diagnosa dipilih.
                                    </td>
                                </tr>
                            @else
                                @foreach ($hasil['jalur'] as $row)
                                    <tr class="transition bg-canvas dark:bg-gray-900
                                           rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700
                                           hover:shadow-lg hover:bg-surface-soft dark:hover:bg-gray-800 cursor-pointer {{ $row['kunjungan'] === 0 ? 'opacity-60' : '' }}"
                                        wire:click="bukaDaftar('{{ $row['jalur'] }}')" title="Klik untuk melihat daftar pasien {{ $row['label'] }}"
                                        wire:key="hitung-diagnosa-jalur-{{ $row['jalur'] }}">
                                        <td class="px-6 py-4 rounded-l-2xl">
                                            <x-badge :variant="$varianJalur[$row['jalur']]">{{ $row['jalur'] }}</x-badge>
                                            <span class="ml-2 font-semibold text-ink dark:text-gray-100">{{ $row['label'] }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-right font-semibold tabular-nums text-ink dark:text-gray-100">{{ $angka($row['kunjungan']) }}</td>
                                        @if ($adaPrimer)
                                            <td class="px-6 py-4 text-right tabular-nums text-body dark:text-gray-300">{{ $angka($row['primer']) }}</td>
                                        @endif
                                        <td class="px-6 py-4 text-right tabular-nums text-body dark:text-gray-300">{{ $angka($row['pasien']) }}</td>
                                        <td class="px-6 py-4 text-right tabular-nums text-body dark:text-gray-300">{{ $angka($row['laki']) }}</td>
                                        <td class="px-6 py-4 text-right tabular-nums text-body dark:text-gray-300 rounded-r-2xl">{{ $angka($row['perempuan']) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="bg-surface-card dark:bg-gray-800 rounded-2xl ring-1 ring-hairline dark:ring-gray-700">
                                    <td class="px-6 py-4 font-bold uppercase rounded-l-2xl text-ink dark:text-gray-100">Total</td>
                                    <td class="px-6 py-4 text-right font-bold tabular-nums text-ink dark:text-gray-100">{{ $angka($hasil['total']['kunjungan']) }}</td>
                                    @if ($adaPrimer)
                                        <td class="px-6 py-4 text-right font-bold tabular-nums text-ink dark:text-gray-100">{{ $angka($hasil['total']['primer']) }}</td>
                                    @endif
                                    <td class="px-6 py-4 text-right font-bold tabular-nums text-ink dark:text-gray-100" title="Pasien yang sama di RJ dan RI dihitung sekali">{{ $angka($hasil['total']['pasien']) }}</td>
                                    <td class="px-6 py-4 text-right font-bold tabular-nums text-ink dark:text-gray-100">{{ $angka($hasil['total']['laki']) }}</td>
                                    <td class="px-6 py-4 text-right font-bold tabular-nums text-ink dark:text-gray-100 rounded-r-2xl">{{ $angka($hasil['total']['perempuan']) }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>

                    {{-- RINCIAN PER SUB-KODE (hanya berarti untuk kode kategori) --}}
                    @if ($adaRincianKode)
                        <table class="min-w-full mt-2 text-base border-separate border-spacing-y-3">
                            <thead class="[&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                                <tr
                                    class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                    <th class="px-6 py-3 min-w-[260px]">Rincian per Kode</th>
                                    <th class="px-6 py-3 text-right">RJ</th>
                                    <th class="px-6 py-3 text-right">UGD</th>
                                    <th class="px-6 py-3 text-right">RI</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($hasil['perKode'] as $row)
                                    <tr class="transition bg-canvas dark:bg-gray-900
                                           rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700
                                           hover:shadow-lg hover:bg-surface-soft dark:hover:bg-gray-800"
                                        wire:key="hitung-diagnosa-kode-{{ $row['icdx'] }}">
                                        <td class="px-6 py-4 rounded-l-2xl">
                                            <div class="font-mono font-semibold text-ink dark:text-gray-100">{{ $row['icdx'] }}</div>
                                            <div class="text-sm text-muted dark:text-gray-400">{{ $row['diag_desc'] }}</div>
                                        </td>
                                        <td class="px-6 py-4 text-right tabular-nums text-body dark:text-gray-300">{{ $angka($row['RJ']) }}</td>
                                        <td class="px-6 py-4 text-right tabular-nums text-body dark:text-gray-300">{{ $angka($row['UGD']) }}</td>
                                        <td class="px-6 py-4 text-right tabular-nums text-body dark:text-gray-300 rounded-r-2xl">{{ $angka($row['RI']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

        </div>
    </div>

    <livewire:pages::manajemen.laporan-diagnosa.hitung-diagnosa.hitung-diagnosa-actions
        wire:key="hitung-diagnosa-actions" />
</div>
