<?php
// Komponen Modal Satu Sehat RI (Rawat Inap).
// Port dari satu-sehat-rj-actions. Trigger: event 'daftar-ri.satu-sehat.open' dengan riHdrNo.
// Body memuat SFC self-contained per resource (Encounter dulu; resource lain menyusul).

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Ri\EmrRITrait;

new class extends Component {
    use EmrRITrait;

    public ?string $riHdrNo = null;
    /** Identitas pasien untuk judul panel — bukan dokumennya. */
    public string $regName = '';
    public string $regNoPasien = '';
    public string $roomDesc = '';

    /**
     * URUTAN KANONIK "Kirim Semua". Bukan sekadar urutan tampilan — ada
     * ketergantungan keras di dalamnya:
     *   - encounter WAJIB pertama; semua resource lain menunjuk padanya;
     *   - episode dibuat sesudah encounter, karena ia merujuk encounter itu;
     *   - medication-dispense butuh medication-request sudah terkirim;
     *   - encounter-selesai butuh condition (SATUSEHAT mewajibkan
     *     Encounter.diagnosis saat finish, RuleNumber 10457);
     *   - episode-selesai PALING AKHIR: episode membungkus seluruh masa rawat,
     *     jadi ia baru ditutup setelah encounter-nya sendiri ditutup;
     *   - resume-medis sesudah keduanya: Composition merangkum resource yang sudah terkirim dan
     *     dikirim sesudah kunjungan ditutup.
     * chief-complaint & allergy kini aktif (kodenya diisi di layar Coding SATUSEHAT RI, node
     * kodingSatuSehat) — dulu dimatikan menunggu LOV SNOMED di Pengkajian Dokter.
     */
    private const URUTAN_KIRIM = [
        'encounter', 'episode', 'condition', 'procedure', 'observation', 'medication-request',
        'chief-complaint', 'keluhan-penyerta', 'riwayat-penyakit', 'riwayat-keluarga',
        'riwayat-pengobatan', 'riwayat-perjalanan', 'allergy', 'medication-dispense',
        'observasi-lanjutan', 'lab', 'radiologi', 'cppt', 'penilaian', 'status-psikologis', 'diet',
        'edukasi', 'perencanaan-pulang', 'prognosis', 'rencana-tindak-lanjut', 'kondisi-pulang',
        'encounter-selesai', 'episode-selesai', 'resume-medis',
    ];

    /** Sisa langkah yang belum dijalankan; kosong = tidak ada rantai berjalan. */
    public array $antrianKirim = [];

    /** Langkah yang sedang ditunggu kabarnya. Dikosongkan begitu kabarnya datang. */
    public string $langkahAktif = '';

    /** Dokumen dibaca sebagai variabel LOKAL; hanya irisan di bawah ini yang disimpan. */
    private function muatDariDokumen(array $data): void
    {
        $this->regName = (string) ($data['regName'] ?? '');
        $this->regNoPasien = (string) ($data['regNo'] ?? '');
        $this->roomDesc = (string) ($data['roomDesc'] ?? '');
    }

    public function kirimSemua(): void
    {
        if (empty($this->riHdrNo)) {
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
     * Satu langkah dijalankan per putaran, TIDAK ditembakkan serentak. Kalau 15
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

        // Dua langkah penutup RI memakai event finish, bukan kirim.
        if (str_ends_with($langkah, '-selesai')) {
            $this->dispatch('ss-' . str_replace('-selesai', '', $langkah) . '-ri.finish', riHdrNo: $this->riHdrNo);
            return;
        }

        $this->dispatch('ss-' . $langkah . '-ri.kirim', riHdrNo: $this->riHdrNo);
    }

    /**
     * Kabar selesai dari sebuah langkah, dicocokkan dengan yang SEDANG ditunggu
     * lalu penandanya dikosongkan. Pencocokan ini juga menangkal laporan susulan
     * dari tombol Kirim satuan yang ditekan petugas di tengah rantai berjalan.
     */
    #[On('ri-satu-sehat.langkah-selesai')]
    public function langkahSelesai(string $langkah): void
    {
        if ($this->langkahAktif === '' || $langkah !== $this->langkahAktif) {
            return;
        }

        $this->langkahAktif = '';
        $this->jalankanLangkahBerikutnya();
    }

    public function mount(?string $initialRiHdrNo = null): void
    {
        if (!empty($initialRiHdrNo)) {
            $this->riHdrNo = $initialRiHdrNo;
            $this->loadData();
        }
    }

    #[On('daftar-ri.satu-sehat.open')]
    public function handleOpenSatuSehat(string $riHdrNo): void
    {
        // Antrean SELALU direset saat modal dibuka. Rantai yang tersangkut pada
        // pasien sebelumnya tidak boleh ikut terbawa: langkah berikutnya akan
        // dikirim atas nama pasien yang SEDANG dibuka, bukan pemilik aslinya.
        $this->antrianKirim = [];
        $this->langkahAktif = '';

        $this->riHdrNo = $riHdrNo;

        if (!$this->loadData()) {
            return;
        }

        $this->dispatch('open-modal', name: 'ri-satu-sehat');
    }

    /**
     * Tutup lewat server supaya penanda kunjungan dikosongkan: isi modal dibungkus
     * guard if($riHdrNo) di template, jadi seluruh langkah SATUSEHAT ikut dihapus saat tutup, bukan
     * tinggal tersembunyi. Antrean kirim ikut dikosongkan (sama seperti saat buka).
     * Escape / klik backdrop tetap menutup di sisi Alpine saja.
     */
    public function closeModal(): void
    {
        $this->antrianKirim = [];
        $this->langkahAktif = '';
        $this->riHdrNo = null;
        $this->regName = '';
        $this->regNoPasien = '';
        $this->roomDesc = '';
        $this->dispatch('close-modal', name: 'ri-satu-sehat');
    }

    #[On('ri-satu-sehat.refresh')]
    public function onRefresh(string $riHdrNo): void
    {
        if ((string) $this->riHdrNo !== $riHdrNo) {
            return;
        }
        $this->loadData();
    }

    private function loadData(): bool
    {
        if (empty($this->riHdrNo)) {
            return false;
        }
        $data = $this->findDataRI($this->riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.');
            return false;
        }
        $this->muatDariDokumen($data);
        return true;
    }
};
?>

<div>
    <x-modal name="ri-satu-sehat" size="full" height="full" focusable>
        {{-- Isi modal hanya di-mount saat ada pasien: tertutup = nol komponen, buka = mount sekali
             (anak memuat datanya dari prop), tutup = dihapus tanpa mount ulang. --}}
        @if ($riHdrNo)
        <div class="flex flex-col min-h-0">
            {{-- HEADER --}}
            <x-modul-dokumen.header judul="Kirim Satu Sehat" ikon="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" jalur="RI">
                <span class="font-semibold">{{ $regName ?: '-' }}</span>
                &mdash; RM: {{ $regNoPasien ?: '-' }}
                &mdash; RI: {{ $riHdrNo ?? '-' }}
                @if (filled($roomDesc))
                    &mdash; Kamar: {{ $roomDesc }}
                @endif
            </x-modul-dokumen.header>
            {{-- KIRIM SEMUA — menjalankan langkah 1..15 berurutan, satu per putaran.
                 Sengaja TIDAK memakai orkestrator lama KirimRawatJalanTrait (583 baris,
                 tak dipakai siapa pun): ia salinan logika terpisah yang tidak ikut
                 menerima perbaikan pemulihan duplikat, penyimpanan hasil parsial,
                 maupun guard tanggal masuk kosong. --}}
            <div class="flex flex-wrap items-center justify-end gap-2 px-6 py-2 border-b border-hairline dark:border-gray-700">
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
                    <x-danger-button type="button" wire:click="batalkanKirimSemua">
                        <span class="inline-flex items-center gap-1.5">
                            <x-satu-sehat.ikon-tombol jenis="hentikan" />
                            Hentikan
                        </span>
                    </x-danger-button>
                @else
                    <x-confirm-button variant="primary" action="kirimSemua()"
                        title="Kirim semua langkah ke SATUSEHAT?"
                        message="Semua langkah dijalankan berurutan mulai dari Encounter, ditutup Finish Encounter, Finish Episode, lalu Resume Medis. Langkah yang datanya belum lengkap akan ditolak dan dilewati — rantai tetap lanjut. Kiriman yang sudah berangkat TIDAK bisa dibatalkan."
                        confirmText="Ya, Kirim Semua"
                        wire:key="kirim-semua-ri-{{ $riHdrNo ?? 'none' }}">
                        <span class="inline-flex items-center gap-1.5">
                            <x-satu-sehat.ikon-tombol />
                            Kirim Semua
                        </span>
                    </x-confirm-button>
                @endif
            </div>

            {{-- BODY — SFC self-contained per resource --}}
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
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-encounter :riHdrNo="$riHdrNo"
                        wire:key="ss-encounter-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-episode :riHdrNo="$riHdrNo"
                        wire:key="ss-episode-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-condition :riHdrNo="$riHdrNo"
                        wire:key="ss-condition-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-procedure :riHdrNo="$riHdrNo"
                        wire:key="ss-procedure-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-observation :riHdrNo="$riHdrNo"
                        wire:key="ss-observation-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-medication-request :riHdrNo="$riHdrNo"
                        wire:key="ss-medication-request-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-chief-complaint :riHdrNo="$riHdrNo"
                        wire:key="ss-chief-complaint-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-keluhan-penyerta :riHdrNo="$riHdrNo"
                        wire:key="ss-keluhan-penyerta-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-riwayat-penyakit :riHdrNo="$riHdrNo"
                        wire:key="ss-riwayat-penyakit-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-riwayat-keluarga :riHdrNo="$riHdrNo"
                        wire:key="ss-riwayat-keluarga-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-riwayat-pengobatan :riHdrNo="$riHdrNo"
                        wire:key="ss-riwayat-pengobatan-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-riwayat-perjalanan :riHdrNo="$riHdrNo"
                        wire:key="ss-riwayat-perjalanan-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-allergy :riHdrNo="$riHdrNo"
                        wire:key="ss-allergy-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-medication-dispense :riHdrNo="$riHdrNo"
                        wire:key="ss-medication-dispense-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-observasi-lanjutan :riHdrNo="$riHdrNo"
                        wire:key="ss-observasi-lanjutan-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-lab :riHdrNo="$riHdrNo"
                        wire:key="ss-lab-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-radiologi :riHdrNo="$riHdrNo"
                        wire:key="ss-radiologi-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-cppt :riHdrNo="$riHdrNo"
                        wire:key="ss-cppt-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-penilaian :riHdrNo="$riHdrNo"
                        wire:key="ss-penilaian-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-status-psikologis :riHdrNo="$riHdrNo"
                        wire:key="ss-status-psikologis-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-diet :riHdrNo="$riHdrNo"
                        wire:key="ss-diet-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-edukasi :riHdrNo="$riHdrNo"
                        wire:key="ss-edukasi-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-perencanaan-pulang :riHdrNo="$riHdrNo"
                        wire:key="ss-perencanaan-pulang-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-prognosis :riHdrNo="$riHdrNo"
                        wire:key="ss-prognosis-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-rencana-tindak-lanjut :riHdrNo="$riHdrNo"
                        wire:key="ss-rencana-tindak-lanjut-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-kondisi-pulang :riHdrNo="$riHdrNo"
                        wire:key="ss-kondisi-pulang-ri-{{ $riHdrNo ?? 'none' }}" />

                    {{-- Resume medis dikirim SESUDAH encounter & episode ditutup — kartunya paling bawah. --}}
                    <div class="md:col-span-2 xl:col-span-3">
                        <livewire:pages::transaksi.ri.satu-sehat.kirim-resume-medis :riHdrNo="$riHdrNo"
                            wire:key="ss-resume-medis-ri-{{ $riHdrNo ?? 'none' }}" />
                    </div>
                </div>
            </div>
        </div>
        @endif
    </x-modal>
</div>
