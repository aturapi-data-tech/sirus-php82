<?php

use App\Support\Downtime\TarifDowntime;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Livewire\Component;
use Livewire\WithPagination;

// Daftar tarif (price list) untuk keperluan waktu henti (down time) SIMRS.
// Sumber angka: App\Support\Downtime\TarifDowntime — master yang sama dengan LOV
// Administrasi RJ/UGD/RI, supaya nominal tulisan tangan saat gangguan sama persis
// dengan yang muncul ketika data dientri ulang setelah sistem pulih.
// Halaman ini hanya membaca & mencetak — tidak pernah menulis ke master.
new class extends Component {
    use WithPagination;

    #[Session(key: 'downtime-tarif-tab')]
    public string $tab = 'kamar';

    public string $searchKeyword = '';
    public int $itemsPerPage = 25;

    public function mount(): void
    {
        // Tab tersimpan di session bisa memakai kunci kategori lama setelah daftar
        // kategori berubah — kembalikan ke kategori pertama supaya tidak kosong.
        if (!TarifDowntime::adaKategori($this->tab)) {
            $this->tab = array_key_first(TarifDowntime::KATEGORI);
        }
    }

    public function gantiTab(string $kategori): void
    {
        if (!TarifDowntime::adaKategori($kategori)) {
            return;
        }

        $this->tab = $kategori;
        $this->searchKeyword = '';
        $this->resetPage();
    }

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    /** Baris halaman aktif — hasil paginate master sesuai kategori & kata kunci. */
    #[Computed]
    public function rows()
    {
        return TarifDowntime::query($this->tab, $this->searchKeyword)->paginate($this->itemsPerPage);
    }

    /** Baris siap tampil (kunci sesuai definisi kolom kategori). */
    #[Computed]
    public function baris(): array
    {
        return TarifDowntime::baris($this->tab, $this->rows->items());
    }

    #[Computed]
    public function kolom(): array
    {
        return TarifDowntime::kolom($this->tab);
    }

    /** Susunan header tabel (bertingkat untuk kategori yang punya kolom per kelas). */
    #[Computed]
    public function header(): array
    {
        return TarifDowntime::headerKolom($this->tab);
    }

    /** Seluruh kategori — sumber tab. */
    #[Computed]
    public function daftarKategori(): array
    {
        return TarifDowntime::KATEGORI;
    }

    /** Keterangan kategori aktif: label, unit pengguna & sumber master. */
    #[Computed]
    public function infoKategori(): array
    {
        return TarifDowntime::KATEGORI[$this->tab] ?? [];
    }

    /** Unduh PDF kategori aktif — mengikuti kata kunci pencarian yang sedang dipakai. */
    public function cetakKategori(): mixed
    {
        $paket = TarifDowntime::paketCetak($this->tab, $this->searchKeyword);

        if (empty($paket['baris'])) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada tarif untuk dicetak pada kategori ini.');
            return null;
        }

        return $this->streamPdf([$paket], 'Kategori ' . $paket['label'], 'daftar-tarif-downtime-' . $this->tab . '.pdf');
    }

    /** Unduh PDF seluruh kategori dalam satu bundel (tanpa kata kunci pencarian). */
    public function cetakSemua(): mixed
    {
        $paket = [];

        foreach (array_keys(TarifDowntime::KATEGORI) as $kategori) {
            $paket[] = TarifDowntime::paketCetak($kategori);
        }

        return $this->streamPdf($paket, 'Seluruh Kategori', 'daftar-tarif-downtime-lengkap.pdf');
    }

    /** Stream PDF ke browser. */
    private function streamPdf(array $paket, string $judul, string $namaFile): mixed
    {
        // Bundel seluruh kategori memuat ribuan baris obat — berat di dompdf.
        // Batas dinaikkan hanya untuk request ini, bukan setelan global.
        set_time_limit(600);
        ini_set('memory_limit', '1024M');

        $data = [
            'paket' => $paket,
            'judul' => $judul,
            'dicetakOleh' => auth()->user()?->myuser_name ?: (auth()->user()?->name ?: '-'),
            // Format dd/mm/yyyy hh24:mi:ss — seragam dengan penulisan tanggal-jam
            // transaksi di SIMRS, sekaligus menegaskan tarif ini potret detik cetak.
            'tglCetak' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
        ];

        $pdf = Pdf::loadView('pages.downtime.cetak.cetak-tarif', $data)->setPaper('A4');

        return response()->streamDownload(fn() => print $pdf->output(), $namaFile);
    }
};

