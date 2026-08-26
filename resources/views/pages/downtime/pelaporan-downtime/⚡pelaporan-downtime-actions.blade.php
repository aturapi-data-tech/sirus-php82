<?php

/**
 * Pelaporan Down Time SIMRS — FORM (Akreditasi MRMIK 13.1), formulir DT-01.
 *
 * Satu modal = SATU KEJADIAN waktu henti. Bagian & urutannya sengaja sama persis
 * dengan cetakan kosong yang dipakai unit IT
 * (resources/views/pages/downtime/cetak/form/dt-01-log-kejadian.blade.php):
 *
 *   A  Data Kejadian            D  Dampak terhadap Pelayanan
 *   B  Pelaporan Awal           E  Evaluasi & Rencana Tindak Lanjut
 *   C  Identifikasi & Penanganan
 *
 * Tak ada TTD tersimpan & tak ada status terkunci: cetakan laporan keluar dengan
 * TIGA garis tanda tangan kosong (Petugas IT Penanganan, Ka. Unit IT / SIMRS,
 * Manajemen RS) untuk diteken basah, persis formulir kertasnya. Yang tersimpan
 * cuma paraf petugas yang merekam laporan.
 *
 * Satu baris = satu kejadian, jadi tak ada lock & tak ada read-modify-write —
 * lihat docs/ddl-pelaporan-downtime.sql.
 */

