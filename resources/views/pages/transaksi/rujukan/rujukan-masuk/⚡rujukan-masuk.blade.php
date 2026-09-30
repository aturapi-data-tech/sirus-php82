<?php
// ╔══════════════════════════════════════════════════════════════════════╗
// ║  PERSETUJUAN RUJUKAN MASUK — sisi FASKES TUJUAN (SRBK/SATUSEHAT)     ║
// ╚══════════════════════════════════════════════════════════════════════╝
//
// Route: /rujukan/masuk → pages::transaksi.rujukan.rujukan-masuk.rujukan-masuk
//
// RS lain mengirim tugas rujukan (Task code=referral-approval-request) dengan
// owner = Organization RS kita. Layar ini kotak masuknya: petugas IGD / admisi
// ranap membaca permintaan lalu MENYETUJUI atau MENOLAK. Keputusan dikirim
// balik sebagai PATCH Task (status completed + output accepted/rejected) —
// perujuk membacanya lewat Task?requester=<org perujuk>.
//
// Hanya jalur Rawat Inap & Rawat Darurat yang lewat sini. Rujukan rawat jalan
// diorkestrasi BPJS (vclaim-sisrute-rest), tidak pernah muncul di kotak masuk ini.
//
// Sumber data MURNI API SATUSEHAT — tidak ada tabel lokal. Tiap panggilan
// terekam di web_log_status (payload + response mentah) lewat SatuSehatRujukanTrait.
// Karena tiap muat = 1 panggilan API (dan kuota staging pernah habis → 429),
// muat ulang otomatis sengaja DEFAULT MATI.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Http\Traits\SATUSEHAT\SatuSehatRujukanTrait;

