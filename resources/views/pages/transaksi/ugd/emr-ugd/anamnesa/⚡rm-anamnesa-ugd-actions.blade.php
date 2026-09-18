<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
use App\Support\Terminologi\AlergiSnomed;
use App\Support\RekonsiliasiObat;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    /**
     * IRISAN dokumen: cabang `anamnesa` (model form — jalur validasi & wire:model kini
     * `anamnesa.*`) plus tiga nilai turunan dari cabang lain.
     */
    public array $anamnesa = [];

    public string $regNoPasien = '';

    /** Keluhan dari Screening — dipakai mengisi keluhan utama bila masih kosong. */
    public string $keluhanScreening = '';

    /** Sudah ada dokter pemeriksa di cabang `perencanaan` (guard alur dokter). */
    public bool $adaDrPemeriksa = false;

    /** Penanda kunjungan sudah dimuat lewat open(). */
    public bool $dokumenTermuat = false;

    /** Dokumen dibaca sebagai variabel LOKAL; hanya irisan + tiga nilai yang disimpan. */
    private function muatDariDokumen(array $data): void
    {
        $this->anamnesa = $data['anamnesa'] ?? [];
        $this->regNoPasien = (string) ($data['regNo'] ?? '');
        $this->keluhanScreening = (string) ($data['screening']['keluhanUtama'] ?? '');
        $this->adaDrPemeriksa = filled($data['perencanaan']['pengkajianMedis']['drPemeriksa'] ?? '');
        $this->dokumenTermuat = true;
    }

    /**
     * Daftar rekonsiliasi obat SAAT FORM DIBUKA — titik cabang untuk merge tiga arah.
     * WAJIB public: properti protected tidak di-dehydrate Livewire, jadi akan reset
     * tiap request dan basisnya hilang sebelum Simpan ditekan.
     */
    public array $rekonsiliasiObatSaatDibuka = [];

    public string $tingkatKegawatan = '';
    public string $caraMasukIgd = '';
    public string $saranaTransportasiId = '4';

    /**
     * Tab aktif disimpan di server (pola suket) — aksi seperti Tambah obat
     * memicu re-render + wire:key baru, dan Alpine state lokal akan balik ke
     * tab pertama kalau tidak di-entangle.
     */
    public string $anamnesaActiveTab = 'pengkajian';

    /* ---- Rekonsiliasi Obat ---- */
    // Entri form Rekonsiliasi Obat — bentuk $formEntry* seperti penilaian
    // (formEntryNyeri, formEntryResikoJatuh, dst).
    public array $formEntryRekonsiliasi = [
        'namaObat' => '',
        'dosis' => '',
        'rute' => '',
        'dibawaRanap' => 'Tidak',
        'digunakanRanap' => 'Tidak',
        'lanjutPulang' => 'Tidak',
    ];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-anamnesa-ugd'];

    /* ===============================
     | MOUNT
     =============================== */
    /**
     * rjNo datang lewat PROP dari induk EMR UGD (seksi lahir di dalam @if($rjNo)),
     * bukan lagi lewat event open-rm-*: satu kali baca CLOB, tidak ada race urutan event.
     * Handler #[On] tetap dipertahankan untuk pemanggil dari luar modal.
     */
    public function mount(?int $rjNo = null): void
    {
        $this->registerAreas(['modal-anamnesa-ugd']);

        if (filled($rjNo)) {
            $this->openAnamnesa($rjNo);
        }
    }

    public function rendering(): void
    {
        $default = $this->getDefaultAnamnesa();
        $this->anamnesa = array_replace_recursive($default, $this->anamnesa);
    }

    /* ===============================
     | OPEN
     =============================== */
    #[On('open-rm-anamnesa-ugd')]
    public function openAnamnesa(int $rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->rjNo = $rjNo;
        $this->resetForm();
        $this->resetValidation();

        $data = $this->findDataUGD($rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        $this->muatDariDokumen($data);

        // Inisialisasi key anamnesa jika belum ada
        if (!$this->anamnesa) {
            $this->anamnesa = $this->getDefaultAnamnesa();
        }

        // Pastikan struktur Status Medik ada — record lama (pra-fitur) tak punya key ini
        // sehingga opsi radio tak muncul bila hanya mengandalkan merge di rendering().
        if (!isset($this->anamnesa['pengkajianPerawatan']['statusMedik']['statusMedikOptions'])) {
            $defStatusMedik = $this->getDefaultAnamnesa()['pengkajianPerawatan']['statusMedik'];
            $defStatusMedik['statusMedik'] = $this->anamnesa['pengkajianPerawatan']['statusMedik']['statusMedik'] ?? '';
            $this->anamnesa['pengkajianPerawatan']['statusMedik'] = $defStatusMedik;
        }

        // Sync property lokal
        $this->tingkatKegawatan = $this->anamnesa['pengkajianPerawatan']['tingkatKegawatan'] ?? '';
        $this->caraMasukIgd = $this->anamnesa['pengkajianPerawatan']['caraMasukIgd'] ?? '';
        $this->saranaTransportasiId = $this->anamnesa['pengkajianPerawatan']['saranaTransportasiId'] ?? '4';

        // Sync keluhan utama dari screening → anamnesa jika kosong
        if (empty($this->anamnesa['keluhanUtama']['keluhanUtama']) && filled($this->keluhanScreening)) {
            $this->anamnesa['keluhanUtama']['keluhanUtama'] = $this->keluhanScreening;
        }

        // Sync alergi + riwayat dari master pasien
        $pasienData = $this->findDataMasterPasien($data['regNo']);
        if (!empty($pasienData['pasien']['alergi'])) {
            $this->anamnesa['alergi']['alergi'] = $pasienData['pasien']['alergi'];
            // Kode SNOMED ikut teksnya — hanya bila teks alergi juga diambil dari master,
            // supaya kode tak menempel ke alergi lain. Dulu snomedCode tak pernah disinkron.
            if (!empty($pasienData['pasien']['alergiSnomedCode'])) {
                $this->anamnesa['alergi']['snomedCode'] = $pasienData['pasien']['alergiSnomedCode'];
                $this->anamnesa['alergi']['snomedDisplayEn'] = $pasienData['pasien']['alergiSnomedDisplayEn'] ?? '';
                $this->anamnesa['alergi']['snomedDisplayId'] = $pasienData['pasien']['alergiSnomedDisplayId'] ?? '';
            }
        }
        if (!empty($pasienData['pasien']['riwayatPenyakitDahulu'])) {
            $this->anamnesa['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] = $pasienData['pasien']['riwayatPenyakitDahulu'];
        }

        // Seragamkan node alergi + turunkan radio "Ada alergi?" (default Tidak -> 716186003).
        // Record lama tak punya key adaAlergi -> diturunkan dari teksnya. Lihat AlergiSnomed.
        $this->anamnesa['alergi'] = AlergiSnomed::normalisasi(
            $this->anamnesa['alergi'] ?? [],
        );

        // Basis merge tiga arah — direkam sebelum user menyentuh apa pun.
        $this->rekonsiliasiObatSaatDibuka = (array) data_get($this->anamnesa, 'rekonsiliasiObat', []);

        $this->isFormLocked = $this->checkEmrUGDStatus($rjNo);
        $this->incrementVersion('modal-anamnesa-ugd');
    }

    /** Radio "Ada alergi?" diubah -> seragamkan node lewat sumber tunggal. */
    public function updatedDataDaftarUgdAnamnesaAlergiAdaAlergi(): void
    {
        $this->anamnesa['alergi'] = AlergiSnomed::normalisasi(
            $this->anamnesa['alergi'] ?? [],
        );
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'anamnesa.pengkajianPerawatan.jamDatang' => 'nullable|date_format:d/m/Y H:i:s',
            'anamnesa.pengkajianPerawatan.caraMasukIgd' => 'required',
            'anamnesa.pengkajianPerawatan.tingkatKegawatan' => 'required',
            'anamnesa.keluhanUtama.keluhanUtama' => 'required',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'date_format' => ':attribute harus dalam format dd/mm/yyyy HH:ii:ss.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'anamnesa.pengkajianPerawatan.jamDatang' => 'Jam Datang',
            'anamnesa.pengkajianPerawatan.caraMasukIgd' => 'Cara Masuk IGD',
            'anamnesa.pengkajianPerawatan.tingkatKegawatan' => 'Tingkat Kegawatan',
            'anamnesa.keluhanUtama.keluhanUtama' => 'Keluhan Utama',
        ];
    }

    /* ===============================
     | SAVE
     =============================== */
    #[On('save-rm-anamnesa-ugd')]
    public function save(?string $logKeterangan = null, bool $silent = false): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        $this->validateWithToast();

        try {
            DB::transaction(function () use ($logKeterangan) {
                // 1. Lock row dulu — cegah race condition update JSON bersamaan
                $this->lockUGDRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataUGD($this->rjNo);

                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
                }

                // Tangkap status sebelum overwrite (untuk verb log Buat/Update)
                $isBaru = empty($data['anamnesa']);

                // 3. Patch key anamnesa.
                // Daftar rekonsiliasi obat DIAMBIL DULU sebelum node ditimpa: modal
                // Farmasi (titik-3 Pelayanan UGD) bisa menambah baris selagi form ini
                // terbuka, dan baris ini menimpa SELURUH node anamnesa dgn salinan layar.
                $daftarRekonsiliasiObatDb = (array) data_get($data, 'anamnesa.rekonsiliasiObat', []);
                $statusRekonsiliasiDb = data_get($data, 'anamnesa.' . RekonsiliasiObat::STATUS_KEY);

                $data['anamnesa'] = $this->anamnesa;
                $data['anamnesa']['rekonsiliasiObat'] = RekonsiliasiObat::gabungTigaArah($this->rekonsiliasiObatSaatDibuka, (array) data_get($this->anamnesa, 'rekonsiliasiObat', []), $daftarRekonsiliasiObatDb);
                // Ceklis apoteker tidak pernah diubah dari form ini — nilai DB yang dipakai.
                RekonsiliasiObat::pertahankanStatus($data['anamnesa'], $statusRekonsiliasiDb);

                // 4. Update waktu_pasien_datang + waktu_pasien_dilayani
                $now = Carbon::now()->format('d/m/Y H:i:s');
                $waktuDatang = $data['anamnesa']['pengkajianPerawatan']['jamDatang'] ?? $now;
                $waktuDilayani = $data['perencanaan']['pengkajianMedis']['waktuPemeriksaan'] ?? $now;

                DB::table('rstxn_ugdhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update([
                        'waktu_pasien_datang' => DB::raw("to_date('{$waktuDatang}','dd/mm/yyyy hh24:mi:ss')"),
                        'waktu_pasien_dilayani' => DB::raw("to_date('{$waktuDilayani}','dd/mm/yyyy hh24:mi:ss')"),
                    ]);

                // 5. Simpan JSON
                $this->updateJsonUGD($this->rjNo, $data);
                $this->muatDariDokumen($data);

                // Basis digeser ke hasil tersimpan — Simpan berikutnya tidak boleh
                // memakai titik cabang yang sudah usang.
                $this->rekonsiliasiObatSaatDibuka = $data['anamnesa']['rekonsiliasiObat'];

                // 6. Update riwayat medis master pasien (masih dalam transaksi yang sama)
                $this->updateRiwayatMedisPasien();

                // 7. Audit log
                $keterangan = $logKeterangan ?? (($isBaru ? 'Buat' : 'Update') . ' Anamnesa UGD — jam datang ' . ($data['anamnesa']['pengkajianPerawatan']['jamDatang'] ?? '-'));
                $this->appendAdminLogUGD((int) $this->rjNo, $keterangan, 'MR');
            });

            // 7. Notify + increment version — di luar transaksi
            $this->incrementVersion('modal-anamnesa-ugd');
            $this->dispatch('refresh-after-ugd.saved');
            // Silent saat save-all (mis. tombol E-Resep) → cegah toast bertumpuk.
            if (! $silent) {
                $this->dispatch('toast', type: 'success', message: 'Anamnesa berhasil disimpan.');
            }
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | UPDATE RIWAYAT MEDIS PASIEN
     | Dipanggil dari dalam transaksi + lock sudah ada di caller.
     =============================== */
    private function updateRiwayatMedisPasien(): void
    {
        $regNo = $this->regNoPasien ?: null;
        if (!$regNo) {
            return;
        }

        $pasienData = $this->findDataMasterPasien($regNo);
        $updated = false;

        $alergi = $this->anamnesa['alergi']['alergi'] ?? '';
        $riwayat = $this->anamnesa['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] ?? '';

        if (!empty($alergi)) {
            $pasienData['pasien']['alergi'] = $alergi;
            // Kode SNOMED ikut teksnya — SELALU ditimpa (termasuk jadi kosong) supaya kode
            // lama tak tertinggal menempel pada teks alergi yang sudah diganti.
            $pasienData['pasien']['alergiSnomedCode'] = $this->anamnesa['alergi']['snomedCode'] ?? '';
            $pasienData['pasien']['alergiSnomedDisplayEn'] = $this->anamnesa['alergi']['snomedDisplayEn'] ?? '';
            $pasienData['pasien']['alergiSnomedDisplayId'] = $this->anamnesa['alergi']['snomedDisplayId'] ?? '';
            $updated = true;
        }
        if (!empty($riwayat)) {
            $pasienData['pasien']['riwayatPenyakitDahulu'] = $riwayat;
            $updated = true;
        }

        if ($updated) {
            $pasienData['pasien']['regNo'] = $regNo;
            $this->updateJsonMasterPasien($regNo, $pasienData);
        }
    }

    /* ===============================
     | ACTIONS
     =============================== */
    public function setPerawatPenerima(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        if (
            !auth()
                ->user()
                ->hasAnyRole(['Perawat', 'Dokter', 'Admin'])
        ) {
            $this->dispatch('toast', type: 'error', message: 'Hanya role Perawat / Dokter / Admin yang dapat melakukan TTD-E.');
            return;
        }

        $this->anamnesa['pengkajianPerawatan']['perawatPenerima'] = auth()->user()->myuser_name;
        $this->anamnesa['pengkajianPerawatan']['perawatPenerimaCode'] = auth()->user()->myuser_code;

        if (empty($this->anamnesa['pengkajianPerawatan']['jamDatang'])) {
            $this->anamnesa['pengkajianPerawatan']['jamDatang'] = now()->format('d/m/Y H:i:s');
        }

        $this->incrementVersion('modal-anamnesa-ugd');
    }


    /* ===============================
     | BUKA KUNCI TTD PERAWAT PENERIMA
     =============================== */
    /**
     * Cabut stempel Perawat Penerima supaya bisa di-TTD ulang.
     *
     * x-signature.ttd-petugas hanya merender tombol TTD selama namanya masih kosong,
     * jadi salah TTD tak punya jalan pulang. Padanan Buka Kunci Screening & modul
     * dokumen, memakai Gate yang sama.
     *
     * Berbeda dari setPerawatPenerima() yang hanya mengubah state di memori dan
     * menunggu tombol Simpan: pencabutan ditulis LANGSUNG ke DB. Kalau hanya di
     * memori, petugas bisa menutup modal tanpa menyimpan dan stempelnya hidup lagi,
     * sementara audit log sudah terlanjur mencatat pencabutan yang tak pernah terjadi.
     */
    public function bukaKunciTtdPerawatPenerima(): void
    {
        // Guard SERVER — guard blade saja bisa ditembus, wire:click memanggil method publik.
        if (! auth()->user()?->can('dokumen.bukaKunci')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak membuka kunci TTD Perawat.');

            return;
        }

        if (blank($this->rjNo)) {
            return;
        }

        if (blank($this->anamnesa['pengkajianPerawatan']['perawatPenerima'] ?? '')) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada TTD Perawat yang perlu dibuka.');

            return;
        }

        // URUTAN PENCABUTAN: dari yang PALING AKHIR menandatangani.
        //
        // Alurnya Screening -> Perawat Penerima -> Dokter, dan TTD dokter itu yang
        // mengesahkan SELURUH rekaman kunjungan (sekaligus menandai erm_status 'L').
        // Kalau stempel perawat boleh dicabut selagi TTD dokter masih berdiri, isinya
        // berubah di bawah tanda tangan yang sudah mengesahkannya — dokter tercatat
        // menyetujui rekaman yang bukan lagi yang dia setujui.
        if ($this->adaDrPemeriksa) {
            $this->dispatch('toast', type: 'error', message: 'Buka kunci TTD-E Dokter Pemeriksa lebih dulu — TTD dokter mengesahkan seluruh rekaman kunjungan ini.');

            return;
        }

        try {
            DB::transaction(function () {
                $this->lockUGDRow($this->rjNo);

                $data = $this->findDataUGD($this->rjNo) ?? [];

                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, buka kunci dibatalkan.');
                }

                $perawatSebelumnya = $data['anamnesa']['pengkajianPerawatan']['perawatPenerima'] ?? '-';

                // Cabut stempel petugas SAJA; isian pengkajian & jam datang dipertahankan —
                // jam datang itu waktu pasien tiba, bukan cap tanda tangan.
                $data['anamnesa']['pengkajianPerawatan']['perawatPenerima'] = '';
                $data['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'] = '';

                $this->updateJsonUGD((int) $this->rjNo, $data);
                $this->muatDariDokumen($data);

                $this->appendAdminLogUGD((int) $this->rjNo, 'Buka Kunci TTD Perawat Penerima — stempel ' . $perawatSebelumnya . ' dicabut oleh ' . (auth()->user()->myuser_name ?? '-'), 'MR');
            });

            $this->incrementVersion('modal-anamnesa-ugd');
            $this->dispatch('refresh-after-ugd.saved');
            $this->dispatch('toast', type: 'success', message: 'Kunci TTD Perawat dibuka — bisa TTD ulang.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuka kunci: ' . $e->getMessage());
        }
    }

    public function setAutoJamDatang(): void
    {
        $this->anamnesa['pengkajianPerawatan']['jamDatang'] = now()->format('d/m/Y H:i:s');
    }

    /* ===============================
     | REKONSILIASI OBAT
     =============================== */

    /**
     * Aksi rekonsiliasi obat langsung memanggil save(), sedangkan save()
     * memvalidasi rules() komponen (field wajib tab Pengkajian). Tanpa
     * pencegat ini user dapat toast "Cara Masuk IGD wajib diisi" saat
     * mengurus obat, dan barisnya terlanjur tampil di tabel padahal gagal
     * tersimpan ke JSON. Panggil SEBELUM state diubah & sebelum reset().
     *
     * @return bool true bila field wajib tab Pengkajian sudah terisi.
     */
    protected function pengkajianSiapUntukRekonsiliasi(): bool
    {
        $pengkajian = $this->anamnesa['pengkajianPerawatan'] ?? [];

        $belum = collect([
            'Cara Masuk IGD' => $pengkajian['caraMasukIgd'] ?? null,
            'Tingkat Kegawatan' => $pengkajian['tingkatKegawatan'] ?? null,
            'Keluhan Utama' => $this->anamnesa['keluhanUtama']['keluhanUtama'] ?? null,
        ])
            ->filter(fn($nilai) => blank($nilai))
            ->keys();

        if ($belum->isEmpty()) {
            return true;
        }

        $this->dispatch(
            'toast',
            type: 'error',
            message: 'Lengkapi tab Pengkajian dulu — ' . $belum->implode(', ') . ' masih kosong.',
        );

        return false;
    }

    public function addRekonsiliasiObat(): void
    {
        // validate() didahulukan supaya field yang kosong tetap ditandai merah
        // (guard/early-return sebelum validate bikin border error tak muncul).
        $this->validateWithToast(
            [
                'formEntryRekonsiliasi.namaObat' => ['required', 'string', 'max:200'],
                'formEntryRekonsiliasi.dosis' => ['required', 'string', 'max:100'],
                'formEntryRekonsiliasi.rute' => ['required', 'string'],
            ],
            [],
            [
                'formEntryRekonsiliasi.namaObat' => 'Nama Obat',
                'formEntryRekonsiliasi.dosis' => 'Dosis',
                'formEntryRekonsiliasi.rute' => 'Rute',
            ],
        );

        // Dicegat di sini: state belum disentuh & isian form belum di-reset,
        // jadi user tinggal melengkapi Pengkajian lalu klik Tambah lagi.
        if (!$this->pengkajianSiapUntukRekonsiliasi()) {
            return;
        }

        if (RekonsiliasiObat::sudahAda($this->anamnesa['rekonsiliasiObat'] ?? [], $this->formEntryRekonsiliasi['namaObat'])) {
            $this->dispatch('toast', type: 'error', message: 'Obat sudah ada dalam daftar.');
            return;
        }

        $this->anamnesa['rekonsiliasiObat'][] = RekonsiliasiObat::barisBaru($this->formEntryRekonsiliasi['namaObat'], $this->formEntryRekonsiliasi['dosis'], $this->formEntryRekonsiliasi['rute'], $this->formEntryRekonsiliasi['dibawaRanap'], $this->formEntryRekonsiliasi['digunakanRanap'], $this->formEntryRekonsiliasi['lanjutPulang']);

        $namaObat = $this->formEntryRekonsiliasi['namaObat'];
        $this->reset(['formEntryRekonsiliasi']);
        $this->save('Tambah Rekonsiliasi Obat UGD — ' . $namaObat);
    }

    public function removeRekonsiliasiObat(int $index): void
    {
        // Dicegat sebelum baris dibuang — daftar tetap utuh bila gagal simpan.
        if (!$this->pengkajianSiapUntukRekonsiliasi()) {
            return;
        }

        if (isset($this->anamnesa['rekonsiliasiObat'][$index])) {
            $namaObat = $this->anamnesa['rekonsiliasiObat'][$index]['namaObat'] ?? '-';
            unset($this->anamnesa['rekonsiliasiObat'][$index]);
            $this->anamnesa['rekonsiliasiObat'] = array_values($this->anamnesa['rekonsiliasiObat']);
            $this->save('Hapus Rekonsiliasi Obat UGD — ' . $namaObat);
        }
    }

    /* ===============================
     | SCREENING GIZI
     =============================== */
    public function calculateScreeningGizi(): void
    {
        $screeningGizi = $this->anamnesa['screeningGizi'] ?? [];
        $total = (int) ($screeningGizi['perubahanBB3BlnScore'] ?? 0) + (int) ($screeningGizi['jmlPerubahanBBScore'] ?? 0) + (int) ($screeningGizi['intakeMakananScore'] ?? 0);

        $this->anamnesa['screeningGizi']['scoreTotalScreeningGizi'] = (string) $total;
        $this->anamnesa['screeningGizi']['tglScreeningGizi'] = now()->format('d/m/Y H:i:s');
    }

    /* ===============================
     | UPDATED HOOKS
     =============================== */
    public function updated(string $name, mixed $value): void
    {
        match ($name) {
            'tingkatKegawatan' => ($this->anamnesa['pengkajianPerawatan']['tingkatKegawatan'] = $value),
            'caraMasukIgd' => ($this->anamnesa['pengkajianPerawatan']['caraMasukIgd'] = $value),
            'saranaTransportasiId' => ($this->anamnesa['pengkajianPerawatan']['saranaTransportasiId'] = $value),
            default => null,
        };
    }

    /* ===============================
     | DEFAULT STRUCTURE
     =============================== */
    private function getDefaultAnamnesa(): array
    {
        return [
            'pengkajianPerawatanTab' => 'Pengkajian',
            'pengkajianPerawatan' => [
                'perawatPenerima' => '',
                'perawatPenerimaCode' => '',
                'jamDatang' => '',
                // Status Medik — model array mengikuti sistem lama (nested statusMedik + options)
                'statusMedik' => [
                    'statusMedik' => '',
                    'statusMedikOptions' => [
                        ['statusMedik' => 'Emergency Trauma'],
                        ['statusMedik' => 'Emergency Non Trauma'],
                        ['statusMedik' => 'Non Emergency Trauma'],
                        ['statusMedik' => 'Non Emergency Non Trauma'],
                    ],
                ],
                'caraMasukIgd' => '',
                'caraMasukIgdDesc' => '',
                'caraMasukIgdOption' => [['caraMasukIgd' => 'Sendiri'], ['caraMasukIgd' => 'Rujuk'], ['caraMasukIgd' => 'Kasus Polisi']],
                'tingkatKegawatan' => '',
                'tingkatKegawatanOption' => [['tingkatKegawatan' => 'P1'], ['tingkatKegawatan' => 'P2'], ['tingkatKegawatan' => 'P3'], ['tingkatKegawatan' => 'P0']],
                'saranaTransportasiId' => '4',
                'saranaTransportasiDesc' => 'Lain-lain',
                'saranaTransportasiKet' => '',
                'saranaTransportasiOptions' => [['saranaTransportasiId' => '1', 'saranaTransportasiDesc' => 'Ambulans'], ['saranaTransportasiId' => '2', 'saranaTransportasiDesc' => 'Mobil'], ['saranaTransportasiId' => '3', 'saranaTransportasiDesc' => 'Motor'], ['saranaTransportasiId' => '4', 'saranaTransportasiDesc' => 'Lain-lain']],
            ],
            'keluhanUtamaTab' => 'Keluhan Utama',
            'keluhanUtama' => ['keluhanUtama' => '', 'snomedCode' => '', 'snomedDisplayEn' => '', 'snomedDisplayId' => ''],

            'anamnesaDiperolehTab' => 'Anamnesa Diperoleh',
            'anamnesaDiperoleh' => ['autoanamnesa' => [], 'allonanamnesa' => [], 'anamnesaDiperolehDari' => ''],

            'riwayatPenyakitSekarangUmumTab' => 'Riwayat Penyakit Sekarang',
            'riwayatPenyakitSekarangUmum' => ['riwayatPenyakitSekarangUmum' => ''],

            'riwayatPenyakitDahuluTab' => 'Riwayat Penyakit Dahulu',
            'riwayatPenyakitDahulu' => ['riwayatPenyakitDahulu' => ''],

            'alergiTab' => 'Alergi',
            'alergi' => ['adaAlergi' => '', 'alergi' => '', 'snomedCode' => '', 'snomedDisplayEn' => '', 'snomedDisplayId' => ''],

            'rekonsiliasiObatTab' => 'Rekonsiliasi Obat',
            'rekonsiliasiObat' => [],

            'statusPsikologisTab' => 'Status Psikologis',
            'statusPsikologis' => [
                'tidakAdaKelainan' => [],
                'marah' => [],
                'cemas' => [],
                'takut' => [],
                'sedih' => [],
                'cenderungBunuhDiri' => [],
                'sebutstatusPsikologis' => '',
            ],

            'statusMentalTab' => 'Status Mental',
            'statusMental' => [
                'statusMental' => '',
                'statusMentalOption' => [['statusMental' => 'Sadar dan Orientasi Baik'], ['statusMental' => 'Ada Masalah Perilaku'], ['statusMental' => 'Perilaku Kekerasan yang dialami sebelumnya']],
                'keteranganStatusMental' => '',
            ],

            'batukTab' => 'Screening Batuk',
            'batuk' => [
                'riwayatDemam' => [],
                'keteranganRiwayatDemam' => '',
                'berkeringatMlmHari' => [],
                'keteranganBerkeringatMlmHari' => '',
                'bepergianDaerahWabah' => [],
                'keteranganBepergianDaerahWabah' => '',
                'riwayatPakaiObatJangkaPanjangan' => [],
                'keteranganRiwayatPakaiObatJangkaPanjangan' => '',
                'BBTurunTanpaSebab' => [],
                'keteranganBBTurunTanpaSebab' => '',
                'pembesaranGetahBening' => [],
                'keteranganPembesaranGetahBening' => '',
            ],
        ];
    }

    /* ===============================
     | LOV SNOMED — Keluhan Utama
     =============================== */
    #[On('lov.selected.keluhanUtamaSnomed')]
    public function onKeluhanUtamaSnomedSelected(string $target, array $payload): void
    {
        $this->anamnesa['keluhanUtama']['snomedCode'] = $payload['snomed_code'] ?? '';
        $this->anamnesa['keluhanUtama']['snomedDisplayEn'] = $payload['display_en'] ?? '';
        $this->anamnesa['keluhanUtama']['snomedDisplayId'] = $payload['display_id'] ?? '';
    }

    #[On('lov.cleared.keluhanUtamaSnomed')]
    public function onKeluhanUtamaSnomedCleared(string $target): void
    {
        $this->anamnesa['keluhanUtama']['snomedCode'] = '';
        $this->anamnesa['keluhanUtama']['snomedDisplayEn'] = '';
        $this->anamnesa['keluhanUtama']['snomedDisplayId'] = '';
    }

    /* ===============================
     | LOV SNOMED — Alergi
     =============================== */
    #[On('lov.selected.alergiSnomed')]
    public function onAlergiSnomedSelected(string $target, array $payload): void
    {
        $this->anamnesa['alergi']['snomedCode'] = $payload['snomed_code'] ?? '';
        $this->anamnesa['alergi']['snomedDisplayEn'] = $payload['display_en'] ?? '';
        $this->anamnesa['alergi']['snomedDisplayId'] = $payload['display_id'] ?? '';
    }

    #[On('lov.cleared.alergiSnomed')]
    public function onAlergiSnomedCleared(string $target): void
    {
        $this->anamnesa['alergi']['snomedCode'] = '';
        $this->anamnesa['alergi']['snomedDisplayEn'] = '';
        $this->anamnesa['alergi']['snomedDisplayId'] = '';
    }

    /* ===============================
     | HELPERS
     =============================== */
    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->anamnesa = [];
        $this->dokumenTermuat = false;
        $this->anamnesaActiveTab = 'pengkajian';
        $this->reset(['formEntryRekonsiliasi']);
        $this->tingkatKegawatan = '';
        $this->caraMasukIgd = '';
        $this->saranaTransportasiId = '4';
    }
};
?>

