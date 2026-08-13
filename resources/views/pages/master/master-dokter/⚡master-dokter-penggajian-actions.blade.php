<?php
// resources/views/pages/master/master-dokter/master-dokter-penggajian-actions.blade.php
//
// Struktur gaji per dokter — DIPISAH dari modal Ubah Data Dokter.
// Alasan pemisahan: dua panel itu dipakai orang & momen yang berbeda. Data
// dokter (nama, poli, NIK, UUID, tarif poli/UGD) disunting petugas master saat
// dokter baru masuk; parameter gaji disunting bagian keuangan dan hanya berubah
// saat kesepakatan gaji berubah. Menggabungkannya membuat satu simpan menulis
// kolom yang sebetulnya tidak sedang diubah.
//
// Kolom yang ditulis komponen ini SAJA:
//   basic_salary, npwp_status, npwp,
//   skema_gaji_pokok, potongan_rs_basis, potongan_rs_persen,
//   potongan_rs_aturan, pph21_persen, tarif_per_kapita_ri/rj,
//   tunjangan_struktural/fungsional/hadir,
//   potongan_idi/arisan/koperasi/angsuran/bpjs/zariyah
//
// Rumus yang memakainya ada di database/sql/2026_08_04_install_gaji_dokter.sql.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Support\GajiDokter\GajiDokter;

