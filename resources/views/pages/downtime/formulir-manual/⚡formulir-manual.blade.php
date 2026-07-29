<?php

use App\Support\Downtime\FormulirDowntime;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Livewire\Attributes\Session;
use Livewire\Component;

// Katalog formulir manual waktu henti (down time) SIMRS.
// Sumber daftar formulir: App\Support\Downtime\FormulirDowntime.
// Halaman ini hanya menampilkan, mem-preview, dan mengunduh PDF — tidak menyimpan data.
new class extends Component {
    #[Session(key: 'downtime-formulir-tab')]
    public string $tab = 'semua';

    /** HTML dokumen cetak untuk iframe modal Lihat. */
    public string $previewHtml = '';

    /** Kode formulir yang sedang dipratinjau (dipakai tombol Cetak di modal). */
    public string $kodeAktif = '';

    public function mount(): void
    {
        // Tab tersimpan di session bisa memakai kunci area lama (mis. 'kasir-rj')
        // setelah daftar area berubah — kembalikan ke 'semua' supaya tidak kosong.
        if ($this->tab !== 'semua' && !array_key_exists($this->tab, FormulirDowntime::AREAS)) {
            $this->tab = 'semua';
        }
    }

    public function gantiTab(string $area): void
    {
        $this->tab = $area;
    }

    /** Daftar formulir sesuai tab aktif. */
    public function daftarTampil(): array
    {
        return FormulirDowntime::byArea($this->tab);
    }

    /** Jumlah formulir per area untuk badge tab. */
    public function jumlahArea(): array
    {
        return FormulirDowntime::jumlahPerArea();
    }

    /** Pratinjau satu formulir di modal (isi = persis hasil cetak PDF). */
    public function lihat(string $kode): void
    {
        $formulir = FormulirDowntime::find($kode);
        if (!$formulir) {
            $this->dispatch('toast', type: 'error', message: 'Formulir tidak ditemukan.');
            return;
        }

        $this->kodeAktif = $kode;
        $this->previewHtml = $this->htmlPratinjau([$formulir], $formulir['kode'] . ' — ' . $formulir['judul'], false);
        $this->dispatch('open-modal', name: 'view-formulir-downtime');
    }

    /** Unduh satu formulir sebagai PDF. */
    public function cetak(string $kode): mixed
    {
        $formulir = FormulirDowntime::find($kode);
        if (!$formulir) {
            $this->dispatch('toast', type: 'error', message: 'Formulir tidak ditemukan.');
            return null;
        }

        return $this->streamPdf(
            [$formulir],
            $formulir['kode'] . ' — ' . $formulir['judul'],
            'formulir-downtime-' . strtolower($formulir['kode']) . '.pdf',
            false,
        );
    }

    /** Unduh bundel: seluruh formulir pada tab aktif (bersampul + daftar isi). */
    public function unduhBundel(): mixed
    {
        $daftar = $this->daftarTampil();
        if (empty($daftar)) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada formulir untuk diunduh.');
            return null;
        }

        $labelArea = $this->tab === 'semua' ? 'Seluruh Area' : FormulirDowntime::labelArea($this->tab);
        $namaFile = 'bundel-formulir-downtime-' . ($this->tab === 'semua' ? 'lengkap' : $this->tab) . '.pdf';

        return $this->streamPdf($daftar, 'Bundel Formulir Manual Down Time SIMRS — ' . $labelArea, $namaFile, true);
    }

    /** Payload cetak seragam untuk view pembungkus. */
    private function dataCetak(array $daftar, string $judul, bool $sampul): array
    {
        return [
            'judul' => $judul,
            'daftar' => $daftar,
            'sampul' => $sampul,
            'dicetakOleh' => auth()->user()?->myuser_name ?: (auth()->user()?->name ?: '-'),
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y H:i'),
        ];
    }

    /** Stream PDF ke browser. */
    private function streamPdf(array $daftar, string $judul, string $namaFile, bool $sampul): mixed
    {
        // Bundel seluruh area (30+ formulir) berat di dompdf: ~2 menit & ~700 MB.
        // Batas dinaikkan hanya untuk request ini, bukan setelan global.
        set_time_limit(600);
        ini_set('memory_limit', '1024M');
        $pdf = Pdf::loadView('pages.downtime.cetak.cetak-formulir', $this->dataCetak($daftar, $judul, $sampul))
            ->setPaper('A4');

        return response()->streamDownload(fn() => print $pdf->output(), $namaFile);
    }

    /**
     * Render blade cetak jadi HTML self-contained untuk iframe pratinjau.
     * Gambar kop memakai path filesystem absolut (kebutuhan dompdf) — untuk
     * pratinjau di browser prefix tersebut dibuang agar jadi URL web relatif.
     */
    private function htmlPratinjau(array $daftar, string $judul, bool $sampul): string
    {
        $html = view('pages.downtime.cetak.cetak-formulir', $this->dataCetak($daftar, $judul, $sampul))->render();
        $html = str_replace(public_path(), '', $html);

        return str_replace('</head>', '<style>body{zoom:1.35}</style></head>', $html);
    }
};

