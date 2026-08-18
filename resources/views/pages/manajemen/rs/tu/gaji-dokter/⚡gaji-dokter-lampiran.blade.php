<?php
// resources/views/pages/manajemen/rs/tu/gaji-dokter/gaji-dokter-lampiran.blade.php
//
// Lampiran rincian pasien — TAMPIL DI LAYAR, bukan diunduh sebagai PDF.
//
// Semula lampiran ini dicetak lewat dompdf. Dibatalkan 2026-08-02: dompdf harus
// mencocokkan seluruh build Tailwind (~188 KB) ke tiap elemen, dan lampiran
// berisi ratusan baris — satu dokter dengan 123 baris butuh 14 detik, dan
// sebelum batas waktunya dinaikkan malah berujung FatalError 60 detik. Tabel di
// layar tidak punya biaya itu, dan kalau tetap butuh kertas, Ctrl+P milik
// peramban mencetak apa yang terlihat.
//
// Versi cetak PDF-nya masih ada di riwayat git pada commit 7d2b9298 bila kelak
// dibutuhkan lagi.
//
// Mesin datanya tetap App\Support\GajiDokter\GajiDokterLampiran — yang berubah hanya cara
// menyajikannya.

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Support\GajiDokter\GajiDokterLampiran;

new class extends Component {
    /**
     * Nomor slip yang sedang ditampilkan. Selalu larik walau isinya satu —
     * tampilan satu dokter dan banyak dokter memakai jalur yang sama, jadi
     * tidak ada dua cabang yang bisa berbeda hasil.
     */
    public array $gajidoctorNoList = [];

    /** Judul modal ikut berubah supaya jelas sedang melihat satu atau semua. */
    public bool $modeSemua = false;

    #[On('gaji.dokter.openLampiran')]
    public function openLampiran(int $gajidoctorNo): void
    {
        $this->siapkan([$gajidoctorNo], false);
    }

    #[On('gaji.dokter.openLampiranSemua')]
    public function openLampiranSemua(array $gajidoctorNoList): void
    {
        $this->siapkan($gajidoctorNoList, true);
    }

    protected function siapkan(array $gajidoctorNoList, bool $modeSemua): void
    {
        $this->gajidoctorNoList = array_values(array_filter(array_map('intval', $gajidoctorNoList)));
        $this->modeSemua = $modeSemua;

        // Computed di-cache satu request; tanpa dibuang, membuka dokter kedua
        // akan menampilkan data dokter pertama.
        unset($this->dataLampiran);

        if ($this->dataLampiran->isEmpty()) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada transaksi pasien pada periode ini.');
            return;
        }

        $this->dispatch('open-modal', name: 'gaji-dokter-lampiran');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'gaji-dokter-lampiran');
    }

    /**
     * Unduh isi lampiran yang sedang tampil sebagai CSV yang siap dibuka Excel.
     *
     * CSV, bukan .xlsx: proyek ini belum memuat PhpSpreadsheet sama sekali, dan
     * data di sini tabel datar tanpa rumus/format — menambah dependensi hanya
     * untuk itu tidak sepadan. Barisnya ditulis langsung ke keluaran, jadi mode
     * Semua (5.000+ baris) tidak menumpuk sebagai satu untai raksasa di memori.
     *
     * BENTUKNYA SENGAJA PIPIH, TIDAK MENIRU PENGELOMPOKAN DI LAYAR.
     * Di layar lampiran dikelompokkan per komponen berikut subtotalnya; kalau
     * itu disalin ke CSV, baris judul & subtotal menyelip di tengah data dan
     * merusak pivot — padahal mem-pivot itulah alasan orang membukanya di Excel.
     * Kolom Dokter & Periode ikut di SETIAP baris supaya ekspor banyak dokter
     * langsung bisa dipivot tanpa dirapikan dulu.
     */
    public function unduhCsv(): mixed
    {
        $data = $this->dataLampiran;

        if ($data->isEmpty()) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada data untuk diunduh.');
            return null;
        }

        $bulanNama = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
            '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
            '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
        ];

        $pertama = $data->first()['header'];

        $namaBerkas = $this->modeSemua
            ? 'lampiran-pasien-' . $pertama->tahun_gaji . $pertama->bulan_gaji . '-semua.csv'
            : 'lampiran-pasien-' . $pertama->dr_id . '-' . $pertama->tahun_gaji . $pertama->bulan_gaji . '.csv';

        return response()->streamDownload(function () use ($data, $bulanNama) {
            $keluaran = fopen('php://output', 'w');

            // BOM UTF-8 — tanpa ini Excel membaca berkas sebagai ANSI dan nama
            // pasien beraksen jadi rusak.
            fwrite($keluaran, "\xEF\xBB\xBF");

            // JANGAN menambahkan baris "sep=;" di sini. Sempat dipasang supaya
            // berkasnya juga benar di Excel berlokal Inggris, dan hasilnya
            // justru barisnya TERCETAK sebagai data di baris pertama: Excel
            // tidak mengenali petunjuk itu bila didahului BOM, sementara BOM-nya
            // wajib demi nama pasien. Tidak perlu juga — Excel berlokal
            // Indonesia sudah memakai ';' sebagai pemisah daftar, jadi berkas
            // ini terbaca benar apa adanya.
            fputcsv($keluaran, self::KOLOM_CSV, ';');

            foreach ($data as $satuDokter) {
                $header = $satuDokter['header'];
                $grupKapita = $satuDokter['grupKapita'];
                $periodeJasa = ($bulanNama[$header->bulan_jasa] ?? $header->bulan_jasa) . ' ' . $header->tahun_jasa;

                foreach ($satuDokter['lampiran'] as $baris) {
                    // Baris komponen per kapita nominalnya tarif komponen yang
                    // TIDAK dibayarkan. Tanpa penanda ini, siapa pun yang mem-blok
                    // kolom Nominal lalu menekan AutoSum dapat angka yang tidak
                    // cocok dengan slip — dan menyalahkan datanya.
                    $masukSlip = in_array($baris['group_doc'], $grupKapita, true) ? 'Tidak' : 'Ya';

                    fputcsv($keluaran, [
                        $header->dr_name,
                        $periodeJasa,
                        $baris['label'],
                        $baris['tgl'],
                        $baris['reg_no'],
                        $baris['nama'],
                        // Terisi hanya untuk komponen radiologi (nama pemeriksaan);
                        // komponen lain tidak punya padanan sedetail ini.
                        $baris['keterangan'],
                        $baris['klaim'],
                        // Jenis klaim (BPJS/UMUM/KRONIS/DOKEL) dipisah dari namanya
                        // supaya bisa jadi kolom pivot; nama klaim terlalu banyak
                        // ragamnya untuk itu.
                        $baris['klaim_status'],
                        // Kosong itu WAJAR, bukan data hilang: pasien umum memang
                        // tidak punya SEP, dan RSTXN_RJHDRKS (klinik) bahkan tidak
                        // menyimpan kolomnya.
                        $baris['no_sep'],
                        $baris['txn_no'],
                        // Angka MENTAH, bukan 450.000 — nominal berformat titik
                        // ribuan dibaca Excel sebagai teks dan SUM-nya nol.
                        (int) round((float) $baris['nominal']),
                        $masukSlip,
                    ], ';');
                }
            }

            fclose($keluaran);
        }, $namaBerkas, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Judul kolom CSV. Dipisah supaya urutannya bisa dibaca sekali pandang
     * bersebelahan dengan urutan nilai di unduhCsv().
     */
    public const KOLOM_CSV = [
        'Dokter', 'Periode Jasa', 'Komponen', 'Tanggal',
        'No. RM', 'Nama Pasien', 'Pemeriksaan', 'Klaim', 'Jenis Klaim', 'No. SEP',
        'No. Transaksi', 'Nominal', 'Masuk Slip',
    ];

    /**
     * Satu entri per dokter: header slip, detail slip, baris lampiran, grup kapita,
     * dan angka rekonsiliasinya.
     *
     * Transaksi ditarik lewat barisMassal() — sekali untuk semua dokter, bukan
     * sekali per dokter. Lihat catatan di GajiDokterLampiran.
     */
    #[Computed]
    public function dataLampiran()
    {
        if ($this->gajidoctorNoList === []) {
            return collect();
        }

        $headerList = DB::table('rstxn_gajidoctorhdrs as h')
            ->join('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->whereIn('h.gajidoctor_no', $this->gajidoctorNoList)
            ->orderBy('d.dr_name')
            ->select('h.*', 'd.dr_name')
            ->get();

        if ($headerList->isEmpty()) {
            return collect();
        }

        // Periode diambil dari header, bukan dari saringan layar: slip yang
        // dibuka bisa saja bukan periode yang sedang tampil di daftar.
        $pertama = $headerList->first();

        $lampiranPerDokter = GajiDokterLampiran::barisMassal(
            $headerList->pluck('dr_id')->unique()->values()->all(),
            $pertama->tahun_jasa,
            $pertama->bulan_jasa,
        );

        $grupKapitaPerDokter = GajiDokterLampiran::grupKapitaMassal(
            $headerList->pluck('dr_id')->unique()->values()->all()
        );

        $detailPerSlip = DB::table('rstxn_gajidoctordtls')
            ->whereIn('gajidoctor_no', $headerList->pluck('gajidoctor_no'))
            ->orderBy('jenis')->orderBy('urutan')->orderBy('gajidoctor_dtl')
            ->get()
            ->groupBy('gajidoctor_no');

        return $headerList
            ->filter(fn ($header) => ($lampiranPerDokter[$header->dr_id] ?? collect())->isNotEmpty())
            ->map(function ($header) use ($lampiranPerDokter, $grupKapitaPerDokter, $detailPerSlip) {
                $lampiran = $lampiranPerDokter[$header->dr_id];
                $detail = $detailPerSlip[$header->gajidoctor_no] ?? collect();
                $grupKapita = $grupKapitaPerDokter[$header->dr_id] ?? [];

                return [
                    'header' => $header,
                    'lampiran' => $lampiran,
                    'grupKapita' => $grupKapita,
                    'perKomponen' => $lampiran->groupBy('desc_doc'),
                    'angka' => GajiDokterLampiran::rekonsiliasi($lampiran, $detail, $grupKapita),
                ];
            })
            ->values();
    }
};
?>

<div>
    <x-modal name="gaji-dokter-lampiran" size="full" height="full" focusable>
        <div class="flex flex-col h-full">

            {{-- HEADER MODAL --}}
            <div class="px-6 py-3 bg-surface-soft dark:bg-gray-900">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold tracking-wider uppercase text-muted dark:text-gray-400">
                            Lampiran Rincian Pasien
                        </h2>
                        <p class="mt-0.5 text-xs text-muted dark:text-gray-400">
                            @if ($this->modeSemua)
                                {{ $this->dataLampiran->count() }} dokter
                            @else
                                {{ $this->dataLampiran->first()['header']->dr_name ?? '' }}
                            @endif
                        </p>
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 pt-2 pb-4 overflow-y-auto bg-surface-soft dark:bg-gray-950/20">
                @include('pages.components.manajemen.gaji-dokter-lampiran.isi-lampiran-layar', [
                    'dataLampiran' => $this->dataLampiran,
                ])
            </div>

            {{-- FOOTER --}}
            <div class="px-6 py-3 border-t border-hairline bg-surface-soft dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-end gap-2">
                    <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>

                    {{-- Unduh mengekspor APA YANG SEDANG TAMPIL — satu dokter atau
                         semua, mengikuti cara modal ini dibuka. Tidak ada tombol
                         unduh terpisah di daftar supaya isi berkas tidak pernah
                         bisa berbeda dari yang barusan dilihat. --}}
                    <x-info-button type="button" class="gap-2" wire:click="unduhCsv"
                        wire:loading.attr="disabled" wire:target="unduhCsv"
                        title="Unduh sebagai CSV — siap dibuka di Excel">
                        <span wire:loading.remove wire:target="unduhCsv" class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            Unduh Excel
                        </span>
                        <span wire:loading wire:target="unduhCsv" class="inline-flex items-center gap-2">
                            <x-loading /> Menyiapkan...
                        </span>
                    </x-info-button>
                </div>
            </div>

        </div>
    </x-modal>
</div>