new class extends Component {
    public ?string $drId = null;
    public string $drName = '';
    public string $poliDesc = '';

    // Gaji pokok memakai kolom basic_salary yang sudah ada sejak lama — TIDAK
    // ada kolom kembarannya. Sengaja diletakkan di sini, bukan di modal data
    // dokter, karena nilainya baru bermakna dibaca bersama skema di bawah.
    public ?string $basicSalary = null;

    /**
     * Status NPWP — 'Y' punya, 'N' tidak. INI yang menentukan pajak: dokter
     * ber-status 'N' dikenai PPh 21 sebesar 20% lebih tinggi.
     */
    public string $npwpStatus = 'Y';

    /**
     * Nomor NPWP — dokumentasi saja, boleh menyusul dan TIDAK ikut menentukan
     * perhitungan. Sempat dipakai sebagai penentu status, tapi kolomnya lahir
     * kosong untuk semua dokter sehingga kekosongan itu berarti "belum didata",
     * bukan "tidak punya".
     */
    public ?string $npwp = null;

    public string $skemaGajiPokok = 'A';
    public string $potonganRsBasis = 'T';
    public ?string $potonganRsPersen = '10';
    /**
     * Aturan berjenjang disimpan sebagai JSON, tapi DIENTRI lewat form di bawah
     * (komponen + tipe + nilai). Tiga properti berikut hanya kotak ketik untuk
     * baris yang sedang ditambahkan, tidak ikut tersimpan ke database.
     */
    public ?string $potonganRsAturan = null;
    public string $aturanKode = '';
    public string $aturanTipe = 'P';
    public ?string $aturanNilai = '';
    public ?string $pph21Persen = '2.5';
    public ?string $tarifPerKapitaRi = '0';
    public ?string $tarifPerKapitaRj = '0';

    // Tunjangan rutin — KENA PAJAK. Di workbook lama ketiganya berada di dalam
    // penjumlahan pembentuk Total Gaji, jadi ikut menaikkan basis PPh. Sengaja
    // dipisah dari potongan rutin di bawah supaya tidak tertukar dengan
    // BONUS/RAPEL yang di slip ditambahkan SETELAH pajak.
    public ?string $tunjanganStruktural = '0';
    public ?string $tunjanganFungsional = '0';
    public ?string $tunjanganHadir = '0';

    public ?string $potonganIdi = '0';
    public ?string $potonganArisan = '0';
    public ?string $potonganKoperasi = '0';
    public ?string $potonganAngsuran = '0';
    public ?string $potonganBpjs = '0';
    public ?string $potonganZariyah = '0';

    /* ===============================
     | OPEN
     =============================== */
    #[On('master.dokter.openPenggajian')]
    public function openPenggajian(string $drId): void
    {
        $row = DB::table('rsmst_doctors as d')
            ->leftJoin('rsmst_polis as p', 'p.poli_id', '=', 'd.poli_id')
            ->where('d.dr_id', $drId)
            ->select('d.*', 'p.poli_desc')
            ->first();

        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data dokter tidak ditemukan.');
            return;
        }

        $this->fillFormFromRow($row);
        $this->resetValidation();
        $this->dispatch('open-modal', name: 'master-dokter-penggajian-actions');
    }

    /* ===============================
     | CLOSE
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'master-dokter-penggajian-actions');
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'basicSalary' => 'nullable|numeric|min:0',
            'npwpStatus' => 'required|in:Y,N',
            'npwp' => 'nullable|string|max:30',
            'skemaGajiPokok' => 'required|in:A,G,N',
            'potonganRsBasis' => 'required|in:T,J,N,B',
            'potonganRsPersen' => 'nullable|numeric|min:0|max:100',
            'potonganRsAturan' => 'nullable|string|max:1000',
            'pph21Persen' => 'nullable|numeric|min:0|max:100',
            'tarifPerKapitaRi' => 'nullable|numeric|min:0',
            'tarifPerKapitaRj' => 'nullable|numeric|min:0',
            'tunjanganStruktural' => 'nullable|numeric|min:0',
            'tunjanganFungsional' => 'nullable|numeric|min:0',
            'tunjanganHadir' => 'nullable|numeric|min:0',
            'potonganIdi' => 'nullable|numeric|min:0',
            'potonganArisan' => 'nullable|numeric|min:0',
            'potonganKoperasi' => 'nullable|numeric|min:0',
            'potonganAngsuran' => 'nullable|numeric|min:0',
            'potonganBpjs' => 'nullable|numeric|min:0',
            'potonganZariyah' => 'nullable|numeric|min:0',
        ];
    }

    protected function messages(): array
    {
        return [
            '*.required' => ':attribute wajib diisi.',
            '*.numeric' => ':attribute harus berupa angka.',
            '*.min' => ':attribute tidak boleh kurang dari :min.',
            '*.max' => ':attribute maksimal :max.',
            '*.in' => ':attribute tidak valid.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'basicSalary' => 'Gaji Pokok',
            'npwpStatus' => 'Status NPWP',
            'npwp' => 'Nomor NPWP',
            'skemaGajiPokok' => 'Skema Gaji Pokok',
            'potonganRsBasis' => 'Basis Potongan RS',
            'potonganRsPersen' => 'Potongan RS (%)',
            'potonganRsAturan' => 'Aturan Potongan Berjenjang',
            'pph21Persen' => 'PPh 21 (%)',
            'tarifPerKapitaRi' => 'Tarif per Pasien RI',
            'tarifPerKapitaRj' => 'Tarif per Pasien RJ',
            'tunjanganStruktural' => 'Tunjangan Struktural',
            'tunjanganFungsional' => 'Tunjangan Fungsional',
            'tunjanganHadir' => 'Tunjangan Kehadiran',
            'potonganIdi' => 'Potongan IDI',
            'potonganArisan' => 'Potongan Arisan',
            'potonganKoperasi' => 'Potongan Koperasi',
            'potonganAngsuran' => 'Potongan Angsuran',
            'potonganBpjs' => 'Potongan BPJS',
            'potonganZariyah' => 'Potongan Zariyah',
        ];
    }

    /* ===============================
     | REAKSI ANTAR FIELD
     =============================== */

    /**
     * Mematikan saklar NPWP ikut mengosongkan nomornya.
     *
     * Field nomornya disembunyikan saat saklar mati, dan menyembunyikan tanpa
     * mengosongkan akan meninggalkan nomor lama tersimpan diam-diam — persis
     * kombinasi (status 'N' + nomor terisi) yang disisir sebagai salah setel
     * di berkas pasang bagian 8.a. Dikosongkan di sini, bukan hanya saat
     * simpan, supaya yang tersimpan sama dengan yang terlihat.
     */
    public function updatedNpwpStatus(string $nilai): void
    {
        if ($nilai === 'N') {
            $this->npwp = null;
        }
    }

    /* ===============================
     | ATURAN BERJENJANG
     =============================== */
    /**
     * Aturan berjenjang + nama panjang tiap komponennya.
     *
     * Labelnya ditempelkan di sini, bukan dipanggil dari template: `use` pada
     * blok <?php Volt tidak menjangkau ekspresi {{ }} — keduanya dikompilasi
     * terpisah — sehingga memanggil GajiDokter dari template memaksa FQCN.
     */
    public function getAturanListProperty(): array
    {
        $aturan = GajiDokter::parseAturan($this->potonganRsAturan);

        foreach ($aturan as $kode => $item) {
            $aturan[$kode]['label'] = GajiDokter::labelKomponen($kode);
        }

        return $aturan;
    }

    /** Pilihan komponen jasa untuk form aturan berjenjang: [kode => label]. */
    public function pilihanKomponenJasa(): array
    {
        return GajiDokter::pilihanKomponenJasa();
    }

    public function tambahAturan(): void
    {
        $kode = trim($this->aturanKode);
        $nilai = str_replace(',', '.', trim((string) $this->aturanNilai));

        if ($kode === '') {
            $this->addError('aturanKode', 'Komponen wajib dipilih.');
            return;
        }

        if (!is_numeric($nilai) || (float) $nilai <= 0) {
            $this->addError('aturanNilai', 'Nilai harus angka lebih dari 0.');
            return;
        }

        if ($this->aturanTipe === 'P' && (float) $nilai > 100) {
            $this->addError('aturanNilai', 'Persen tidak boleh lebih dari 100. Untuk nominal tetap, pilih tipe Rupiah.');
            return;
        }

        // Satu komponen hanya masuk akal punya satu aturan — kode yang sudah ada
        // ditimpa, bukan digandakan.
        $aturan = $this->aturanList;
        $aturan[$kode] = ['tipe' => $this->aturanTipe, 'nilai' => (float) $nilai];

        // Belum menyentuh database: baru tersimpan saat tombol Simpan ditekan,
        // sama seperti field lain di form ini.
        $this->potonganRsAturan = GajiDokter::susunAturan($aturan);

        $this->aturanKode = '';
        $this->aturanTipe = 'P';
        $this->aturanNilai = '';
        $this->resetValidation(['aturanKode', 'aturanNilai', 'potonganRsAturan']);
    }

    public function hapusAturan(string $kode): void
    {
        $aturan = $this->aturanList;
        unset($aturan[$kode]);
        $this->potonganRsAturan = GajiDokter::susunAturan($aturan);
    }

    /* ===============================
     | SAVE
     =============================== */
    public function save(): void
    {
        if ($this->drId === null) {
            $this->dispatch('toast', type: 'error', message: 'Dokter belum dipilih.');
            return;
        }

        $data = $this->validate();

        // Aturan berjenjang divalidasi lewat GajiDokter — validator yang SAMA
        // dipakai form rincian slip, supaya format yang diterima di dua tempat
        // tidak pernah berbeda. Sengaja di sini, bukan di rules(), supaya
        // pesannya bisa menjelaskan formatnya.
        if ($data['potonganRsBasis'] === 'B') {
            $pesan = GajiDokter::validasiAturan($data['potonganRsAturan'] ?? null);

            if ($pesan !== null) {
                $this->addError('potonganRsAturan', $pesan);
                $this->dispatch('toast', type: 'error', message: $pesan);
                return;
            }
        }

        // Field kosong disimpan 0, bukan NULL — kolom-kolom ini nullable di
        // database (baris lama tidak ikut ter-backfill oleh DEFAULT saat ALTER),
        // dan NULL dalam aritmetika Oracle membuat seluruh hasil hitung gaji
        // jadi NULL secara senyap.
        $payload = [
            'basic_salary' => $data['basicSalary'] ?? 0,
            'npwp_status' => $data['npwpStatus'],
            // Dijaga dua kali (di sini dan di updatedNpwpStatus): field nomornya
            // TERSEMBUNYI saat status 'N', jadi kalau lolos ke sini nilainya tidak
            // akan pernah terlihat siapa pun untuk dikoreksi.
            'npwp' => $data['npwpStatus'] === 'N' ? null : (trim((string) ($data['npwp'] ?? '')) ?: null),
            'skema_gaji_pokok' => $data['skemaGajiPokok'],
            'potongan_rs_basis' => $data['potonganRsBasis'],
            'potongan_rs_persen' => $data['potonganRsPersen'] ?? 0,
            'potongan_rs_aturan' => $data['potonganRsBasis'] === 'B' ? $data['potonganRsAturan'] : null,
            'pph21_persen' => $data['pph21Persen'] ?? 0,
            'tarif_per_kapita_ri' => $data['tarifPerKapitaRi'] ?? 0,
            'tarif_per_kapita_rj' => $data['tarifPerKapitaRj'] ?? 0,
            'tunjangan_struktural' => $data['tunjanganStruktural'] ?? 0,
            'tunjangan_fungsional' => $data['tunjanganFungsional'] ?? 0,
            'tunjangan_hadir' => $data['tunjanganHadir'] ?? 0,
            'potongan_idi' => $data['potonganIdi'] ?? 0,
            'potongan_arisan' => $data['potonganArisan'] ?? 0,
            'potongan_koperasi' => $data['potonganKoperasi'] ?? 0,
            'potongan_angsuran' => $data['potonganAngsuran'] ?? 0,
            'potongan_bpjs' => $data['potonganBpjs'] ?? 0,
            'potongan_zariyah' => $data['potonganZariyah'] ?? 0,
        ];

        DB::table('rsmst_doctors')->where('dr_id', $this->drId)->update($payload);

        $this->dispatch('toast', type: 'success', message: 'Parameter penggajian berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.dokter.saved');
    }

    /* ===============================
     | HELPERS
     =============================== */
    protected function fillFormFromRow(object $row): void
    {
        $this->drId = (string) $row->dr_id;
        $this->drName = (string) ($row->dr_name ?? '');
        $this->poliDesc = (string) ($row->poli_desc ?? '-');

        $this->basicSalary = $row->basic_salary !== null ? (string) $row->basic_salary : '0';
        $this->npwpStatus = ($row->npwp_status ?? 'Y') === 'N' ? 'N' : 'Y';
        $this->npwp = $row->npwp;

        // Kolom-kolom ini ditambahkan belakangan lewat ALTER TABLE tanpa NOT
        // NULL, jadi SEMUA dokter lama masih NULL — di Oracle, DEFAULT pada
        // ALTER hanya berlaku untuk baris baru. Karena itu NULL di-fallback ke
        // default supaya form tidak tampil kosong dan nilainya ikut tersimpan
        // begitu dokter tsb disunting.
        $this->skemaGajiPokok = (string) ($row->skema_gaji_pokok ?? 'A');
        $this->potonganRsBasis = (string) ($row->potongan_rs_basis ?? 'T');
        $this->potonganRsPersen = (string) ($row->potongan_rs_persen ?? '10');
        $this->potonganRsAturan = $row->potongan_rs_aturan;
        $this->pph21Persen = (string) ($row->pph21_persen ?? '2.5');
        $this->tarifPerKapitaRi = (string) ($row->tarif_per_kapita_ri ?? '0');
        $this->tarifPerKapitaRj = (string) ($row->tarif_per_kapita_rj ?? '0');
        $this->tunjanganStruktural = (string) ($row->tunjangan_struktural ?? '0');
        $this->tunjanganFungsional = (string) ($row->tunjangan_fungsional ?? '0');
        $this->tunjanganHadir = (string) ($row->tunjangan_hadir ?? '0');
        $this->potonganIdi = (string) ($row->potongan_idi ?? '0');
        $this->potonganArisan = (string) ($row->potongan_arisan ?? '0');
        $this->potonganKoperasi = (string) ($row->potongan_koperasi ?? '0');
        $this->potonganAngsuran = (string) ($row->potongan_angsuran ?? '0');
        $this->potonganBpjs = (string) ($row->potongan_bpjs ?? '0');
        $this->potonganZariyah = (string) ($row->potongan_zariyah ?? '0');
    }
};
?>

