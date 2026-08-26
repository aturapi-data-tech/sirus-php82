<?php
// ╔══════════════════════════════════════════════════════════════════════╗
// ║  RUJUKAN MASUK DISETUJUI — daftar pasien yang ditunggu kedatangannya ║
// ╚══════════════════════════════════════════════════════════════════════╝
//
// Menyetujui permintaan rujukan TIDAK berarti pasiennya datang: ia bisa tiba
// besok, atau tidak datang sama sekali. Karena itu persetujuan hanya menyimpan
// JANJI (RSTXN_RUJUKANMASUKS), dan layar ini yang menjembataninya ke kunjungan
// nyata — petugas membukanya SAAT PASIENNYA TIBA, memilih barisnya, lalu form
// pendaftaran UGD terbuka sudah terisi.
//
// Komponen ini MODAL SAJA, dibuka lewat event 'rujukan-masuk-disetujui.open';
// tombol pemanggilnya milik layar pemakai (toolbar /ugd/daftar berikut lencana
// jumlahnya). Sengaja dipisah: toolbar itu sticky ber-z-index, jadi ia membuat
// konteks tumpukan sendiri — modal yang bersarang di dalamnya akan tertimbun
// navbar layout yang z-50.
//
// PENCOCOKAN PASIEN CUMA LEWAT IHS. Nama tak bisa dipakai karena Patient/<ihs>
// dari SATUSEHAT itu cangkang (name null, NIK di-mask), dan baru 4,7% pasien
// lokal punya PATIENT_UUID. Jadi "tidak ketemu" adalah hasil yang WAJAR, bukan
// error: barisnya tetap bisa didaftarkan, petugas mencari pasiennya manual di
// form, dan IHS-nya ditulis balik saat itu supaya cakupannya menambal sendiri.

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\RujukanMasuk\RujukanMasukTrait;

new class extends Component {
    use RujukanMasukTrait;

    /** Baris janji yang pasiennya masih ditunggu; diisi saat modal dibuka. */
    public array $daftar = [];

    public bool $sudahDimuat = false;

    #[On('rujukan-masuk-disetujui.open')]
    public function buka(): void
    {
        $this->muat();
        $this->dispatch('open-modal', name: 'rujukan-masuk-disetujui');
    }

    public function muat(): void
    {
        $this->sudahDimuat = true;
        $this->daftar = array_map(function (array $baris): array {
            $pasien = $this->findPasienDariIhs((string) ($baris['permintaan']['pasienIhs'] ?? ''));

            $baris['pasienLokal'] = [
                'regNo' => (string) ($pasien->reg_no ?? ''),
                'regName' => (string) ($pasien->reg_name ?? ''),
            ];

            return $baris;
        }, $this->findRujukanMasukDisetujui(true));
    }

    /**
     * Pasiennya tiba → buka form pendaftaran UGD yang sudah terisi.
     *
     * Yang dikirim hanya BAHAN, bukan perintah simpan: petugas tetap melengkapi
     * dokter, klaim, dan waktu, lalu menekan Simpan sendiri. Janji rujukan baru
     * ditandai terpakai setelah kunjungannya benar-benar tersimpan — kalau
     * ditandai di sini, membatalkan form akan menghilangkan pasien dari daftar
     * tunggu padahal ia belum terdaftar di mana pun.
     */
    public function daftarkanKeUgd(int $indeks): void
    {
        $baris = $this->daftar[$indeks] ?? null;

        if (! $baris) {
            $this->dispatch('toast', type: 'error', message: 'Baris janji rujukan tidak terbaca — muat ulang daftarnya.');

            return;
        }

        $this->dispatch('daftar-ugd.create.open', rujukanMasuk: [
            'rujukanMasukNo' => $baris['rujukanMasukNo'],
            'taskId' => $baris['taskId'],
            // Kunci pencarian rujukan resmi belakangan: ServiceRequest yang
            // diterbitkan perujuk menunjuk balik ke CarePlan permintaan ini.
            'rencanaId' => (string) ($baris['permintaan']['rencanaId'] ?? ''),
            'pasienIhs' => (string) ($baris['permintaan']['pasienIhs'] ?? ''),
            'pasienNama' => (string) ($baris['permintaan']['pasienNama'] ?? ''),
            'perujukNama' => (string) ($baris['permintaan']['perujukNama'] ?? ''),
            'perujukOrgId' => (string) ($baris['permintaan']['perujukOrgId'] ?? ''),
            'jalur' => (string) ($baris['permintaan']['jalur'] ?? ''),
            'layananNama' => (string) ($baris['permintaan']['layananNama'] ?? ''),
            'noPermintaan' => (string) ($baris['permintaan']['noPermintaan'] ?? ''),
            'regNo' => $baris['pasienLokal']['regNo'],
            'regName' => $baris['pasienLokal']['regName'],
        ]);

        $this->dispatch('close-modal', name: 'rujukan-masuk-disetujui');
    }

    /** Pendaftaran UGD tersimpan → satu janji mungkin baru saja terpakai. */
    #[On('refresh-after-ugd.saved')]
    public function segarkan(): void
    {
        if ($this->sudahDimuat) {
            $this->muat();
        }
    }
};
?>

