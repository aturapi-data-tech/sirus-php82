<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/pra-induksi-ri/rm-pra-induksi-rj-actions.blade.php
// Asesmen Pra Induksi — PAB 6 / RM 50.a (re-asesmen sesaat sebelum induksi).
// Pola: multi-entri append-only (Draft + Lanjut Isi + TTD-Kunci + Lihat read-only + tabel expandable),
// disimpan ke datadaftarri_json (key praInduksiRI). Kunci entri stabil = createdAt.
// TTD PETUGAS = stempel nama user login via setTtd() (ttd/ttdCode/ttdDate) = FINALIZE/kunci; tanpa TTD gambar.

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?string $rjNo = null;
    public ?string $regNo = null;
    public bool $disabled = false;
    public array $dataDaftarPoliRJ = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-pra-induksi-rj'];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'praInduksiRJ';

    // ── Asesmen Pra Induksi — PAB 6 / RM 50.a ──
    public array $newForm = [
        'tanggal' => '',
        'tempat' => 'Kamar Operasi (OK)',
        'diagnosisPraAnestesi' => '',
        'rencanaTindakan' => '',
        'dokterOperator' => '',
        'dokterAnestesi' => '',
        'asistenOperator' => '',
        'asistenAnestesi' => '',
        'instrumen' => '',
        'onLoop' => '',
        'amnanese' => '',
        'riwayatAnestesi' => false,
        'riwayatAnestesiJenis' => '',
        'merokok' => false,
        'merokokKet' => '',
        'alkohol' => false,
        'alkoholKet' => '',
        'riwayatAlergi' => false,
        'riwayatAlergiJenis' => '',
        'persiapanTransfusi' => false,
        'transfusiJumlah' => '',
        // Tanda vital — mengikuti pola Pra Anestesi RI (sistolik/diastolik terpisah + satuan)
        'sistolik' => '',
        'diastolik' => '',
        'nadi' => '',
        'rr' => '',
        'suhu' => '',
        'spo2' => '',
        'gda' => '',
        'pemFisikPernafasan' => '',
        'pemFisikTulangBelakang' => '',
        'pemFisikJantungParu' => '',
        'pemFisikAbdomen' => '',
        'penunjangLab' => '',
        'penunjangEkg' => '',
        'penunjangThorak' => '',
        'klasifikasiAsa' => '',
        'rencanaAnestesi' => '',
        'pemulihanPasca' => '',
        'manajemenNyeri' => '',
        'obatPreMedikasi' => [],
        'ttd' => '',
        'ttdCode' => '',
        'ttdDate' => '',
    ];

    public array $praInduksiList = [];

    // Kunci entri yang sedang diedit (createdAt = kunci stabil). null = membuat entri baru.
    public ?string $editingKey = null;

    // true = entri terkunci sedang ditampilkan di form dalam mode read-only (lihat saja).
    public bool $viewOnly = false;

    public array $asaOptions = ['1', '2', '3', '4', '5'];
    public array $rencanaAnestesiOptions = ['Umum', 'Spinal', 'Regional lain', 'Sedasi'];
    public array $pemulihanOptions = ['Ruang Perawatan', 'ICU/HCU'];
    public array $nyeriOptions = ['IV', 'IM', 'Oral', 'Epidural'];

    public function mount(?string $rjNo = null, bool $disabled = false): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-pra-induksi-rj']);

        if ($this->rjNo) {
            $data = $this->findDataRJ($this->rjNo);
            if ($data) {
                $this->dataDaftarPoliRJ = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->praInduksiList = $data[$this->jsonKey] ?? [];
                $this->isFormLocked = $this->checkEmrRJStatus($this->rjNo) || $disabled;
            }
        }
    }

    public function openModal(): void
    {
        if (!$this->rjNo || $this->disabled) {
            return;
        }
        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();

        $data = $this->findDataRJ($this->rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
            return;
        }
        $this->dataDaftarPoliRJ = $data;
        $this->regNo = $data['regNo'] ?? null;
        if (!isset($this->dataDaftarPoliRJ[$this->jsonKey]) || !is_array($this->dataDaftarPoliRJ[$this->jsonKey])) {
            $this->dataDaftarPoliRJ[$this->jsonKey] = [];
        }
        $this->praInduksiList = $this->dataDaftarPoliRJ[$this->jsonKey];
        $this->isFormLocked = $this->checkEmrRJStatus($this->rjNo) || $this->disabled;
        $this->prefillTimDariOk();
        $this->incrementVersion('modal-pra-induksi-rj');
        $this->dispatch('open-modal', name: "rm-pra-induksi-rj-{$this->rjNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-pra-induksi-rj-{$this->rjNo}");
    }

    protected function rules(): array
    {
        return [
            'newForm.tanggal' => 'required|date_format:d/m/Y H:i:s',
            'newForm.diagnosisPraAnestesi' => 'required|string|max:500',
            'newForm.rencanaTindakan' => 'required|string|max:500',
            'newForm.klasifikasiAsa' => 'required|in:1,2,3,4,5',
            'newForm.rencanaAnestesi' => 'required|string|max:100',
            'newForm.transfusiJumlah' => 'nullable|string|max:100',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'date_format' => 'Format :attribute harus dd/mm/yyyy HH:mm:ss.',
            'in' => ':attribute tidak valid.',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newForm.tanggal' => 'Tanggal/jam',
            'newForm.diagnosisPraAnestesi' => 'Diagnosis pra anestesi',
            'newForm.rencanaTindakan' => 'Rencana tindakan',
            'newForm.klasifikasiAsa' => 'Klasifikasi ASA',
            'newForm.rencanaAnestesi' => 'Rencana anestesi',
        ];
    }

    public function setTanggalSekarang(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['tanggal'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | OBAT PRE MEDIKASI — tabel entri {obat, dosis, jam, pelaksana}.
     | Baris masuk ke newForm['obatPreMedikasi'] dan ikut tersimpan saat Simpan entri.
     =============================== */
    public string $preMedikasiObat = '';
    public string $preMedikasiDosis = '';
    public string $preMedikasiJam = '';
    public string $preMedikasiPelaksana = '';

    // Data legacy menyimpan string bebas — bungkus jadi satu baris supaya tetap tampil.
    private function normalizePreMedikasi($nilai): array
    {
        if (is_array($nilai)) {
            return array_values($nilai);
        }
        return filled($nilai) ? [['obat' => (string) $nilai, 'dosis' => '', 'jam' => '', 'pelaksana' => '']] : [];
    }

    public function setPreMedikasiJamNow(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->preMedikasiJam = Carbon::now(config('app.timezone'))->format('H:i:s');
    }

    public function addPreMedikasi(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }

        // validate() didahulukan supaya field yang kosong tetap ditandai merah.
        $this->validateWithToast(
            [
                'preMedikasiObat' => ['required', 'string', 'max:200'],
                'preMedikasiDosis' => ['nullable', 'string', 'max:100'],
                'preMedikasiJam' => ['nullable', 'string', 'max:20'],
                'preMedikasiPelaksana' => ['nullable', 'string', 'max:200'],
            ],
            [],
            [
                'preMedikasiObat' => 'Obat Pre Medikasi',
                'preMedikasiDosis' => 'Dosis',
                'preMedikasiJam' => 'Jam',
                'preMedikasiPelaksana' => 'Pelaksana',
            ],
        );

        $list = $this->normalizePreMedikasi($this->newForm['obatPreMedikasi'] ?? []);
        $list[] = [
            'obat' => $this->preMedikasiObat,
            'dosis' => $this->preMedikasiDosis,
            'jam' => $this->preMedikasiJam,
            'pelaksana' => $this->preMedikasiPelaksana,
        ];
        $this->newForm['obatPreMedikasi'] = $list;

        $this->preMedikasiObat = '';
        $this->preMedikasiDosis = '';
        $this->preMedikasiJam = '';
        $this->preMedikasiPelaksana = '';
    }

    public function removePreMedikasi(int $index): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $list = $this->normalizePreMedikasi($this->newForm['obatPreMedikasi'] ?? []);
        unset($list[$index]);
        $this->newForm['obatPreMedikasi'] = array_values($list);
    }

    /* ===============================
     | HELPER — status & bentuk entri
     =============================== */
    // Entri dianggap FINAL/terkunci bila flag finalized true; entri lama (tanpa flag) yang sudah
    // ada TTD (nama penanda) dianggap final (kompatibilitas data lama).
    public function entryIsFinal(array $e): bool
    {
        return array_key_exists('finalized', $e) ? (bool) $e['finalized'] : !empty($e['ttd']);
    }

    // Susun array entri dari state form. $key = createdAt (kunci stabil); $finalized = status kunci.
    private function buildEntry(string $key, bool $finalized): array
    {
        return [
            'tanggal' => $this->newForm['tanggal'] ?? '',
            'tempat' => $this->newForm['tempat'] ?? '',
            'diagnosisPraAnestesi' => $this->newForm['diagnosisPraAnestesi'] ?? '',
            'rencanaTindakan' => $this->newForm['rencanaTindakan'] ?? '',
            'dokterOperator' => $this->newForm['dokterOperator'] ?? '',
            'dokterAnestesi' => $this->newForm['dokterAnestesi'] ?? '',
            'asistenOperator' => $this->newForm['asistenOperator'] ?? '',
            'asistenAnestesi' => $this->newForm['asistenAnestesi'] ?? '',
            'instrumen' => $this->newForm['instrumen'] ?? '',
            'onLoop' => $this->newForm['onLoop'] ?? '',
            'amnanese' => $this->newForm['amnanese'] ?? '',
            'riwayatAnestesi' => (bool) ($this->newForm['riwayatAnestesi'] ?? false),
            'riwayatAnestesiJenis' => $this->newForm['riwayatAnestesiJenis'] ?? '',
            'merokok' => (bool) ($this->newForm['merokok'] ?? false),
            'merokokKet' => $this->newForm['merokokKet'] ?? '',
            'alkohol' => (bool) ($this->newForm['alkohol'] ?? false),
            'alkoholKet' => $this->newForm['alkoholKet'] ?? '',
            'riwayatAlergi' => (bool) ($this->newForm['riwayatAlergi'] ?? false),
            'riwayatAlergiJenis' => $this->newForm['riwayatAlergiJenis'] ?? '',
            'persiapanTransfusi' => (bool) ($this->newForm['persiapanTransfusi'] ?? false),
            'transfusiJumlah' => $this->newForm['transfusiJumlah'] ?? '',
            'sistolik' => $this->newForm['sistolik'] ?? '',
            'diastolik' => $this->newForm['diastolik'] ?? '',
            'nadi' => $this->newForm['nadi'] ?? '',
            'rr' => $this->newForm['rr'] ?? '',
            'suhu' => $this->newForm['suhu'] ?? '',
            'spo2' => $this->newForm['spo2'] ?? '',
            'gda' => $this->newForm['gda'] ?? '',
            'pemFisikPernafasan' => $this->newForm['pemFisikPernafasan'] ?? '',
            'pemFisikTulangBelakang' => $this->newForm['pemFisikTulangBelakang'] ?? '',
            'pemFisikJantungParu' => $this->newForm['pemFisikJantungParu'] ?? '',
            'pemFisikAbdomen' => $this->newForm['pemFisikAbdomen'] ?? '',
            'penunjangLab' => $this->newForm['penunjangLab'] ?? '',
            'penunjangEkg' => $this->newForm['penunjangEkg'] ?? '',
            'penunjangThorak' => $this->newForm['penunjangThorak'] ?? '',
            'klasifikasiAsa' => $this->newForm['klasifikasiAsa'] ?? '',
            'rencanaAnestesi' => $this->newForm['rencanaAnestesi'] ?? '',
            'pemulihanPasca' => $this->newForm['pemulihanPasca'] ?? '',
            'manajemenNyeri' => $this->newForm['manajemenNyeri'] ?? '',
            'obatPreMedikasi' => $this->normalizePreMedikasi($this->newForm['obatPreMedikasi'] ?? []),
            'ttd' => $this->newForm['ttd'] ?? '',
            'ttdCode' => $this->newForm['ttdCode'] ?? '',
            'ttdDate' => $this->newForm['ttdDate'] ?? '',
            'createdAt' => $key,
            'finalized' => $finalized,
        ];
    }

    // Cek: minimal isi inti terisi (diagnosis / rencana tindakan / klasifikasi ASA).
    private function adaIntiTerisi(): bool
    {
        return collect(['diagnosisPraAnestesi', 'rencanaTindakan', 'klasifikasiAsa'])
            ->contains(fn($k) => filled($this->newForm[$k] ?? null));
    }

    // Simpan entri (add/update by createdAt) dengan status $finalized. Dipakai draft & kunci.
    private function persistEntry(string $key, bool $finalized, string $logVerb): void
    {
        $entry = $this->buildEntry($key, $finalized);

        DB::transaction(function () use ($entry, $key, $logVerb) {
            $this->lockRJRow($this->rjNo);

            $fresh = $this->findDataRJ($this->rjNo) ?: [];
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

            $this->updateJsonRJ((int) $this->rjNo, $fresh);
            $this->dataDaftarPoliRJ = $fresh;
            $this->praInduksiList = $fresh[$this->jsonKey];

            $this->appendAdminLogRJ((int) $this->rjNo, $logVerb . ' Asesmen Pra Induksi — ASA ' . ($entry['klasifikasiAsa'] ?: '-') . ' — ' . ($entry['tanggal'] ?: '-') . ' (' . $key . ')', 'MR');
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
        if (!$this->adaIntiTerisi()) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal salah satu: Diagnosis pra anestesi, Rencana tindakan, atau Klasifikasi ASA.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-pra-induksi-rj');
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
    public function setTtd(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (!$this->adaIntiTerisi()) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal salah satu: Diagnosis pra anestesi, Rencana tindakan, atau Klasifikasi ASA sebelum TTD.');
            return;
        }
        $this->validateWithToast();

        // Stempel TTD petugas = user login.
        $this->newForm['ttd'] = auth()->user()->myuser_name ?? '';
        $this->newForm['ttdCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['ttdDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, true, 'Kunci (TTD)');
            $this->resetNewForm();
            $this->editingKey = null;
            $this->viewOnly = false;
            $this->incrementVersion('modal-pra-induksi-rj');
            $this->dispatch('toast', type: 'success', message: 'Asesmen pra induksi ditandatangani & terkunci.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /** Batalkan TTD pada form (saat draft/edit, sebelum finalize benar-benar tersimpan). */
    public function clearTtd(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['ttd'] = '';
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
                $this->lockRJRow($this->rjNo);

                $fresh = $this->findDataRJ($this->rjNo) ?: [];
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

                $this->updateJsonRJ((int) $this->rjNo, $fresh);
                $this->dataDaftarPoliRJ = $fresh;
                $this->praInduksiList = $fresh[$this->jsonKey];

                $pembukaKunci = auth()->user()->myuser_name ?? '-';
                $this->appendAdminLogRJ((int) $this->rjNo, 'Buka kunci Asesmen Pra Induksi (' . $createdAt . ') oleh ' . $pembukaKunci . ' — TTD petugas dicabut', 'MR');
            });

            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }
            $this->incrementVersion('modal-pra-induksi-rj');
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
    // Muat 1 entri ke form atas (dipakai edit draft & lihat entri terkunci).
    private function hydrateFormFromEntry(array $entry, string $key): void
    {
        foreach ($this->newForm as $k => $v) {
            $this->newForm[$k] = $entry[$k] ?? (is_bool($v) ? false : (is_array($v) ? [] : ''));
        }
        $this->newForm['obatPreMedikasi'] = $this->normalizePreMedikasi($this->newForm['obatPreMedikasi'] ?? []);
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-pra-induksi-rj');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->praInduksiList)->firstWhere('createdAt', $key);
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
        $entry = collect($this->praInduksiList)->firstWhere('createdAt', $key);
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
        $this->prefillTimDariOk();
        $this->incrementVersion('modal-pra-induksi-rj');
    }

    /* ===============================
     | PREFILL TIM dari crew Administrasi OK (rstxn_oks) — best effort.
     | Hanya entri BARU dengan kolom tim masih kosong; nama tetap bisa dikoreksi manual.
     | On Loop tidak diprefill: OK tidak menyimpan orangnya (hanya pos tarif).
     =============================== */
    private function prefillTimDariOk(): void
    {
        if (!$this->rjNo || $this->isFormLocked || $this->viewOnly || $this->editingKey) {
            return;
        }
        $adaIsi = collect(['dokterOperator', 'dokterAnestesi', 'asistenOperator', 'asistenAnestesi', 'instrumen'])
            ->contains(fn($k) => filled($this->newForm[$k] ?? null));
        if ($adaIsi) {
            return;
        }

        try {
            $crewOk = DB::table('rstxn_oks as o')
                ->leftJoin('rsmst_doctors as dokterOpr', 'dokterOpr.dr_id', '=', 'o.dr_id')
                ->leftJoin('rsmst_doctors as dokterAnes', 'dokterAnes.dr_id', '=', 'o.dr_id_ok')
                ->leftJoin('hrmst_employees as asistenOpr', 'asistenOpr.emp_id', '=', 'o.emp_id_asistopr')
                ->leftJoin('hrmst_employees as asistenAnes', 'asistenAnes.emp_id', '=', 'o.emp_id_asistanes')
                ->leftJoin('hrmst_employees as instrumenCrew', 'instrumenCrew.emp_id', '=', 'o.emp_id_instrument')
                ->where('o.status_rjri', 'RJ')
                ->where('o.ref_no', (int) $this->rjNo)
                ->whereRaw("COALESCE(o.ok_status, 'A') = 'A'")
                ->orderByDesc('o.ok_reg')
                ->select('dokterOpr.dr_name as operator_name', 'dokterAnes.dr_name as anestesi_name', 'asistenOpr.name as asistopr_name', 'asistenAnes.name as asistanes_name', 'instrumenCrew.name as instrument_name')
                ->first();
        } catch (\Throwable) {
            return; // prefill jangan menggagalkan buka form
        }

        if (!$crewOk) {
            return;
        }
        $this->newForm['dokterOperator'] = (string) ($crewOk->operator_name ?? '');
        $this->newForm['dokterAnestesi'] = (string) ($crewOk->anestesi_name ?? '');
        $this->newForm['asistenOperator'] = (string) ($crewOk->asistopr_name ?? '');
        $this->newForm['asistenAnestesi'] = (string) ($crewOk->asistanes_name ?? '');
        $this->newForm['instrumen'] = (string) ($crewOk->instrument_name ?? '');
    }

    public function cetak(string $createdAt)
    {
        $entry = collect($this->praInduksiList)->firstWhere('createdAt', $createdAt);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan.');
            return;
        }
        $entry['obatPreMedikasi'] = $this->normalizePreMedikasi($entry['obatPreMedikasi'] ?? []);
        try {
            $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
            $pasienData = $this->findDataMasterPasien($this->regNo ?? '');
            $pasien = $pasienData['pasien'] ?? [];
            if (!empty($pasien['tglLahir'])) {
                try {
                    $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])->diff(Carbon::now(config('app.timezone')))->format('%y Thn, %m Bln %d Hr');
                } catch (\Throwable) {
                    $pasien['thn'] = '-';
                }
            }
            $ttdPath = null;
            $ttdCode = $entry['ttdCode'] ?? null;
            if ($ttdCode) {
                $path = DB::table('users')->where('myuser_code', $ttdCode)->value('myuser_ttd_image');
                if (!empty($path) && file_exists(public_path('storage/' . $path))) {
                    $ttdPath = public_path('storage/' . $path);
                }
            }
            $data = array_merge($pasien, [
                'dataRi' => $this->dataDaftarPoliRJ, 'form' => $entry, 'identitasRs' => $identitasRs,
                'ttdPath' => $ttdPath, 'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);
            set_time_limit(300);
            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.pra-induksi-ri.cetak-pra-induksi-ri-print', ['data' => $data])->setPaper('A4');
            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak asesmen pra induksi.');
            return response()->streamDownload(fn() => print $pdf->output(), 'pra-induksi-rj-' . ($pasien['regNo'] ?? $this->rjNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

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
                $this->lockRJRow($this->rjNo);
                $fresh = $this->findDataRJ($this->rjNo) ?: [];
                if (!isset($fresh[$this->jsonKey])) {
                    throw new \RuntimeException('Data tidak ditemukan.');
                }
                $fresh[$this->jsonKey] = collect($fresh[$this->jsonKey])->reject(fn($item) => ($item['createdAt'] ?? '') === $createdAt)->values()->toArray();
                $this->updateJsonRJ((int) $this->rjNo, $fresh);
                $this->dataDaftarPoliRJ = $fresh;
                $this->praInduksiList = $fresh[$this->jsonKey];
                $this->appendAdminLogRJ((int) $this->rjNo, 'Hapus Asesmen Pra Induksi — ' . $createdAt, 'MR');
            });

            // Jika entri yang dihapus sedang di form, kosongkan form.
            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }

            $this->incrementVersion('modal-pra-induksi-rj');
            $this->dispatch('toast', type: 'success', message: 'Asesmen pra induksi berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    private function resetNewForm(): void
    {
        $this->newForm = [
            'tanggal' => '', 'tempat' => 'Kamar Operasi (OK)', 'diagnosisPraAnestesi' => '', 'rencanaTindakan' => '',
            'dokterOperator' => '', 'dokterAnestesi' => '', 'asistenOperator' => '', 'asistenAnestesi' => '', 'instrumen' => '', 'onLoop' => '',
            'amnanese' => '', 'riwayatAnestesi' => false, 'riwayatAnestesiJenis' => '',
            'merokok' => false, 'merokokKet' => '', 'alkohol' => false, 'alkoholKet' => '',
            'riwayatAlergi' => false, 'riwayatAlergiJenis' => '', 'persiapanTransfusi' => false, 'transfusiJumlah' => '',
            'sistolik' => '', 'diastolik' => '', 'nadi' => '', 'rr' => '', 'suhu' => '', 'spo2' => '', 'gda' => '',
            'pemFisikPernafasan' => '', 'pemFisikTulangBelakang' => '', 'pemFisikJantungParu' => '', 'pemFisikAbdomen' => '',
            'penunjangLab' => '', 'penunjangEkg' => '', 'penunjangThorak' => '', 'klasifikasiAsa' => '',
            'rencanaAnestesi' => '', 'pemulihanPasca' => '', 'manajemenNyeri' => '', 'obatPreMedikasi' => [],
            'ttd' => '', 'ttdCode' => '', 'ttdDate' => '',
        ];
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarPoliRJ = [];
        $this->praInduksiList = [];
        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
    }
};
?>

<div>
    @php $entriCount = count($praInduksiList ?? []); @endphp

    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-3">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Asesmen Pra Induksi</h3>
                    @if ($entriCount > 0) <x-badge variant="success">{{ $entriCount }} asesmen</x-badge>
                    @else <x-badge variant="warning">Belum ada</x-badge> @endif
                </div>
                <p class="text-base text-muted dark:text-gray-400">
                    Re-asesmen segera sebelum induksi (PAB 6 / RM 50.a): verifikasi kondisi terkini, ASA, rencana
                    anestesi & obat pre-medikasi sesaat sebelum tindakan.
                </p>
                @if ($entriCount > 0)
                    <ul class="space-y-1 text-base text-muted dark:text-gray-300 list-disc pl-5">
                        @foreach (array_slice(array_reverse($praInduksiList), 0, 3) as $entri)
                            <li><span class="font-medium">ASA {{ $entri['klasifikasiAsa'] ?? '-' }} · {{ $entri['rencanaAnestesi'] ?? '-' }}</span>
                                @if (!empty($entri['tanggal'])) <span class="text-sm text-muted-soft">— {{ $entri['tanggal'] }}</span> @endif
                            </li>
                        @endforeach
                        @if ($entriCount > 3) <li class="text-sm italic text-muted-soft">+{{ $entriCount - 3 }} lainnya…</li> @endif
                    </ul>
                @endif
            </div>
            <div class="flex shrink-0">
                <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled" wire:target="openModal" :disabled="$disabled || !$rjNo" class="gap-2">
                    <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                        Buka Formulir
                    </span>
                    <span wire:loading wire:target="openModal" class="flex items-center gap-1.5"><x-loading class="w-4 h-4" /> Memuat...</span>
                </x-primary-button>
            </div>
        </div>
    </div>

    <x-modal name="rm-pra-induksi-rj-{{ $rjNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]" wire:key="{{ $this->renderKey('modal-pra-induksi-rj', [$rjNo ?? 'new']) }}">

            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-fuchsia-500/10">
                                <svg class="w-6 h-6 text-fuchsia-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            </div>
                            <div>
                                <h2 class="font-semibold text-2xl text-ink dark:text-gray-100">Asesmen Pra Induksi</h2>
                                <p class="mt-0.5 text-base text-muted dark:text-gray-400">PAB 6 / RM 50.a — sesaat sebelum induksi</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="brand">Rawat Inap</x-badge>
                            @if (count($praInduksiList) > 0) <x-badge variant="info">{{ count($praInduksiList) }} tersimpan</x-badge> @endif
                            @if ($isFormLocked) <x-badge variant="danger">Read Only</x-badge> @endif
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </x-icon-button>
                </div>
            </div>

            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">
                    <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo" wire:key="prai-rj-display-pasien-{{ $rjNo ?? 'init' }}" />

                    <div class="p-6 space-y-6 bg-canvas border border-hairline shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        @php $formReadOnly = $isFormLocked || $viewOnly; @endphp

                        @if ($isFormLocked)
                            <div class="flex items-center gap-2 px-4 py-2.5 text-base font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                EMR terkunci — data tidak dapat diubah.
                            </div>
                        @endif

                        @if ($viewOnly)
                            <div class="flex items-center gap-2 px-4 py-2.5 text-base font-medium text-sky-700 bg-sky-50 border border-sky-200 rounded-xl dark:bg-sky-900/20 dark:border-sky-600 dark:text-sky-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Menampilkan entri terkunci <strong>{{ $editingKey }}</strong> (hanya lihat) — klik <strong>Selesai Melihat</strong> untuk kembali ke form entri baru.
                            </div>
                        @elseif ($editingKey && !$isFormLocked)
                            <div class="flex items-center gap-2 px-4 py-2.5 text-base font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl dark:bg-emerald-900/20 dark:border-emerald-600 dark:text-emerald-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Sedang melanjutkan entri <strong>{{ $editingKey }}</strong> — <strong>Simpan Perubahan</strong> menyimpan ke entri ini; klik <strong>Entri Baru</strong> untuk menambah asesmen lain.
                            </div>
                        @endif

                        <fieldset @disabled($formReadOnly) class="space-y-6">

                            <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Tanggal / Jam *" class="mb-1" />
                                    <div class="flex items-center gap-2">
                                        <x-text-input wire:model.live="newForm.tanggal" placeholder="dd/mm/yyyy HH:mm:ss" :error="$errors->has('newForm.tanggal')" class="w-full" />
                                        @if (!$formReadOnly) <x-now-button wire:click="setTanggalSekarang" /> @endif
                                    </div>
                                    <x-input-error :messages="$errors->get('newForm.tanggal')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Tempat" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.tempat" :error="$errors->has('newForm.tempat')" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Diagnosis Pra Anestesi *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.diagnosisPraAnestesi" :error="$errors->has('newForm.diagnosisPraAnestesi')" rows="2" class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.diagnosisPraAnestesi')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Rencana Tindakan *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.rencanaTindakan" :error="$errors->has('newForm.rencanaTindakan')" rows="2" class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.rencanaTindakan')" class="mt-1" />
                                </div>
                                {{-- Tim operasi — peran mengikuti crew Administrasi OK; terisi otomatis dari
                                     transaksi OK kunjungan ini (bila ada) dan tetap bisa dikoreksi manual. --}}
                                <div class="md:col-span-2">
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                        <div>
                                            <x-input-label value="Dokter Operator" class="mb-1" />
                                            <x-text-input wire:model.live="newForm.dokterOperator" :error="$errors->has('newForm.dokterOperator')" class="w-full" />
                                        </div>
                                        <div>
                                            <x-input-label value="Dokter Anestesi" class="mb-1" />
                                            <x-text-input wire:model.live="newForm.dokterAnestesi" :error="$errors->has('newForm.dokterAnestesi')" class="w-full" />
                                        </div>
                                        <div>
                                            <x-input-label value="Asisten Operator" class="mb-1" />
                                            <x-text-input wire:model.live="newForm.asistenOperator" :error="$errors->has('newForm.asistenOperator')" class="w-full" />
                                        </div>
                                        <div>
                                            <x-input-label value="Asisten Anestesi" class="mb-1" />
                                            <x-text-input wire:model.live="newForm.asistenAnestesi" :error="$errors->has('newForm.asistenAnestesi')" class="w-full" />
                                        </div>
                                        <div>
                                            <x-input-label value="Instrumen" class="mb-1" />
                                            <x-text-input wire:model.live="newForm.instrumen" :error="$errors->has('newForm.instrumen')" class="w-full" />
                                        </div>
                                        <div>
                                            <x-input-label value="On Loop" class="mb-1" />
                                            <x-text-input wire:model.live="newForm.onLoop" :error="$errors->has('newForm.onLoop')" class="w-full" />
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs italic text-muted-soft">Terisi otomatis dari crew Administrasi OK bila transaksinya sudah ada — boleh dikoreksi.</p>
                                </div>
                            </section>

                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <div>
                                    <x-input-label value="Amnanese" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.amnanese" :error="$errors->has('newForm.amnanese')" rows="2" class="w-full" />
                                </div>
                                <div class="space-y-3">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                        <div class="shrink-0 sm:w-52">
                                            <x-toggle wire:model.live="newForm.riwayatAnestesi" :trueValue="true" :falseValue="false" label="Ada riwayat anestesi" :disabled="$formReadOnly" />
                                        </div>
                                        @if ($newForm['riwayatAnestesi'])
                                            <x-text-input wire:model.live="newForm.riwayatAnestesiJenis" :error="$errors->has('newForm.riwayatAnestesiJenis')" placeholder="Jenis / keterangan" class="w-full" />
                                        @endif
                                    </div>
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                        <div class="shrink-0 sm:w-52">
                                            <x-toggle wire:model.live="newForm.riwayatAlergi" :trueValue="true" :falseValue="false" label="Ada riwayat alergi" :disabled="$formReadOnly" />
                                        </div>
                                        @if ($newForm['riwayatAlergi'])
                                            <x-text-input wire:model.live="newForm.riwayatAlergiJenis" :error="$errors->has('newForm.riwayatAlergiJenis')" placeholder="Jenis / keterangan" class="w-full" />
                                        @endif
                                    </div>
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                        <div class="shrink-0 sm:w-52">
                                            <x-toggle wire:model.live="newForm.merokok" :trueValue="true" :falseValue="false" label="Merokok" :disabled="$formReadOnly" />
                                        </div>
                                        @if ($newForm['merokok'])
                                            <x-text-input wire:model.live="newForm.merokokKet" :error="$errors->has('newForm.merokokKet')" placeholder="Jumlah / lama merokok" class="w-full" />
                                        @endif
                                    </div>
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                        <div class="shrink-0 sm:w-52">
                                            <x-toggle wire:model.live="newForm.alkohol" :trueValue="true" :falseValue="false" label="Alkohol" :disabled="$formReadOnly" />
                                        </div>
                                        @if ($newForm['alkohol'])
                                            <x-text-input wire:model.live="newForm.alkoholKet" :error="$errors->has('newForm.alkoholKet')" placeholder="Jumlah / frekuensi" class="w-full" />
                                        @endif
                                    </div>
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                        <div class="shrink-0 sm:w-52">
                                            <x-toggle wire:model.live="newForm.persiapanTransfusi" :trueValue="true" :falseValue="false" label="Persiapan transfusi" :disabled="$formReadOnly" />
                                        </div>
                                        @if ($newForm['persiapanTransfusi'])
                                            <x-text-input wire:model.live="newForm.transfusiJumlah" :error="$errors->has('newForm.transfusiJumlah')" placeholder="Jumlah / kolf / unit" class="w-full" />
                                        @endif
                                    </div>
                                </div>
                            </section>

                            {{-- ══ TANDA VITAL — mengikuti pola Pra Anestesi RI ══ --}}
                            <section class="pt-6 border-t border-hairline dark:border-gray-700">
                                <h3 class="mb-3 text-base font-semibold text-ink dark:text-gray-200">Tanda Vital</h3>
                                <x-border-form :title="__('Tanda Vital')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
                                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-7">
                                        <div>
                                            <x-input-label value="Sistolik (mmHg)" class="whitespace-nowrap" />
                                            <x-text-input wire:model.live="newForm.sistolik" :error="$errors->has('newForm.sistolik')" class="w-full mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="Diastolik (mmHg)" class="whitespace-nowrap" />
                                            <x-text-input wire:model.live="newForm.diastolik" :error="$errors->has('newForm.diastolik')" class="w-full mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="Nadi (x/mnt)" class="whitespace-nowrap" />
                                            <x-text-input wire:model.live="newForm.nadi" :error="$errors->has('newForm.nadi')" class="w-full mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="RR (x/mnt)" class="whitespace-nowrap" />
                                            <x-text-input wire:model.live="newForm.rr" :error="$errors->has('newForm.rr')" class="w-full mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="Suhu (°C)" class="whitespace-nowrap" />
                                            <x-text-input wire:model.live="newForm.suhu" :error="$errors->has('newForm.suhu')" class="w-full mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="SPO2 (%)" class="whitespace-nowrap" />
                                            <x-text-input wire:model.live="newForm.spo2" :error="$errors->has('newForm.spo2')" class="w-full mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="GDA (g/dl)" class="whitespace-nowrap" />
                                            <x-text-input wire:model.live="newForm.gda" :error="$errors->has('newForm.gda')" class="w-full mt-1" />
                                        </div>
                                    </div>
                                </x-border-form>
                            </section>

                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Pemeriksaan Fisik & Penunjang</h3>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div><x-input-label value="Pernafasan / Jalan Nafas" class="mb-1" /><x-text-input wire:model.live="newForm.pemFisikPernafasan" :error="$errors->has('newForm.pemFisikPernafasan')" class="w-full" /></div>
                                    <div><x-input-label value="Kelainan Tulang Belakang" class="mb-1" /><x-text-input wire:model.live="newForm.pemFisikTulangBelakang" :error="$errors->has('newForm.pemFisikTulangBelakang')" class="w-full" /></div>
                                    <div><x-input-label value="Jantung / Paru-paru" class="mb-1" /><x-text-input wire:model.live="newForm.pemFisikJantungParu" :error="$errors->has('newForm.pemFisikJantungParu')" class="w-full" /></div>
                                    <div><x-input-label value="Abdomen" class="mb-1" /><x-text-input wire:model.live="newForm.pemFisikAbdomen" :error="$errors->has('newForm.pemFisikAbdomen')" class="w-full" /></div>
                                    <div><x-input-label value="Laboratorium" class="mb-1" /><x-text-input wire:model.live="newForm.penunjangLab" :error="$errors->has('newForm.penunjangLab')" class="w-full" /></div>
                                    <div><x-input-label value="EKG" class="mb-1" /><x-text-input wire:model.live="newForm.penunjangEkg" :error="$errors->has('newForm.penunjangEkg')" class="w-full" /></div>
                                    <div><x-input-label value="Thorak" class="mb-1" /><x-text-input wire:model.live="newForm.penunjangThorak" :error="$errors->has('newForm.penunjangThorak')" class="w-full" /></div>
                                </div>
                            </section>

                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Rencana Anestesi</h3>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <x-input-label value="Klasifikasi ASA *" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.klasifikasiAsa" :error="$errors->has('newForm.klasifikasiAsa')" class="w-full">
                                            <option value="">— pilih —</option>
                                            @foreach ($asaOptions as $opt) <option value="{{ $opt }}">ASA {{ $opt }}</option> @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('newForm.klasifikasiAsa')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Rencana Anestesi *" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.rencanaAnestesi" :error="$errors->has('newForm.rencanaAnestesi')" class="w-full">
                                            <option value="">— pilih —</option>
                                            @foreach ($rencanaAnestesiOptions as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('newForm.rencanaAnestesi')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Rencana Pemulihan Pasca Anestesi" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.pemulihanPasca" :error="$errors->has('newForm.pemulihanPasca')" class="w-full">
                                            <option value="">— pilih —</option>
                                            @foreach ($pemulihanOptions as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
                                        </x-select-input>
                                    </div>
                                    <div>
                                        <x-input-label value="Manajemen Nyeri" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.manajemenNyeri" :error="$errors->has('newForm.manajemenNyeri')" class="w-full">
                                            <option value="">— pilih —</option>
                                            @foreach ($nyeriOptions as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
                                        </x-select-input>
                                    </div>
                                </div>
                                {{-- Obat Pre Medikasi — tabel entri (obat/dosis/jam/pelaksana) ala persiapan pre-op --}}
                                <x-border-form :title="__('Obat Pre Medikasi')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
                                    <div class="space-y-3">
                                        @if (!$formReadOnly)
                                            <div class="grid grid-cols-12 gap-2">
                                                <div class="col-span-12 sm:col-span-4">
                                                    <x-input-label value="Obat Pre Medikasi" :required="true" class="truncate whitespace-nowrap" />
                                                    <x-text-input wire:model="preMedikasiObat" wire:keydown.enter.prevent="addPreMedikasi" placeholder="Midazolam" :error="$errors->has('preMedikasiObat')" class="w-full px-2 mt-1" />
                                                    <x-input-error :messages="$errors->get('preMedikasiObat')" class="mt-1" />
                                                </div>
                                                <div class="col-span-12 sm:col-span-2">
                                                    <x-input-label value="Dosis" class="truncate whitespace-nowrap" />
                                                    <x-text-input wire:model="preMedikasiDosis" wire:keydown.enter.prevent="addPreMedikasi" placeholder="2 mg" :error="$errors->has('preMedikasiDosis')" class="w-full px-2 mt-1" />
                                                    <x-input-error :messages="$errors->get('preMedikasiDosis')" class="mt-1" />
                                                </div>
                                                <div class="col-span-12 sm:col-span-3">
                                                    <x-input-label value="Jam" class="truncate whitespace-nowrap" />
                                                    <div class="flex gap-1 mt-1">
                                                        <x-text-input wire:model="preMedikasiJam" placeholder="HH:mm:ss" :error="$errors->has('preMedikasiJam')" class="w-full px-2" />
                                                        <x-now-button wire:click="setPreMedikasiJamNow" />
                                                    </div>
                                                    <x-input-error :messages="$errors->get('preMedikasiJam')" class="mt-1" />
                                                </div>
                                                <div class="col-span-12 sm:col-span-3">
                                                    <x-input-label value="Pelaksana" class="truncate whitespace-nowrap" />
                                                    <x-text-input wire:model="preMedikasiPelaksana" wire:keydown.enter.prevent="addPreMedikasi" placeholder="Nama pelaksana" :error="$errors->has('preMedikasiPelaksana')" class="w-full px-2 mt-1" />
                                                    <x-input-error :messages="$errors->get('preMedikasiPelaksana')" class="mt-1" />
                                                </div>
                                            </div>

                                            <x-primary-button type="button" wire:click="addPreMedikasi" wire:loading.attr="disabled" wire:target="addPreMedikasi" class="justify-center gap-1.5 w-full">
                                                <span wire:loading.remove wire:target="addPreMedikasi" class="flex items-center gap-1.5">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                                    Tambah
                                                </span>
                                                <span wire:loading wire:target="addPreMedikasi" class="flex items-center gap-1.5"><x-loading class="w-4 h-4" /> Menambahkan...</span>
                                            </x-primary-button>
                                        @endif

                                        <div class="overflow-x-auto bg-canvas border rounded-2xl border-hairline dark:border-gray-700">
                                            <table class="ds-table">
                                                <thead>
                                                    <tr>
                                                        <th class="ds-c w-10">No</th>
                                                        <th>Obat Pre Medikasi</th>
                                                        <th>Dosis</th>
                                                        <th>Jam</th>
                                                        <th>Pelaksana</th>
                                                        <th class="ds-c w-14">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse ($newForm['obatPreMedikasi'] ?? [] as $preMedikasiIndex => $preMedikasiItem)
                                                        <tr wire:key="pre-med-row-{{ $preMedikasiIndex }}">
                                                            <td class="ds-c ds-td-meta">{{ $preMedikasiIndex + 1 }}</td>
                                                            <td class="ds-td-strong">{{ ($preMedikasiItem['obat'] ?? '') ?: '-' }}</td>
                                                            <td>{{ ($preMedikasiItem['dosis'] ?? '') ?: '-' }}</td>
                                                            <td>{{ ($preMedikasiItem['jam'] ?? '') ?: '-' }}</td>
                                                            <td>{{ ($preMedikasiItem['pelaksana'] ?? '') ?: '-' }}</td>
                                                            <td class="ds-c">
                                                                @if (!$formReadOnly)
                                                                    <x-confirm-button variant="danger-soft" :action="'removePreMedikasi(' . $preMedikasiIndex . ')'"
                                                                        title="Hapus Baris" :message="'Yakin hapus ' . ($preMedikasiItem['obat'] ?? 'baris ini') . ' dari daftar?'"
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
                                                            <td colspan="6" class="ds-c italic text-muted-soft">Belum ada obat pre medikasi.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </x-border-form>
                            </section>

                            {{-- ══ TTD PETUGAS & KUNCI ══ --}}
                            <x-signature.ttd-petugas :ttd="$newForm['ttd']" :date="$newForm['ttdDate'] ?? ''"
                                :code="$newForm['ttdCode'] ?? ''" :locked="$formReadOnly" sign="setTtd" clear="clearTtd"
                                title="Tanda Tangan Dokter Anestesi"
                                nameLabel="Dokter Anestesi" dateLabel="Waktu TTD"
                                signLabel="TTD Petugas & Kunci" clearLabel="Batal TTD" />
                            @if (!$formReadOnly)
                                <p class="-mt-2 text-xs text-center text-muted">Menandatangani = mengunci asesmen ini.</p>
                            @endif
                        </fieldset>

                        {{-- ── DAFTAR ASESMEN TERSIMPAN (expandable) ── --}}
                        @if (count($praInduksiList) > 0)
                            <div class="mt-6">
                                <h3 class="text-base font-semibold text-body dark:text-gray-300 pb-2 border-b border-hairline-soft dark:border-gray-800 mb-3">Daftar Asesmen Tersimpan</h3>
                                <p class="mb-3 text-xs italic text-muted-soft">Klik baris untuk lihat detail lengkap</p>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-base border border-hairline rounded-lg dark:border-gray-700">
                                        <thead class="bg-surface-soft dark:bg-gray-800">
                                            <tr class="text-left text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-300">
                                                <th class="w-8 px-2 py-3 border-b"></th>
                                                <th class="px-4 py-3 border-b">Tanggal</th>
                                                <th class="px-4 py-3 border-b">ASA</th>
                                                <th class="px-4 py-3 border-b">Rencana</th>
                                                <th class="px-4 py-3 border-b">Petugas (TTD)</th>
                                                <th class="px-4 py-3 text-center border-b">Status</th>
                                                <th class="px-4 py-3 text-center border-b">Aksi</th>
                                            </tr>
                                        </thead>
                                        @foreach (array_reverse($praInduksiList) as $entry)
                                            @php
                                                $isFinal = $this->entryIsFinal($entry);
                                                $rowKey = $entry['createdAt'] ?? '';
                                            @endphp
                                            <tbody x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="border-b border-hairline dark:border-gray-700">
                                                <tr @click="open = !open"
                                                    class="cursor-pointer hover:bg-surface-soft dark:hover:bg-gray-800 {{ $editingKey && $editingKey === $rowKey ? 'bg-emerald-50 dark:bg-emerald-900/10' : '' }}">
                                                    <td class="px-2 py-3 text-center align-middle">
                                                        <svg class="w-4 h-4 mx-auto transition-transform text-muted" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                        </svg>
                                                    </td>
                                                    <td class="px-4 py-3 font-semibold align-middle text-ink dark:text-gray-100">{{ $entry['tanggal'] ?: ($rowKey ?: '-') }}</td>
                                                    <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">ASA {{ $entry['klasifikasiAsa'] ?: '-' }}</td>
                                                    <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">{{ $entry['rencanaAnestesi'] ?: '-' }}</td>
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
                                                    <td class="px-4 py-3 text-center align-middle" @click.stop>
                                                        <div class="flex flex-col items-center gap-2">
                                                            <div class="flex items-center justify-center gap-2">
                                                            @if (!$isFinal && !$isFormLocked)
                                                                <x-primary-button type="button" wire:click="editEntry('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="editEntry('{{ $rowKey }}')" class="gap-1.5" title="Lanjutkan mengisi entri ini">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                    </svg>
                                                                    Lanjut Isi
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
                                                            <x-secondary-button wire:click="cetak('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="cetak('{{ $rowKey }}')" class="gap-1.5" title="Cetak">
                                                                <span wire:loading.remove wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                                                                    Cetak
                                                                </span>
                                                                <span wire:loading wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1"><x-loading class="w-4 h-4" /> ...</span>
                                                            </x-secondary-button>
                                                            </div>
                                                            @if (!$isFormLocked)
                                                                <div class="flex items-center justify-center gap-2">
                                                                @if ($isFinal)
                                                                    @can('dokumen.bukaKunci')
                                                                        <x-confirm-button action="bukaKunci('{{ $rowKey }}')"
                                                                            title="Buka Kunci Asesmen Pra Induksi"
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
                                                                <x-outline-button type="button" wire:click.prevent="hapus('{{ $rowKey }}')" wire:confirm="Yakin hapus asesmen ini?" wire:loading.attr="disabled"
                                                                    class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300" title="Hapus">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                                </x-outline-button>
                                                                @endcan
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>

                                                {{-- DETAIL (expand) --}}
                                                <tr x-show="open" x-cloak>
                                                    <td colspan="7" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                                        <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tanggal / Jam</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['tanggal'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tempat</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['tempat'] ?: '-' }}</dd>
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Diagnosis Pra Anestesi</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['diagnosisPraAnestesi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Rencana Tindakan</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['rencanaTindakan'] ?: '-' }}</dd>
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tim</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">
                                                                    Dokter Operator: {{ ($entry['dokterOperator'] ?? '') ?: '-' }}
                                                                    &nbsp;&bull;&nbsp; Dokter Anestesi: {{ ($entry['dokterAnestesi'] ?? '') ?: '-' }}
                                                                    &nbsp;&bull;&nbsp; Asisten Operator: {{ ($entry['asistenOperator'] ?? '') ?: '-' }}
                                                                    &nbsp;&bull;&nbsp; Asisten Anestesi: {{ ($entry['asistenAnestesi'] ?? '') ?: '-' }}
                                                                    &nbsp;&bull;&nbsp; Instrumen: {{ ($entry['instrumen'] ?? '') ?: '-' }}
                                                                    &nbsp;&bull;&nbsp; On Loop: {{ ($entry['onLoop'] ?? '') ?: '-' }}
                                                                </dd>
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Amnanese</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['amnanese'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Riwayat Anestesi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['riwayatAnestesi']) ? 'Ya' . (!empty($entry['riwayatAnestesiJenis']) ? ' — ' . $entry['riwayatAnestesiJenis'] : '') : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Riwayat Alergi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['riwayatAlergi']) ? 'Ya' . (!empty($entry['riwayatAlergiJenis']) ? ' — ' . $entry['riwayatAlergiJenis'] : '') : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Merokok / Alkohol</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">
                                                                    {{ !empty($entry['merokok']) ? 'Merokok' . (!empty($entry['merokokKet']) ? ' (' . $entry['merokokKet'] . ')' : '') : '—' }}
                                                                    &nbsp;&bull;&nbsp;
                                                                    {{ !empty($entry['alkohol']) ? 'Alkohol' . (!empty($entry['alkoholKet']) ? ' (' . $entry['alkoholKet'] . ')' : '') : '—' }}
                                                                </dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Persiapan Transfusi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['persiapanTransfusi']) ? 'Ya' . (!empty($entry['transfusiJumlah']) ? ' — ' . $entry['transfusiJumlah'] : '') : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tanda Vital</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">
                                                                    TD {{ $entry['sistolik'] ?: '-' }}/{{ $entry['diastolik'] ?: '-' }} mmHg
                                                                    &nbsp;&bull;&nbsp; N {{ $entry['nadi'] ?: '-' }} x/mnt
                                                                    &nbsp;&bull;&nbsp; RR {{ $entry['rr'] ?: '-' }} x/mnt
                                                                    &nbsp;&bull;&nbsp; S {{ $entry['suhu'] ?: '-' }} °C
                                                                    &nbsp;&bull;&nbsp; SpO2 {{ $entry['spo2'] ?: '-' }}%
                                                                    &nbsp;&bull;&nbsp; GDA {{ $entry['gda'] ?: '-' }} g/dl
                                                                </dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Pernafasan / Jalan Nafas</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['pemFisikPernafasan'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Kelainan Tulang Belakang</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['pemFisikTulangBelakang'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jantung / Paru-paru</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['pemFisikJantungParu'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Abdomen</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['pemFisikAbdomen'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Laboratorium</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['penunjangLab'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">EKG</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['penunjangEkg'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Thorak</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['penunjangThorak'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Klasifikasi ASA</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['klasifikasiAsa'] ? 'ASA ' . $entry['klasifikasiAsa'] : '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Rencana Anestesi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['rencanaAnestesi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Rencana Pemulihan Pasca</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['pemulihanPasca'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Manajemen Nyeri</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['manajemenNyeri'] ?: '-' }}</dd>
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Obat Pre Medikasi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">
                                                                    @php $preMedikasiRows = $this->normalizePreMedikasi($entry['obatPreMedikasi'] ?? []); @endphp
                                                                    @if (count($preMedikasiRows) > 0)
                                                                        <ul class="pl-5 list-disc space-y-0.5">
                                                                            @foreach ($preMedikasiRows as $preMedikasiRow)
                                                                                <li>
                                                                                    <span class="font-medium">{{ ($preMedikasiRow['obat'] ?? '') ?: '-' }}</span>
                                                                                    @if (filled($preMedikasiRow['dosis'] ?? '')) — {{ $preMedikasiRow['dosis'] }} @endif
                                                                                    @if (filled($preMedikasiRow['jam'] ?? '')) &bull; Jam {{ $preMedikasiRow['jam'] }} @endif
                                                                                    @if (filled($preMedikasiRow['pelaksana'] ?? '')) &bull; {{ $preMedikasiRow['pelaksana'] }} @endif
                                                                                </li>
                                                                            @endforeach
                                                                        </ul>
                                                                    @else
                                                                        -
                                                                    @endif
                                                                </dd>
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
                                                        </dl>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        @endforeach
                                    </table>
                                </div>
                            </div>
                        @endif

                    </div>
                </div>
            </div>

            <div class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
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
                        <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>

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
                                    title="Kosongkan form untuk menambah asesmen lain — entri yang sudah tersimpan tidak berubah">
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
            </div>

        </div>
    </x-modal>
</div>
