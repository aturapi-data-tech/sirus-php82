<?php
// Komponen Modal Satu Sehat UGD — pola sama satu-sehat-rj-actions.
// Trigger dari parent: dispatch event 'daftar-ugd.satu-sehat.open' dengan rjNo (= rj_no ugdhdrs).
// CATATAN UGD (beda dari RJ): Encounter class EMER, tanpa poli (lokasi via env/DB),
//   anamnesa tanpa SNOMED → tidak ada kartu Chief Complaint & Allergy.

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;

new class extends Component {
    use EmrUGDTrait;

    public ?string $rjNo = null;
    public array $dataDaftarUGD = [];

    /**
     * URUTAN KANONIK "Kirim Semua". Bukan sekadar urutan tampilan — ada
     * ketergantungan keras di dalamnya:
     *   - encounter WAJIB pertama; semua resource lain menunjuk padanya;
     *   - medication-dispense butuh medication-request sudah terkirim (ia
     *     menautkan penyerahan ke resep lewat id yang tersimpan);
     *   - encounter-selesai butuh condition (SATUSEHAT mewajibkan
     *     Encounter.diagnosis saat finish, RuleNumber 10457);
     *   - resume-medis paling akhir, sesudah encounter finished dan sesudah semua
     *     resource yang dirangkumnya ada.
     * Mengubah urutan di sini berarti mengubah perilaku kirim, bukan tata letak.
     */
    private const URUTAN_KIRIM = [
        'encounter', 'condition', 'observation', 'procedure', 'medication-request',
        'medication-dispense', 'lab', 'radiologi', 'clinical-impression',
        'chief-complaint', 'allergy', 'penilaian', 'encounter-selesai', 'resume-medis',
    ];

    /** Sisa langkah yang belum dijalankan; kosong = tidak ada rantai berjalan. */
    public array $antrianKirim = [];

    /** Langkah yang sedang ditunggu kabarnya. Dikosongkan begitu kabarnya datang. */
    public string $langkahAktif = '';

    public function kirimSemua(): void
    {
        if (empty($this->rjNo)) {
            return;
        }

        // Sekali jalan saja. Tanpa penjaga ini, klik kedua MERESTART antrean dari
        // awal sementara langkah pertama masih di udara — dua rantai berjalan
        // bergantian dan langkah yang sama berangkat dua kali ke SATUSEHAT.
        if (!empty($this->antrianKirim) || $this->langkahAktif !== '') {
            $this->dispatch('toast', type: 'info', message: 'Kirim Semua sedang berjalan. Tunggu selesai, atau tekan Hentikan.');
            return;
        }

        $this->antrianKirim = self::URUTAN_KIRIM;
        $this->langkahAktif = '';
        $this->jalankanLangkahBerikutnya();
    }

    public function batalkanKirimSemua(): void
    {
        $this->antrianKirim = [];
        $this->langkahAktif = '';
        $this->dispatch('toast', type: 'info', message: 'Kirim Semua dihentikan. Langkah yang sudah berjalan tidak dibatalkan.');
    }

    /**
     * Satu langkah dijalankan per putaran, TIDAK ditembakkan serentak. Kalau 14
     * event dilepas sekaligus, Livewire menjalankannya paralel dan Condition bisa
     * berangkat sebelum Encounter-nya ada.
     */
    private function jalankanLangkahBerikutnya(): void
    {
        $langkah = array_shift($this->antrianKirim);
        if ($langkah === null) {
            $this->langkahAktif = '';
            $this->dispatch('toast', type: 'success', message: 'Kirim Semua selesai. Periksa status tiap langkah di bawah.');
            return;
        }

        $this->langkahAktif = $langkah;

        if ($langkah === 'encounter-selesai') {
            $this->dispatch('ss-encounter-ugd.finish', rjNo: $this->rjNo);
            return;
        }

        $this->dispatch('ss-' . $langkah . '-ugd.kirim', rjNo: $this->rjNo);
    }

    /**
     * Kabar selesai dari sebuah langkah, dicocokkan dengan yang SEDANG ditunggu
     * lalu penandanya dikosongkan. Di UGD kartu Encounter memang hanya dirender
     * sekali (tombol Kirim & Finish menyatu), tapi pencocokan ini tetap dipasang:
     * ia juga menangkal laporan susulan dari tombol Kirim satuan yang ditekan
     * petugas di tengah rantai berjalan.
     */
    #[On('ugd-satu-sehat.langkah-selesai')]
    public function langkahSelesai(string $langkah): void
    {
        if ($this->langkahAktif === '' || $langkah !== $this->langkahAktif) {
            return;
        }

        $this->langkahAktif = '';
        $this->jalankanLangkahBerikutnya();
    }

    public function mount(?string $initialRjNo = null): void
    {
        if (!empty($initialRjNo)) {
            $this->rjNo = $initialRjNo;
            $this->loadData();
        }
    }

    #[On('daftar-ugd.satu-sehat.open')]
    public function handleOpenSatuSehat(string $rjNo): void
    {
        // Antrean SELALU direset saat modal dibuka. Rantai yang tersangkut pada
        // pasien sebelumnya tidak boleh ikut terbawa: langkah berikutnya akan
        // dikirim atas nama pasien yang SEDANG dibuka, bukan pemilik aslinya.
        $this->antrianKirim = [];
        $this->langkahAktif = '';

        $this->rjNo = $rjNo;

        if (!$this->loadData()) {
            return;
        }

        $this->dispatch('open-modal', name: 'ugd-satu-sehat');
    }

    #[On('ugd-satu-sehat.refresh')]
    public function onRefresh(string $rjNo): void
    {
        if ((string) $this->rjNo !== $rjNo) {
            return;
        }
        $this->loadData();
    }

    private function loadData(): bool
    {
        if (empty($this->rjNo)) {
            return false;
        }
        $data = $this->findDataUGD($this->rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return false;
        }
        $this->dataDaftarUGD = $data;
        return true;
    }
};
?>

