<?php
// Modal daftar pasien per jalur untuk Hitung Jumlah Diagnosa.
//
// Dibuka dari halaman induk (⚡hitung-diagnosa) lewat event hitung-diagnosa.openDaftar
// saat baris RJ / UGD / RI diklik — pola sama dengan master-poli → master-poli-actions.
// Induk mengirim SEMUA parameter hitungan (kode, sumber, periode), jadi isi daftar
// adalah kunjungan yang sama persis dengan angka di tabel induk.

use App\Http\Traits\Manajemen\Rs\HitungDiagnosaTrait;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use HitungDiagnosaTrait, WithPagination;

    /** Jalur yang daftar pasiennya sedang dibuka ('' = modal tertutup). */
    public string $jalurDaftar = '';
    public string $kode = '';
    public string $deskripsi = '';
    public string $sumber = 'emr';
    public string $tanggalDari = ''; // Y-m-d
    public string $tanggalSampai = ''; // Y-m-d
    public string $cariDaftar = '';
    public int $itemsPerPage = 25;

    #[On('hitung-diagnosa.openDaftar')]
    public function openDaftar(string $jalur, string $kode, string $deskripsi, string $sumber, string $tanggalDari, string $tanggalSampai): void
    {
        // Parameter datang dari klien → whitelist, jangan dipercaya mentah.
        if (!in_array($jalur, ['RJ', 'UGD', 'RI'], true) || trim($kode) === '') {
            return;
        }

        $this->jalurDaftar = $jalur;
        $this->kode = trim($kode);
        $this->deskripsi = $deskripsi;
        $this->sumber = array_key_exists($sumber, self::SUMBER_DIAGNOSA) ? $sumber : 'emr';
        $this->tanggalDari = $tanggalDari;
        $this->tanggalSampai = $tanggalSampai;
        $this->cariDaftar = '';
        $this->resetPage();
        unset($this->daftarKunjungan);

        $this->dispatch('open-modal', name: 'hitung-diagnosa-daftar');
    }

    public function updatedCariDaftar(): void
    {
        $this->resetPage();
    }

    /** [awal, akhir] dari tanggal kiriman induk; null bila tidak terbaca. */
    private function periodeDaftar(): ?array
    {
        try {
            $dari = Carbon::createFromFormat('!Y-m-d', $this->tanggalDari, config('app.timezone'));
            $sampai = Carbon::createFromFormat('!Y-m-d', $this->tanggalSampai, config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }

        return [$dari->startOfDay(), $sampai->endOfDay()];
    }

    /** "dd/mm/yyyy s/d dd/mm/yyyy" untuk header modal. */
    public function teksPeriode(): string
    {
        $periode = $this->periodeDaftar();

        return $periode === null ? '-' : $periode[0]->format('d/m/Y') . ' s/d ' . $periode[1]->format('d/m/Y');
    }

    /**
     * Seluruh kunjungan jalur terpilih — hasil query TIDAK disimpan di properti publik
     * (bisa ribuan baris untuk periode tahunan), dihitung ulang tiap render modal.
     */
    #[Computed]
    public function daftarKunjungan(): array
    {
        $periode = $this->periodeDaftar();
        if ($this->jalurDaftar === '' || $this->kode === '' || $periode === null) {
            return [];
        }

        [$dari, $sampai] = $periode;
        $sumber = array_key_exists($this->sumber, self::SUMBER_DIAGNOSA) ? $this->sumber : 'emr';
        $daftar = $this->daftarKunjunganDiagnosa($this->kode, $dari, $sampai, $sumber, $this->jalurDaftar);

        $keyword = mb_strtolower(trim($this->cariDaftar));
        if ($keyword === '') {
            return $daftar;
        }

        return array_values(array_filter($daftar, fn(array $row) => str_contains(
            mb_strtolower($row['regNo'] . ' ' . $row['nama'] . ' ' . $row['kunjunganNo'] . ' ' . $row['sep']),
            $keyword,
        )));
    }

    /** Paginasi dirakit sendiri: sebagian baris berasal dari CLOB yang di-decode di PHP. */
    #[Computed]
    public function daftarHalaman(): LengthAwarePaginator
    {
        $semua = $this->daftarKunjungan;
        $halaman = Paginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            array_slice($semua, ($halaman - 1) * $this->itemsPerPage, $this->itemsPerPage),
            count($semua),
            $this->itemsPerPage,
            $halaman,
            ['path' => request()->url()],
        );
    }
};
?>

