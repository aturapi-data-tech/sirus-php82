<?php
// Modal "Cek Task ID" — tarik SATU tugas rujukan masuk langsung dari SATUSEHAT
// berdasarkan Task ID, untuk Task yang tidak ikut termuat di kotak masuk atau
// yang ingin dicek status terkininya (mis. saat perujuk menelepon menanyakan).
//
// Query tetap dikunci owner = RS kita + code referral-approval-request
// (rujukanTaskMasukById), jadi Task milik RS lain tidak bisa ditarik dari sini.
// Tiap klik "Cek" = 1 panggilan API; tidak ada pencarian otomatis saat mengetik.
//
// Hasil yang ingin dijawab diteruskan ke layar kotak masuk (event
// rujukan-masuk.task-dicek) supaya barisnya ikut tampil & ikut dimuat ulang,
// lalu modal actions yang sudah ada dibuka — jawab setuju/tolak tetap di satu tempat.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Http\Traits\SATUSEHAT\SatuSehatRujukanTrait;

new class extends Component {
    use SatuSehatRujukanTrait;

    public string $taskIdInput = '';

    /** '' belum dicek | ditemukan | tidak-ditemukan | tersensor | gagal */
    public string $statusCek = '';
    public string $pesanCek = '';
    public array $permintaan = [];
    public string $responsMentah = '';
    public string $waktuCek = '';

    #[On('rujukan-masuk-cek-task.open')]
    public function open(string $taskId = ''): void
    {
        $this->resetHasil();
        $this->taskIdInput = trim($taskId);
        $this->dispatch('open-modal', name: 'rujukan-masuk-cek-task');

        if ($this->taskIdValid() !== '') {
            $this->cek();
        }
    }

    /** Sesudah permintaan dijawab di modal actions, hasil cek yang sedang tampil ikut disegarkan. */
    #[On('rujukan-masuk.dijawab')]
    public function segarkan(): void
    {
        if ($this->statusCek === 'ditemukan') {
            $this->cek();
        }
    }

    private function resetHasil(): void
    {
        $this->statusCek = '';
        $this->pesanCek = '';
        $this->permintaan = [];
        $this->responsMentah = '';
        $this->waktuCek = '';
    }

    /** Task ID berformat UUID (huruf kecil), '' bila tidak valid. */
    public function taskIdValid(): string
    {
        $taskId = strtolower(trim($this->taskIdInput));
        // Petugas kadang menempel referensi lengkap "Task/<uuid>".
        $taskId = preg_replace('#^task/#', '', $taskId);

        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $taskId) ? $taskId : '';
    }

    public function cek(): void
    {
        $this->resetHasil();

        $taskId = $this->taskIdValid();
        if ($taskId === '') {
            $this->addError('taskIdInput', 'Task ID tidak valid — tempel ID lengkap (format UUID, mis. 7a974c5c-…-…).');
            return;
        }
        $this->resetErrorBag('taskIdInput');
        $this->taskIdInput = $taskId;

        $hasil = $this->rujukanTaskMasukById($taskId);
        $this->waktuCek = Carbon::now(env('APP_TIMEZONE'))->format('d/m/Y H:i:s');
        $this->responsMentah = Str::limit(
            is_array($hasil['body']) ? json_encode($hasil['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $hasil['body'],
            20000,
        );

        if ($hasil['code'] < 200 || $hasil['code'] >= 300) {
            $this->statusCek = 'gagal';
            $this->pesanCek = 'SATUSEHAT membalas [' . $hasil['code'] . '] — ' . $this->ringkasError($hasil['body']);
            return;
        }

        $tersensor = $this->rujukanPermintaanTersensor($hasil['body']);
        if (isset($tersensor['Task/' . $taskId])) {
            $this->statusCek = 'tersensor';
            $this->pesanCek = $tersensor['Task/' . $taskId];
            return;
        }

        $daftarBaris = $this->rujukanParsePermintaanMasuk($hasil['body']);
        if (count($daftarBaris) === 0) {
            $this->statusCek = 'tidak-ditemukan';
            return;
        }

        $baris = $daftarBaris[0];
        $baris['perujukNama'] = $this->rujukanNamaOrganisasi($baris['perujukOrgId']);
        $this->permintaan = $baris;
        $this->statusCek = 'ditemukan';
    }

    public function menunggu(): bool
    {
        return ($this->permintaan['keputusan'] ?? '') === '' && ($this->permintaan['statusTask'] ?? '') !== 'cancelled';
    }

    /** Masukkan hasil cek ke tabel kotak masuk (diingat saat muat ulang). */
    public function tampilkanDiDaftar(): void
    {
        if ($this->statusCek !== 'ditemukan') {
            return;
        }

        $this->dispatch('rujukan-masuk.task-dicek', permintaan: $this->permintaan);
        $this->dispatch('toast', type: 'success', message: 'Task ditampilkan di daftar kotak masuk.');
    }

    /** Buka modal tinjau/jawab yang sama dengan tombol di tabel. */
    public function bukaDetail(): void
    {
        if ($this->statusCek !== 'ditemukan') {
            return;
        }

        $this->dispatch('rujukan-masuk.task-dicek', permintaan: $this->permintaan);
        $this->dispatch('close-modal', name: 'rujukan-masuk-cek-task');
        $this->dispatch('rujukan-masuk-actions.open', permintaan: $this->permintaan);
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
    <x-modal name="rujukan-masuk-cek-task" size="full" height="full" focusable>
        @php
            $jalur = $permintaan['jalur'] ?? '';
            $keputusan = $permintaan['keputusan'] ?? '';
            $statusTask = $permintaan['statusTask'] ?? '';
            $diblokir = (bool) ($permintaan['rencanaDiblokir'] ?? false);
            $isi = fn($nilai, $cadangan = '-') => ($nilai ?? '') !== '' ? $nilai : $cadangan;
        @endphp

        {{-- Modal full: padding panel jadi p-0, jadi header/body/footer memakai padding
             sendiri — pola sama dengan modal rujukan-masuk-actions. --}}
        <div class="flex flex-col min-h-[calc(100vh-8rem)]">

        {{-- HEADER — komponen baku modul dokumen (docs/modul-dokumen-ri-pattern.md §2a) --}}
        <x-modul-dokumen.header judul="Cek Task ID Rujukan Masuk" namaModal="rujukan-masuk-cek-task"
            ikon="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z">
            Tarik satu permintaan langsung dari SATUSEHAT — untuk Task yang tidak ada di kotak masuk atau untuk
            memastikan status terkininya. Hanya Task yang ditujukan ke RS ini yang bisa dicek.
            <x-slot:badge>
                @if ($statusCek === 'ditemukan')
                    @if ($jalur === 'ranap')
                        <x-badge class="shrink-0 whitespace-nowrap" variant="info">Rawat Inap</x-badge>
                    @elseif ($jalur === 'igd')
                        <x-badge class="shrink-0 whitespace-nowrap" variant="danger">Gawat Darurat</x-badge>
                    @elseif ($diblokir)
                        <x-badge class="shrink-0 whitespace-nowrap" variant="warning">Jalur belum terbuka</x-badge>
                    @else
                        <x-badge class="shrink-0 whitespace-nowrap" variant="gray">Layanan tidak dikenali</x-badge>
                    @endif

                    @if ($statusTask === 'cancelled')
                        <x-badge class="shrink-0 whitespace-nowrap" variant="gray">Dibatalkan perujuk</x-badge>
                    @elseif ($keputusan === 'accepted')
                        <x-badge class="shrink-0 whitespace-nowrap" variant="success">Disetujui</x-badge>
                    @elseif ($keputusan === 'rejected')
                        <x-badge class="shrink-0 whitespace-nowrap" variant="danger">Ditolak</x-badge>
                    @else
                        <x-badge class="shrink-0 whitespace-nowrap" variant="warning">Menunggu Jawaban</x-badge>
                    @endif
                @endif
            </x-slot:badge>
        </x-modul-dokumen.header>

        {{-- BODY --}}
        <div class="flex flex-col flex-1 gap-4 px-6 py-5 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">

        {{-- INPUT --}}
        <div>
        <form wire:submit="cek" class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-0">
                <x-input-label for="taskIdInput" value="Task ID" />
                <x-text-input id="taskIdInput" wire:model="taskIdInput" class="block w-full mt-1 font-mono"
                    placeholder="mis. 7a974c5c-1234-4abc-9def-0123456789ab" autocomplete="off" />
            </div>
            <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="cek" class="gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                    wire:loading.remove wire:target="cek">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <span wire:loading.remove wire:target="cek">Cek</span>
                <span wire:loading wire:target="cek">Mengecek...</span>
            </x-primary-button>
        </form>
        <x-input-error :messages="$errors->get('taskIdInput')" class="mt-1" />
        </div>

        {{-- HASIL --}}
        <div class="space-y-3">
            @if ($statusCek === 'gagal')
                <div
                    class="px-4 py-3 text-sm border rounded-xl bg-error-tint border-red-200 text-error-deep dark:bg-red-900/20 dark:border-red-800 dark:text-red-200">
                    <div class="font-semibold">Gagal membaca SATUSEHAT</div>
                    <div>{{ $pesanCek }}</div>
                </div>
            @elseif ($statusCek === 'tidak-ditemukan')
                <div
                    class="px-4 py-3 text-sm border rounded-xl bg-warning-tint border-amber-200 text-warning-deep dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200">
                    <div class="font-semibold">Task tidak ditemukan</div>
                    <div>Tidak ada permintaan rujukan dengan Task ID ini yang ditujukan ke RS ini. Periksa lagi ID-nya,
                        atau Task tersebut memang ditujukan ke RS lain / bukan tugas persetujuan rujukan.</div>
                </div>
            @elseif ($statusCek === 'tersensor')
                <div
                    class="px-4 py-3 text-sm border rounded-xl bg-warning-tint border-amber-200 text-warning-deep dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200">
                    <div class="font-semibold">Task ada, tetapi disembunyikan SATUSEHAT</div>
                    <div>Aturan consent/privasi menyensor Task ini, jadi isinya tidak bisa dibaca atau dijawab dari
                        layar ini. Hubungi RS perujuk bila mereka menunggu.</div>
                    @if ($pesanCek !== '')
                        <div class="mt-1 text-xs">Keterangan: {{ $pesanCek }}</div>
                    @endif
                </div>
            @elseif ($statusCek === 'ditemukan')
                <div class="p-4 border bg-canvas border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <span class="text-sm font-semibold text-ink dark:text-gray-100">Hasil Cek</span>
                        <span class="ml-auto text-xs text-muted-soft">Dicek {{ $waktuCek }}</span>
                    </div>

                    <dl class="grid grid-cols-1 text-sm gap-x-6 gap-y-2 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-muted">Pasien</dt>
                            <dd class="font-semibold text-ink dark:text-gray-100">
                                {{ $isi($permintaan['pasienNama'] ?? '', $diblokir ? '(belum terbuka — menunggu persetujuan)' : '(nama tidak dikirim perujuk)') }}
                            </dd>
                            <dd class="text-xs text-muted-soft">IHS: {{ $isi($permintaan['pasienId'] ?? '') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">RS Perujuk</dt>
                            <dd class="font-medium text-ink dark:text-gray-100">{{ $isi($permintaan['perujukNama'] ?? '', '(nama RS belum terbaca)') }}</dd>
                            <dd class="text-xs text-muted-soft">Org ID: {{ $isi($permintaan['perujukOrgId'] ?? '') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Layanan Diminta</dt>
                            <dd class="text-body dark:text-gray-200">
                                {{ $isi($permintaan['layananNama'] ?? '') }}
                                @if (($permintaan['layananKode'] ?? '') !== '')
                                    <span class="text-muted-soft">({{ $permintaan['layananKode'] }})</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Waktu Permintaan</dt>
                            <dd class="text-body dark:text-gray-200">{{ $this->waktuTampil($permintaan['waktu'] ?? '') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">No. Permintaan</dt>
                            <dd class="font-mono text-xs break-all text-body dark:text-gray-200">{{ $isi($permintaan['noPermintaan'] ?? '') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Status Task</dt>
                            <dd class="text-body dark:text-gray-200">{{ $isi($statusTask) }}</dd>
                        </div>
                        @if (($permintaan['dokterPerujuk'] ?? '') !== '')
                            <div>
                                <dt class="text-xs text-muted">DPJP Perujuk</dt>
                                <dd class="text-body dark:text-gray-200">{{ $permintaan['dokterPerujuk'] }}</dd>
                            </div>
                        @endif
                        @if (($permintaan['deskripsi'] ?? '') !== '')
                            <div class="sm:col-span-2">
                                <dt class="text-xs text-muted">Keterangan Klinis</dt>
                                <dd class="whitespace-pre-line text-body dark:text-gray-200">{{ $permintaan['deskripsi'] }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif

            @if ($responsMentah !== '')
                <details class="text-sm border rounded-xl border-hairline dark:border-gray-700">
                    <summary class="px-4 py-2 cursor-pointer text-muted dark:text-gray-400">Respons mentah SATUSEHAT</summary>
                    <pre class="px-4 pb-3 overflow-auto text-xs max-h-96 text-body dark:text-gray-300">{{ $responsMentah }}</pre>
                </details>
            @endif
        </div>

        </div>{{-- /BODY --}}

        {{-- FOOTER --}}
        <div
            class="sticky bottom-0 z-10 flex flex-wrap justify-end gap-2 px-6 py-4 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
            <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'rujukan-masuk-cek-task' })">
                Tutup
            </x-secondary-button>

            @if ($statusCek === 'ditemukan')
                <x-outline-button type="button" wire:click="tampilkanDiDaftar">
                    Tampilkan di Daftar
                </x-outline-button>
                <x-lihat-button wire:click="bukaDetail" :label="$this->menunggu() ? 'Tinjau & Jawab' : 'Lihat Detail'" />
            @endif
        </div>

        </div>
    </x-modal>
</div>
