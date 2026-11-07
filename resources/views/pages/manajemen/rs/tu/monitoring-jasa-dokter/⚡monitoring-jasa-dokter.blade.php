<?php
// resources/views/pages/manajemen/rs/tu/monitoring-jasa-dokter/monitoring-jasa-dokter.blade.php
//
// Monitoring Jasa Dokter — pergerakan transaksi jasa dokter per bulan jasa.
//
// Halaman ini MENGGANTIKAN Slip Gaji Dokter (2026-11-07). Penggajian dokter kini
// diproses di SIAP: struktur gaji disetel di SIAP, slipnya dihitung di SIAP, dan
// SIRUS tinggal memasok transaksi mentah lewat API SIAP
// (GET /api/siap/gaji-dokter/{tahun}/{bulan}/transaksi). Yang tersisa untuk SIRUS
// adalah fungsi administratifnya: memperlihatkan APA yang dipasok — per dokter,
// per jalur layanan, dibanding bulan jasa sebelumnya — dan menelusuri sampai baris
// pasien bila SIAP mempertanyakan angkanya.
//
// Tidak ada tombol Proses / Final / Cetak / Hapus: tidak ada lagi yang ditulis
// dari layar ini. Mesin slip lama (kelas GajiDokter, tabel RSTXN_GAJIDOCTOR*,
// kolom parameter gaji di RSMST_DOCTORS) dihapus 2026-11-07 —
// database/sql/2026_11_07_drop_gaji_dokter.sql.

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use App\Support\GajiDokter\GajiDokterLampiran;
use App\Support\GajiDokter\MonitoringJasaDokter;
use Carbon\Carbon;

new class extends Component {
    #[Session(key: 'monitoringJasaDokter.tahunJasa')]
    public string $tahunJasa = '';

    #[Session(key: 'monitoringJasaDokter.bulanJasa')]
    public string $bulanJasa = '';

    public string $cariDokter = '';

    public function mount(): void
    {
        // #[Session] mengembalikan nilai lama antar kunjungan; pada kunjungan
        // pertama isinya kosong — baru di situ diisi bulan jasa yang lazim
        // ditarik SIAP, yaitu bulan lalu.
        if ($this->tahunJasa === '' || $this->bulanJasa === '') {
            $this->isiPeriodeAwal();
        }
    }

    protected function isiPeriodeAwal(): void
    {
        $bulanLalu = Carbon::now()->subMonth();
        $this->tahunJasa = $bulanLalu->format('Y');
        $this->bulanJasa = $bulanLalu->format('m');
    }

    protected function periodeValid(): bool
    {
        return preg_match('/^\d{4}$/', $this->tahunJasa) === 1 && preg_match('/^(0[1-9]|1[0-2])$/', $this->bulanJasa) === 1;
    }

    #[Computed]
    public function periodeJasaLabel(): string
    {
        return $this->periodeValid() ? MonitoringJasaDokter::labelPeriode($this->tahunJasa, $this->bulanJasa) : '-';
    }

    #[Computed]
    public function periodeLaluLabel(): string
    {
        return $this->periodeValid() ? MonitoringJasaDokter::labelPeriode(...MonitoringJasaDokter::bulanSebelumnya($this->tahunJasa, $this->bulanJasa)) : '-';
    }

    /** Satu baris per dokter (bulan ini + bulan lalu), sudah tersaring nama & terurut nominal. */
    #[Computed]
    public function rows()
    {
        if (!$this->periodeValid()) {
            return collect();
        }

        return MonitoringJasaDokter::pergerakan($this->tahunJasa, $this->bulanJasa, $this->cariDokter);
    }

    #[Computed]
    public function ringkasan(): array
    {
        return MonitoringJasaDokter::ringkasan($this->rows);
    }

    /**
     * Dipanggil <x-toolbar-refresh-reset>. Periode disimpan lewat #[Session],
     * jadi reset harus menuliskan nilai awalnya kembali — $this->reset() saja
     * tidak cukup karena sesi akan mengisi ulang.
     */
    public function resetFilters(): void
    {
        $this->isiPeriodeAwal();
        $this->cariDokter = '';

        unset($this->rows, $this->ringkasan);
    }

    /* ===============================
     | AKSI — semuanya baca saja
     =============================== */

    /** Baris pasien SATU dokter di layar (modal lampiran). */
    public function lihatRincian(string $drId): void
    {
        $this->dispatch('jasa.dokter.openRincian', drIdList: [$drId], tahunJasa: $this->tahunJasa, bulanJasa: $this->bulanJasa, modeSemua: false);
    }

    /**
     * Baris pasien SEMUA dokter yang sedang tampil. Yang dikirim ke modal cuma
     * daftar DR_ID; datanya ditarik komponen lampiran — hasil kueri besar
     * bukan untuk properti publik.
     */
    public function lihatRincianSemua(): void
    {
        $drIdList = $this->rows->where('transaksi', '>', 0)->pluck('dr_id')->values()->all();

        if ($drIdList === []) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada transaksi pada tampilan ini.');
            return;
        }

        $this->dispatch('jasa.dokter.openRincian', drIdList: $drIdList, tahunJasa: $this->tahunJasa, bulanJasa: $this->bulanJasa, modeSemua: true);
    }

    /** Rekap per dokter yang sedang tampil sebagai CSV siap Excel. */
    public function unduhRekap(): mixed
    {
        $rows = $this->rows;

        if ($rows->isEmpty()) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada data untuk diunduh.');
            return null;
        }

        $tahunJasa = $this->tahunJasa;
        $bulanJasa = $this->bulanJasa;

        return response()->streamDownload(function () use ($rows, $tahunJasa, $bulanJasa) {
            $keluaran = fopen('php://output', 'w');
            MonitoringJasaDokter::tulisCsvRekap($keluaran, $rows, $tahunJasa, $bulanJasa);
            fclose($keluaran);
        }, 'jasa-dokter-' . $tahunJasa . $bulanJasa . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
};
?>

