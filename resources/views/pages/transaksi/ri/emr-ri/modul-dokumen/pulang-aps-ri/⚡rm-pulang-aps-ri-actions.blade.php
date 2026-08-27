<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/pulang-aps-ri/rm-pulang-aps-ri-actions.blade.php
//
// Surat Pernyataan Pulang Atas Permintaan Sendiri (APS) — modul dokumen RI.
// Pola: permintaan-kerohanian-ri (Draft → TTD petugas = kunci → Lihat/Cetak)
// + Buka Kunci gate terpusat (dokumen.bukaKunci) sesuai standar terbaru.

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Support\Clause\PulangApsClause;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public ?string $regNo = null;
    public bool $disabled = false;
    public array $dataDaftarRi = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-pulang-aps-ri'];

    // ── Form entri baru ──
    public array $newForm = [
        'pembuatNama' => '',
        'hubunganPasien' => 'pasien',
        'alasanPulang' => '',
        'keterangan' => '',
        'risikoDijelaskan' => '',
        'saksiNama' => '',
        'petugas' => '',
        'petugasCode' => '',
        'petugasDate' => '',
        'clauseVersion' => PulangApsClause::CURRENT,
    ];

    public string $signature = ''; // TTD pembuat pernyataan untuk entri baru
    public string $signatureSaksi = ''; // TTD saksi (opsional, pola inform-consent)

    public array $apsList = [];

    public array $hubunganPasienOptions = [
        ['value' => 'pasien', 'label' => 'Diri Sendiri (Pasien)'],
        ['value' => 'suami', 'label' => 'Suami'],
        ['value' => 'istri', 'label' => 'Istri'],
        ['value' => 'ayah', 'label' => 'Ayah'],
        ['value' => 'ibu', 'label' => 'Ibu'],
        ['value' => 'anak', 'label' => 'Anak'],
        ['value' => 'saudara', 'label' => 'Saudara'],
        ['value' => 'wali_hukum', 'label' => 'Wali Hukum'],
        ['value' => 'lainnya', 'label' => 'Lainnya'],
    ];

    // Kunci entri yang sedang diedit (signatureDate = kunci stabil, di-set saat entri pertama dibuat).
    // null = sedang membuat entri baru.
    public ?string $editingKey = null;

    // Layar aktif di modal: 'daftar' (grid entri) atau 'form' (tambah/edit/lihat).
    // Formulir sengaja tidak nongkrong bersama daftarnya: dulu ia ikut tampil terus lalu
    // dikosongkan diam-diam sesudah tersimpan, dan petugas yang mengira itu masih formulir
    // yang tadi diisi mengetik ulang — tersimpan sebagai draft baru.
    public string $layar = 'daftar';

    // true = entri terkunci sedang ditampilkan di form dalam mode read-only (lihat saja, tak bisa edit).
    public bool $viewOnly = false;

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-pulang-aps-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->apsList = $data['pulangApsRI'] ?? [];
                $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $disabled;
            }
        }
    }

    /* ===============================
     | OPEN MODAL
     =============================== */
    public function openModal(): void
    {
        if (!$this->riHdrNo || $this->disabled) {
            return;
        }

        $this->resetNewForm();
        $this->signature = '';
        $this->signatureSaksi = '';
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
        if (!isset($this->dataDaftarRi['pulangApsRI']) || !is_array($this->dataDaftarRi['pulangApsRI'])) {
            $this->dataDaftarRi['pulangApsRI'] = [];
        }
        $this->apsList = $this->dataDaftarRi['pulangApsRI'];
        $this->newForm['pembuatNama'] = $this->dataDaftarRi['regName'] ?? '';
        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;
        $this->incrementVersion('modal-pulang-aps-ri');

        $this->layar = 'daftar';

        $this->dispatch('open-modal', name: "rm-pulang-aps-ri-{$this->riHdrNo}");
    }

    /* ===============================
     | CLOSE
     =============================== */
    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-pulang-aps-ri-{$this->riHdrNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'newForm.pembuatNama' => 'required|string|max:200',
            'newForm.hubunganPasien' => 'required|string|max:50',
            'newForm.alasanPulang' => 'required|string|max:500',
            'newForm.keterangan' => 'nullable|string|max:500',
            'newForm.risikoDijelaskan' => 'nullable|string|max:1000',
            'newForm.saksiNama' => 'nullable|string|max:200',
            'signature' => 'required|string',
            'signatureSaksi' => 'nullable|string',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newForm.pembuatNama' => 'Nama pembuat pernyataan',
            'newForm.hubunganPasien' => 'Hubungan dengan pasien',
            'newForm.alasanPulang' => 'Alasan pulang',
            'newForm.keterangan' => 'Keterangan tambahan',
            'newForm.risikoDijelaskan' => 'Risiko/akibat yang dijelaskan',
            'newForm.saksiNama' => 'Nama saksi',
            'signature' => 'Tanda tangan pembuat pernyataan',
            'signatureSaksi' => 'Tanda tangan saksi',
        ];
    }

    /* ===============================
     | SIGNATURE (pembuat pernyataan)
     =============================== */
    public function setSignature(string $dataUrl): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signature = $dataUrl;
        $this->incrementVersion('modal-pulang-aps-ri');
    }

    public function clearSignature(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signature = '';
        $this->incrementVersion('modal-pulang-aps-ri');
    }

    public function setSignatureSaksi(string $dataUrl): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signatureSaksi = $dataUrl;
        $this->incrementVersion('modal-pulang-aps-ri');
    }

    public function clearSignatureSaksi(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signatureSaksi = '';
        $this->incrementVersion('modal-pulang-aps-ri');
    }

    /* ===============================
     | TTD PETUGAS RS = FINALIZE
     | Petugas TTD di akhir → validasi lengkap + kunci entri.
     =============================== */
    public function setPetugasRS(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (empty($this->signature)) {
            $this->dispatch('toast', type: 'error', message: 'TTD pembuat pernyataan wajib sebelum TTD petugas.');
            return;
        }

        $this->validateWithToast();

        // Stempel TTD petugas RS = user login.
        $this->newForm['petugas'] = auth()->user()->myuser_name ?? '';
        $this->newForm['petugasCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['petugasDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, true, 'Kunci (TTD Petugas)');
            $this->resetNewForm();
            $this->newForm['pembuatNama'] = $this->dataDaftarRi['regName'] ?? '';
            $this->signature = '';
            $this->signatureSaksi = '';
            $this->editingKey = null;
            $this->viewOnly = false;
            $this->incrementVersion('modal-pulang-aps-ri');
            $this->dispatch('toast', type: 'success', message: 'Surat pernyataan pulang APS ditandatangani petugas dan terkunci.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HELPER — status & bentuk entri
     =============================== */
    public function entryIsFinal(array $e): bool
    {
        return array_key_exists('finalized', $e) ? (bool) $e['finalized'] : !empty($e['signature']);
    }

    // Susun array entri dari state form. $key = signatureDate (kunci stabil); $finalized = status kunci.
    private function buildEntry(string $key, bool $finalized): array
    {
        return [
            'pembuatNama' => $this->newForm['pembuatNama'] ?? '',
            'hubunganPasien' => $this->newForm['hubunganPasien'] ?? 'pasien',
            'alasanPulang' => $this->newForm['alasanPulang'] ?? '',
            'keterangan' => $this->newForm['keterangan'] ?? '',
            'risikoDijelaskan' => $this->newForm['risikoDijelaskan'] ?? '',
            'saksiNama' => $this->newForm['saksiNama'] ?? '',
            'signatureSaksi' => $this->signatureSaksi,
            'signature' => $this->signature,
            'signatureDate' => $key,
            'petugas' => $this->newForm['petugas'] ?? '',
            'petugasCode' => $this->newForm['petugasCode'] ?? '',
            'petugasDate' => $this->newForm['petugasDate'] ?? '',
            'clauseVersion' => $this->newForm['clauseVersion'] ?? PulangApsClause::CURRENT,
            'finalized' => $finalized,
        ];
    }

    // Simpan entri (add/update by $key) dengan status $finalized. Dipakai draft & kunci.
    private function persistEntry(string $key, bool $finalized, string $logVerb): void
    {
        $entry = $this->buildEntry($key, $finalized);

        DB::transaction(function () use ($entry, $key, $logVerb) {
            $this->lockRIRow($this->riHdrNo);

            $data = $this->findDataRI($this->riHdrNo);
            if (empty($data)) {
                throw new \RuntimeException('Data RI tidak ditemukan, simpan dibatalkan.');
            }
            if (!isset($data['pulangApsRI']) || !is_array($data['pulangApsRI'])) {
                $data['pulangApsRI'] = [];
            }

            $list = $data['pulangApsRI'];
            $idx = collect($list)->search(fn($it) => ($it['signatureDate'] ?? '') === $key);
            if ($idx === false) {
                $list[] = $entry;
            } else {
                if ($this->entryIsFinal($list[$idx])) {
                    throw new \RuntimeException('Entri sudah terkunci, tidak dapat diubah.');
                }
                $list[$idx] = $entry;
            }
            $data['pulangApsRI'] = array_values($list);

            $this->updateJsonRI((int) $this->riHdrNo, $data);
            $this->dataDaftarRi = $data;
            $this->apsList = $data['pulangApsRI'];

            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Surat Pernyataan Pulang APS RI — oleh "' . ($entry['pembuatNama'] ?: '-') . '" (' . $key . ')', 'MR');
        });
    }

    /* ===============================
     | SIMPAN DRAFT (nyicil, tanpa validasi lengkap)
     =============================== */
    public function saveDraft(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }
        if (trim($this->newForm['pembuatNama'] ?? '') === '') {
            $this->dispatch('toast', type: 'error', message: 'Nama pembuat pernyataan wajib diisi untuk menyimpan draft.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-pulang-aps-ri');
            $this->dispatch('toast', type: 'success', message: 'Draft tersimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    /* ===============================
     | EDIT / LIHAT / BATAL entri
     =============================== */
    // Muat 1 entri ke form atas (dipakai edit draft & lihat entri terkunci).
    private function hydrateFormFromEntry(array $entry, string $key): void
    {
        $this->newForm = [
            'pembuatNama' => $entry['pembuatNama'] ?? '',
            'hubunganPasien' => $entry['hubunganPasien'] ?? 'pasien',
            'alasanPulang' => $entry['alasanPulang'] ?? '',
            'keterangan' => $entry['keterangan'] ?? '',
            'risikoDijelaskan' => $entry['risikoDijelaskan'] ?? '',
            'saksiNama' => $entry['saksiNama'] ?? '',
            'petugas' => $entry['petugas'] ?? '',
            'petugasCode' => $entry['petugasCode'] ?? '',
            'petugasDate' => $entry['petugasDate'] ?? '',
            'clauseVersion' => $entry['clauseVersion'] ?? PulangApsClause::CURRENT,
        ];
        $this->signature = $entry['signature'] ?? '';
        $this->signatureSaksi = $entry['signatureSaksi'] ?? '';
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-pulang-aps-ri');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->apsList)->firstWhere('signatureDate', $key);
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
        $entry = collect($this->apsList)->firstWhere('signatureDate', $key);
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
        $this->newForm['pembuatNama'] = $this->dataDaftarRi['regName'] ?? '';
        $this->signature = '';
        $this->signatureSaksi = '';
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();
        $this->incrementVersion('modal-pulang-aps-ri');
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
     | BUKA KUNCI (gate terpusat dokumen.bukaKunci)
     | Cabut finalized + TTD petugas; TTD pembuat pernyataan TETAP.
     =============================== */
    public function bukaKunci(string $key): void
    {
        if (!auth()->user()?->can('dokumen.bukaKunci')) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Admin / Manager yang dapat membuka kunci.');
            return;
        }
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang — form read-only.');
            return;
        }

        try {
            DB::transaction(function () use ($key) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $list = $fresh['pulangApsRI'] ?? [];
                $index = collect($list)->search(fn($it) => ($it['signatureDate'] ?? '') === $key);
                if ($index === false) {
                    throw new \RuntimeException('Entri tidak ditemukan.');
                }

                // Cabut kunci + TTD petugas; TTD pembuat pernyataan tetap.
                $list[$index]['finalized'] = false;
                $list[$index]['petugas'] = '';
                $list[$index]['petugasCode'] = '';
                $list[$index]['petugasDate'] = '';

                $fresh['pulangApsRI'] = array_values($list);
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->apsList = $fresh['pulangApsRI'];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Buka kunci Surat Pernyataan Pulang APS — entri ' . $key . ' (oleh ' . (auth()->user()->myuser_name ?? auth()->user()->name ?? '-') . ')', 'MR');
            });

            $this->incrementVersion('modal-pulang-aps-ri');
            $this->dispatch('toast', type: 'success', message: 'Kunci dibuka — entri kembali draft & TTD petugas dicabut. Silakan koreksi lalu kunci ulang.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuka kunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK (stream PDF)
     =============================== */
    public function cetak(string $signatureDate)
    {
        $entry = collect($this->apsList)->firstWhere('signatureDate', $signatureDate);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data formulir tidak ditemukan.');
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

            // TTD Petugas RS (myuser_code → myuser_ttd_image)
            $ttdPetugasPath = null;
            $petugasCode = $entry['petugasCode'] ?? null;
            if ($petugasCode) {
                $ttdPath = DB::table('users')->where('myuser_code', $petugasCode)->value('myuser_ttd_image');
                if (!empty($ttdPath) && file_exists(public_path('storage/' . $ttdPath))) {
                    $ttdPetugasPath = public_path('storage/' . $ttdPath);
                }
            }

            $data = array_merge($pasien, [
                'dataRi' => $this->dataDaftarRi,
                'form' => $entry,
                'identitasRs' => $identitasRs,
                'ttdPetugasPath' => $ttdPetugasPath,
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);

            $pdf = Pdf::loadView('pages.components.modul-dokumen.ri.pulang-aps-ri.cetak-pulang-aps-ri-print', ['data' => $data])->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak surat pernyataan pulang APS.');
            return response()->streamDownload(fn() => print $pdf->output(), 'pulang-aps-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HAPUS (gate terpusat dokumen.hapus)
     =============================== */
    public function hapus(string $signatureDate): void
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
            DB::transaction(function () use ($signatureDate) {
                $this->lockRIRow($this->riHdrNo);

                $data = $this->findDataRI($this->riHdrNo);
                if (empty($data) || !isset($data['pulangApsRI'])) {
                    throw new \RuntimeException('Data formulir tidak ditemukan.');
                }

                $data['pulangApsRI'] = collect($data['pulangApsRI'])
                    ->reject(fn($item) => ($item['signatureDate'] ?? '') === $signatureDate)
                    ->values()
                    ->toArray();

                $this->updateJsonRI((int) $this->riHdrNo, $data);
                $this->dataDaftarRi = $data;
                $this->apsList = $data['pulangApsRI'];
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Surat Pernyataan Pulang APS — TTD ' . $signatureDate, 'MR');
            });

            $this->incrementVersion('modal-pulang-aps-ri');
            $this->dispatch('toast', type: 'success', message: 'Surat pernyataan pulang APS berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | RESET
     =============================== */
    private function resetNewForm(): void
    {
        $this->newForm = [
            'pembuatNama' => '',
            'hubunganPasien' => 'pasien',
            'alasanPulang' => '',
            'keterangan' => '',
            'risikoDijelaskan' => '',
            'saksiNama' => '',
            'petugas' => '',
            'petugasCode' => '',
            'petugasDate' => '',
            'clauseVersion' => PulangApsClause::CURRENT,
        ];
        $this->layar = 'daftar';   // mengosongkan formulir = kembali ke daftar
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRi = [];
        $this->apsList = [];
        $this->resetNewForm();
        $this->signature = '';
        $this->signatureSaksi = '';
        $this->editingKey = null;
        $this->viewOnly = false;
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline) ══ --}}
    @php $poCount = count($apsList ?? []); @endphp

    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-3">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                        Pulang Atas Permintaan Sendiri (APS)
                    </h3>
                    @if ($poCount > 0)
                        <x-badge variant="success">{{ $poCount }} surat</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
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

            <p class="text-base text-muted dark:text-gray-400">
                Surat pernyataan pasien/keluarga yang meminta pulang sebelum perawatan dinyatakan selesai, setelah mendapat
                penjelasan dokter/petugas. Dapat lebih dari satu surat.
            </p>

            @if ($poCount > 0)
                <div class="overflow-x-auto">
                    <h4 class="mb-2 text-sm font-semibold text-body dark:text-gray-300">Daftar Surat Tersimpan</h4>
                    <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                        <thead class="bg-surface-soft dark:bg-gray-800">
                            <tr class="text-left text-muted dark:text-gray-300">
                                <th class="px-3 py-2 border-b">Pembuat Pernyataan</th>
                                <th class="px-3 py-2 border-b">Alasan Pulang</th>
                                <th class="px-3 py-2 border-b">Tanggal</th>
                                <th class="px-3 py-2 border-b">Petugas RS</th>
                                <th class="px-3 py-2 border-b text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (array_reverse($apsList) as $po)
                                <tr class="border-b border-hairline dark:border-gray-700">
                                    <td class="px-3 py-2 font-medium text-ink dark:text-gray-200">
                                        {{ Str::limit($po['pembuatNama'] ?? '-', 50) ?: '-' }}
                                    </td>
                                    <td class="px-3 py-2 text-muted dark:text-gray-400">
                                        {{ Str::limit($po['alasanPulang'] ?? '-', 40) ?: '-' }}
                                    </td>
                                    <td class="px-3 py-2 text-muted dark:text-gray-400">{{ $po['signatureDate'] ?? '-' }}</td>
                                    <td class="px-3 py-2 text-muted dark:text-gray-400">
                                        @if (!empty($po['petugas'])){{ $po['petugas'] }}@else<x-badge variant="danger">Belum TTD</x-badge>@endif
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        @if ($this->entryIsFinal($po))
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
    </div>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="rm-pulang-aps-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-pulang-aps-ri', [$riHdrNo ?? 'new']) }}">
            {{-- BARIS TOMBOL TUTUP — barisnya sendiri, tidak menimpa display pasien --}}
            <div class="flex justify-end px-4 pt-3">
                <x-icon-button color="gray" type="button" wire:click="closeModal">
                    <span class="sr-only">Close</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20"
                        fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </x-icon-button>
            </div>

            {{-- DISPLAY PASIEN — paling atas, mengikuti pola EMR --}}
            <div class="px-4 pt-2">
                <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                    wire:key="po-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />
            </div>

            {{-- JUDUL SATU BARIS — judul dipotong, subjudul & badge ke kanan, tak menumpuk ke bawah --}}
            <div class="relative px-6 py-2.5 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-center gap-3 min-w-0">
                    <div class="flex items-center flex-1 gap-3 min-w-0">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="flex items-center justify-center w-7 h-7 rounded-lg shrink-0 bg-red-500/10">
                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                            </div>

                            <div class="flex items-baseline gap-2 min-w-0">
                                <h2 class="truncate shrink-0 font-semibold text-sm text-ink dark:text-gray-100">
                                    Surat Pernyataan Pulang Atas Permintaan Sendiri
                                </h2>
                                <p class="truncate text-xs text-muted dark:text-gray-400">
                                    Formulir diisi & dijelaskan kepada pasien/keluarga — tampilan dapat diputar ke arah
                                    pasien
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 ml-auto shrink-0">
                            <x-badge variant="brand">Rawat Inap</x-badge>
                            @if (count($apsList) > 0)
                                <x-badge variant="info">{{ count($apsList) }} tersimpan</x-badge>
                            @endif
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                        </div>
                    </div>

                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    {{-- Display Pasien --}}

                    <div
                        class="p-6 space-y-6 bg-canvas border border-hairline shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        @php $formReadOnly = $isFormLocked || $viewOnly; @endphp

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
                            <div
                                class="flex items-center gap-2 px-4 py-2.5 text-base font-medium text-sky-700 bg-sky-50 border border-sky-200 rounded-xl dark:bg-sky-900/20 dark:border-sky-600 dark:text-sky-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Menampilkan entri terkunci <strong>{{ $editingKey }}</strong> (hanya lihat) — klik <strong>Selesai Melihat</strong> untuk kembali ke form entri baru.
                            </div>
                        @elseif ($editingKey && !$isFormLocked)
                            <div
                                class="flex items-center gap-2 px-4 py-2.5 text-base font-medium text-brand-green bg-brand-lime/10 border border-brand-lime/40 rounded-xl dark:text-brand-lime dark:bg-brand-lime/5">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Sedang melanjutkan entri <strong>{{ $editingKey }}</strong> — <strong>Simpan Perubahan</strong> menyimpan ke entri ini; klik <strong>Entri Baru</strong> untuk menambah surat lain.
                            </div>
                        @endif

                        {{-- ══ ALASAN PULANG ══ --}}
                        @if ($this->diForm())
                        <section class="space-y-3">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                                Alasan Pulang
                            </h3>

                            {{-- Tiga isian sebaris. Semuanya textarea rows=3 supaya tinggi kolomnya
                                 rata; di layar sempit tetap menumpuk sendiri. --}}
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <x-input-label value="Alasan Pulang *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.alasanPulang" :error="$errors->has('newForm.alasanPulang')" rows="3"
                                        placeholder="cth: ingin dirawat di rumah / kendala biaya / permintaan keluarga..." :disabled="$formReadOnly"
                                        class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.alasanPulang')" class="mt-1" />
                                </div>

                                <div>
                                    <x-input-label value="Keterangan Tambahan (opsional)" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.keterangan" :error="$errors->has('newForm.keterangan')" rows="3"
                                        placeholder="Catatan lain yang perlu direkam (mis. rencana kontrol, kondisi saat pulang)..." :disabled="$formReadOnly"
                                        class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.keterangan')" class="mt-1" />
                                </div>

                                <div>
                                    <x-input-label value="Risiko / Akibat yang Dijelaskan (opsional)" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.risikoDijelaskan" :error="$errors->has('newForm.risikoDijelaskan')" rows="3"
                                        placeholder="cth: infeksi dapat memburuk/meluas, penyembuhan lebih lama, nyeri tidak terkontrol..."
                                        :disabled="$formReadOnly" class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.risikoDijelaskan')" class="mt-1" />
                                    <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                        Ikut tercetak di surat sebagai bukti penjelasan.
                                    </p>
                                </div>
                            </div>
                        </section>

                        {{-- ══ PERNYATAAN (teks klausul ber-versi) ══ --}}
                        @php $clause = App\Support\Clause\PulangApsClause::get($newForm['clauseVersion'] ?? null); @endphp
                        <div
                            class="px-4 py-3 space-y-2 text-sm border rounded-2xl bg-red-50 border-red-200 text-red-900 dark:bg-red-900/20 dark:border-red-800 dark:text-red-200">
                            <p class="font-semibold">Isi pernyataan yang ditandatangani:</p>
                            {{-- Urutan butir WAJIB sama dengan berkas cetak — layar adalah pratinjau
                                 surat, bukan susunan lain. Nomor di sini artefak format (<ol>), teksnya
                                 sendiri tetap satu sumber dari PulangApsClause. --}}
                            <ol class="pl-5 space-y-2 list-decimal">
                                <li>{{ $clause['statementPre'] }} <em>(identitas pasien tercetak otomatis)</em>.</li>
                                <li>{{ $clause['penjelasanRisiko'] }}</li>
                                <li>{{ $clause['tanggungJawab'] }}</li>
                                <li>{{ $clause['kontrolUlang'] }}</li>
                            </ol>
                        </div>

                        {{-- ══ TANDA TANGAN ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                                Tanda Tangan
                            </h3>

                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                {{-- Pembuat pernyataan --}}
                                <div class="flex flex-col">
                                    <div
                                        class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                                        Yang Membuat Pernyataan
                                    </div>
                                    <x-input-error :messages="$errors->get('signature')" class="mb-2" />
                                    @if (!empty($signature))
                                        <x-signature.signature-result :signature="$signature" :date="''"
                                            :disabled="$formReadOnly" wireMethod="clearSignature" />
                                    @elseif (!$formReadOnly)
                                        <x-signature.signature-pad wireMethod="setSignature" />
                                    @else
                                        <p class="py-8 text-base italic text-center text-muted-soft">Belum
                                            ditandatangani.</p>
                                    @endif

                                    {{-- Data penanda tangan DI BAWAH pad — pola inform-consent --}}
                                    <div class="mt-3">
                                        <x-input-label value="Nama Pembuat Pernyataan *" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.pembuatNama" :error="$errors->has('newForm.pembuatNama')"
                                            placeholder="Nama pasien / keluarga yang membuat pernyataan..."
                                            :disabled="$formReadOnly" class="w-full" />
                                        <x-input-error :messages="$errors->get('newForm.pembuatNama')" class="mt-1" />
                                    </div>

                                    <div class="mt-2">
                                        <x-input-label value="Hubungan dengan Pasien *" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.hubunganPasien" :error="$errors->has('newForm.hubunganPasien')"
                                            :disabled="$formReadOnly" class="w-full">
                                            @foreach ($hubunganPasienOptions as $opt)
                                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                            @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('newForm.hubunganPasien')" class="mt-1" />
                                    </div>
                                </div>

                                {{-- Saksi (opsional — pola inform-consent) --}}
                                <div class="flex flex-col">
                                    <div
                                        class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                                        Saksi
                                    </div>
                                    <x-input-error :messages="$errors->get('signatureSaksi')" class="mb-2" />
                                    @if (!empty($signatureSaksi))
                                        <x-signature.signature-result :signature="$signatureSaksi" :date="''"
                                            :disabled="$formReadOnly" wireMethod="clearSignatureSaksi" />
                                    @elseif (!$formReadOnly)
                                        <x-signature.signature-pad wireMethod="setSignatureSaksi" />
                                    @else
                                        <p class="py-8 text-base italic text-center text-muted-soft">Belum
                                            ditandatangani.</p>
                                    @endif

                                    <div class="mt-3">
                                        <x-input-label value="Nama Saksi" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.saksiNama" :error="$errors->has('newForm.saksiNama')"
                                            placeholder="Nama saksi..." :disabled="$formReadOnly" class="w-full" />
                                        <x-input-error :messages="$errors->get('newForm.saksiNama')" class="mt-1" />
                                    </div>
                                </div>

                                {{-- Petugas RS --}}
                                <div class="flex flex-col">
                                    <div
                                        class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                                        Petugas RS
                                    </div>
                                    @if (empty($newForm['petugas']))
                                        @if (!$formReadOnly)
                                            <div
                                                class="flex flex-col items-center justify-center flex-1 gap-2 p-6 border-2 border-gray-300 border-dashed rounded-xl dark:border-gray-700">
                                                <x-primary-button wire:click.prevent="setPetugasRS"
                                                    wire:loading.attr="disabled" wire:target="setPetugasRS"
                                                    class="gap-2">
                                                    <span wire:loading.remove wire:target="setPetugasRS"
                                                        class="flex items-center gap-1.5">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z" />
                                                        </svg>
                                                        TTD Petugas &amp; Kunci
                                                    </span>
                                                    <span wire:loading wire:target="setPetugasRS">
                                                        <x-loading class="w-4 h-4" /> Mengunci...
                                                    </span>
                                                </x-primary-button>
                                                <p class="text-xs text-center text-muted">Menandatangani = validasi &amp; mengunci surat pernyataan ini.</p>
                                            </div>
                                        @else
                                            <p class="py-8 text-base italic text-center text-muted-soft">Belum
                                                ditandatangani.</p>
                                        @endif
                                    @else
                                        <div
                                            class="flex flex-col items-center justify-center flex-1 p-4 border border-hairline bg-surface-soft rounded-xl dark:bg-gray-800 dark:border-gray-700">
                                            <div class="font-semibold text-center text-ink dark:text-gray-200">
                                                {{ $newForm['petugas'] }}
                                            </div>
                                            @if (!empty($newForm['petugasCode']))
                                                <div class="text-sm text-muted mt-0.5">
                                                    Kode: {{ $newForm['petugasCode'] }}
                                                </div>
                                            @endif
                                            <div class="mt-1 text-sm text-muted">
                                                {{ $newForm['petugasDate'] ?? '-' }}
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </section>

                        {{-- ══ DAFTAR TERSIMPAN (expandable) ══ --}}
                        @endif
                        @unless ($this->diForm())
                            <div class="mt-6 overflow-x-auto">
                                <div class="flex items-center justify-between gap-2 pb-2 border-b border-hairline-soft dark:border-gray-800 mb-3">
                                    <h3 class="text-base font-semibold text-body dark:text-gray-300">
                                        Daftar Surat Tersimpan
                                    </h3>
                                    <span class="text-xs italic text-muted-soft">Klik baris untuk lihat detail lengkap</span>
                                </div>
                                <table class="min-w-full text-base border border-hairline rounded-lg dark:border-gray-700">
                                    <thead class="bg-surface-soft dark:bg-gray-800">
                                        <tr class="text-left text-sm font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                                            <th class="w-8 px-2 py-3 border-b"></th>
                                            <th class="px-4 py-3 border-b">Pembuat Pernyataan</th>
                                            <th class="px-4 py-3 border-b">Alasan Pulang</th>
                                            <th class="px-4 py-3 border-b">Tanggal Dibuat</th>
                                            <th class="px-4 py-3 border-b">Petugas RS</th>
                                            <th class="px-4 py-3 border-b text-center">Status</th>
                                            <th class="px-4 py-3 border-b text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    @forelse (array_reverse($apsList) as $entry)
                                        @php
                                            // Normalisasi entri agar semua key ada (cegah "Undefined array key")
                                            $entry = array_replace([
                                                'pembuatNama' => '',
                                                'hubunganPasien' => '', 'alasanPulang' => '', 'keterangan' => '', 'risikoDijelaskan' => '',
                                                'saksiNama' => '', 'signatureSaksi' => '',
                                                'petugas' => '', 'petugasCode' => '', 'petugasDate' => '',
                                                'signature' => '', 'signatureDate' => '',
                                            ], $entry);
                                            $isFinal = $this->entryIsFinal($entry);
                                            $rowKey = $entry['signatureDate'] ?? '';
                                            $hubLabel = collect($hubunganPasienOptions)->firstWhere('value', $entry['hubunganPasien'] ?? '')['label'] ?? ($entry['hubunganPasien'] ?? '');
                                        @endphp
                                        {{-- Semua baris mulai TERTUTUP: daftar dipakai untuk MEMILIH entri, bukan
                                             membacanya. Baris teratas yang terbuka sendiri bikin grid langsung panjang. --}}
                                        <tbody x-data="{ open: false }" class="border-b border-hairline dark:border-gray-700">
                                            <tr @click="open = !open"
                                                class="cursor-pointer hover:bg-surface-soft dark:hover:bg-gray-800 {{ $editingKey && $editingKey === $rowKey ? 'bg-brand-lime/10 dark:bg-brand-lime/5' : '' }}">
                                                <td class="px-2 py-3 text-center align-middle">
                                                    <svg class="w-4 h-4 mx-auto text-muted transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </td>
                                                <td class="px-4 py-3 align-middle font-semibold text-ink dark:text-gray-100">
                                                    {{ Str::limit($entry['pembuatNama'] ?: '(tanpa nama)', 50) }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    {{ Str::limit($entry['alasanPulang'] ?: '-', 40) }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-sm tabular-nums text-muted dark:text-gray-400">
                                                    {{ $rowKey ?: '-' }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    @if (!empty($entry['petugas']))
                                                        <span class="font-medium text-ink dark:text-gray-200">{{ $entry['petugas'] }}</span>
                                                    @else
                                                        <x-badge variant="danger">Belum TTD</x-badge>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 align-middle text-center">
                                                    @if ($isFinal)
                                                        <x-badge variant="info">Terkunci</x-badge>
                                                    @else
                                                        <x-badge variant="warning">Draft</x-badge>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 align-middle text-center whitespace-nowrap" @click.stop>
                                                    <div class="flex flex-wrap items-center justify-center gap-1.5">
                                                        {{-- Baris atas: aksi non-destruktif --}}
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
                                                                <x-secondary-button wire:click="cetak('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="cetak('{{ $rowKey }}')" class="gap-1.5">
                                                                    <span wire:loading.remove wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5">
                                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                                        </svg>
                                                                        Cetak
                                                                    </span>
                                                                    <span wire:loading wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5"><x-loading class="w-5 h-5" /> Mencetak...</span>
                                                                </x-secondary-button>
                                                            @endif
                                                        </div>

                                                        {{-- Baris bawah: aksi terkunci/destruktif (Buka Kunci + Hapus) --}}
                                                        @if (!$isFormLocked)
                                                            <div class="flex items-center justify-center gap-2">
                                                                @if ($isFinal && $rowKey)
                                                                    @can('dokumen.bukaKunci')
                                                                        <x-confirm-button action="bukaKunci('{{ $rowKey }}')"
                                                                            title="Buka Kunci Surat Pernyataan Pulang APS"
                                                                            message="TTD petugas akan dicabut & entri kembali menjadi draft untuk dikoreksi. TTD pembuat pernyataan tetap. Lanjutkan?"
                                                                            confirmText="Ya, Buka Kunci" class="gap-1.5">
                                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                                    d="M8 11V7a4 4 0 118 0m-8 4h10a2 2 0 012 2v5a2 2 0 01-2 2H8a2 2 0 01-2-2v-5a2 2 0 012-2z" />
                                                                            </svg>
                                                                            Buka Kunci
                                                                        </x-confirm-button>
                                                                    @endcan
                                                                @endif
                                                                @if ($rowKey)
                                                                    @can('dokumen.hapus')
                                                                        <x-outline-button type="button" wire:click.prevent="hapus('{{ $rowKey }}')" wire:confirm="Yakin hapus surat pernyataan ini?"
                                                                            wire:loading.attr="disabled"
                                                                            class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                                            title="Hapus">
                                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                            </svg>
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
                                                <td colspan="7" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                                    <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Pembuat Pernyataan</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['pembuatNama'] ?: '-' }}@if ($hubLabel) <span class="text-muted">({{ $hubLabel }})</span>@endif</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Alasan Pulang</dt>
                                                            <dd class="mt-0.5 font-semibold text-red-700 dark:text-red-400">{{ $entry['alasanPulang'] ?: '-' }}</dd>
                                                        </div>
                                                        @if ($entry['keterangan'] !== '')
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Keterangan Tambahan</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['keterangan'] }}</dd>
                                                            </div>
                                                        @endif
                                                        @if ($entry['risikoDijelaskan'] !== '')
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Risiko / Akibat yang Dijelaskan</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['risikoDijelaskan'] }}</dd>
                                                            </div>
                                                        @endif
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">TTD Pembuat Pernyataan</dt>
                                                            <dd class="mt-0.5">
                                                                @if (!empty($entry['signature']))
                                                                    <span class="text-success-deep dark:text-green-300">Sudah TTD</span>
                                                                    <span class="text-sm text-muted-soft">— {{ $entry['signatureDate'] ?? '-' }}</span>
                                                                @else
                                                                    <x-badge variant="danger">Belum TTD</x-badge>
                                                                @endif
                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Saksi</dt>
                                                            <dd class="mt-0.5">
                                                                @if (!empty($entry['saksiNama']) || !empty($entry['signatureSaksi']))
                                                                    <span class="text-ink dark:text-gray-200">{{ $entry['saksiNama'] ?: '-' }}</span>
                                                                    @if (!empty($entry['signatureSaksi']))
                                                                        <span class="text-sm text-success-deep dark:text-green-300">— Sudah TTD</span>
                                                                    @endif
                                                                @else
                                                                    <span class="text-muted-soft">-</span>
                                                                @endif
                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Petugas RS</dt>
                                                            <dd class="mt-0.5">
                                                                @if (!empty($entry['petugas']))
                                                                    <span class="text-ink dark:text-gray-200">{{ $entry['petugas'] }}</span>
                                                                    <span class="text-sm text-muted-soft">— {{ $entry['petugasDate'] ?? '-' }}</span>
                                                                @else
                                                                    <x-badge variant="danger">Belum TTD</x-badge>
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
                                                <td colspan="7" class="px-6 py-12">
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
                        @endunless

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                @if ($this->diForm())
                <div class="flex flex-wrap items-center justify-between gap-3">
                    @if ($viewOnly)
                        <p class="flex items-center gap-1.5 text-sm text-sky-600 dark:text-sky-400">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span>Mode lihat — entri terkunci, tidak dapat diubah.</span>
                        </p>
                    @elseif ($riHdrNo && !$isFormLocked)
                        <p class="flex items-center gap-1.5 text-sm text-muted dark:text-gray-400">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Simpan draft dulu, lalu <strong>kunci</strong> lewat tombol <strong>TTD Petugas &amp; Kunci</strong> di kolom Petugas RS.</span>
                        </p>
                    @else
                        <span></span>
                    @endif

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <x-secondary-button wire:click="kembaliKeDaftar">Kembali ke Daftar</x-secondary-button>

                        @if ($viewOnly)
                            <x-primary-button wire:click.prevent="cancelEdit" wire:target="cancelEdit"
                                wire:loading.attr="disabled" class="gap-1.5 min-w-[160px] justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Selesai Melihat
                            </x-primary-button>
                        @elseif ($riHdrNo && !$isFormLocked)
                            @if ($editingKey)
                                <x-outline-button wire:click.prevent="cancelEdit" wire:target="cancelEdit"
                                    wire:loading.attr="disabled" class="gap-1.5"
                                    title="Kosongkan form untuk menambah surat lain — entri yang sudah tersimpan tidak berubah">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4" />
                                    </svg>
                                    Entri Baru
                                </x-outline-button>
                            @endif
                            <x-primary-button wire:click.prevent="saveDraft" wire:loading.attr="disabled"
                                wire:target="saveDraft" class="gap-2 min-w-[160px] justify-center">
                                <span wire:loading.remove wire:target="saveDraft" class="flex items-center gap-1.5">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 21v-8H7v8M7 3v5h8M5 3h11l4 4v12a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
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