<div>
    <x-modal name="hitung-diagnosa-daftar" size="full" height="full" focusable>
        @php
            $labelJalurDaftar = ['RJ' => 'Rawat Jalan', 'UGD' => 'UGD', 'RI' => 'Rawat Inap'][$jalurDaftar] ?? '';
            $daftarHalaman = $jalurDaftar !== '' ? $this->daftarHalaman : null;
            $labelSumber = $this::SUMBER_DIAGNOSA[$sumber] ?? 'EMR';
            $angka = fn($nilai) => number_format((float) $nilai);
        @endphp

        <div class="flex flex-col min-h-[calc(100vh-8rem)]">

            <x-modul-dokumen.header judul="Daftar Pasien — {{ $kode }} · {{ $labelJalurDaftar }}"
                namaModal="hitung-diagnosa-daftar"
                ikon="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z">
                {{ $deskripsi }} · Sumber {{ $labelSumber }} ·
                {{ $this->teksPeriode() }}
                <x-slot:badge>
                    @if ($daftarHalaman)
                        <x-badge class="shrink-0 whitespace-nowrap" variant="brand">{{ $angka($daftarHalaman->total()) }} kunjungan</x-badge>
                    @endif
                </x-slot:badge>
            </x-modul-dokumen.header>

            {{-- BODY --}}
            <div class="flex flex-col flex-1 min-h-0 gap-4 px-6 py-5 bg-surface-soft/70 dark:bg-gray-950/20">

                <div class="relative w-full sm:max-w-md">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <x-text-input wire:model.live.debounce.300ms="cariDaftar" class="block w-full pl-10"
                        placeholder="Cari No RM / nama pasien / no kunjungan / SEP..." />
                </div>

                <div
                    class="flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                        <table class="min-w-full text-base -mt-3 border-separate border-spacing-y-3">
                            <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                                <tr class="text-sm font-semibold tracking-wide text-left uppercase text-muted dark:text-gray-300">
                                    <th class="px-6 py-3 min-w-[260px]">Pasien</th>
                                    <th class="px-6 py-3 min-w-[200px]">Kunjungan</th>
                                    <th class="px-6 py-3 min-w-[240px]">{{ $jalurDaftar === 'RI' ? 'DPJP' : 'Poli / Dokter' }}</th>
                                    <th class="px-6 py-3 min-w-[260px]">Diagnosa</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($daftarHalaman ?? [] as $row)
                                    <tr class="transition bg-canvas dark:bg-gray-900
                                           rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700
                                           hover:shadow-lg hover:bg-surface-soft dark:hover:bg-gray-800"
                                        wire:key="hitung-diagnosa-daftar-{{ $jalurDaftar }}-{{ $row['kunjunganNo'] }}">
                                        <td class="px-6 py-4 align-top rounded-l-2xl">
                                            <x-list.identitas-pasien :regNo="$row['regNo']" :nama="$row['nama']" :sex="$row['sex']"
                                                :tglLahir="$row['tglLahir']" :alamat="$row['alamat']" />
                                        </td>
                                        <td class="px-6 py-4 space-y-1 text-sm align-top">
                                            <div class="font-mono text-ink dark:text-gray-100">No. {{ $row['kunjunganNo'] }}</div>
                                            @if ($jalurDaftar === 'RI')
                                                <div class="text-body dark:text-gray-300">Masuk: {{ $row['masukTampil'] !== '' ? $row['masukTampil'] : '-' }}</div>
                                                <div class="text-body dark:text-gray-300">Pulang: {{ $row['tanggalTampil'] !== '' ? $row['tanggalTampil'] : '-' }}</div>
                                            @else
                                                <div class="text-body dark:text-gray-300">{{ $row['tanggalTampil'] !== '' ? $row['tanggalTampil'] : '-' }}</div>
                                            @endif
                                            <div class="text-muted dark:text-gray-400">{{ $row['penjamin'] !== '' ? $row['penjamin'] : '-' }}</div>
                                            @if ($row['sep'] !== '')
                                                <div class="font-mono text-xs text-emerald-700 dark:text-emerald-400">SEP {{ $row['sep'] }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 space-y-1 text-sm align-top">
                                            @if ($jalurDaftar === 'RI')
                                                {{-- DPJP dari Leveling Dokter (EMR RI → Pengkajian Awal) --}}
                                                @if ($row['dpjpUtama'] !== '')
                                                    <div class="font-medium text-ink dark:text-gray-100">{{ $row['dpjpUtama'] }}</div>
                                                    <div class="text-xs text-muted-soft">DPJP Utama</div>
                                                @else
                                                    <div class="text-body dark:text-gray-300">{{ $row['dokter'] !== '' ? $row['dokter'] : '-' }}</div>
                                                    <div class="text-xs text-amber-700 dark:text-amber-400">Leveling dokter belum diisi — dokter admisi</div>
                                                @endif
                                                @if (count($row['rawatGabung']) > 0)
                                                    <div class="pt-1 text-body dark:text-gray-300">{{ implode(', ', $row['rawatGabung']) }}</div>
                                                    <div class="text-xs text-muted-soft">Rawat Gabung</div>
                                                @endif
                                            @else
                                                <div class="font-medium text-ink dark:text-gray-100">{{ $row['poli'] !== '' ? $row['poli'] : '-' }}</div>
                                                <div class="text-body dark:text-gray-300">{{ $row['dokter'] !== '' ? $row['dokter'] : '-' }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 space-y-1 text-sm align-top rounded-r-2xl">
                                            @foreach ($row['kodeList'] as $kodeRow)
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <span class="font-mono font-semibold text-ink dark:text-gray-100">{{ $kodeRow['kode'] }}</span>
                                                    @if ($kodeRow['primer'])
                                                        <x-badge variant="brand">Primer</x-badge>
                                                    @endif
                                                    <span class="text-muted dark:text-gray-400">{{ $kodeRow['desc'] }}</span>
                                                </div>
                                            @endforeach
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-6 py-10 text-center text-muted dark:text-gray-400">
                                            {{ trim($cariDaftar) !== '' ? 'Tidak ada pasien yang cocok dengan pencarian.' : 'Tidak ada kunjungan.' }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($daftarHalaman && $daftarHalaman->hasPages())
                        <div class="px-4 py-3 border-t bg-canvas border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                            {{ $daftarHalaman->links() }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 flex flex-wrap justify-end gap-2 px-6 py-4 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'hitung-diagnosa-daftar' })">
                    Tutup
                </x-secondary-button>
            </div>

        </div>
    </x-modal>
</div>
