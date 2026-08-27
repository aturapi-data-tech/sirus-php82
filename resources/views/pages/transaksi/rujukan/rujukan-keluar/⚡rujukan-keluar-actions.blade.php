<?php
// Modal rincian satu rujukan KELUAR (SRBK sisi faskes perujuk). HANYA BACA.
//
// Baris dikirim utuh lewat event dari layar pemantauan — MEMBUKA modal tidak
// menarik ulang Task, supaya melihat detail tidak memakan kuota API.
//
// Satu-satunya panggilan API di sini: tombol "Cek Status" di footer, dan hanya
// bila ditekan. Ia GET Task (rujukanGetTask) — membaca, bukan mengubah apa pun di
// SATUSEHAT — lalu menyegarkan status di modal DAN baris di layar pemantauan.
// Tombolnya cuma muncul selagi rujukan belum dijawab; begitu ada keputusan atau
// tugasnya dibatalkan, status sudah final dan mengecek ulang tak ada gunanya.
//
// Membatalkan tugas TETAP tidak ada di sini. Secara teknis bisa (rujukanTaskCancel
// di trait), tapi itu tindakan yang terbaca RS tujuan dan mengubah alur pelayanan
// pasien — bukan sesuatu yang pantas menempel diam-diam pada layar pemantauan.
// Tempatnya di panel rujukan EMR pasien, bersama konfirmasinya.

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Http\Traits\SATUSEHAT\SatuSehatRujukanTrait;

new class extends Component {
    use SatuSehatRujukanTrait;

    public array $rujukan = [];

    #[On('rujukan-keluar-actions.open')]
    public function open(array $rujukan): void
    {
        $this->rujukan = $rujukan;
        $this->dispatch('open-modal', name: 'rujukan-keluar-actions');
    }

    /** Belum dijawab = belum ada keputusan DAN tugasnya belum dibatalkan. */
    public function belumDijawab(): bool
    {
        return ($this->rujukan['keputusan'] ?? '') === ''
            && ($this->rujukan['statusTask'] ?? '') !== 'cancelled';
    }

    /**
     * Tanyakan ulang satu Task ke SATUSEHAT. Dipanggil HANYA saat tombol ditekan —
     * SATUSEHAT tidak memberi tahu sendiri saat RS tujuan menjawab.
     */
    public function cekStatus(): void
    {
        $taskId = (string) ($this->rujukan['taskId'] ?? '');
        if ($taskId === '') {
            $this->dispatch('toast', type: 'error', message: 'Task ID rujukan ini tidak diketahui, status tak bisa dicek.');
            return;
        }

        $hasil = $this->rujukanGetTask($taskId);
        if (($hasil['code'] ?? 0) < 200 || ($hasil['code'] ?? 0) >= 300) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengecek status [' . ($hasil['code'] ?? '-') . '] — ' . $this->ringkasGangguan($hasil['body'] ?? null));
            return;
        }

        $task = $this->rujukanTaskDariResponse($hasil['body'] ?? null);
        if (!$task) {
            $this->dispatch('toast', type: 'error', message: 'SATUSEHAT menjawab tanpa Task — status tidak berubah.');
            return;
        }

        $statusLama = (string) ($this->rujukan['statusTask'] ?? '');
        $keputusanLama = (string) ($this->rujukan['keputusan'] ?? '');

        $this->rujukan['statusTask'] = (string) ($task['status'] ?? $statusLama);
        $this->rujukan['keputusan'] = $this->rujukanKeputusanDariTask($task);

        // Layar pemantauan memegang salinan baris yang sama — segarkan juga di sana,
        // supaya menutup modal tidak mengembalikan badge ke keadaan basi.
        $this->dispatch(
            'rujukan-keluar.status-diperbarui',
            taskId: $taskId,
            statusTask: $this->rujukan['statusTask'],
            keputusan: $this->rujukan['keputusan'],
        );

        if ($this->rujukan['statusTask'] === $statusLama && $this->rujukan['keputusan'] === $keputusanLama) {
            $this->dispatch('toast', type: 'info', message: 'Belum ada jawaban baru — RS tujuan masih belum menjawab.');
            return;
        }

        $kabar = match ($this->rujukan['keputusan']) {
            'accepted' => 'RS tujuan MENYETUJUI rujukan ini.',
            'rejected' => 'RS tujuan MENOLAK rujukan ini.',
            default => $this->rujukan['statusTask'] === 'cancelled'
                ? 'Tugas rujukan ini sudah dibatalkan.'
                : 'Status rujukan diperbarui.',
        };
        $this->dispatch('toast', type: 'success', message: $kabar);
    }

    /** Ringkas badan error SATUSEHAT jadi satu kalimat yang muat di toast. */
    private function ringkasGangguan($body): string
    {
        if (is_string($body)) {
            return Str::limit($body, 140);
        }
        if (is_array($body)) {
            $pesan = $body['issue'][0]['details']['text'] ?? ($body['issue'][0]['diagnostics'] ?? ($body['message'] ?? null));
            return Str::limit((string) ($pesan ?: json_encode($body)), 140);
        }
        return 'tidak ada keterangan';
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
};
?>

