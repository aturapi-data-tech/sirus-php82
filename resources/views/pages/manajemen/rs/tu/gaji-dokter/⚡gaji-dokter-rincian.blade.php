<?php
// resources/views/pages/manajemen/rs/tu/gaji-dokter/gaji-dokter-rincian.blade.php
//
// Rincian satu slip gaji — isi RSTXN_GAJIDOCTORDTLS.
//
// Inilah alasan bentuknya header-detail, bukan 22 kolom: bagian keuangan bisa
// menambah potongan atau tambahan yang tidak ada di master (kasbon bulan ini,
// rapel, koreksi bulan lalu) tanpa ALTER TABLE. Tiap perubahan detail memicu
// hitung ulang ringkasan header lewat GajiDokter::hitungUlang().
//
// Slip FINAL hanya bisa dilihat. Menyuntingnya harus lewat Buka Kunci di daftar.

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Support\GajiDokter;
use Barryvdh\DomPDF\Facade\Pdf;

new class extends Component {
    public ?int $gajidoctorNo = null;
    public ?object $header = null;
    public string $drName = '';

    /**
     * Gaji pokok milik SLIP ini (snapshot header, bukan master). Bisa berbeda
     * antar bulan — mis. dokter baru masuk pertengahan bulan, atau ada
     * kesepakatan sementara — tanpa menyentuh setelan baku di Master Dokter.
     */
    public ?string $nilaiGajiPokok = null;

    /**
     * Persen potongan RS milik SLIP ini (snapshot header, bukan master).
     * Hanya untuk basis 'T' dan 'J'. Basis 'B' (berjenjang per komponen)
     * diatur di Master Dokter — di slip cukup ditimpa lewat nilai baris RS.
     */
    public ?string $potonganRsPersen = null;

    /**
     * Kotak ketik baris baru, SATU SET PER JENIS. Dulu satu form bersama dengan
     * dropdown "Jenis"; sekarang formnya menempel di bloknya masing-masing
     * sehingga jenisnya tersirat dari tempat mengetik — tidak ada lagi peluang
     * memasukkan potongan ke blok tambahan karena dropdown-nya lupa diganti.
     */
    public array $entri = [
        'J' => ['kode' => '', 'keterangan' => '', 'nilai' => ''],
        'P' => ['kode' => '', 'keterangan' => '', 'nilai' => ''],
        'T' => ['kode' => '', 'keterangan' => '', 'nilai' => ''],
    ];

    #[On('gaji.dokter.openRincian')]
    public function openRincian(int $gajidoctorNo): void
    {
        $this->gajidoctorNo = $gajidoctorNo;
        $this->muatHeader();

        if (!$this->header) {
            $this->dispatch('toast', type: 'error', message: 'Slip tidak ditemukan.');
            return;
        }

        $this->resetValidation();
        $this->dispatch('open-modal', name: 'gaji-dokter-rincian');
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'gaji-dokter-rincian');
    }

    /* ===============================
     | DATA
     =============================== */
    protected function muatHeader(): void
    {
        $this->header = DB::table('rstxn_gajidoctorhdrs as h')
            ->join('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->leftJoin('rsmst_polis as p', 'p.poli_id', '=', 'd.poli_id')
            ->where('h.gajidoctor_no', $this->gajidoctorNo)
            ->select('h.*', 'd.dr_name', 'd.poli_id', 'p.poli_desc')
            ->first();

        $this->drName = (string) ($this->header->dr_name ?? '');
        $this->nilaiGajiPokok = (string) (int) round((float) ($this->header->nilai_gaji_pokok ?? 0));
        $this->potonganRsPersen = $this->header->potongan_rs_persen !== null
            ? rtrim(rtrim(number_format((float) $this->header->potongan_rs_persen, 2, '.', ''), '0'), '.')
            : '0';
    }

    /**
     * Ubah gaji pokok untuk slip ini saja, lalu hitung ulang.
     *
     * Berdampak berantai: gaji pokok masuk Total Gaji, sehingga potongan RS
     * (basis T) dan PPh 21 ikut bergerak. hitungUlang() yang mengurusnya.
     */
    public function simpanGajiPokok(): void
    {
        if ($this->terkunci) {
            $this->dispatch('toast', type: 'error', message: 'Slip final tidak bisa disunting.');
            return;
        }

        $nilai = trim((string) $this->nilaiGajiPokok);

        if (!is_numeric($nilai) || (float) $nilai < 0) {
            $this->addError('nilaiGajiPokok', 'Gaji pokok harus angka 0 atau lebih.');
            $this->dispatch('toast', type: 'error', message: 'Gaji pokok harus angka 0 atau lebih.');
            return;
        }

        $this->resetValidation('nilaiGajiPokok');

        DB::table('rstxn_gajidoctorhdrs')->where('gajidoctor_no', $this->gajidoctorNo)->update([
            'nilai_gaji_pokok' => (float) $nilai,
            'update_user' => Auth::user()->myuser_name ?? 'system',
            'update_date' => now(),
        ]);

        // Potongan RS & PPh dihitung dari Total Gaji yang baru — penanda manual
        // pada keduanya dilepas supaya angkanya benar-benar ikut bergerak.
        DB::table('rstxn_gajidoctordtls')
            ->where('gajidoctor_no', $this->gajidoctorNo)
            ->where('jenis', 'P')->whereIn('kode', ['RS', 'PPH21'])
            ->update(['nilai_manual' => 'N']);

        $this->hitungUlang();
        $this->dispatch('toast', type: 'success', message: 'Gaji pokok disimpan, slip dihitung ulang.');
    }

    /**
     * Ubah persen potongan RS untuk slip ini saja, lalu hitung ulang.
     *
     * Yang disunting SNAPSHOT di header, bukan master dokter — koreksi satu
     * periode tidak boleh diam-diam menggeser periode lain. Untuk mengubah
     * setelan bakunya, sunting di Master Dokter lalu proses ulang.
     */
    public function simpanPersen(): void
    {
        if ($this->terkunci) {
            $this->dispatch('toast', type: 'error', message: 'Slip final tidak bisa disunting.');
            return;
        }

        $nilai = str_replace(',', '.', trim((string) $this->potonganRsPersen));

        if (!is_numeric($nilai) || (float) $nilai < 0 || (float) $nilai > 100) {
            $this->addError('potonganRsPersen', 'Persen harus angka 0–100.');
            $this->dispatch('toast', type: 'error', message: 'Persen potongan RS harus angka 0–100.');
            return;
        }

        $this->resetValidation('potonganRsPersen');

        DB::table('rstxn_gajidoctorhdrs')->where('gajidoctor_no', $this->gajidoctorNo)->update([
            'potongan_rs_persen' => (float) $nilai,
            'update_user' => Auth::user()->myuser_name ?? 'system',
            'update_date' => now(),
        ]);

        // Persen baru hanya terasa kalau baris RS masih mengikuti rumus. Kalau
        // angkanya sudah ditimpa manual, penandanya dilepas dulu — kalau tidak,
        // persen tersimpan tapi nilainya tidak bergerak dan itu menyesatkan.
        DB::table('rstxn_gajidoctordtls')
            ->where('gajidoctor_no', $this->gajidoctorNo)
            ->where('jenis', 'P')->where('kode', 'RS')
            ->update(['nilai_manual' => 'N']);

        $this->hitungUlang();
        $this->dispatch('toast', type: 'success', message: 'Persen disimpan, potongan RS dihitung ulang.');
    }

    /**
     * Kunci slip jadi final langsung dari rincian — tanpa menutup modal dan
     * mencarinya lagi di daftar. Modal tetap terbuka lalu berubah jadi mode
     * baca supaya perubahan statusnya terlihat.
     */
    public function finalkan(): void
    {
        if ($this->terkunci) {
            $this->dispatch('toast', type: 'warning', message: 'Slip sudah final.');
            return;
        }

        DB::table('rstxn_gajidoctorhdrs')->where('gajidoctor_no', $this->gajidoctorNo)->update([
            'gaji_status' => GajiDokter::STATUS_FINAL,
            'update_user' => Auth::user()->myuser_name ?? 'system',
            'update_date' => now(),
        ]);

        $this->muatHeader();
        unset($this->terkunci, $this->detail);
        $this->dispatch('gaji.dokter.saved');
        $this->dispatch('toast', type: 'success', message: 'Slip dikunci sebagai final.');
    }

    public function bukaKunci(): void
    {
        DB::table('rstxn_gajidoctorhdrs')->where('gajidoctor_no', $this->gajidoctorNo)->update([
            'gaji_status' => GajiDokter::STATUS_DRAFT,
            'update_user' => Auth::user()->myuser_name ?? 'system',
            'update_date' => now(),
        ]);

        $this->muatHeader();
        unset($this->terkunci, $this->detail);
        $this->dispatch('gaji.dokter.saved');
        $this->dispatch('toast', type: 'success', message: 'Slip dibuka kembali menjadi draft.');
    }

    /**
     * Cetak slip — satu halaman landscape berisi DUA lembar berdampingan
     * (Arsip & Dokter), meniru bentuk workbook lama.
     */
    public function cetak(): mixed
    {
        if (!$this->header) {
            $this->dispatch('toast', type: 'error', message: 'Slip tidak ditemukan.');
            return null;
        }

        $identitasRs = DB::table('rsmst_identitases')
            ->select('int_name', 'int_address', 'int_city', 'int_phone1')
            ->first();

        $kota = trim((string) ($identitasRs->int_city ?? ''));

        $pdf = Pdf::loadView('pages.components.manajemen.cetak-slip-gaji.cetak-slip-gaji-print', [
            'header' => $this->header,
            'detail' => $this->detail,
            'identitasRs' => $identitasRs,
            // locale('id') WAJIB — APP_LOCALE repo ini 'en', tanpa itu bulannya
            // tercetak 'August' alih-alih 'Agustus'. Pola yang sama dipakai
            // cetakan resume medis & ringkasan pulang.
            'tanggalCetak' => trim($kota . ($kota !== '' ? ', ' : '') . now()->locale('id')->translatedFormat('d F Y')),
        ])->setPaper('A4');   // sama dengan cetak kwitansi RJ — dompdf mengabaikan
                              // width/height pada @page milik layout-kwitansi, jadi
                              // kertasnya tetap ditentukan di sini.

        $namaBerkas = 'slip-gaji-' . $this->header->dr_id . '-'
            . $this->header->tahun_gaji . $this->header->bulan_gaji . '.pdf';

        return response()->streamDownload(fn () => print $pdf->output(), $namaBerkas);
    }

    #[Computed]
    public function detail()
    {
        if ($this->gajidoctorNo === null) {
            return collect();
        }

        return DB::table('rstxn_gajidoctordtls')
            ->where('gajidoctor_no', $this->gajidoctorNo)
            ->orderBy('jenis')
            ->orderBy('urutan')
            ->orderBy('gajidoctor_dtl')
            ->get();
    }

    /**
     * CATATAN: memakai #[Computed], bukan getTerkunciProperty(). Nilainya
     * di-cache selama satu request — dan aksi seperti finalkan() membaca
     * $this->terkunci sebagai penjaga SEBELUM mengubah status, sehingga nilai
     * lama ikut terbawa sampai render. Dengan #[Computed], cache-nya bisa
     * dibuang eksplisit lewat unset() begitu status berubah.
     */
    #[Computed]
    public function terkunci(): bool
    {
        return ($this->header->gaji_status ?? GajiDokter::STATUS_FINAL) === GajiDokter::STATUS_FINAL;
    }

    /* ===============================
     | AKSI
     =============================== */
    public function tambahBaris(string $jenis): void
    {
        if ($this->terkunci) {
            $this->dispatch('toast', type: 'error', message: 'Slip final tidak bisa disunting.');
            return;
        }

        if (!isset($this->entri[$jenis])) {
            return;
        }

        $this->resetValidation();

        $kode = strtoupper(trim((string) $this->entri[$jenis]['kode']));
        $keterangan = trim((string) $this->entri[$jenis]['keterangan']);
        $nilai = str_replace(',', '.', trim((string) $this->entri[$jenis]['nilai']));

        if ($kode === '') {
            $this->addError("entri.{$jenis}.kode", 'Kode wajib dipilih.');
            return;
        }

        // Keterangan wajib: inilah yang tercetak di slip. Sebelumnya kosong akan
        // diisi kode, sehingga baris 'LAIN' bisa berjejer tanpa penjelasan apa pun.
        if ($keterangan === '') {
            $this->addError("entri.{$jenis}.keterangan", 'Keterangan wajib diisi.');
            return;
        }

        // 'RS' & 'PPH21' dihitung ulang otomatis tiap kali detail berubah —
        // baris manual bernama sama akan langsung tertimpa dan membingungkan.
        if (in_array($kode, ['RS', 'PPH21'], true)) {
            $this->addError("entri.{$jenis}.kode", 'Kode RS dan PPH21 dihitung otomatis, tidak bisa ditambah manual.');
            return;
        }

        if (!is_numeric($nilai) || (float) $nilai <= 0) {
            $this->addError("entri.{$jenis}.nilai", 'Nilai harus angka lebih dari 0.');
            return;
        }

        // Kode 'LAIN' boleh berulang selama keterangannya beda — itu memang
        // penampung untuk hal di luar daftar. Kode baku tidak boleh kembar.
        $sudahAda = DB::table('rstxn_gajidoctordtls')
            ->where('gajidoctor_no', $this->gajidoctorNo)
            ->where('jenis', $jenis)
            ->where('kode', $kode)
            ->when($kode === 'LAIN', fn ($query) => $query->where('keterangan', $keterangan))
            ->exists();

        if ($sudahAda) {
            $this->addError("entri.{$jenis}.kode", 'Sudah ada pada slip ini. Sunting nilainya langsung di tabel.');
            return;
        }

        // Potongan disimpan NEGATIF, jasa & tambahan POSITIF. Petugas cukup
        // mengetik angka positif; tandanya diurus di sini supaya tidak ada slip
        // dengan potongan bertanda terbalik.
        GajiDokter::simpanDetail($this->gajidoctorNo, [
            'jenis' => $jenis,
            'kode' => $kode,
            'keterangan' => $keterangan,
            'nilai' => $jenis === 'P' ? -abs((float) $nilai) : abs((float) $nilai),
            'jumlah_pasien' => 0,
        ], 500, Auth::user()->myuser_name ?? 'system');

        $this->entri[$jenis] = ['kode' => '', 'keterangan' => '', 'nilai' => ''];

        // x-text-input-number menyetel tampilannya sendiri lewat $wire.$watch, dan
        // watcher itu tidak tepercaya untuk properti bersarang seperti
        // entri.P.nilai — angkanya tertinggal di layar walau state sudah kosong.
        // Karena itu pengosongan diberitahukan eksplisit ke elemennya.
        $this->dispatch('entri-gaji-direset', jenis: $jenis);

        $this->hitungUlang();
        $this->dispatch('toast', type: 'success', message: "Baris {$kode} ditambahkan.");
    }

    public function ubahNilai(int $gajidoctorDtl, int|float|string $nilai): void
    {
        if ($this->terkunci) {
            $this->dispatch('toast', type: 'error', message: 'Slip final tidak bisa disunting.');
            return;
        }

        if (!is_numeric($nilai)) {
            $this->dispatch('toast', type: 'error', message: 'Nilai harus berupa angka.');
            return;
        }

        $baris = DB::table('rstxn_gajidoctordtls')->where('gajidoctor_dtl', $gajidoctorDtl)->first();
        if (!$baris) {
            return;
        }

        $angka = (float) $nilai;
        $angka = $baris->jenis === 'P' ? -abs($angka) : abs($angka);

        // Potongan RS & PPh 21 normalnya hasil rumus. Begitu angkanya diketik
        // sendiri, barisnya ditandai manual supaya hitung ulang berikutnya tidak
        // menimpanya balik. Baris lain tidak perlu penanda — memang selalu manual.
        $update = [
            'nilai' => round($angka, 2),
            'update_user' => Auth::user()->myuser_name ?? 'system',
            'update_date' => now(),
        ];

        if (in_array($baris->kode, ['RS', 'PPH21'], true)) {
            $update['nilai_manual'] = 'Y';
        }

        DB::table('rstxn_gajidoctordtls')->where('gajidoctor_dtl', $gajidoctorDtl)->update($update);

        $this->hitungUlang();
    }

    /**
     * Kembalikan potongan RS / PPh 21 ke hasil rumus — cukup lepas penandanya,
     * hitung ulang yang mengisi angkanya lagi.
     */
    public function kembalikanHitungan(int $gajidoctorDtl): void
    {
        if ($this->terkunci) {
            $this->dispatch('toast', type: 'error', message: 'Slip final tidak bisa disunting.');
            return;
        }

        DB::table('rstxn_gajidoctordtls')->where('gajidoctor_dtl', $gajidoctorDtl)->update([
            'nilai_manual' => 'N',
            'update_user' => Auth::user()->myuser_name ?? 'system',
            'update_date' => now(),
        ]);

        $this->hitungUlang();
        $this->dispatch('toast', type: 'success', message: 'Dikembalikan ke hasil hitungan.');
    }

    public function ubahJumlahPasien(int $gajidoctorDtl, int|float|string $jumlah): void
    {
        if ($this->terkunci || !is_numeric($jumlah)) {
            return;
        }

        $baris = DB::table('rstxn_gajidoctordtls')->where('gajidoctor_dtl', $gajidoctorDtl)->first();
        if (!$baris) {
            return;
        }

        $pasien = max(0, (int) $jumlah);

        // Khusus baris per kapita, nilainya turunan: jumlah pasien x tarif.
        // Tarifnya dibaca balik dari nilai lama supaya tetap konsisten walau
        // tarif di master sudah berubah setelah slip dibuat.
        $update = ['jumlah_pasien' => $pasien];

        if (str_starts_with($baris->kode, 'KAPITA') && $baris->jumlah_pasien > 0) {
            $tarif = (float) $baris->nilai / (int) $baris->jumlah_pasien;
            $update['nilai'] = round($pasien * $tarif, 2);
        }

        $update['update_user'] = Auth::user()->myuser_name ?? 'system';
        $update['update_date'] = now();

        DB::table('rstxn_gajidoctordtls')->where('gajidoctor_dtl', $gajidoctorDtl)->update($update);
        $this->hitungUlang();
    }

    public function hapusBaris(int $gajidoctorDtl): void
    {
        if ($this->terkunci) {
            $this->dispatch('toast', type: 'error', message: 'Slip final tidak bisa disunting.');
            return;
        }

        DB::table('rstxn_gajidoctordtls')->where('gajidoctor_dtl', $gajidoctorDtl)->delete();
        $this->hitungUlang();
        $this->dispatch('toast', type: 'success', message: 'Baris dihapus.');
    }

    protected function hitungUlang(): void
    {
        GajiDokter::hitungUlang($this->gajidoctorNo, Auth::user()->myuser_name ?? 'system');
        $this->muatHeader();
        unset($this->terkunci, $this->detail);
        $this->dispatch('gaji.dokter.saved');
    }

};
?>