<div>
    <x-modal name="rujukan-masuk-disetujui" size="full" height="full" focusable>
        {{-- Modal full: padding panel jadi p-0, header/body/footer memakai padding sendiri. --}}
        <div class="flex flex-col min-h-[calc(100vh-8rem)]">

            {{-- HEADER --}}
            <div class="flex flex-wrap items-start gap-3 px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="flex-1 min-w-0">
                    <h2 class="text-lg font-semibold text-ink dark:text-gray-100">
                        Rujukan Masuk Disetujui
                    </h2>
                    <p class="mt-1 text-sm text-muted dark:text-gray-400">
                        Pasien yang permintaan rujukannya sudah kita setujui dan ditunggu kedatangannya.
                        Pilih barisnya saat pasiennya tiba — form Pendaftaran UGD terbuka sudah terisi.
                    </p>
                </div>
                <x-outline-button type="button" wire:click="muat" wire:loading.attr="disabled" wire:target="muat"
                    class="whitespace-nowrap">
                    <span wire:loading.remove wire:target="muat">Muat Ulang</span>
                    <span wire:loading wire:target="muat">Memuat...</span>
                </x-outline-button>
            </div>

            {{-- BODY --}}
            <div class="flex flex-col flex-1 gap-4 px-6 py-5 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">

                {{-- Daftar ini murni dari basis data kita, bukan SATUSEHAT: yang belum
                     pernah kita setujui tidak akan pernah muncul di sini. --}}
                <div
                    class="p-3 text-sm border rounded-xl bg-blue-50 border-blue-200 text-info-deep dark:bg-blue-900/20 dark:border-blue-800">
                    Baris hilang dari daftar begitu pendaftaran kunjungannya tersimpan. Yang belum tiba
                    tetap menunggu di sini — menyetujui rujukan tidak membuat kunjungan apa pun.
                </div>

                <div class="overflow-x-auto border bg-canvas rounded-2xl border-hairline dark:border-gray-700">
                    <table class="ds-table">
                        <thead>
                            <tr>
                                <th class="ds-c w-10">No</th>
                                <th class="min-w-[240px]">Pasien</th>
                                <th class="min-w-[200px]">RS Perujuk</th>
                                <th class="min-w-[200px]">Layanan Diminta</th>
                                <th class="min-w-[170px]">Disetujui</th>
                                <th class="ds-c w-44">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($daftar as $indeks => $baris)
                                @php
                                    $permintaan = $baris['permintaan'] ?? [];
                                    $pasienLokal = $baris['pasienLokal'] ?? ['regNo' => '', 'regName' => ''];
                                    $terpetakan = $pasienLokal['regNo'] !== '';
                                @endphp
                                <tr wire:key="rujukan-ditunggu-{{ $baris['rujukanMasukNo'] }}">
                                    <td class="ds-c ds-td-meta">{{ $indeks + 1 }}</td>

                                    <td>
                                        {{-- Nama yang dipakai adalah nama PASIEN LOKAL bila IHS-nya
                                             ketemu; nama dari perujuk cuma cadangan, dan hampir
                                             selalu kosong karena SATUSEHAT tidak mengirimnya. --}}
                                        <span class="ds-td-strong">
                                            {{ $terpetakan
                                                ? $pasienLokal['regName']
                                                : (($permintaan['pasienNama'] ?? '') !== ''
                                                    ? $permintaan['pasienNama']
                                                    : '(nama tidak dikirim perujuk)') }}
                                        </span>
                                        <span class="block mt-1 text-xs text-muted dark:text-gray-400">
                                            IHS {{ ($permintaan['pasienIhs'] ?? '') !== '' ? $permintaan['pasienIhs'] : '—' }}
                                        </span>
                                        <span class="inline-flex mt-1">
                                            @if ($terpetakan)
                                                <x-badge variant="success">No. RM {{ $pasienLokal['regNo'] }}</x-badge>
                                            @else
                                                <x-badge variant="warning">Cari pasiennya di form</x-badge>
                                            @endif
                                        </span>
                                    </td>

                                    <td>
                                        <span class="ds-td-strong">
                                            {{ ($permintaan['perujukNama'] ?? '') !== '' ? $permintaan['perujukNama'] : '(nama RS belum terbaca)' }}
                                        </span>
                                        <span class="block mt-1 text-xs text-muted dark:text-gray-400">
                                            Org ID {{ ($permintaan['perujukOrgId'] ?? '') !== '' ? $permintaan['perujukOrgId'] : '—' }}
                                        </span>
                                        @if (($permintaan['dokterPerujuk'] ?? '') !== '')
                                            <span class="block text-xs text-muted-soft">
                                                DPJP perujuk: {{ $permintaan['dokterPerujuk'] }}
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        @if (($permintaan['jalur'] ?? '') === 'ranap')
                                            <x-badge variant="info">Rawat Inap</x-badge>
                                        @elseif (($permintaan['jalur'] ?? '') === 'igd')
                                            <x-badge variant="danger">Gawat Darurat</x-badge>
                                        @else
                                            <x-badge variant="gray">Layanan tidak dikenali</x-badge>
                                        @endif
                                        <span class="block mt-1 text-xs text-muted dark:text-gray-400">
                                            {{ ($permintaan['layananNama'] ?? '') !== '' ? $permintaan['layananNama'] : '—' }}
                                        </span>
                                        <span class="block text-xs text-muted-soft">
                                            No. Permintaan: {{ ($permintaan['noPermintaan'] ?? '') !== '' ? $permintaan['noPermintaan'] : '—' }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="ds-td-strong">{{ $baris['disetujui']['waktu'] ?? '—' }}</span>
                                        <span class="block mt-1 text-xs text-muted dark:text-gray-400">
                                            oleh {{ ($baris['disetujui']['oleh'] ?? '') !== '' ? $baris['disetujui']['oleh'] : '—' }}
                                        </span>
                                    </td>

                                    <td class="ds-c">
                                        {{-- Rujukan Ranap pun didaftarkan lewat UGD: pasien rujukan
                                             ranap umumnya masuk lewat IGD dulu. Yang langsung ke
                                             admisi ranap tetap didaftarkan di layar RI, dan janjinya
                                             akan menunggu di sini sampai jalur RI dibangun. --}}
                                        <x-primary-button type="button" wire:click="daftarkanKeUgd({{ $indeks }})"
                                            class="whitespace-nowrap">
                                            Daftarkan ke UGD
                                        </x-primary-button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-muted dark:text-gray-400">
                                        @if (!$sudahDimuat)
                                            Daftar belum dimuat.
                                        @else
                                            Tidak ada pasien rujukan yang sedang ditunggu.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>{{-- /BODY --}}

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 flex flex-wrap justify-end gap-2 px-6 py-4 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                <x-secondary-button type="button"
                    x-on:click="$dispatch('close-modal', { name: 'rujukan-masuk-disetujui' })">
                    Tutup
                </x-secondary-button>
            </div>

        </div>
    </x-modal>
</div>
