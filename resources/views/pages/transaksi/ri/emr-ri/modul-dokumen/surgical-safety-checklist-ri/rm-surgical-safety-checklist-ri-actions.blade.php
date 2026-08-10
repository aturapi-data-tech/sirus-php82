<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/surgical-safety-checklist-ri/rm-surgical-safety-checklist-ri-actions.blade.php
// Surgical Safety Checklist — Rawat Inap (WHO SSC).
// Pola: multi-entri append-only (Draft + Lanjut Isi + TTD-Kunci + Lihat read-only + tabel expandable),
// disimpan ke datadaftarri_json (key surgicalSafetyChecklistRI). Kunci entri stabil = createdAt.
// TTD 3 PIHAK (stamp user login, setTtdRole): Dokter Anestesi + Perawat Instrumen + Operator.
// Entri otomatis TERKUNCI saat KETIGA TTD terisi (TTD terakhir = finalize).

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
    protected array $renderAreas = ['modal-surgical-safety-checklist-ri'];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'surgicalSafetyChecklistRI';

    // ── Surgical Safety Checklist ──
    public array $newForm = [
        // Header
        'diagnosa' => '',
        'tindakan' => '',
        'operator' => '',
        'anestesi' => '',
        'instrumen' => '',
        'tanggal' => '',
        'asisten1' => '',
        'asisten2' => '',
        'jamInduksi' => '',
        'jamInsisi' => '',
        'jamSelesai' => '',

        // SIGN IN — Sebelum Anestesi
        'jamSignIn' => '',
        'identitasAreaTindakanPersetujuan' => '',
        'areaOperasiDitandai' => '',
        'mesinAnestesiObatDiperiksa' => '',
        'pulseOksimeterBerfungsi' => false,
        'riwayatAlergi' => false,
        'riwayatAlergiKet' => '',
        'penyulitAirwayResikoAspirasi' => false,
        'penyulitAirwayKet' => '',
        'resikoKehilanganDarah' => false,

        // TIME OUT — Sebelum Insisi
        'jamTimeOut' => '',
        'timMemperkenalkanNamaTugas' => '',
        'konfirmasiNamaTindakanArea' => '',
        'antibiotikProfilaksis' => '',
        'operatorTindakanDarurat' => false,
        'operatorLamaOperasi' => '',
        'operatorAntisipasiKehilanganDarah' => '',
        'anestesiPerhatianKhusus' => false,
        'instrumenPeralatanDisterilisasi' => false,
        'instrumenPerhatianKhususPeralatan' => '',
        'instrumenInstrumentasiRadiologi' => false,

        // SIGN OUT — Sebelum Meninggalkan Kamar Operasi
        'jamSignOut' => '',
        'perawatMembacakanJenisTindakan' => false,
        'kecocokanJumlahInstrumenKasaJarum' => false,
        'labelSpesimen' => false,
        'permasalahanAlat' => false,
        'perhatianKhususRecovery' => '',

        // TTD 3 pihak
        'ttdDokterAnestesi' => '',
        'ttdDokterAnestesiCode' => '',
        'ttdDokterAnestesiDate' => '',
        'ttdPerawatInstrumen' => '',
        'ttdPerawatInstrumenCode' => '',
        'ttdPerawatInstrumenDate' => '',
        'ttdOperator' => '',
        'ttdOperatorCode' => '',
        'ttdOperatorDate' => '',
    ];

    public array $surgicalSafetyChecklistList = [];

    // Kunci entri yang sedang diedit (createdAt = kunci stabil). null = membuat entri baru.
    public ?string $editingKey = null;

    // true = entri terkunci sedang ditampilkan di form dalam mode read-only (lihat saja).
    public bool $viewOnly = false;

    public array $sudahBelumOptions = ['Sudah', 'Belum'];
    public array $sudahBelumTidakPerluOptions = ['Sudah', 'Belum', 'Tidak Perlu'];

    private const TTD_ROLES = [
        'dokterAnestesi' => ['field' => 'ttdDokterAnestesi', 'label' => 'Dokter Anestesi'],
        'perawatInstrumen' => ['field' => 'ttdPerawatInstrumen', 'label' => 'Perawat Instrumen'],
        'operator' => ['field' => 'ttdOperator', 'label' => 'Operator'],
    ];

    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-surgical-safety-checklist-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->surgicalSafetyChecklistList = $data[$this->jsonKey] ?? [];
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
        $this->surgicalSafetyChecklistList = $this->dataDaftarRi[$this->jsonKey];
        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;
        $this->incrementVersion('modal-surgical-safety-checklist-ri');
        $this->dispatch('open-modal', name: "rm-surgical-safety-checklist-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-surgical-safety-checklist-ri-{$this->riHdrNo}");
    }

    protected function rules(): array
    {
        return [
            'newForm.tanggal' => 'required|date_format:d/m/Y H:i:s',
            'newForm.diagnosa' => 'required|string|max:500',
            'newForm.tindakan' => 'required|string|max:500',
            'newForm.operator' => 'required|string|max:200',
            'newForm.anestesi' => 'required|string|max:200',
            'newForm.instrumen' => 'required|string|max:200',
            'newForm.asisten1' => 'nullable|string|max:200',
            'newForm.asisten2' => 'nullable|string|max:200',
            'newForm.jamInduksi' => 'nullable|string|max:20',
            'newForm.jamInsisi' => 'nullable|string|max:20',
            'newForm.jamSelesai' => 'nullable|string|max:20',
            'newForm.jamSignIn' => 'nullable|string|max:20',
            'newForm.jamTimeOut' => 'nullable|string|max:20',
            'newForm.jamSignOut' => 'nullable|string|max:20',
            'newForm.riwayatAlergiKet' => 'nullable|string|max:300',
            'newForm.penyulitAirwayKet' => 'nullable|string|max:300',
            'newForm.operatorLamaOperasi' => 'nullable|string|max:200',
            'newForm.operatorAntisipasiKehilanganDarah' => 'nullable|string|max:300',
            'newForm.instrumenPerhatianKhususPeralatan' => 'nullable|string|max:300',
            'newForm.perhatianKhususRecovery' => 'nullable|string|max:1000',
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
            'newForm.tanggal' => 'Tanggal',
            'newForm.diagnosa' => 'Diagnose',
            'newForm.tindakan' => 'Tindakan',
            'newForm.operator' => 'Operator',
            'newForm.anestesi' => 'Anestesi',
            'newForm.instrumen' => 'Instrumen',
        ];
    }

    public function setTanggalSekarang(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['tanggal'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function setJamSekarang(string $field): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        if (!in_array($field, ['jamInduksi', 'jamInsisi', 'jamSelesai', 'jamSignIn', 'jamTimeOut', 'jamSignOut'], true)) {
            return;
        }
        $this->newForm[$field] = Carbon::now(config('app.timezone'))->format('H:i:s');
    }

    /* ===============================
     | HELPER — status & bentuk entri
     =============================== */
    public function entryIsFinal(array $e): bool
    {
        return array_key_exists('finalized', $e)
            ? (bool) $e['finalized']
            : (!empty($e['ttdDokterAnestesi']) && !empty($e['ttdPerawatInstrumen']) && !empty($e['ttdOperator']));
    }

    private function buildEntry(string $key, bool $finalized): array
    {
        return [
            // Header
            'diagnosa' => $this->newForm['diagnosa'] ?? '',
            'tindakan' => $this->newForm['tindakan'] ?? '',
            'operator' => $this->newForm['operator'] ?? '',
            'anestesi' => $this->newForm['anestesi'] ?? '',
            'instrumen' => $this->newForm['instrumen'] ?? '',
            'tanggal' => $this->newForm['tanggal'] ?? '',
            'asisten1' => $this->newForm['asisten1'] ?? '',
            'asisten2' => $this->newForm['asisten2'] ?? '',
            'jamInduksi' => $this->newForm['jamInduksi'] ?? '',
            'jamInsisi' => $this->newForm['jamInsisi'] ?? '',
            'jamSelesai' => $this->newForm['jamSelesai'] ?? '',

            // SIGN IN
            'jamSignIn' => $this->newForm['jamSignIn'] ?? '',
            'identitasAreaTindakanPersetujuan' => $this->newForm['identitasAreaTindakanPersetujuan'] ?? '',
            'areaOperasiDitandai' => $this->newForm['areaOperasiDitandai'] ?? '',
            'mesinAnestesiObatDiperiksa' => $this->newForm['mesinAnestesiObatDiperiksa'] ?? '',
            'pulseOksimeterBerfungsi' => (bool) ($this->newForm['pulseOksimeterBerfungsi'] ?? false),
            'riwayatAlergi' => (bool) ($this->newForm['riwayatAlergi'] ?? false),
            'riwayatAlergiKet' => $this->newForm['riwayatAlergiKet'] ?? '',
            'penyulitAirwayResikoAspirasi' => (bool) ($this->newForm['penyulitAirwayResikoAspirasi'] ?? false),
            'penyulitAirwayKet' => $this->newForm['penyulitAirwayKet'] ?? '',
            'resikoKehilanganDarah' => (bool) ($this->newForm['resikoKehilanganDarah'] ?? false),

            // TIME OUT
            'jamTimeOut' => $this->newForm['jamTimeOut'] ?? '',
            'timMemperkenalkanNamaTugas' => $this->newForm['timMemperkenalkanNamaTugas'] ?? '',
            'konfirmasiNamaTindakanArea' => $this->newForm['konfirmasiNamaTindakanArea'] ?? '',
            'antibiotikProfilaksis' => $this->newForm['antibiotikProfilaksis'] ?? '',
            'operatorTindakanDarurat' => (bool) ($this->newForm['operatorTindakanDarurat'] ?? false),
            'operatorLamaOperasi' => $this->newForm['operatorLamaOperasi'] ?? '',
            'operatorAntisipasiKehilanganDarah' => $this->newForm['operatorAntisipasiKehilanganDarah'] ?? '',
            'anestesiPerhatianKhusus' => (bool) ($this->newForm['anestesiPerhatianKhusus'] ?? false),
            'instrumenPeralatanDisterilisasi' => (bool) ($this->newForm['instrumenPeralatanDisterilisasi'] ?? false),
            'instrumenPerhatianKhususPeralatan' => $this->newForm['instrumenPerhatianKhususPeralatan'] ?? '',
            'instrumenInstrumentasiRadiologi' => (bool) ($this->newForm['instrumenInstrumentasiRadiologi'] ?? false),

            // SIGN OUT
            'jamSignOut' => $this->newForm['jamSignOut'] ?? '',
            'perawatMembacakanJenisTindakan' => (bool) ($this->newForm['perawatMembacakanJenisTindakan'] ?? false),
            'kecocokanJumlahInstrumenKasaJarum' => (bool) ($this->newForm['kecocokanJumlahInstrumenKasaJarum'] ?? false),
            'labelSpesimen' => (bool) ($this->newForm['labelSpesimen'] ?? false),
            'permasalahanAlat' => (bool) ($this->newForm['permasalahanAlat'] ?? false),
            'perhatianKhususRecovery' => $this->newForm['perhatianKhususRecovery'] ?? '',

            // TTD
            'ttdDokterAnestesi' => $this->newForm['ttdDokterAnestesi'] ?? '',
            'ttdDokterAnestesiCode' => $this->newForm['ttdDokterAnestesiCode'] ?? '',
            'ttdDokterAnestesiDate' => $this->newForm['ttdDokterAnestesiDate'] ?? '',
            'ttdPerawatInstrumen' => $this->newForm['ttdPerawatInstrumen'] ?? '',
            'ttdPerawatInstrumenCode' => $this->newForm['ttdPerawatInstrumenCode'] ?? '',
            'ttdPerawatInstrumenDate' => $this->newForm['ttdPerawatInstrumenDate'] ?? '',
            'ttdOperator' => $this->newForm['ttdOperator'] ?? '',
            'ttdOperatorCode' => $this->newForm['ttdOperatorCode'] ?? '',
            'ttdOperatorDate' => $this->newForm['ttdOperatorDate'] ?? '',

            'createdAt' => $key,
            'finalized' => $finalized,
        ];
    }

    private function adaIntiTerisi(): bool
    {
        return collect(['diagnosa', 'tindakan', 'operator'])
            ->contains(fn($k) => filled($this->newForm[$k] ?? null));
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
            $this->surgicalSafetyChecklistList = $fresh[$this->jsonKey];

            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Surgical Safety Checklist — ' . ($entry['tindakan'] ?: '-') . ' (' . $key . ')', 'MR');
        });
    }

    public function saveDraft(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }
        if (!$this->adaIntiTerisi()) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal salah satu: Diagnose, Tindakan, atau Operator.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key;
            $this->incrementVersion('modal-surgical-safety-checklist-ri');
            $this->dispatch('toast', type: 'success', message: 'Draft tersimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    private function semuaTtdTerisi(): bool
    {
        return collect(self::TTD_ROLES)->every(fn($r) => filled($this->newForm[$r['field']] ?? null));
    }

    public function setTtdRole(string $role): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $info = self::TTD_ROLES[$role] ?? null;
        if (!$info) {
            return;
        }

        $this->validateWithToast();

        $field = $info['field'];
        $this->newForm[$field] = auth()->user()->myuser_name ?? '';
        $this->newForm[$field . 'Code'] = auth()->user()->myuser_code ?? '';
        $this->newForm[$field . 'Date'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $finalized = $this->semuaTtdTerisi();
        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, $finalized, 'TTD ' . $info['label'] . ($finalized ? ' + Kunci' : ''));
            if ($finalized) {
                $this->resetNewForm();
                $this->editingKey = null;
                $this->viewOnly = false;
                $this->dispatch('toast', type: 'success', message: 'Ketiga TTD lengkap — Surgical Safety Checklist terkunci.');
            } else {
                $this->editingKey = $key;
                $this->dispatch('toast', type: 'success', message: 'TTD ' . $info['label'] . ' tersimpan. Entri terkunci otomatis setelah ketiga TTD lengkap.');
            }
            $this->incrementVersion('modal-surgical-safety-checklist-ri');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan TTD: ' . $e->getMessage());
        }
    }

    public function clearTtdRole(string $role): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $info = self::TTD_ROLES[$role] ?? null;
        if (!$info) {
            return;
        }
        $field = $info['field'];
        $this->newForm[$field] = '';
        $this->newForm[$field . 'Code'] = '';
        $this->newForm[$field . 'Date'] = '';
    }

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
                $idx = collect($list)->search(fn($it) => ($it['createdAt'] ?? '') === $createdAt);
                if ($idx === false) {
                    throw new \RuntimeException('Entri tidak ditemukan.');
                }

                $list[$idx]['finalized'] = false;
                foreach (self::TTD_ROLES as $info) {
                    $list[$idx][$info['field']] = '';
                    $list[$idx][$info['field'] . 'Code'] = '';
                    $list[$idx][$info['field'] . 'Date'] = '';
                }
                $fresh[$this->jsonKey] = array_values($list);

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->surgicalSafetyChecklistList = $fresh[$this->jsonKey];

                $pelaku = auth()->user()->myuser_name ?? '-';
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Buka kunci Surgical Safety Checklist (' . $createdAt . ') oleh ' . $pelaku . ' — ketiga TTD dicabut', 'MR');
            });

            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }
            $this->incrementVersion('modal-surgical-safety-checklist-ri');
            $this->dispatch('toast', type: 'success', message: 'Kunci dibuka — ketiga TTD dicabut, entri kembali Draft.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuka kunci: ' . $e->getMessage());
        }
    }

    private function hydrateFormFromEntry(array $entry, string $key): void
    {
        foreach ($this->newForm as $k => $v) {
            $this->newForm[$k] = $entry[$k] ?? (is_bool($v) ? false : (is_array($v) ? [] : ''));
        }
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-surgical-safety-checklist-ri');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->surgicalSafetyChecklistList)->firstWhere('createdAt', $key);
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

    public function viewEntry(string $key): void
    {
        $entry = collect($this->surgicalSafetyChecklistList)->firstWhere('createdAt', $key);
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
        $this->incrementVersion('modal-surgical-safety-checklist-ri');
    }

    public function cetak(string $createdAt)
    {
        $entry = collect($this->surgicalSafetyChecklistList)->firstWhere('createdAt', $createdAt);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan.');
            return;
        }
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

            // Ambil TTD gambar untuk operator (TTD utama di dokumen cetak).
            $ttdPath = null;
            $ttdCode = $entry['ttdOperatorCode'] ?? null;
            if ($ttdCode) {
                $path = DB::table('users')->where('myuser_code', $ttdCode)->value('myuser_ttd_image');
                if (!empty($path) && file_exists(public_path('storage/' . $path))) {
                    $ttdPath = public_path('storage/' . $path);
                }
            }

            $data = array_merge($pasien, [
                'dataRi' => $this->dataDaftarRi, 'form' => $entry, 'identitasRs' => $identitasRs,
                'ttdPath' => $ttdPath, 'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);
            set_time_limit(300);
            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.surgical-safety-checklist-ri.cetak-surgical-safety-checklist-ri-print', ['data' => $data])->setPaper('A4');
            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak Surgical Safety Checklist.');
            return response()->streamDownload(fn() => print $pdf->output(), 'surgical-safety-checklist-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
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
                $this->lockRIRow($this->riHdrNo);
                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                if (!isset($fresh[$this->jsonKey])) {
                    throw new \RuntimeException('Data tidak ditemukan.');
                }
                $fresh[$this->jsonKey] = collect($fresh[$this->jsonKey])->reject(fn($item) => ($item['createdAt'] ?? '') === $createdAt)->values()->toArray();
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->surgicalSafetyChecklistList = $fresh[$this->jsonKey];
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Surgical Safety Checklist — ' . $createdAt, 'MR');
            });

            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }

            $this->incrementVersion('modal-surgical-safety-checklist-ri');
            $this->dispatch('toast', type: 'success', message: 'Surgical Safety Checklist berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    private function resetNewForm(): void
    {
        $this->newForm = [
            'diagnosa' => '', 'tindakan' => '', 'operator' => '', 'anestesi' => '', 'instrumen' => '',
            'tanggal' => '', 'asisten1' => '', 'asisten2' => '', 'jamInduksi' => '', 'jamInsisi' => '', 'jamSelesai' => '',
            'jamSignIn' => '', 'identitasAreaTindakanPersetujuan' => '', 'areaOperasiDitandai' => '',
            'mesinAnestesiObatDiperiksa' => '', 'pulseOksimeterBerfungsi' => false,
            'riwayatAlergi' => false, 'riwayatAlergiKet' => '',
            'penyulitAirwayResikoAspirasi' => false, 'penyulitAirwayKet' => '',
            'resikoKehilanganDarah' => false,
            'jamTimeOut' => '', 'timMemperkenalkanNamaTugas' => '', 'konfirmasiNamaTindakanArea' => '', 'antibiotikProfilaksis' => '',
            'operatorTindakanDarurat' => false, 'operatorLamaOperasi' => '', 'operatorAntisipasiKehilanganDarah' => '',
            'anestesiPerhatianKhusus' => false,
            'instrumenPeralatanDisterilisasi' => false, 'instrumenPerhatianKhususPeralatan' => '', 'instrumenInstrumentasiRadiologi' => false,
            'jamSignOut' => '',
            'perawatMembacakanJenisTindakan' => false, 'kecocokanJumlahInstrumenKasaJarum' => false,
            'labelSpesimen' => false, 'permasalahanAlat' => false, 'perhatianKhususRecovery' => '',
            'ttdDokterAnestesi' => '', 'ttdDokterAnestesiCode' => '', 'ttdDokterAnestesiDate' => '',
            'ttdPerawatInstrumen' => '', 'ttdPerawatInstrumenCode' => '', 'ttdPerawatInstrumenDate' => '',
            'ttdOperator' => '', 'ttdOperatorCode' => '', 'ttdOperatorDate' => '',
        ];
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRi = [];
        $this->surgicalSafetyChecklistList = [];
        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
    }
};
?>

<div>
    @php $entriCount = count($surgicalSafetyChecklistList ?? []); @endphp

    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-3">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Surgical Safety Checklist</h3>
                    @if ($entriCount > 0) <x-badge variant="success">{{ $entriCount }} checklist</x-badge>
                    @else <x-badge variant="warning">Belum ada</x-badge> @endif
                </div>
                <p class="text-base text-muted dark:text-gray-400">
                    WHO Surgical Safety Checklist — verifikasi tiga fase: Sign In (sebelum anestesi),
                    Time Out (sebelum insisi), dan Sign Out (sebelum meninggalkan kamar operasi).
                </p>
                @if ($entriCount > 0)
                    <ul class="space-y-1 text-base text-muted dark:text-gray-300 list-disc pl-5">
                        @foreach (array_slice(array_reverse($surgicalSafetyChecklistList), 0, 3) as $entri)
                            <li>
                                <span class="font-medium">{{ $entri['tindakan'] ?? '-' }}</span>
                                @if (!empty($entri['tanggal'])) <span class="text-sm text-muted-soft">— {{ $entri['tanggal'] }}</span> @endif
                            </li>
                        @endforeach
                        @if ($entriCount > 3) <li class="text-sm italic text-muted-soft">+{{ $entriCount - 3 }} lainnya…</li> @endif
                    </ul>
                @endif
            </div>
            <div class="flex shrink-0">
                <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled" wire:target="openModal" :disabled="$disabled || !$riHdrNo" class="gap-2">
                    <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                        Buka Formulir
                    </span>
                    <span wire:loading wire:target="openModal" class="flex items-center gap-1.5"><x-loading class="w-4 h-4" /> Memuat...</span>
                </x-primary-button>
            </div>
        </div>
    </div>

    <x-modal name="rm-surgical-safety-checklist-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]" wire:key="{{ $this->renderKey('modal-surgical-safety-checklist-ri', [$riHdrNo ?? 'new']) }}">

            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-teal-500/10">
                                <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <div>
                                <h2 class="font-semibold text-2xl text-ink dark:text-gray-100">Surgical Safety Checklist</h2>
                                <p class="mt-0.5 text-base text-muted dark:text-gray-400">Rawat Inap — WHO SSC</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="brand">Rawat Inap</x-badge>
                            @if (count($surgicalSafetyChecklistList) > 0) <x-badge variant="info">{{ count($surgicalSafetyChecklistList) }} tersimpan</x-badge> @endif
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
                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo" wire:key="ssc-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />

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
                                Sedang melanjutkan entri <strong>{{ $editingKey }}</strong> — <strong>Simpan Perubahan</strong> menyimpan ke entri ini; klik <strong>Entri Baru</strong> untuk menambah checklist lain.
                            </div>
                        @endif

                        <fieldset @disabled($formReadOnly) class="space-y-6">

                            {{-- ══ HEADER ══ --}}
                            <section class="space-y-4">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Informasi Operasi</h3>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    <div>
                                        <x-input-label value="Tanggal *" class="mb-1" />
                                        <div class="flex items-center gap-2">
                                            <x-text-input wire:model.live="newForm.tanggal" placeholder="dd/mm/yyyy HH:mm:ss" :error="$errors->has('newForm.tanggal')" class="w-full" />
                                            @if (!$formReadOnly) <x-now-button wire:click="setTanggalSekarang" /> @endif
                                        </div>
                                        <x-input-error :messages="$errors->get('newForm.tanggal')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Diagnose *" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.diagnosa" :error="$errors->has('newForm.diagnosa')" class="w-full" />
                                        <x-input-error :messages="$errors->get('newForm.diagnosa')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Tindakan *" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.tindakan" :error="$errors->has('newForm.tindakan')" class="w-full" />
                                        <x-input-error :messages="$errors->get('newForm.tindakan')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Operator *" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.operator" :error="$errors->has('newForm.operator')" class="w-full" />
                                        <x-input-error :messages="$errors->get('newForm.operator')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Anestesi *" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.anestesi" :error="$errors->has('newForm.anestesi')" class="w-full" />
                                        <x-input-error :messages="$errors->get('newForm.anestesi')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Instrumen *" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.instrumen" :error="$errors->has('newForm.instrumen')" class="w-full" />
                                        <x-input-error :messages="$errors->get('newForm.instrumen')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Asisten 1" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.asisten1" :error="$errors->has('newForm.asisten1')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Asisten 2" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.asisten2" :error="$errors->has('newForm.asisten2')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Jam Induksi" class="mb-1" />
                                        <div class="flex items-center gap-2">
                                            <x-text-input wire:model.live="newForm.jamInduksi" placeholder="HH:mm:ss" :error="$errors->has('newForm.jamInduksi')" class="w-full" />
                                            @if (!$formReadOnly) <x-now-button wire:click="setJamSekarang('jamInduksi')" /> @endif
                                        </div>
                                    </div>
                                    <div>
                                        <x-input-label value="Jam Insisi" class="mb-1" />
                                        <div class="flex items-center gap-2">
                                            <x-text-input wire:model.live="newForm.jamInsisi" placeholder="HH:mm:ss" :error="$errors->has('newForm.jamInsisi')" class="w-full" />
                                            @if (!$formReadOnly) <x-now-button wire:click="setJamSekarang('jamInsisi')" /> @endif
                                        </div>
                                    </div>
                                    <div>
                                        <x-input-label value="Jam Selesai" class="mb-1" />
                                        <div class="flex items-center gap-2">
                                            <x-text-input wire:model.live="newForm.jamSelesai" placeholder="HH:mm:ss" :error="$errors->has('newForm.jamSelesai')" class="w-full" />
                                            @if (!$formReadOnly) <x-now-button wire:click="setJamSekarang('jamSelesai')" /> @endif
                                        </div>
                                    </div>
                                </div>
                            </section>

                            {{-- ══ TIGA FASE ══ --}}
                            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

                                {{-- SIGN IN --}}
                                <x-border-form title="Sebelum Anestesi (SIGN IN)" align="start" bgcolor="bg-surface-soft">
                                    <div class="space-y-4">
                                        <div>
                                            <x-input-label value="Jam" class="mb-1" />
                                            <div class="flex items-center gap-2">
                                                <x-text-input wire:model.live="newForm.jamSignIn" placeholder="HH:mm:ss" class="w-full" />
                                                @if (!$formReadOnly) <x-now-button wire:click="setJamSekarang('jamSignIn')" /> @endif
                                            </div>
                                        </div>

                                        <div>
                                            <x-input-label value="Identitas, area, tindakan, & persetujuan" class="mb-1" />
                                            <x-select-input wire:model.live="newForm.identitasAreaTindakanPersetujuan" class="w-full">
                                                <option value="">— pilih —</option>
                                                @foreach ($sudahBelumOptions as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
                                            </x-select-input>
                                        </div>

                                        <div>
                                            <x-input-label value="Area operasi ditandai" class="mb-1" />
                                            <x-select-input wire:model.live="newForm.areaOperasiDitandai" class="w-full">
                                                <option value="">— pilih —</option>
                                                @foreach ($sudahBelumTidakPerluOptions as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
                                            </x-select-input>
                                        </div>

                                        <div>
                                            <x-input-label value="Mesin anestesi & obat diperiksa" class="mb-1" />
                                            <x-select-input wire:model.live="newForm.mesinAnestesiObatDiperiksa" class="w-full">
                                                <option value="">— pilih —</option>
                                                @foreach ($sudahBelumOptions as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
                                            </x-select-input>
                                        </div>

                                        <div>
                                            <x-toggle wire:model.live="newForm.pulseOksimeterBerfungsi" :trueValue="true" :falseValue="false" label="Pulse oksimeter berfungsi" :disabled="$formReadOnly" />
                                        </div>

                                        <div class="space-y-2">
                                            <x-toggle wire:model.live="newForm.riwayatAlergi" :trueValue="true" :falseValue="false" label="Ada riwayat alergi" :disabled="$formReadOnly" />
                                            @if ($newForm['riwayatAlergi'])
                                                <x-text-input wire:model.live="newForm.riwayatAlergiKet" placeholder="Keterangan alergi" class="w-full" />
                                            @endif
                                        </div>

                                        <div class="space-y-2">
                                            <x-toggle wire:model.live="newForm.penyulitAirwayResikoAspirasi" :trueValue="true" :falseValue="false" label="Penyulit airway / resiko aspirasi" :disabled="$formReadOnly" />
                                            @if ($newForm['penyulitAirwayResikoAspirasi'])
                                                <x-text-input wire:model.live="newForm.penyulitAirwayKet" placeholder="Tersedia peralatan / keterangan" class="w-full" />
                                            @endif
                                        </div>

                                        <div>
                                            <x-toggle wire:model.live="newForm.resikoKehilanganDarah" :trueValue="true" :falseValue="false" label="Resiko kehilangan darah >500ml / 7cc/kgBB" :disabled="$formReadOnly" />
                                        </div>
                                    </div>
                                </x-border-form>

                                {{-- TIME OUT --}}
                                <x-border-form title="Sebelum Insisi (TIME OUT)" align="start" bgcolor="bg-surface-soft">
                                    <div class="space-y-4">
                                        <div>
                                            <x-input-label value="Jam" class="mb-1" />
                                            <div class="flex items-center gap-2">
                                                <x-text-input wire:model.live="newForm.jamTimeOut" placeholder="HH:mm:ss" class="w-full" />
                                                @if (!$formReadOnly) <x-now-button wire:click="setJamSekarang('jamTimeOut')" /> @endif
                                            </div>
                                        </div>

                                        <div>
                                            <x-input-label value="Tim memperkenalkan nama & tugas" class="mb-1" />
                                            <x-select-input wire:model.live="newForm.timMemperkenalkanNamaTugas" class="w-full">
                                                <option value="">— pilih —</option>
                                                @foreach ($sudahBelumOptions as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
                                            </x-select-input>
                                        </div>

                                        <div>
                                            <x-input-label value="Konfirmasi nama pasien, tindakan, & area" class="mb-1" />
                                            <x-select-input wire:model.live="newForm.konfirmasiNamaTindakanArea" class="w-full">
                                                <option value="">— pilih —</option>
                                                @foreach ($sudahBelumOptions as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
                                            </x-select-input>
                                        </div>

                                        <div>
                                            <x-input-label value="Antibiotik profilaksis diberikan <60 menit" class="mb-1" />
                                            <x-select-input wire:model.live="newForm.antibiotikProfilaksis" class="w-full">
                                                <option value="">— pilih —</option>
                                                @foreach ($sudahBelumOptions as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
                                            </x-select-input>
                                        </div>

                                        <div class="pt-3 border-t border-hairline-soft dark:border-gray-700">
                                            <p class="mb-2 text-sm font-semibold text-ink dark:text-gray-200">Antisipasi Kejadian Kritis</p>

                                            <div class="space-y-3">
                                                <div class="p-3 rounded-lg bg-canvas dark:bg-gray-900 border border-hairline dark:border-gray-700 space-y-2">
                                                    <p class="text-sm font-medium text-muted dark:text-gray-400">Operator</p>
                                                    <x-toggle wire:model.live="newForm.operatorTindakanDarurat" :trueValue="true" :falseValue="false" label="Tindakan darurat / prosedur luar standar" :disabled="$formReadOnly" />
                                                    <x-text-input wire:model.live="newForm.operatorLamaOperasi" placeholder="Lama operasi" class="w-full" />
                                                    <x-text-input wire:model.live="newForm.operatorAntisipasiKehilanganDarah" placeholder="Antisipasi kehilangan darah" class="w-full" />
                                                </div>

                                                <div class="p-3 rounded-lg bg-canvas dark:bg-gray-900 border border-hairline dark:border-gray-700 space-y-2">
                                                    <p class="text-sm font-medium text-muted dark:text-gray-400">Anestesi</p>
                                                    <x-toggle wire:model.live="newForm.anestesiPerhatianKhusus" :trueValue="true" :falseValue="false" label="Perhatian khusus pembiusan" :disabled="$formReadOnly" />
                                                </div>

                                                <div class="p-3 rounded-lg bg-canvas dark:bg-gray-900 border border-hairline dark:border-gray-700 space-y-2">
                                                    <p class="text-sm font-medium text-muted dark:text-gray-400">Instrumen</p>
                                                    <x-toggle wire:model.live="newForm.instrumenPeralatanDisterilisasi" :trueValue="true" :falseValue="false" label="Peralatan disterilisasi" :disabled="$formReadOnly" />
                                                    <x-text-input wire:model.live="newForm.instrumenPerhatianKhususPeralatan" placeholder="Perhatian khusus peralatan" class="w-full" />
                                                    <x-toggle wire:model.live="newForm.instrumenInstrumentasiRadiologi" :trueValue="true" :falseValue="false" label="Instrumentasi radiologi" :disabled="$formReadOnly" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </x-border-form>

                                {{-- SIGN OUT --}}
                                <x-border-form title="Sebelum Meninggalkan Kamar Operasi (SIGN OUT)" align="start" bgcolor="bg-surface-soft">
                                    <div class="space-y-4">
                                        <div>
                                            <x-input-label value="Jam" class="mb-1" />
                                            <div class="flex items-center gap-2">
                                                <x-text-input wire:model.live="newForm.jamSignOut" placeholder="HH:mm:ss" class="w-full" />
                                                @if (!$formReadOnly) <x-now-button wire:click="setJamSekarang('jamSignOut')" /> @endif
                                            </div>
                                        </div>

                                        <p class="text-sm font-semibold text-ink dark:text-gray-200">Perawat membacakan:</p>

                                        <div>
                                            <x-toggle wire:model.live="newForm.perawatMembacakanJenisTindakan" :trueValue="true" :falseValue="false" label="Jenis tindakan" :disabled="$formReadOnly" />
                                        </div>

                                        <div>
                                            <x-toggle wire:model.live="newForm.kecocokanJumlahInstrumenKasaJarum" :trueValue="true" :falseValue="false" label="Kecocokan jumlah instrumen/kasa/jarum" :disabled="$formReadOnly" />
                                        </div>

                                        <div>
                                            <x-toggle wire:model.live="newForm.labelSpesimen" :trueValue="true" :falseValue="false" label="Label spesimen" :disabled="$formReadOnly" />
                                        </div>

                                        <div>
                                            <x-toggle wire:model.live="newForm.permasalahanAlat" :trueValue="true" :falseValue="false" label="Permasalahan alat" :disabled="$formReadOnly" />
                                        </div>

                                        <div class="pt-3 border-t border-hairline-soft dark:border-gray-700">
                                            <x-input-label value="Perhatian khusus masa pemulihan / recovery" class="mb-1" />
                                            <x-textarea wire:model.live="newForm.perhatianKhususRecovery" rows="3" class="w-full" />
                                        </div>
                                    </div>
                                </x-border-form>

                            </div>

                            {{-- ══ TTD 3 PIHAK = KUNCI ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Tanda Tangan (3 Pihak)</h3>
                                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                    <x-signature.ttd-petugas :ttd="$newForm['ttdDokterAnestesi']" :date="$newForm['ttdDokterAnestesiDate'] ?? ''"
                                        :code="$newForm['ttdDokterAnestesiCode'] ?? ''" :locked="$formReadOnly"
                                        sign="setTtdRole('dokterAnestesi')" clear="clearTtdRole('dokterAnestesi')"
                                        title="Dokter Anestesi" nameLabel="Dokter Anestesi" dateLabel="Waktu TTD"
                                        signLabel="TTD Dokter Anestesi" clearLabel="Batal TTD" />
                                    <x-signature.ttd-petugas :ttd="$newForm['ttdPerawatInstrumen']" :date="$newForm['ttdPerawatInstrumenDate'] ?? ''"
                                        :code="$newForm['ttdPerawatInstrumenCode'] ?? ''" :locked="$formReadOnly"
                                        sign="setTtdRole('perawatInstrumen')" clear="clearTtdRole('perawatInstrumen')"
                                        title="Perawat Instrumen" nameLabel="Perawat Instrumen" dateLabel="Waktu TTD"
                                        signLabel="TTD Perawat Instrumen" clearLabel="Batal TTD" />
                                    <x-signature.ttd-petugas :ttd="$newForm['ttdOperator']" :date="$newForm['ttdOperatorDate'] ?? ''"
                                        :code="$newForm['ttdOperatorCode'] ?? ''" :locked="$formReadOnly"
                                        sign="setTtdRole('operator')" clear="clearTtdRole('operator')"
                                        title="Operator" nameLabel="Operator" dateLabel="Waktu TTD"
                                        signLabel="TTD Operator" clearLabel="Batal TTD" />
                                </div>
                                @if (!$formReadOnly)
                                    <p class="-mt-2 text-xs text-center text-muted">
                                        Tiap TTD langsung tersimpan (bisa menyusul oleh user berbeda). Entri otomatis <strong>terkunci</strong> saat ketiga TTD lengkap.
                                    </p>
                                @endif
                            </section>
                        </fieldset>

                        {{-- ── DAFTAR CHECKLIST TERSIMPAN (expandable) ── --}}
                        @if (count($surgicalSafetyChecklistList) > 0)
                            <div class="mt-6">
                                <h3 class="text-base font-semibold text-body dark:text-gray-300 pb-2 border-b border-hairline-soft dark:border-gray-800 mb-3">Daftar Checklist Tersimpan</h3>
                                <p class="mb-3 text-xs italic text-muted-soft">Klik baris untuk lihat detail lengkap</p>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-base border border-hairline rounded-lg dark:border-gray-700">
                                        <thead class="bg-surface-soft dark:bg-gray-800">
                                            <tr class="text-left text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-300">
                                                <th class="w-8 px-2 py-3 border-b"></th>
                                                <th class="px-4 py-3 border-b">Tanggal</th>
                                                <th class="px-4 py-3 border-b">Tindakan</th>
                                                <th class="px-4 py-3 border-b">TTD (3 Pihak)</th>
                                                <th class="px-4 py-3 text-center border-b">Status</th>
                                                <th class="px-4 py-3 text-center border-b">Aksi</th>
                                            </tr>
                                        </thead>
                                        @foreach (array_reverse($surgicalSafetyChecklistList) as $entry)
                                            @php
                                                $isFinal = $this->entryIsFinal($entry);
                                                $rowKey = $entry['createdAt'] ?? '';
                                                $entryTtdCount = collect(['ttdDokterAnestesi', 'ttdPerawatInstrumen', 'ttdOperator'])->filter(fn($k) => !empty($entry[$k]))->count();
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
                                                    <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">{{ $entry['tindakan'] ? Str::limit($entry['tindakan'], 45) : '-' }}</td>
                                                    <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                        <x-badge :variant="$entryTtdCount === 3 ? 'success' : ($entryTtdCount > 0 ? 'warning' : 'danger')">{{ $entryTtdCount }}/3 TTD</x-badge>
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
                                                                @can('dokumen.hapus')
                                                                <x-outline-button type="button" wire:click.prevent="hapus('{{ $rowKey }}')" wire:confirm="Yakin hapus checklist ini?" wire:loading.attr="disabled"
                                                                    class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300" title="Hapus">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                                </x-outline-button>
                                                                @endcan
                                                                @if ($isFinal)
                                                                    @can('dokumen.bukaKunci')
                                                                    <x-outline-button type="button" wire:click="bukaKunci('{{ $rowKey }}')" wire:confirm="Yakin buka kunci? Ketiga TTD akan dicabut." wire:loading.attr="disabled"
                                                                        class="!text-amber-600 !bg-amber-50 !border-amber-200 hover:!bg-amber-100 hover:!text-amber-700 hover:!border-amber-300 dark:!text-amber-400 dark:!bg-amber-900/20 dark:!border-amber-800/30 dark:hover:!bg-amber-900/30 dark:hover:!text-amber-300" title="Buka Kunci">
                                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" /></svg>
                                                                    </x-outline-button>
                                                                    @endcan
                                                                @endif
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>

                                                {{-- DETAIL (expand) --}}
                                                <tr x-show="open" x-cloak>
                                                    <td colspan="6" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                                        <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                                                            <div><dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tanggal</dt><dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['tanggal'] ?: '-' }}</dd></div>
                                                            <div><dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Diagnosa</dt><dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['diagnosa'] ?: '-' }}</dd></div>
                                                            <div class="md:col-span-2"><dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tindakan</dt><dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['tindakan'] ?: '-' }}</dd></div>
                                                            <div><dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Operator</dt><dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['operator'] ?: '-' }}</dd></div>
                                                            <div><dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Anestesi</dt><dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['anestesi'] ?: '-' }}</dd></div>
                                                            <div><dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Instrumen</dt><dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['instrumen'] ?: '-' }}</dd></div>
                                                            <div><dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Asisten</dt><dd class="mt-0.5 text-ink dark:text-gray-200">{{ trim(($entry['asisten1'] ?? '') . (!empty($entry['asisten1']) && !empty($entry['asisten2']) ? ' & ' : '') . ($entry['asisten2'] ?? '')) ?: '-' }}</dd></div>
                                                            <div><dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jam Induksi / Insisi / Selesai</dt><dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['jamInduksi'] ?: '-') . ' / ' . ($entry['jamInsisi'] ?: '-') . ' / ' . ($entry['jamSelesai'] ?: '-') }}</dd></div>

                                                            <div class="md:col-span-2 pt-2 border-t border-hairline-soft dark:border-gray-700">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Sebelum Anestesi (SIGN IN) — Jam {{ $entry['jamSignIn'] ?: '-' }}</dt>
                                                                <dd class="mt-1 space-y-1 text-ink dark:text-gray-200">
                                                                    <p>Identitas/area/tindakan/persetujuan: <span class="font-medium">{{ $entry['identitasAreaTindakanPersetujuan'] ?: '-' }}</span></p>
                                                                    <p>Area operasi ditandai: <span class="font-medium">{{ $entry['areaOperasiDitandai'] ?: '-' }}</span></p>
                                                                    <p>Mesin anestesi & obat diperiksa: <span class="font-medium">{{ $entry['mesinAnestesiObatDiperiksa'] ?: '-' }}</span></p>
                                                                    <p>Pulse oksimeter berfungsi: <span class="font-medium">{{ !empty($entry['pulseOksimeterBerfungsi']) ? 'Ya' : 'Tidak' }}</span></p>
                                                                    <p>Riwayat alergi: <span class="font-medium">{{ !empty($entry['riwayatAlergi']) ? 'Ya' . (!empty($entry['riwayatAlergiKet']) ? ' — ' . $entry['riwayatAlergiKet'] : '') : 'Tidak' }}</span></p>
                                                                    <p>Penyulit airway / resiko aspirasi: <span class="font-medium">{{ !empty($entry['penyulitAirwayResikoAspirasi']) ? 'Ya' . (!empty($entry['penyulitAirwayKet']) ? ' — ' . $entry['penyulitAirwayKet'] : '') : 'Tidak' }}</span></p>
                                                                    <p>Resiko kehilangan darah: <span class="font-medium">{{ !empty($entry['resikoKehilanganDarah']) ? 'Ya' : 'Tidak' }}</span></p>
                                                                </dd>
                                                            </div>

                                                            <div class="md:col-span-2 pt-2 border-t border-hairline-soft dark:border-gray-700">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Sebelum Insisi (TIME OUT) — Jam {{ $entry['jamTimeOut'] ?: '-' }}</dt>
                                                                <dd class="mt-1 space-y-1 text-ink dark:text-gray-200">
                                                                    <p>Tim memperkenalkan nama & tugas: <span class="font-medium">{{ $entry['timMemperkenalkanNamaTugas'] ?: '-' }}</span></p>
                                                                    <p>Konfirmasi nama/tindakan/area: <span class="font-medium">{{ $entry['konfirmasiNamaTindakanArea'] ?: '-' }}</span></p>
                                                                    <p>Antibiotik profilaksis: <span class="font-medium">{{ $entry['antibiotikProfilaksis'] ?: '-' }}</span></p>
                                                                    <p class="font-medium">Antisipasi kejadian kritis:</p>
                                                                    <ul class="pl-4 list-disc text-sm">
                                                                        <li>Operator: {{ !empty($entry['operatorTindakanDarurat']) ? 'Ya' : 'Tidak' }}{{ !empty($entry['operatorLamaOperasi']) ? ' · Lama: ' . $entry['operatorLamaOperasi'] : '' }}{{ !empty($entry['operatorAntisipasiKehilanganDarah']) ? ' · Antisipasi darah: ' . $entry['operatorAntisipasiKehilanganDarah'] : '' }}</li>
                                                                        <li>Anestesi: {{ !empty($entry['anestesiPerhatianKhusus']) ? 'Ya' : 'Tidak' }}</li>
                                                                        <li>Instrumen: {{ !empty($entry['instrumenPeralatanDisterilisasi']) ? 'Ya' : 'Tidak' }}{{ !empty($entry['instrumenPerhatianKhususPeralatan']) ? ' · ' . $entry['instrumenPerhatianKhususPeralatan'] : '' }} · Radiologi: {{ !empty($entry['instrumenInstrumentasiRadiologi']) ? 'Ya' : 'Tidak' }}</li>
                                                                    </ul>
                                                                </dd>
                                                            </div>

                                                            <div class="md:col-span-2 pt-2 border-t border-hairline-soft dark:border-gray-700">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Sebelum Meninggalkan Kamar Operasi (SIGN OUT) — Jam {{ $entry['jamSignOut'] ?: '-' }}</dt>
                                                                <dd class="mt-1 space-y-1 text-ink dark:text-gray-200">
                                                                    <p>Jenis tindakan: <span class="font-medium">{{ !empty($entry['perawatMembacakanJenisTindakan']) ? 'Ya' : 'Tidak' }}</span></p>
                                                                    <p>Kecocokan instrumen/kasa/jarum: <span class="font-medium">{{ !empty($entry['kecocokanJumlahInstrumenKasaJarum']) ? 'Ya' : 'Tidak' }}</span></p>
                                                                    <p>Label spesimen: <span class="font-medium">{{ !empty($entry['labelSpesimen']) ? 'Ya' : 'Tidak' }}</span></p>
                                                                    <p>Permasalahan alat: <span class="font-medium">{{ !empty($entry['permasalahanAlat']) ? 'Ya' : 'Tidak' }}</span></p>
                                                                    <p>Perhatian khusus recovery: <span class="font-medium">{{ $entry['perhatianKhususRecovery'] ?: '-' }}</span></p>
                                                                </dd>
                                                            </div>

                                                            <div class="md:col-span-2 pt-2 border-t border-hairline-soft dark:border-gray-700">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tanda Tangan</dt>
                                                                <dd class="mt-1 grid grid-cols-1 md:grid-cols-3 gap-2 text-sm">
                                                                    <p>Dokter Anestesi: <span class="font-medium">{{ $entry['ttdDokterAnestesi'] ?: '-' }}</span></p>
                                                                    <p>Perawat Instrumen: <span class="font-medium">{{ $entry['ttdPerawatInstrumen'] ?: '-' }}</span></p>
                                                                    <p>Operator: <span class="font-medium">{{ $entry['ttdOperator'] ?: '-' }}</span></p>
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
                            <span>Simpan draft dulu, lalu <strong>kunci</strong> lewat tombol <strong>TTD Petugas</strong>.</span>
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
                                    title="Kosongkan form untuk menambah checklist lain — entri yang sudah tersimpan tidak berubah">
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