<div>
    <x-modal name="ugd-satu-sehat" size="full" height="full" focusable>
        <div class="flex flex-col min-h-0">
            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-teal-500/10 dark:bg-teal-400/15">
                                <svg class="w-6 h-6 text-teal-600 dark:text-teal-400" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="font-semibold text-2xl text-ink dark:text-gray-100">Kirim Satu Sehat
                                    <span class="text-sm font-normal text-muted dark:text-gray-400">— UGD (Emergency)</span>
                                </h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    <span class="font-semibold">{{ $dataDaftarUGD['regName'] ?? '-' }}</span>
                                    &mdash; RM: {{ $dataDaftarUGD['regNo'] ?? '-' }}
                                    &mdash; UGD: {{ $rjNo ?? '-' }}
                                </p>
                            </div>
                        </div>
                    </div>
                    {{-- KIRIM SEMUA — menjalankan langkah 1..14 berurutan, satu per putaran.
                         Sengaja TIDAK memakai orkestrator lama KirimRawatJalanTrait (583 baris,
                         tak dipakai siapa pun): ia salinan logika terpisah yang tidak ikut
                         menerima perbaikan pemulihan duplikat, penyimpanan hasil parsial,
                         maupun guard tanggal masuk kosong. --}}
                    <div class="flex items-center gap-2 ml-auto">
                        @if (!empty($antrianKirim) || $langkahAktif !== '')
                            {{-- Spinner dirender apa adanya, TIDAK lewat wire:loading: tiap langkah
                                 dijalankan oleh komponen ANAK, jadi wadah ini sendiri tidak sedang
                                 memuat dan wire:loading di sini takkan pernah menyala. Selama antrean
                                 belum habis, prosesnya memang sedang berjalan — itu yang ditandai. --}}
                            <span class="inline-flex items-center gap-1.5 text-xs text-muted dark:text-gray-400">
                                <x-loading class="text-teal-600 dark:text-teal-400" />
                                Mengirim <span class="font-semibold">{{ $langkahAktif ?: '…' }}</span>
                                · sisa {{ count($antrianKirim) }} langkah
                            </span>
                            <x-outline-button type="button" wire:click="batalkanKirimSemua">
                                <span class="inline-flex items-center gap-1.5">
                                    <x-satu-sehat.ikon-tombol jenis="hentikan" />
                                    Hentikan
                                </span>
                            </x-outline-button>
                        @else
                            <x-confirm-button variant="primary" action="kirimSemua()"
                                title="Kirim semua langkah ke SATUSEHAT?"
                                message="14 langkah dijalankan berurutan mulai dari Encounter. Langkah yang datanya belum lengkap akan ditolak dan dilewati — rantai tetap lanjut. Kiriman yang sudah berangkat TIDAK bisa dibatalkan."
                                confirmText="Ya, Kirim Semua"
                                wire:key="kirim-semua-ugd-{{ $rjNo ?? 'none' }}">
                                <span class="inline-flex items-center gap-1.5">
                                    <x-satu-sehat.ikon-tombol />
                                    Kirim Semua
                                </span>
                            </x-confirm-button>
                        @endif
                    </div>

                    <x-icon-button color="gray" type="button"
                        x-on:click="$dispatch('close-modal', { name: 'ugd-satu-sehat' })">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY — SFC self-contained (UGD: tanpa Chief Complaint & Allergy, anamnesa tanpa SNOMED) --}}
            <div class="relative flex-1 px-6 py-6 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">

                {{-- PENGHALANG selama Kirim Semua berjalan. Tiap langkah menunggu jawaban
                     SATUSEHAT, jadi jendela untuk salah klik lebar: tanpa ini petugas bisa
                     menekan Kirim di kartu lain di tengah rantai dan mengirim satu langkah
                     dua kali. Dipasang di wadah, bukan menonaktifkan 41 tombol satu per
                     satu — dan sekalian jadi penanda visual bahwa proses sedang jalan.
                     Header di atasnya tetap bisa diklik, jadi tombol Hentikan tetap hidup. --}}
                @if (!empty($antrianKirim) || $langkahAktif !== '')
                    <div class="absolute inset-0 z-20 cursor-not-allowed bg-surface-soft/60 dark:bg-gray-950/40"
                        title="Kirim Semua sedang berjalan — tekan Hentikan di atas untuk membatalkan sisanya.">
                    </div>
                @endif

                {{-- Grid, bukan tumpukan: modal ini full-width dan 13-15 kartu berderet ke
                     bawah menyisakan dua pertiga layar kosong. items-start supaya kartu
                     yang pratinjaunya dibuka memanjang sendiri, tidak ikut menarik tinggi
                     tetangga sebarisnya. --}}
                <div class="grid items-start max-w-7xl gap-3 mx-auto grid-cols-1 md:grid-cols-2 xl:grid-cols-3">
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-encounter :rjNo="$rjNo"
                        wire:key="ss-encounter-ugd-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-condition :rjNo="$rjNo"
                        wire:key="ss-condition-ugd-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-observation :rjNo="$rjNo"
                        wire:key="ss-observation-ugd-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-procedure :rjNo="$rjNo"
                        wire:key="ss-procedure-ugd-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-medication-request :rjNo="$rjNo"
                        wire:key="ss-medication-request-ugd-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-medication-dispense :rjNo="$rjNo"
                        wire:key="ss-medication-dispense-ugd-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-lab :rjNo="$rjNo"
                        wire:key="ss-lab-ugd-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-radiologi :rjNo="$rjNo"
                        wire:key="ss-radiologi-ugd-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-clinical-impression :rjNo="$rjNo"
                        wire:key="ss-clinical-impression-ugd-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-chief-complaint :rjNo="$rjNo"
                        wire:key="ss-chief-complaint-ugd-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-allergy :rjNo="$rjNo"
                        wire:key="ss-allergy-ugd-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-penilaian :rjNo="$rjNo"
                        wire:key="ss-penilaian-ugd-{{ $rjNo ?? 'none' }}" />

                    {{-- Resume medis dikirim SESUDAH encounter finished (playbook IGD),
                         jadi kartunya paling bawah. Susunan section IGD beda dari RJ. --}}
                    <div class="md:col-span-2 xl:col-span-3">
                        <livewire:pages::transaksi.ugd.satu-sehat.kirim-resume-medis :rjNo="$rjNo"
                            wire:key="ss-resume-medis-ugd-{{ $rjNo ?? 'none' }}" />
                    </div>
                </div>
            </div>
        </div>
    </x-modal>
</div>