use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
use App\Http\Traits\Sistem\PelaporanDowntime\PelaporanDowntimeTrait;
use App\Support\Options\PelaporanDowntimeOptions;
use App\Support\Options\SuhuRuangServerOptions;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    use PelaporanDowntimeTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public array $renderVersions = [];

    protected array $renderAreas = ['modal'];

    public string $formMode = 'create';

    /** 0 = laporan belum pernah disimpan. */
    public int $downtimeNo = 0;

    public bool $siapDipakai = false;

    public array $kejadian = [];      // Bagian A

    public array $pelaporan = [];     // Bagian B

    public array $penanganan = [];    // Bagian C

    public array $dampak = [];        // Bagian D — panjangnya tetap, sembilan unit

    public array $evaluasi = [];      // Bagian E

    /** Paraf perekam pertama — dipertahankan apa adanya saat laporan dikoreksi. */
    public array $paraf = ['nama' => '', 'kode' => '', 'tanggal' => ''];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
        $this->resetLaporan();
    }

    /* ══════════════════════ BUKA / TUTUP ══════════════════════ */

    #[On('pelaporan-downtime.openCreate')]
    public function openCreate(): void
    {
        $this->resetLaporan();
        $this->siapDipakai = $this->checkTabelDowntime();
        $this->formMode = 'create';

        // Kejadian hampir selalu direkam tak lama setelah pulih; waktu mulainya
        // diisi sebagai titik awal yang masih bisa diubah, bukan dikosongkan.
        $this->kejadian['waktuMulai'] = Carbon::now(config('app.timezone'))
            ->format(SuhuRuangServerOptions::FORMAT_WAKTU);

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'pelaporan-downtime-actions');
    }

    #[On('pelaporan-downtime.openEdit')]
    public function openEdit(int $downtimeNo): void
    {
        $this->resetLaporan();
        $this->siapDipakai = $this->checkTabelDowntime();

        [$baris, $isi] = $this->findDowntime($downtimeNo);

        if ($baris === null) {
            $this->dispatch('toast', type: 'error', message: 'Laporan down time tidak ditemukan.');

            return;
        }

        $this->formMode = 'edit';
        $this->downtimeNo = (int) $baris->downtime_no;

        // array_replace, bukan penugasan langsung: laporan lama bisa belum punya
        // kunci yang baru ditambahkan, dan kunci itu tetap terisi nilai bawaan.
        // array_intersect_key: kunci yang sudah DIBUANG dari rancangan (mis.
        // modulTerdampak, unitPelapor, hasil) tidak ikut terbawa saat record lama
        // dibuka lalu disimpan ulang — kalau tidak, sampahnya hidup selamanya.
        $this->kejadian = $this->rapikan($isi['kejadian'] ?? [], $this->kejadianKosong());
        $this->pelaporan = $this->rapikan($isi['pelaporan'] ?? [], $this->pelaporanKosong());
        $this->penanganan = $this->rapikan($isi['penanganan'] ?? [], $this->penangananKosong());
        $this->evaluasi = $this->rapikan($isi['evaluasi'] ?? [], $this->evaluasiKosong());

        // Unit yang belum ada di record lama tetap muncul kosong, unit yang sudah
        // tak dipakai dibuang — bukan ditampilkan sebagai kunci mentah.
        $this->dampak = PelaporanDowntimeOptions::gabungDampak($isi['dampak'] ?? []);

        $this->paraf = [
            'nama' => (string) ($isi['paraf']['nama'] ?? ''),
            'kode' => (string) ($isi['paraf']['kode'] ?? ''),
            'tanggal' => (string) ($isi['paraf']['tanggal'] ?? ''),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'pelaporan-downtime-actions');
    }

    public function closeModal(): void
    {
        $this->resetLaporan();
        $this->dispatch('close-modal', name: 'pelaporan-downtime-actions');
        $this->resetVersion();
    }

    private function resetLaporan(): void
    {
        $this->formMode = 'create';
        $this->downtimeNo = 0;
        $this->kejadian = $this->kejadianKosong();
        $this->pelaporan = $this->pelaporanKosong();
        $this->penanganan = $this->penangananKosong();
        $this->evaluasi = $this->evaluasiKosong();
        $this->dampak = PelaporanDowntimeOptions::dampakKosong();
        $this->paraf = ['nama' => '', 'kode' => '', 'tanggal' => ''];
        $this->resetValidation();
    }

    /** Isi tersimpan ditumpuk ke kerangka, lalu kunci di luar kerangka dibuang. */
    private function rapikan(array $tersimpan, array $kerangka): array
    {
        return array_intersect_key(array_replace($kerangka, $tersimpan), $kerangka);
    }

    private function kejadianKosong(): array
    {
        return [
            'jenis' => 'tidakTerencana',
            'noLog' => '',
            'waktuMulai' => '',
            'waktuPulih' => '',
            'durasi' => '',
            'lingkup' => 'sebagianModul',
        ];
    }

    private function pelaporanKosong(): array
    {
        return [
            'dilaporkanOleh' => '',
            'jamLaporanDiterima' => '',
            'mediaLaporan' => 'telepon',
            'gejalaAwal' => '',
        ];
    }

    private function penangananKosong(): array
    {
        return [
            'penyebab' => '',
            'estimasiPemulihan' => '',
            'jamInformasi' => '',
            'tindakan' => '',
        ];
    }

    private function evaluasiKosong(): array
    {
        return [
            'akarMasalah' => '',
            'rencanaTindakLanjut' => '',
            'penanggungJawab' => '',
            'targetSelesai' => '',
            'statusBackup' => '',
        ];
    }

    /* ══════════════════════ TOMBOL WAKTU ══════════════════════ */

    /** Durasi ikut berubah begitu salah satu ujung waktunya diketik. */
    public function updatedKejadian(mixed $nilai = null, ?string $kunci = null): void
    {
        $this->kejadian['durasi'] = PelaporanDowntimeOptions::hitungDurasi($this->kejadian);
    }

    public function setMulaiSekarang(): void
    {
        $this->kejadian['waktuMulai'] = Carbon::now(config('app.timezone'))
            ->format(SuhuRuangServerOptions::FORMAT_WAKTU);
        $this->updatedKejadian();
    }

    public function setPulihSekarang(): void
    {
        $this->kejadian['waktuPulih'] = Carbon::now(config('app.timezone'))
            ->format(SuhuRuangServerOptions::FORMAT_WAKTU);
        $this->updatedKejadian();
    }

    public function setJamLaporanSekarang(): void
    {
        $this->pelaporan['jamLaporanDiterima'] = Carbon::now(config('app.timezone'))->format('H:i');
    }

    public function setJamInformasiSekarang(): void
    {
        $this->penanganan['jamInformasi'] = Carbon::now(config('app.timezone'))->format('H:i');
    }

    /* ══════════════════════ SIMPAN ══════════════════════ */

    public function save(): void
    {
        if (! $this->siapDipakai) {
            $this->dispatch('toast', type: 'error', message: 'Tabel laporan down time belum dipasang.');

            return;
        }

        // validate() DULUAN — guard sebelum validasi menyembunyikan field merah.
        $this->validateWithToast([
            'kejadian.jenis' => ['required', 'in:' . implode(',', array_keys(PelaporanDowntimeOptions::JENIS))],
            'kejadian.noLog' => ['nullable', 'string', 'max:50'],
            'kejadian.waktuMulai' => ['required', 'date_format:' . SuhuRuangServerOptions::FORMAT_WAKTU],
            'kejadian.waktuPulih' => ['nullable', 'date_format:' . SuhuRuangServerOptions::FORMAT_WAKTU],
            'kejadian.lingkup' => ['required', 'in:' . implode(',', array_keys(PelaporanDowntimeOptions::LINGKUP))],

            'pelaporan.dilaporkanOleh' => ['nullable', 'string', 'max:150'],
            'pelaporan.jamLaporanDiterima' => ['nullable', 'date_format:H:i'],
            'pelaporan.mediaLaporan' => ['required', 'in:' . implode(',', array_keys(PelaporanDowntimeOptions::MEDIA_LAPORAN))],
            'pelaporan.gejalaAwal' => ['nullable', 'string', 'max:500'],

            'penanganan.penyebab' => ['nullable', 'string', 'max:1000'],
            'penanganan.estimasiPemulihan' => ['nullable', 'string', 'max:100'],
            'penanganan.jamInformasi' => ['nullable', 'date_format:H:i'],
            'penanganan.tindakan' => ['nullable', 'string', 'max:1000'],

            'dampak.*.jumlah' => ['nullable', 'string', 'max:20'],
            'dampak.*.catatan' => ['nullable', 'string', 'max:255'],

            'evaluasi.akarMasalah' => ['nullable', 'string', 'max:1000'],
            'evaluasi.rencanaTindakLanjut' => ['nullable', 'string', 'max:1000'],
            'evaluasi.penanggungJawab' => ['nullable', 'string', 'max:100'],
            'evaluasi.targetSelesai' => ['nullable', 'date_format:d/m/Y'],
            'evaluasi.statusBackup' => ['nullable', 'string', 'max:255'],
        ], [
            'kejadian.waktuMulai.required' => 'Waktu mulai down time wajib diisi.',
            'kejadian.waktuMulai.date_format' => 'Waktu mulai harus berformat dd/mm/yyyy HH:MM:SS.',
            'kejadian.waktuPulih.date_format' => 'Waktu pulih harus berformat dd/mm/yyyy HH:MM:SS.',
            'kejadian.jenis.in' => 'Jenis waktu henti tidak dikenal.',
            'kejadian.lingkup.in' => 'Lingkup gangguan tidak dikenal.',
            'pelaporan.mediaLaporan.in' => 'Media laporan tidak dikenal.',
            'evaluasi.targetSelesai.date_format' => 'Target selesai harus berformat dd/mm/yyyy.',
        ], [
            'kejadian.jenis' => 'Jenis waktu henti',
            'kejadian.noLog' => 'No. Log',
            'kejadian.waktuMulai' => 'Waktu mulai',
            'kejadian.waktuPulih' => 'Waktu pulih',
            'kejadian.lingkup' => 'Lingkup gangguan',
            'pelaporan.dilaporkanOleh' => 'Dilaporkan oleh',
            'pelaporan.jamLaporanDiterima' => 'Jam laporan diterima',
            'pelaporan.mediaLaporan' => 'Media laporan',
            'pelaporan.gejalaAwal' => 'Gejala / keluhan awal',
            'penanganan.penyebab' => 'Hasil identifikasi penyebab',
            'penanganan.estimasiPemulihan' => 'Estimasi pemulihan',
            'penanganan.jamInformasi' => 'Jam informasi disampaikan',
            'penanganan.tindakan' => 'Tindakan penanganan',
            'evaluasi.akarMasalah' => 'Analisis akar masalah',
            'evaluasi.rencanaTindakLanjut' => 'Rencana tindak lanjut',
            'evaluasi.penanggungJawab' => 'Penanggung jawab',
            'evaluasi.targetSelesai' => 'Target selesai',
            'evaluasi.statusBackup' => 'Status pencadangan terakhir',
        ]);

        // Keduanya bertanggal penuh, jadi gangguan yang melewati tengah malam
        // tetap sah — yang ditolak hanya pulih SEBELUM mulai, yang pasti salah ketik.
        if (PelaporanDowntimeOptions::pulihSebelumMulai($this->kejadian)) {
            $this->dispatch('toast', type: 'error', message: 'Waktu pulih mendahului waktu mulai — periksa tanggal & jamnya.');

            return;
        }

        $this->kejadian['durasi'] = PelaporanDowntimeOptions::hitungDurasi($this->kejadian);

        // Paraf milik petugas yang MEREKAM laporan, jadi ia tak berpindah tangan
        // saat laporannya dikoreksi belakangan.
        $paraf = $this->formMode === 'edit' && filled($this->paraf['nama'])
            ? $this->paraf
            : [
                'nama' => auth()->user()->myuser_name ?? auth()->user()->name ?? '',
                'kode' => auth()->user()->myuser_code ?? '',
                'tanggal' => Carbon::now(config('app.timezone'))->format(SuhuRuangServerOptions::FORMAT_WAKTU),
            ];

        try {
            $this->downtimeNo = $this->simpanDowntime($this->downtimeNo === 0 ? null : $this->downtimeNo, [
                'kejadian' => $this->kejadian,
                'pelaporan' => $this->pelaporan,
                'penanganan' => $this->penanganan,
                'dampak' => $this->dampak,
                'evaluasi' => $this->evaluasi,
                'paraf' => $paraf,
            ]);
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $exception->getMessage());

            return;
        }

        $this->paraf = $paraf;
        $this->formMode = 'edit';

        $this->dispatch('toast', type: 'success', message: 'Laporan down time tersimpan.');
        $this->dispatch('pelaporan-downtime.saved');
    }

    #[On('pelaporan-downtime.requestDelete')]
    public function hapusLaporan(int $downtimeNo): void
    {
        // Dua lapis: @can di blade menyembunyikan tombolnya, guard ini menutup
        // wire:click yang dipanggil langsung.
        if (! auth()->user()?->can('downtime.pelaporanHapus')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang menghapus laporan.');

            return;
        }

        $terhapus = DB::table('rstxn_downtimes')->where('downtime_no', $downtimeNo)->delete();

        if ($terhapus === 0) {
            $this->dispatch('toast', type: 'error', message: 'Laporan tidak ditemukan.');

            return;
        }

        if ($this->downtimeNo === $downtimeNo) {
            $this->closeModal();
        }

        $this->dispatch('toast', type: 'success', message: 'Laporan down time dihapus.');
        $this->dispatch('pelaporan-downtime.saved');
    }

    /* ══════════════════════ CETAK ══════════════════════ */

    public function cetak()
    {
        if ($this->downtimeNo === 0) {
            $this->dispatch('toast', type: 'error', message: 'Simpan laporan dulu sebelum mencetak.');

            return null;
        }

        try {
            [$baris, $isi] = $this->findDowntime($this->downtimeNo);

            if ($baris === null) {
                $this->dispatch('toast', type: 'error', message: 'Laporan down time tidak ditemukan.');

                return null;
            }

            $kejadian = array_replace($this->kejadianKosong(), $isi['kejadian'] ?? []);

            $data = [
                'downtimeNo' => (int) $baris->downtime_no,
                'kejadian' => $kejadian,
                'waktuMulai' => SuhuRuangServerOptions::pecahWaktu($kejadian['waktuMulai']),
                'waktuPulih' => SuhuRuangServerOptions::pecahWaktu($kejadian['waktuPulih']),
                'pelaporan' => array_replace($this->pelaporanKosong(), $isi['pelaporan'] ?? []),
                'penanganan' => array_replace($this->penangananKosong(), $isi['penanganan'] ?? []),
                'dampak' => PelaporanDowntimeOptions::gabungDampak($isi['dampak'] ?? []),
                'evaluasi' => array_replace($this->evaluasiKosong(), $isi['evaluasi'] ?? []),
                'paraf' => $isi['paraf'] ?? [],
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ];

            set_time_limit(300);

            $pdf = Pdf::loadView(
                'pages.components.downtime.pelaporan-downtime.cetak-pelaporan-downtime-print',
                ['data' => $data]
            )->setPaper('A4', 'portrait');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak laporan down time.');

            return response()->streamDownload(
                fn () => print $pdf->output(),
                'pelaporan-downtime-' . $this->downtimeNo . '.pdf'
            );
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $exception->getMessage());

            return null;
        }
    }
};
?>