?>

<div>
    <x-page-title title="Formulir Manual Down Time"
        subtitle="Formulir cadangan saat SIMRS tidak dapat diakses — unduh, gandakan, dan simpan di tiap unit" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TAB AREA --}}
            @php $jumlahArea = $this->jumlahArea(); @endphp
            <div class="pt-2">
                <x-tabs class="flex-wrap">
                    <x-tab :active="$tab === 'semua'" wire:click="gantiTab('semua')">
                        Semua ({{ count(App\Support\Downtime\FormulirDowntime::all()) }})
                    </x-tab>
                    @foreach (App\Support\Downtime\FormulirDowntime::AREAS as $areaKunci => $areaLabel)
                        <x-tab :active="$tab === $areaKunci" wire:click="gantiTab('{{ $areaKunci }}')">
                            {{ $areaLabel }} ({{ $jumlahArea[$areaKunci] ?? 0 }})
                        </x-tab>
                    @endforeach
                </x-tabs>
            </div>

            {{-- PANDUAN — panel biru standar, default tertutup --}}
            <details
                class="p-3 mt-2 text-sm border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800/40">
                <summary class="font-semibold text-blue-800 cursor-pointer dark:text-blue-300">
                    Panduan pemakaian formulir manual down time — klik untuk membaca
                </summary>
                <div class="mt-2 space-y-3 text-blue-900/80 dark:text-blue-200/80">
                    <p>
                        Waktu henti (<em>down time</em>) adalah kondisi sistem data / aplikasi SIMRS tidak dapat
                        diakses, baik terencana (maintenance, update) maupun tidak terencana (gangguan). Formulir di
                        halaman ini adalah prosedur alternatif supaya pelayanan pasien tetap berjalan dan data tetap
                        lengkap setelah sistem pulih.
                    </p>

                    <ul class="space-y-1.5" style="list-style: disc; padding-left: 18px">
                        <li>
                            <strong>Sebelum terjadi down time.</strong> Unduh bundel formulir area masing-masing,
                            gandakan secukupnya, dan simpan di map khusus di tiap unit. Jangan mengandalkan
                            pencetakan saat gangguan sudah terjadi — printer dan jaringan bisa ikut terdampak.
                        </li>
                        <li>
                            <strong>Down time terencana.</strong> Unit IT memberitahu seluruh unit minimal 3 hari
                            sebelumnya memakai formulir <strong>DT-02</strong>, sekaligus memastikan tiap unit sudah
                            menyiapkan formulir manual.
                        </li>
                        <li>
                            <strong>Saat down time berlangsung.</strong> Seluruh pencatatan dilakukan tulis tangan
                            pada formulir ini. Tulis jam kejadian sebenarnya — bukan jam entri — karena jam itulah
                            yang nanti dimasukkan ke SIMRS.
                        </li>
                        <li>
                            <strong>Setelah sistem pulih.</strong> Data manual dientri ulang oleh petugas berwenang
                            sesuai keterangan &ldquo;Entri ulang di&rdquo; pada tiap kartu, lalu tiap unit mengisi
                            <strong>DT-03</strong> (ceklist rekonsiliasi) dan Unit IT mengisi <strong>DT-01</strong>
                            (log kejadian &amp; evaluasi).
                        </li>
                        <li>
                            <strong>Urutan entri ulang yang aman.</strong> Pendaftaran &rarr; EMR (asesmen, diagnosa)
                            &rarr; penunjang &rarr; e-resep &rarr; administrasi &rarr; kasir. Untuk pasien UGD yang
                            lanjut rawat inap, selesaikan entri UGD lebih dulu baru pendaftaran rawat inap, supaya
                            biaya UGD tertarik ke tagihan RI dan tidak tertagih dua kali. Stok obat terpotong
                            otomatis saat e-resep dientri, jadi jangan melakukan penyesuaian stok manual lebih dulu.
                        </li>
                    </ul>

                    <p class="text-xs">
                        Setiap formulir memuat blok &ldquo;diisi setelah sistem pulih&rdquo; berisi waktu down time,
                        petugas entri ulang, dan paraf — blok inilah yang menjadi bukti rekonsiliasi data.
                    </p>
                </div>
            </details>

            {{-- TOOLBAR --}}
            <div class="flex flex-wrap items-center justify-between gap-3 mt-3">
                <div class="text-sm text-muted dark:text-gray-400">
                    Menampilkan <strong class="text-ink dark:text-gray-100">{{ count($this->daftarTampil()) }}</strong>
                    formulir
                    @if ($tab !== 'semua')
                        pada area <strong
                            class="text-ink dark:text-gray-100">{{ App\Support\Downtime\FormulirDowntime::labelArea($tab) }}</strong>
                    @endif
                </div>

                <x-primary-button type="button" wire:click="unduhBundel" wire:loading.attr="disabled"
                    wire:target="unduhBundel">
                    <span wire:loading.remove wire:target="unduhBundel" class="inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" />
                        </svg>
                        Unduh Bundel PDF {{ $tab === 'semua' ? '(semua area)' : '(area ini)' }}
                    </span>
                    <span wire:loading wire:target="unduhBundel" class="inline-flex items-center gap-1.5">
                        <x-loading /> Menyiapkan PDF...
                    </span>
                </x-primary-button>
            </div>

            {{-- DAFTAR FORMULIR --}}
            <div class="flex-1 min-h-0 mt-3 overflow-y-auto">
                <div class="grid grid-cols-1 gap-4 pb-2 lg:grid-cols-2 xl:grid-cols-3">
                    @foreach ($this->daftarTampil() as $formulir)
                        <div wire:key="form-{{ $formulir['kode'] }}"
                            class="flex flex-col p-4 bg-white border rounded-2xl border-hairline dark:bg-gray-900 dark:border-gray-700">

                            <div class="flex items-start justify-between gap-2">
                                <x-badge variant="brand">{{ $formulir['kode'] }}</x-badge>
                                <span class="text-xs text-muted dark:text-gray-400">
                                    {{ App\Support\Downtime\FormulirDowntime::labelArea($formulir['area']) }}
                                </span>
                            </div>

                            <h3 class="mt-2 text-base font-semibold leading-snug text-ink dark:text-gray-100">
                                {{ $formulir['judul'] }}
                            </h3>
                            <p class="mt-0.5 text-xs text-muted dark:text-gray-400">{{ $formulir['unit'] }}</p>

                            <p class="mt-2 text-sm text-body dark:text-gray-300">{{ $formulir['desc'] }}</p>

                            <ul class="mt-2 space-y-1 text-xs text-muted dark:text-gray-400"
                                style="list-style: disc; padding-left: 16px">
                                @foreach ($formulir['isi'] as $poin)
                                    <li>{{ $poin }}</li>
                                @endforeach
                            </ul>

                            <div
                                class="p-2 mt-3 text-xs border rounded-lg bg-warning-tint border-amber-200 text-warning-deep dark:bg-amber-900/20 dark:border-amber-800/40 dark:text-amber-200">
                                <span class="font-semibold">Entri ulang di:</span> {{ $formulir['entriUlang'] }}
                            </div>

                            <div class="flex items-center gap-2 mt-auto pt-3">
                                <x-outline-button type="button" wire:click="lihat('{{ $formulir['kode'] }}')"
                                    wire:loading.attr="disabled" wire:target="lihat('{{ $formulir['kode'] }}')"
                                    class="flex-1">
                                    Lihat
                                </x-outline-button>
                                <x-primary-button type="button" wire:click="cetak('{{ $formulir['kode'] }}')"
                                    wire:loading.attr="disabled" wire:target="cetak('{{ $formulir['kode'] }}')"
                                    class="flex-1">
                                    Unduh PDF
                                </x-primary-button>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if (empty($this->daftarTampil()))
                    <div class="py-16 text-sm text-center text-muted dark:text-gray-400">
                        Belum ada formulir untuk area ini.
                    </div>
                @endif
            </div>

        </div>
    </div>

    {{-- MODAL PRATINJAU --}}
    <x-rm.dokumen-view-modal name="view-formulir-downtime" title="Pratinjau Formulir Manual Down Time"
        :subtitle="$kodeAktif" :cetakId="$kodeAktif" :showCetak="filled($kodeAktif)" :previewHtml="$previewHtml" />
</div>
