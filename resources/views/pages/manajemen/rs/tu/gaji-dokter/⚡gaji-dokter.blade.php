<?php
// resources/views/pages/manajemen/rs/tu/gaji-dokter/gaji-dokter.blade.php
//
// Slip gaji dokter per periode — RSTXN_GAJIDOCTORHDRS / RSTXN_GAJIDOCTORDTLS.
// Seluruh perhitungan ada di App\Support\GajiDokter\GajiDokter; komponen ini hanya
// menyajikan dan memanggil aksinya.
//
// PERIODE: yang dipilih adalah BULAN JASA. Gaji dibayarkan bulan berikutnya —
// jasa Juli 2026 dibayar Agustus 2026. Database menjaga hubungan itu lewat
// CHECK ck_gajidoctorhdrs_periode.

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Support\GajiDokter\GajiDokter;
use Carbon\Carbon;

new class extends Component {
    #[Session(key: 'gajiDokter.tahunJasa')]
    public string $tahunJasa = '';

    #[Session(key: 'gajiDokter.bulanJasa')]
    public string $bulanJasa = '';

    #[Session(key: 'gajiDokter.filterStatus')]
    public string $filterStatus = '';

    public string $cariDokter = '';

    public function mount(): void
    {
        // Guard: #[Session] mengembalikan nilai lama antar kunjungan, tapi pada
        // kunjungan pertama isinya kosong — baru di situ diisi default.
        if ($this->tahunJasa === '' || $this->bulanJasa === '') {
            $bulanLalu = Carbon::now()->subMonth();
            $this->tahunJasa = $bulanLalu->format('Y');
            $this->bulanJasa = $bulanLalu->format('m');
        }
    }

    #[Computed]
    public function periodeGajiLabel(): string
    {
        [$tahun, $bulan] = GajiDokter::periodeGaji($this->tahunJasa, $this->bulanJasa);

        return Carbon::createFromDate((int) $tahun, (int) $bulan, 1)->translatedFormat('F Y');
    }

    #[Computed]
    public function periodeJasaLabel(): string
    {
        return Carbon::createFromDate((int) $this->tahunJasa, (int) $this->bulanJasa, 1)->translatedFormat('F Y');
    }

    /**
     * Saringan bersama untuk daftar, Final Semua, dan Cetak Semua — satu tempat
     * supaya ketiganya mustahil mengenai kumpulan baris yang berbeda.
     */
    protected function slipQuery()
    {
        return DB::table('rstxn_gajidoctorhdrs as h')
            ->join('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->where('h.tahun_jasa', $this->tahunJasa)
            ->where('h.bulan_jasa', $this->bulanJasa)
            ->when($this->filterStatus !== '', fn ($query) => $query->where('h.gaji_status', $this->filterStatus))
            ->when(trim($this->cariDokter) !== '', fn ($query) => $query->whereRaw('UPPER(d.dr_name) LIKE ?', ['%' . strtoupper(trim($this->cariDokter)) . '%']));
    }

    #[Computed]
    public function rows()
    {
        return $this->slipQuery()
            ->select([
                'h.gajidoctor_no', 'h.dr_id', 'd.dr_name',
                'h.skema_gaji_pokok', 'h.potongan_rs_basis', 'h.potongan_rs_persen',
                'h.jasa_total', 'h.nilai_gaji_pokok', 'h.total_gaji',
                'h.potongan_rs', 'h.pph21', 'h.potongan_lain_total', 'h.tambahan_total',
                'h.gaji_diterima', 'h.gaji_status', 'h.tanggal_bayar', 'h.npwp_status',
            ])
            ->orderByDesc('h.gaji_diterima')
            ->get();
    }

    #[Computed]
    public function ringkasan(): array
    {
        $rows = $this->rows;

        return [
            'slip' => $rows->count(),
            'draft' => $rows->where('gaji_status', GajiDokter::STATUS_DRAFT)->count(),
            'final' => $rows->where('gaji_status', GajiDokter::STATUS_FINAL)->count(),
            'jasa' => (float) $rows->sum('jasa_total'),
            'total' => (float) $rows->sum('total_gaji'),
            'potonganRs' => (float) $rows->sum('potongan_rs'),
            'pph21' => (float) $rows->sum('pph21'),
            'diterima' => (float) $rows->sum('gaji_diterima'),
            // Slip yang PPh-nya sudah terlanjur dinaikkan 20%. Dibaca dari SNAPSHOT
            // di header, bukan dari master — yang perlu dilihat adalah apa yang
            // dipakai slip ini, bukan status NPWP dokter hari ini.
            'tanpaNpwp' => $rows->where('npwp_status', 'N')->count(),
        ];
    }

    /**
     * Dipanggil <x-toolbar-refresh-reset>. Filter periode & status disimpan
     * lewat #[Session], jadi reset harus menuliskan nilai awalnya kembali —
     * $this->reset() saja tidak cukup karena sesi akan mengisi ulang.
     */
    public function resetFilters(): void
    {
        $bulanLalu = Carbon::now()->subMonth();
        $this->tahunJasa = $bulanLalu->format('Y');
        $this->bulanJasa = $bulanLalu->format('m');
        $this->filterStatus = '';
        $this->cariDokter = '';

        unset($this->rows, $this->ringkasan);
    }

    /* ===============================
     | AKSI
     =============================== */
    public function generate(): void
    {
        $hasil = GajiDokter::generate($this->tahunJasa, $this->bulanJasa, null, Auth::user()->myuser_name ?? 'system');

        $pesan = "Slip dibuat {$hasil['dibuat']}, diperbarui {$hasil['diperbarui']}.";

        if ($hasil['dilewati']) {
            $pesan .= ' ' . count($hasil['dilewati']) . ' slip final dilewati.';
        }

        $this->dispatch('toast', type: 'success', message: $pesan);

        // Dokter per kapita yang tidak punya sumber hitung diberi peringatan
        // terpisah — slipnya tetap dibuat tapi nilai kapitanya nol, jadi harus
        // diisi manual sebelum difinalkan.
        $tanpaSumber = array_unique($hasil['tanpaSumberKapita']);
        if ($tanpaSumber) {
            $this->dispatch('toast', type: 'warning',
                message: 'Jumlah pasien per kapita tidak ditemukan untuk: ' . implode(', ', $tanpaSumber) . '. Isi manual di rincian slip.');
        }

        // Dokter tanpa NPWP kena PPh 21 +20%. Kalau NPWP-nya sekadar belum diisi di
        // master, tambahan itu salah — jadi jumlahnya selalu dilaporkan, tidak hanya
        // sesekali, supaya tidak lolos diam-diam.
        $tanpaNpwp = array_unique($hasil['tanpaNpwp']);
        if ($tanpaNpwp) {
            $contoh = array_slice($tanpaNpwp, 0, 3);
            $sisa = count($tanpaNpwp) - count($contoh);

            $this->dispatch('toast', type: 'warning',
                message: count($tanpaNpwp) . ' dokter tanpa NPWP kena PPh 21 +20%: '
                    . implode(', ', $contoh) . ($sisa > 0 ? ', dan ' . $sisa . ' lainnya' : '')
                    . '. Pastikan NPWP sudah diisi di Master Dokter → Struktur Gaji.');
        }

        unset($this->rows, $this->ringkasan);
    }

    public function finalkan(int $gajidoctorNo): void
    {
        $header = DB::table('rstxn_gajidoctorhdrs')->where('gajidoctor_no', $gajidoctorNo)->first();
        if (!$header) {
            $this->dispatch('toast', type: 'error', message: 'Slip tidak ditemukan.');
            return;
        }

        if ($header->gaji_status === GajiDokter::STATUS_FINAL) {
            $this->dispatch('toast', type: 'warning', message: 'Slip sudah final.');
            return;
        }

        DB::table('rstxn_gajidoctorhdrs')->where('gajidoctor_no', $gajidoctorNo)->update([
            'gaji_status' => GajiDokter::STATUS_FINAL,
            'update_user' => Auth::user()->myuser_name ?? 'system',
            'update_date' => now(),
        ]);

        $this->dispatch('toast', type: 'success', message: 'Slip dikunci sebagai final.');
        unset($this->rows, $this->ringkasan);
    }

    public function bukaKunci(int $gajidoctorNo): void
    {
        DB::table('rstxn_gajidoctorhdrs')->where('gajidoctor_no', $gajidoctorNo)->update([
            'gaji_status' => GajiDokter::STATUS_DRAFT,
            'update_user' => Auth::user()->myuser_name ?? 'system',
            'update_date' => now(),
        ]);

        $this->dispatch('toast', type: 'success', message: 'Slip dibuka kembali menjadi draft.');
        unset($this->rows, $this->ringkasan);
    }

    public function hapus(int $gajidoctorNo): void
    {
        $header = DB::table('rstxn_gajidoctorhdrs')->where('gajidoctor_no', $gajidoctorNo)->first();

        if ($header && $header->gaji_status === GajiDokter::STATUS_FINAL) {
            $this->dispatch('toast', type: 'error', message: 'Slip final tidak bisa dihapus. Buka kunci dulu.');
            return;
        }

        // Detail ikut terhapus lewat ON DELETE CASCADE pada FK-nya.
        DB::table('rstxn_gajidoctorhdrs')->where('gajidoctor_no', $gajidoctorNo)->delete();

        $this->dispatch('toast', type: 'success', message: 'Slip draft dihapus.');
        unset($this->rows, $this->ringkasan);
    }

    /**
     * Kunci SEMUA slip draft pada periode & filter yang sedang tampil.
     *
     * Sengaja mengikuti filter, bukan seluruh periode: yang dikunci harus persis
     * yang terlihat di layar. Kalau tidak, mencari satu dokter lalu menekan
     * "Final Semua" akan diam-diam mengunci 35 slip lain yang tidak terlihat.
     */
    public function finalkanSemua(): void
    {
        // Nomornya dikumpulkan dulu, baru di-update terpisah. Oracle MENOLAK
        // UPDATE ber-JOIN (ORA-00971: missing SET keyword) — dan slipQuery()
        // selalu mengandung join ke RSMST_DOCTORS untuk saringan nama dokter.
        $nomorSlipList = $this->slipQuery()
            ->where('h.gaji_status', GajiDokter::STATUS_DRAFT)
            ->pluck('h.gajidoctor_no');

        if ($nomorSlipList->isEmpty()) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada slip draft pada tampilan ini.');
            return;
        }

        $jumlah = DB::table('rstxn_gajidoctorhdrs')
            ->whereIn('gajidoctor_no', $nomorSlipList)
            ->update([
                'gaji_status' => GajiDokter::STATUS_FINAL,
                'update_user' => Auth::user()->myuser_name ?? 'system',
                'update_date' => now(),
            ]);

        $this->dispatch('toast', type: 'success', message: "{$jumlah} slip dikunci sebagai final.");
        unset($this->rows, $this->ringkasan);
    }

    /**
     * Cetak seluruh slip pada tampilan ini jadi SATU berkas, satu dokter satu
     * halaman. Detail ditarik sekali lalu dikelompokkan di memori — bukan
     * di-query per slip — supaya tidak jadi N+1 saat mencetak puluhan dokter.
     */
    public function cetakSemua(): mixed
    {
        $headerList = $this->slipQuery()
            ->orderBy('d.dr_name')
            ->select('h.*', 'd.dr_name')
            ->get();

        if ($headerList->isEmpty()) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada slip untuk dicetak.');
            return null;
        }

        $detailPerSlip = DB::table('rstxn_gajidoctordtls')
            ->whereIn('gajidoctor_no', $headerList->pluck('gajidoctor_no'))
            ->orderBy('jenis')->orderBy('urutan')->orderBy('gajidoctor_dtl')
            ->get()
            ->groupBy('gajidoctor_no');

        $identitasRs = DB::table('rsmst_identitases')
            ->select('int_name', 'int_address', 'int_city', 'int_phone1')
            ->first();

        $kota = trim((string) ($identitasRs->int_city ?? ''));

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pages.components.manajemen.cetak-slip-gaji.cetak-slip-gaji-massal-print', [
            'slipList' => $headerList->map(fn ($header) => [
                'header' => $header,
                'detail' => $detailPerSlip[$header->gajidoctor_no] ?? collect(),
            ]),
            'judulPeriode' => $this->periodeGajiLabel,
            'identitasRs' => $identitasRs,
            // locale('id') WAJIB — APP_LOCALE repo ini 'en'.
            'tanggalCetak' => trim($kota . ($kota !== '' ? ', ' : '') . now()->locale('id')->translatedFormat('d F Y')),
        ])->setPaper('A4');

        $namaBerkas = 'slip-gaji-' . $this->tahunJasa . $this->bulanJasa . '-semua.pdf';

        return response()->streamDownload(fn () => print $pdf->output(), $namaBerkas);
    }

    /**
     * Buka lampiran rincian pasien SELURUH slip pada tampilan ini di layar.
     *
     * Dulu ini mengunduh PDF. Diganti 2026-08-02 karena dompdf harus
     * mencocokkan build Tailwind ke tiap elemen: satu dokter 123 baris saja
     * butuh 14 detik, dan berkas massal jauh lebih berat lagi. Yang dikirim
     * ke modal cuma daftar nomor slip; datanya ditarik komponen lampiran.
     */
    public function lihatLampiranSemua(): void
    {
        $gajidoctorNoList = $this->slipQuery()->pluck('h.gajidoctor_no');

        if ($gajidoctorNoList->isEmpty()) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada slip pada tampilan ini.');
            return;
        }

        $this->dispatch('gaji.dokter.openLampiranSemua', gajidoctorNoList: $gajidoctorNoList->values()->all());
    }

    /** Cetak slip tanpa membuka rincian — payload sama dengan tombol di modal. */
    public function cetak(int $gajidoctorNo): mixed
    {
        $header = DB::table('rstxn_gajidoctorhdrs as h')
            ->join('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->where('h.gajidoctor_no', $gajidoctorNo)
            ->select('h.*', 'd.dr_name')
            ->first();

        if (!$header) {
            $this->dispatch('toast', type: 'error', message: 'Slip tidak ditemukan.');
            return null;
        }

        $identitasRs = DB::table('rsmst_identitases')
            ->select('int_name', 'int_address', 'int_city', 'int_phone1')
            ->first();

        $kota = trim((string) ($identitasRs->int_city ?? ''));

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pages.components.manajemen.cetak-slip-gaji.cetak-slip-gaji-print', [
            'header' => $header,
            'detail' => DB::table('rstxn_gajidoctordtls')
                ->where('gajidoctor_no', $gajidoctorNo)
                ->orderBy('jenis')->orderBy('urutan')->orderBy('gajidoctor_dtl')
                ->get(),
            'identitasRs' => $identitasRs,
            // locale('id') WAJIB — APP_LOCALE repo ini 'en'.
            'tanggalCetak' => trim($kota . ($kota !== '' ? ', ' : '') . now()->locale('id')->translatedFormat('d F Y')),
        ])->setPaper('A4');   // sama dengan cetak kwitansi RJ — dompdf mengabaikan
                              // width/height pada @page milik layout-kwitansi, jadi
                              // kertasnya tetap ditentukan di sini.

        $namaBerkas = 'slip-gaji-' . $header->dr_id . '-' . $header->tahun_gaji . $header->bulan_gaji . '.pdf';

        return response()->streamDownload(fn () => print $pdf->output(), $namaBerkas);
    }

    /**
     * Buka lampiran rincian pasien satu dokter di layar.
     *
     * Ada di baris daftar karena inilah yang paling sering diminta menyusul
     * slipnya: pertanyaan "angka ini dari pasien mana" muncul saat memandang
     * barisnya, bukan setelah membuka rinciannya.
     */
    public function lihatLampiran(int $gajidoctorNo): void
    {
        $this->dispatch('gaji.dokter.openLampiran', gajidoctorNo: $gajidoctorNo);
    }

    public function lihatRincian(int $gajidoctorNo): void
    {
        $this->dispatch('gaji.dokter.openRincian', gajidoctorNo: $gajidoctorNo);
    }

    #[On('gaji.dokter.saved')]
    public function segarkan(): void
    {
        unset($this->rows, $this->ringkasan);
    }
};
?>

