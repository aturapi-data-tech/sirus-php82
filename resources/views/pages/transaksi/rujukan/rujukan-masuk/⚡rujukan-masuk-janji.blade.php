<?php
// ╔══════════════════════════════════════════════════════════════════════╗
// ║  JANJI RUJUKAN MASUK — grid RSTXN_RUJUKANMASUKS (tab /rujukan/masuk) ║
// ╚══════════════════════════════════════════════════════════════════════╝
//
// Tab "Janji Disetujui" di layar Persetujuan Rujukan Masuk. Beda sumber dengan
// tab Kotak Masuk: ini MURNI basis data kita — satu baris per permintaan yang
// kita setujui (RujukanMasukTrait), lengkap dengan nasibnya: masih ditunggu,
// atau sudah jadi kunjungan UGD/RI nomor berapa. Angka "Disetujui" di kotak
// masuk (dari API SATUSEHAT) bisa beda dengan isi grid ini, mis. permintaan
// yang disetujui sebelum tabelnya dipasang.
//
// Pendaftaran pasiennya tetap lewat tombol Rujukan Masuk di /ugd/daftar —
// form pendaftaran beserta SEP/VClaim-nya milik layar itu.
//
// "Cek di SATUSEHAT" (per baris, kolom Rujukan Resmi) menarik rujukan resmi
// (ServiceRequest) yang diterbitkan perujuk SESUDAH kita setuju; yang cocok
// disimpan ke node rujukanResmi (sama seperti saat Encounter dikirim). Tanpa
// tombol ini nomornya baru terlihat setelah Encounter terkirim.
//
// Data dibaca ulang tiap render lewat #[Computed], bukan disimpan di properti
// publik: tiap baris membawa salinan permintaan utuh, dan tabelnya terus
// bertambah. Jumlahnya masih puluhan — kalau sudah ribuan, saring tanggal di
// SQL (kolom JSON tak bisa disaring Oracle ini, perlu kolom tanggal sendiri).

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Http\Traits\Txn\RujukanMasuk\RujukanMasukTrait;
use App\Http\Traits\SATUSEHAT\SatuSehatRujukanTrait;