<div>
    <x-page-title
        title="Monitoring Jasa Dokter"
        subtitle="Pergerakan transaksi jasa dokter per bulan jasa — data yang dipasok SIRUS ke SIAP untuk penggajian" />

    {{-- Kerangka halaman meniru pelayanan-rj: tinggi layar dikunci lalu dibagi
         flex-kolom, sehingga yang menggulung adalah isi tabel — toolbar & kepala
         kolom tetap di tempat. --}}
    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-0 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 pt-1 pb-2 bg-surface-soft border-b border-hairline top-16 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Bulan Jasa" />
                        <x-select-input wire:model.live="bulanJasa" class="mt-1 block w-full sm:w-32">
                            @foreach (GajiDokterLampiran::NAMA_BULAN as $kode => $nama)
                                <option value="{{ $kode }}">{{ $nama }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Tahun Jasa" />
                        <x-text-input type="text" wire:model.live.debounce.500ms="tahunJasa" inputmode="numeric"
                            maxlength="4" class="mt-1 block w-full sm:w-20 text-right" />
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Cari Dokter" />
                        <x-text-input type="text" wire:model.live.debounce.500ms="cariDokter"
                            class="mt-1 block w-full sm:w-44" placeholder="nama dokter" />
                    </div>

                    <div class="ml-auto flex flex-wrap items-end gap-2">
                        {{-- Rincian = outline: membuka tampilan pendamping, bukan aksi utama. --}}
                        <x-outline-button type="button" class="gap-2" wire:click="lihatRincianSemua"
                            wire:loading.attr="disabled" wire:target="lihatRincianSemua"
                            :disabled="$this->ringkasan['transaksi'] === 0"
                            title="Baris pasien semua dokter pada tampilan ini — tanggal layanan, no. RM, nama, klaim, nominal">
                            <span wire:loading.remove wire:target="lihatRincianSemua" class="inline-flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                                </svg>
                                Rincian Semua
                            </span>
                            <span wire:loading wire:target="lihatRincianSemua" class="inline-flex items-center gap-2">
                                <x-loading /> Menyiapkan...
                            </span>
                        </x-outline-button>

                        <x-info-button type="button" class="gap-2" wire:click="unduhRekap"
                            wire:loading.attr="disabled" wire:target="unduhRekap"
                            :disabled="$this->rows->isEmpty()"
                            title="Unduh rekap per dokter pada tampilan ini sebagai CSV — siap dibuka di Excel">
                            <span wire:loading.remove wire:target="unduhRekap" class="inline-flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                                Unduh Excel
                            </span>
                            <span wire:loading wire:target="unduhRekap" class="inline-flex items-center gap-2">
                                <x-loading /> Menyiapkan...
                            </span>
                        </x-info-button>

                        <x-toolbar-refresh-reset :label="null" />

                        <a href="{{ route('manajemen.monitoring-keuangan') }}" wire:navigate
                            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-body bg-canvas border border-gray-300 rounded-lg hover:bg-surface-soft dark:bg-gray-900 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-800 shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Kembali
                        </a>
                    </div>
                </div>

                <p class="mt-2 text-sm text-muted dark:text-gray-400">
                    Transaksi jasa <span class="font-semibold">{{ $this->periodeJasaLabel }}</span> dibandingkan dengan
                    <span class="font-semibold">{{ $this->periodeLaluLabel }}</span>. Angka di sini sama dengan yang
                    ditarik SIAP untuk menghitung gaji dokter; slip, potongan, dan PPh 21 dilihat di SIAP.
                </p>
            </div>

            {{-- Ringkasan & Panduan sebaris: keduanya panel yang bisa ditutup, jadi
                 saat ditutup dua-duanya cuma satu bilah tipis. --}}
            <div class="grid grid-cols-1 gap-3 mt-4 lg:grid-cols-2 items-start">
                @php $ringkasan = $this->ringkasan; @endphp
                <div x-data="{ buka: false }"
                    class="overflow-hidden border rounded-2xl border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700">
                    <button type="button" x-on:click="buka = !buka"
                        class="flex items-center justify-between w-full px-4 py-2.5 text-sm font-semibold transition-colors text-ink hover:bg-surface-soft dark:text-gray-100 dark:hover:bg-gray-800">
                        <span class="flex items-center min-w-0 gap-2">
                            <svg class="w-4 h-4 shrink-0 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 17V7m4 10V11m4 6V9M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            <span class="shrink-0">Ringkasan periode</span>
                            <span class="font-normal truncate text-sm text-muted dark:text-gray-400">
                                ({{ $ringkasan['dokter'] }} dokter &middot; {{ number_format($ringkasan['nominal'], 0, ',', '.') }}
                                @if ($ringkasan['persen'] !== null)
                                    &middot; {{ $ringkasan['selisih'] >= 0 ? '+' : '' }}{{ number_format($ringkasan['persen'], 1, ',', '.') }}% dari bulan lalu
                                @endif)
                            </span>
                        </span>
                        <svg class="w-4 h-4 ml-2 transition-transform shrink-0 text-muted" x-bind:class="buka && 'rotate-180'"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="buka" x-cloak class="grid grid-cols-5 px-4 pb-4 divide-x divide-hairline dark:divide-gray-700">
                        <div class="px-2 first:pl-0">
                            <div class="text-sm text-muted dark:text-gray-400">Dokter</div>
                            <div class="font-semibold t-num text-ink dark:text-gray-100">{{ $ringkasan['dokter'] }}</div>
                            <div class="text-sm t-num text-muted dark:text-gray-400 whitespace-nowrap">
                                {{ $ringkasan['baru'] }} baru &middot; {{ $ringkasan['hilang'] }} tanpa transaksi
                            </div>
                        </div>
                        <div class="px-2">
                            <div class="text-sm text-muted dark:text-gray-400">Transaksi</div>
                            <div class="font-semibold t-num text-ink dark:text-gray-100">{{ number_format($ringkasan['transaksi'], 0, ',', '.') }}</div>
                        </div>
                        <div class="px-2">
                            <div class="text-sm text-muted dark:text-gray-400">Nominal</div>
                            <div class="font-semibold t-num text-ink dark:text-gray-100">{{ number_format($ringkasan['nominal'], 0, ',', '.') }}</div>
                        </div>
                        <div class="px-2">
                            <div class="text-sm text-muted dark:text-gray-400 whitespace-nowrap">Bulan lalu</div>
                            <div class="font-semibold t-num text-ink dark:text-gray-100">{{ number_format($ringkasan['nominalLalu'], 0, ',', '.') }}</div>
                        </div>
                        <div class="px-2 last:pr-0">
                            <div class="text-sm text-muted dark:text-gray-400">Selisih</div>
                            <div class="font-semibold t-num {{ $ringkasan['selisih'] < 0 ? 'text-rose-600 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300' }}">
                                {{ $ringkasan['selisih'] >= 0 ? '+' : '' }}{{ number_format($ringkasan['selisih'], 0, ',', '.') }}
                            </div>
                            <div class="text-sm t-num text-muted dark:text-gray-400">
                                {{ $ringkasan['persen'] === null ? '—' : ($ringkasan['persen'] >= 0 ? '+' : '') . number_format($ringkasan['persen'], 1, ',', '.') . '%' }}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- PANDUAN — gaya biru-info standar, default TERTUTUP.
                     Lihat memory project_panduan_panel_blue_info_standard. --}}
                <div x-data="{ buka: false }"
                    class="overflow-hidden border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
                    <button type="button" x-on:click="buka = !buka"
                        class="flex items-center justify-between w-full px-4 py-2.5 text-sm font-semibold text-blue-900 transition-colors hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30">
                        <span class="flex items-center min-w-0 gap-2">
                            <svg class="w-4 h-4 shrink-0 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="truncate">Panduan: apa yang dipantau di sini &amp; hubungannya dengan SIAP</span>
                        </span>
                        <svg class="w-4 h-4 ml-2 text-blue-600 transition-transform shrink-0" x-bind:class="buka && 'rotate-180'"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="buka" x-cloak class="px-4 pb-4 space-y-4 text-sm text-blue-900 dark:text-blue-100">
                        <div>
                            <div class="font-semibold">Pembagian peran</div>
                            <ul class="mt-1 ml-5 space-y-1 list-disc">
                                <li><span class="font-semibold">SIRUS</span> mencatat transaksi pelayanan dan menghitung
                                    jasa dokternya (uang periksa, jasa tindakan, visite, konsul, operasi, radiologi) &mdash;
                                    itulah baris di halaman ini.</li>
                                <li><span class="font-semibold">SIAP</span> menarik baris yang sama lewat API tiap bulan
                                    jasa, lalu menghitung slip: gaji pokok, tunjangan, potongan RS, PPh 21, dan potongan
                                    lain. Struktur gaji tiap dokter disetel di SIAP.</li>
                                <li>Halaman ini <span class="font-semibold">tidak menulis apa pun</span>. Koreksi jasa
                                    dilakukan di transaksi asalnya (kasir / administrasi RJ, UGD, RI, OK); SIAP menarik
                                    ulang dan membuat versi baru.</li>
                            </ul>
                        </div>

                        <div>
                            <div class="font-semibold">Membaca pergerakan</div>
                            <ul class="mt-1 ml-5 space-y-1 list-disc">
                                <li>Setiap baris dibandingkan dengan <span class="font-semibold">bulan jasa sebelumnya</span>.
                                    Dokter berlabel <span class="font-semibold">baru</span> belum ada bulan lalu; berlabel
                                    <span class="font-semibold">tanpa transaksi</span> ada bulan lalu tetapi nihil bulan ini
                                    &mdash; keduanya patut dicek sebelum SIAP menarik data.</li>
                                <li>Kolom jalur = nominal per unit layanan. Pasien transfer antar unit ikut jalur tujuannya;
                                    radiologi dipisah karena jasanya dibaca dokter radiologi, bukan dokter pengirim.</li>
                                <li><span class="font-semibold">Rincian</span> membuka baris per pasien (tanggal layanan,
                                    no. RM, klaim, SEP, nominal) &mdash; dipakai saat SIAP atau dokter mempertanyakan angka.</li>
                            </ul>
                        </div>

                        <div>
                            <div class="font-semibold">Catatan periode</div>
                            <ul class="mt-1 ml-5 space-y-1 list-disc">
                                <li>Bulan jasa rawat inap ditentukan saat pasien <span class="font-semibold">pulang</span>,
                                    sehingga baris visite/konsul dapat bertanggal bulan sebelumnya. Itu benar, bukan salah periode.</li>
                                <li>Angka berubah selama bulan berjalan masih dikoreksi kasir. SIAP menyimpan snapshot
                                    berversi, jadi perbedaan antara tarikan SIAP dan layar ini berarti ada transaksi yang
                                    berubah sesudah ditarik &mdash; cukup tarik ulang di SIAP.</li>
                                <li>Bulan yang dipilih selalu dibaca langsung. Angka <span class="font-semibold">bulan
                                    pembanding</span> disegarkan paling lama tiap 6 jam supaya halaman tidak menyapu
                                    riwayat transaksi dua kali setiap dibuka.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TABEL --}}
            <div class="flex flex-col flex-1 min-h-0 mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="w-full min-w-full text-base -mt-3 border-separate border-spacing-y-3 table-fixed">
                        <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                            {{-- Enam jalur disusun sebagai kolom kecil (text-sm) di kiri Total:
                                 yang dibaca pertama adalah Total & pergerakannya, jalur hanya
                                 menjawab "naik/turunnya di mana". .t-num wajib untuk kolom uang. --}}
                            <tr class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                <th class="px-3 py-3 w-[3%] text-right">No</th>
                                <th class="px-4 py-3 w-[17%]">Dokter</th>
                                @foreach (MonitoringJasaDokter::JALUR as $jalur)
                                    <th class="px-2 py-3 w-[8%] text-right">{{ $jalur['label'] }}</th>
                                @endforeach
                                <th class="px-4 py-3 w-[10%] text-right">Total</th>
                                <th class="px-4 py-3 w-[12%] text-right">Pergerakan</th>
                                <th class="px-3 py-3 w-[10%] text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="jasa-{{ $row['dr_id'] }}"
                                    class="transition rounded-2xl shadow-sm ring-1 ring-hairline bg-canvas hover:shadow-lg hover:bg-surface-soft dark:ring-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800 border-l-4
                                    {{ $row['status'] === 'hilang' ? 'border-rose-400' : ($row['status'] === 'baru' ? 'border-sky-400' : 'border-transparent') }}">
                                    <td class="px-3 py-3 text-sm text-right align-top t-num text-muted dark:text-gray-400 rounded-l-2xl">{{ $loop->iteration }}</td>
                                    <td class="px-4 py-3 align-top">
                                        <div class="font-semibold truncate text-ink dark:text-gray-100" title="{{ $row['dr_name'] }}">{{ $row['dr_name'] }}</div>
                                        <div class="text-sm t-num text-muted dark:text-gray-400">
                                            {{ number_format($row['transaksi'], 0, ',', '.') }} transaksi
                                            @if ($row['status'] === 'baru')
                                                <span class="ml-1 px-1.5 py-0.5 text-xs font-medium rounded bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200">baru</span>
                                            @elseif ($row['status'] === 'hilang')
                                                <span class="ml-1 px-1.5 py-0.5 text-xs font-medium rounded bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200">tanpa transaksi</span>
                                            @endif
                                        </div>
                                    </td>
                                    @foreach (MonitoringJasaDokter::JALUR as $kunci => $jalur)
                                        <td class="px-2 py-3 text-sm text-right align-top t-num {{ $row['jalur'][$kunci] == 0.0 ? 'text-muted/60 dark:text-gray-600' : 'text-body dark:text-gray-200' }}">
                                            {{ $row['jalur'][$kunci] == 0.0 ? '—' : number_format($row['jalur'][$kunci], 0, ',', '.') }}
                                        </td>
                                    @endforeach
                                    <td class="px-4 py-3 text-right align-top">
                                        <div class="font-semibold t-num text-ink dark:text-gray-100">{{ number_format($row['nominal'], 0, ',', '.') }}</div>
                                        @if ($row['jalur']['lain'] != 0.0)
                                            <div class="text-sm t-num text-muted dark:text-gray-400" title="Grup komponen di luar enam jalur">lain {{ number_format($row['jalur']['lain'], 0, ',', '.') }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right align-top">
                                        @php $naik = $row['selisih'] >= 0; @endphp
                                        <div class="font-semibold t-num {{ $row['selisih'] == 0.0 ? 'text-muted dark:text-gray-400' : ($naik ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300') }}">
                                            {{ $naik ? '+' : '' }}{{ number_format($row['selisih'], 0, ',', '.') }}
                                        </div>
                                        <div class="text-sm t-num text-muted dark:text-gray-400">
                                            bulan lalu {{ number_format($row['nominal_lalu'], 0, ',', '.') }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-center align-top rounded-r-2xl">
                                        @if ($row['transaksi'] > 0)
                                            <x-outline-button type="button" class="whitespace-nowrap"
                                                wire:click="lihatRincian('{{ $row['dr_id'] }}')"
                                                wire:loading.attr="disabled" wire:target="lihatRincian('{{ $row['dr_id'] }}')"
                                                title="Baris pasien {{ $row['dr_name'] }} — {{ $this->periodeJasaLabel }}">
                                                <span wire:loading.remove wire:target="lihatRincian('{{ $row['dr_id'] }}')" class="inline-flex items-center gap-2">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                    Rincian
                                                </span>
                                                <span wire:loading wire:target="lihatRincian('{{ $row['dr_id'] }}')"><x-loading /> Membuka</span>
                                            </x-outline-button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 5 + count(MonitoringJasaDokter::JALUR) }}" class="px-6 py-16 text-center text-muted dark:text-gray-400">
                                        Tidak ada transaksi jasa dokter pada {{ $this->periodeJasaLabel }}
                                        @if (trim($cariDokter) !== '') untuk pencarian &ldquo;{{ $cariDokter }}&rdquo; @endif.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <livewire:pages::manajemen.rs.tu.monitoring-jasa-dokter.monitoring-jasa-dokter-lampiran wire:key="monitoring-jasa-dokter-lampiran" />
</div>
