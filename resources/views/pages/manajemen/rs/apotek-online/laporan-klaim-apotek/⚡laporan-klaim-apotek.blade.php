<?php
// ╔══════════════════════════════════════════════════════════════════════╗
// ║  LAPORAN KLAIM APOTEK ONLINE — monitoring rekap klaim obat ke BPJS    ║
// ╚══════════════════════════════════════════════════════════════════════╝
//
// Route: /manajemen/rs/apotek-online/laporan-klaim
//
// Meniru pola Evaluasi Rujukan Keluar: rentang periode MANUAL + tombol "Tarik
// Data" (tidak auto-fire — tiap tarik = 1 panggilan BPJS, dan kuota staging bisa
// habis). Sumber: ApotekTrait::apotek_monitoring_klaim(bulan, tahun, jenis, status)
// yang mengembalikan rekap siap pakai + listsep per SEP apotek.
//
// Selama CID belum aktif, BPJS membalas "Consumer ID is expired!" — ditampilkan
// apa adanya sebagai pesan gangguan, bukan tampak rusak.

use Livewire\Component;
use Livewire\Attributes\Session;
use Carbon\Carbon;
use App\Http\Traits\BPJS\ApotekTrait;

new class extends Component {
    use ApotekTrait;

    #[Session(key: 'lapKlaimApotek.periode')]
    public string $periode = ''; // mm/yyyy

    #[Session(key: 'lapKlaimApotek.jenisObat')]
    public string $jenisObat = '0'; // 0 semua · 1 PRB · 2 Kronis · 3 Kemoterapi

    #[Session(key: 'lapKlaimApotek.status')]
    public string $status = '1'; // 1 belum diverifikasi · 2 sudah verifikasi

    public string $cari = '';

    /** Hasil tarik. */
    public array $rekap = [];       // jumlahdata, totalbiayapengajuan, totalbiayasetuju
    public array $listSep = [];     // rincian per SEP
    public bool $sudahTarik = false;
    public string $infoTarik = '';

    private array $jenisLabel = ['0' => 'Semua', '1' => 'Obat PRB', '2' => 'Obat Kronis Belum Stabil', '3' => 'Obat Kemoterapi'];
    private array $statusLabel = ['1' => 'Belum Diverifikasi', '2' => 'Sudah Diverifikasi'];

    public function mount(): void
    {
        if ($this->periode === '') {
            $this->periode = now()->format('m/Y');
        }
    }

    public function resetFilters(): void
    {
        $this->periode = now()->format('m/Y');
        $this->jenisObat = '0';
        $this->status = '1';
        $this->cari = '';
        $this->kosongkanHasil();
    }

    private function kosongkanHasil(): void
    {
        $this->rekap = [];
        $this->listSep = [];
        $this->sudahTarik = false;
        $this->infoTarik = '';
    }

    /**
     * Tarik rekap klaim dari BPJS. MANUAL, seperti Evaluasi Rujukan Keluar —
     * tak boleh auto-fire tiap render.
     */
    public function tarikData(): void
    {
        try {
            $d = Carbon::createFromFormat('m/Y', trim($this->periode));
        } catch (\Throwable) {
            $this->dispatch('toast', type: 'error', message: 'Periode harus format mm/yyyy.');
            return;
        }

        $bulan = (int) $d->format('m');
        $tahun = $d->format('Y');

        $hasil = $this->apotek_monitoring_klaim($bulan, $tahun, $this->jenisObat, $this->status);
        $body = $hasil->getData(true);
        $code = $body['metadata']['code'] ?? null;

        if ((string) $code !== '200') {
            $this->kosongkanHasil();
            $this->sudahTarik = true;
            $this->infoTarik = 'BPJS: ' . ($body['metadata']['message'] ?? 'gangguan tanpa keterangan');
            $this->dispatch('toast', type: 'error', message: $this->infoTarik);
            return;
        }

        $rekap = $body['response']['rekap'] ?? [];
        $this->rekap = [
            'jumlahdata' => (int) ($rekap['jumlahdata'] ?? 0),
            'totalbiayapengajuan' => (float) ($rekap['totalbiayapengajuan'] ?? 0),
            'totalbiayasetuju' => (float) ($rekap['totalbiayasetuju'] ?? 0),
        ];
        $this->listSep = array_values(array_filter($rekap['listsep'] ?? [], 'is_array'));
        $this->sudahTarik = true;
        $this->infoTarik = $this->rekap['jumlahdata'] . ' SEP apotek pada '
            . $this->jenisLabel[$this->jenisObat] . ' · ' . $this->statusLabel[$this->status] . '.';
    }

    /** Baris tampil sesudah filter pencarian di memori (data sudah di tangan). */
    public function barisTampil(): array
    {
        $kw = trim(mb_strtolower($this->cari));
        if ($kw === '') {
            return $this->listSep;
        }

        return array_values(array_filter($this->listSep, function ($b) use ($kw) {
            $gabung = mb_strtolower(implode(' ', [
                $b['namapeserta'] ?? '', $b['nokartu'] ?? '',
                $b['noresep'] ?? '', $b['nosepapotek'] ?? '', $b['nosepaasal'] ?? '',
            ]));
            return str_contains($gabung, $kw);
        }));
    }

    /** Unduh CSV — pola sama Evaluasi Rujukan Keluar (streamDownload, pemisah ;). */
    public function unduhCsv()
    {
        if (!$this->sudahTarik || $this->listSep === []) {
            $this->dispatch('toast', type: 'info', message: 'Tarik data dulu sebelum mengunduh.');
            return;
        }

        $baris = $this->barisTampil();
        $rekap = $this->rekap;
        $periode = $this->periode;
        $jenis = $this->jenisLabel[$this->jenisObat];
        $statusTeks = $this->statusLabel[$this->status];
        $nama = 'klaim-apotek-' . str_replace('/', '-', $periode) . '.csv';

        return response()->streamDownload(function () use ($baris, $rekap, $periode, $jenis, $statusTeks) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            $tulis = fn(array $k) => fputcsv($out, $k, ';');
            $kosong = fn() => fputcsv($out, [], ';');

            $tulis(['Laporan Klaim Apotek Online']);
            $tulis(['Periode', $periode, 'Jenis', $jenis, 'Status', $statusTeks]);
            $tulis(['Jumlah SEP', $rekap['jumlahdata'] ?? 0]);
            $tulis(['Total Biaya Pengajuan', $rekap['totalbiayapengajuan'] ?? 0]);
            $tulis(['Total Biaya Disetujui', $rekap['totalbiayasetuju'] ?? 0]);
            $kosong();
            $tulis(['No SEP Apotek', 'No SEP Asal', 'No Kartu', 'Nama Peserta', 'No Resep', 'Jenis Obat', 'Tgl Pelayanan', 'Biaya Pengajuan', 'Biaya Disetujui']);
            foreach ($baris as $b) {
                $tulis([
                    $b['nosepapotek'] ?? '', $b['nosepaasal'] ?? '', $b['nokartu'] ?? '',
                    $b['namapeserta'] ?? '', $b['noresep'] ?? '', $b['jnsobat'] ?? '',
                    $b['tglpelayanan'] ?? '', $b['biayapengajuan'] ?? 0, $b['biayasetuju'] ?? 0,
                ]);
            }
            fclose($out);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
};
?>

<div class="w-full min-h-[calc(100vh-5rem)] bg-surface-soft dark:bg-gray-800">
    <div class="px-6 pt-2 pb-8">

        <x-page-title title="Laporan Klaim Apotek Online"
            subtitle="Rekap klaim obat PRB / kronis / kemoterapi ke BPJS per periode — sumber Apotek Online (apotek-rest)" />

        {{-- TOOLBAR --}}
        <div class="mt-3 p-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-end gap-3">
                <div class="w-full sm:w-auto">
                    <x-input-label value="Periode" />
                    <div class="relative mt-1">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <x-text-input type="text" wire:model="periode" placeholder="mm/yyyy" maxlength="7"
                            class="block w-full pl-10 sm:w-36" />
                    </div>
                </div>

                <div class="w-full sm:w-auto">
                    <x-input-label value="Jenis Obat" />
                    <x-select-input wire:model="jenisObat" class="w-full mt-1 sm:w-56">
                        <option value="0">Semua</option>
                        <option value="1">Obat PRB</option>
                        <option value="2">Obat Kronis Belum Stabil</option>
                        <option value="3">Obat Kemoterapi</option>
                    </x-select-input>
                </div>

                <div class="w-full sm:w-auto">
                    <x-input-label value="Status" />
                    <x-select-input wire:model="status" class="w-full mt-1 sm:w-48">
                        <option value="1">Belum Diverifikasi</option>
                        <option value="2">Sudah Diverifikasi</option>
                    </x-select-input>
                </div>

                <div class="flex items-center gap-2">
                    <x-primary-button type="button" wire:click="tarikData" wire:loading.attr="disabled"
                        wire:target="tarikData" title="Tarik rekap klaim dari BPJS Apotek Online">
                        {{-- Ikon awan-unduh: menegaskan datanya ditarik dari luar (BPJS),
                             bukan dibaca dari database kita. --}}
                        <span wire:loading.remove wire:target="tarikData" class="inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M12 11v6m0 0l-2-2m2 2l2-2" />
                            </svg>
                            Tarik Data
                        </span>
                        <span wire:loading wire:target="tarikData" class="inline-flex items-center gap-1.5">
                            <x-loading /> Menarik...
                        </span>
                    </x-primary-button>
                    <x-outline-button type="button" wire:click="resetFilters">Reset</x-outline-button>
                </div>
            </div>

            @if ($infoTarik !== '')
                <p class="mt-3 text-sm {{ str_starts_with($infoTarik, 'BPJS:') ? 'text-error-deep dark:text-red-300' : 'text-muted dark:text-gray-400' }}">
                    {{ $infoTarik }}
                </p>
            @endif
        </div>

        {{-- HASIL --}}
        @if ($sudahTarik && $rekap)
            {{-- KARTU REKAP --}}
            <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-3">
                <div class="p-4 bg-canvas border border-hairline rounded-2xl shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs uppercase text-muted dark:text-gray-400">Jumlah SEP Apotek</div>
                    <div class="mt-1 text-2xl font-bold text-ink dark:text-gray-100">{{ number_format($rekap['jumlahdata']) }}</div>
                </div>
                <div class="p-4 border rounded-2xl shadow-sm bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800">
                    <div class="text-xs uppercase text-info-deep dark:text-blue-300">Total Biaya Pengajuan</div>
                    <div class="mt-1 text-2xl font-bold text-info-deep dark:text-blue-200">Rp{{ number_format($rekap['totalbiayapengajuan'], 0, ',', '.') }}</div>
                </div>
                <div class="p-4 border rounded-2xl shadow-sm bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800">
                    <div class="text-xs uppercase text-emerald-700 dark:text-emerald-300">Total Biaya Disetujui</div>
                    <div class="mt-1 text-2xl font-bold text-emerald-800 dark:text-emerald-200">Rp{{ number_format($rekap['totalbiayasetuju'], 0, ',', '.') }}</div>
                    @if ($status === '1')
                        <div class="mt-1 text-xs text-emerald-700/80 dark:text-emerald-400/80">Rp0 wajar pada status "Belum Diverifikasi".</div>
                    @endif
                </div>
            </div>

            {{-- TABEL LISTSEP --}}
            <div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b border-hairline dark:border-gray-700">
                    <div class="flex items-center gap-2">
                        <x-text-input wire:model.live.debounce.300ms="cari" placeholder="Cari nama / no kartu / no resep / SEP..."
                            class="w-72" />
                    </div>
                    <x-outline-button type="button" wire:click="unduhCsv">Unduh CSV</x-outline-button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-xs font-semibold tracking-wide text-left uppercase text-muted border-b border-hairline dark:text-gray-300 dark:border-gray-700">
                                <th class="px-4 py-3">Peserta</th>
                                <th class="px-4 py-3">SEP Apotek / Asal</th>
                                <th class="px-4 py-3">Resep</th>
                                <th class="px-4 py-3">Jenis / Tgl</th>
                                <th class="px-4 py-3 text-right">Pengajuan</th>
                                <th class="px-4 py-3 text-right">Disetujui</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->barisTampil() as $b)
                                <tr class="border-b border-hairline last:border-0 dark:border-gray-800">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-ink dark:text-gray-100">{{ $b['namapeserta'] ?? '-' }}</div>
                                        <div class="font-mono text-xs text-muted dark:text-gray-400">{{ $b['nokartu'] ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs">
                                        <div class="text-ink dark:text-gray-200">{{ $b['nosepapotek'] ?? '-' }}</div>
                                        <div class="text-muted-soft">← {{ $b['nosepaasal'] ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3">{{ $b['noresep'] ?? '-' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="text-body dark:text-gray-200">{{ $b['jnsobat'] ?? '-' }}</div>
                                        <div class="text-xs text-muted-soft">{{ $b['tglpelayanan'] ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-right text-body dark:text-gray-200">
                                        Rp{{ number_format((float) ($b['biayapengajuan'] ?? 0), 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 font-mono text-right {{ (float) ($b['biayasetuju'] ?? 0) > 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-muted-soft' }}">
                                        Rp{{ number_format((float) ($b['biayasetuju'] ?? 0), 0, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center text-muted dark:text-gray-400">
                                        Tidak ada SEP apotek yang cocok.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif ($sudahTarik)
            <div class="mt-4 px-4 py-12 text-center bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <p class="text-base font-medium text-muted dark:text-gray-400">Tidak ada data klaim</p>
                <p class="mt-1 text-xs text-muted-soft">Tidak ada SEP apotek pada periode & filter ini.</p>
            </div>
        @else
            <div class="mt-4 px-4 py-12 text-center bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <p class="text-base font-medium text-muted dark:text-gray-400">Belum ditarik</p>
                <p class="mt-1 text-xs text-muted-soft">Atur periode & filter, lalu tekan <strong>Tarik Data</strong>.</p>
            </div>
        @endif

    </div>
</div>
