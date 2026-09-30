<?php
// Modal tinjau + jawab satu permintaan rujukan masuk (SRBK sisi faskes tujuan).
//
// Baris dikirim utuh lewat event dari layar kotak masuk — modal TIDAK menarik
// ulang Task-nya, supaya membuka detail tidak memakan kuota API. Yang memanggil
// API di sini hanya keputusan: PATCH Task (status completed + output
// accepted/rejected), persis pola Postman V30062026.
//
// Setelah disetujui, perujuk masih harus mengirim ServiceRequest. Di sisi kita,
// persetujuan menyimpan JANJI rujukan (RujukanMasukTrait) — pendaftaran kunjungannya
// langkah terpisah, dikerjakan saat pasiennya tiba lewat daftar tunggu "Rujukan Masuk
// Disetujui" di layar Pendaftaran UGD.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Http\Traits\SATUSEHAT\SatuSehatRujukanTrait;
use App\Http\Traits\Txn\RujukanMasuk\RujukanMasukTrait;

new class extends Component {
    use SatuSehatRujukanTrait, RujukanMasukTrait;

    public array $permintaan = [];
    public bool $sedangKirim = false;

    #[On('rujukan-masuk-actions.open')]
    public function open(array $permintaan): void
    {
        $this->permintaan = $permintaan;
        $this->sedangKirim = false;
        $this->dispatch('open-modal', name: 'rujukan-masuk-actions');
    }

    public function menunggu(): bool
    {
        return ($this->permintaan['keputusan'] ?? '') === '' && ($this->permintaan['statusTask'] ?? '') !== 'cancelled';
    }

    public function setujui(): void
    {
        $this->jawab('accepted');
    }

    public function tolak(): void
    {
        $this->jawab('rejected');
    }

    private function jawab(string $keputusan): void
    {
        $taskId = (string) ($this->permintaan['taskId'] ?? '');
        if ($taskId === '') {
            $this->dispatch('toast', type: 'error', message: 'ID tugas rujukan tidak terbaca.');
            return;
        }
        if (!$this->menunggu()) {
            $this->dispatch('toast', type: 'error', message: 'Permintaan ini sudah dijawab atau dibatalkan oleh faskes perujuk.');
            return;
        }

        $this->sedangKirim = true;
        $hasil = $this->rujukanTaskRespon($taskId, $keputusan);
        $this->sedangKirim = false;

        if ($hasil['code'] < 200 || $hasil['code'] >= 300) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengirim keputusan [' . $hasil['code'] . '] ' . $this->ringkasError($hasil['body']));
            return;
        }

        // Tandai lokal supaya modal langsung memperlihatkan hasilnya walau
        // kotak masuk baru disegarkan sesudah ini.
        $this->permintaan['keputusan'] = $keputusan;
        $this->permintaan['statusTask'] = 'completed';

        // Yang DISETUJUI dicatat sebagai janji rujukan: petugas perlu daftar
        // "siapa yang ditunggu kedatangannya", dan saat pasiennya tiba inilah
        // bahan untuk mengisi pendaftaran + Encounter.basedOn. Yang ditolak tak
        // punya kelanjutan, jadi tak perlu dicatat.
        //
        // URUTANNYA SENGAJA SESUDAH PATCH BERHASIL. Persetujuan sudah sampai ke
        // SATUSEHAT dan tak bisa ditarik; kegagalan menulis ke basis data kita
        // TIDAK boleh membuat layar bilang persetujuannya gagal — petugas akan
        // menekan Setujui lagi, dan Task-nya sudah completed. Jadi kegagalannya
        // dilaporkan terpisah, apa adanya.
        if ($keputusan === 'accepted') {
            $catatan = $this->checkTabelRujukanMasuk()
                ? $this->catatRujukanMasukDisetujui($this->permintaan)
                : ['tersimpan' => false, 'sudahAda' => false, 'pesan' => 'Tabel RSTXN_RUJUKANMASUKS belum dipasang — jalankan docs/ddl-rujukan-masuk-disetujui.sql.'];

            if (!$catatan['tersimpan'] && !$catatan['sudahAda']) {
                $this->dispatch('toast', type: 'warning', message: 'Persetujuan sudah terkirim ke SATUSEHAT, tetapi gagal dicatat di SIRUS: ' . $catatan['pesan'] . ' Mohon catat nomor permintaannya secara manual.');
            }
        }

        $this->dispatch('toast', type: 'success', message: $keputusan === 'accepted' ? 'Permintaan rujukan disetujui dan telah dikirim ke SATUSEHAT.' : 'Permintaan rujukan ditolak dan telah dikirim ke SATUSEHAT.');
        $this->dispatch('rujukan-masuk.dijawab');
        $this->dispatch('close-modal', name: 'rujukan-masuk-actions');
    }

    public function waktuTampil(string $iso): string
    {
        if ($iso === '') {
            return '-';
        }

        try {
            return Carbon::parse($iso)->timezone(env('APP_TIMEZONE'))->format('d/m/Y H:i');
        } catch (\Throwable $e) {
            return $iso;
        }
    }

    public function ringkasError($body): string
    {
        if (is_string($body)) {
            return Str::limit($body, 180);
        }
        if (is_array($body)) {
            $pesan = $body['issue'][0]['details']['text'] ?? ($body['issue'][0]['diagnostics'] ?? ($body['message'] ?? null));
            if ($pesan) {
                return Str::limit((string) $pesan, 180);
            }
            return Str::limit(json_encode($body), 180);
        }

        return 'Tidak ada keterangan.';
    }
};
?>