new class extends Component {
    use SatuSehatRujukanTrait;

    /** Baris mentah hasil parse Bundle Task+CarePlan. */
    public array $daftarPermintaan = [];

    /**
     * Permintaan yang DISEMBUNYIKAN SATUSEHAT karena consent — tidak punya baris
     * di tabel, jadi satu-satunya jejaknya di layar adalah spanduk peringatan.
     * ['Task/<id>' => alasan]
     */
    public array $permintaanTersensor = [];

    // Dua-duanya default SEMUA: kotak masuk dibuka untuk melihat apa saja yang datang,
    // bukan cuma yang belum dijawab. Rekap di bawah toolbar sudah menghitung per status
    // dari data mentah, jadi yang menunggu tetap ketara tanpa perlu memfilter duluan.
    public string $filterStatus = ''; // '' (semua) | menunggu | accepted | rejected
    public string $filterJalur = ''; // '' (semua) | ranap | igd
    public string $searchKeyword = '';

    public bool $muatOtomatis = false;
    public string $waktuMuat = '';
    public string $pesanGangguan = '';
    public bool $sudahPernahMuat = false;

    /**
     * Task ID yang ditarik lewat modal "Cek Task ID" karena tidak ada di kotak masuk.
     * Diingat supaya tidak hilang dari daftar saat kotak masuk dimuat ulang
     * (mis. sesudah dijawab) — tiap id = 1 panggilan API, dibatasi MAKS_TASK_DICARI.
     */
    public array $taskIdDicariList = [];

    private const MAKS_TASK_DICARI = 5;

    public function mount(): void
    {
        $this->muatPermintaan();
    }

    /**
     * Tarik kotak masuk dari SATUSEHAT. Gangguan pusat = kondisi normal:
     * tampilkan pesan ramah + tombol coba lagi, jangan kosongkan daftar
     * yang sudah tampil supaya petugas tidak kehilangan konteks.
     */
    public function muatPermintaan(): void
    {
        $this->sudahPernahMuat = true;
        $hasil = $this->rujukanTaskMasuk();

        if ($hasil['code'] < 200 || $hasil['code'] >= 300) {
            $this->pesanGangguan = 'Gagal membaca kotak masuk rujukan [' . $hasil['code'] . '] — ' . $this->ringkasError($hasil['body']);
            return;
        }

        $this->pesanGangguan = '';
        $baris = $this->rujukanParsePermintaanMasuk($hasil['body']);
        $this->permintaanTersensor = $this->rujukanPermintaanTersensor($hasil['body']);

        // Task hasil "Cari Task ID" yang tidak ikut di kotak masuk ditarik ulang satu per
        // satu, supaya statusnya ikut segar dan barisnya tidak lenyap sesudah dijawab.
        $taskIdTermuat = array_column($baris, 'taskId');
        foreach (array_slice($this->taskIdDicariList, 0, self::MAKS_TASK_DICARI) as $taskId) {
            if (!is_string($taskId) || in_array($taskId, $taskIdTermuat, true)) {
                continue;
            }
            $hasilCari = $this->rujukanTaskMasukById($taskId);
            if ($hasilCari['code'] >= 200 && $hasilCari['code'] < 300) {
                $baris = array_merge($baris, $this->rujukanParsePermintaanMasuk($hasilCari['body']));
                $this->permintaanTersensor += $this->rujukanPermintaanTersensor($hasilCari['body']);
            }
        }

        usort($baris, fn($barisPertama, $barisKedua) => strcmp($barisKedua['waktu'], $barisPertama['waktu']));

        $this->daftarPermintaan = $this->lengkapiNamaPerujuk($baris);
        $this->waktuMuat = Carbon::now(env('APP_TIMEZONE'))->format('d/m/Y H:i:s');
    }

    /** Nama RS perujuk tidak ikut di Task; ambil sekali per organisasi (di-cache 1 hari). */
    private function lengkapiNamaPerujuk(array $daftarBaris): array
    {
        $namaOrganisasi = [];
        foreach ($daftarBaris as $index => $satu) {
            $orgId = $satu['perujukOrgId'];
            if ($orgId !== '' && !array_key_exists($orgId, $namaOrganisasi)) {
                $namaOrganisasi[$orgId] = $this->rujukanNamaOrganisasi($orgId);
            }
            $daftarBaris[$index]['perujukNama'] = $namaOrganisasi[$orgId] ?? '';
        }

        return $daftarBaris;
    }

    /** Kata kunci berbentuk Task ID (UUID) — penentu tombol "Cek Task ID ini" di tabel kosong. */
    public function kataKunciTaskId(): string
    {
        $kataKunci = strtolower(trim($this->searchKeyword));

        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $kataKunci) ? $kataKunci : '';
    }

    /**
     * Hasil modal "Cek Task ID" yang dipilih petugas: barisnya masuk tabel (atau
     * menimpa baris lama dengan status terbaru) dan Task ID-nya diingat.
     */
    #[On('rujukan-masuk.task-dicek')]
    public function ingatTaskDicek(array $permintaan): void
    {
        $taskId = (string) ($permintaan['taskId'] ?? '');
        if ($taskId === '') {
            return;
        }

        $this->taskIdDicariList = array_slice(array_values(array_unique([$taskId, ...$this->taskIdDicariList])), 0, self::MAKS_TASK_DICARI);

        $daftarBaris = array_values(array_filter($this->daftarPermintaan, fn(array $baris) => $baris['taskId'] !== $taskId));
        $daftarBaris[] = $permintaan;
        usort($daftarBaris, fn($barisPertama, $barisKedua) => strcmp($barisKedua['waktu'], $barisPertama['waktu']));
        $this->daftarPermintaan = $daftarBaris;
    }

    /** Reset = filter & pencarian kembali ke awal; data kotak masuk tidak ditarik ulang. */
    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterJalur', 'filterStatus']);
    }

    /** Setelah petugas menjawab di modal, kotak masuk disegarkan. */
    #[On('rujukan-masuk.dijawab')]
    public function segarkan(): void
    {
        $this->muatPermintaan();
    }

    public function bukaDetail(int $indeks): void
    {
        $baris = $this->daftarPermintaan[$indeks] ?? null;
        if (!$baris) {
            return;
        }

        $this->dispatch('rujukan-masuk-actions.open', permintaan: $baris);
    }

    /**
     * Filter dikerjakan di memori — sekali tarik, banyak saring.
     * Menambah filter TIDAK menambah panggilan API.
     */
    #[Computed]
    public function rows(): array
    {
        $kataKunci = trim(strtolower($this->searchKeyword));

        return array_values(
            array_filter($this->daftarPermintaan, function (array $baris) use ($kataKunci) {
                if ($this->filterStatus === 'menunggu' && !$this->menunggu($baris)) {
                    return false;
                }
                if (in_array($this->filterStatus, ['accepted', 'rejected'], true) && $baris['keputusan'] !== $this->filterStatus) {
                    return false;
                }
                if ($this->filterJalur !== '' && $baris['jalur'] !== $this->filterJalur) {
                    return false;
                }
                if ($kataKunci === '') {
                    return true;
                }

                $gabungan = strtolower(implode(' ', [$baris['taskId'], $baris['pasienNama'], $baris['pasienId'], $baris['noPermintaan'], $baris['perujukNama'] ?? '', $baris['perujukOrgId'], $baris['layananNama']]));

                return str_contains($gabungan, $kataKunci);
            }),
        );
    }

    /** Ringkasan jumlah per status — dihitung dari data mentah, bukan hasil filter. */
    #[Computed]
    public function rekap(): array
    {
        $rekap = ['menunggu' => 0, 'accepted' => 0, 'rejected' => 0, 'batal' => 0];
        foreach ($this->daftarPermintaan as $baris) {
            if ($baris['statusTask'] === 'cancelled') {
                $rekap['batal']++;
            } elseif ($this->menunggu($baris)) {
                $rekap['menunggu']++;
            } elseif ($baris['keputusan'] === 'accepted') {
                $rekap['accepted']++;
            } elseif ($baris['keputusan'] === 'rejected') {
                $rekap['rejected']++;
            }
        }

        return $rekap;
    }

    /** Belum dijawab = belum ada output keputusan DAN belum dibatalkan perujuk. */
    public function menunggu(array $baris): bool
    {
        return $baris['keputusan'] === '' && $baris['statusTask'] !== 'cancelled';
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

    /** Ambil pesan terpakai dari OperationOutcome / body apa pun tanpa membanjiri toast. */
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
    <x-page-title title="Persetujuan Rujukan Masuk"
        subtitle="Permintaan rujukan Rawat Inap & Gawat Darurat dari RS lain — SATUSEHAT Rujukan (SRBK)" />

    {{-- Pemicu muat ulang berkala dipisah jadi elemen sendiri: directive di dalam
         atribut adalah jebakan compiler Blade yang sudah pernah menggigit repo ini. --}}
    @if ($muatOtomatis)
        <div wire:poll.60s="muatPermintaan" class="hidden"></div>
    @endif

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">

                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full pl-10"
                                placeholder="Cari nama pasien / RS perujuk / nomor permintaan / Task ID..." />
                        </div>
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Layanan Diminta" />
                        <x-select-input wire:model.live="filterJalur" class="w-full mt-1 sm:w-56">
                            <option value="">Semua Layanan</option>
                            <option value="ranap">Rawat Inap</option>
                            <option value="igd">Gawat Darurat</option>
                        </x-select-input>
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-52">
                            <option value="">Semua Status</option>
                            <option value="menunggu">Menunggu Jawaban</option>
                            <option value="accepted">Disetujui</option>
                            <option value="rejected">Ditolak</option>
                        </x-select-input>
                    </div>

                    <div class="flex items-center gap-3 ml-auto">
                        <div title="Tiap muat ulang = 1 panggilan API SATUSEHAT. Nyalakan hanya saat menunggu jawaban.">
                            <x-toggle wire:model.live="muatOtomatis" :trueValue="true" :falseValue="false"
                                label="Muat ulang tiap 60 detik" />
                        </div>

                        <x-primary-button type="button"
                            wire:click="$dispatch('rujukan-masuk-cek-task.open', { taskId: '' })"
                            class="whitespace-nowrap" title="Tarik satu permintaan langsung dari SATUSEHAT berdasarkan Task ID">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Cek Task ID
                        </x-primary-button>

                        <x-toolbar-refresh-reset :label="null" refresh-action="muatPermintaan" />
                    </div>

                </div>
            </div>

            {{-- REKAP + JEJAK WAKTU MUAT --}}
            @php $rekap = $this->rekap; @endphp
            <div
                class="mt-4 px-4 py-3 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="mr-1 text-sm font-semibold text-muted dark:text-gray-300">Kotak masuk:</span>
                    <x-badge variant="warning">Menunggu: {{ $rekap['menunggu'] }}</x-badge>
                    <x-badge variant="success">Disetujui: {{ $rekap['accepted'] }}</x-badge>
                    <x-badge variant="danger">Ditolak: {{ $rekap['rejected'] }}</x-badge>
                    @if ($rekap['batal'] > 0)
                        <x-badge variant="gray">Dibatalkan perujuk: {{ $rekap['batal'] }}</x-badge>
                    @endif
                    @if ($waktuMuat !== '')
                        <span class="ml-auto text-xs text-muted-soft">Terakhir dimuat {{ $waktuMuat }}</span>
                    @endif
                </div>
            </div>

            {{-- PERMINTAAN TERSEMBUNYI — tidak punya baris di tabel, jadi tanpa spanduk ini
                 petugas tidak punya cara apa pun untuk tahu ada rujukan yang tidak terbaca. --}}
            @if (count($permintaanTersensor) > 0)
                <div
                    class="mt-4 px-4 py-3 border rounded-2xl bg-warning-tint border-amber-200 dark:bg-amber-900/20 dark:border-amber-800">
                    <div class="flex flex-wrap items-start gap-x-3 gap-y-1">
                        <span class="text-sm font-semibold text-warning-deep dark:text-amber-200">
                            {{ count($permintaanTersensor) }} permintaan tidak dapat ditampilkan
                        </span>
                        <span class="text-sm text-warning-deep dark:text-amber-200">
                            SATUSEHAT menyembunyikannya karena aturan consent/privasi. Permintaan itu
                            tidak bisa dijawab dari layar ini — hubungi RS perujuk bila mereka menunggu.
                        </span>
                        <span class="w-full text-xs text-warning-deep dark:text-amber-200">
                            Referensi: {{ implode(', ', array_keys($permintaanTersensor)) }}
                        </span>
                    </div>
                </div>
            @endif

            {{-- GANGGUAN PUSAT — bukan edge case, tampilkan apa adanya + tombol coba lagi --}}
            @if ($pesanGangguan !== '')
                <div
                    class="mt-4 px-4 py-3 border rounded-2xl bg-error-tint border-red-200 dark:bg-red-900/20 dark:border-red-800">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-sm font-semibold text-error-deep dark:text-red-200">
                            Layanan SATUSEHAT sedang tidak bisa dihubungi
                        </span>
                        <span class="text-sm text-error-deep dark:text-red-200">{{ $pesanGangguan }}</span>
                        <x-outline-button type="button" wire:click="muatPermintaan" class="ml-auto">
                            Coba Lagi
                        </x-outline-button>
                    </div>
                </div>
            @endif

            {{-- TABEL --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-base -mt-3 border-separate border-spacing-y-3">
                        <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                            <tr
                                class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                <th class="px-6 py-3 min-w-[260px]">Pasien</th>
                                <th class="px-6 py-3 min-w-[260px]">RS Perujuk</th>
                                <th class="px-6 py-3 min-w-[240px]">Layanan Diminta</th>
                                <th class="px-6 py-3 min-w-[160px]">Waktu Permintaan</th>
                                <th class="px-6 py-3 min-w-[150px]">Status</th>
                                <th class="w-40 px-6 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $indeks => $baris)
                                <tr class="transition bg-canvas dark:bg-gray-900
                                       rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700
                                       hover:shadow-lg hover:bg-surface-soft dark:hover:bg-gray-800"
                                    wire:key="permintaan-rujukan-{{ $baris['taskId'] }}">

                                    @php
                                        // CarePlan belum terbaca → nama, layanan & jalur kosong berjamaah.
                                        // Ini PERILAKU SATUSEHAT, bukan cacat dan bukan salah perujuk:
                                        // detail klinis baru terbuka setelah permintaan disetujui.
                                        // Permintaan yang dibatalkan perujuk juga tidak pernah terbuka.
                                        $barisDiblokir = (bool) ($baris['rencanaDiblokir'] ?? false);
                                        $barisDibatalkan = ($baris['statusTask'] ?? '') === 'cancelled';
                                    @endphp

                                    <td class="px-6 py-4 rounded-l-2xl">
                                        <div class="font-semibold text-ink dark:text-gray-100">
                                            {{ $baris['pasienNama'] !== ''
                                                ? $baris['pasienNama']
                                                : ($barisDiblokir
                                                    ? ($barisDibatalkan
                                                        ? '(dibatalkan perujuk)'
                                                        : '(belum terbuka — menunggu persetujuan)')
                                                    : '(nama tidak dikirim perujuk)') }}
                                        </div>
                                        <div class="text-sm text-muted dark:text-gray-400">
                                            IHS: {{ $baris['pasienId'] !== '' ? $baris['pasienId'] : '-' }}
                                        </div>
                                        <div class="text-xs text-muted-soft">No. Permintaan:
                                            {{ $baris['noPermintaan'] !== '' ? $baris['noPermintaan'] : '-' }}
                                        </div>
                                        <div class="font-mono text-xs break-all text-muted-soft">Task ID:
                                            {{ $baris['taskId'] !== '' ? $baris['taskId'] : '-' }}
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="font-medium text-ink dark:text-gray-100">
                                            {{ $baris['perujukNama'] !== '' ? $baris['perujukNama'] : '(nama RS belum terbaca)' }}
                                        </div>
                                        <div class="text-sm text-muted dark:text-gray-400">
                                            Org ID: {{ $baris['perujukOrgId'] !== '' ? $baris['perujukOrgId'] : '-' }}
                                        </div>
                                        @if ($baris['dokterPerujuk'] !== '')
                                            <div class="text-xs text-muted-soft">DPJP perujuk:
                                                {{ $baris['dokterPerujuk'] }}</div>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4">
                                        @if ($baris['jalur'] === 'ranap')
                                            <x-badge variant="info">Rawat Inap</x-badge>
                                        @elseif ($baris['jalur'] === 'igd')
                                            <x-badge variant="danger">Gawat Darurat</x-badge>
                                        @elseif ($barisDiblokir)
                                            <x-badge variant="warning">Jalur belum terbuka</x-badge>
                                        @else
                                            <x-badge variant="gray">Layanan tidak dikenali</x-badge>
                                        @endif
                                        <div class="mt-1 text-sm text-muted dark:text-gray-400">
                                            {{ $baris['layananNama'] !== ''
                                                ? $baris['layananNama']
                                                : ($barisDiblokir
                                                    ? 'Belum terbuka — menunggu persetujuan'
                                                    : '-') }}
                                            @if ($baris['layananKode'] !== '')
                                                <span class="text-muted-soft">({{ $baris['layananKode'] }})</span>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-sm text-muted dark:text-gray-300">
                                        {{ $this->waktuTampil($baris['waktu']) }}
                                    </td>

                                    <td class="px-6 py-4">
                                        @if ($baris['statusTask'] === 'cancelled')
                                            <x-badge variant="gray">Dibatalkan perujuk</x-badge>
                                        @elseif ($baris['keputusan'] === 'accepted')
                                            <x-badge variant="success">Disetujui</x-badge>
                                        @elseif ($baris['keputusan'] === 'rejected')
                                            <x-badge variant="danger">Ditolak</x-badge>
                                        @else
                                            <x-badge variant="warning">Menunggu Jawaban</x-badge>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 text-center rounded-r-2xl">
                                        @if ($this->menunggu($baris))
                                            <x-lihat-button wire:click="bukaDetail({{ $indeks }})" label="Tinjau & Jawab" />
                                        @else
                                            <x-lihat-button wire:click="bukaDetail({{ $indeks }})" title="Lihat Detail" />
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-muted dark:text-gray-400">
                                        @if (!$sudahPernahMuat)
                                            Kotak masuk belum dimuat.
                                        @elseif ($pesanGangguan !== '')
                                            Kotak masuk tidak dapat dibaca — lihat keterangan gangguan di atas.
                                        @elseif ($this->kataKunciTaskId() !== '')
                                            <div>Task ID ini tidak ada di kotak masuk yang termuat.</div>
                                            <x-outline-button type="button" class="mt-3"
                                                wire:click="$dispatch('rujukan-masuk-cek-task.open', { taskId: '{{ $this->kataKunciTaskId() }}' })">
                                                Cek Task ID ini di SATUSEHAT
                                            </x-outline-button>
                                            @if ($filterStatus !== '' || $filterJalur !== '')
                                                <div class="mt-2 text-xs text-muted-soft">Filter Layanan/Status aktif — hasil yang tidak cocok filter tetap tersembunyi.</div>
                                            @endif
                                        @elseif (count($daftarPermintaan) > 0)
                                            Tidak ada permintaan yang cocok dengan filter.
                                        @else
                                            Belum ada permintaan rujukan masuk untuk RS ini.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <livewire:pages::transaksi.rujukan.rujukan-masuk.rujukan-masuk-actions />
    <livewire:pages::transaksi.rujukan.rujukan-masuk.rujukan-masuk-cek-task />
</div>