<div>
    {{-- size/height "full" — seragam dengan modal Ubah Data Dokter. Panel gaji
         punya grid sampai 6 kolom (potongan rutin), jadi butuh lebar penuh. --}}
    <x-modal name="master-dokter-penggajian-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-dokter-penggajian-actions"
            event="master.dokter.saved"
            label="Parameter Penggajian"
            :wireKey="'master-dokter-penggajian-actions-' . ($drId ?? 'none')">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 bg-surface-soft">
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="ds-display-sm dark:text-gray-100">Struktur Gaji</h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    Dipakai saat memproses slip gaji dokter.
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 mt-3">
                            <x-badge variant="warning">{{ $drId ?? '-' }}</x-badge>
                            <span class="text-sm font-semibold dark:text-gray-100">{{ $drName ?: '-' }}</span>
                            <span class="text-sm text-muted dark:text-gray-400">&middot; {{ $poliDesc ?: '-' }}</span>
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" x-on:click="tryClose()">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

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
                                <span class="truncate">Panduan: cara menyetel struktur gaji</span>
                            </span>
                            <svg class="w-4 h-4 ml-2 text-blue-600 transition-transform shrink-0"
                                x-bind:class="buka && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div x-show="buka" x-cloak class="px-4 pb-4 space-y-4 text-sm text-blue-900 dark:text-blue-100">

                            <div>
                                <div class="font-semibold">Isi berurutan dari atas</div>
                                <ol class="mt-1 ml-4 space-y-1 list-decimal">
                                    <li><span class="font-semibold">Gaji Pokok</span> &mdash; nominal tetap per bulan.
                                        Isi 0 bila dokter murni dibayar dari jasa.</li>
                                    <li><span class="font-semibold">Status NPWP</span> &mdash; saklar punya / tidak punya.
                                        Hanya saklar inilah yang menentukan pajak: dimatikan berarti tidak ber-NPWP dan
                                        PPh 21-nya otomatis dipotong 20% lebih tinggi (UU PPh Pasal 21 ayat 5a).
                                        Bawaannya <span class="font-semibold">menyala</span> &mdash; matikan hanya untuk
                                        dokter yang memang tidak ber-NPWP.</li>
                                    <li><span class="font-semibold">Nomor NPWP</span> &mdash; opsional, boleh menyusul.
                                        Sifatnya arsip dan <span class="font-semibold">tidak ikut menentukan hitungan</span>,
                                        jadi saklar menyala dengan nomor masih kosong itu wajar selama pendataan berjalan.</li>
                                    <li><span class="font-semibold">Skema Gaji Pokok</span> &mdash; bagaimana gaji pokok
                                        digabung dengan jasa.</li>
                                    <li><span class="font-semibold">Basis Potongan RS</span> &mdash; dari apa potongan
                                        rumah sakit dihitung.</li>
                                    <li><span class="font-semibold">Persen</span> potongan RS &amp; PPh 21.</li>
                                    <li><span class="font-semibold">Tarif per pasien</span>, tunjangan, dan potongan rutin
                                        &mdash; isi 0 bila tidak dipakai.</li>
                                </ol>
                            </div>

                            <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                                <div class="font-semibold">Memilih Skema Gaji Pokok</div>
                                <table class="w-full mt-1 text-sm text-left">
                                    <tbody class="align-top">
                                        <tr><td class="py-0.5 pr-3 font-mono">A</td>
                                            <td class="py-0.5"><span class="font-semibold">Aditif</span> &mdash; jasa
                                                <em>ditambah</em> gaji pokok. Paling umum.</td></tr>
                                        <tr><td class="py-0.5 pr-3 font-mono">G</td>
                                            <td class="py-0.5"><span class="font-semibold">Garanty fee</span> &mdash; dibayar
                                                yang <em>terbesar</em> antara jasa dan gaji pokok. Gaji pokok jadi jaminan
                                                minimum, bukan tambahan.</td></tr>
                                        <tr><td class="py-0.5 pr-3 font-mono">N</td>
                                            <td class="py-0.5"><span class="font-semibold">Tanpa gaji pokok</span> &mdash;
                                                murni dari jasa.</td></tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                                <div class="font-semibold">Memilih Basis Potongan RS</div>
                                <table class="w-full mt-1 text-sm text-left">
                                    <tbody class="align-top">
                                        <tr><td class="py-0.5 pr-3 font-mono">T</td>
                                            <td class="py-0.5">Dari <span class="font-semibold">total gaji</span> &mdash;
                                                gaji pokok ikut dipotong.</td></tr>
                                        <tr><td class="py-0.5 pr-3 font-mono">J</td>
                                            <td class="py-0.5">Dari <span class="font-semibold">jasa saja</span> &mdash;
                                                gaji pokok bebas potongan.</td></tr>
                                        <tr><td class="py-0.5 pr-3 font-mono">N</td>
                                            <td class="py-0.5"><span class="font-semibold">Tidak dipotong</span> sama sekali.</td></tr>
                                        <tr><td class="py-0.5 pr-3 font-mono">B</td>
                                            <td class="py-0.5"><span class="font-semibold">Berjenjang</span> &mdash; persen
                                                atau nominal berbeda per komponen jasa. Aturannya diisi di tabel yang muncul
                                                setelah basis ini dipilih.</td></tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                                <div class="font-semibold">Tarif per Pasien &mdash; beda urusan dengan Skema Gaji Pokok</div>
                                <p class="mt-1">
                                    Keduanya sering tertukar karena sama-sama terdengar seperti &ldquo;cara dokter
                                    dibayar&rdquo;. Padahal keduanya mengatur bagian yang berbeda dan
                                    <span class="font-semibold">boleh menyala bersamaan</span>:
                                </p>
                                <table class="w-full mt-2">
                                    <tbody class="align-top">
                                        <tr>
                                            <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">Skema Gaji Pokok</td>
                                            <td class="py-0.5">mengatur bagian <span class="font-semibold">tetap</span>
                                                &mdash; bagaimana gaji pokok digabung dengan jasa.</td>
                                        </tr>
                                        <tr>
                                            <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">Tarif per Pasien</td>
                                            <td class="py-0.5">mengatur bagian <span class="font-semibold">jasa</span>
                                                &mdash; bagaimana jasa satu jalur dihitung.</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <p class="mt-2">
                                    Yang wajib disadari: Tarif per Pasien <span class="font-semibold">MENGGANTI</span>,
                                    bukan menambah. Begitu diisi di atas 0, komponen jasa jalur itu dibuang dan diganti
                                    satu baris <span class="font-mono">jumlah pasien &times; tarif</span>:
                                </p>
                                <table class="w-full mt-2">
                                    <tbody class="align-top">
                                        <tr>
                                            <td class="py-0.5 pr-3 font-mono whitespace-nowrap">RI</td>
                                            <td class="py-0.5">membuang VISIT, KONSUL, JD RI</td>
                                        </tr>
                                        <tr>
                                            <td class="py-0.5 pr-3 font-mono whitespace-nowrap">RJ</td>
                                            <td class="py-0.5">membuang UP RJ, JD RJ</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <p class="mt-2">
                                    Jalur lain tidak tersentuh &mdash; OK, radiologi, klinik, UGD, dan tarif transfer
                                    tetap dihitung per komponen. Jadi mengisinya karena mengira menambah penghasilan
                                    justru <span class="font-semibold">menghapus</span> jasa jalur tsb.
                                </p>
                                <p class="mt-2">
                                    Contoh nyata: dr. Kristina SpPK dibayar
                                    <span class="font-mono">40.000</span>/pasien RI, dr. Bambang SpKFR
                                    <span class="font-mono">65.000</span>/kunjungan RJ. Kalau angkanya berubah,
                                    ganti di sini &mdash; jangan diakali lewat baris manual di rincian slip, karena
                                    baris itu akan tertimpa setiap slip diproses ulang.
                                </p>
                                <p class="mt-2">
                                    Bila jumlah pasiennya tidak ditemukan saat Proses Slip, slip tetap dibuat dengan
                                    nilai 0 dan namanya muncul di peringatan &mdash; isi jumlahnya lewat Rincian slip.
                                </p>
                            </div>

                            <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                                <div class="font-semibold">PPh 21 &mdash; kapan perlu diubah</div>
                                <p class="mt-1">
                                    Biarkan <span class="font-mono">2.5</span> untuk hampir semua dokter. Angka itu berasal
                                    dari NPPN 50% &times; tarif Pasal 17 lapis pertama 5%. Dokter dihitung
                                    sebagai <span class="font-semibold">bukan pegawai</span>, jadi yang dikenai pajak hanya
                                    setengah dari bruto. Tambahan 20% untuk yang tidak ber-NPWP
                                    <span class="font-semibold">tidak perlu diketik</span>; sistem menghitungnya sendiri
                                    dari saklar Status NPWP.
                                </p>

                                {{-- Daftar istilah ditaruh SEBELUM tabel-tabelnya, bukan di
                                     akhir seksi: singkatan pajak dipakai padat di bawah, dan
                                     pembaca yang belum tahu artinya akan berhenti di tabel
                                     pertama. --}}
                                <div class="mt-3 font-semibold">Istilah pajak yang dipakai di bawah</div>
                                <div class="mt-2 overflow-x-auto">
                                    <table class="w-full">
                                        <tbody class="align-top">
                                            <tr>
                                                <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">PPh 21</td>
                                                <td class="py-0.5"><span class="font-semibold">Pajak Penghasilan Pasal
                                                    21</span> &mdash; pajak atas penghasilan orang pribadi, dipotong oleh
                                                    pihak yang membayar. Di sini RS yang memotong dan menyetorkannya.</td>
                                            </tr>
                                            <tr>
                                                <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">Bruto</td>
                                                <td class="py-0.5">Penghasilan <span class="font-semibold">sebelum
                                                    dipotong apa pun</span>. Di slip ini sama dengan
                                                    <span class="font-semibold">Total Gaji</span> &mdash; jasa +
                                                    tunjangan + gaji pokok.</td>
                                            </tr>
                                            <tr>
                                                <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">Neto</td>
                                                <td class="py-0.5">Penghasilan bersih &mdash; bruto dikurangi biaya
                                                    memperolehnya. Untuk dokter, biaya itu tidak dihitung satu per satu
                                                    melainkan dipukul rata 50% lewat NPPN di bawah.</td>
                                            </tr>
                                            <tr>
                                                <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">NPPN</td>
                                                <td class="py-0.5"><span class="font-semibold">Norma Penghitungan
                                                    Penghasilan Neto</span> &mdash; persentase baku dari pemerintah untuk
                                                    menaksir penghasilan neto tanpa pembukuan. Untuk tenaga ahli termasuk
                                                    dokter besarnya <span class="font-mono">50%</span>.</td>
                                            </tr>
                                            <tr>
                                                <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">DPP</td>
                                                <td class="py-0.5"><span class="font-semibold">Dasar Pengenaan
                                                    Pajak</span> &mdash; angka yang benar-benar dikalikan tarif. Untuk
                                                    dokter <span class="font-mono">DPP = 50% &times; bruto</span>. Inilah
                                                    sebabnya tarif 5% terasa jadi 2,5% atas bruto.</td>
                                            </tr>
                                            <tr>
                                                <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">Pasal 17</td>
                                                <td class="py-0.5">Pasal di UU Pajak Penghasilan yang memuat
                                                    <span class="font-semibold">lapisan tarif</span> 5% / 15% / 25% /
                                                    30% / 35%.</td>
                                            </tr>
                                            <tr>
                                                <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">UU HPP</td>
                                                <td class="py-0.5"><span class="font-semibold">UU Harmonisasi Peraturan
                                                    Perpajakan</span> (UU 7/2021) &mdash; aturan yang menetapkan batas
                                                    lapisan yang dipakai tabel di bawah.</td>
                                            </tr>
                                            <tr>
                                                <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">Bukan pegawai</td>
                                                <td class="py-0.5">Status dokter menurut aturan PPh 21 &mdash; menerima
                                                    imbalan atas jasa, bukan gaji karyawan. Status inilah yang membuat
                                                    NPPN 50% berlaku. Diatur di
                                                    <span class="font-semibold">PMK 168/2023</span>.</td>
                                            </tr>
                                            <tr>
                                                <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">NPWP</td>
                                                <td class="py-0.5"><span class="font-semibold">Nomor Pokok Wajib
                                                    Pajak</span> &mdash; nomor identitas pajak. Yang tidak punya dikenai
                                                    tarif 20% lebih tinggi.</td>
                                            </tr>
                                            <tr>
                                                <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">Tarif marginal</td>
                                                <td class="py-0.5">Tarif yang berlaku untuk <span class="font-semibold">irisan
                                                    penghasilan di satu lapisan saja</span> &mdash; bukan untuk seluruh
                                                    penghasilan. Angka di tabel lapisan bersifat marginal.</td>
                                            </tr>
                                            <tr>
                                                <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">Tarif rata-rata</td>
                                                <td class="py-0.5">Pajak seluruhnya dibagi bruto. <span class="font-semibold">Ini
                                                    yang diketik</span> di kolom PPh 21 (%), sebab kolom itu mengalikan
                                                    satu angka ke seluruh nilai.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-3 font-semibold">Lapisan tarif Pasal 17 (UU HPP)</div>
                                <p class="mt-1">
                                    Batas resminya dinyatakan atas DPP. Karena DPP di sini
                                    separuh bruto, batas itu ditulis ulang jadi <span class="font-semibold">bruto
                                    sebulan</span> supaya bisa langsung dibandingkan dengan Total Gaji di slip:
                                </p>
                                <div class="mt-2 overflow-x-auto">
                                    <table class="w-full">
                                        <thead>
                                            <tr class="text-left border-b border-blue-200 dark:border-blue-800">
                                                <th class="py-1 pr-3 font-semibold whitespace-nowrap">Bruto sebulan</th>
                                                <th class="py-1 pr-3 font-semibold whitespace-nowrap">Tarif Pasal 17</th>
                                                <th class="py-1 font-semibold whitespace-nowrap">Atas bruto</th>
                                            </tr>
                                        </thead>
                                        <tbody class="align-top">
                                            <tr>
                                                <td class="py-0.5 pr-3 whitespace-nowrap">s/d 120 juta</td>
                                                <td class="py-0.5 pr-3 font-mono">5%</td>
                                                <td class="py-0.5 font-mono">2,5%</td>
                                            </tr>
                                            <tr>
                                                <td class="py-0.5 pr-3 whitespace-nowrap">120 juta &ndash; 500 juta</td>
                                                <td class="py-0.5 pr-3 font-mono">15%</td>
                                                <td class="py-0.5 font-mono">7,5%</td>
                                            </tr>
                                            <tr>
                                                <td class="py-0.5 pr-3 whitespace-nowrap">500 juta &ndash; 1 miliar</td>
                                                <td class="py-0.5 pr-3 font-mono">25%</td>
                                                <td class="py-0.5 font-mono">12,5%</td>
                                            </tr>
                                            <tr>
                                                <td class="py-0.5 pr-3 whitespace-nowrap">1 miliar &ndash; 10 miliar</td>
                                                <td class="py-0.5 pr-3 font-mono">30%</td>
                                                <td class="py-0.5 font-mono">15%</td>
                                            </tr>
                                            <tr>
                                                <td class="py-0.5 pr-3 whitespace-nowrap">di atas 10 miliar</td>
                                                <td class="py-0.5 pr-3 font-mono">35%</td>
                                                <td class="py-0.5 font-mono">17,5%</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                {{-- Bagian paling penting dari seksi ini. Angka di kolom kanan tabel
                                     atas adalah tarif MARGINAL — hanya berlaku untuk irisan
                                     penghasilan di lapisan itu. Kolom PPh 21 (%) mengalikan SELURUH
                                     nilai dengan satu angka, jadi menyalin tarif marginal ke sana
                                     memotong jauh lebih besar dari yang seharusnya. --}}
                                <div class="mt-3 font-semibold">Yang diketik tarif rata-rata, bukan tarif marginal</div>
                                <p class="mt-1">
                                    Kolom <span class="font-semibold">PPh 21 (%)</span> mengalikan seluruh nilai dengan
                                    satu angka, sedangkan pajak sebenarnya progresif &mdash; tiap tarif hanya mengenai
                                    irisannya sendiri. Angka di tabel lapisan itu
                                    <span class="font-semibold">tarif marginal</span>, jadi menyalinnya apa adanya
                                    <span class="font-semibold">memotong terlalu besar</span>. Contoh pada bruto
                                    <span class="font-mono">200 juta</span>: pajak sebenarnya
                                    <span class="font-mono">9 juta</span>, tapi <span class="font-mono">7.5</span>
                                    menghasilkan <span class="font-mono">15 juta</span> &mdash; dokter kelebihan potong
                                    <span class="font-semibold">Rp6 juta sebulan</span>.
                                </p>
                                <p class="mt-2">Isi dengan angka di kolom terakhir tabel ini:</p>
                                <div class="mt-2 overflow-x-auto">
                                    <table class="w-full">
                                        <thead>
                                            <tr class="text-left border-b border-blue-200 dark:border-blue-800">
                                                <th class="py-1 pr-3 font-semibold whitespace-nowrap">Bruto sebulan</th>
                                                <th class="py-1 pr-3 font-semibold whitespace-nowrap">Pajak sebenarnya</th>
                                                <th class="py-1 font-semibold whitespace-nowrap">Ketik</th>
                                            </tr>
                                        </thead>
                                        <tbody class="align-top">
                                            <tr>
                                                <td class="py-0.5 pr-3 whitespace-nowrap">s/d 120 juta</td>
                                                <td class="py-0.5 pr-3 whitespace-nowrap">2,5% &times; bruto</td>
                                                <td class="py-0.5 font-mono font-semibold">2.5</td>
                                            </tr>
                                            <tr>
                                                <td class="py-0.5 pr-3 whitespace-nowrap">150 juta</td>
                                                <td class="py-0.5 pr-3 whitespace-nowrap">5.250.000</td>
                                                <td class="py-0.5 font-mono font-semibold">3.5</td>
                                            </tr>
                                            <tr>
                                                <td class="py-0.5 pr-3 whitespace-nowrap">200 juta</td>
                                                <td class="py-0.5 pr-3 whitespace-nowrap">9.000.000</td>
                                                <td class="py-0.5 font-mono font-semibold">4.5</td>
                                            </tr>
                                            <tr>
                                                <td class="py-0.5 pr-3 whitespace-nowrap">300 juta</td>
                                                <td class="py-0.5 pr-3 whitespace-nowrap">16.500.000</td>
                                                <td class="py-0.5 font-mono font-semibold">5.5</td>
                                            </tr>
                                            <tr>
                                                <td class="py-0.5 pr-3 whitespace-nowrap">500 juta</td>
                                                <td class="py-0.5 pr-3 whitespace-nowrap">31.500.000</td>
                                                <td class="py-0.5 font-mono font-semibold">6.3</td>
                                            </tr>
                                            <tr>
                                                <td class="py-0.5 pr-3 whitespace-nowrap">1 miliar</td>
                                                <td class="py-0.5 pr-3 whitespace-nowrap">94.000.000</td>
                                                <td class="py-0.5 font-mono font-semibold">9.4</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p class="mt-2">
                                    Di antara baris itu, hitung sendiri:
                                    <span class="font-mono">pajak &divide; bruto &times; 100</span>. Pajaknya
                                    <span class="font-mono">5% &times; 60 juta</span> untuk irisan pertama, lalu
                                    <span class="font-mono">15%</span> atas sisa DPP di atasnya &mdash; ingat
                                    DPP-nya separuh bruto.
                                </p>
                                <p class="mt-2">
                                    Kondisi sekarang: bruto tertinggi yang pernah tercatat
                                    <span class="font-mono">67.063.061</span> sebulan, masih jauh di bawah 120 juta.
                                    Jadi <span class="font-mono">2.5</span> benar untuk
                                    <span class="font-semibold">semua dokter</span> saat ini &mdash; tabel di atas
                                    baru terpakai kalau ada yang melewatinya.
                                </p>
                            </div>

                            <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
                                <div class="font-semibold">Setelah disimpan</div>
                                <p class="mt-1">
                                    Setelan di sini adalah <span class="font-semibold">nilai baku</span>. Slip yang sudah
                                    terbentuk TIDAK ikut berubah &mdash; parameternya disalin saat slip diproses. Supaya
                                    berlaku, buka <span class="font-semibold">Manajemen &rarr; Monitoring Keuangan &rarr;
                                    Slip Gaji Dokter</span>, lalu tekan <span class="font-semibold">Proses Slip</span> pada
                                    periode yang dimaksud. Untuk koreksi satu periode saja, sunting langsung di Rincian
                                    slipnya.
                                </p>
                            </div>

                        </div>
                    </div>

                    {{-- ── Gaji pokok & skema ── --}}
                    <x-border-form title="Gaji Pokok & Skema">
                        <div class="space-y-4">

                            {{-- Satu baris, 12 kolom. Lebar per field:
                                 select & nominal 2 — dua persen 1.
                                 Teks bantuan sengaja dipendekkan; di kolom sempit
                                 kalimat panjang bikin tinggi tiap sel jomplang.

                                 URUTANNYA DISENGAJA: tiap field pengendali diikuti
                                 langsung oleh field yang bergantung padanya —
                                 Skema lalu Gaji Pokok, Status NPWP lalu Nomor NPWP.
                                 Yang bergantung DISEMBUNYIKAN saat tidak relevan,
                                 jadi 12 kolom itu jumlah maksimum, bukan selalu.
                                 Kalau urutannya tidak berdekatan, mengubah select di
                                 tengah baris akan memunculkan field di ujung lain dan
                                 menggeser semuanya — terbaca seperti layar berkedip.

                                 Kedua field persen memakai :decimals="2" — tanpa itu
                                 x-text-input-number membuang titik desimal dan "2.5"
                                 tersimpan jadi 25. Kolomnya NUMBER(5,2) dan tarif PPh
                                 default memang 2,5. --}}
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-12">
                                <div class="lg:col-span-2">
                                    <x-input-label value="Skema Gaji Pokok *" class="mb-1" />
                                    <x-select-input wire:model.live="skemaGajiPokok"
                                        :error="$errors->has('skemaGajiPokok')">
                                        <option value="A">A — Aditif (jasa + gaji pokok)</option>
                                        <option value="G">G — Garanty fee (yang terbesar antara jasa & gaji pokok)</option>
                                        <option value="N">N — Tanpa gaji pokok</option>
                                    </x-select-input>
                                    <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                        Label <span class="font-semibold">Garanty Fee</span> di Excel lama belum tentu
                                        skema <span class="font-mono">G</span> — periksa rumusnya.
                                    </p>
                                    <x-input-error :messages="$errors->get('skemaGajiPokok')" class="mt-1" />
                                </div>

                                {{-- Nominalnya hanya muncul bila skemanya memang memakai gaji
                                     pokok. Pada skema 'N' angka ini diabaikan mesin hitung,
                                     dan field yang ditampilkan tapi tidak berpengaruh justru
                                     mengundang orang mengisinya lalu bingung kenapa slipnya
                                     tidak berubah. Letaknya TEPAT SESUDAH skema supaya
                                     munculnya terbaca sebagai lanjutan pilihan barusan. --}}
                                @if ($skemaGajiPokok !== 'N')
                                    <div class="lg:col-span-2">
                                        <x-input-label value="Gaji Pokok" class="mb-1" />
                                        <x-text-input-number wire:model="basicSalary" x-ref="inputBasicSalary"
                                            :error="$errors->has('basicSalary')" class="w-full"
                                            x-on:keydown.enter.prevent="$refs.inputPotonganRsPersen?.focus()" />
                                        <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                            @if ($skemaGajiPokok === 'G')
                                                Jaminan minimum &mdash; dibayar bila jasa lebih kecil.
                                            @else
                                                2,5–10 juta, beda tiap dokter.
                                            @endif
                                        </p>
                                        <x-input-error :messages="$errors->get('basicSalary')" class="mt-1" />
                                    </div>
                                @endif

                                {{-- Status NPWP = TOGGLE, bukan disimpulkan dari terisinya
                                     nomor. Nomornya kerap belum didata padahal dokternya
                                     ber-NPWP; menyimpulkan dari kolom kosong akan menaikkan
                                     pajak 20% tanpa dasar. --}}
                                <div class="lg:col-span-2">
                                    <x-input-label value="Status NPWP *" class="mb-1" />
                                    <div class="flex items-center h-[42px]">
                                        <x-toggle wire:model.live="npwpStatus" trueValue="Y" falseValue="N"
                                            :label="$npwpStatus === 'Y' ? 'Ber-NPWP' : 'Tidak ber-NPWP'" />
                                    </div>
                                    @if ($npwpStatus === 'N')
                                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">
                                            PPh 21 dinaikkan 20%. Nomor dikosongkan.
                                        </p>
                                    @else
                                        <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                            PPh 21 tarif normal.
                                        </p>
                                    @endif
                                    <x-input-error :messages="$errors->get('npwpStatus')" class="mt-1" />
                                </div>

                                {{-- Nomornya dokumentasi saja — boleh menyusul dan tidak pernah
                                     ikut menentukan perhitungan. Disembunyikan saat saklarnya
                                     mati: dokter tanpa NPWP tidak punya nomor untuk diisi, dan
                                     kombinasi status 'N' + nomor terisi justru yang disisir
                                     sebagai salah setel (lihat berkas pasang bagian 8.a). --}}
                                @if ($npwpStatus === 'Y')
                                    <div class="lg:col-span-2">
                                        <x-input-label value="Nomor NPWP" class="mb-1" />
                                        <x-text-input wire:model.blur="npwp" class="w-full"
                                            :error="$errors->has('npwp')" maxlength="30"
                                            placeholder="09.254.294.3-407.000" />
                                        <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                            Opsional &mdash; boleh menyusul.
                                        </p>
                                        <x-input-error :messages="$errors->get('npwp')" class="mt-1" />
                                    </div>
                                @endif

                                <div class="lg:col-span-2">
                                    <x-input-label value="Basis Potongan RS *" class="mb-1" />
                                    <x-select-input wire:model.live="potonganRsBasis"
                                        :error="$errors->has('potonganRsBasis')">
                                        <option value="T">T — Total gaji (gaji pokok ikut dipotong)</option>
                                        <option value="J">J — Jasa saja (gaji pokok bebas potongan)</option>
                                        <option value="N">N — Tidak dipotong</option>
                                        <option value="B">B — Berjenjang per komponen</option>
                                    </x-select-input>
                                    <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                        Lepas dari skema — dokter garanty fee pun umumnya
                                        <span class="font-mono">T</span>.
                                    </p>
                                    <x-input-error :messages="$errors->get('potonganRsBasis')" class="mt-1" />
                                </div>

                                <div class="lg:col-span-1">
                                    <x-input-label value="Potongan RS (%)" class="mb-1" />
                                    <x-text-input-number wire:model="potonganRsPersen" x-ref="inputPotonganRsPersen"
                                        :decimals="2"
                                        :disabled="$potonganRsBasis === 'N' || $potonganRsBasis === 'B'"
                                        :error="$errors->has('potonganRsPersen')" class="w-full"
                                        x-on:keydown.enter.prevent="$refs.inputPph21Persen?.focus()" />
                                    <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                        Titik untuk desimal, mis. <span class="font-mono">7.5</span>.
                                    </p>
                                    <x-input-error :messages="$errors->get('potonganRsPersen')" class="mt-1" />
                                </div>

                                <div class="lg:col-span-1">
                                    <x-input-label value="PPh 21 (%)" class="mb-1" />
                                    <x-text-input-number wire:model="pph21Persen" x-ref="inputPph21Persen"
                                        :decimals="2"
                                        :error="$errors->has('pph21Persen')" class="w-full"
                                        x-on:keydown.enter.prevent="$refs.inputTarifPerKapitaRi?.focus()" />
                                    <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                        Default <span class="font-mono">2.5</span>. Basis: total dikurangi potongan RS.
                                    </p>
                                    <x-input-error :messages="$errors->get('pph21Persen')" class="mt-1" />
                                </div>
                            </div>

                            {{-- Aturan berjenjang — hanya relevan untuk basis 'B'.
                                 Entri lewat form, bukan ketik JSON. --}}
                            @if ($potonganRsBasis === 'B')
                                <div class="pt-2 space-y-3 border-t border-hairline dark:border-gray-800">
                                    <div>
                                        <p class="text-xs font-semibold text-muted uppercase tracking-wide">
                                            Aturan Potongan Berjenjang
                                        </p>
                                        <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                            Potongan dihitung per komponen jasa. Komponen yang tidak terdaftar
                                            di bawah tidak dipotong sama sekali.
                                        </p>
                                    </div>

                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                                        <div class="md:col-span-5">
                                            <x-input-label value="Komponen" class="mb-1" />
                                            <x-select-input wire:model="aturanKode" :error="$errors->has('aturanKode')">
                                                <option value="">— pilih komponen —</option>
                                                {{-- Nilai tersimpan tetap kodenya (jadi kunci JSON aturan);
                                                     yang dibaca petugas nama panjangnya. --}}
                                                @foreach ($this->pilihanKomponenJasa() as $kode => $label)
                                                    <option value="{{ $kode }}">{{ $label }}</option>
                                                @endforeach
                                            </x-select-input>
                                            <x-input-error :messages="$errors->get('aturanKode')" class="mt-1" />
                                        </div>

                                        <div class="md:col-span-3">
                                            <x-input-label value="Tipe" class="mb-1" />
                                            <x-select-input wire:model.live="aturanTipe">
                                                <option value="P">Persen (%)</option>
                                                <option value="N">Nominal (Rp)</option>
                                            </x-select-input>
                                        </div>

                                        <div class="md:col-span-3">
                                            <x-input-label :value="$aturanTipe === 'N' ? 'Nominal' : 'Persen'" class="mb-1" />
                                            {{-- Persen memakai x-text-input karena butuh desimal (7.5);
                                                 x-text-input-number integer-only sehingga 7.5 jadi 75. --}}
                                            @if ($aturanTipe === 'N')
                                                <x-text-input-number wire:model="aturanNilai" class="w-full"
                                                    :error="$errors->has('aturanNilai')"
                                                    x-on:keydown.enter.prevent="$el.blur(); $wire.tambahAturan()" />
                                            @else
                                                <x-text-input wire:model="aturanNilai" inputmode="decimal"
                                                    class="w-full text-right" :error="$errors->has('aturanNilai')"
                                                    placeholder="10" maxlength="6"
                                                    x-on:keydown.enter.prevent="$el.blur(); $wire.tambahAturan()" />
                                            @endif
                                            <x-input-error :messages="$errors->get('aturanNilai')" class="mt-1" />
                                        </div>

                                        <div class="flex items-end md:col-span-1">
                                            <x-secondary-button type="button" wire:click="tambahAturan" class="w-full">
                                                Tambah
                                            </x-secondary-button>
                                        </div>
                                    </div>

                                    @if ($aturanTipe === 'P')
                                        <p class="-mt-2 text-xs text-muted dark:text-gray-400">
                                            Pecahan persen memakai titik, mis. <span class="font-mono">7.5</span>.
                                        </p>
                                    @endif

                                    <div class="overflow-x-auto">
                                        <table class="w-full min-w-full text-sm border-separate border-spacing-y-2 table-fixed">
                                            <thead>
                                                <tr class="text-xs font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                                    <th class="px-4 py-2 w-[45%] bg-surface-card dark:bg-gray-800">Komponen</th>
                                                    <th class="px-4 py-2 w-[20%] bg-surface-card dark:bg-gray-800">Tipe</th>
                                                    <th class="px-4 py-2 w-[25%] text-right bg-surface-card dark:bg-gray-800">Potongan</th>
                                                    <th class="px-4 py-2 w-[10%] text-center bg-surface-card dark:bg-gray-800"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($this->aturanList as $kode => $item)
                                                    <tr class="rounded-2xl shadow-sm ring-1 ring-hairline bg-canvas dark:bg-gray-900 dark:ring-gray-700">
                                                        <td class="px-4 py-2 align-middle" title="Kode: {{ $kode }}">
                                                            {{ $item['label'] }}
                                                        </td>
                                                        <td class="px-4 py-2 align-middle">
                                                            {{ $item['tipe'] === 'N' ? 'Nominal' : 'Persen' }}
                                                        </td>
                                                        <td class="px-4 py-2 text-right align-middle">
                                                            {{ $item['tipe'] === 'N'
                                                                ? 'Rp ' . number_format($item['nilai'], 0, ',', '.')
                                                                : rtrim(rtrim(number_format($item['nilai'], 2, ',', '.'), '0'), ',') . '%' }}
                                                        </td>
                                                        <td class="px-4 py-2 text-center align-middle">
                                                            {{-- Tombol hapus baku (pola eresep RJ): outline merah + ikon
                                                                 tempat sampah, bukan teks. --}}
                                                            <x-outline-button type="button"
                                                                wire:click="hapusAturan('{{ $kode }}')"
                                                                class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                                title="Hapus aturan {{ $kode }}">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
                                                            </x-outline-button>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="4" class="px-4 py-4 text-center text-muted dark:text-gray-400">
                                                            Belum ada aturan.
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>

                                    <x-input-error :messages="$errors->get('potonganRsAturan')" class="mt-1" />
                                </div>
                            @endif

                        </div>
                    </x-border-form>

                    {{-- Tarif per pasien & tunjangan sebaris — keduanya hanya berisi 2–3
                         input pendek, jadi mubazir kalau masing-masing makan satu baris
                         penuh. Turun menumpuk lagi di bawah lg. --}}
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

                    {{-- ── Tarif per pasien ── --}}
                    <x-border-form title="Tarif per Pasien">
                        <div class="space-y-4">
                            {{-- Ini BUKAN skema gaji dan bukan tambahan. Mengisinya MENGGANTI
                                 cara jasa jalur itu dihitung: komponen aslinya dibuang, diganti
                                 satu baris jumlah pasien x tarif. Sifat mengganti inilah yang
                                 paling mudah disalahpahami, jadi ditulis paling depan. --}}
                            <p class="text-xs text-muted dark:text-gray-400">
                                <span class="font-semibold text-ink dark:text-gray-100">Mengganti</span>, bukan
                                menambah. Untuk dokter yang dibayar per kepala alih-alih per komponen jasa
                                (mis. Patologi Klinik, Rehabilitasi Medik) &mdash; jasa jalur tsb dihitung
                                <span class="font-mono">jumlah pasien &times; tarif</span>. Isi
                                <span class="font-mono">0</span> untuk memakai perhitungan komponen biasa.
                            </p>

                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Tarif per Pasien RI" class="mb-1" />
                                    <x-text-input-number wire:model="tarifPerKapitaRi" x-ref="inputTarifPerKapitaRi"
                                        :error="$errors->has('tarifPerKapitaRi')" class="w-full"
                                        x-on:keydown.enter.prevent="$refs.inputTarifPerKapitaRj?.focus()" />
                                    @if ((float) $tarifPerKapitaRi > 0)
                                        <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                                            VISIT, KONSUL, dan JD RI <span class="font-semibold">tidak dihitung</span>.
                                        </p>
                                    @else
                                        <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                            Tidak dipakai &mdash; VISIT, KONSUL, JD RI dihitung normal.
                                        </p>
                                    @endif
                                    <x-input-error :messages="$errors->get('tarifPerKapitaRi')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Tarif per Pasien RJ" class="mb-1" />
                                    <x-text-input-number wire:model="tarifPerKapitaRj" x-ref="inputTarifPerKapitaRj"
                                        :error="$errors->has('tarifPerKapitaRj')" class="w-full"
                                        x-on:keydown.enter.prevent="$refs.inputTunjanganStruktural?.focus()" />
                                    @if ((float) $tarifPerKapitaRj > 0)
                                        <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                                            UP RJ dan JD RJ <span class="font-semibold">tidak dihitung</span>.
                                        </p>
                                    @else
                                        <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                            Tidak dipakai &mdash; UP RJ, JD RJ dihitung normal.
                                        </p>
                                    @endif
                                    <x-input-error :messages="$errors->get('tarifPerKapitaRj')" class="mt-1" />
                                </div>
                            </div>

                            <p class="text-xs text-muted dark:text-gray-400">
                                Jalur lain tetap utuh: OK, radiologi, klinik, UGD, dan tarif transfer tidak
                                terpengaruh. RI dan RJ juga berdiri sendiri &mdash; boleh salah satu saja.
                            </p>
                        </div>
                    </x-border-form>

                    {{-- ── Tunjangan rutin — masuk Total Gaji SEBELUM pajak ── --}}
                    <x-border-form title="Tunjangan Rutin Bulanan">
                        <div class="space-y-4">
                            <p class="text-xs text-muted dark:text-gray-400">
                                Menambah total gaji sebelum PPh, jadi ikut menaikkan pajak. Beda dengan bonus atau
                                rapel yang diberikan setelah pajak dan diisi saat men-generate slip, bukan di sini.
                            </p>
                            <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                                <div>
                                    <x-input-label value="Struktural" class="mb-1" />
                                    <x-text-input-number wire:model="tunjanganStruktural"
                                        x-ref="inputTunjanganStruktural"
                                        :error="$errors->has('tunjanganStruktural')" class="w-full"
                                        x-on:keydown.enter.prevent="$refs.inputTunjanganFungsional?.focus()" />
                                    <x-input-error :messages="$errors->get('tunjanganStruktural')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Fungsional" class="mb-1" />
                                    <x-text-input-number wire:model="tunjanganFungsional"
                                        x-ref="inputTunjanganFungsional"
                                        :error="$errors->has('tunjanganFungsional')" class="w-full"
                                        x-on:keydown.enter.prevent="$refs.inputTunjanganHadir?.focus()" />
                                    <x-input-error :messages="$errors->get('tunjanganFungsional')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Kehadiran" class="mb-1" />
                                    <x-text-input-number wire:model="tunjanganHadir" x-ref="inputTunjanganHadir"
                                        :error="$errors->has('tunjanganHadir')" class="w-full"
                                        x-on:keydown.enter.prevent="$refs.inputPotonganIdi?.focus()" />
                                    <x-input-error :messages="$errors->get('tunjanganHadir')" class="mt-1" />
                                </div>
                            </div>
                        </div>
                    </x-border-form>

                    </div>

                    {{-- ── Potongan rutin — dipotong SETELAH pajak ── --}}
                    <x-border-form title="Potongan Rutin Bulanan">
                        <div class="space-y-4">
                            <p class="text-xs text-muted dark:text-gray-400">
                                Dipotong setelah PPh. Kasbon atau uang yang sudah diambil di muka tidak diisi di sini
                                karena nilainya berubah tiap bulan.
                            </p>
                            <div class="grid grid-cols-2 gap-3 md:grid-cols-6">
                                <div>
                                    <x-input-label value="IDI" class="mb-1" />
                                    <x-text-input-number wire:model="potonganIdi" x-ref="inputPotonganIdi"
                                        :error="$errors->has('potonganIdi')" class="w-full"
                                        x-on:keydown.enter.prevent="$refs.inputPotonganArisan?.focus()" />
                                    <x-input-error :messages="$errors->get('potonganIdi')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Arisan" class="mb-1" />
                                    <x-text-input-number wire:model="potonganArisan" x-ref="inputPotonganArisan"
                                        :error="$errors->has('potonganArisan')" class="w-full"
                                        x-on:keydown.enter.prevent="$refs.inputPotonganKoperasi?.focus()" />
                                    <x-input-error :messages="$errors->get('potonganArisan')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Koperasi" class="mb-1" />
                                    <x-text-input-number wire:model="potonganKoperasi" x-ref="inputPotonganKoperasi"
                                        :error="$errors->has('potonganKoperasi')" class="w-full"
                                        x-on:keydown.enter.prevent="$refs.inputPotonganAngsuran?.focus()" />
                                    <x-input-error :messages="$errors->get('potonganKoperasi')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Angsuran" class="mb-1" />
                                    <x-text-input-number wire:model="potonganAngsuran" x-ref="inputPotonganAngsuran"
                                        :error="$errors->has('potonganAngsuran')" class="w-full"
                                        x-on:keydown.enter.prevent="$refs.inputPotonganBpjs?.focus()" />
                                    <x-input-error :messages="$errors->get('potonganAngsuran')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="BPJS" class="mb-1" />
                                    <x-text-input-number wire:model="potonganBpjs" x-ref="inputPotonganBpjs"
                                        :error="$errors->has('potonganBpjs')" class="w-full"
                                        x-on:keydown.enter.prevent="$refs.inputPotonganZariyah?.focus()" />
                                    <x-input-error :messages="$errors->get('potonganBpjs')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Zariyah" class="mb-1" />
                                    <x-text-input-number wire:model="potonganZariyah" x-ref="inputPotonganZariyah"
                                        :error="$errors->has('potonganZariyah')" class="w-full"
                                        x-on:keydown.enter.prevent="$el.blur(); $wire.save()" />
                                    <x-input-error :messages="$errors->get('potonganZariyah')" class="mt-1" />
                                </div>
                            </div>
                        </div>
                    </x-border-form>

                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-surface-soft border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs text-muted dark:text-gray-400">
                        Perubahan di sini tidak mengubah slip gaji periode yang sudah final.
                    </p>
                    <div class="flex gap-2">
                        <x-secondary-button type="button" x-on:click="tryClose()">Batal</x-secondary-button>
                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove>Simpan</span>
                            <span wire:loading><x-loading /> Menyimpan...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </x-dirty-modal-content>
    </x-modal>
</div>