<div>
    <x-page-title
        title="Slip Gaji Dokter"
        subtitle="Proses, koreksi, dan kunci slip gaji dokter per periode" />

    {{-- Kerangka halaman meniru pelayanan-rj: tinggi layar dikunci lalu dibagi
         flex-kolom, sehingga yang menggulung adalah isi tabel — toolbar & kepala
         kolom tetap di tempat. Latar surface-soft, kartu tabel yang canvas. --}}
    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-0 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 pt-1 pb-2 bg-surface-soft border-b border-hairline top-16 dark:bg-gray-900 dark:border-gray-700">
                {{-- Lebar saringan sengaja dipangkas dari ukuran semula (bulan 40,
                     tahun 24, cari 56) supaya seluruh tombol muat sebaris dengannya.
                     Sejak tombol Lampiran Semua ikut di baris ini, ukuran lama
                     mendorong kelompok tombol turun ke baris kedua. --}}
                <div class="flex flex-wrap items-end gap-3">

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Bulan Jasa" />
                        <x-select-input wire:model.live="bulanJasa" class="mt-1 block w-full sm:w-32">
                            @foreach (['01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'] as $kode => $nama)
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
                        <x-input-label value="Status" />
                        <x-select-input wire:model.live="filterStatus" class="mt-1 block w-full sm:w-36">
                            <option value="">Semua</option>
                            <option value="D">Draft</option>
                            <option value="F">Final</option>
                        </x-select-input>
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Cari Dokter" />
                        <x-text-input type="text" wire:model.live.debounce.500ms="cariDokter"
                            class="mt-1 block w-full sm:w-40" placeholder="nama dokter" />
                    </div>

                    {{-- Generate, Refresh/Reset, dan Kembali disatukan di baris toolbar
                         ini — sebelumnya Kembali berdiri sendiri di baris atas dan
                         memakan satu baris penuh hanya untuk satu tombol. --}}
                    <div class="ml-auto flex flex-wrap items-end gap-2">
                        {{-- Label "Proses Slip", bukan "Generate Slip": campuran Inggris-
                             Indonesia, dan kata "generate" menyiratkan hanya membuat baru
                             padahal aksi ini juga MENULIS ULANG slip draft yang sudah ada.
                             Nama method tetap generate() supaya wire:target tidak berubah. --}}
                        <x-primary-button type="button" wire:click="generate" wire:loading.attr="disabled"
                            wire:target="generate">
                            <span wire:loading.remove wire:target="generate" class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6h7.5v2.25h-7.5V6zM8.25 12h.008v.008H8.25V12zm0 3h.008v.008H8.25V15zm0 3h.008v.008H8.25V18zm3.75-6h.008v.008H12V12zm0 3h.008v.008H12V15zm0 3h.008v.008H12V18zm3.75-6h.008v.008h-.008V12zm0 3h.008v.008h-.008V15zM6.75 2.25h10.5a2.25 2.25 0 012.25 2.25v15a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5v-15A2.25 2.25 0 016.75 2.25z" /></svg>
                            Proses Slip
                            </span>
                            <span wire:loading wire:target="generate"><x-loading /> Memproses...</span>
                        </x-primary-button>

                        {{-- Aksi massal — berlaku untuk baris yang SEDANG TAMPIL, bukan
                             seluruh periode. Konfirmasinya menyebut jumlahnya supaya
                             tidak ada yang terkunci/tercetak di luar dugaan. --}}
                        <x-primary-button type="button" wire:click="finalkanSemua"
                            wire:loading.attr="disabled" wire:target="finalkanSemua"
                            wire:confirm="Kunci {{ $this->ringkasan['draft'] }} slip draft pada tampilan ini sebagai final?"
                            :disabled="$this->ringkasan['draft'] === 0">
                            <span wire:loading.remove wire:target="finalkanSemua" class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                            Final Semua
                        </span>
                            <span wire:loading wire:target="finalkanSemua"><x-loading /> Mengunci...</span>
                        </x-primary-button>

                        <x-info-button type="button" class="gap-2" wire:click="cetakSemua"
                            wire:loading.attr="disabled" wire:target="cetakSemua"
                            :disabled="$this->ringkasan['slip'] === 0"
                            title="Cetak semua slip pada tampilan ini — satu dokter satu halaman">
                            <span wire:loading.remove wire:target="cetakSemua" class="inline-flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5z" />
                                </svg>
                                Cetak Semua
                            </span>
                            <span wire:loading wire:target="cetakSemua" class="inline-flex items-center gap-2">
                                <x-loading /> Menyiapkan...
                            </span>
                        </x-info-button>

                        {{-- Lampiran = outline, bukan info: tampilan pendamping yang
                             berdiri sendiri, bukan dokumen utama periode ini. Dibuka di
                             layar sebagai tabel, tidak diunduh sebagai PDF. --}}
                        <x-outline-button type="button" class="gap-2" wire:click="lihatLampiranSemua"
                            wire:loading.attr="disabled" wire:target="lihatLampiranSemua"
                            :disabled="$this->ringkasan['slip'] === 0"
                            title="Lihat lampiran rincian pasien semua slip pada tampilan ini — tanggal layanan, no. RM, nama, nominal">
                            <span wire:loading.remove wire:target="lihatLampiranSemua" class="inline-flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                                </svg>
                                Lampiran Semua
                            </span>
                            <span wire:loading wire:target="lihatLampiranSemua" class="inline-flex items-center gap-2">
                                <x-loading /> Menyiapkan...
                            </span>
                        </x-outline-button>

                        {{-- Tombol baku toolbar list: Refresh (muat ulang tanpa mengubah
                             filter) + Reset (kembalikan filter ke awal). --}}
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
                    Jasa <span class="font-semibold">{{ $this->periodeJasaLabel }}</span> dibayarkan pada gaji
                    <span class="font-semibold">{{ $this->periodeGajiLabel }}</span>. Memproses akan menulis ulang slip
                    berstatus draft; slip final dilewati.
                </p>
            </div>

            {{-- Ringkasan & Panduan sebaris: keduanya panel yang bisa ditutup, jadi
                             saat ditutup dua-duanya cuma satu bilah tipis. items-start supaya
                             yang terbuka tidak menarik tinggi pasangannya. --}}
            <div class="grid grid-cols-1 gap-3 mt-4 lg:grid-cols-2 items-start">
                {{-- RINGKASAN — bisa dibuka/tutup, sebaris dengan Panduan. Sengaja BUKAN
                     bergaya biru-info: biru dicadangkan untuk panel panduan/instruksional.
                     Default TERTUTUP menyamai Panduan; angka pentingnya tetap terbaca di
                     bilah judul walau tertutup. --}}
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
                                ({{ $ringkasan['slip'] }} slip &middot; diterima {{ number_format($ringkasan['diterima'], 0, ',', '.') }})
                            </span>
                        </span>
                        <svg class="w-4 h-4 ml-2 transition-transform shrink-0 text-muted" x-bind:class="buka && 'rotate-180'"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
            
                    {{-- Lima angka dalam SATU baris: kotak kartu dilepas, tinggal
                         label kecil di atas nilai, dipisah garis tipis. Bentuk kartu
                         memakan lebar yang tidak dipunyai panel setengah lebar ini. --}}
                    <div x-show="buka" x-cloak
                        class="grid grid-cols-5 px-4 pb-4 divide-x divide-hairline dark:divide-gray-700">

                        <div class="px-2 first:pl-0 last:pr-0">
                            <div class="text-sm text-muted dark:text-gray-400">Slip</div>
                            <div class="font-semibold t-num text-ink dark:text-gray-100">{{ $ringkasan['slip'] }}</div>
                            <div class="text-sm t-num text-muted dark:text-gray-400 whitespace-nowrap">
                                {{ $ringkasan['draft'] }} draft &middot; {{ $ringkasan['final'] }} final
                            </div>
                        </div>

                        <div class="px-2">
                            <div class="text-sm text-muted dark:text-gray-400">Jasa</div>
                            <div class="font-semibold t-num text-ink dark:text-gray-100">
                                {{ number_format($ringkasan['jasa'], 0, ',', '.') }}
                            </div>
                        </div>

                        <div class="px-2">
                            <div class="text-sm text-muted dark:text-gray-400">Total Gaji</div>
                            <div class="font-semibold t-num text-ink dark:text-gray-100">
                                {{ number_format($ringkasan['total'], 0, ',', '.') }}
                            </div>
                        </div>

                        <div class="px-2">
                            <div class="text-sm text-muted dark:text-gray-400 whitespace-nowrap">Pot. RS + PPh</div>
                            <div class="font-semibold t-num text-rose-600 dark:text-rose-300">
                                {{ number_format($ringkasan['potonganRs'] + $ringkasan['pph21'], 0, ',', '.') }}
                            </div>
                        </div>

                        <div class="px-2 last:pr-0">
                            <div class="text-sm text-muted dark:text-gray-400">Diterima</div>
                            <div class="font-semibold t-num text-emerald-700 dark:text-emerald-300">
                                {{ number_format($ringkasan['diterima'], 0, ',', '.') }}
                            </div>
                        </div>

                    </div>

                    {{-- Peringatan NPWP — di luar grid 5 kolom supaya tidak merusak
                         susunannya, dan hanya muncul kalau memang ada slipnya.
                         Toast saat generate hilang sendiri; penanda ini menetap. --}}
                    @if ($ringkasan['tanpaNpwp'] > 0)
                        <div class="flex items-start gap-2 px-4 pb-4 -mt-1">
                            <svg class="w-4 h-4 mt-0.5 shrink-0 text-amber-600 dark:text-amber-400" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.5 0l-7.1 12.25A2 2 0 004.98 19z" />
                            </svg>
                            <p class="text-sm text-amber-800 dark:text-amber-300">
                                <span class="font-semibold t-num">{{ $ringkasan['tanpaNpwp'] }}</span>
                                slip dihitung <span class="font-semibold">tanpa NPWP</span> &mdash; PPh 21-nya
                                dinaikkan 20%. Kalau itu hanya karena NPWP belum diisi, lengkapi di
                                Master Dokter &rarr; Struktur Gaji lalu proses ulang slipnya.
                            </p>
                        </div>
                    @endif
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
                            <span class="truncate">Panduan: cara gaji &amp; PPh 21 dihitung</span>
                        </span>
                        <svg class="w-4 h-4 ml-2 text-blue-600 transition-transform shrink-0" x-bind:class="buka && 'rotate-180'"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="buka" x-cloak class="px-4 pb-4 space-y-4 text-sm text-blue-900 dark:text-blue-100">

                        {{-- 1. ISTILAH --}}
                        <div>
                            <div class="font-semibold">Istilah</div>
                            <div class="mt-1 overflow-x-auto">
                                <table class="w-full text-sm text-left">
                                    <tbody class="align-top">
                                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">PPh 21</td>
                                            <td class="py-0.5">Pajak Penghasilan Pasal 21 &mdash; pajak atas penghasilan
                                                sehubungan dengan pekerjaan/jasa, dipotong oleh pemberi penghasilan.</td></tr>
                                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">DPP</td>
                                            <td class="py-0.5">Dasar Pengenaan Pajak &mdash; nilai yang dikenai tarif pajak.</td></tr>
                                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">NPPN</td>
                                            <td class="py-0.5">
                                                <span class="font-semibold">Norma Penghitungan Penghasilan Neto</span> &mdash;
                                                persentase baku yang menaksir berapa bagian dari penghasilan kotor yang
                                                dianggap penghasilan bersih, tanpa perlu membuktikan biaya satu per satu.
                                                Untuk dokter dipakai angka 50%: separuh dianggap biaya menjalankan praktik
                                                (alat, bahan habis pakai, tenaga), separuh sisanya baru dianggap penghasilan
                                                bersih dan itulah yang dipajaki. Karena itu tarif 5% atas separuh bruto
                                                berujung 2,5% dari bruto.
                                                <span class="block mt-0.5 italic">
                                                    Catatan: istilah &ldquo;NPPN&rdquo; dipakai mengikuti berkas Excel lama.
                                                    Secara aturan, angka 50% pada PPh 21 bukan pegawai adalah faktor DPP
                                                    yang ditetapkan PMK 168/2023. NPPN yang sesungguhnya (Pasal 14 UU PPh)
                                                    mekanisme terpisah, dipakai saat menghitung pajak tahunan wajib pajak
                                                    yang tidak menyelenggarakan pembukuan. Idenya sama &mdash;
                                                    &ldquo;sekian persen bruto dianggap penghasilan bersih&rdquo; &mdash;
                                                    tapi dasar hukum &amp; pemakaiannya beda.
                                                </span>
                                            </td></tr>
                                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">Bruto</td>
                                            <td class="py-0.5">Penghasilan kotor dokter, yaitu total gaji setelah dikurangi
                                                potongan rumah sakit.</td></tr>
                                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">UU PPh</td>
                                            <td class="py-0.5">Undang-Undang Pajak Penghasilan.</td></tr>
                                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">PMK</td>
                                            <td class="py-0.5">Peraturan Menteri Keuangan.</td></tr>
                                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">NPWP</td>
                                            <td class="py-0.5">Nomor Pokok Wajib Pajak.</td></tr>
                                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">UP / JD</td>
                                            <td class="py-0.5">Uang Periksa / Jasa Dokter &mdash; dua jenis komponen jasa.</td></tr>
                                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">RJ / RI / UGD / OK</td>
                                            <td class="py-0.5">Rawat Jalan / Rawat Inap / Unit Gawat Darurat / Kamar Operasi.
                                                Akhiran <span class="font-mono">TRF</span> = pasien transfer.</td></tr>
                                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">Skema A / G / N</td>
                                            <td class="py-0.5">Aditif (jasa + gaji pokok) / Garanty fee (yang terbesar antara
                                                jasa dan gaji pokok) / tidak ada gaji pokok.</td></tr>
                                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">Basis T / J / N / B</td>
                                            <td class="py-0.5">Potongan RS dihitung dari Total gaji / Jasa saja /
                                                tidak dipotong / Berjenjang per komponen.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- 2. URUTAN HITUNG --}}
                        <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                            <div class="font-semibold">Urutan hitung</div>
                            <ol class="mt-1 ml-4 space-y-0.5 list-decimal">
                                <li><span class="font-semibold">Jasa</span> = jumlah seluruh komponen dari transaksi bulan
                                    jasa (UP RJ, JD RI, VISIT, KONSUL, OPERATOR, RAD, dan seterusnya).</li>
                                <li><span class="font-semibold">Total Gaji</span> = jasa + tunjangan rutin + gaji pokok,
                                    sesuai skemanya.</li>
                                <li><span class="font-semibold">Potongan RS</span> (bagian rumah sakit) = persen &times;
                                    basisnya.</li>
                                <li><span class="font-semibold">PPh 21</span> &mdash; lihat bagian berikutnya.</li>
                                <li><span class="font-semibold">Gaji Diterima</span> = Total Gaji &minus; Potongan RS
                                    &minus; PPh 21 &minus; potongan rutin + tambahan.</li>
                            </ol>
                        </div>

                        {{-- 3. PPh 21 --}}
                        <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                            <div class="font-semibold">PPh 21 &mdash; dasar hukum &amp; rumus</div>
                            <p class="mt-1">
                                Dokter praktik di rumah sakit tergolong <span class="font-semibold">bukan pegawai</span>
                                (tenaga ahli). Menurut <span class="font-semibold">PMK 168/2023</span>, pajaknya:
                            </p>
                            <div class="p-2 mt-1 font-mono text-sm bg-blue-100 rounded dark:bg-blue-900/40">
                                PPh 21 = tarif Pasal 17 &times; DPP<br>
                                DPP&nbsp;&nbsp;&nbsp;&nbsp;= 50% &times; bruto&nbsp;&nbsp;(norma penghasilan neto)<br>
                                bruto&nbsp;&nbsp;= Total Gaji &minus; Potongan RS
                            </div>
                            <p class="mt-1">
                                Dihitung <span class="font-semibold">per bulan</span>, tidak diakumulasi dengan bulan
                                sebelumnya. Yang jadi bruto adalah bagian yang benar-benar diterima dokter, yaitu setelah
                                potongan rumah sakit.
                            </p>
                            <p class="mt-1">
                                <span class="font-semibold">Hasilnya dibulatkan ke bawah</span> ke rupiah penuh
                                (mis. 286.762,5 &rarr; 286.762). Komponen lain tidak dibulatkan.
                            </p>
                        </div>

                        {{-- 4. TARIF PASAL 17 --}}
                        <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                            <div class="font-semibold">Tarif Pasal 17 ayat (1) huruf a UU PPh</div>
                            <p class="mt-1">
                                Tarif berlaku atas <span class="font-semibold">DPP</span> (separuh bruto). Kolom terakhir
                                adalah nilai yang diisikan ke field <span class="font-semibold">PPh 21 (%)</span> di Master
                                Dokter, karena field itu dikalikan ke bruto &mdash; bukan ke DPP.
                            </p>
                            <div class="mt-2 overflow-x-auto">
                                <table class="w-full text-sm text-left">
                                    <thead class="border-b border-blue-200 dark:border-blue-800">
                                        <tr>
                                            <th class="py-1 pr-3 font-semibold">Lapisan DPP</th>
                                            <th class="py-1 pr-3 font-semibold whitespace-nowrap">Setara bruto / bulan</th>
                                            <th class="py-1 pr-3 font-semibold">Tarif</th>
                                            <th class="py-1 font-semibold whitespace-nowrap">Isi kolom PPh 21 (%)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="t-num">
                                        <tr class="font-semibold">
                                            <td class="py-1 pr-3 whitespace-nowrap">s/d 60 juta</td>
                                            <td class="py-1 pr-3 whitespace-nowrap">s/d 120 juta</td>
                                            <td class="py-1 pr-3">5%</td>
                                            <td class="py-1">2,5 &nbsp;&larr; dipakai semua dokter saat ini</td>
                                        </tr>
                                        <tr>
                                            <td class="py-1 pr-3 whitespace-nowrap">&gt; 60 &ndash; 250 juta</td>
                                            <td class="py-1 pr-3 whitespace-nowrap">&gt; 120 &ndash; 500 juta</td>
                                            <td class="py-1 pr-3">15%</td>
                                            <td class="py-1">7,5</td>
                                        </tr>
                                        <tr>
                                            <td class="py-1 pr-3 whitespace-nowrap">&gt; 250 &ndash; 500 juta</td>
                                            <td class="py-1 pr-3 whitespace-nowrap">&gt; 500 juta &ndash; 1 miliar</td>
                                            <td class="py-1 pr-3">25%</td>
                                            <td class="py-1">12,5</td>
                                        </tr>
                                        <tr>
                                            <td class="py-1 pr-3 whitespace-nowrap">&gt; 500 juta &ndash; 5 miliar</td>
                                            <td class="py-1 pr-3 whitespace-nowrap">&gt; 1 &ndash; 10 miliar</td>
                                            <td class="py-1 pr-3">30%</td>
                                            <td class="py-1">15</td>
                                        </tr>
                                        <tr>
                                            <td class="py-1 pr-3 whitespace-nowrap">&gt; 5 miliar</td>
                                            <td class="py-1 pr-3 whitespace-nowrap">&gt; 10 miliar</td>
                                            <td class="py-1 pr-3">35%</td>
                                            <td class="py-1">17,5</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="mt-2">
                                <span class="font-semibold">Perhatikan:</span> tarif Pasal 17 bersifat berlapis
                                (progresif) &mdash; hanya bagian penghasilan yang masuk suatu lapisan yang dikenai tarif
                                lapisan itu. Selama bruto sebulan masih di bawah 120 juta, seluruhnya jatuh di lapisan
                                pertama sehingga satu angka 2,5 sudah tepat. Begitu melewatinya, satu persen tunggal tidak
                                lagi bisa mewakili &mdash; hitung berlapis, lalu ketik hasilnya langsung pada baris
                                <span class="font-mono">PPh 21</span> di Rincian slip.
                            </p>
                            <p class="mt-1">
                                Dokter <span class="font-semibold">tanpa NPWP</span> dikenai tarif 20% lebih tinggi.
                                Master Dokter belum menyimpan NPWP, jadi hal ini belum bisa diterapkan otomatis.
                            </p>
                        </div>

                        {{-- 5. CATATAN PEMAKAIAN --}}
                        <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                            <div class="font-semibold">Yang perlu diingat</div>
                            <ul class="mt-1 ml-4 space-y-0.5 list-disc">
                                <li>Parameter (skema, basis, persen, gaji pokok) <span class="font-semibold">disalin</span>
                                    ke slip saat diproses &mdash; mengubah Master Dokter tidak menggeser slip lama.</li>
                                <li>Baris Potongan RS &amp; PPh 21 terisi dari rumus, tapi boleh disesuaikan di Rincian.
                                    Setelah diubah, rumus berhenti menimpanya.</li>
                                <li>Memproses ulang akan <span class="font-semibold">menulis ulang</span> slip
                                    draft &mdash; koreksi manual yang belum difinalkan akan hilang. Urutannya:
                                    proses &rarr; koreksi &rarr; final.</li>
                                <li>Slip gaji <span class="font-semibold">bukan</span> bukti potong pajak. Bukti potong
                                    PPh 21 diterbitkan terpisah.</li>
                            </ul>
                        </div>

                    </div>
                </div>
            </div>


            {{-- TABEL --}}
            {{-- Tabel bergaya Daftar RJ: baris = kartu ber-ring dengan jarak antar
                 baris, header sticky, penanda warna kiri per status. --}}
            <div class="flex flex-col flex-1 min-h-0 mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                <table class="w-full min-w-full text-base -mt-3 border-separate border-spacing-y-3 table-fixed">
                    <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                        {{-- 11 kolom dirapatkan jadi 5. Yang digabung adalah angka yang
                             memang satu cerita: Jasa + Gaji Pokok itu penyusun Total Gaji,
                             dan Potongan RS + PPh + Lain itu penyusun total potongan. Angka
                             induknya ditampilkan besar, penyusunnya jadi baris kecil di
                             bawahnya — informasinya utuh, matanya tidak perlu melompat
                             sebelas kolom. Skema & status ikut ke kolom Dokter karena
                             keduanya sifat slip, bukan nominal. --}}
                        {{-- Skala huruf memakai token design system di resources/css/app.css:
                             Skala tipografi tabel: text-base (16px) mengikuti pelayanan-rj,
                             angka utama base + semibold, Diterima text-xl, baris rincian &
                             nomor urut text-sm. text-sm (14px) adalah AMBANG TERKECIL di
                             halaman ini — di layar sepadat ini, 12-13px sulit dipindai.
                             .t-num = tabular-nums, wajib untuk kolom uang supaya digit
                             antar-baris lurus. --}}
                        <tr class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                            <th class="px-3 py-3 w-[4%] text-right">No</th>
                            <th class="px-6 py-3 w-[20%]">Dokter</th>
                            <th class="px-6 py-3 w-[15%] text-right">Total Gaji</th>
                            <th class="px-6 py-3 w-[17%] text-right">Potongan</th>
                            <th class="px-6 py-3 w-[14%] text-right">Diterima</th>
                            <th class="px-6 py-3 w-[30%] text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rows as $row)
                            <tr wire:key="slip-{{ $row->gajidoctor_no }}"
                                class="transition rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700
                                {{ $row->gaji_status === 'F'
                                    ? 'bg-success/10 dark:bg-gray-800 hover:shadow-lg hover:bg-success/20 dark:hover:bg-gray-700 border-l-4 border-success'
                                    : 'bg-canvas dark:bg-gray-900 hover:shadow-lg hover:bg-surface-soft dark:hover:bg-gray-800 border-l-4 border-warning' }}">
                                @php
                                    $potonganTotal = (float) $row->potongan_rs + (float) $row->pph21 + (float) $row->potongan_lain_total;
                                    $rupiah = fn ($nilai) => number_format((float) $nilai, 0, ',', '.');
                                @endphp

                                {{-- Nomor urut TAMPILAN — mengikuti urutan baris yang sedang
                                     tersaring & terurut, bukan nomor slip di database (yang itu
                                     tampil sebagai "Slip #" di modal Rincian). --}}
                                <td class="px-3 py-3 text-right align-middle text-sm t-num text-muted dark:text-gray-400">
                                    {{ $loop->iteration }}
                                </td>

                                {{-- DOKTER + sifat slip --}}
                                <td class="px-6 py-3 align-middle">
                                    <div class="font-semibold truncate text-ink dark:text-gray-100">{{ $row->dr_name }}</div>
                                    <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                        <x-badge :variant="$row->gaji_status === 'F' ? 'success' : 'warning'">
                                            {{ $row->gaji_status === 'F' ? 'Final' : 'Draft' }}
                                        </x-badge>
                                        <span class="text-sm text-muted dark:text-gray-400">
                                            {{ $row->dr_id }} &middot;
                                            <span class="font-mono">{{ $row->skema_gaji_pokok }}/{{ $row->potongan_rs_basis }}</span>
                                            @if (in_array($row->potongan_rs_basis, ['T', 'J'], true))
                                                {{ rtrim(rtrim(number_format((float) $row->potongan_rs_persen, 2, ',', '.'), '0'), ',') }}%
                                            @endif
                                        </span>
                                    </div>
                                </td>

                                {{-- TOTAL GAJI = jasa + gaji pokok --}}
                                <td class="px-6 py-3 text-right align-middle">
                                    <div class="font-semibold t-num text-ink dark:text-gray-100">
                                        {{ $rupiah($row->total_gaji) }}
                                    </div>
                                    <div class="text-sm t-num text-muted dark:text-gray-400">
                                        jasa {{ $rupiah($row->jasa_total) }}
                                        @if ((float) $row->nilai_gaji_pokok > 0)
                                            + pokok {{ $rupiah($row->nilai_gaji_pokok) }}
                                        @endif
                                    </div>
                                </td>

                                {{-- POTONGAN = RS + PPh + lain-lain --}}
                                <td class="px-6 py-3 text-right align-middle">
                                    <div class="font-semibold t-num text-rose-600 dark:text-rose-300">
                                        {{ $rupiah($potonganTotal) }}
                                    </div>
                                    <div class="text-sm t-num text-muted dark:text-gray-400">
                                        RS {{ $rupiah(abs((float) $row->potongan_rs)) }}
                                        &middot; PPh {{ $rupiah(abs((float) $row->pph21)) }}
                                        @if ((float) $row->potongan_lain_total != 0)
                                            &middot; lain {{ $rupiah(abs((float) $row->potongan_lain_total)) }}
                                        @endif
                                    </div>
                                </td>

                                {{-- DITERIMA --}}
                                <td class="px-6 py-3 text-right align-middle">
                                    <div class="text-xl font-bold t-num text-emerald-700 dark:text-emerald-300">
                                        {{ $rupiah($row->gaji_diterima) }}
                                    </div>
                                    @if ((float) $row->tambahan_total > 0)
                                        <div class="text-sm t-num text-muted dark:text-gray-400">
                                            termasuk tambahan {{ $rupiah($row->tambahan_total) }}
                                        </div>
                                    @endif
                                </td>

                                <td class="px-6 py-3 align-middle">
                                    {{-- flex-nowrap: aksi wajib satu baris. Kalau membungkus,
                                         tinggi baris jadi tidak seragam dan tabel terlihat patah. --}}
                                    {{-- Warna mengikuti docs/standar-komponen-tombol.md:
                                         Rincian  -> outline  (aksi alternatif / buka panel)
                                         Cetak    -> icon-button biru (biru = cetak/info)
                                         Final    -> primary  (aksi utama baris ini)
                                         BukaKunci-> warning  (perlu perhatian, tidak destruktif)
                                         Hapus    -> icon-button merah (hapus item di dalam tabel)

                                         Tiap tombol memakai wire:target ber-argumen supaya yang
                                         berputar hanya barisnya sendiri — tanpa itu satu klik
                                         membuat 36 baris ikut berputar.

                                         Tinggi & lebar TIDAK ditimpa: dibiarkan memakai ukuran
                                         bawaan komponen (px-5 py-2.5 untuk tombol berteks, p-2
                                         untuk icon-button) supaya seragam dengan tombol di
                                         seluruh aplikasi. --}}
                                    <div class="flex flex-nowrap items-center justify-between gap-2">
                                        <div class="flex flex-nowrap items-center gap-1.5">
                                        <x-outline-button type="button"
                                            class="whitespace-nowrap shrink-0"
                                            wire:click="lihatRincian({{ $row->gajidoctor_no }})"
                                            wire:loading.attr="disabled"
                                            wire:target="lihatRincian({{ $row->gajidoctor_no }})">
                                            <span wire:loading.remove wire:target="lihatRincian({{ $row->gajidoctor_no }})" class="inline-flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                                Rincian
                                            </span>
                                            <span wire:loading wire:target="lihatRincian({{ $row->gajidoctor_no }})">
                                                <x-loading /> Membuka
                                            </span>
                                        </x-outline-button>

                                        @if ($row->gaji_status === 'F')
                                            <x-warning-button type="button"
                                                class="whitespace-nowrap shrink-0"
                                                wire:click="bukaKunci({{ $row->gajidoctor_no }})"
                                                wire:loading.attr="disabled"
                                                wire:target="bukaKunci({{ $row->gajidoctor_no }})"
                                                wire:confirm="Buka kunci slip {{ $row->dr_name }}? Slip akan bisa disunting & di-generate ulang.">
                                                <span wire:loading.remove wire:target="bukaKunci({{ $row->gajidoctor_no }})" class="inline-flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" /></svg>
                                                Buka Kunci
                                            </span>
                                                <span wire:loading wire:target="bukaKunci({{ $row->gajidoctor_no }})">
                                                    <x-loading /> Membuka
                                                </span>
                                            </x-warning-button>
                                        @else
                                            <x-primary-button type="button"
                                                class="whitespace-nowrap shrink-0"
                                                wire:click="finalkan({{ $row->gajidoctor_no }})"
                                                wire:loading.attr="disabled"
                                                wire:target="finalkan({{ $row->gajidoctor_no }})"
                                                wire:confirm="Kunci slip {{ $row->dr_name }} sebagai final?">
                                                <span wire:loading.remove wire:target="finalkan({{ $row->gajidoctor_no }})" class="inline-flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                                Final
                                            </span>
                                                <span wire:loading wire:target="finalkan({{ $row->gajidoctor_no }})">
                                                    <x-loading /> Mengunci
                                                </span>
                                            </x-primary-button>
                                        @endif

                                        {{-- p-2.5 + ikon w-5 h-5: menyamakan tinggi dengan tombol berteks di
                                             sebelahnya (py-2.5 + tinggi baris teks 20px). Bawaan
                                             icon-button p-2 + ikon 16px membuatnya ~8px lebih pendek. --}}
                                        <x-icon-button color="blue" type="button"
                                            class="!p-2.5 shrink-0"
                                            wire:click="cetak({{ $row->gajidoctor_no }})"
                                            wire:loading.attr="disabled"
                                            wire:target="cetak({{ $row->gajidoctor_no }})"
                                            title="Cetak slip {{ $row->dr_name }}">
                                            <span wire:loading.remove wire:target="cetak({{ $row->gajidoctor_no }})">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                    stroke-width="1.8">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5z" />
                                                </svg>
                                            </span>
                                            <span wire:loading wire:target="cetak({{ $row->gajidoctor_no }})">
                                                <x-loading size="md" />
                                            </span>
                                        </x-icon-button>

                                        {{-- Lampiran: abu-abu, bukan biru. Biru dipakai Cetak slip di
                                             sebelahnya, dan yang ini bukan cetakan — ia membuka
                                             tabel di layar. Ikonnya garis-garis daftar,
                                             menandakan isinya rincian baris. --}}
                                        <x-icon-button color="gray" type="button"
                                            class="!p-2.5 shrink-0"
                                            wire:click="lihatLampiran({{ $row->gajidoctor_no }})"
                                            wire:loading.attr="disabled"
                                            wire:target="lihatLampiran({{ $row->gajidoctor_no }})"
                                            title="Lihat lampiran rincian pasien {{ $row->dr_name }}">
                                            <span wire:loading.remove wire:target="lihatLampiran({{ $row->gajidoctor_no }})">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                    stroke-width="1.8">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                                                </svg>
                                            </span>
                                            <span wire:loading wire:target="lihatLampiran({{ $row->gajidoctor_no }})">
                                                <x-loading size="md" />
                                            </span>
                                        </x-icon-button>

                                        </div>

                                        {{-- Hapus paling ujung & hanya untuk draft. Sengaja dipisah dari
                                             percabangan status di atas supaya Cetak bisa berdiri di
                                             antara Final dan Hapus. Wadah luar justify-between: tiga
                                             tombol pertama berkelompok di kiri, Hapus terdorong ke tepi
                                             kanan supaya jaraknya jauh dari tombol yang sering diklik. --}}
                                        @if ($row->gaji_status !== 'F')
                                                <x-icon-button color="red" type="button"
                                                    class="!p-2.5 shrink-0"
                                                    wire:click="hapus({{ $row->gajidoctor_no }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="hapus({{ $row->gajidoctor_no }})"
                                                    wire:confirm="Hapus slip draft {{ $row->dr_name }}?"
                                                    title="Hapus slip draft">
                                                    <span wire:loading.remove wire:target="hapus({{ $row->gajidoctor_no }})">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                            stroke-width="1.8">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </span>
                                                    <span wire:loading wire:target="hapus({{ $row->gajidoctor_no }})">
                                                        <x-loading size="md" />
                                                    </span>
                                                </x-icon-button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-16 text-center text-muted dark:text-gray-400">
                                    Belum ada slip untuk periode ini. Klik <span class="font-semibold">Proses Slip</span>
                                    untuk menyusunnya dari data jasa {{ $this->periodeJasaLabel }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>

        </div>
    </div>

    <livewire:pages::manajemen.rs.tu.gaji-dokter.gaji-dokter-rincian wire:key="gaji-dokter-rincian" />

    {{-- Modal lampiran berdiri SEJAJAR dengan modal rincian, bukan di dalamnya:
         tombol Lampiran ada di dua tempat (baris daftar & footer modal rincian),
         dan modal bersarang membuat yang kedua tidak pernah tampil benar. --}}
    <livewire:pages::manajemen.rs.tu.gaji-dokter.gaji-dokter-lampiran wire:key="gaji-dokter-lampiran" />
</div>
