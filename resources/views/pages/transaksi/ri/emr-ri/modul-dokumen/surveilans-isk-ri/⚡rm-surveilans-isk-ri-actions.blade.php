<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/surveilans-isk-ri/rm-surveilans-isk-ri-actions.blade.php
// Surveilans HAIs — Infeksi Saluran Kemih (ISK) / CAUTI, Formulir Surveilans HIPPII F/011/001/R/03.
// Multi-entri: Draft → TTD (kunci) → Lihat/Cetak → Buka Kunci/Hapus. Disimpan di datadaftarri_json.

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
use App\Support\DpjpUtamaRI;
use App\Support\Options\SurveilansHaisOptions;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public ?string $regNo = null;
    public bool $disabled = false;
    public array $dataDaftarRi = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-surveilans-isk-ri'];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'surveilansIskRI';

    public array $newForm = [];
    public array $entriList = [];

    /** Baris staging pemakaian antibiotik sebelum masuk daftar. */
    public array $barisObat = [
        'namaObat' => '',
        'tglMulai' => '',
        'tglSelesai' => '',
        'dosis' => '',
        'rute' => '',
        'indikasi' => '',
    ];

    /** Baris staging hasil kultur/pemeriksaan per daftar (key = nama list di $newForm). */
    public array $barisKultur = [];

    /** Baris staging "Pemasangan" sebelum masuk daftar (pola Leveling Dokter: form tambah + tabel). */
    public array $barisPasang = [];

    public ?string $editingKey = null;

    // Layar aktif di modal: 'daftar' (grid entri) atau 'form' (tambah/edit/lihat).
    // Formulir sengaja tidak nongkrong bersama daftarnya: dulu ia ikut tampil terus lalu
    // dikosongkan diam-diam sesudah tersimpan, dan petugas yang mengira itu masih formulir
    // yang tadi diisi mengetik ulang — tersimpan sebagai draft baru.
    public string $layar = 'daftar';
    public bool $viewOnly = false;

    /* ===============================
     | DEFAULT FORM
     =============================== */
    public function defaultForm(): array
    {
        $tandaKosong = array_fill_keys(
            array_merge(
                array_keys(SurveilansHaisOptions::TANDA_ISK_BALITA),
                array_keys(SurveilansHaisOptions::TANDA_ISK_DEWASA),
            ),
            false,
        );

        return [
            // ── Data dasar surveilans ──
            'tanggal' => '',
            'diagnosisAkhir' => '',
            'faktorRisiko' => array_fill_keys(array_keys(SurveilansHaisOptions::FAKTOR_RISIKO), false),

            // ── Infeksi Saluran Kemih ──
            'jenisKateter' => '',
            'tglPasangKateter' => '',
            'kelompokUsia' => '',
            'pemasangan' => [],
            'pemeriksaanUrinHasil' => [],
            'biakanUrinHasil' => [],

            // ── Antibiotik ──
            'antibiotik' => [],

            // ── Penutup ──
            'dokterMerawat' => '',
            'catatan' => '',
            'ttd' => '',
            'ttdCode' => '',
            'ttdDate' => '',
        ];
    }

    /* ===============================
     | MOUNT / MODAL
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->newForm = $this->defaultForm();
        $this->barisObat = $this->defaultBarisObat();
        $this->barisKultur = $this->defaultBarisKultur();
        $this->barisPasang = $this->defaultBarisPasang();
        $this->registerAreas(['modal-surveilans-isk-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->entriList = $data[$this->jsonKey] ?? [];
                $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $disabled;
            }
        }
    }

    public function openModal(): void
    {
        if (!$this->riHdrNo || $this->disabled) {
            return;
        }

        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();

        $data = $this->findDataRI($this->riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->regNo = $data['regNo'] ?? null;
        if (!isset($this->dataDaftarRi[$this->jsonKey]) || !is_array($this->dataDaftarRi[$this->jsonKey])) {
            $this->dataDaftarRi[$this->jsonKey] = [];
        }
        $this->entriList = $this->dataDaftarRi[$this->jsonKey];
        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;
        $this->isiDpjpUtamaBilaKosong();

        $this->incrementVersion('modal-surveilans-isk-ri');
        $this->layar = 'daftar';
        $this->dispatch('open-modal', name: "rm-surveilans-isk-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-surveilans-isk-ri-{$this->riHdrNo}");
    }

    /* ===============================
     | VALIDASI (minimal)
     =============================== */
    protected function rules(): array
    {
        return [
            'newForm.tanggal' => 'required|date_format:d/m/Y H:i:s',
            'newForm.kelompokUsia' => 'required|string',
            'newForm.diagnosisAkhir' => 'nullable|string|max:500',
            'newForm.catatan' => 'nullable|string|max:2000',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'date_format' => 'Format :attribute harus dd/mm/yyyy HH:mm:ss.',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newForm.tanggal' => 'Tanggal/jam surveilans',
            'newForm.kelompokUsia' => 'Kelompok usia pasien',
            'newForm.diagnosisAkhir' => 'Diagnosis akhir',
            'newForm.catatan' => 'Catatan',
        ];
    }

    /* ===============================
     | HELPER FORM
     =============================== */
    private function resetNewForm(): void
    {
        $this->newForm = $this->defaultForm();
        $this->barisObat = $this->defaultBarisObat();
        $this->barisKultur = $this->defaultBarisKultur();
        $this->barisPasang = $this->defaultBarisPasang();
        $this->layar = 'daftar';   // mengosongkan formulir = kembali ke daftar
    }

    /**
     * Isi awal baris TTD dengan DPJP Utama dari Leveling Dokter — hanya bila masih
     * kosong, supaya nilai yang sudah diketik/diganti petugas tidak tertimpa.
     */
    private function isiDpjpUtamaBilaKosong(): void
    {
        if (filled($this->newForm['dokterMerawat'] ?? null)) {
            return;
        }

        $this->newForm['dokterMerawat'] = DpjpUtamaRI::nama($this->dataDaftarRi);
    }

    public function setNow(string $path): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $form = $this->newForm;
        data_set($form, $path, Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'));
        $this->newForm = $form;
    }

    public function entryIsFinal(array $entri): bool
    {
        return array_key_exists('finalized', $entri) ? (bool) $entri['finalized'] : !empty($entri['ttd']);
    }

    private function adaIsiInti(): bool
    {
        if (filled($this->newForm['tanggal'] ?? null) || filled($this->newForm['kelompokUsia'] ?? null)) {
            return true;
        }

        return filled($this->newForm['jenisKateter'] ?? null);
    }

    private function buildEntry(string $key, bool $finalized): array
    {
        $entry = array_replace_recursive($this->defaultForm(), $this->newForm);
        $entry['createdAt'] = $key;
        $entry['finalized'] = $finalized;

        return $entry;
    }

    private function persistEntry(string $key, bool $finalized, string $logVerb): void
    {
        $entry = $this->buildEntry($key, $finalized);

        DB::transaction(function () use ($entry, $key, $logVerb) {
            $this->lockRIRow($this->riHdrNo);

            $fresh = $this->findDataRI($this->riHdrNo) ?: [];
            if (empty($fresh)) {
                throw new \RuntimeException('Data RI tidak ditemukan, simpan dibatalkan.');
            }
            if (!isset($fresh[$this->jsonKey]) || !is_array($fresh[$this->jsonKey])) {
                $fresh[$this->jsonKey] = [];
            }

            $list = $fresh[$this->jsonKey];
            $indeks = collect($list)->search(fn($item) => ($item['createdAt'] ?? '') === $key);
            if ($indeks === false) {
                $list[] = $entry;
            } else {
                if ($this->entryIsFinal($list[$indeks])) {
                    throw new \RuntimeException('Entri sudah terkunci, tidak dapat diubah.');
                }
                $list[$indeks] = $entry;
            }
            $fresh[$this->jsonKey] = array_values($list);

            $this->updateJsonRI((int) $this->riHdrNo, $fresh);
            $this->dataDaftarRi = $fresh;
            $this->entriList = $fresh[$this->jsonKey];

            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Surveilans ISK — ' . ($entry['tanggal'] ?: '-') . ' (' . $key . ')', 'MR');
        });
    }

    /* ===============================
     | SIMPAN DRAFT / TTD (KUNCI)
     =============================== */
    public function saveDraft(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }
        if (!$this->adaIsiInti()) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal tanggal / kelompok usia / jenis kateter terlebih dahulu.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key;
            $this->incrementVersion('modal-surveilans-isk-ri');
            $this->dispatch('toast', type: 'success', message: 'Draft surveilans tersimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    public function ttdSaya(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        $this->validateWithToast();

        $this->newForm['ttd'] = auth()->user()->myuser_name ?? '';
        $this->newForm['ttdCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['ttdDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, true, 'Kunci (TTD)');
            $this->resetNewForm();
            $this->editingKey = null;
            $this->viewOnly = false;
            $this->incrementVersion('modal-surveilans-isk-ri');
            $this->dispatch('toast', type: 'success', message: 'Surveilans ditandatangani & terkunci.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    public function hapusTtd(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['ttd'] = '';
        $this->newForm['ttdCode'] = '';
        $this->newForm['ttdDate'] = '';
    }

    /* ===============================
     | EDIT / LIHAT / BATAL
     =============================== */
    private function hydrateFormFromEntry(array $entri, string $key): void
    {
        $this->newForm = array_replace_recursive($this->defaultForm(), $entri);
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-surveilans-isk-ri');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        $entri = collect($this->entriList)->firstWhere('createdAt', $key);
        if (!$entri) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }
        if ($this->entryIsFinal($entri)) {
            $this->dispatch('toast', type: 'error', message: 'Entri sudah terkunci — buka kunci dahulu.');
            return;
        }

        $this->viewOnly = false;
        $this->hydrateFormFromEntry($entri, $key);
    }

    public function viewEntry(string $key): void
    {
        $entri = collect($this->entriList)->firstWhere('createdAt', $key);
        if (!$entri) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }

        $this->viewOnly = true;
        $this->hydrateFormFromEntry($entri, $key);
        $this->dispatch('toast', type: 'info', message: 'Menampilkan entri (hanya lihat).');
    }

    public function cancelEdit(): void
    {
        $this->resetNewForm();
        $this->isiDpjpUtamaBilaKosong();
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();
        $this->incrementVersion('modal-surveilans-isk-ri');
    }

    /** Layar formulir sedang tampil? Saat terkunci, formulir tak pernah dirender. */
    public function diForm(): bool
    {
        return !$this->isFormLocked && ($this->viewOnly || $this->editingKey !== null || $this->layar === 'form');
    }

    /** Buka formulir kosong untuk entri baru. */
    public function tambahEntri(): void
    {
        if ($this->isFormLocked || $this->disabled) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menambah entri.');
            return;
        }
        $this->cancelEdit();     // kosongkan formulir (sekaligus balik ke daftar)…
        $this->layar = 'form';   // …lalu naikkan formulirnya
    }

    /** Tutup formulir, kembali ke daftar entri. Formulir selalu ditinggalkan kosong. */
    public function kembaliKeDaftar(): void
    {
        $this->cancelEdit();
    }

    /* ===============================
     | BUKA KUNCI / HAPUS
     =============================== */
    public function bukaKunci(string $key): void
    {
        if (!auth()->user()?->can('dokumen.bukaKunci')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang membuka kunci entri.');
            return;
        }
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        try {
            DB::transaction(function () use ($key) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $list = $fresh[$this->jsonKey] ?? [];
                $indeks = collect($list)->search(fn($item) => ($item['createdAt'] ?? '') === $key);
                if ($indeks === false) {
                    throw new \RuntimeException('Entri tidak ditemukan.');
                }

                $list[$indeks]['finalized'] = false;
                $list[$indeks]['ttd'] = '';
                $list[$indeks]['ttdCode'] = '';
                $list[$indeks]['ttdDate'] = '';
                $fresh[$this->jsonKey] = array_values($list);

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->entriList = $fresh[$this->jsonKey];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Buka kunci Surveilans ISK (' . $key . ') oleh ' . (auth()->user()->myuser_name ?? '-'), 'MR');
            });

            $this->incrementVersion('modal-surveilans-isk-ri');
            $this->dispatch('toast', type: 'success', message: 'Entri dibuka kembali sebagai draft.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal buka kunci: ' . $e->getMessage());
        }
    }

    public function hapus(string $key): void
    {
        if (!auth()->user()?->can('dokumen.hapus')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang menghapus entri.');
            return;
        }
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menghapus.');
            return;
        }

        try {
            DB::transaction(function () use ($key) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $fresh[$this->jsonKey] = collect($fresh[$this->jsonKey] ?? [])
                    ->reject(fn($item) => ($item['createdAt'] ?? '') === $key)
                    ->values()
                    ->toArray();

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->entriList = $fresh[$this->jsonKey];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Surveilans ISK — ' . $key, 'MR');
            });

            if ($this->editingKey === $key) {
                $this->cancelEdit();
            }

            $this->incrementVersion('modal-surveilans-isk-ri');
            $this->dispatch('toast', type: 'success', message: 'Entri surveilans dihapus.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | PEMASANGAN (daftar dinamis, pola Leveling Dokter)
     =============================== */
    /** Bentuk kosong baris pemasangan — kunci tanda infeksi gabungan kedua kelompok usia. */
    private function defaultBarisPasang(): array
    {
        return [
            'pasangKe' => '',
            'tglMulai' => '',
            'tglSelesai' => '',
            'hariKe' => '',
            'tanda' => array_fill_keys(
                array_merge(
                    array_keys(SurveilansHaisOptions::TANDA_ISK_BALITA),
                    array_keys(SurveilansHaisOptions::TANDA_ISK_DEWASA),
                ),
                false,
            ),
        ];
    }

    /** Set tanggal/jam sekarang pada baris pemasangan yang sedang disusun. */
    public function setNowPasang(string $field): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->barisPasang[$field] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function tambahPemasangan(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (!filled($this->barisPasang['pasangKe'] ?? null) && !filled($this->barisPasang['tglMulai'] ?? null)) {
            $this->dispatch('toast', type: 'error', message: 'Isi keterangan pemasangan terlebih dahulu.');
            return;
        }

        $this->newForm['pemasangan'][] = array_replace($this->defaultBarisPasang(), $this->barisPasang);
        $this->barisPasang = $this->defaultBarisPasang();
    }

    public function hapusPemasangan(int $index): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (!isset($this->newForm['pemasangan'][$index])) {
            return;
        }

        unset($this->newForm['pemasangan'][$index]);
        $this->newForm['pemasangan'] = array_values($this->newForm['pemasangan']);
    }

    /* ===============================
     | HASIL KULTUR / PEMERIKSAAN (daftar dinamis, pola Leveling Dokter)
     =============================== */
    /** Daftar list hasil yang boleh disentuh aksi di bawah — penjaga argumen dari blade. */
    private array $daftarKultur = ['pemeriksaanUrinHasil', 'biakanUrinHasil'];

    private function defaultBarisKultur(): array
    {
        return collect($this->daftarKultur)
            ->mapWithKeys(fn($list) => [$list => ['tgl' => '', 'hasil' => '']])
            ->all();
    }

    private function kulturValid(string $list): bool
    {
        if (!in_array($list, $this->daftarKultur, true)) {
            $this->dispatch('toast', type: 'error', message: 'Daftar hasil tidak dikenal.');
            return false;
        }
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return false;
        }

        return true;
    }

    public function setNowKultur(string $list): void
    {
        if (!$this->kulturValid($list)) {
            return;
        }
        $this->barisKultur[$list]['tgl'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function tambahKultur(string $list): void
    {
        if (!$this->kulturValid($list)) {
            return;
        }

        $baris = $this->barisKultur[$list] ?? ['tgl' => '', 'hasil' => ''];
        if (!filled($baris['tgl'] ?? null) && !filled($baris['hasil'] ?? null)) {
            $this->dispatch('toast', type: 'error', message: 'Isi tanggal atau hasil terlebih dahulu.');
            return;
        }

        $this->newForm[$list][] = ['tgl' => $baris['tgl'] ?? '', 'hasil' => $baris['hasil'] ?? ''];
        $this->barisKultur[$list] = ['tgl' => '', 'hasil' => ''];
    }

    public function hapusKultur(string $list, int $index): void
    {
        if (!$this->kulturValid($list)) {
            return;
        }
        if (!isset($this->newForm[$list][$index])) {
            return;
        }

        unset($this->newForm[$list][$index]);
        $this->newForm[$list] = array_values($this->newForm[$list]);
    }

    /* ===============================
     | PEMAKAIAN ANTIBIOTIK (daftar dinamis, pola Leveling Dokter)
     =============================== */
    public function setNowObat(string $field): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->barisObat[$field] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function tambahAntibiotik(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (!filled($this->barisObat['namaObat'] ?? null)) {
            $this->dispatch('toast', type: 'error', message: 'Isi nama obat terlebih dahulu.');
            return;
        }

        $this->newForm['antibiotik'][] = $this->barisObat;
        $this->barisObat = $this->defaultBarisObat();
    }

    public function hapusAntibiotik(int $index): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (!isset($this->newForm['antibiotik'][$index])) {
            return;
        }

        unset($this->newForm['antibiotik'][$index]);
        $this->newForm['antibiotik'] = array_values($this->newForm['antibiotik']);
    }

    private function defaultBarisObat(): array
    {
        return ['namaObat' => '', 'tglMulai' => '', 'tglSelesai' => '', 'dosis' => '', 'rute' => '', 'indikasi' => ''];
    }

    /* ===============================
     | CETAK
     =============================== */
    public function cetak(string $key)
    {
        $entri = collect($this->entriList)->firstWhere('createdAt', $key);
        if (!$entri) {
            $this->dispatch('toast', type: 'error', message: 'Data surveilans tidak ditemukan.');
            return;
        }

        try {
            $identitasRs = DB::table('rsmst_identitases')
                ->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
            $pasienData = $this->findDataMasterPasien($this->regNo ?? '');
            $pasien = $pasienData['pasien'] ?? [];

            if (!empty($pasien['tglLahir'])) {
                try {
                    $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                        ->diff(Carbon::now(config('app.timezone')))->format('%y Thn, %m Bln %d Hr');
                } catch (\Throwable) {
                    $pasien['thn'] = '-';
                }
            }

            $ttdPath = null;
            if (!empty($entri['ttdCode'])) {
                $ttdImg = DB::table('users')->where('myuser_code', $entri['ttdCode'])->value('myuser_ttd_image');
                if (!empty($ttdImg) && file_exists(public_path('storage/' . $ttdImg))) {
                    $ttdPath = public_path('storage/' . $ttdImg);
                }
            }

            $data = array_merge($pasien, [
                'ttdPath' => $ttdPath,
                'dataRi' => $this->dataDaftarRi,
                'form' => array_replace_recursive($this->defaultForm(), $entri),
                'opsiLabel' => SurveilansHaisOptions::labels(),
                'identitasRs' => $identitasRs,
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);
            $pdf = Pdf::loadView('pages.components.modul-dokumen.ri.surveilans-isk-ri.cetak-surveilans-isk-ri-print', ['data' => $data])->setPaper('A4');

            return response()->streamDownload(fn() => print $pdf->output(), 'surveilans-isk-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }
};
?>

@php
    $opsiFaktorRisiko = \App\Support\Options\SurveilansHaisOptions::FAKTOR_RISIKO;
    $opsiKelompokUsia = \App\Support\Options\SurveilansHaisOptions::KELOMPOK_USIA;
    $opsiJenisKateter = \App\Support\Options\SurveilansHaisOptions::JENIS_KATETER_ISK;
    $opsiTandaBalita = \App\Support\Options\SurveilansHaisOptions::TANDA_ISK_BALITA;
    $opsiTandaDewasa = \App\Support\Options\SurveilansHaisOptions::TANDA_ISK_DEWASA;
    $opsiRute = \App\Support\Options\SurveilansHaisOptions::RUTE_ANTIBIOTIK;
    $opsiIndikasi = \App\Support\Options\SurveilansHaisOptions::INDIKASI_ANTIBIOTIK;
@endphp

<div>
    {{-- ══ KARTU RINGKAS ══ --}}
    @php $jumlahEntri = count($entriList ?? []); @endphp
    <div class="p-5 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-2">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Surveilans Infeksi Saluran Kemih</h3>
                    @if ($jumlahEntri > 0)
                        <x-badge variant="success">{{ $jumlahEntri }} entri</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-sm text-muted dark:text-gray-400">
                    Pemantauan ISK terkait pemasangan kateter urin (SPP / douer / intermiten / kondom) — lama
                    pemasangan, tanda infeksi per kelompok usia, pemeriksaan leukosit urin &amp; biakan urin.
                    Diisi IPCLN / Perawat ruangan.
                </p>
            </div>
            <div class="flex shrink-0">
                <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                    wire:target="openModal" :disabled="$disabled || !$riHdrNo" class="gap-2">
                    <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        Buka Formulir
                    </span>
                    <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                        <x-loading class="w-4 h-4" /> Memuat...
                    </span>
                </x-primary-button>
            </div>
        </div>
    </div>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="rm-surveilans-isk-ri-{{ $riHdrNo }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-surveilans-isk-ri', [$riHdrNo ?? 'new', $editingKey ?? 'baru']) }}">
            {{-- JUDUL + TOMBOL TUTUP SEBARIS — judul di kiri, X di kanan, paling atas modal --}}
            <div class="px-6 py-2.5 border-b shrink-0 bg-surface-soft border-hairline dark:border-gray-700">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex items-center gap-2.5">
                        <div class="flex items-center justify-center w-7 h-7 rounded-lg shrink-0 bg-brand-green/10 dark:bg-brand-lime/15">
                            <svg class="w-4 h-4 text-brand-green dark:text-brand-lime" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l5.5 6.5a5.5 5.5 0 11-11 0L12 3z" />
                            </svg>
                        </div>
                        <div class="flex items-baseline gap-2 min-w-0">
                            <h2 class="text-sm truncate shrink-0 font-semibold text-ink dark:text-gray-100">Surveilans Infeksi Saluran Kemih</h2>
                            <p class="truncate text-xs text-muted dark:text-gray-400">Formulir Surveilans HAIs — diisi IPCLN / Perawat ruangan.</p>
                        </div>
                    </div>
                <x-icon-button color="gray" type="button" wire:click="closeModal" class="ml-auto shrink-0">
                    <span class="sr-only">Tutup</span>
                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </x-icon-button>
                </div>
            </div>

            {{-- DISPLAY PASIEN — paling atas, mengikuti pola EMR --}}
            <div class="px-4 pt-2">
                <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                    wire:key="surveilans-isk-display-pasien-{{ $riHdrNo }}" />
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="w-full space-y-4">

                    @if ($isFormLocked)
                        <div class="px-4 py-2 text-sm border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
                            Mode tampilan saja (read-only) — pasien sudah pulang / form terkunci.
                        </div>
                    @elseif ($viewOnly)
                        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm border rounded-lg text-blue-800 bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-300">
                            <span>Menampilkan entri terkunci — hanya lihat.</span>
                            <x-secondary-button type="button" wire:click="cancelEdit" class="px-3 py-1 text-sm">Kembali ke entri baru</x-secondary-button>
                        </div>
                    @elseif ($editingKey)
                        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
                            <span>Melanjutkan draft entri <b>{{ $editingKey }}</b>.</span>
                            <x-secondary-button type="button" wire:click="cancelEdit" class="px-3 py-1 text-sm">Batal / entri baru</x-secondary-button>
                        </div>
                    @endif

                    {{-- PANEL KRITERIA KASUS (gaya biru-info standar, default tertutup) --}}
                    <div class="overflow-hidden border border-blue-200 rounded-2xl bg-blue-50 dark:bg-blue-900/20 dark:border-blue-700"
                        x-data="{ showKriteria: false }">
                        <button type="button" x-on:click="showKriteria = !showKriteria"
                            class="flex items-center justify-between w-full px-4 py-2.5 text-left transition-colors hover:bg-blue-100 dark:hover:bg-blue-900/30">
                            <span class="flex items-center gap-2 text-base font-semibold text-blue-900 dark:text-blue-200">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Kriteria Kasus ISK (CAUTI) — Kapan Dihitung Insiden
                            </span>
                            <svg class="w-4 h-4 text-blue-600 transition-transform" :class="showKriteria && 'rotate-180'" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div x-show="showKriteria" x-collapse style="display:none" class="px-4 pb-4 space-y-3">
                        <div>
                            <p class="mb-1.5 text-sm font-semibold text-ink dark:text-gray-200">Definisi (NHSN 2021):</p>
                            <p class="text-sm text-body dark:text-gray-300">Infeksi saluran kemih pada pasien yang terpasang kateter urine menetap setelah <b>2 hari kalender berturut-turut</b>.</p>
                        </div>
                        <div class="pt-2 border-t border-blue-200/60 dark:border-blue-700/60">
                            <p class="mb-1.5 text-sm font-semibold text-ink dark:text-gray-200">Kriteria klinis:</p>
                            <ul class="pl-5 space-y-1 text-sm list-disc text-body dark:text-gray-300">
                                <li>Kateter sudah terpasang 2 hari kalender saat spesimen diambil.</li>
                                <li>Minimal salah satu tanda: demam &gt;38&deg;C, nyeri suprapubik, nyeri sudut kostovertebral, <i>urgency</i>, <i>frequency</i>, atau disuria.</li>
                                <li>Kultur urine <b>&ge; 10&sup5; CFU/ml</b> dengan tidak lebih dari 2 spesies mikroorganisme.</li>
                            </ul>
                        </div>
                        <div class="pt-2 border-t border-blue-200/60 dark:border-blue-700/60">
                            <p class="mb-1.5 text-sm font-semibold text-ink dark:text-gray-200">Cara entri ini dihitung di Laporan Surveilans HAIs:</p>
                            <ul class="pl-5 space-y-1 text-sm list-disc text-body dark:text-gray-300">
                                <li><b>Insiden ISK</b> bila: ada tanda klinis dicentang pada baris pemasangan yang kateternya sudah terpasang <b>&ge; 3 hari kalender</b> (hari pasang = hari ke-1, sesuai syarat &gt;2 hari kalender) + <b>ada minimal satu baris Hasil Biakan Urin</b> terisi.</li>
                                <li>Tanggal pasang/lepas di sini dipakai untuk <b>syarat &ge;3 hari</b> dan menentukan bulan kasus. <b>Penyebutnya</b> (hari kateter urine) diambil dari <b>Observasi &rarr; Alat Invasif</b>, yang diisi perawat ruangan untuk semua pasien terpasang kateter — pastikan entri di sana juga ada.</li>
                            </ul>
                        </div>
                        <div class="pt-2 border-t border-blue-200/60 dark:border-blue-700/60">
                            <p class="text-sm text-body dark:text-gray-300">
                                <b>Penetapan kasus resmi</b> tetap gabungan <b>gejala klinis + pemeriksaan penunjang + diagnosis DPJP</b>.
                                Isi formulir seapa adanya; angka insiden di laporan manajemen dihitung dari centangan ini dan
                                tetap perlu diverifikasi IPCN sebelum dilaporkan keluar.
                            </p>
                        </div>
                        </div>
                    </div>

                    @php $formReadOnly = $isFormLocked || $viewOnly; @endphp

                    @if ($this->diForm())
                    <fieldset @disabled($formReadOnly) class="space-y-4">

                        {{-- 1. DATA DASAR --}}
                        <x-border-form title="1. Data Dasar Surveilans" :collapsible="true" :open="true">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <x-input-label value="Tanggal / Jam Surveilans *" />
                                    <div class="flex gap-1 mt-1">
                                        <x-text-input wire:model="newForm.tanggal" class="w-full" placeholder="dd/mm/yyyy HH:mm:ss"
                                            :error="$errors->has('newForm.tanggal')" />
                                        <x-now-button wire:click="setNow('tanggal')" :disabled="$formReadOnly" />
                                    </div>
                                    <x-input-error :messages="$errors->get('newForm.tanggal')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Kelompok Usia *" />
                                    <x-select-input wire:model.live="newForm.kelompokUsia" class="w-full mt-1"
                                        :error="$errors->has('newForm.kelompokUsia')">
                                        <option value="">—</option>
                                        @foreach ($opsiKelompokUsia as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('newForm.kelompokUsia')" class="mt-1" />
                                </div>
                                <div class="sm:col-span-2 lg:col-span-4">
                                    <x-input-label value="Diagnosis Akhir" />
                                    <x-text-input wire:model="newForm.diagnosisAkhir" class="w-full mt-1" placeholder="Diagnosis akhir / SMF utama" />
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 2. FAKTOR RISIKO --}}
                        <x-border-form title="2. Faktor Risiko" :collapsible="true" :open="true">
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($opsiFaktorRisiko as $key => $label)
                                    <x-toggle wire:key="fr-{{ $key }}" wire:model="newForm.faktorRisiko.{{ $key }}"
                                        :current="(bool) ($newForm['faktorRisiko'][$key] ?? false)" :disabled="$formReadOnly"
                                        :label="$label" :trueValue="true" :falseValue="false" />
                                @endforeach
                            </div>
                        </x-border-form>

                        {{-- 3. PEMASANGAN KATETER URIN --}}
                        <x-border-form title="3. Pemasangan Kateter Urin" :collapsible="true" :open="true">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <x-input-label value="Jenis Kateter" />
                                    <x-select-input wire:model="newForm.jenisKateter" class="w-full mt-1">
                                        <option value="">—</option>
                                        @foreach ($opsiJenisKateter as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </x-select-input>
                                </div>
                                <div class="sm:col-span-2">
                                    <x-input-label value="Tanggal / Jam Pasang Pertama" />
                                    <div class="flex gap-1 mt-1">
                                        <x-text-input wire:model="newForm.tglPasangKateter" class="w-full" placeholder="dd/mm/yyyy HH:mm:ss" />
                                        <x-now-button wire:click="setNow('tglPasangKateter')" :disabled="$formReadOnly" />
                                    </div>
                                </div>
                            </div>

                            @php
                                $usia = $newForm['kelompokUsia'] ?? '';
                                $opsiTanda = $usia === 'balita' ? $opsiTandaBalita : ($usia === 'dewasa' ? $opsiTandaDewasa : []);
                            @endphp

                            <div class="mt-4">
                                <p class="mb-2 text-xs font-semibold tracking-wide uppercase text-muted-soft">
                                    Riwayat Pemasangan &amp; Tanda Infeksi
                                    @if ($usia)
                                        — {{ $opsiKelompokUsia[$usia] ?? '' }}
                                    @endif
                                </p>

                                @if (!$usia)
                                    <p class="px-3 py-2 text-sm border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
                                        Pilih <b>Kelompok Usia</b> pada bagian 1 agar daftar tanda infeksi yang sesuai muncul.
                                    </p>
                                @endif

                                @php $daftarPasang = $newForm['pemasangan'] ?? []; @endphp
                                @if (count($daftarPasang) > 0)
                                    <div class="mb-3 overflow-x-auto">
                                        <table class="w-full overflow-hidden text-sm border rounded-lg border-hairline dark:border-gray-700">
                                            <thead class="uppercase bg-surface-soft dark:bg-gray-800 text-muted dark:text-gray-400">
                                                <tr>
                                                    <th class="px-3 py-2 text-left">Pemasangan</th>
                                                    <th class="px-3 py-2 text-left">Tgl Pasang</th>
                                                    <th class="px-3 py-2 text-left">s/d Tgl Lepas</th>
                                                    <th class="px-3 py-2 text-left">Hari Ke</th>
                                                    <th class="px-3 py-2 text-left">Tanda Infeksi</th>
                                                    @unless ($formReadOnly)
                                                        <th class="px-3 py-2 text-center">Aksi</th>
                                                    @endunless
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-hairline-soft dark:divide-gray-700">
                                                @foreach ($daftarPasang as $indeks => $baris)
                                                    @php
                                                        $tandaBaris = collect($opsiTanda ?: ($opsiTandaBalita + $opsiTandaDewasa))
                                                            ->filter(fn($lbl, $k) => !empty($baris['tanda'][$k]))
                                                            ->values()
                                                            ->implode(', ');
                                                    @endphp
                                                    <tr wire:key="pasang-{{ $indeks }}" class="align-top bg-canvas dark:bg-gray-900">
                                                        <td class="px-3 py-2 font-medium text-ink dark:text-gray-100">{{ $baris['pasangKe'] ?: '-' }}</td>
                                                        <td class="px-3 py-2 font-mono text-muted">{{ $baris['tglMulai'] ?: '-' }}</td>
                                                        <td class="px-3 py-2 font-mono text-muted">{{ $baris['tglSelesai'] ?: '-' }}</td>
                                                        <td class="px-3 py-2 text-muted">{{ $baris['hariKe'] ?: '-' }}</td>
                                                        <td class="px-3 py-2 text-body dark:text-gray-300">{{ $tandaBaris ?: '-' }}</td>
                                                        @unless ($formReadOnly)
                                                            <td class="px-3 py-2 text-center">
                                                                <x-outline-button type="button" wire:click.prevent="hapusPemasangan({{ $indeks }})"
                                                                    wire:confirm="Hapus baris pemasangan ini dari daftar?" wire:loading.attr="disabled"
                                                                    class="!px-2 !py-1 !text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30"
                                                                    title="Hapus dari daftar">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                    </svg>
                                                                </x-outline-button>
                                                            </td>
                                                        @endunless
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="mb-3 text-sm italic text-muted-soft">Belum ada riwayat pemasangan pada daftar.</p>
                                @endif

                                @unless ($formReadOnly)
                                    <div class="p-3 border border-dashed rounded-lg border-gray-300 dark:border-gray-600 bg-canvas dark:bg-gray-800/50">
                                        <p class="mb-3 text-sm font-semibold tracking-wide uppercase text-ink dark:text-white">Tambah Baris Pemasangan</p>
                                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-12">
                                            <div class="sm:col-span-4">
                                                <x-input-label value="Pemasangan" class="text-xs" />
                                                <x-text-input wire:model="barisPasang.pasangKe" class="w-full mt-1" placeholder="mis. pasang ke-1 (douer)" />
                                            </div>
                                            <div class="sm:col-span-3">
                                                <x-input-label value="Tgl Pasang" class="text-xs" />
                                                <div class="flex gap-1 mt-1">
                                                    <x-text-input wire:model="barisPasang.tglMulai" class="w-full" placeholder="dd/mm/yyyy HH:mm:ss" />
                                                    <x-now-button wire:click="setNowPasang('tglMulai')" />
                                                </div>
                                            </div>
                                            <div class="sm:col-span-3">
                                                <x-input-label value="s/d Tgl Lepas" class="text-xs" />
                                                <div class="flex gap-1 mt-1">
                                                    <x-text-input wire:model="barisPasang.tglSelesai" class="w-full" placeholder="dd/mm/yyyy HH:mm:ss" />
                                                    <x-now-button wire:click="setNowPasang('tglSelesai')" />
                                                </div>
                                            </div>
                                            <div class="sm:col-span-2">
                                                <x-input-label value="Hari Ke" class="text-xs" />
                                                <x-text-input wire:model="barisPasang.hariKe" class="w-full mt-1" placeholder="mis. 3" />
                                            </div>
                                        </div>

                                        @if ($opsiTanda)
                                            <div class="grid grid-cols-2 gap-2 pt-3 mt-3 border-t sm:grid-cols-3 lg:grid-cols-5 border-hairline dark:border-gray-700">
                                                @foreach ($opsiTanda as $key => $label)
                                                    <x-toggle wire:key="tanda-baru-{{ $key }}" wire:model="barisPasang.tanda.{{ $key }}"
                                                        :current="(bool) ($barisPasang['tanda'][$key] ?? false)"
                                                        :label="$label" :trueValue="true" :falseValue="false" />
                                                @endforeach
                                            </div>
                                        @endif

                                        <div class="flex justify-end mt-3">
                                            <x-primary-button type="button" wire:click="tambahPemasangan" class="gap-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                </svg>
                                                Tambah
                                            </x-primary-button>
                                        </div>
                                    </div>
                                @endunless
                            </div>
                        </x-border-form>

                        {{-- 4. PEMERIKSAAN URIN --}}
                        <x-border-form title="4. Pemeriksaan & Biakan Urin" :collapsible="true" :open="true">
                            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                                <div class="space-y-3">
                                    {{-- Tak ada lagi toggle "Dilakukan": ada-tidaknya baris hasil di bawah
                                         sudah menjawabnya, dan itu pula yang dibaca aturan kasus ISK. --}}
                                    <x-surveilans.kultur-list namaDaftar="pemeriksaanUrinHasil" title="Hasil Pemeriksaan Urin"
                                        :barisList="$newForm['pemeriksaanUrinHasil'] ?? []" :barisBaru="$barisKultur['pemeriksaanUrinHasil'] ?? []"
                                        :formReadOnly="$formReadOnly" hasilLabel="Leukosit Urin" hasilPlaceholder="Leukosit urin"
                                        kosongTeks="Belum ada hasil pemeriksaan urin." />
                                </div>
                                <div class="space-y-3">
                                    <x-surveilans.kultur-list namaDaftar="biakanUrinHasil" title="Hasil Biakan Urin"
                                        :barisList="$newForm['biakanUrinHasil'] ?? []" :barisBaru="$barisKultur['biakanUrinHasil'] ?? []"
                                        :formReadOnly="$formReadOnly" hasilLabel="Hasil" hasilPlaceholder="Hasil biakan urin"
                                        kosongTeks="Belum ada hasil biakan urin." />
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 5. PEMAKAIAN ANTIBIOTIK --}}
                        <x-border-form title="5. Pemakaian Antibiotik" :collapsible="true" :open="true">
                            {{-- Tak ada toggle "Ada Pemakaian Antibiotik": daftar di bawah sudah menjawabnya. --}}
                            <div>
                                <x-surveilans.antibiotik-list :barisList="$newForm['antibiotik'] ?? []" :barisBaru="$barisObat"
                                    :formReadOnly="$formReadOnly" :opsiRute="$opsiRute" :opsiIndikasi="$opsiIndikasi" />
                            </div>
                        </x-border-form>

                        {{-- 6. PENUTUP & TTD --}}
                        <x-border-form title="6. Catatan & Tanda Tangan" :collapsible="true" :open="true">
                            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                <div class="space-y-3">
                                    <div>
                                        <x-input-label value="Mengetahui — Dokter yang Merawat" />
                                        {{-- Terisi otomatis dari DPJP Utama (Leveling Dokter, Pengkajian Awal RI),
                                             tetap bisa diganti — penanda tangan tak selalu DPJP Utama. --}}
                                        <div class="mt-1">
                                            <x-ppa-combobox wireModel="newForm.dokterMerawat" :disabled="$formReadOnly"
                                                placeholder="Nama dokter — pilih dari daftar atau ketik" />
                                        </div>
                                    </div>
                                    <div>
                                        <x-input-label value="Catatan" />
                                        <x-textarea wire:model="newForm.catatan" rows="3" class="w-full mt-1" placeholder="Catatan tambahan surveilans" />
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <x-signature.ttd-petugas :framed="false" :allowClear="false"
                                        :ttd="$newForm['ttd'] ?? ''" :date="$newForm['ttdDate'] ?? ''"
                                        :code="$newForm['ttdCode'] ?? ''" :locked="$formReadOnly"
                                        sign="ttdSaya" nameLabel="Perawat / IPCLN" dateLabel="Jam TTD" signLabel="TTD & Kunci" />
                                    <p class="text-xs text-muted dark:text-gray-400">
                                        TTD petugas = memvalidasi &amp; <strong>mengunci</strong> entri surveilans ini.
                                        Kolom wajib yang belum terisi akan ditandai merah lebih dulu.
                                    </p>
                                </div>
                            </div>
                        </x-border-form>

                    </fieldset>

                    {{-- ══ DAFTAR ENTRI ══ --}}
                    @endif
                    @unless ($this->diForm())
                    <x-border-form padding="p-0">
                        @forelse ($entriList as $entri)
                            @php
                                $rowKey = $entri['createdAt'] ?? '';
                                $rowFinal = $this->entryIsFinal($entri);
                            @endphp
                            <div wire:key="entri-{{ $rowKey }}"
                                class="flex flex-wrap items-center justify-between gap-3 px-3 py-2 mb-2 border rounded-lg border-hairline dark:border-gray-700">
                                <div class="text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-ink dark:text-gray-100">{{ $entri['tanggal'] ?: $rowKey }}</span>
                                        @if ($rowFinal)
                                            <x-badge variant="success">Terkunci</x-badge>
                                        @else
                                            <x-badge variant="warning">Draft</x-badge>
                                        @endif
                                    </div>
                                    <div class="text-xs text-muted dark:text-gray-400">
                                        {{ $opsiKelompokUsia[$entri['kelompokUsia'] ?? ''] ?? '-' }}
                                        · Kateter: {{ $opsiJenisKateter[$entri['jenisKateter'] ?? ''] ?? '-' }}
                                        · Petugas: {{ $entri['ttd'] ?: '-' }}
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center justify-center gap-1.5">
                                    <div class="flex items-center justify-center gap-2">
                                        @if ($rowFinal)
                                            <x-secondary-button type="button" wire:click="viewEntry('{{ $rowKey }}')" class="px-3 py-1.5 text-sm">Lihat</x-secondary-button>
                                        @else
                                            <x-secondary-button type="button" wire:click="editEntry('{{ $rowKey }}')" class="px-3 py-1.5 text-sm">Lanjutkan Pengisian</x-secondary-button>
                                        @endif
                                        <x-secondary-button type="button" wire:click="cetak('{{ $rowKey }}')" wire:loading.attr="disabled"
                                            wire:target="cetak('{{ $rowKey }}')" class="px-3 py-1.5 text-sm">
                                            <span wire:loading.remove wire:target="cetak('{{ $rowKey }}')">Cetak</span>
                                            <span wire:loading wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5"><x-loading class="w-4 h-4" /> Mencetak...</span>
                                        </x-secondary-button>
                                    </div>
                                    @unless ($isFormLocked)
                                        <div class="flex items-center justify-center gap-2">
                                            @if ($rowFinal)
                                                @can('dokumen.bukaKunci')
                                                    <x-confirm-button action="bukaKunci('{{ $rowKey }}')"
                                                        message="Buka kunci entri surveilans ini? TTD petugas akan dicabut."
                                                        class="px-3 py-1.5 text-sm">Buka Kunci</x-confirm-button>
                                                @endcan
                                            @endif
                                            @can('dokumen.hapus')
                                                <x-outline-button type="button" wire:click.prevent="hapus('{{ $rowKey }}')"
                                                    wire:confirm="Yakin hapus entri surveilans ini?" class="px-3 py-1.5 text-sm !text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30">Hapus</x-outline-button>
                                            @endcan
                                        </div>
                                    @endunless
                                </div>
                            </div>
                        @empty
                            <div class="flex flex-col items-center justify-center gap-3 px-6 py-12">
                                <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                <p class="text-base font-medium text-muted dark:text-gray-400">Belum ada entri surveilans ISK.</p>
                            </div>
                        @endforelse
                    </x-border-form>
                    @endunless

                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 border-t shrink-0 bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                @if ($this->diForm())
                <div class="flex justify-end gap-3">
                    <x-secondary-button type="button" wire:click="kembaliKeDaftar">Kembali ke Daftar</x-secondary-button>
                    @if (!$isFormLocked && !$viewOnly)
                        @if ($editingKey)
                            <x-secondary-button type="button" wire:click="cancelEdit">Batal Edit</x-secondary-button>
                        @endif
                        <x-primary-button type="button" wire:click.prevent="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft">
                            <span wire:loading.remove wire:target="saveDraft">{{ $editingKey ? 'Simpan Perubahan' : 'Simpan Draft' }}</span>
                            <span wire:loading wire:target="saveDraft" class="flex items-center gap-1.5"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                        </x-primary-button>
                    @endif
                </div>
                @else
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                        @unless ($isFormLocked)
                            <x-primary-button type="button" wire:click="tambahEntri" wire:target="tambahEntri"
                                wire:loading.attr="disabled" class="gap-1.5 min-w-[150px] justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Isi Formulir Baru
                            </x-primary-button>
                        @endunless
                    </div>
                @endif
            </div>

        </div>
    </x-modal>
</div>