<div>
    <x-modal name="rujukan-keluar-actions" size="full" height="full" focusable>
        @php
            $jalur = $rujukan['jalur'] ?? '';
            $keputusan = $rujukan['keputusan'] ?? '';
            $statusTask = $rujukan['statusTask'] ?? '';

            // CarePlan disensor SATUSEHAT ("No consent available") → nama pasien,
            // layanan, jalur & keterangan klinis kosong SEKALIGUS.
            $diblokir = (bool) ($rujukan['rencanaDiblokir'] ?? false);
            $kosongKarena = $diblokir ? '(detail tidak terbaca)' : '';

            $belumDijawab = $keputusan === '' && $statusTask !== 'cancelled';
        @endphp

        {{-- Modal full: padding panel jadi p-0, jadi header/body/footer memakai padding
             sendiri — pola sama dengan modal EMR (screening, modul dokumen). --}}
        <div class="flex flex-col min-h-[calc(100vh-8rem)]">

            {{-- HEADER --}}
            <div class="flex flex-wrap items-start gap-3 px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="flex-1 min-w-0">
                    <h2 class="text-lg font-semibold text-ink dark:text-gray-100">
                        Rujukan Keluar
                    </h2>
                    <p class="mt-1 text-sm text-muted dark:text-gray-400">
                        Rincian permintaan rujukan yang RS kita kirim, berikut jawaban RS tujuan.
                        Layar ini hanya membaca — tidak ada yang dikirim ke SATUSEHAT dari sini.
                    </p>
                </div>
                <div class="flex flex-col items-end gap-1">
                    @if ($jalur === 'ranap')
                        <x-badge variant="info">Rawat Inap</x-badge>
                    @elseif ($jalur === 'igd')
                        <x-badge variant="danger">Gawat Darurat</x-badge>
                    @elseif ($diblokir)
                        <x-badge variant="warning">Jalur tidak terbaca</x-badge>
                    @else
                        <x-badge variant="gray">Layanan tidak dikenali</x-badge>
                    @endif

                    @if ($statusTask === 'cancelled')
                        <x-badge variant="gray">Dibatalkan</x-badge>
                    @elseif ($keputusan === 'accepted')
                        <x-badge variant="success">Disetujui RS Tujuan</x-badge>
                    @elseif ($keputusan === 'rejected')
                        <x-badge variant="danger">Ditolak RS Tujuan</x-badge>
                    @else
                        <x-badge variant="warning">Menunggu Jawaban</x-badge>
                    @endif
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex flex-col flex-1 gap-5 px-6 py-5 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">

                {{-- IDENTITAS & TUJUAN --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div
                        class="p-4 border bg-surface-soft border-hairline rounded-xl dark:bg-gray-800 dark:border-gray-700">
                        <div class="text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">Pasien
                        </div>
                        <div class="mt-1 font-semibold text-ink dark:text-gray-100">
                            {{ ($rujukan['pasienNama'] ?? '') !== '' ? $rujukan['pasienNama'] : ($kosongKarena ?: '(nama tidak terbaca)') }}
                        </div>
                        <div class="text-sm text-muted dark:text-gray-400">
                            IHS Pasien: {{ ($rujukan['pasienId'] ?? '') !== '' ? $rujukan['pasienId'] : '-' }}
                        </div>
                    </div>

                    <div
                        class="p-4 border bg-surface-soft border-hairline rounded-xl dark:bg-gray-800 dark:border-gray-700">
                        <div class="text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">RS
                            Tujuan
                        </div>
                        <div class="mt-1 font-semibold text-ink dark:text-gray-100">
                            {{ ($rujukan['tujuanNama'] ?? '') !== '' ? $rujukan['tujuanNama'] : '(nama RS belum terbaca)' }}
                        </div>
                        <div class="text-sm text-muted dark:text-gray-400">
                            Org ID: {{ ($rujukan['tujuanOrgId'] ?? '') !== '' ? $rujukan['tujuanOrgId'] : '-' }}
                        </div>
                        @if (($rujukan['dokterPerujuk'] ?? '') !== '')
                            <div class="text-sm text-muted dark:text-gray-400">DPJP perujuk:
                                {{ $rujukan['dokterPerujuk'] }}</div>
                        @endif
                    </div>
                </div>

                {{-- PERMINTAAN LAYANAN --}}
                <div class="p-4 border bg-canvas border-hairline rounded-xl dark:bg-gray-900 dark:border-gray-700">
                    <div class="text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">
                        Layanan yang Diminta
                    </div>
                    <div class="mt-1 font-medium text-ink dark:text-gray-100">
                        {{ ($rujukan['layananNama'] ?? '') !== '' ? $rujukan['layananNama'] : ($kosongKarena ?: '-') }}
                        @if (($rujukan['layananKode'] ?? '') !== '')
                            <span class="text-sm font-normal text-muted-soft">({{ $rujukan['layananKode'] }})</span>
                        @endif
                    </div>

                    <div class="mt-3 text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">
                        Keterangan Klinis yang Kita Kirim
                    </div>
                    <p class="mt-1 text-sm whitespace-pre-line text-body dark:text-gray-200">
                        {{ ($rujukan['deskripsi'] ?? '') !== ''
                            ? $rujukan['deskripsi']
                            : ($diblokir
                                ? 'Keterangan klinis tidak dapat dibaca — lihat catatan di bawah.'
                                : 'Tidak ada keterangan klinis pada rencana rujukan ini.') }}
                    </p>

                    <div class="mt-3 text-sm text-muted dark:text-gray-400">
                        Waktu dikirim: {{ $this->waktuTampil($rujukan['waktu'] ?? '') }}
                        · No. Permintaan:
                        {{ ($rujukan['noPermintaan'] ?? '') !== '' ? $rujukan['noPermintaan'] : '-' }}
                    </div>
                </div>

                {{-- APA LANGKAH BERIKUTNYA — inti layar ini. Petugas datang ke sini untuk
                     tahu nasib rujukannya, jadi jawabannya harus disertai tindak lanjut,
                     bukan cuma badge berwarna. --}}
                @if ($statusTask === 'cancelled')
                    <div
                        class="p-4 text-sm border rounded-xl bg-surface-soft border-hairline text-body dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200">
                        <div class="font-semibold">Permintaan dibatalkan</div>
                        <p class="mt-1">
                            Tugas rujukan ini sudah berstatus <span class="font-mono text-xs">cancelled</span>,
                            jadi RS tujuan tidak akan menjawabnya lagi. Bila pasien masih perlu dirujuk,
                            kirim permintaan baru dari EMR pasiennya.
                        </p>
                    </div>
                @elseif ($keputusan === 'accepted')
                    <div
                        class="p-4 text-sm border rounded-xl bg-success-tint border-success/30 text-success-deep">
                        <div class="font-semibold">Disetujui — rujukan belum selesai</div>
                        <p class="mt-1">
                            Persetujuan barulah kesediaan menerima. Rujukannya sendiri baru sah setelah
                            ServiceRequest terkirim dan mendapat nomor rujukan SATUSEHAT. Buka panel rujukan
                            di EMR pasien untuk menuntaskan langkah itu bila belum.
                        </p>
                    </div>
                @elseif ($keputusan === 'rejected')
                    <div
                        class="p-4 text-sm border rounded-xl bg-error-tint border-red-200 text-error-deep dark:bg-red-900/20 dark:border-red-800 dark:text-red-200">
                        <div class="font-semibold">Ditolak RS tujuan</div>
                        <p class="mt-1">
                            Alasan penolakan tidak dikirim lewat SATUSEHAT — hanya kode
                            <span class="font-mono text-xs">rejected</span>. Tanyakan lewat jalur komunikasi RS,
                            lalu pilih faskes kandidat lain dari panel rujukan di EMR pasien.
                        </p>
                    </div>
                @elseif ($belumDijawab)
                    <div
                        class="p-4 text-sm border rounded-xl bg-blue-50 border-blue-200 text-info-deep dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-200">
                        <div class="font-semibold">Masih menunggu jawaban RS tujuan</div>
                        <p class="mt-1">
                            Jawaban datang menyusul, tidak seketika. Bila sudah lewat ±15 menit dan pasien
                            tidak bisa menunggu, hubungi RS tujuan lewat jalur komunikasi RS atau alihkan ke
                            faskes kandidat berikutnya dari EMR pasien.
                        </p>
                    </div>
                @endif

                {{-- DATA KLINIS DISENSOR — di arah keluar ini seharusnya mustahil, karena
                     CarePlan-nya kita sendiri yang membuat. Kalau muncul, itu temuan. --}}
                @if ($diblokir)
                    <div
                        class="p-4 text-sm border rounded-xl bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200">
                        <div class="font-semibold">Detail klinis tidak terbaca</div>
                        <p class="mt-1">
                            SATUSEHAT membalas <span class="font-mono text-xs">No consent available</span> untuk
                            CarePlan rujukan ini, sehingga nama pasien, layanan, jalur, dan keterangan klinis
                            tidak terbaca — padahal rencana rujukan ini RS kita sendiri yang membuat.
                            Data aslinya tetap ada di EMR pasien; yang bermasalah pembacaan baliknya.
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
                            <dd class="break-all text-body dark:text-gray-200">{{ $rujukan['taskId'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-soft">CarePlan ID</dt>
                            <dd class="break-all text-body dark:text-gray-200">{{ $rujukan['rencanaId'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-soft">Encounter kita</dt>
                            <dd class="break-all text-body dark:text-gray-200">
                                {{ ($rujukan['encounterId'] ?? '') !== '' ? $rujukan['encounterId'] : '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-soft">Condition (diagnosa awal)</dt>
                            <dd class="break-all text-body dark:text-gray-200">
                                {{ ($rujukan['diagnosaId'] ?? '') !== '' ? $rujukan['diagnosaId'] : '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-soft">Patient IHS</dt>
                            <dd class="break-all text-body dark:text-gray-200">
                                {{ ($rujukan['pasienId'] ?? '') !== '' ? $rujukan['pasienId'] : '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-soft">Status Task</dt>
                            <dd class="text-body dark:text-gray-200">{{ $statusTask !== '' ? $statusTask : '-' }}</dd>
                        </div>
                    </dl>
                </details>

            </div>{{-- /BODY --}}

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 flex flex-wrap justify-end gap-2 px-6 py-4 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                <x-secondary-button type="button"
                    x-on:click="$dispatch('close-modal', { name: 'rujukan-keluar-actions' })">
                    Tutup
                </x-secondary-button>

                {{-- Hanya selagi menunggu: sesudah dijawab/dibatalkan, statusnya final. --}}
                @if ($this->belumDijawab())
                    <x-primary-button type="button" wire:click="cekStatus" wire:loading.attr="disabled"
                        wire:target="cekStatus"
                        title="Tanyakan ulang jawaban RS tujuan — SATUSEHAT tidak memberi tahu sendiri (1 panggilan API)">
                        <span wire:loading.remove wire:target="cekStatus" class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                            Cek Status
                        </span>
                        <span wire:loading wire:target="cekStatus" class="inline-flex items-center gap-1">
                            <x-loading /> Mengecek...
                        </span>
                    </x-primary-button>
                @endif
            </div>

        </div>
    </x-modal>
</div>