?>

<div>
    <x-page-title title="Daftar Tarif Down Time"
        subtitle="Acuan nominal saat SIMRS tidak dapat diakses — kamar, jasa medis, dokter, penunjang, obat & lain-lain" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TAB KATEGORI --}}
            <div class="pt-2">
                <x-tabs class="flex-wrap">
                    @foreach ($this->daftarKategori as $kategoriKunci => $kategori)
                        <x-tab :active="$tab === $kategoriKunci" wire:click="gantiTab('{{ $kategoriKunci }}')">
                            {{ $kategori['label'] }}
                        </x-tab>
                    @endforeach
                </x-tabs>
            </div>

            {{-- DUA PANEL BUKA-TUTUP SEJAJAR: panduan (kiri) & keterangan kategori (kanan).
                 Keduanya default tertutup; di layar sempit otomatis bertumpuk. --}}
            <div class="grid grid-cols-1 gap-3 mt-2 lg:grid-cols-2 items-start">

            {{-- PANDUAN — panel biru standar, default tertutup --}}
            <details
                class="p-3 text-sm border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800/40">
                <summary class="font-semibold text-blue-800 cursor-pointer dark:text-blue-300">
                    Panduan pemakaian daftar tarif down time — klik untuk membaca
                </summary>
                <div class="mt-2 space-y-3 text-blue-900/80 dark:text-blue-200/80">
                    <p>
                        Formulir manual waktu henti tetap meminta nominal biaya. Daftar ini mengambil angka dari
                        master yang sama dengan LOV Administrasi RJ/UGD/RI, jadi nominal yang ditulis tangan saat
                        gangguan akan sama dengan yang muncul ketika data dientri ulang setelah sistem pulih.
                    </p>

                    <ul class="space-y-1.5" style="list-style: disc; padding-left: 18px">
                        <li>
                            <strong>Cetak sebelum terjadi down time.</strong> Simpan hasil cetak di map formulir
                            manual tiap unit. Saat SIMRS mati, halaman ini ikut tidak bisa dibuka.
                        </li>
                        <li>
                            <strong>Cetak ulang berkala.</strong> Tarif berubah mengikuti master; tanggal cetak
                            tertera di kop tiap halaman. Cetak ulang setiap ada perubahan tarif.
                        </li>
                        <li>
                            <strong>Tarif poli vs tarif rawat inap.</strong> Pada Jasa Medis & Jasa Dokter, kolom
                            <em>Tarif Poli</em> dipakai pasien rawat jalan & UGD, sedangkan kolom di bawah judul
                            <em>Tarif Rawat Inap per Kelas Kamar</em> dipakai pasien rawat inap sesuai kelas kamarnya.
                            Angka kelas yang tampil abu-abu berarti kelas itu belum punya tarif sendiri di master
                            sehingga mengikuti tarif poli.
                        </li>
                        <li>
                            <strong>Kolom BPJS.</strong> Diisi bila pasien berstatus klaim BPJS. Bila tarif BPJS
                            kosong, sistem memakai tarif umum — begitu juga saat mengisi manual.
                        </li>
                        <li>
                            <strong>Tanda &ldquo;&mdash;&rdquo;</strong> berarti tarif belum diisi di master.
                            Konfirmasi ke bagian administrasi sebelum menulis nominal.
                        </li>
                    </ul>

                    <p class="text-xs">
                        Daftar formulir manualnya ada di menu <strong>Down Time &rarr; Formulir Manual Down Time</strong>.
                    </p>
                </div>
            </details>

            {{-- KETERANGAN KATEGORI AKTIF — warna hijau membedakannya dari panel panduan. --}}
            <details
                class="text-sm border rounded-2xl bg-success-tint border-success/30 dark:bg-emerald-900/20 dark:border-emerald-800/40">
                <summary class="p-3 font-semibold cursor-pointer text-success-deep dark:text-emerald-300">
                    Keterangan &amp; sumber data &mdash; {{ $this->infoKategori['label'] ?? '-' }}
                </summary>
                <div class="px-3 pb-3 text-success-deep dark:text-emerald-200/80">
                    <p>{{ $this->infoKategori['desc'] ?? '' }}</p>
                    <p class="mt-1 text-caption">
                        Pengguna: {{ $this->infoKategori['unit'] ?? '-' }} &middot;
                        Sumber master: {{ $this->infoKategori['sumber'] ?? '-' }}
                    </p>
                </div>
            </details>

            </div>

            {{-- TOOLBAR --}}
            <div class="flex flex-col gap-3 mt-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="w-full lg:max-w-md">
                    <x-input-label for="searchKeyword" value="Cari tarif" class="sr-only" />
                    <x-text-input id="searchKeyword" type="text" wire:model.live.debounce.300ms="searchKeyword"
                        placeholder="Cari nama atau kode..." class="block w-full" />
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <div class="w-28">
                        <x-input-label for="itemsPerPage" value="Per halaman" class="sr-only" />
                        <x-select-input id="itemsPerPage" wire:model.live="itemsPerPage">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </x-select-input>
                    </div>

                    <x-outline-button type="button" wire:click="cetakKategori" wire:loading.attr="disabled"
                        wire:target="cetakKategori">
                        <span wire:loading.remove wire:target="cetakKategori">Unduh PDF kategori ini</span>
                        <span wire:loading wire:target="cetakKategori" class="inline-flex items-center gap-1.5">
                            <x-loading /> Menyiapkan PDF...
                        </span>
                    </x-outline-button>

                    <x-primary-button type="button" wire:click="cetakSemua" wire:loading.attr="disabled"
                        wire:target="cetakSemua">
                        <span wire:loading.remove wire:target="cetakSemua">Unduh PDF semua kategori</span>
                        <span wire:loading wire:target="cetakSemua" class="inline-flex items-center gap-1.5">
                            <x-loading /> Menyiapkan PDF...
                        </span>
                    </x-primary-button>
                </div>
            </div>

            {{-- TABEL TARIF --}}
            @php
                // Gaya tabel tarif — mengikuti standar UI (/standarisasi-ui):
                // - badan tabel memakai tipografi bawaan .ds-table: font-sans, text-sm
                // - judul kolom memakai .ds-table thead th: text-caption-up (11,5px) uppercase
                // - warna memakai token design system (warning/info tint, surface, hairline),
                //   bukan palet Tailwind mentah; palet hanya dipakai untuk mode gelap
                //   karena token tint hanya tersedia untuk permukaan terang.
                // Yang di-override hanya JARAK (padding) supaya 14 kolom tetap ringkas —
                // ukuran & jenis huruf dibiarkan standar. Tanda "!" perlu karena
                // .ds-table menargetkan "thead th"/"tbody td" yang spesifisitasnya
                // di atas utility satu-kelas.
                // Kelas ditulis literal (bukan dirangkai dari variabel) supaya ikut
                // ter-scan saat "npm run build".
                $selWarna = [
                    'poli' => 'bg-warning-tint dark:bg-amber-900/20',
                    'inap' => 'bg-info-tint dark:bg-sky-900/20',
                    'inap-a' => 'bg-info-tint dark:bg-sky-900/20',
                    'inap-b' => 'bg-surface-soft dark:bg-gray-800/60',
                ];
                // Judul kelompok: teks text-ink supaya kontras di atas tint tetap di atas
                // 4,5:1 (text-muted bawaan hanya 4,37:1 di atas warning-tint).
                $judulWarna = [
                    'poli' => 'bg-warning-tint !text-ink dark:bg-amber-900/30 dark:!text-amber-100',
                    'inap' => 'bg-info-tint !text-ink dark:bg-sky-900/30 dark:!text-sky-100',
                    'inap-a' => 'bg-info-tint !text-ink dark:bg-sky-900/30 dark:!text-sky-100',
                    'inap-b' => 'bg-surface-strong !text-ink dark:bg-gray-800 dark:!text-gray-100',
                ];

                $gayaJudul = '!px-3 !py-2 !text-center';
                $gayaSel = '!px-3 !py-2 align-middle';
                $garisTipis = 'border-l border-hairline-soft dark:border-gray-700';
                $garisKelompok = 'border-l border-hairline dark:border-gray-600';
            @endphp

            @php
                // Tabel bergrup memuat 12 kolom nominal — awalan "Rp" di tiap sel membuat
                // tabel meluber ke samping, jadi satuannya ditulis sekali di atas tabel.
                $ringkas = ($this->header['tingkat'] ?? 1) >= 3;
            @endphp

            @if ($ringkas)
                <p class="mt-2 text-caption text-muted-soft dark:text-gray-400">
                    Seluruh nominal dalam rupiah. Angka abu-abu = kelas belum ditarifkan sendiri, mengikuti tarif poli.
                </p>
            @endif

            <div
                class="mt-2 flex flex-col flex-1 min-h-0 bg-canvas border shadow-sm border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr class="text-left">
                                <th rowspan="{{ $this->header['tingkat'] }}"
                                    class="{{ $gayaJudul }} min-w-[44px]">No</th>
                                @foreach ($this->header['atas'] as $judulKolom)
                                    <th colspan="{{ $judulKolom['colspan'] ?? 1 }}" rowspan="{{ $judulKolom['rowspan'] ?? 1 }}"
                                        class="{{ $gayaJudul }} {{ $judulWarna[$judulKolom['warna'] ?? ''] ?? '' }} {{ ($judulKolom['batas'] ?? false) ? $garisKelompok : $garisTipis }}">
                                        {{ $judulKolom['label'] }}
                                    </th>
                                @endforeach
                            </tr>
                            @if ($this->header['tingkat'] >= 3)
                                <tr class="text-left">
                                    @foreach ($this->header['tengah'] as $judulKolom)
                                        <th colspan="{{ $judulKolom['colspan'] ?? 1 }}"
                                            class="{{ $gayaJudul }} {{ $judulWarna[$judulKolom['warna'] ?? ''] ?? '' }} {{ ($judulKolom['batas'] ?? false) ? $garisKelompok : $garisTipis }}">
                                            {{ $judulKolom['label'] }}
                                        </th>
                                    @endforeach
                                </tr>
                            @endif
                            @if ($this->header['berlapis'])
                                <tr class="text-left">
                                    @foreach ($this->header['bawah'] as $judulKolom)
                                        <th
                                            class="{{ $gayaJudul }} {{ $judulWarna[$judulKolom['warna'] ?? ''] ?? '' }} {{ ($judulKolom['batas'] ?? false) ? $garisKelompok : $garisTipis }}">
                                            {{ $judulKolom['label'] }}</th>
                                    @endforeach
                                </tr>
                            @endif
                        </thead>
                        <tbody>
                            @forelse ($this->baris as $indeks => $baris)
                                <tr wire:key="tarif-{{ $tab }}-{{ $this->rows->currentPage() }}-{{ $indeks }}">
                                    <td class="ds-td-token {{ $gayaSel }} text-center">
                                        {{ ($this->rows->currentPage() - 1) * $this->rows->perPage() + $indeks + 1 }}
                                    </td>

                                    @foreach ($this->kolom as $kolom)
                                        <td
                                            class="{{ $gayaSel }} {{ ($kolom['rata'] ?? 'kiri') === 'kanan' ? 'text-right tabular-nums whitespace-nowrap min-w-[86px]' : 'min-w-[90px]' }} {{ $kolom['key'] === 'nama' ? 'min-w-[200px]' : '' }} {{ $kolom['key'] === 'kode' ? 'ds-td-token' : '' }} {{ $selWarna[$kolom['warna'] ?? ''] ?? '' }} {{ ($kolom['batas'] ?? false) ? $garisKelompok : $garisTipis }}">
                                            @if (($kolom['tipe'] ?? '') === 'tarifKelas')
                                                @php $tarif = $baris[$kolom['key']] ?? []; @endphp
                                                <span
                                                    class="{{ ($tarif['asal'] ?? 'poli') === 'kelas' ? 'font-medium text-ink dark:text-white' : 'text-gray-600 dark:text-gray-400' }}"
                                                    title="{{ ($tarif['asal'] ?? 'poli') === 'kelas' ? 'Tarif khusus kelas ini' : 'Kelas ini belum ditarifkan sendiri — mengikuti tarif poli' }}">
                                                    {{ $ringkas ? App\Support\Downtime\TarifDowntime::angka($tarif['harga'] ?? 0) : App\Support\Downtime\TarifDowntime::rupiah($tarif['harga'] ?? 0) }}
                                                </span>
                                            @elseif ($kolom['uang'] ?? false)
                                                <span
                                                    class="font-medium text-ink dark:text-white">{{ $ringkas ? App\Support\Downtime\TarifDowntime::angka($baris[$kolom['key']] ?? 0) : App\Support\Downtime\TarifDowntime::rupiah($baris[$kolom['key']] ?? 0) }}</span>
                                            @else
                                                {{ $baris[$kolom['key']] ?? '-' }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($this->kolom) + 1 }}" class="px-6 py-10 text-center"
                                        style="color:var(--muted)">
                                        Tidak ada tarif yang cocok dengan pencarian.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- PAGINATION --}}
            <div
                class="sticky bottom-0 z-10 px-4 py-3 border-t bg-canvas border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                {{ $this->rows->links() }}
            </div>

        </div>
    </div>
</div>