<div>
    <x-modal name="rujukan-masuk-actions" size="full" height="full" focusable>
        @php
            $jalur = $permintaan['jalur'] ?? '';
            $keputusan = $permintaan['keputusan'] ?? '';
            $statusTask = $permintaan['statusTask'] ?? '';

            // CarePlan disensor SATUSEHAT ("No consent available") → nama pasien,
            // layanan, jalur, keterangan klinis & DPJP kosong SEKALIGUS. Bedakan dari
            // "perujuk memang tak mengisi", karena tindak lanjutnya beda: yang satu
            // urusan consent/perizinan, yang satu urusan kelengkapan data perujuk.
            $diblokir = (bool) ($permintaan['rencanaDiblokir'] ?? false);
            $dibatalkan = ($permintaan['statusTask'] ?? '') === 'cancelled';
            $kosongKarena = $diblokir
                ? ($dibatalkan ? '(dibatalkan perujuk)' : '(belum terbuka — menunggu persetujuan)')
                : '';
        @endphp

        {{-- Modal full: padding panel jadi p-0, jadi header/body/footer memakai padding
             sendiri — pola sama dengan modal EMR (screening, modul dokumen). --}}
        <div class="flex flex-col min-h-[calc(100vh-8rem)]">

            {{-- HEADER --}}
            <div class="flex flex-wrap items-start gap-3 px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="flex-1 min-w-0">
                    <h2 class="text-lg font-semibold text-ink dark:text-gray-100">
                        Permintaan Rujukan Masuk
                    </h2>
                    <p class="mt-1 text-sm text-muted dark:text-gray-400">
                        Tinjau data klinis dari faskes perujuk, lalu setujui atau tolak permintaan ini.
                        Keputusan Anda langsung dikirim ke SATUSEHAT dan diterima faskes perujuk.
                    </p>
                </div>
                <div class="flex flex-col items-end gap-1">
                    @if ($jalur === 'ranap')
                        <x-badge variant="info">Rawat Inap</x-badge>
                    @elseif ($jalur === 'igd')
                        <x-badge variant="danger">Gawat Darurat</x-badge>
                    @elseif ($diblokir)
                        <x-badge variant="warning">Jalur belum terbuka</x-badge>
                    @else
                        <x-badge variant="gray">Layanan tidak dikenali</x-badge>
                    @endif

                    @if ($statusTask === 'cancelled')
                        <x-badge variant="gray">Dibatalkan Perujuk</x-badge>
                    @elseif ($keputusan === 'accepted')
                        <x-badge variant="success">Disetujui</x-badge>
                    @elseif ($keputusan === 'rejected')
                        <x-badge variant="danger">Ditolak</x-badge>
                    @else
                        <x-badge variant="warning">Menunggu Jawaban</x-badge>
                    @endif
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex flex-col flex-1 gap-5 px-6 py-5 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">

                {{-- IDENTITAS & ASAL --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div
                        class="p-4 border bg-surface-soft border-hairline rounded-xl dark:bg-gray-800 dark:border-gray-700">
                        <div class="text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">Pasien
                        </div>
                        <div class="mt-1 font-semibold text-ink dark:text-gray-100">
                            {{ ($permintaan['pasienNama'] ?? '') !== '' ? $permintaan['pasienNama'] : ($kosongKarena ?: '(nama pasien tidak disertakan perujuk)') }}
                        </div>
                        <div class="text-sm text-muted dark:text-gray-400">
                            No. IHS: {{ ($permintaan['pasienId'] ?? '') !== '' ? $permintaan['pasienId'] : '-' }}
                        </div>
                    </div>

                    <div
                        class="p-4 border bg-surface-soft border-hairline rounded-xl dark:bg-gray-800 dark:border-gray-700">
                        <div class="text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">Faskes
                            Perujuk
                        </div>
                        <div class="mt-1 font-semibold text-ink dark:text-gray-100">
                            {{ ($permintaan['perujukNama'] ?? '') !== '' ? $permintaan['perujukNama'] : '(nama faskes belum terbaca)' }}
                        </div>
                        <div class="text-sm text-muted dark:text-gray-400">
                            ID Organisasi: {{ ($permintaan['perujukOrgId'] ?? '') !== '' ? $permintaan['perujukOrgId'] : '-' }}
                        </div>
                        @if (($permintaan['dokterPerujuk'] ?? '') !== '')
                            <div class="text-sm text-muted dark:text-gray-400">Dokter perujuk: {{ $permintaan['dokterPerujuk'] }}
                            </div>
                        @endif
                    </div>
                </div>

                {{-- PERMINTAAN LAYANAN --}}
                <div class="p-4 border bg-canvas border-hairline rounded-xl dark:bg-gray-900 dark:border-gray-700">
                    <div class="text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">
                        Layanan yang Diminta
                    </div>
                    <div class="mt-1 font-medium text-ink dark:text-gray-100">
                        {{ ($permintaan['layananNama'] ?? '') !== '' ? $permintaan['layananNama'] : ($kosongKarena ?: '-') }}
                        @if (($permintaan['layananKode'] ?? '') !== '')
                            <span class="text-sm font-normal text-muted-soft">({{ $permintaan['layananKode'] }})</span>
                        @endif
                    </div>

                    <div class="mt-3 text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">
                        Keterangan Klinis Perujuk
                    </div>
                    <p class="mt-1 text-sm whitespace-pre-line text-body dark:text-gray-200">
                        {{ ($permintaan['deskripsi'] ?? '') !== ''
                            ? $permintaan['deskripsi']
                            : ($diblokir
                                ? 'Keterangan klinis belum dapat ditampilkan — lihat catatan di bawah.'
                                : 'Faskes perujuk tidak menyertakan keterangan klinis.') }}
                    </p>

                    <div class="mt-3 text-sm text-muted dark:text-gray-400">
                        Waktu permintaan: {{ $this->waktuTampil($permintaan['waktu'] ?? '') }}
                        · No. Permintaan:
                        {{ ($permintaan['noPermintaan'] ?? '') !== '' ? $permintaan['noPermintaan'] : '-' }}
                    </div>
                </div>

                {{-- DATA KLINIS DISENSOR — jangan sampai petugas mengira perujuk yang lalai,
                 dan jangan sampai ia menjawab tanpa sadar sedang menjawab tanpa data. --}}
                @if ($diblokir)
                    <div
                        class="p-4 text-sm border rounded-xl bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200">
                        <div class="font-semibold">Detail klinis baru terbuka setelah disetujui</div>
                        <p class="mt-1">
                            SATUSEHAT membalas <span class="font-mono text-xs">No consent available</span> untuk
                            CarePlan rujukan ini, sehingga layanan yang diminta, jalur, dan keterangan klinis belum
                            dapat ditampilkan. Ini <strong>hal yang wajar</strong>, bukan gangguan sistem dan bukan
                            berarti perujuk tidak mengisinya: datanya baru terbuka setelah permintaan dijawab.
                        </p>
                        <p class="mt-2">
                            Dengan demikian, keputusan di bawah diambil tanpa data klinis. Bila perlu kepastian,
                            silakan konfirmasi terlebih dahulu kepada faskes perujuk melalui jalur komunikasi RS.
                        </p>
                    </div>
                @endif

                {{-- RINCIAN TEKNIS — bekal saat harus lapor Issue Tracker --}}
                <details
                    class="p-4 border bg-surface-soft border-hairline rounded-xl dark:bg-gray-800 dark:border-gray-700">
                    <summary class="text-sm font-medium cursor-pointer text-muted dark:text-gray-300">
                        Rincian teknis (referensi FHIR)
                    </summary>
                    <dl class="grid grid-cols-1 gap-2 mt-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-muted-soft">Task ID</dt>
                            <dd class="break-all text-body dark:text-gray-200">{{ $permintaan['taskId'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-soft">CarePlan ID</dt>
                            <dd class="break-all text-body dark:text-gray-200">{{ $permintaan['rencanaId'] ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-soft">Encounter perujuk</dt>
                            <dd class="break-all text-body dark:text-gray-200">
                                {{ ($permintaan['encounterId'] ?? '') !== '' ? $permintaan['encounterId'] : '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-soft">Condition (diagnosa awal)</dt>
                            <dd class="break-all text-body dark:text-gray-200">
                                {{ ($permintaan['diagnosaId'] ?? '') !== '' ? $permintaan['diagnosaId'] : '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-soft">Status Task</dt>
                            <dd class="text-body dark:text-gray-200">{{ $statusTask !== '' ? $statusTask : '-' }}</dd>
                        </div>
                    </dl>
                </details>

                @if (!$this->menunggu())
                    <div
                        class="p-3 text-sm border rounded-xl bg-blue-50 border-blue-200 text-info-deep dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-200">
                        @if ($statusTask === 'cancelled')
                            Faskes perujuk telah membatalkan permintaan ini, sehingga tidak dapat dijawab lagi.
                        @else
                            Permintaan ini sudah dijawab dan keputusannya tidak dapat diubah. Bila kondisi pasien
                            berubah, faskes perujuk perlu mengirim permintaan baru.
                        @endif
                    </div>
                @endif

            </div>{{-- /BODY --}}

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 flex flex-wrap justify-end gap-2 px-6 py-4 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                <x-secondary-button type="button"
                    x-on:click="$dispatch('close-modal', { name: 'rujukan-masuk-actions' })">
                    Tutup
                </x-secondary-button>

                @if ($this->menunggu())
                    <x-confirm-button variant="danger" action="tolak()" title="Tolak permintaan rujukan ini?"
                        message="Penolakan akan langsung dikirim ke SATUSEHAT dan diterima faskes perujuk. Sebaiknya alasan penolakan sudah disampaikan kepada faskes perujuk melalui jalur komunikasi RS."
                        confirmText="Ya, Tolak" wire:key="tolak-{{ $permintaan['taskId'] ?? 'x' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        Tolak Rujukan
                    </x-confirm-button>

                    <x-confirm-button variant="primary" action="setujui()" title="Setujui permintaan rujukan ini?"
                        message="Pastikan tempat tidur dan layanan yang diminta tersedia. Persetujuan akan langsung dikirim ke SATUSEHAT dan diterima faskes perujuk."
                        confirmText="Ya, Setujui" wire:key="setujui-{{ $permintaan['taskId'] ?? 'x' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                        Setujui Rujukan
                    </x-confirm-button>
                @endif
            </div>

        </div>
    </x-modal>
</div>