new class extends Component {
    use WithPagination, RujukanMasukTrait, SatuSehatRujukanTrait;

    /**
     * Hasil cek nomor rujukan per janji di sesi ini, tampil di sel Rujukan
     * Resmi: [rujukanMasukNo => 'Belum terbit (dicek 27/10 09:58)'].
     */
    public array $hasilCekPerJanji = [];

    public string $searchKeyword = '';
    public string $filterStatus = ''; // '' (semua) | ditunggu | didaftarkan
    public string $filterJalur = ''; // '' (semua) | ranap | igd | belum
    // Default bulan berjalan; dikosongkan = semua bulan (janji bulan lalu yang
    // masih ditunggu tetap bisa dicari dengan mengosongkannya).
    public string $filterBulan = ''; // mm/yyyy, bulan disetujui
    public int $itemsPerPage = 10;

    public function mount(): void
    {
        $this->filterBulan = Carbon::now(config('app.timezone'))->format('m/Y');
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['searchKeyword', 'filterStatus', 'filterJalur', 'filterBulan', 'itemsPerPage'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterJalur']);
        $this->filterBulan = Carbon::now(config('app.timezone'))->format('m/Y');
        $this->resetPage();
    }

    /** Muat ulang = buang cache Computed; datanya memang dibaca ulang tiap render. */
    public function muatUlang(): void
    {
        unset($this->semuaJanji);
    }

    /**
     * Tarik rujukan resmi (ServiceRequest) satu janji dari SATUSEHAT.
     *
     * Bentuk GET-nya daftar rujukan aktif ke RS kita (performer), bukan cari per
     * janji: hanya bentuk itu yang terbukti dijawab SATUSEHAT (grup resmi
     * 22/05/26). Dari daftar itu yang diambil HANYA kecocokan kuat dengan janji
     * ini — basedOn → CarePlan permintaan atau supportingInfo → Task
     * persetujuan (rujukanServiceRequestCocok); pasien yang sama bisa punya
     * lebih dari satu rujukan ke kita.
     */
    public function cekNomorRujukan(int $rujukanMasukNo): void
    {
        $janji = collect($this->findRujukanMasukDisetujui(null))->firstWhere('rujukanMasukNo', $rujukanMasukNo);

        if (! $janji) {
            $this->dispatch('toast', type: 'error', message: "Janji rujukan #{$rujukanMasukNo} tidak ditemukan.");

            return;
        }

        if (trim((string) ($janji['rujukanResmi']['serviceRequestId'] ?? '')) !== '') {
            unset($this->semuaJanji);
            $this->dispatch('toast', type: 'info', message: 'Janji ini sudah punya nomor rujukan resmi.');

            return;
        }

        $waktuCek = Carbon::now(config('app.timezone'))->format('d/m H:i');
        $respon = $this->rujukanServiceRequestMasukAktif();

        if ($respon['code'] < 200 || $respon['code'] >= 300) {
            $pesan = is_array($respon['body'])
                ? ($respon['body']['issue'][0]['details']['text'] ?? $respon['body']['issue'][0]['diagnostics'] ?? 'tanpa keterangan')
                : (string) $respon['body'];
            $this->hasilCekPerJanji[$rujukanMasukNo] = "Gagal dicek [{$respon['code']}] ({$waktuCek})";
            $this->dispatch('toast', type: 'error', message: 'Gagal membaca rujukan resmi dari SATUSEHAT [' . $respon['code'] . '] — ' . Str::limit($pesan, 160));

            return;
        }

        $cocok = $this->rujukanServiceRequestCocok(
            $respon['body'],
            (string) ($janji['permintaan']['rencanaId'] ?? ''),
            (string) $janji['taskId'],
        );

        if ($cocok === null) {
            $this->hasilCekPerJanji[$rujukanMasukNo] = "Belum terbit di SATUSEHAT (dicek {$waktuCek})";
            $this->dispatch('toast', type: 'info', message: 'Perujuk belum menerbitkan rujukan resmi untuk permintaan ini.');

            return;
        }

        $noRujukan = $this->rujukanNomorDariServiceRequest($cocok);
        $simpan = $this->simpanRujukanResmiJanji($rujukanMasukNo, (string) ($cocok['id'] ?? ''), $noRujukan);

        if (! $simpan['tersimpan']) {
            $this->hasilCekPerJanji[$rujukanMasukNo] = 'Ditemukan ' . ($noRujukan ?: '(tanpa nomor)') . ", gagal disimpan ({$waktuCek})";
            $this->dispatch('toast', type: 'warning', message: 'Nomor rujukan ditemukan tapi gagal disimpan: ' . $simpan['pesan'], duration: 10000);

            return;
        }

        unset($this->hasilCekPerJanji[$rujukanMasukNo], $this->semuaJanji);
        $this->dispatch('toast', type: 'success', message: $noRujukan !== ''
            ? "Nomor rujukan resmi {$noRujukan} ditemukan dan disimpan."
            : 'Rujukan resmi ditemukan dan disimpan, tetapi SATUSEHAT belum mencantumkan nomornya.');
    }

    /**
     * Seluruh janji + pasien lokalnya. Pasien lokal diambil dari pendaftaran bila
     * sudah didaftarkan (pasti benar), selain itu dari IHS (bisa tidak ketemu).
     */
    #[Computed]
    public function semuaJanji(): array
    {
        if (! $this->checkTabelRujukanMasuk()) {
            return [];
        }

        $janjiList = $this->findRujukanMasukDisetujui(null);
        $namaPasien = [];

        foreach ($janjiList as $indeks => $janji) {
            $regNo = (string) ($janji['pendaftaran']['regNo'] ?? '');

            if ($regNo === '') {
                $pasien = $this->findPasienDariIhs((string) ($janji['permintaan']['pasienIhs'] ?? ''));
                $regNo = (string) ($pasien->reg_no ?? '');
                $namaPasien[$regNo] = (string) ($pasien->reg_name ?? '');
            }

            $janjiList[$indeks]['pasienLokal'] = ['regNo' => $regNo, 'regName' => $namaPasien[$regNo] ?? null];
        }

        // Nama pasien yang sudah didaftarkan: satu query, bukan satu per baris.
        $regNoTanpaNama = collect($janjiList)
            ->filter(fn (array $janji): bool => $janji['pasienLokal']['regNo'] !== '' && $janji['pasienLokal']['regName'] === null)
            ->pluck('pasienLokal.regNo')
            ->unique()
            ->values()
            ->all();

        if ($regNoTanpaNama !== []) {
            $namaPasien += DB::table('rsmst_pasiens')->whereIn('reg_no', $regNoTanpaNama)->pluck('reg_name', 'reg_no')->all();
        }

        foreach ($janjiList as $indeks => $janji) {
            $janjiList[$indeks]['pasienLokal']['regName'] = (string) ($namaPasien[$janji['pasienLokal']['regNo']] ?? '');
        }

        return $janjiList;
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $kataKunci = strtolower(trim($this->searchKeyword));
        $bulan = $this->bulanFilter();

        $tersaring = array_values(array_filter($this->semuaJanji, function (array $janji) use ($kataKunci, $bulan): bool {
            $permintaan = $janji['permintaan'];

            if ($this->filterStatus === 'ditunggu' && $janji['sudahDidaftarkan']) {
                return false;
            }
            if ($this->filterStatus === 'didaftarkan' && ! $janji['sudahDidaftarkan']) {
                return false;
            }

            $jalur = (string) ($permintaan['jalur'] ?? '');
            if ($this->filterJalur === 'belum' && $jalur !== '') {
                return false;
            }
            if (in_array($this->filterJalur, ['ranap', 'igd'], true) && $jalur !== $this->filterJalur) {
                return false;
            }

            if ($bulan !== null) {
                $waktu = $janji['urut'] > 0 ? Carbon::createFromTimestamp($janji['urut'], config('app.timezone')) : null;
                if (! $waktu || $waktu->format('m/Y') !== $bulan) {
                    return false;
                }
            }

            if ($kataKunci === '') {
                return true;
            }

            $gabungan = strtolower(implode(' ', [
                $janji['taskId'],
                $permintaan['noPermintaan'] ?? '',
                $permintaan['pasienNama'] ?? '',
                $permintaan['pasienIhs'] ?? '',
                $permintaan['perujukNama'] ?? '',
                $permintaan['perujukOrgId'] ?? '',
                $permintaan['layananNama'] ?? '',
                $janji['pasienLokal']['regNo'],
                $janji['pasienLokal']['regName'],
                $janji['pendaftaran']['noKunjungan'] ?? '',
                $janji['rujukanResmi']['noRujukan'] ?? '',
            ]));

            return str_contains($gabungan, $kataKunci);
        }));

        $halaman = $this->getPage();

        return new LengthAwarePaginator(
            array_slice($tersaring, ($halaman - 1) * $this->itemsPerPage, $this->itemsPerPage),
            count($tersaring),
            $this->itemsPerPage,
            $halaman,
        );
    }

    /** Rekap dari seluruh baris, bukan hasil filter. */
    #[Computed]
    public function rekap(): array
    {
        $rekap = ['ditunggu' => 0, 'didaftarkan' => 0];

        foreach ($this->semuaJanji as $janji) {
            $rekap[$janji['sudahDidaftarkan'] ? 'didaftarkan' : 'ditunggu']++;
        }

        return $rekap;
    }

    /**
     * 'mm/yyyy' yang sah, atau null (= tidak menyaring). Isian setengah jadi saat
     * diketik ("10/20") jatuh ke null, jadi tabel tidak mendadak kosong.
     */
    private function bulanFilter(): ?string
    {
        $bulan = trim($this->filterBulan);

        if (! preg_match('/^(\d{2})\/(\d{4})$/', $bulan, $bagian) || (int) $bagian[1] < 1 || (int) $bagian[1] > 12) {
            return null;
        }

        return $bulan;
    }
};
?>

<div class="flex flex-col flex-1 min-h-0">

    {{-- TOOLBAR --}}
    <div
        class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-wrap items-end gap-3">

            <div class="w-full sm:flex-1">
                <x-input-label value="Pencarian" class="sr-only" />
                <div class="relative mt-1">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full pl-10"
                        placeholder="Cari pasien / No. RM / RS perujuk / nomor permintaan / No. kunjungan..." />
                </div>
            </div>

            <div class="w-full sm:w-auto">
                <x-input-label value="Status" />
                <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-48">
                    <option value="">Semua Status</option>
                    <option value="ditunggu">Ditunggu</option>
                    <option value="didaftarkan">Sudah Didaftarkan</option>
                </x-select-input>
            </div>

            <div class="w-full sm:w-auto">
                <x-input-label value="Layanan Diminta" />
                <x-select-input wire:model.live="filterJalur" class="w-full mt-1 sm:w-48">
                    <option value="">Semua Layanan</option>
                    <option value="ranap">Rawat Inap</option>
                    <option value="igd">Gawat Darurat</option>
                    <option value="belum">Belum terbuka</option>
                </x-select-input>
            </div>

            <div class="w-full sm:w-auto">
                <x-input-label value="Bulan Disetujui" />
                <x-text-input type="text" wire:model.live.debounce.500ms="filterBulan"
                    class="block w-full mt-1 sm:w-40" placeholder="mm/yyyy" maxlength="7" />
            </div>

            <div class="w-full sm:w-auto">
                <x-input-label value="Per halaman" />
                <x-select-input wire:model.live="itemsPerPage" class="w-full mt-1 sm:w-24">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </x-select-input>
            </div>

            <div class="flex items-center gap-3 ml-auto">
                <x-toolbar-refresh-reset :label="null" refresh-action="muatUlang" />
            </div>
        </div>
    </div>

    {{-- REKAP --}}
    @php $rekap = $this->rekap; @endphp
    <div
        class="mt-4 px-4 py-3 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap items-center gap-2">
            <span class="mr-1 text-sm font-semibold text-muted dark:text-gray-300">Janji disetujui:</span>
            <x-badge variant="warning">Ditunggu: {{ $rekap['ditunggu'] }}</x-badge>
            <x-badge variant="success">Sudah didaftarkan: {{ $rekap['didaftarkan'] }}</x-badge>
            <span class="ml-auto text-xs text-muted-soft">
                Sumber: basis data RS (RSTXN_RUJUKANMASUKS), bukan SATUSEHAT.
            </span>
        </div>
    </div>

    {{-- TABEL --}}
    <div
        class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

        <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
            <table class="min-w-full text-base -mt-3 border-separate border-spacing-y-3">
                <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                    <tr class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                        <th class="px-6 py-3 min-w-[240px]">Pasien</th>
                        <th class="px-6 py-3 min-w-[220px]">RS Perujuk</th>
                        <th class="px-6 py-3 min-w-[220px]">Layanan Diminta</th>
                        <th class="px-6 py-3 min-w-[160px]">Disetujui</th>
                        <th class="px-6 py-3 min-w-[200px]">Status</th>
                        <th class="px-6 py-3 min-w-[180px]">Rujukan Resmi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->rows as $row)
                        @php
                            $permintaan = $row['permintaan'];
                            $pasienLokal = $row['pasienLokal'];
                            $pendaftaran = $row['pendaftaran'];
                            $jalur = $permintaan['jalur'] ?? '';
                        @endphp
                        <tr class="transition bg-canvas dark:bg-gray-900
                               rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700
                               hover:shadow-lg hover:bg-surface-soft dark:hover:bg-gray-800"
                            wire:key="janji-rujukan-{{ $row['rujukanMasukNo'] }}">

                            <td class="px-6 py-4 rounded-l-2xl">
                                <div class="font-semibold text-ink dark:text-gray-100">
                                    {{ $pasienLokal['regName'] !== ''
                                        ? $pasienLokal['regName']
                                        : (($permintaan['pasienNama'] ?? '') !== '' ? $permintaan['pasienNama'] : '(nama tidak dikirim perujuk)') }}
                                </div>
                                <div class="text-sm text-muted dark:text-gray-400">
                                    IHS: {{ ($permintaan['pasienIhs'] ?? '') !== '' ? $permintaan['pasienIhs'] : '-' }}
                                </div>
                                <div class="mt-1">
                                    @if ($pasienLokal['regNo'] !== '')
                                        <x-badge variant="success">No. RM {{ $pasienLokal['regNo'] }}</x-badge>
                                    @else
                                        <x-badge variant="warning">Belum terpetakan ke No. RM</x-badge>
                                    @endif
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <div class="font-medium text-ink dark:text-gray-100">
                                    {{ ($permintaan['perujukNama'] ?? '') !== '' ? $permintaan['perujukNama'] : '(nama RS belum terbaca)' }}
                                </div>
                                <div class="text-sm text-muted dark:text-gray-400">
                                    Org ID: {{ ($permintaan['perujukOrgId'] ?? '') !== '' ? $permintaan['perujukOrgId'] : '-' }}
                                </div>
                                @if (($permintaan['dokterPerujuk'] ?? '') !== '')
                                    <div class="text-xs text-muted-soft">DPJP perujuk: {{ $permintaan['dokterPerujuk'] }}</div>
                                @endif
                            </td>

                            <td class="px-6 py-4">
                                @if ($jalur === 'ranap')
                                    <x-badge variant="info">Rawat Inap</x-badge>
                                @elseif ($jalur === 'igd')
                                    <x-badge variant="danger">Gawat Darurat</x-badge>
                                @else
                                    <x-badge variant="warning">Layanan belum terbuka</x-badge>
                                @endif
                                <div class="mt-1 text-sm text-muted dark:text-gray-400">
                                    {{ ($permintaan['layananNama'] ?? '') !== '' ? $permintaan['layananNama'] : '-' }}
                                </div>
                                <div class="text-xs text-muted-soft">
                                    No. Permintaan: {{ ($permintaan['noPermintaan'] ?? '') !== '' ? $permintaan['noPermintaan'] : '-' }}
                                </div>
                                <div class="font-mono text-xs break-all text-muted-soft">Task ID: {{ $row['taskId'] }}</div>
                            </td>

                            <td class="px-6 py-4 text-sm">
                                <div class="text-ink dark:text-gray-100">{{ $row['disetujui']['waktu'] ?? '-' }}</div>
                                <div class="text-xs text-muted dark:text-gray-400">
                                    oleh {{ ($row['disetujui']['oleh'] ?? '') !== '' ? $row['disetujui']['oleh'] : '-' }}
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                @if ($row['sudahDidaftarkan'])
                                    <x-badge variant="success">Sudah didaftarkan</x-badge>
                                    <div class="mt-1 text-sm font-medium text-ink dark:text-gray-100">
                                        {{ ($pendaftaran['jenis'] ?? '') === 'ri' ? 'Rawat Inap' : 'UGD' }}
                                        No. {{ $pendaftaran['noKunjungan'] ?? '-' }}
                                    </div>
                                    <div class="text-xs text-muted dark:text-gray-400">
                                        {{ $pendaftaran['waktu'] ?? '-' }}
                                        @if (($pendaftaran['oleh'] ?? '') !== '')
                                            &middot; {{ $pendaftaran['oleh'] }}
                                        @endif
                                    </div>
                                @else
                                    <x-badge variant="warning">Ditunggu</x-badge>
                                    <div class="mt-1 text-xs text-muted dark:text-gray-400">Pasien belum tiba / belum didaftarkan</div>
                                @endif
                            </td>

                            <td class="px-6 py-4 text-sm rounded-r-2xl">
                                @if (($row['rujukanResmi']['serviceRequestId'] ?? '') !== '')
                                    <div class="font-medium text-ink dark:text-gray-100">
                                        {{ ($row['rujukanResmi']['noRujukan'] ?? '') !== '' ? $row['rujukanResmi']['noRujukan'] : '(nomor kosong)' }}
                                    </div>
                                    <div class="font-mono text-xs break-all text-muted-soft">
                                        SR: {{ $row['rujukanResmi']['serviceRequestId'] }}
                                    </div>
                                @else
                                    {{-- Dipungut lewat tombol ini, atau otomatis saat Encounter kunjungannya dikirim. --}}
                                    <div class="text-xs text-muted dark:text-gray-400">
                                        {{ $hasilCekPerJanji[$row['rujukanMasukNo']] ?? 'Belum ada nomor' }}
                                    </div>
                                    <x-outline-button type="button" class="mt-2 whitespace-nowrap"
                                        wire:click="cekNomorRujukan({{ $row['rujukanMasukNo'] }})"
                                        wire:loading.attr="disabled" wire:target="cekNomorRujukan({{ $row['rujukanMasukNo'] }})">
                                        <span wire:loading.remove wire:target="cekNomorRujukan({{ $row['rujukanMasukNo'] }})">Cek di SATUSEHAT</span>
                                        <span wire:loading wire:target="cekNomorRujukan({{ $row['rujukanMasukNo'] }})">Mengecek...</span>
                                    </x-outline-button>
                                @endif
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-muted dark:text-gray-400">
                                @if (count($this->semuaJanji) > 0)
                                    Tidak ada janji yang cocok dengan filter.
                                @else
                                    Belum ada permintaan rujukan yang disetujui.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-hairline dark:border-gray-700">
            {{ $this->rows->links() }}
        </div>
    </div>
</div>
