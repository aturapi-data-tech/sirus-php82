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

    public string $tanggalDari = '';
    public string $tanggalSampai = '';

    public function mount(): void
    {
        $hariIni = Carbon::now(config('app.timezone'));
        $this->tanggalDari = $hariIni->copy()->startOfMonth()->format('Y-m-d');
        $this->tanggalSampai = $hariIni->format('Y-m-d');
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

    /** [awal, akhir] periode; tanggal kosong/ngawur jatuh ke bulan berjalan, urutan terbalik ditukar. */
    public function periode(): array
    {
        $hariIni = Carbon::now(config('app.timezone'));
        $dari = $this->tanggalAman($this->tanggalDari) ?? $hariIni->copy()->startOfMonth();
        $sampai = $this->tanggalAman($this->tanggalSampai) ?? $hariIni->copy();

        if ($dari->gt($sampai)) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        return [$dari->startOfDay(), $sampai->endOfDay()];
    }

    private function tanggalAman(string $nilai): ?Carbon
    {
        try {
            return $nilai !== '' ? Carbon::createFromFormat('!Y-m-d', $nilai, config('app.timezone')) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    #[Computed]
    public function hasilHitung(): ?array
    {
        $kode = $this->kodeDihitung();
        if ($kode === '') {
            return null;
        }

        [$dari, $sampai] = $this->periode();

        return $this->hitungDiagnosa($kode, $dari, $sampai);
    }
};
?>

<div>
    @php
        $hasil = $this->hasilHitung;
        [$periodeDari, $periodeSampai] = $this->periode();
        $angka = fn($nilai) => number_format((float) $nilai);
        $kodeTerpilih = ($diagnosaTerpilih['icdx'] ?? '') ?: ($diagnosaTerpilih['diag_id'] ?? '');
        // Rincian hanya berarti bila ada sub-kode selain kode terpilih (mis. E11 → E11.0–E11.9).
        $adaRincianKode = $hasil !== null && collect($hasil['perKode'])->contains(fn($row) => $row['icdx'] !== $kodeTerpilih);
        $varianJalur = ['RJ' => 'info', 'UGD' => 'danger', 'RI' => 'purple'];
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

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Dari Tanggal" />
                        <x-text-input type="date" wire:model.live="tanggalDari" class="w-full mt-1 sm:w-44" />
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Sampai Tanggal" />
                        <x-text-input type="date" wire:model.live="tanggalSampai" class="w-full mt-1 sm:w-44" />
                    </div>

                </div>
            </div>

            {{-- REKAP --}}
            <div
                class="mt-4 px-4 py-3 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($hasil === null)
                        <span class="text-sm text-muted dark:text-gray-300">Pilih diagnosa untuk melihat jumlahnya.</span>
                    @else
                        <span class="mr-1 text-sm font-semibold text-ink dark:text-gray-100">{{ $kodeTerpilih }}</span>
                        <span class="mr-2 text-sm text-muted dark:text-gray-300">{{ $diagnosaTerpilih['diag_desc'] ?? '' }}</span>
                        <x-badge variant="brand">Total: {{ $angka($hasil['total']['kunjungan']) }} kunjungan</x-badge>
                        <x-badge variant="gray">{{ $angka($hasil['total']['pasien']) }} pasien unik</x-badge>
                        @foreach ($hasil['jalur'] as $row)
                            <x-badge :variant="$varianJalur[$row['jalur']]">{{ $row['jalur'] }}: {{ $angka($row['kunjungan']) }}</x-badge>
                        @endforeach
                    @endif
                    <span class="ml-auto text-xs text-muted-soft" wire:loading.remove>
                        Periode {{ $periodeDari->format('d/m/Y') }} s/d {{ $periodeSampai->format('d/m/Y') }}
                    </span>
                    <span class="ml-auto text-xs text-muted-soft" wire:loading>Menghitung…</span>
                </div>
                <p class="mt-2 text-xs text-muted-soft">
                    Menghitung kunjungan yang punya diagnosa ini (primer maupun sekunder). RJ &amp; UGD menurut tanggal kunjungan,
                    RI menurut tanggal pulang &mdash; pasien yang masih dirawat belum terhitung. Kode kategori (mis. E11) ikut
                    menghitung semua sub-kodenya (E11.0&ndash;E11.9). Pasien Kronis dan kunjungan batal tidak dihitung.
                </p>
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
                                <th class="px-6 py-3 text-right" title="Jumlah pasien berbeda (No. RM)">Pasien Unik</th>
                                <th class="px-6 py-3 text-right">Laki-laki</th>
                                <th class="px-6 py-3 text-right">Perempuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($hasil === null)
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-muted dark:text-gray-400">
                                        Belum ada diagnosa dipilih.
                                    </td>
                                </tr>
                            @else
                                @foreach ($hasil['jalur'] as $row)
                                    <tr class="transition bg-canvas dark:bg-gray-900
                                           rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700
                                           hover:shadow-lg hover:bg-surface-soft dark:hover:bg-gray-800 {{ $row['kunjungan'] === 0 ? 'opacity-60' : '' }}"
                                        wire:key="hitung-diagnosa-jalur-{{ $row['jalur'] }}">
                                        <td class="px-6 py-4 rounded-l-2xl">
                                            <x-badge :variant="$varianJalur[$row['jalur']]">{{ $row['jalur'] }}</x-badge>
                                            <span class="ml-2 font-semibold text-ink dark:text-gray-100">{{ $row['label'] }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-right font-semibold tabular-nums text-ink dark:text-gray-100">{{ $angka($row['kunjungan']) }}</td>
                                        <td class="px-6 py-4 text-right tabular-nums text-body dark:text-gray-300">{{ $angka($row['pasien']) }}</td>
                                        <td class="px-6 py-4 text-right tabular-nums text-body dark:text-gray-300">{{ $angka($row['laki']) }}</td>
                                        <td class="px-6 py-4 text-right tabular-nums text-body dark:text-gray-300 rounded-r-2xl">{{ $angka($row['perempuan']) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="bg-surface-card dark:bg-gray-800 rounded-2xl ring-1 ring-hairline dark:ring-gray-700">
                                    <td class="px-6 py-4 font-bold uppercase rounded-l-2xl text-ink dark:text-gray-100">Total</td>
                                    <td class="px-6 py-4 text-right font-bold tabular-nums text-ink dark:text-gray-100">{{ $angka($hasil['total']['kunjungan']) }}</td>
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
</div>