<div>
    <x-modal name="pelaporan-downtime-actions" size="full" height="full" focusable>
        <div class="flex flex-col h-full" wire:key="{{ $this->renderKey('modal', [$formMode, $downtimeNo]) }}">

            {{-- ══ HEADER ══ --}}
            <div class="px-6 py-4 border-b border-hairline dark:border-gray-700">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-base font-semibold text-ink dark:text-gray-100">
                                DT-01 &middot; Log Kejadian &amp; Penanganan Down Time SIMRS
                            </h2>
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                            @if ($downtimeNo > 0)
                                <x-badge variant="gray">No. {{ $downtimeNo }}</x-badge>
                            @endif
                        </div>
                        <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                            MRMIK 13.1 — Penanganan Down Time &middot; diisi Unit IT / Penyelenggara SIMRS
                        </p>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal" class="shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- ══ ISI ══ --}}
            <div class="flex-1 min-h-0 px-6 py-4 space-y-4 overflow-y-auto bg-surface-soft dark:bg-gray-950/20">
                @if (! $siapDipakai)
                    <div class="px-4 py-3 text-sm border rounded-2xl bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200">
                        Tabel <span class="font-mono">RSTXN_DOWNTIMES</span> belum dipasang di basis data.
                        Jalankan <span class="font-mono">docs/ddl-pelaporan-downtime.sql</span> lebih dulu.
                    </div>
                @else
                    @include('pages.downtime.pelaporan-downtime.pelaporan-downtime-actions-kejadian')
                    @include('pages.downtime.pelaporan-downtime.pelaporan-downtime-actions-penanganan')
                    @include('pages.downtime.pelaporan-downtime.pelaporan-downtime-actions-dampak')
                    @include('pages.downtime.pelaporan-downtime.pelaporan-downtime-actions-evaluasi')

                    @if ($formMode === 'edit' && filled($paraf['nama']))
                        <p class="text-xs text-muted dark:text-gray-400">
                            Direkam <span class="font-semibold">{{ $paraf['nama'] }}</span>
                            @if (filled($paraf['tanggal']))
                                &middot; <span class="font-mono">{{ $paraf['tanggal'] }}</span>
                            @endif
                            &mdash; paraf tetap milik perekam pertama walau laporan ini dikoreksi.
                            Tanda tangan Pelaksana, Verifikator &amp; Manajemen dibubuhkan di cetakan.
                        </p>
                    @endif
                @endif
            </div>

            {{-- ══ FOOTER ══ --}}
            <div class="sticky bottom-0 z-10 flex flex-wrap items-center justify-end gap-2 px-6 py-3 border-t border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700">
                @if ($siapDipakai)
                    <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $downtimeNo > 0 ? 'Simpan Laporan' : 'Simpan & Buat Laporan' }}</span>
                        <span wire:loading wire:target="save">Menyimpan...</span>
                    </x-primary-button>
                @endif
                @if ($siapDipakai && $downtimeNo > 0)
                    <x-outline-button type="button" wire:click="cetak" wire:loading.attr="disabled" wire:target="cetak"
                        class="!text-amber-600 !bg-amber-50 !border-amber-200 hover:!bg-amber-100 hover:!text-amber-700 hover:!border-amber-300 dark:!text-amber-400 dark:!bg-amber-900/20 dark:!border-amber-800/30 dark:hover:!bg-amber-900/30 dark:hover:!text-amber-300"
                        title="Cetak laporan ini">
                        <span wire:loading.remove wire:target="cetak">Cetak Laporan</span>
                        <span wire:loading wire:target="cetak" class="flex items-center gap-1"><x-loading class="w-4 h-4" /> Mencetak...</span>
                    </x-outline-button>
                @endif
                <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
            </div>
        </div>
    </x-modal>
</div>
