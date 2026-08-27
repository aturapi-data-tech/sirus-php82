<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/pengkajian-pre-op-ri/rm-pengkajian-pre-op-ri-actions.blade.php
// Pengkajian Pre Operasi (RM 49) — persiapan pasien & serah-terima ruangan → OK,
// GABUNG dengan Penandaan Lokasi Operasi (Site Marking, SKP 4): perlu penandaan,
// region/sisi/detail lokasi + diagram tubuh (marks).
// Pola: multi-entri (Draft + Lanjutkan Pengisian + TTD + Lihat read-only + tabel expandable),
// disimpan ke datadaftarri_json (jsonKey = pengkajianPreOpRI). Kunci entri stabil = createdAt.
// TTD 3 PIHAK (stamp user login, setTtdRole): Perawat Ruangan + Perawat Kamar Bedah +
// Dokter Operator. Entri otomatis TERKUNCI saat KETIGA TTD terisi (TTD terakhir = finalize);
// TTD bisa menyusul antar user/sesi (tiap TTD langsung tersimpan ke DB).
// bukaKunci() = cabut status final + RESET KETIGA TTD (proses TTD diulang dari awal).

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
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
    protected array $renderAreas = ['modal-pengkajian-pre-op-ri'];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'pengkajianPreOpRI';

    // ── Form entri baru (Pengkajian Pre Operasi keperawatan — RM 49) ──
    public array $newForm = [
        'diagnosaPreOp' => '',
        'rencanaOperasi' => '',
        'dokterOperator' => '',
        'tanggalOperasi' => '',
        'perjanjianPerawatOk' => '',
        'urgensi' => '',
        // Keadaan pra bedah — klasifikasi mengikuti EMR RJ: Tanda Vital / Nutrisi / Darah
        'sistolik' => '',
        'diastolik' => '',
        'nadi' => '',
        'rr' => '',
        'suhu' => '',
        'spo2' => '',
        'gda' => '',
        'bb' => '',
        'tb' => '',
        'imt' => '',
        'hb' => '',
        'golDarah' => '',
        // Persiapan pasien
        // Pre Medikasi / Cairan / Obat — daftar array ala rekonsiliasi obat: {jenis, nama, tglJam}
        'persiapanObatCairan' => [],
        'puasaMulaiJam' => '',
        'sudahDicukur' => false,
        'persiapanDarah' => false,
        'gigiPalsuDilepas' => false,
        'pengosonganKandungKemih' => false,
        'clysma' => false,
        'riwayatPenyakit' => false,
        'riwayatPenyakitKet' => '',
        'lainLain' => '',
        // Persiapan administrasi (sertakan ke OK)
        'adaRekamMedis' => false,
        'adaSuratIjin' => false,
        'adaLab' => false,
        'adaRadiologi' => false,
        'radiologiJenis' => '',
        'adaDiagnostik' => false,
        'diagnostikJenis' => '',
        // Penandaan Lokasi Operasi (Site Marking, SKP 4)
        'perluPenandaan' => 'Ya',
        'alasanTidakPerlu' => '',
        'regionAnatomi' => '',
        'sisi' => '',
        'detailLokasi' => '',
        'metodePenandaan' => 'Spidol permanen - inisial/tanda operator',
        'pasienDilibatkan' => false,
        'marks' => [],
        // TTD 3 pihak (stamp user login) — entri TERKUNCI saat ketiganya terisi
        'ttdPerawatRuangan' => '',
        'ttdPerawatRuanganCode' => '',
        'ttdPerawatRuanganDate' => '',
        'ttdPerawatKamarBedah' => '',
        'ttdPerawatKamarBedahCode' => '',
        'ttdPerawatKamarBedahDate' => '',
        'ttdDokterOperator' => '',
        'ttdDokterOperatorCode' => '',
        'ttdDokterOperatorDate' => '',
    ];

    public array $preOpList = [];

    public array $urgensiOptions = ['Elektif', 'Cito'];

    // ── Opsi Penandaan Lokasi Operasi (Site Marking) ──
    public array $perluOptions = ['Ya', 'Tidak diperlukan'];
    public array $sisiOptions = ['Kiri', 'Kanan', 'Bilateral', 'Garis tengah', 'Multipel level'];
    public array $regionOptions = [
        'Kepala & Leher', 'Mata', 'THT', 'Gigi & Mulut', 'Dada / Thoraks', 'Payudara',
        'Abdomen', 'Punggung / Spinal', 'Panggul', 'Genitalia',
        'Ekstremitas Atas', 'Ekstremitas Bawah', 'Tangan / Jari Tangan', 'Kaki / Jari Kaki', 'Lainnya',
    ];

    // Kunci entri yang sedang diedit (createdAt = kunci stabil, di-set saat entri pertama dibuat).
    // null = sedang membuat entri baru.
    public ?string $editingKey = null;

    // true = entri terkunci sedang ditampilkan di form dalam mode read-only (lihat saja, tak bisa edit).
    public bool $viewOnly = false;

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-pengkajian-pre-op-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->preOpList = $data[$this->jsonKey] ?? [];
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
        $this->preOpList = $this->dataDaftarRi[$this->jsonKey];
        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;
        $this->incrementVersion('modal-pengkajian-pre-op-ri');

        $this->dispatch('open-modal', name: "rm-pengkajian-pre-op-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-pengkajian-pre-op-ri-{$this->riHdrNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'newForm.diagnosaPreOp' => 'required|string|max:500',
            'newForm.rencanaOperasi' => 'required|string|max:500',
            'newForm.dokterOperator' => 'required|string|max:200',
            'newForm.tanggalOperasi' => 'nullable|string|max:30',
            'newForm.urgensi' => 'required|string',
            'newForm.riwayatPenyakitKet' => 'nullable|string|max:300',
            'newForm.radiologiJenis' => 'nullable|string|max:200',
            'newForm.diagnostikJenis' => 'nullable|string|max:200',
            'newForm.lainLain' => 'nullable|string|max:1000',
            'newForm.perjanjianPerawatOk' => 'nullable|string|max:200',
            'newForm.perluPenandaan' => 'required|string',
            'newForm.alasanTidakPerlu' => 'required_if:newForm.perluPenandaan,Tidak diperlukan|nullable|string|max:500',
            'newForm.regionAnatomi' => 'required_if:newForm.perluPenandaan,Ya|nullable|string|max:100',
            'newForm.sisi' => 'required_if:newForm.perluPenandaan,Ya|nullable|string|max:50',
            'newForm.detailLokasi' => 'nullable|string|max:300',
            'newForm.metodePenandaan' => 'nullable|string|max:300',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'required_if' => ':attribute wajib diisi.',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newForm.diagnosaPreOp' => 'Diagnosa pre operasi',
            'newForm.rencanaOperasi' => 'Rencana operasi',
            'newForm.dokterOperator' => 'Dokter operator',
            'newForm.tanggalOperasi' => 'Tanggal operasi',
            'newForm.urgensi' => 'Urgensi operasi',
            'newForm.perjanjianPerawatOk' => 'Perjanjian dgn perawat OK',
            'newForm.perluPenandaan' => 'Penandaan lokasi',
            'newForm.alasanTidakPerlu' => 'Alasan tidak perlu penandaan',
            'newForm.regionAnatomi' => 'Region anatomi',
            'newForm.sisi' => 'Sisi/lateralitas',
        ];
    }

    /* ===============================
     | HELPER — status & bentuk entri
     =============================== */
    // Entri dianggap FINAL/terkunci bila flag finalized true; entri lama (tanpa flag) yang sudah
    // ada TTD (nama penanda) dianggap final (kompatibilitas data lama).
    public function entryIsFinal(array $e): bool
    {
        return array_key_exists('finalized', $e)
            ? (bool) $e['finalized']
            : (!empty($e['ttdPerawatRuangan']) && !empty($e['ttdPerawatKamarBedah']) && !empty($e['ttdDokterOperator']));
    }

    // Susun array entri dari state form. $key = createdAt (kunci stabil); $finalized = status kunci.
    private function buildEntry(string $key, bool $finalized): array
    {
        return [
            'diagnosaPreOp' => $this->newForm['diagnosaPreOp'] ?? '',
            'rencanaOperasi' => $this->newForm['rencanaOperasi'] ?? '',
            'dokterOperator' => $this->newForm['dokterOperator'] ?? '',
            'tanggalOperasi' => $this->newForm['tanggalOperasi'] ?? '',
            'perjanjianPerawatOk' => $this->newForm['perjanjianPerawatOk'] ?? '',
            'urgensi' => $this->newForm['urgensi'] ?? '',
            'sistolik' => $this->newForm['sistolik'] ?? '',
            'diastolik' => $this->newForm['diastolik'] ?? '',
            'nadi' => $this->newForm['nadi'] ?? '',
            'rr' => $this->newForm['rr'] ?? '',
            'suhu' => $this->newForm['suhu'] ?? '',
            'spo2' => $this->newForm['spo2'] ?? '',
            'gda' => $this->newForm['gda'] ?? '',
            'bb' => $this->newForm['bb'] ?? '',
            'tb' => $this->newForm['tb'] ?? '',
            'imt' => $this->imtValue(),
            'hb' => $this->newForm['hb'] ?? '',
            'golDarah' => $this->newForm['golDarah'] ?? '',
            'persiapanObatCairan' => array_values($this->newForm['persiapanObatCairan'] ?? []),
            'puasaMulaiJam' => $this->newForm['puasaMulaiJam'] ?? '',
            'sudahDicukur' => (bool) ($this->newForm['sudahDicukur'] ?? false),
            'persiapanDarah' => (bool) ($this->newForm['persiapanDarah'] ?? false),
            'gigiPalsuDilepas' => (bool) ($this->newForm['gigiPalsuDilepas'] ?? false),
            'pengosonganKandungKemih' => (bool) ($this->newForm['pengosonganKandungKemih'] ?? false),
            'clysma' => (bool) ($this->newForm['clysma'] ?? false),
            'riwayatPenyakit' => (bool) ($this->newForm['riwayatPenyakit'] ?? false),
            'riwayatPenyakitKet' => $this->newForm['riwayatPenyakitKet'] ?? '',
            'lainLain' => $this->newForm['lainLain'] ?? '',
            'adaRekamMedis' => (bool) ($this->newForm['adaRekamMedis'] ?? false),
            'adaSuratIjin' => (bool) ($this->newForm['adaSuratIjin'] ?? false),
            'adaLab' => (bool) ($this->newForm['adaLab'] ?? false),
            'adaRadiologi' => (bool) ($this->newForm['adaRadiologi'] ?? false),
            'radiologiJenis' => $this->newForm['radiologiJenis'] ?? '',
            'adaDiagnostik' => (bool) ($this->newForm['adaDiagnostik'] ?? false),
            'diagnostikJenis' => $this->newForm['diagnostikJenis'] ?? '',
            'perluPenandaan' => $this->newForm['perluPenandaan'] ?? 'Ya',
            'alasanTidakPerlu' => $this->newForm['alasanTidakPerlu'] ?? '',
            'regionAnatomi' => $this->newForm['regionAnatomi'] ?? '',
            'sisi' => $this->newForm['sisi'] ?? '',
            'detailLokasi' => $this->newForm['detailLokasi'] ?? '',
            'metodePenandaan' => $this->newForm['metodePenandaan'] ?? '',
            'pasienDilibatkan' => (bool) ($this->newForm['pasienDilibatkan'] ?? false),
            'marks' => ($this->newForm['perluPenandaan'] ?? '') === 'Ya' ? array_values($this->newForm['marks'] ?? []) : [],
            'ttdPerawatRuangan' => $this->newForm['ttdPerawatRuangan'] ?? '',
            'ttdPerawatRuanganCode' => $this->newForm['ttdPerawatRuanganCode'] ?? '',
            'ttdPerawatRuanganDate' => $this->newForm['ttdPerawatRuanganDate'] ?? '',
            'ttdPerawatKamarBedah' => $this->newForm['ttdPerawatKamarBedah'] ?? '',
            'ttdPerawatKamarBedahCode' => $this->newForm['ttdPerawatKamarBedahCode'] ?? '',
            'ttdPerawatKamarBedahDate' => $this->newForm['ttdPerawatKamarBedahDate'] ?? '',
            'ttdDokterOperator' => $this->newForm['ttdDokterOperator'] ?? '',
            'ttdDokterOperatorCode' => $this->newForm['ttdDokterOperatorCode'] ?? '',
            'ttdDokterOperatorDate' => $this->newForm['ttdDokterOperatorDate'] ?? '',
            'createdAt' => $key,
            'finalized' => $finalized,
        ];
    }

    // Cek: minimal inti pengkajian terisi (untuk draft).
    private function adaIntiPreOp(): bool
    {
        return collect(['diagnosaPreOp', 'rencanaOperasi', 'dokterOperator'])
            ->contains(fn($k) => filled($this->newForm[$k] ?? null));
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
            $this->preOpList = $fresh[$this->jsonKey];

            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Pengkajian Pre Operasi — ' . ($entry['rencanaOperasi'] ?: '-') . ' (' . $key . ')', 'MR');
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
        if (!$this->adaIntiPreOp()) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal salah satu: Diagnosa, Rencana Operasi, atau Dokter Operator.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-pengkajian-pre-op-ri');
            $this->dispatch('toast', type: 'success', message: 'Draft tersimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TTD 3 PIHAK (Perawat Ruangan / Perawat Kamar Bedah / Dokter Operator)
     | Konsep kunci ala Inform Consent: tiap pihak stamp TTD user login, langsung
     | tersimpan ke DB (bisa menyusul antar user/sesi). Entri otomatis TERKUNCI
     | saat KETIGA TTD terisi (TTD terakhir = finalize).
     =============================== */
    private const TTD_ROLES = [
        'perawatRuangan' => ['field' => 'ttdPerawatRuangan', 'label' => 'Perawat Ruangan'],
        'perawatKamarBedah' => ['field' => 'ttdPerawatKamarBedah', 'label' => 'Perawat Kamar Bedah'],
        'dokterOperator' => ['field' => 'ttdDokterOperator', 'label' => 'Dokter Operator'],
    ];

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

        // Validasi penuh sebelum TTD (field wajib RM 49 + penandaan lokasi).
        $this->validateWithToast();

        // Stempel TTD = user login.
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
                $this->dispatch('toast', type: 'success', message: 'Ketiga TTD lengkap — pengkajian terkunci.');
            } else {
                $this->editingKey = $key; // lanjut di entri yang sama, TTD lain menyusul
                $this->dispatch('toast', type: 'success', message: 'TTD ' . $info['label'] . ' tersimpan. Entri terkunci otomatis setelah ketiga TTD lengkap.');
            }
            $this->incrementVersion('modal-pengkajian-pre-op-ri');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan TTD: ' . $e->getMessage());
        }
    }

    /** Batalkan satu TTD pada form (hanya saat entri masih draft / belum terkunci). */
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

    /* ===============================
     | BUKA KUNCI — cabut status final + RESET KETIGA TTD.
     | Beda dari modul lain (yang hanya mencabut TTD petugas): kunci form ini =
     | kesepakatan 3 pihak, jadi buka kunci mengulang proses TTD dari awal.
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
                $this->preOpList = $fresh[$this->jsonKey];

                $pelaku = auth()->user()->myuser_name ?? '-';
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Buka kunci Pengkajian Pre Operasi (' . $createdAt . ') oleh ' . $pelaku . ' — ketiga TTD dicabut', 'MR');
            });

            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }
            $this->incrementVersion('modal-pengkajian-pre-op-ri');
            $this->dispatch('toast', type: 'success', message: 'Kunci dibuka — ketiga TTD dicabut, entri kembali Draft.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuka kunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TANDA DIAGRAM TUBUH (klik SVG) — Penandaan Lokasi Operasi
     =============================== */
    public array $validViews = [
        'priaFront', 'priaBack', 'wanitaFront', 'wanitaBack',
        'handPalmKiri', 'handPalmKanan', 'handDorsumKiri', 'handDorsumKanan',
        'footPalmKanan', 'footPalmKiri', 'footDorsumKiri', 'footDorsumKanan',
        'headFront', 'headBack', 'headProfileKiri', 'headProfileKanan',
    ];

    public function addMark(string $view, $x, $y): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        if (!in_array($view, $this->validViews, true)) {
            return;
        }
        // koordinat persen (0..100) relatif panel
        $x = max(0, min(100, (float) $x));
        $y = max(0, min(100, (float) $y));
        $this->newForm['marks'][] = ['view' => $view, 'x' => round($x, 2), 'y' => round($y, 2)];
    }

    public function undoMark(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        array_pop($this->newForm['marks']);
    }

    public function clearMarks(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['marks'] = [];
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
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-pengkajian-pre-op-ri');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->preOpList)->firstWhere('createdAt', $key);
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
        $entry = collect($this->preOpList)->firstWhere('createdAt', $key);
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
        $this->incrementVersion('modal-pengkajian-pre-op-ri');
    }

    /* ===============================
     | CETAK (inline stream PDF, by createdAt)
     =============================== */
    public function cetak(string $createdAt)
    {
        $entry = collect($this->preOpList)->firstWhere('createdAt', $createdAt);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data pengkajian tidak ditemukan.');
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

            // Path gambar TTD ketiga pihak (dari myuser_code masing-masing).
            $ttdPaths = [];
            foreach (['ttdPerawatRuangan', 'ttdPerawatKamarBedah', 'ttdDokterOperator'] as $ttdField) {
                $ttdPaths[$ttdField . 'Path'] = null;
                $code = $entry[$ttdField . 'Code'] ?? null;
                if ($code) {
                    $path = DB::table('users')->where('myuser_code', $code)->value('myuser_ttd_image');
                    if (!empty($path) && file_exists(public_path('storage/' . $path))) {
                        $ttdPaths[$ttdField . 'Path'] = public_path('storage/' . $path);
                    }
                }
            }

            $data = array_merge($pasien, $ttdPaths, [
                'dataRi' => $this->dataDaftarRi,
                'form' => $entry,
                'identitasRs' => $identitasRs,
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);

            $pdf = Pdf::loadView('pages.components.modul-dokumen.ri.pengkajian-pre-op-ri.cetak-pengkajian-pre-op-ri-print', ['data' => $data])->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak pengkajian pre operasi.');
            return response()->streamDownload(fn() => print $pdf->output(), 'pengkajian-pre-op-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HAPUS entri (final atau draft, by createdAt)
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
                    ->reject(fn($item) => ($item['createdAt'] ?? '') === $createdAt)
                    ->values()
                    ->toArray();

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->preOpList = $fresh[$this->jsonKey];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Pengkajian Pre Operasi — ' . $createdAt, 'MR');
            });

            // Jika entri yang dihapus sedang di form, kosongkan form.
            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }

            $this->incrementVersion('modal-pengkajian-pre-op-ri');
            $this->dispatch('toast', type: 'success', message: 'Pengkajian pre operasi berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | IMT — dihitung otomatis dari BB/TB (meniru hitungIMT() EMR RJ)
     =============================== */
    private function imtValue(): string
    {
        $bb = (float) ($this->newForm['bb'] ?? 0);
        $tbM = ((float) ($this->newForm['tb'] ?? 0)) / 100;

        return $bb > 0 && $tbM > 0 ? (string) round($bb / ($tbM * $tbM), 2) : '';
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['newForm.bb', 'newForm.tb'], true)) {
            $this->newForm['imt'] = $this->imtValue();
        }
    }

    // Isi field tanggal/jam dgn waktu sekarang (tombol x-now-button), format dd/mm/yyyy HH:mm:ss.
    public function setNow(string $field): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm[$field] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | PERSIAPAN (Pre Medikasi / Cairan / Obat) — daftar array ala rekonsiliasi obat.
     | Baris masuk ke newForm['persiapanObatCairan'] dan ikut tersimpan saat Simpan entri.
     =============================== */
    public string $persiapanJenis = '';
    public string $persiapanNama = '';
    public string $persiapanTglJam = '';

    public function setPersiapanTglJamNow(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->persiapanTglJam = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function addPersiapan(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }

        // validate() didahulukan supaya field yang kosong tetap ditandai merah.
        $this->validateWithToast(
            [
                'persiapanJenis' => ['required', 'string'],
                'persiapanNama' => ['required', 'string', 'max:200'],
                'persiapanTglJam' => ['required', 'string'],
            ],
            [],
            [
                'persiapanJenis' => 'Jenis',
                'persiapanNama' => 'Nama / Keterangan',
                'persiapanTglJam' => 'Tgl/Jam Pemberian',
            ],
        );

        $list = array_values($this->newForm['persiapanObatCairan'] ?? []);
        $list[] = [
            'jenis' => $this->persiapanJenis,
            'nama' => $this->persiapanNama,
            'tglJam' => $this->persiapanTglJam,
        ];
        $this->newForm['persiapanObatCairan'] = $list;

        $this->persiapanJenis = '';
        $this->persiapanNama = '';
        $this->persiapanTglJam = '';
    }

    public function removePersiapan(int $index): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $list = array_values($this->newForm['persiapanObatCairan'] ?? []);
        unset($list[$index]);
        $this->newForm['persiapanObatCairan'] = array_values($list);
    }

    /* ===============================
     | RESET
     =============================== */
    private function resetNewForm(): void
    {
        $this->newForm = [
            'diagnosaPreOp' => '',
            'rencanaOperasi' => '',
            'dokterOperator' => '',
            'tanggalOperasi' => '',
            'perjanjianPerawatOk' => '',
            'urgensi' => '',
            'sistolik' => '',
            'diastolik' => '',
            'nadi' => '',
            'rr' => '',
            'suhu' => '',
            'spo2' => '',
            'gda' => '',
            'bb' => '',
            'tb' => '',
            'imt' => '',
            'hb' => '',
            'golDarah' => '',
            'persiapanObatCairan' => [],
            'puasaMulaiJam' => '',
            'sudahDicukur' => false,
            'persiapanDarah' => false,
            'gigiPalsuDilepas' => false,
            'pengosonganKandungKemih' => false,
            'clysma' => false,
            'riwayatPenyakit' => false,
            'riwayatPenyakitKet' => '',
            'lainLain' => '',
            'adaRekamMedis' => false,
            'adaSuratIjin' => false,
            'adaLab' => false,
            'adaRadiologi' => false,
            'radiologiJenis' => '',
            'adaDiagnostik' => false,
            'diagnostikJenis' => '',
            'perluPenandaan' => 'Ya',
            'alasanTidakPerlu' => '',
            'regionAnatomi' => '',
            'sisi' => '',
            'detailLokasi' => '',
            'metodePenandaan' => 'Spidol permanen - inisial/tanda operator',
            'pasienDilibatkan' => false,
            'marks' => [],
            'ttdPerawatRuangan' => '',
            'ttdPerawatRuanganCode' => '',
            'ttdPerawatRuanganDate' => '',
            'ttdPerawatKamarBedah' => '',
            'ttdPerawatKamarBedahCode' => '',
            'ttdPerawatKamarBedahDate' => '',
            'ttdDokterOperator' => '',
            'ttdDokterOperatorCode' => '',
            'ttdDokterOperatorDate' => '',
        ];
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRi = [];
        $this->preOpList = [];
        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline) ══ --}}
    @php $preOpCount = count($preOpList ?? []); @endphp

    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-3">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Pengkajian Pre Operasi</h3>
                    @if ($preOpCount > 0)
                        <x-badge variant="success">{{ $preOpCount }} pengkajian</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-base text-muted dark:text-gray-400">
                    Persiapan pasien & serah-terima ruangan → OK (RM 49): keadaan pra bedah, persiapan pasien
                    (puasa/cukur/premedikasi), kelengkapan administrasi yang disertakan ke kamar operasi.
                    Tiap entri = 1 pengkajian; simpan draft dulu lalu kunci lewat TTD.
                </p>
            </div>

            <div class="flex shrink-0">
                <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                    wire:target="openModal" :disabled="$disabled || !$riHdrNo" class="gap-2">
                    <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        Buka Formulir
                    </span>
                    <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                        <x-loading class="w-4 h-4" /> Memuat...
                    </span>
                </x-primary-button>
            </div>
        </div>

        @if ($preOpCount > 0)
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                    <thead class="bg-surface-soft dark:bg-gray-800">
                        <tr class="text-left text-muted dark:text-gray-300">
                            <th class="px-3 py-2 border-b">Tanggal</th>
                            <th class="px-3 py-2 border-b">Rencana Operasi</th>
                            <th class="px-3 py-2 border-b">TTD (3 Pihak)</th>
                            <th class="px-3 py-2 text-center border-b">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_reverse($preOpList) as $entry)
                            @php $entryTtdCount = collect(['ttdPerawatRuangan', 'ttdPerawatKamarBedah', 'ttdDokterOperator'])->filter(fn($ttdKey) => !empty($entry[$ttdKey]))->count(); @endphp
                            <tr class="border-b border-hairline dark:border-gray-700">
                                <td class="px-3 py-2 font-medium text-ink dark:text-gray-200">{{ $entry['createdAt'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-muted dark:text-gray-400">{{ $entry['rencanaOperasi'] ? \Illuminate\Support\Str::limit($entry['rencanaOperasi'], 45) : '-' }}</td>
                                <td class="px-3 py-2 text-muted dark:text-gray-400">
                                    <x-badge :variant="$entryTtdCount === 3 ? 'success' : 'warning'">{{ $entryTtdCount }}/3 TTD</x-badge>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    @if ($this->entryIsFinal($entry))
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
    <x-modal name="rm-pengkajian-pre-op-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-pengkajian-pre-op-ri', [$riHdrNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-teal-500/10">
                                <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="font-semibold text-2xl text-ink dark:text-gray-100">Pengkajian Pre Operasi
                                </h2>
                                <p class="mt-0.5 text-base text-muted dark:text-gray-400">
                                    RM 49 — persiapan pasien & serah-terima ruangan → kamar operasi
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="brand">Rawat Inap</x-badge>
                            @if (count($preOpList) > 0)
                                <x-badge variant="info">{{ count($preOpList) }} tersimpan</x-badge>
                            @endif
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="po-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />

                    @php $formReadOnly = $isFormLocked || $viewOnly; @endphp

                    <div
                        class="p-6 space-y-6 bg-canvas border border-hairline shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        @if ($isFormLocked)
                            <div
                                class="flex items-center gap-2 px-4 py-2.5 text-base font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
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
                            <div class="flex items-center gap-2 px-4 py-2.5 text-base font-medium text-brand-green bg-brand-lime/10 border border-brand-lime/40 rounded-xl dark:text-brand-lime dark:bg-brand-lime/5">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Sedang melanjutkan entri <strong>{{ $editingKey }}</strong> — <strong>Simpan Perubahan</strong> menyimpan ke entri ini; klik <strong>Entri Baru</strong> untuk menambah pengkajian lain.
                            </div>
                        @endif

                        {{-- ── FORM ENTRI ── --}}
                        <fieldset @disabled($formReadOnly) class="space-y-6">

                            @include('pages.transaksi.ri.emr-ri.modul-dokumen.pengkajian-pre-op-ri.rm-pengkajian-pre-op-ri-data-operasi')
                            @include('pages.transaksi.ri.emr-ri.modul-dokumen.pengkajian-pre-op-ri.rm-pengkajian-pre-op-ri-keadaan-pra-bedah')
                            @include('pages.transaksi.ri.emr-ri.modul-dokumen.pengkajian-pre-op-ri.rm-pengkajian-pre-op-ri-persiapan-pasien')
                            @include('pages.transaksi.ri.emr-ri.modul-dokumen.pengkajian-pre-op-ri.rm-pengkajian-pre-op-ri-persiapan-administrasi')
                            @include('pages.transaksi.ri.emr-ri.modul-dokumen.pengkajian-pre-op-ri.rm-pengkajian-pre-op-ri-site-marking')
                            @include('pages.transaksi.ri.emr-ri.modul-dokumen.pengkajian-pre-op-ri.rm-pengkajian-pre-op-ri-ttd')
                        @include('pages.transaksi.ri.emr-ri.modul-dokumen.pengkajian-pre-op-ri.rm-pengkajian-pre-op-ri-daftar-tersimpan')

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
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
                            <span>Simpan draft dulu; entri otomatis <strong>terkunci</strong> setelah TTD <strong>Perawat Ruangan + Perawat Kamar Bedah + Dokter Operator</strong> lengkap.</span>
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
                                    title="Kosongkan form untuk menambah pengkajian lain — entri yang sudah tersimpan tidak berubah">
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