<div>
    {{-- size/height "full" — seragam dengan modal transaksi lain. Rinciannya tiga
         tabel bertumpuk, jadi butuh lebar & tinggi penuh. --}}
    <x-modal name="gaji-dokter-rincian" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]">

            {{-- HEADER --}}
            {{-- py-3, bukan py-5: judulnya sekarang label kecil, tidak butuh ruang
                 setinggi itu. Ini yang paling terasa melebarkan jarak ke kartu. --}}
            <div class="px-6 py-3 bg-surface-soft">
                <div class="flex items-start justify-between gap-4">
                    {{-- Nama dokter TIDAK diulang di sini — sudah jadi judul kartu
                         identitas tepat di bawahnya. Dua kali di satu layar cuma
                         memakan tinggi tanpa menambah informasi. --}}
                    <div class="min-w-0">
                        {{-- Sengaja kecil: yang berhak jadi judul besar adalah nama dokter
                             di kartu identitas bawah. Ini cuma penanda modal apa yang
                             sedang dibuka. Tetap <h2> supaya urutan heading tidak putus. --}}
                        <h2 class="text-sm font-semibold tracking-wider uppercase text-muted dark:text-gray-400">
                            Rincian Slip Gaji
                        </h2>
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
            <div class="flex-1 px-4 pt-2 pb-4 bg-surface-soft dark:bg-gray-950/20">
                <div class="space-y-4">

                    {{-- KARTU IDENTITAS DOKTER — meniru tema display-pasien-rj:
                         satu kartu dibagi 5 kolom, kiri (3) identitas & parameter,
                         kanan (2) info periode, dipisah garis vertikal. --}}
                    @if ($header)
                        <div class="px-4 py-2.5 text-base border rounded-lg border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700">
                            <div class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-5">

                                {{-- ===== KIRI: Identitas & Parameter Gaji ===== --}}
                                <div class="space-y-1.5 sm:col-span-3 sm:border-r sm:border-hairline dark:sm:border-gray-700 sm:pr-4">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <span class="text-xl font-bold truncate text-ink dark:text-white">
                                            {{ $header->dr_name }}
                                        </span>
                                        <span class="font-mono text-base text-muted dark:text-gray-400 shrink-0">
                                            {{ $header->dr_id }}
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-2">
                                        <div class="space-y-1">
                                            <div>
                                                <span class="text-muted">Poli:</span>
                                                <span class="ml-1 text-body dark:text-gray-300">{{ $header->poli_desc ?: '-' }}</span>
                                            </div>
                                            <div>
                                                <span class="text-muted">Skema:</span>
                                                <span class="ml-1 text-body dark:text-gray-300">
                                                    <span class="font-mono">{{ $header->skema_gaji_pokok }}</span>
                                                    &mdash;
                                                    {{ match ($header->skema_gaji_pokok) {
                                                        'G' => 'garanty fee',
                                                        'N' => 'tanpa gaji pokok',
                                                        default => 'aditif',
                                                    } }}
                                                </span>
                                            </div>
                                            <div>
                                                <span class="text-muted">Gaji Pokok:</span>
                                                <span class="ml-1 t-num text-body dark:text-gray-300">
                                                    {{ number_format((float) $header->nilai_gaji_pokok, 0, ',', '.') }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="space-y-1">
                                            <div>
                                                <span class="text-muted">Potongan RS:</span>
                                                <span class="ml-1 text-body dark:text-gray-300">
                                                    <span class="font-mono">{{ $header->potongan_rs_basis }}</span>
                                                    @if (in_array($header->potongan_rs_basis, ['T', 'J'], true))
                                                        &middot;
                                                        <span class="t-num">{{ rtrim(rtrim(number_format((float) $header->potongan_rs_persen, 2, ',', '.'), '0'), ',') }}%</span>
                                                        dari {{ $header->potongan_rs_basis === 'J' ? 'jasa' : 'total gaji' }}
                                                    @elseif ($header->potongan_rs_basis === 'B')
                                                        &middot; berjenjang per komponen
                                                    @else
                                                        &middot; tidak dipotong
                                                    @endif
                                                </span>
                                            </div>
                                            <div>
                                                <span class="text-muted">PPh 21:</span>
                                                <span class="ml-1 t-num text-body dark:text-gray-300">
                                                    {{ rtrim(rtrim(number_format((float) $header->pph21_persen, 2, ',', '.'), '0'), ',') }}%
                                                </span>
                                            </div>
                                            <div class="font-mono text-sm text-muted dark:text-gray-400">
                                                Slip #{{ $header->gajidoctor_no }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- ===== KANAN: Info Periode ===== --}}
                                <div class="space-y-1.5 sm:col-span-2">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-muted">Status:</span>
                                        <x-badge :variant="$header->gaji_status === 'F' ? 'success' : 'warning'">
                                            {{ $header->gaji_status === 'F' ? 'Final' : 'Draft' }}
                                        </x-badge>
                                    </div>

                                    <div class="flex items-start justify-between gap-2 text-lg">
                                        <span class="font-semibold text-ink dark:text-white">
                                            Jasa {{ $header->bulan_jasa }}/{{ $header->tahun_jasa }}
                                        </span>
                                        <span class="font-semibold text-right text-brand dark:text-brand-lime">
                                            Gaji {{ $header->bulan_gaji }}/{{ $header->tahun_gaji }}
                                        </span>
                                    </div>

                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-muted">Total Gaji:</span>
                                        <span class="font-semibold t-num text-ink dark:text-gray-100">
                                            {{ number_format((float) $header->total_gaji, 0, ',', '.') }}
                                        </span>
                                    </div>

                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-muted">Gaji Diterima:</span>
                                        <span class="text-xl font-bold t-num text-emerald-700 dark:text-emerald-300">
                                            {{ number_format((float) $header->gaji_diterima, 0, ',', '.') }}
                                        </span>
                                    </div>
                                </div>

                            </div>
                        </div>
                    @endif

                    @if ($this->terkunci)
                        <div class="px-4 py-3 text-sm border rounded-xl bg-emerald-50 border-emerald-200 text-emerald-900 dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-200">
                            Slip sudah final dan terkunci. Untuk menyuntingnya, buka kunci dulu lewat daftar slip.
                        </div>
                    @endif

                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    @foreach ([
                        'J' => [
                            'judul' => 'Jasa & Tunjangan',
                            'catatan' => 'Membentuk total gaji — kena PPh.',
                            'contoh' => 'mis. Bacaan USG bulan Juli',
                        ],
                        'P' => [
                            'judul' => 'Potongan',
                            'catatan' => 'Dua baris teratas (Potongan RS & PPh 21) terisi dari rumus dan ikut menentukan pajak; sisanya di bawah garis dipotong setelah pajak. Ketik nilainya positif, tanda minus diurus otomatis.',
                            'contoh' => 'mis. Kasbon 12 Juli',
                        ],
                        'T' => [
                            'judul' => 'Tambahan',
                            'catatan' => 'Diberikan setelah pajak (bonus, rapel, koreksi bulan lalu).',
                            'contoh' => 'mis. Rapel kenaikan Mei–Juni',
                        ],
                    ] as $jenis => $meta)
                        @php $baris = $this->detail->where('jenis', $jenis); @endphp

                        {{-- Tiap blok jadi item grid utuh — pembungkusnya dibuka & ditutup
                             dalam satu iterasi, jadi tidak ada tag komponen yang terbelah
                             antar-@if (pola itu bikin konten hilang saat kondisinya berubah).
                             'Tambahan' merentang dua kolom karena isinya paling jarang. --}}
                        <div class="{{ $jenis === 'T' ? 'xl:col-span-2' : '' }}">
                        <x-border-form :title="$meta['judul']">
                            <div class="space-y-3">
                                <p class="text-sm text-muted dark:text-gray-400">{{ $meta['catatan'] }}</p>

                                {{-- Kendali gaji pokok — ditaruh di blok Jasa & Tunjangan karena
                                     gaji pokok itu PEMBENTUK Total Gaji, bukan pengurang. Bisa beda
                                     antar bulan tanpa menyentuh Master Dokter. Bentuknya sengaja
                                     kembar dengan strip Potongan RS di blok sebelah. --}}
                                @if ($header && $jenis === 'J')
                                    <div class="flex flex-wrap items-center gap-3 px-3 py-2 border rounded-xl border-hairline bg-surface-soft dark:bg-gray-800 dark:border-gray-700">
                                        <span class="text-sm font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                                            Gaji Pokok
                                        </span>

                                        @if ($header->skema_gaji_pokok === 'N')
                                            <span class="inline-flex items-center px-2 py-1 font-mono text-sm border rounded-lg border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700">
                                                skema N
                                            </span>
                                            <span class="text-sm text-muted dark:text-gray-400">
                                                Dokter ini tidak memakai gaji pokok. Ubah skemanya di
                                                <span class="font-semibold">Master Dokter &rarr; Parameter Penggajian</span>,
                                                lalu proses ulang.
                                            </span>
                                        @else
                                            {{-- Lebar lewat pembungkus, bukan kelas pada komponen:
                                                 x-text-input-number sudah membawa w-full di baseClass-nya. --}}
                                            <div class="w-40">
                                                <x-text-input-number wire:model="nilaiGajiPokok"
                                                    :disabled="$this->terkunci"
                                                    :error="$errors->has('nilaiGajiPokok')"
                                                    x-on:keydown.enter.prevent="$el.blur(); $wire.simpanGajiPokok()" />
                                            </div>

                                            <span class="text-sm whitespace-nowrap text-muted dark:text-gray-400">
                                                {{ $header->skema_gaji_pokok === 'G'
                                                    ? 'garanty fee — dipakai bila lebih besar dari jasa'
                                                    : 'ditambahkan ke jasa' }}
                                            </span>

                                            @unless ($this->terkunci)
                                                <x-secondary-button type="button" class="px-3 py-1.5 text-sm ml-auto shrink-0"
                                                    wire:click="simpanGajiPokok" wire:loading.attr="disabled"
                                                    wire:target="simpanGajiPokok">
                                                    <span wire:loading.remove wire:target="simpanGajiPokok">Terapkan</span>
                                                    <span wire:loading wire:target="simpanGajiPokok"><x-loading /> Menghitung...</span>
                                                </x-secondary-button>
                                            @endunless
                                        @endif

                                        <x-input-error :messages="$errors->get('nilaiGajiPokok')" class="w-full" />
                                    </div>
                                @endif

                                {{-- Kendali potongan RS ditaruh DI SINI, bukan sebagai panel
                                     tersendiri di atas: yang diubahnya adalah baris RS pada tabel
                                     tepat di bawah, jadi sebab dan akibatnya terbaca sekali lihat.

                                     SELALU DITAMPILKAN untuk semua basis. Sebelumnya hanya muncul
                                     pada basis T/J, sehingga pada slip basis N/B kendalinya lenyap
                                     tanpa penjelasan — terbaca seperti fitur yang kadang ada kadang
                                     tidak. Sekarang basis N & B tetap punya barisnya, isinya
                                     keterangan kenapa tidak ada persen yang bisa diketik. --}}
                                @if ($header && $jenis === 'P')
                                    <div class="flex flex-wrap items-center gap-3 px-3 py-2 border rounded-xl border-hairline bg-surface-soft dark:bg-gray-800 dark:border-gray-700">
                                        <span class="text-sm font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                                            Potongan RS
                                        </span>

                                        @if (in_array($header->potongan_rs_basis, ['T', 'J'], true))
                                            {{-- Satu baris utuh: label, kotak persen, keterangan, tombol.
                                                 Semua bagian shrink-0 dan keterangannya dipendekkan supaya
                                                 tidak membungkus & mendorong tombol turun. Penjelasan
                                                 panjangnya pindah ke tooltip. --}}
                                            <div class="flex items-center gap-2 shrink-0">
                                                {{-- Lebar diatur lewat pembungkus, BUKAN kelas pada komponen:
                                                     x-text-input sudah membawa w-full di baseClass-nya, dan
                                                     w-full menang atas w-16 karena urutannya belakangan di CSS
                                                     hasil build — bukan karena urutan penulisan di sini.

                                                     :decimals="2" wajib — tanpa itu titik desimal dibuang dan
                                                     "7.5" tersimpan jadi 75. --}}
                                                <div class="w-20">
                                                    <x-text-input-number wire:model="potonganRsPersen" :decimals="2"
                                                        :disabled="$this->terkunci"
                                                        :error="$errors->has('potonganRsPersen')" maxlength="6"
                                                        x-on:keydown.enter.prevent="$el.blur(); $wire.simpanPersen()" />
                                                </div>
                                                <span class="text-sm text-muted dark:text-gray-400">%</span>
                                            </div>

                                            <span class="text-sm whitespace-nowrap text-muted dark:text-gray-400"
                                                title="{{ $header->potongan_rs_basis === 'J'
                                                    ? 'Dihitung dari jasa saja — gaji pokok bebas potongan.'
                                                    : 'Dihitung dari total gaji — gaji pokok ikut dipotong.' }}">
                                                dari {{ $header->potongan_rs_basis === 'J' ? 'jasa' : 'total gaji' }}
                                            </span>

                                            @unless ($this->terkunci)
                                                <x-secondary-button type="button" class="px-3 py-1.5 text-sm ml-auto shrink-0"
                                                    wire:click="simpanPersen">
                                                    Terapkan
                                                </x-secondary-button>
                                            @endunless
                                        @else
                                            <span class="inline-flex items-center px-2 py-1 font-mono text-sm border rounded-lg border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700">
                                                basis {{ $header->potongan_rs_basis }}
                                            </span>

                                            <span class="text-sm text-muted dark:text-gray-400">
                                                {{ $header->potongan_rs_basis === 'N'
                                                    ? 'Dokter ini tidak dikenai potongan RS, jadi tidak ada persen yang diatur.'
                                                    : 'Dihitung berjenjang per komponen jasa, bukan satu persentase.' }}
                                                Ubah di <span class="font-semibold">Master Dokter &rarr; Parameter Penggajian</span>,
                                                lalu proses ulang. Untuk koreksi periode ini saja, ketik langsung
                                                nominalnya pada baris <span class="font-mono">RS</span> di bawah.
                                            </span>
                                        @endif

                                        <x-input-error :messages="$errors->get('potonganRsPersen')" class="w-full" />
                                    </div>
                                @endif

                                {{-- Tabel bergaya Daftar RJ: baris = kartu ber-ring, dipisah
                                     border-spacing-y-2, header sticky. --}}
                                <div class="overflow-x-auto overflow-y-auto max-h-[26rem] rounded-2xl">
                                    <table class="w-full min-w-full -mt-2 text-sm border-separate border-spacing-y-2 table-fixed">
                                        <thead class="sticky top-0 z-10">
                                            <tr class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                                {{-- Kolom Kode disembunyikan: untuk baris hasil generate
                                                     isinya sama persis dengan Keterangan, jadi cuma
                                                     mengulang. Kodenya tetap ada di data & muncul sebagai
                                                     tooltip pada Keterangan. --}}
                                                <th class="px-4 py-3 w-[44%] bg-surface-card dark:bg-gray-800">Keterangan</th>
                                                <th class="px-3 py-3 w-[16%] text-right bg-surface-card dark:bg-gray-800">Jml Pasien</th>
                                                <th class="px-3 py-3 w-[28%] text-right bg-surface-card dark:bg-gray-800">Nilai</th>
                                                <th class="px-2 py-3 w-[12%] text-center bg-surface-card dark:bg-gray-800"></th>
                                            </tr>
                                        </thead>
                                        @php $sudahDipisah = false; @endphp
                                        <tbody>
                                            @forelse ($baris as $item)
                                                @php
                                                    // 'RS' & 'PPH21' berasal dari rumus, tapi TETAP bisa disunting —
                                                    // begitu diketik, baris ditandai manual & rumus berhenti menimpa.
                                                    $terhitung = in_array($item->kode, ['RS', 'PPH21'], true);
                                                    $disesuaikan = $terhitung && $item->nilai_manual === 'Y';
                                                @endphp

                                                {{-- Garis pemisah sekali saja, tepat sebelum potongan pertama yang
                                                     bukan hasil rumus. Baris RS & PPh 21 di atasnya ikut membentuk
                                                     dasar pajak; yang di bawah tidak — dipisah supaya tidak dikira
                                                     sama-sama mengurangi PPh. --}}
                                                @if ($jenis === 'P' && !$terhitung && !$sudahDipisah)
                                                    @php $sudahDipisah = true; @endphp
                                                    <tr>
                                                        <td colspan="4"
                                                            class="px-4 pt-3 pb-1 text-sm text-muted dark:text-gray-400">
                                                            &darr; dipotong setelah pajak &mdash; tidak mengurangi dasar PPh
                                                        </td>
                                                    </tr>
                                                @endif
                                                <tr class="transition rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700
                                                    {{ $disesuaikan
                                                        ? 'bg-warning/10 dark:bg-amber-900/15 hover:shadow-lg'
                                                        : ($terhitung
                                                            ? 'bg-surface-soft dark:bg-gray-800 hover:shadow-lg'
                                                            : 'bg-canvas dark:bg-gray-900 hover:shadow-lg hover:bg-surface-soft dark:hover:bg-gray-800') }}">
                                                    <td class="px-4 py-2 align-middle rounded-l-2xl" title="Kode: {{ $item->kode }}">
                                                        {{ $item->keterangan }}
                                                        @if ($disesuaikan)
                                                            <button type="button"
                                                                class="ml-1 text-sm text-brand-green hover:underline dark:text-brand-lime"
                                                                wire:click="kembalikanHitungan({{ $item->gajidoctor_dtl }})"
                                                                wire:confirm="Kembalikan {{ $item->kode }} ke hasil hitungan rumus?">
                                                                kembalikan ke hitungan
                                                            </button>
                                                        @elseif ($terhitung)
                                                            <span class="ml-1 text-sm text-muted dark:text-gray-400">(dari rumus &mdash; boleh diubah)</span>
                                                        @endif
                                                    </td>
                                                    @php
                                                        // Nilai disimpan negatif untuk potongan; yang ditampilkan &
                                                        // diketik selalu positif, tandanya diurus di sisi PHP.
                                                        $pasienAwal = (int) $item->jumlah_pasien;
                                                        $nilaiAwal = (int) round(abs((float) $item->nilai));
                                                    @endphp
                                                    <td class="px-3 py-2 text-right align-middle tabular-nums">
                                                        @if ($this->terkunci || $terhitung)
                                                            {{ $pasienAwal ?: '' }}
                                                        @else
                                                            {{-- extraBlur hanya menembak saat angkanya BERUBAH — tanpa
                                                                 penjaga ini tiap klik keluar kolom memicu satu request
                                                                 dan satu hitung ulang slip. --}}
                                                            <div class="w-full">
                                                                <x-text-input-number
                                                                    :value="$pasienAwal"
                                                                    :extraBlur="'if (raw !== ' . $pasienAwal . ') $wire.ubahJumlahPasien(' . $item->gajidoctor_dtl . ', raw)'"
                                                                    x-on:keydown.enter.prevent="$el.blur()" />
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 text-right align-middle tabular-nums {{ (float) $item->nilai < 0 ? 'text-rose-600 dark:text-rose-300' : '' }}">
                                                        @if ($this->terkunci)
                                                            {{ number_format((float) $item->nilai, 0, ',', '.') }}
                                                        @else
                                                            <div class="w-full">
                                                                <x-text-input-number
                                                                    :value="$nilaiAwal"
                                                                    :extraBlur="'if (raw !== ' . $nilaiAwal . ') $wire.ubahNilai(' . $item->gajidoctor_dtl . ', raw)'"
                                                                    x-on:keydown.enter.prevent="$el.blur()" />
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td class="px-2 py-2 text-center align-middle">
                                                        {{-- icon-button color="red" — warna & ukuran dari komponen,
                                                             sesuai docs/standar-komponen-tombol.md ("red = Hapus,
                                                             ikon kecil di dalam form"). Tidak ada kelas yang
                                                             ditimpa. --}}
                                                        @unless ($this->terkunci || $terhitung)
                                                            <x-icon-button color="red" type="button"
                                                                wire:click="hapusBaris({{ $item->gajidoctor_dtl }})"
                                                                wire:confirm="Hapus baris {{ $item->kode }}?"
                                                                wire:loading.attr="disabled"
                                                                wire:target="hapusBaris({{ $item->gajidoctor_dtl }})"
                                                                title="Hapus baris {{ $item->kode }}">
                                                                <span wire:loading.remove wire:target="hapusBaris({{ $item->gajidoctor_dtl }})">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                                        stroke-width="1.8">
                                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                    </svg>
                                                                </span>
                                                                <span wire:loading wire:target="hapusBaris({{ $item->gajidoctor_dtl }})">
                                                                    <x-loading />
                                                                </span>
                                                            </x-icon-button>
                                                        @endunless
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="px-4 py-6 text-center text-muted dark:text-gray-400">
                                                        Belum ada baris.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                        {{-- Subtotal sengaja lebih besar & tebal dari baris data:
                                             ini angka yang dicari mata setelah menyunting, jadi
                                             harus menang tanpa perlu dibaca satu-satu. --}}
                                        <tfoot>
                                            @php $subtotal = (float) $baris->sum('nilai'); @endphp
                                            <tr class="rounded-2xl ring-1 ring-hairline bg-surface-card dark:bg-gray-800 dark:ring-gray-700">
                                                <td class="px-4 py-3 text-sm font-semibold tracking-wide uppercase rounded-l-2xl text-muted dark:text-gray-300"
                                                    colspan="2">
                                                    Subtotal
                                                </td>
                                                <td class="px-3 py-3 text-lg font-bold text-right tabular-nums {{ $subtotal < 0 ? 'text-rose-600 dark:text-rose-300' : 'text-ink dark:text-gray-100' }}">
                                                    {{ number_format($subtotal, 0, ',', '.') }}
                                                </td>
                                                <td class="rounded-r-2xl"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                {{-- ENTRI BARIS — menempel di bloknya sendiri, jadi jenisnya
                                     tersirat dan tidak perlu dropdown "Jenis" lagi. --}}
                                @unless ($this->terkunci)
                                    {{-- Satu baris: Kode / Keterangan / Nilai / Tambah.
                                         Porsinya 3-4-3-2 dari 12 — dua select & angka dapat ruang
                                         cukup, tombol secukupnya. Menumpuk jadi satu kolom di
                                         bawah md, karena di layar sempit empat kendali sebaris
                                         pasti berdesakan. --}}
                                    <div class="grid grid-cols-1 gap-3 pt-1 border-t md:grid-cols-12 border-hairline dark:border-gray-800">
                                        {{-- Enter-chain (pola e-resep, lihat skill livewire-input-patterns):
                                             Kode → Keterangan → Nilai → simpan, lalu fokus balik ke Kode.
                                             Tombol Tambah dipertahankan untuk pemakai tetikus.

                                             Kode memakai wire:model.live, BUKAN deferred: nilainya harus
                                             sudah sampai server sebelum Enter di kolom Nilai memicu simpan.

                                             Enter di Nilai wajib $el.blur() dulu — x-text-input-number
                                             menyinkron lewat $wire.set saat blur, jadi tanpa itu angkanya
                                             belum terkirim dan validasi menolak seolah kosong.

                                             Tombol "Tambah" sengaja tidak ada: Enter sudah menyimpan, dan
                                             tombol terpisah membuat orang ragu apakah Enter berfungsi. --}}
                                        <div class="md:col-span-3">
                                            <x-input-label value="Kode *" class="mb-1" />
                                            <x-select-input wire:model.live="entri.{{ $jenis }}.kode"
                                                id="entri-kode-{{ $jenis }}"
                                                :error="$errors->has('entri.' . $jenis . '.kode')"
                                                x-on:keydown.enter.prevent="$refs.entriKet{{ $jenis }}?.focus()">
                                                <option value="">— pilih —</option>
                                                @foreach (\App\Support\GajiDokter::kodeUntukJenis($jenis) as $kode)
                                                    <option value="{{ $kode }}">{{ $kode }}</option>
                                                @endforeach
                                            </x-select-input>
                                            <x-input-error :messages="$errors->get('entri.' . $jenis . '.kode')" class="mt-1" />
                                        </div>

                                        <div class="md:col-span-6">
                                            <x-input-label value="Keterangan *" class="mb-1" />
                                            <x-text-input wire:model="entri.{{ $jenis }}.keterangan" class="w-full"
                                                x-ref="entriKet{{ $jenis }}" maxlength="100"
                                                :placeholder="$meta['contoh']"
                                                :error="$errors->has('entri.' . $jenis . '.keterangan')"
                                                x-on:keydown.enter.prevent="$refs.entriNilai{{ $jenis }}?.focus()" />
                                            <x-input-error :messages="$errors->get('entri.' . $jenis . '.keterangan')" class="mt-1" />
                                        </div>

                                        <div class="md:col-span-3">
                                            <x-input-label value="Nilai *" class="mb-1" />
                                            <x-text-input-number wire:model="entri.{{ $jenis }}.nilai" class="w-full"
                                                x-ref="entriNilai{{ $jenis }}"
                                                x-on:entri-gaji-direset.window="if ($event.detail.jenis === '{{ $jenis }}') $el.value = ''"
                                                :error="$errors->has('entri.' . $jenis . '.nilai')"
                                                x-on:keydown.enter.prevent="$el.blur(); $wire.tambahBaris('{{ $jenis }}').then(() =>
                                                    setTimeout(() => document.getElementById('entri-kode-{{ $jenis }}')?.focus(), 100))" />
                                            <x-input-error :messages="$errors->get('entri.' . $jenis . '.nilai')" class="mt-1" />
                                        </div>

                                        <p class="text-sm text-muted dark:text-gray-400 md:col-span-12">
                                            Tekan <span class="font-semibold">Enter</span> untuk pindah kolom;
                                            Enter di kolom Nilai menyimpan barisnya.
                                        </p>
                                    </div>
                                @endunless
                            </div>
                        </x-border-form>
                        </div>
                    @endforeach
                    </div>

                </div>
            </div>

            {{-- FOOTER --}}
            @if ($header)
                <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-surface-soft border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="grid flex-1 grid-cols-2 gap-x-6 gap-y-2 sm:grid-cols-4">
                            <div>
                                <div class="text-sm tracking-wide text-muted uppercase dark:text-gray-400">Total Gaji</div>
                                <div class="text-lg font-semibold tabular-nums text-ink dark:text-gray-100">
                                    {{ number_format((float) $header->total_gaji, 0, ',', '.') }}
                                </div>
                            </div>
                            <div>
                                <div class="text-sm tracking-wide text-muted uppercase dark:text-gray-400">Potongan RS</div>
                                <div class="text-lg font-semibold tabular-nums text-rose-600 dark:text-rose-300">
                                    {{ number_format((float) $header->potongan_rs, 0, ',', '.') }}
                                </div>
                            </div>
                            <div>
                                <div class="text-sm tracking-wide text-muted uppercase dark:text-gray-400">PPh 21</div>
                                <div class="text-lg font-semibold tabular-nums text-rose-600 dark:text-rose-300">
                                    {{ number_format((float) $header->pph21, 0, ',', '.') }}
                                </div>
                            </div>
                            {{-- Gaji Diterima dibuat paling besar: ini angka yang benar-benar
                                 dibayarkan, sisanya cuma penyusunnya. --}}
                            <div>
                                <div class="text-sm tracking-wide text-muted uppercase dark:text-gray-400">Gaji Diterima</div>
                                <div class="text-2xl font-bold leading-tight tabular-nums text-emerald-700 dark:text-emerald-300">
                                    {{ number_format((float) $header->gaji_diterima, 0, ',', '.') }}
                                </div>
                            </div>
                        </div>

                        {{-- Kunci/buka kunci ditaruh di sini juga supaya alurnya tuntas dalam
                             satu layar: koreksi rincian lalu langsung final, tanpa menutup
                             modal dan mencari barisnya lagi di daftar. --}}
                        <div class="flex items-center gap-2 shrink-0">
                            {{-- Warna mengikuti docs/standar-komponen-tombol.md:
                                 Tutup    -> secondary (aksi netral di footer modal)
                                 Cetak    -> info      (biru, aksi cetak bertext)
                                 Final    -> primary   (aksi utama modal ini)
                                 BukaKunci-> warning   (perlu perhatian, tidak destruktif) --}}
                            <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>

                            <x-info-button type="button" class="gap-2" wire:click="cetak"
                                wire:loading.attr="disabled" wire:target="cetak"
                                title="Cetak slip gaji">
                                <span wire:loading.remove wire:target="cetak" class="inline-flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5z" />
                                    </svg>
                                    Cetak
                                </span>
                                <span wire:loading wire:target="cetak" class="inline-flex items-center gap-2">
                                    <x-loading /> Menyiapkan...
                                </span>
                            </x-info-button>

                            @if ($this->terkunci)
                                <x-warning-button type="button" wire:click="bukaKunci"
                                    wire:loading.attr="disabled" wire:target="bukaKunci"
                                    wire:confirm="Buka kunci slip {{ $drName }}? Slip akan bisa disunting & diproses ulang.">
                                    <span wire:loading.remove wire:target="bukaKunci" class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" /></svg>
                                        Buka Kunci
                                    </span>
                                    <span wire:loading wire:target="bukaKunci"><x-loading /> Membuka...</span>
                                </x-warning-button>
                            @else
                                <x-primary-button type="button" wire:click="finalkan"
                                    wire:loading.attr="disabled" wire:target="finalkan"
                                    wire:confirm="Kunci slip {{ $drName }} sebagai final?">
                                    <span wire:loading.remove wire:target="finalkan" class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                        Final
                                    </span>
                                    <span wire:loading wire:target="finalkan"><x-loading /> Mengunci...</span>
                                </x-primary-button>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </x-modal>
</div>
