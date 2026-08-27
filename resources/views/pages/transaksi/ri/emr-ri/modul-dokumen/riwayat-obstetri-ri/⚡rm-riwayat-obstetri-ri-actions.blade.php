<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/riwayat-obstetri-ri/rm-riwayat-obstetri-ri-actions.blade.php
// Dokumen VK/Kebidanan — Riwayat Obstetri (RM 44.b).
// Header G-P-A + tabel riwayat kehamilan lalu (repeating rows, nested di dalam tiap entri).
// Pola: multi-entri (Draft + Lanjutkan Pengisian + TTD-Kunci + Lihat read-only + tabel expandable),
// disimpan FLAT ke datadaftarri_json → $data['riwayatObstetriRI'][] (tiap entri = 1 catatan G-P-A + rows).
// Kunci entri stabil = createdAt. TTD = stempel nama user login (ttdSaya = FINALIZE/kunci), tanpa TTD gambar.

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
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
    protected array $renderAreas = ['modal-riwayat-obstetri-ri'];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'riwayatObstetriRI';

    public array $newForm = [
        'gravida' => '',
        'para'    => '',
        'abortus' => '',
        'ttd'     => '',   // nama penanda-tangan (myuser_name)
        'ttdDate' => '',   // tgl/jam TTD (d/m/Y H:i:s)
        'ttdCode' => '',   // myuser_code penanda-tangan
        'rows'    => [],    // tabel riwayat kehamilan lalu (nested di dalam entri)
    ];

    public array $entriList = [];

    // Kunci entri yang sedang diedit (createdAt = kunci stabil, di-set saat entri pertama dibuat).
    // null = sedang membuat entri baru.
    public ?string $editingKey = null;

    // Layar aktif di modal: 'daftar' (grid entri) atau 'form' (tambah/edit/lihat).
    // Formulir sengaja tidak nongkrong bersama daftarnya: dulu ia ikut tampil terus lalu
    // dikosongkan diam-diam sesudah tersimpan, dan petugas yang mengira itu masih formulir
    // yang tadi diisi mengetik ulang — tersimpan sebagai draft baru.
    public string $layar = 'daftar';

    // true = entri terkunci sedang ditampilkan di form dalam mode read-only (lihat saja, tak bisa edit).
    public bool $viewOnly = false;

    protected function rules(): array
    {
        return [];
    }

    protected function messages(): array
    {
        return [];
    }

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-riwayat-obstetri-ri']);

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

    /* ===============================
     | OPEN / CLOSE MODAL
     =============================== */
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

        $this->incrementVersion('modal-riwayat-obstetri-ri');
        $this->layar = 'daftar';
        $this->dispatch('open-modal', name: 'riwayat-obstetri-ri');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'riwayat-obstetri-ri');
    }

    /* ===============================
     | BARIS RIWAYAT KEHAMILAN LALU (nested di dalam entri)
     =============================== */
    private function emptyRow(): array
    {
        return [
            'kehamilan'        => '',   // Aterm | Prematur | Imatur | Abortus
            'caraPersalinan'   => '',   // Spontan | Tindakan | Operasi
            'tempat'           => '',   // Rumah | PKM | Klinik | RS
            'penolong'         => '',   // Dukun | Bidan | Perawat | Dokter | Lain
            'komplikasi'       => '',
            'jenisKelaminAnak' => '',   // L | P
            'keadaanAnak'      => '',   // Hidup | Mati
            'umurAnak'         => '',
            'bbl'              => '',    // gram
            'keterangan'       => '',
            // Petugas penambah baris — di-stempel otomatis saat Tambah (pola Obat & Cairan RI).
            'petugas'          => '',   // myuser_name saat baris ditambahkan
            'petugasCode'      => '',   // myuser_code saat baris ditambahkan
        ];
    }

    // Field entri baris — diisi di atas tabel, lalu ditambahkan lewat tombol Tambah.
    public string $barisKehamilan = '';
    public string $barisCaraPersalinan = '';
    public string $barisTempat = '';
    public string $barisPenolong = '';
    public string $barisKomplikasi = '';
    public string $barisJenisKelaminAnak = '';
    public string $barisKeadaanAnak = '';
    public string $barisUmurAnak = '';
    public string $barisBbl = '';
    public string $barisKeterangan = '';

    // Baris lama bisa saja belum punya semua kolom — lengkapi dengan bentuk baris kosong.
    private function normalizeRows(array $entry): array
    {
        $daftarBaris = $entry['rows'] ?? [];
        if (!is_array($daftarBaris)) {
            return [];
        }

        return array_values(
            array_map(
                fn($baris) => array_replace($this->emptyRow(), is_array($baris) ? $baris : []),
                $daftarBaris,
            ),
        );
    }

    private function resetBarisInput(): void
    {
        $this->barisKehamilan = '';
        $this->barisCaraPersalinan = '';
        $this->barisTempat = '';
        $this->barisPenolong = '';
        $this->barisKomplikasi = '';
        $this->barisJenisKelaminAnak = '';
        $this->barisKeadaanAnak = '';
        $this->barisUmurAnak = '';
        $this->barisBbl = '';
        $this->barisKeterangan = '';
    }

    public function tambahBaris(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }

        // validate() didahulukan supaya field yang kosong tetap ditandai merah.
        $this->validateWithToast(
            [
                'barisKehamilan' => ['required', 'string', 'max:50'],
                'barisCaraPersalinan' => ['nullable', 'string', 'max:50'],
                'barisTempat' => ['nullable', 'string', 'max:50'],
                'barisPenolong' => ['nullable', 'string', 'max:50'],
                'barisKomplikasi' => ['nullable', 'string', 'max:200'],
                'barisJenisKelaminAnak' => ['nullable', 'string', 'max:10'],
                'barisKeadaanAnak' => ['nullable', 'string', 'max:20'],
                'barisUmurAnak' => ['nullable', 'string', 'max:50'],
                'barisBbl' => ['nullable', 'numeric'],
                'barisKeterangan' => ['nullable', 'string', 'max:200'],
            ],
            [],
            [
                'barisKehamilan' => 'Kehamilan',
                'barisCaraPersalinan' => 'Cara Persalinan',
                'barisTempat' => 'Tempat',
                'barisPenolong' => 'Penolong',
                'barisKomplikasi' => 'Komplikasi',
                'barisJenisKelaminAnak' => 'Jenis Kelamin Anak',
                'barisKeadaanAnak' => 'Keadaan Anak',
                'barisUmurAnak' => 'Umur Anak',
                'barisBbl' => 'Berat Badan Lahir',
                'barisKeterangan' => 'Keterangan',
            ],
        );

        $daftarBaris = $this->normalizeRows($this->newForm);
        $daftarBaris[] = array_replace($this->emptyRow(), [
            'kehamilan' => $this->barisKehamilan,
            'caraPersalinan' => $this->barisCaraPersalinan,
            'tempat' => $this->barisTempat,
            'penolong' => $this->barisPenolong,
            'komplikasi' => $this->barisKomplikasi,
            'jenisKelaminAnak' => $this->barisJenisKelaminAnak,
            'keadaanAnak' => $this->barisKeadaanAnak,
            'umurAnak' => $this->barisUmurAnak,
            'bbl' => $this->barisBbl,
            'keterangan' => $this->barisKeterangan,
            'petugas' => auth()->user()->myuser_name ?? '',
            'petugasCode' => auth()->user()->myuser_code ?? '',
        ]);
        $this->newForm['rows'] = $daftarBaris;

        $this->resetBarisInput();
    }

    public function hapusBaris(int $index): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $daftarBaris = $this->normalizeRows($this->newForm);
        unset($daftarBaris[$index]);
        $this->newForm['rows'] = array_values($daftarBaris);
    }

    /* ===============================
     | HELPER — status & bentuk entri
     =============================== */
    // Entri dianggap FINAL/terkunci bila flag finalized true; entri lama (tanpa flag) yang sudah
    // ada TTD (nama penanda) dianggap final (kompatibilitas data lama).
    public function entryIsFinal(array $entri): bool
    {
        return array_key_exists('finalized', $entri) ? (bool) $entri['finalized'] : !empty($entri['ttd']);
    }

    // Susun array entri dari state form. $key = createdAt (kunci stabil); $finalized = status kunci.
    private function buildEntry(string $key, bool $finalized): array
    {
        return [
            'gravida'   => $this->newForm['gravida'] ?? '',
            'para'      => $this->newForm['para'] ?? '',
            'abortus'   => $this->newForm['abortus'] ?? '',
            'rows'      => $this->normalizeRows($this->newForm),
            'ttd'       => $this->newForm['ttd'] ?? '',
            'ttdCode'   => $this->newForm['ttdCode'] ?? '',
            'ttdDate'   => $this->newForm['ttdDate'] ?? '',
            'createdAt' => $key,
            'finalized' => $finalized,
        ];
    }

    // Cek: minimal G/P/A terisi atau ada minimal 1 baris riwayat kehamilan.
    private function adaIsiInti(): bool
    {
        if (collect(['gravida', 'para', 'abortus'])->contains(fn($k) => filled($this->newForm[$k] ?? null))) {
            return true;
        }
        return !empty($this->newForm['rows']);
    }

    // Simpan entri (add/update by createdAt) dengan status $finalized. Dipakai draft & kunci.
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
            $idx = collect($list)->search(fn($it) => ($it['createdAt'] ?? '') === $key);
            if ($idx === false) {
                $list[] = $entry;
            } else {
                if ($this->entryIsFinal($list[$idx])) {
                    throw new \RuntimeException('Entri sudah terkunci, tidak dapat diubah.');
                }
                $list[$idx] = $entry;
            }
            $fresh[$this->jsonKey] = array_values($list);

            $this->updateJsonRI((int) $this->riHdrNo, $fresh);
            $this->dataDaftarRi = $fresh;
            $this->entriList = $fresh[$this->jsonKey];

            $gpa = 'G' . (($entry['gravida'] ?? '') ?: '-') . 'P' . (($entry['para'] ?? '') ?: '-') . 'A' . (($entry['abortus'] ?? '') ?: '-');
            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Riwayat Obstetri — ' . $gpa . ' (' . $key . ')', 'MR');
        });
    }

    /* ===============================
     | SIMPAN DRAFT (nyicil, tanpa wajib TTD)
     =============================== */
    public function saveDraft(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }
        if (!$this->adaIsiInti()) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal G/P/A atau tambah salah satu baris riwayat kehamilan.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-riwayat-obstetri-ri');
            $this->dispatch('toast', type: 'success', message: 'Draft tersimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TTD PETUGAS = FINALIZE (kunci entri)
     | Stempel nama user login + tgl/jam → kunci entri.
     =============================== */
    public function ttdSaya(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (!$this->adaIsiInti()) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal G/P/A atau baris riwayat kehamilan sebelum TTD.');
            return;
        }

        // Stempel TTD petugas = user login.
        $this->newForm['ttd']     = auth()->user()->myuser_name ?? '';
        $this->newForm['ttdCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['ttdDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, true, 'Kunci (TTD)');
            $this->resetNewForm();
            $this->editingKey = null;
            $this->viewOnly = false;
            $this->incrementVersion('modal-riwayat-obstetri-ri');
            $this->dispatch('toast', type: 'success', message: 'Riwayat obstetri ditandatangani & terkunci.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /** Batalkan TTD pada form (saat draft/edit, sebelum finalize benar-benar tersimpan). */
    public function hapusTtd(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['ttd']     = '';
        $this->newForm['ttdCode'] = '';
        $this->newForm['ttdDate'] = '';
    }

    /* ===============================
     | BUKA KUNCI (Gate dokumen.bukaKunci) — cabut TTD petugas, entri kembali Draft.
     =============================== */
    public function bukaKunci(string $createdAt): void
    {
        if (!auth()->user()?->can('dokumen.bukaKunci')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang membuka kunci.');
            return;
        }
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        try {
            DB::transaction(function () use ($createdAt) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $list = is_array($fresh[$this->jsonKey] ?? null) ? $fresh[$this->jsonKey] : [];
                $index = collect($list)->search(fn($item) => ($item['createdAt'] ?? '') === $createdAt);
                if ($index === false) {
                    throw new \RuntimeException('Entri tidak ditemukan.');
                }

                $list[$index]['finalized'] = false;
                $list[$index]['ttd'] = '';
                $list[$index]['ttdCode'] = '';
                $list[$index]['ttdDate'] = '';
                $fresh[$this->jsonKey] = array_values($list);

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->entriList = $fresh[$this->jsonKey];

                $pembukaKunci = auth()->user()->myuser_name ?? '-';
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Buka kunci Riwayat Obstetri (' . $createdAt . ') oleh ' . $pembukaKunci . ' — TTD petugas dicabut', 'MR');
            });

            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }
            $this->incrementVersion('modal-riwayat-obstetri-ri');
            $this->dispatch('toast', type: 'success', message: 'Kunci dibuka — TTD petugas dicabut, entri kembali Draft.');
        } catch (\RuntimeException $exception) {
            $this->dispatch('toast', type: 'error', message: $exception->getMessage());
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuka kunci: ' . $exception->getMessage());
        }
    }

    /* ===============================
     | EDIT / LIHAT / BATAL entri
     =============================== */
    // Muat 1 entri ke form atas (dipakai edit draft & lihat entri terkunci). TANPA TTD gambar.
    private function hydrateFormFromEntry(array $entry, string $key): void
    {
        foreach ($this->newForm as $k => $v) {
            $this->newForm[$k] = $entry[$k] ?? (is_array($v) ? [] : '');
        }
        $this->newForm['rows'] = $this->normalizeRows($this->newForm);
        $this->resetBarisInput();
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-riwayat-obstetri-ri');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->entriList)->firstWhere('createdAt', $key);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }
        if ($this->entryIsFinal($entry)) {
            $this->dispatch('toast', type: 'warning', message: 'Entri sudah terkunci, tidak dapat diedit.');
            return;
        }

        $this->viewOnly = false;
        $this->hydrateFormFromEntry($entry, $key);
        $this->dispatch('toast', type: 'info', message: 'Draft dimuat untuk dilanjutkan.');
    }

    // Lihat entri terkunci: muat ke form atas dalam mode read-only.
    public function viewEntry(string $key): void
    {
        $entry = collect($this->entriList)->firstWhere('createdAt', $key);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }

        $this->viewOnly = true;
        $this->hydrateFormFromEntry($entry, $key);
        $this->dispatch('toast', type: 'info', message: 'Menampilkan entri terkunci (hanya lihat).');
    }

    public function cancelEdit(): void
    {
        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();
        $this->incrementVersion('modal-riwayat-obstetri-ri');
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

    private function resetNewForm(): void
    {
        $this->newForm = [
            'gravida' => '',
            'para'    => '',
            'abortus' => '',
            'ttd'     => '',
            'ttdCode' => '',
            'ttdDate' => '',
            'rows'    => [],
        ];
        $this->resetBarisInput();
        $this->layar = 'daftar';   // mengosongkan formulir = kembali ke daftar
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRi = [];
        $this->entriList = [];
        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
    }

    /* ===============================
     | HAPUS entri (final atau draft)
     =============================== */
    public function hapus(string $createdAt): void
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
            DB::transaction(function () use ($createdAt) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $fresh[$this->jsonKey] = collect($fresh[$this->jsonKey] ?? [])
                    ->reject(fn($entri) => ($entri['createdAt'] ?? null) === $createdAt)
                    ->values()
                    ->all();

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->entriList = $fresh[$this->jsonKey];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Riwayat Obstetri — ' . $createdAt, 'MR');
            });

            // Jika entri yang dihapus sedang di form, kosongkan form.
            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }

            $this->incrementVersion('modal-riwayat-obstetri-ri');
            $this->dispatch('toast', type: 'success', message: 'Entri dihapus.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK (per-entri by createdAt)
     =============================== */
    public function cetak(string $createdAt)
    {
        $entry = collect($this->entriList)->firstWhere('createdAt', $createdAt);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data riwayat obstetri tidak ditemukan.');
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

            // TTD (myuser_code -> myuser_ttd_image) untuk stempel di cetakan
            $ttdPath = null;
            $ttdCode = $entry['ttdCode'] ?? null;
            if ($ttdCode) {
                $ttdImg = DB::table('users')->where('myuser_code', $ttdCode)->value('myuser_ttd_image');
                if (!empty($ttdImg) && file_exists(public_path('storage/' . $ttdImg))) {
                    $ttdPath = public_path('storage/' . $ttdImg);
                }
            }

            $data = array_merge($pasien, [
                'ttdPath'      => $ttdPath,
                'dataRi'       => $this->dataDaftarRi,
                'form'         => $entry,
                'identitasRs'  => $identitasRs,
                'tglCetak'     => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);
            $pdf = Pdf::loadView('pages.components.modul-dokumen.ri.riwayat-obstetri-ri.cetak-riwayat-obstetri-ri-print', ['data' => $data])->setPaper('A4', 'landscape');

            return response()->streamDownload(fn() => print $pdf->output(), 'riwayat-obstetri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline di tab) ══ --}}
    @php $roCount = count($entriList ?? []); @endphp
    <div class="p-5 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-2">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Riwayat Obstetri</h3>
                    @if ($roCount > 0)
                        <x-badge variant="success">{{ $roCount }} entri</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-sm text-muted dark:text-gray-400">
                    Riwayat kehamilan/persalinan yang lalu (RM 44.b) — tabel per kehamilan: cara persalinan, tempat,
                    penolong, komplikasi, keadaan &amp; keterangan anak. Header G-P-A. Diisi Bidan/Dokter.
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

        @if ($roCount > 0)
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                    <thead class="bg-surface-soft dark:bg-gray-800">
                        <tr class="text-left text-muted dark:text-gray-300">
                            <th class="px-3 py-2 border-b">Waktu</th>
                            <th class="px-3 py-2 border-b">G-P-A</th>
                            <th class="px-3 py-2 border-b">Petugas (TTD)</th>
                            <th class="px-3 py-2 text-center border-b">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_reverse($entriList) as $entri)
                            <tr class="border-b border-hairline dark:border-gray-700">
                                <td class="px-3 py-2 font-medium text-ink dark:text-gray-200">{{ $entri['createdAt'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-muted dark:text-gray-400">G{{ ($entri['gravida'] ?? '') ?: '-' }}P{{ ($entri['para'] ?? '') ?: '-' }}A{{ ($entri['abortus'] ?? '') ?: '-' }}</td>
                                <td class="px-3 py-2 text-muted dark:text-gray-400">
                                    @if (!empty($entri['ttd'])){{ $entri['ttd'] }}@else<x-badge variant="danger">Belum TTD</x-badge>@endif
                                </td>
                                <td class="px-3 py-2 text-center">
                                    @if ($this->entryIsFinal($entri))
                                        <x-badge variant="info">Terkunci</x-badge>
                                    @else
                                        <x-badge variant="warning">Draft</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="riwayat-obstetri-ri" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
             wire:key="{{ $this->renderKey('modal-riwayat-obstetri-ri', [$riHdrNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="px-6 py-4 border-b shrink-0 bg-surface-soft border-hairline dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                            <svg class="w-6 h-6 text-brand-green dark:text-brand-lime" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Riwayat Obstetri</h2>
                            <p class="mt-0.5 text-sm text-muted dark:text-gray-400">RM 44.b — riwayat kehamilan lalu (VK). Diisi Bidan / Dokter.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        @if (count($entriList) > 0)
                            <x-badge variant="info">{{ count($entriList) }} tersimpan</x-badge>
                        @endif
                        @if ($isFormLocked)
                            <x-badge variant="danger">Read Only</x-badge>
                        @endif
                        <x-icon-button color="gray" type="button" wire:click="closeModal">
                            <span class="sr-only">Tutup</span>
                            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </x-icon-button>
                    </div>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="riwayat-obstetri-display-pasien-{{ $riHdrNo }}" />

                    @php $formReadOnly = $isFormLocked || $viewOnly; @endphp

                    @if ($isFormLocked)
                        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            Mode tampilan saja (read-only) — pasien sudah pulang / form terkunci.
                        </div>
                    @endif

                    @if ($viewOnly)
                        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-sky-700 bg-sky-50 border border-sky-200 rounded-lg dark:bg-sky-900/20 dark:border-sky-600 dark:text-sky-300">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            Menampilkan entri terkunci <strong>{{ $editingKey }}</strong> (hanya lihat) — klik <strong>Selesai Melihat</strong> untuk kembali ke form entri baru.
                        </div>
                    @elseif ($editingKey && !$isFormLocked)
                        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-brand-green bg-brand-lime/10 border border-brand-lime/40 rounded-lg dark:text-brand-lime dark:bg-brand-lime/5">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Sedang melanjutkan entri <strong>{{ $editingKey }}</strong> — <strong>Simpan Perubahan</strong> menyimpan ke entri ini; klik <strong>Entri Baru</strong> untuk menambah catatan lain.
                        </div>
                    @endif

                    {{-- ── FORM ENTRI ── --}}
                    @if ($this->diForm())
                    <fieldset @disabled($formReadOnly) class="space-y-4">

                        {{-- 1. Status Obstetri (G-P-A) --}}
                        <x-border-form title="1. Status Obstetri">
                            <div class="grid grid-cols-3 gap-4 sm:grid-cols-4">
                                <div><x-input-label value="Gravida (G)" /><x-text-input type="number" wire:model="newForm.gravida" class="w-full mt-1" /></div>
                                <div><x-input-label value="Para (P)" /><x-text-input type="number" wire:model="newForm.para" class="w-full mt-1" /></div>
                                <div><x-input-label value="Abortus (A)" /><x-text-input type="number" wire:model="newForm.abortus" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 2. Tabel Riwayat Kehamilan Lalu — isi field di atas, klik Tambah, hasil masuk tabel --}}
                        <x-border-form title="2. Riwayat Kehamilan / Persalinan Yang Lalu">
                            <div class="space-y-2">
                                <div class="overflow-x-auto bg-canvas border rounded-2xl border-hairline dark:border-gray-700">
                                    <table class="ds-table min-w-[1280px]">
                                        <thead>
                                            <tr>
                                                <th class="ds-c w-10">No</th>
                                                <th class="w-32">Kehamilan</th>
                                                <th class="w-36">Cara Persalinan</th>
                                                <th class="w-28">Tempat</th>
                                                <th class="w-32">Penolong</th>
                                                <th class="w-48">Komplikasi</th>
                                                <th class="w-20">JK Anak</th>
                                                <th class="w-28">Keadaan</th>
                                                <th class="w-24">Umur</th>
                                                <th class="w-24">BBL (gr)</th>
                                                <th class="w-56">Keterangan</th>
                                                <th class="w-36">Petugas</th>
                                                <th class="ds-c w-24">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @if (!$formReadOnly)
                                                {{-- Baris ENTRI: sejajar kolom tabel, sekali Tambah langsung masuk daftar --}}
                                                <tr class="align-top bg-surface-soft/70 dark:bg-gray-800/40">
                                                    <td class="ds-c ds-td-meta">+</td>
                                                    <td>
                                                        <x-select-input wire:model="barisKehamilan" :error="$errors->has('barisKehamilan')" class="w-full px-1">
                                                            <option value="">— pilih —</option>
                                                            <option value="Aterm">Aterm</option>
                                                            <option value="Prematur">Prematur</option>
                                                            <option value="Imatur">Imatur</option>
                                                            <option value="Abortus">Abortus</option>
                                                        </x-select-input>
                                                        <x-input-error :messages="$errors->get('barisKehamilan')" class="mt-1" />
                                                    </td>
                                                    <td>
                                                        <x-select-input wire:model="barisCaraPersalinan" :error="$errors->has('barisCaraPersalinan')" class="w-full px-1">
                                                            <option value="">— pilih —</option>
                                                            <option value="Spontan">Spontan</option>
                                                            <option value="Tindakan">Tindakan</option>
                                                            <option value="Operasi">Operasi</option>
                                                        </x-select-input>
                                                    </td>
                                                    <td>
                                                        <x-select-input wire:model="barisTempat" :error="$errors->has('barisTempat')" class="w-full px-1">
                                                            <option value="">— pilih —</option>
                                                            <option value="Rumah">Rumah</option>
                                                            <option value="PKM">PKM</option>
                                                            <option value="Klinik">Klinik</option>
                                                            <option value="RS">RS</option>
                                                        </x-select-input>
                                                    </td>
                                                    <td>
                                                        <x-select-input wire:model="barisPenolong" :error="$errors->has('barisPenolong')" class="w-full px-1">
                                                            <option value="">— pilih —</option>
                                                            <option value="Dukun">Dukun</option>
                                                            <option value="Bidan">Bidan</option>
                                                            <option value="Perawat">Perawat</option>
                                                            <option value="Dokter">Dokter</option>
                                                            <option value="Lain">Lain</option>
                                                        </x-select-input>
                                                    </td>
                                                    <td><x-text-input wire:model="barisKomplikasi" wire:keydown.enter.prevent="tambahBaris" :error="$errors->has('barisKomplikasi')" class="w-full px-2" placeholder="Perdarahan, dll." /></td>
                                                    <td>
                                                        <x-select-input wire:model="barisJenisKelaminAnak" :error="$errors->has('barisJenisKelaminAnak')" class="w-full px-1">
                                                            <option value="">—</option>
                                                            <option value="L">L</option>
                                                            <option value="P">P</option>
                                                        </x-select-input>
                                                    </td>
                                                    <td>
                                                        <x-select-input wire:model="barisKeadaanAnak" :error="$errors->has('barisKeadaanAnak')" class="w-full px-1">
                                                            <option value="">— pilih —</option>
                                                            <option value="Hidup">Hidup</option>
                                                            <option value="Mati">Mati</option>
                                                        </x-select-input>
                                                    </td>
                                                    <td><x-text-input wire:model="barisUmurAnak" wire:keydown.enter.prevent="tambahBaris" :error="$errors->has('barisUmurAnak')" class="w-full px-1" placeholder="th/bl" /></td>
                                                    <td><x-text-input type="number" wire:model="barisBbl" wire:keydown.enter.prevent="tambahBaris" :error="$errors->has('barisBbl')" class="w-full px-1" placeholder="3000" /></td>
                                                    <td><x-text-input wire:model="barisKeterangan" wire:keydown.enter.prevent="tambahBaris" :error="$errors->has('barisKeterangan')" class="w-full px-2" placeholder="Keterangan tambahan" /></td>
                                                    <td class="ds-td-meta">{{ auth()->user()->myuser_name ?? '-' }}</td>
                                                    <td class="ds-c">
                                                        <x-primary-button type="button" wire:click="tambahBaris" wire:loading.attr="disabled" wire:target="tambahBaris" class="justify-center gap-1 w-full px-2 py-1.5 text-sm">
                                                            <span wire:loading.remove wire:target="tambahBaris" class="flex items-center gap-1">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                                                Tambah
                                                            </span>
                                                            <span wire:loading wire:target="tambahBaris"><x-loading class="w-4 h-4" /></span>
                                                        </x-primary-button>
                                                    </td>
                                                </tr>
                                            @endif

                                            @forelse ($newForm['rows'] ?? [] as $nomor => $baris)
                                                <tr wire:key="ro-row-{{ $nomor }}">
                                                    <td class="ds-c ds-td-meta">{{ $nomor + 1 }}</td>
                                                    <td class="ds-td-strong">{{ ($baris['kehamilan'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['caraPersalinan'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['tempat'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['penolong'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['komplikasi'] ?? '') ?: '-' }}</td>
                                                    <td class="ds-c">{{ ($baris['jenisKelaminAnak'] ?? '') ?: '-' }}</td>
                                                    <td class="ds-c">{{ ($baris['keadaanAnak'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['umurAnak'] ?? '') ?: '-' }}</td>
                                                    <td class="ds-c">{{ ($baris['bbl'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['keterangan'] ?? '') ?: '-' }}</td>
                                                    <td class="ds-td-meta">{{ ($baris['petugas'] ?? '') ?: '-' }}</td>
                                                    <td class="ds-c">
                                                        @if (!$formReadOnly)
                                                            <x-confirm-button variant="danger-soft" :action="'hapusBaris(' . $nomor . ')'"
                                                                title="Hapus Baris" :message="'Yakin hapus riwayat kehamilan ke-' . ($nomor + 1) . ' dari daftar?'"
                                                                confirmText="Ya, hapus" cancelText="Batal" class="px-2 py-1">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                            </x-confirm-button>
                                                        @else
                                                            <span class="text-muted-soft">—</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="13" class="ds-c italic text-muted-soft">Belum ada riwayat kehamilan.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                @if (!$formReadOnly)
                                    <p class="text-xs text-muted-soft">Isi baris paling atas lalu klik <strong>Tambah</strong> (atau tekan Enter). Petugas penambah baris ikut tercatat.</p>
                                @endif
                            </div>
                        </x-border-form>

                        {{-- ══ TTD PETUGAS & KUNCI ══ --}}
                        <x-signature.ttd-petugas :ttd="$newForm['ttd']" :code="$newForm['ttdCode'] ?? ''"
                            :date="$newForm['ttdDate'] ?? ''" :locked="$formReadOnly" sign="ttdSaya" clear="hapusTtd"
                            title="Tanda Tangan Petugas"
                            nameLabel="Petugas (Bidan / Dokter)" dateLabel="Waktu TTD"
                            signLabel="TTD Petugas & Kunci" clearLabel="Batal TTD" />
                        @if (!$formReadOnly)
                            <p class="-mt-2 text-xs text-center text-muted">Menandatangani = mengunci riwayat obstetri ini.</p>
                        @endif
                    </fieldset>

                    {{-- ── DAFTAR ENTRI TERSIMPAN (expandable) ── --}}
                    @endif
                    @unless ($this->diForm())
                    <x-border-form title="Riwayat Obstetri Tersimpan">
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <span class="text-xs italic text-muted-soft">Klik baris untuk lihat detail lengkap</span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                                    <thead class="bg-surface-soft dark:bg-gray-800">
                                        <tr class="text-left text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-300">
                                            <th class="w-8 px-2 py-3 border-b"></th>
                                            <th class="px-4 py-3 border-b">Waktu</th>
                                            <th class="px-4 py-3 border-b">G-P-A</th>
                                            <th class="px-4 py-3 border-b">Petugas (TTD)</th>
                                            <th class="px-4 py-3 text-center border-b">Status</th>
                                            <th class="px-4 py-3 text-center border-b">Aksi</th>
                                        </tr>
                                    </thead>
                                    @forelse (array_reverse($entriList) as $entry)
                                        @php
                                            $isFinal = $this->entryIsFinal($entry);
                                            $rowKey = $entry['createdAt'] ?? '';
                                        @endphp
                                        {{-- Semua baris mulai TERTUTUP: daftar dipakai untuk MEMILIH entri, bukan
                                             membacanya. Baris teratas yang terbuka sendiri bikin grid langsung panjang. --}}
                                        <tbody x-data="{ open: false }" class="border-b border-hairline dark:border-gray-700">
                                            <tr @click="open = !open"
                                                class="cursor-pointer hover:bg-surface-soft dark:hover:bg-gray-800 {{ $editingKey && $editingKey === $rowKey ? 'bg-brand-lime/10 dark:bg-brand-lime/5' : '' }}">
                                                <td class="px-2 py-3 text-center align-middle">
                                                    <svg class="w-4 h-4 mx-auto transition-transform text-muted" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </td>
                                                <td class="px-4 py-3 font-semibold align-middle text-ink dark:text-gray-100">
                                                    {{ $rowKey ?: '-' }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    G{{ ($entry['gravida'] ?? '') ?: '-' }}P{{ ($entry['para'] ?? '') ?: '-' }}A{{ ($entry['abortus'] ?? '') ?: '-' }}
                                                    <span class="ml-1 text-muted-soft">· {{ count($entry['rows'] ?? []) }} kehamilan</span>
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    @if (!empty($entry['ttd']))
                                                        <span class="font-medium text-ink dark:text-gray-200">{{ $entry['ttd'] }}</span>
                                                    @else
                                                        <x-badge variant="danger">Belum TTD</x-badge>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-center align-middle">
                                                    @if ($isFinal)
                                                        <x-badge variant="info">Terkunci</x-badge>
                                                    @else
                                                        <x-badge variant="warning">Draft</x-badge>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-center align-middle whitespace-nowrap" @click.stop>
                                                    <div class="flex flex-wrap items-center justify-center gap-1.5">
                                                        <div class="flex items-center justify-center gap-2">
                                                        @if (!$isFinal && !$isFormLocked)
                                                            <x-primary-button type="button" wire:click="editEntry('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="editEntry('{{ $rowKey }}')" class="gap-1.5" title="Lanjutkan mengisi entri ini">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                </svg>
                                                                Lanjutkan Pengisian
                                                            </x-primary-button>
                                                        @endif
                                                        @if ($isFinal)
                                                            <x-secondary-button type="button" wire:click="viewEntry('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="viewEntry('{{ $rowKey }}')" class="gap-1.5" title="Lihat detail (read-only) di form atas">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                                </svg>
                                                                Lihat
                                                            </x-secondary-button>
                                                        @endif
                                                        <x-secondary-button type="button" wire:click="cetak('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="cetak('{{ $rowKey }}')" class="gap-1.5" title="Cetak entri ini">
                                                            <span wire:loading.remove wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                                </svg>
                                                                Cetak
                                                            </span>
                                                            <span wire:loading wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5"><x-loading class="w-5 h-5" /> Mencetak...</span>
                                                        </x-secondary-button>
                                                        </div>
                                                        @if (!$isFormLocked)
                                                            <div class="flex items-center justify-center gap-2">
                                                            @if ($isFinal)
                                                                @can('dokumen.bukaKunci')
                                                                    <x-confirm-button action="bukaKunci('{{ $rowKey }}')"
                                                                        title="Buka Kunci Riwayat Obstetri"
                                                                        message="TTD petugas akan dicabut & entri kembali menjadi Draft — proses TTD diulang dari awal. Lanjutkan?"
                                                                        confirmText="Ya, Buka Kunci" class="gap-1.5">
                                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                                d="M8 11V7a4 4 0 118 0m-8 4h10a2 2 0 012 2v5a2 2 0 01-2 2H8a2 2 0 01-2-2v-5a2 2 0 012-2z" />
                                                                        </svg>
                                                                        Buka Kunci
                                                                    </x-confirm-button>
                                                                @endcan
                                                            @endif
                                                            @can('dokumen.hapus')
                                                            <x-outline-button type="button" wire:click.prevent="hapus('{{ $rowKey }}')" wire:confirm="Yakin hapus entri riwayat obstetri ini?"
                                                                wire:loading.attr="disabled"
                                                                class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                                title="Hapus">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
                                                            </x-outline-button>
                                                            @endcan
                                                            </div>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>

                                            {{-- DETAIL (expand) --}}
                                            <tr x-show="open" x-cloak>
                                                <td colspan="6" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                                    <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Gravida (G)</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['gravida'] ?? '') ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Para (P)</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['para'] ?? '') ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Abortus (A)</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['abortus'] ?? '') ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Petugas (TTD)</dt>
                                                            <dd class="mt-0.5">
                                                                @if (!empty($entry['ttd']))
                                                                    <span class="text-ink dark:text-gray-200">{{ $entry['ttd'] }}</span>
                                                                    <span class="text-sm text-muted-soft">— {{ $entry['ttdDate'] ?? '-' }}</span>
                                                                @else
                                                                    <x-badge variant="danger">Belum TTD</x-badge>
                                                                @endif
                                                            </dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="mb-1 text-xs font-semibold tracking-wide uppercase text-muted-soft">Riwayat Kehamilan / Persalinan Yang Lalu</dt>
                                                            <dd class="mt-0.5">
                                                                @if (!empty($entry['rows']))
                                                                    <div class="overflow-x-auto border rounded-lg border-hairline dark:border-gray-700">
                                                                        <table class="w-full text-xs border-collapse">
                                                                            <thead>
                                                                                <tr class="text-left tracking-wide uppercase text-muted bg-surface-soft border-b border-hairline dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400">
                                                                                    <th class="px-2 py-1.5 font-semibold text-center">No</th>
                                                                                    <th class="px-2 py-1.5 font-semibold">Kehamilan</th>
                                                                                    <th class="px-2 py-1.5 font-semibold">Cara Persalinan</th>
                                                                                    <th class="px-2 py-1.5 font-semibold">Tempat</th>
                                                                                    <th class="px-2 py-1.5 font-semibold">Penolong</th>
                                                                                    <th class="px-2 py-1.5 font-semibold">Komplikasi</th>
                                                                                    <th class="px-2 py-1.5 font-semibold">JK</th>
                                                                                    <th class="px-2 py-1.5 font-semibold">Keadaan</th>
                                                                                    <th class="px-2 py-1.5 font-semibold">Umur</th>
                                                                                    <th class="px-2 py-1.5 font-semibold">BBL</th>
                                                                                    <th class="px-2 py-1.5 font-semibold">Keterangan</th>
                                                                                    <th class="px-2 py-1.5 font-semibold">Petugas</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                @foreach ($entry['rows'] as $nomorRiwayat => $barisRiwayat)
                                                                                    <tr class="border-t border-hairline dark:border-gray-700">
                                                                                        <td class="px-2 py-1 text-center">{{ $nomorRiwayat + 1 }}</td>
                                                                                        <td class="px-2 py-1">{{ ($barisRiwayat['kehamilan'] ?? '') ?: '-' }}</td>
                                                                                        <td class="px-2 py-1">{{ ($barisRiwayat['caraPersalinan'] ?? '') ?: '-' }}</td>
                                                                                        <td class="px-2 py-1">{{ ($barisRiwayat['tempat'] ?? '') ?: '-' }}</td>
                                                                                        <td class="px-2 py-1">{{ ($barisRiwayat['penolong'] ?? '') ?: '-' }}</td>
                                                                                        <td class="px-2 py-1">{{ ($barisRiwayat['komplikasi'] ?? '') ?: '-' }}</td>
                                                                                        <td class="px-2 py-1">{{ ($barisRiwayat['jenisKelaminAnak'] ?? '') ?: '-' }}</td>
                                                                                        <td class="px-2 py-1">{{ ($barisRiwayat['keadaanAnak'] ?? '') ?: '-' }}</td>
                                                                                        <td class="px-2 py-1">{{ ($barisRiwayat['umurAnak'] ?? '') ?: '-' }}</td>
                                                                                        <td class="px-2 py-1">{{ ($barisRiwayat['bbl'] ?? '') ?: '-' }}</td>
                                                                                        <td class="px-2 py-1">{{ ($barisRiwayat['keterangan'] ?? '') ?: '-' }}</td>
                                                                                        <td class="px-2 py-1">{{ ($barisRiwayat['petugas'] ?? '') ?: '-' }}</td>
                                                                                    </tr>
                                                                                @endforeach
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                @else
                                                                    <span class="text-ink dark:text-gray-200">-</span>
                                                                @endif
                                                            </dd>
                                                        </div>
                                                    </dl>
                                                </td>
                                            </tr>
                                        </tbody>
                                    @empty
                                        <tbody>
                                            <tr>
                                                <td colspan="6" class="px-6 py-12">
                                                    <div class="flex flex-col items-center justify-center gap-3">
                                                        <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                                        <p class="text-base font-medium text-muted dark:text-gray-400">Belum ada data tersimpan</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    @endforelse
                                </table>
                            </div>
                    </x-border-form>
                    @endunless

                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-3 border-t shrink-0 bg-surface-soft border-hairline dark:bg-gray-900 dark:border-gray-700">
                @if ($this->diForm())
                <div class="flex flex-wrap items-center justify-between gap-3">
                    @if ($viewOnly)
                        <p class="flex items-center gap-1.5 text-sm text-sky-600 dark:text-sky-400">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span>Mode lihat — entri terkunci, tidak dapat diubah.</span>
                        </p>
                    @elseif (!$isFormLocked)
                        <p class="flex items-center gap-1.5 text-sm text-muted dark:text-gray-400">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Simpan draft dulu, lalu <strong>kunci</strong> lewat tombol <strong>TTD Petugas &amp; Kunci</strong>.</span>
                        </p>
                    @else
                        <span></span>
                    @endif

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <x-secondary-button type="button" wire:click="kembaliKeDaftar">Kembali ke Daftar</x-secondary-button>

                        @if ($viewOnly)
                            <x-primary-button wire:click.prevent="cancelEdit" wire:target="cancelEdit"
                                wire:loading.attr="disabled" class="gap-1.5 min-w-[160px] justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Selesai Melihat
                            </x-primary-button>
                        @elseif (!$isFormLocked)
                            @if ($editingKey)
                                <x-outline-button wire:click.prevent="cancelEdit" wire:target="cancelEdit"
                                    wire:loading.attr="disabled" class="gap-1.5"
                                    title="Kosongkan form untuk menambah catatan lain — entri yang sudah tersimpan tidak berubah">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Entri Baru
                                </x-outline-button>
                            @endif
                            <x-primary-button wire:click.prevent="saveDraft" wire:loading.attr="disabled"
                                wire:target="saveDraft" class="gap-2 min-w-[160px] justify-center">
                                <span wire:loading.remove wire:target="saveDraft" class="flex items-center gap-1.5">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 21v-8H7v8M7 3v5h8M5 3h11l4 4v12a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
                                    </svg>
                                    {{ $editingKey ? 'Simpan Perubahan' : 'Simpan Draft' }}
                                </span>
                                <span wire:loading wire:target="saveDraft"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                            </x-primary-button>
                        @endif
                    </div>
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