<div>
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-anamnesa-ugd', [$rjNo ?? 'new']) }}">
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-6 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                @if ($dokumenTermuat)
                    <div x-data="{ activeTab: @entangle('anamnesaActiveTab') }" class="w-full">

                        {{-- TAB NAVIGATION --}}
                        <x-scrollable-tabs class="w-full px-2 mb-2 border-b border-hairline dark:border-gray-700">
                            <div class="flex flex-nowrap gap-1 -mb-px">

                                <x-tab variant="underline" active-expr="activeTab === 'pengkajian'"
                                    x-on:click="activeTab = 'pengkajian'">
                                    Pengkajian
                                </x-tab>

                                {{-- <li class="mr-1">
                                    <button type="button"
                                        class="inline-block p-4 border-b-2 border-transparent rounded-t-lg transition-colors"
                                        :class="activeTab === 'keluhan' ? 'text-brand border-brand dark:text-emerald-300 dark:border-emerald-400 bg-surface-soft' : 'border-transparent hover:text-muted hover:border-gray-300'"
                                        @click="activeTab = 'keluhan'">
                                        Keluhan & Riwayat
                                    </button>
                                </li> --}}

                                <x-tab variant="underline" active-expr="activeTab === 'rekonsiliasi'"
                                    x-on:click="activeTab = 'rekonsiliasi'">
                                    Rekonsiliasi Obat
                                </x-tab>

                                <x-tab variant="underline" active-expr="activeTab === 'psikologis'"
                                    x-on:click="activeTab = 'psikologis'">
                                    Psikologis & Mental
                                </x-tab>

                                <x-tab variant="underline" active-expr="activeTab === 'batuk'"
                                    x-on:click="activeTab = 'batuk'">
                                    Screening Batuk
                                </x-tab>

                            </div>
                        </x-scrollable-tabs>

                        {{-- TAB CONTENTS --}}
                        <div class="w-full p-4">

                            <div x-show="activeTab === 'pengkajian'" x-transition.opacity.duration.300ms>
                                @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.pengkajian-perawatan-tab')
                            </div>

                            {{-- <div x-show="activeTab === 'keluhan'" x-transition.opacity.duration.300ms>
                                @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.keluhan-riwayat-tab')
                            </div> --}}

                            <div x-show="activeTab === 'rekonsiliasi'" x-transition.opacity.duration.300ms>
                                @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.rekonsiliasi-obat-tab')
                            </div>

                            <div x-show="activeTab === 'psikologis'" x-transition.opacity.duration.300ms>
                                @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.status-psikologis-tab')
                            </div>

                            <div x-show="activeTab === 'batuk'" x-transition.opacity.duration.300ms>
                                @include('pages.transaksi.ugd.emr-ugd.anamnesa.tabs.batuk-tab')
                            </div>

                        </div>
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-24 text-gray-300 dark:text-gray-600">
                        <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <p class="text-base font-medium">Data UGD belum dimuat</p>
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>
