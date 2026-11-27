<?php
// resources/views/pages/transaksi/ugd/emr-ugd/cppt/rm-cppt-ugd-actions.blade.php
// Format menyalin CPPT Rawat Inap (cppt-ri/rm-cppt-ri-actions), pola port sama dengan SBAR UGD.
// Perbedaan yang disengaja:
//  - Sumber data: datadaftarugd_json (rjNo) via EmrUGDTrait, node root `cppt`.
//  - DPJP = dokter kunjungan UGD (drId/drDesc), bukan leveling Pengkajian Awal RI.
//  - Simpan pakai tombol milik komponen (Tambah/Perbarui CPPT) — footer Simpan EMR UGD
//    hanya menyimpan SOAP, tidak tab-aware seperti EMR RI.
//  - TANPA tab MPP (Case Manager hanya di RI), TANPA sinkron Asuhan Keperawatan (askep UGD
//    memang tidak dilempar ke CPPT), TANPA sinkron Plan dari E-Resep RI.

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;

    /**
     * IRISAN dokumen: hanya cabang `cppt`.
     *
     * Dokumen `datadaftarugd_json` utuh sengaja TIDAK disimpan di properti publik — properti
     * publik ikut snapshot Livewire dan dikirim bolak-balik tiap request. Untuk MENYIMPAN,
     * dokumen utuh tetap dibaca ulang dari DB di dalam transaksi ber-lock.
     */
    public array $daftarCppt = [];

    /** Skalar identitas yang ikut dipakai komponen ini. */
    public ?string $regNoPasien = null;
    public string $dpjpId = '';
    public string $dpjpDesc = '';
    public string $diagnosaAwal = '';

    // cpptId entri yang sedang diedit (null = mode tambah baru)
    public ?string $editingCpptId = null;

    public string $activeProfession = 'Semua';
    public array $professionTabs = ['Semua', 'Dokter', 'Perawat', 'Apoteker', 'Gizi', 'Penunjang'];

    public array $formEntryCPPT = [
        'tglCPPT' => '',
        'petugasCPPT' => '',
        'petugasCPPTCode' => '',
        'profession' => '',
        'soap' => ['subjective' => '', 'objective' => '', 'assessment' => '', 'plan' => ''],
        'instruction' => '',
        'review' => '',
    ];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-cppt-ugd'];

    /** Dokumen dibaca sebagai variabel LOKAL; hanya irisan di atas yang disimpan. */
    private function muatDariDokumen(array $data): void
    {
        $this->daftarCppt = $data['cppt'] ?? [];
        $this->regNoPasien = $data['regNo'] ?? null;
        $this->dpjpId = (string) ($data['drId'] ?? '');
        $this->dpjpDesc = (string) ($data['drDesc'] ?? '');
        $this->diagnosaAwal = $this->diagnosaUgd($data);
    }

    /* ── Diagnosis kunjungan UGD: freetext dokter, fallback keterangan ICD-10 (pola Resume) ── */
    private function diagnosaUgd(array $data): string
    {
        $teks = trim((string) ($data['diagnosisFreeText'] ?? ''));
        if ($teks !== '') {
            return $teks;
        }

        return collect($data['diagnosis'] ?? [])
            ->pluck('diagDesc')
            ->map(fn($desc) => trim((string) $desc))
            ->filter()
            ->implode("\n");
    }

    /**
     * rjNo datang lewat PROP dari induk EMR UGD (seksi lahir di dalam @if($rjNo)),
     * bukan lewat event open-rm-*: satu kali baca CLOB, tidak ada race urutan event.
     * Handler #[On] tetap dipertahankan untuk pemanggil dari luar modal.
     */
    public function mount(?int $rjNo = null): void
    {
        $this->registerAreas(['modal-cppt-ugd']);

        if (filled($rjNo)) {
            $this->open($rjNo);
        }
    }

    #[On('open-rm-cppt-ugd')]
    public function open(int $rjNo): void
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
        $this->isiAssessmentDariDiagnosa();

        $role = $this->profesiSaya();
        $this->activeProfession = in_array($role, ['Dokter', 'Perawat', 'Apoteker', 'Gizi'], true) ? $role : 'Semua';

        $this->isFormLocked = $this->checkEmrUGDStatus($rjNo);

        $this->incrementVersion('modal-cppt-ugd');
    }

    /* ── Dokter/Admin: Assessment terisi otomatis dari diagnosis kunjungan (pola CPPT RI) ── */
    private function isiAssessmentDariDiagnosa(): void
    {
        if (!auth()->user()->hasAnyRole(['Dokter', 'Admin'])) {
            return;
        }
        if ($this->diagnosaAwal !== '' && empty($this->formEntryCPPT['soap']['assessment'])) {
            $this->formEntryCPPT['soap']['assessment'] = $this->diagnosaAwal;
        }
    }

    /* ── Simpan SOAP mem-morph parent EMR → komponen pasif ikut ter-wipe.
       Muat ulang HANYA bila memang ter-wipe (regNoPasien hilang) agar entri berjalan tak reset. ── */
    #[On('refresh-after-ugd.saved')]
    public function reloadAfterUgdSaved(): void
    {
        if (empty($this->rjNo) || !empty($this->regNoPasien)) {
            return;
        }

        $this->open((int) $this->rjNo);
    }

    public function setTglCPPT(): void
    {
        $this->formEntryCPPT['tglCPPT'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->incrementVersion('modal-cppt-ugd');
    }

    #[On('save-rm-cppt-ugd')]
    public function addCPPT(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'EMR terkunci — data tidak dapat diubah.');
            return;
        }

        // Mode edit: perbarui entri yang ada, bukan tambah baru.
        if ($this->editingCpptId !== null) {
            $this->updateCPPT();
            return;
        }

        $this->formEntryCPPT['petugasCPPT'] = auth()->user()->myuser_name;
        $this->formEntryCPPT['petugasCPPTCode'] = auth()->user()->myuser_code;
        $this->formEntryCPPT['profession'] = $this->profesiSaya();

        $this->validateWithToast($this->aturanCPPT(), $this->pesanCPPT());

        $fingerprint = md5(json_encode([$this->formEntryCPPT['tglCPPT'], $this->formEntryCPPT['soap']], JSON_UNESCAPED_UNICODE));

        try {
            $inserted = false;

            DB::transaction(function () use ($fingerprint, &$inserted) {
                $this->lockUGDRow($this->rjNo);

                $fresh = $this->findDataUGD($this->rjNo) ?? [];
                if (empty($fresh)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
                }
                $fresh['cppt'] ??= [];

                if (collect($fresh['cppt'])->first(fn($r) => ($r['fingerprint'] ?? null) === $fingerprint)) {
                    $this->dispatch('toast', type: 'info', message: 'CPPT yang sama sudah tersimpan.');
                    return;
                }

                $fresh['cppt'][] = array_merge($this->formEntryCPPT, [
                    'cpptId' => (string) Str::uuid(),
                    'fingerprint' => $fingerprint,
                ]);

                $this->updateJsonUGD((int) $this->rjNo, $fresh);
                $this->muatDariDokumen($fresh);
                $inserted = true;

                $this->appendAdminLogUGD((int) $this->rjNo, 'Tambah CPPT UGD — entri ' . $this->formEntryCPPT['tglCPPT'] . ' (' . ($this->formEntryCPPT['profession'] ?: '-') . ')', 'MR');
            });

            if ($inserted) {
                $this->reset(['formEntryCPPT']);
                $this->isiAssessmentDariDiagnosa();
                $this->afterSave('CPPT berhasil ditambahkan.');
            }
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ── Muat entri ke form untuk diedit — HANYA pemilik entri atau role ugd.cppt.ubahSemua ── */
    public function editCPPT(string $cpptId): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'EMR terkunci — data tidak dapat diubah.');
            return;
        }

        $cppt = collect($this->daftarCppt)->first(fn($r) => ($r['cpptId'] ?? null) === $cpptId);
        if (!$cppt) {
            $this->dispatch('toast', type: 'error', message: 'CPPT tidak ditemukan.');
            return;
        }

        if (!$this->canEditCppt($cppt)) {
            $this->dispatch('toast', type: 'error', message: 'Hanya pemilik entri atau Admin yang dapat mengedit CPPT.');
            return;
        }

        $this->editingCpptId = $cpptId;
        $this->formEntryCPPT = array_merge($this->formEntryCPPT, [
            'tglCPPT' => $cppt['tglCPPT'] ?? '',
            'petugasCPPT' => $cppt['petugasCPPT'] ?? '',
            'petugasCPPTCode' => $cppt['petugasCPPTCode'] ?? '',
            'profession' => $cppt['profession'] ?? '',
            'soap' => $cppt['soap'] ?? ['subjective' => '', 'objective' => '', 'assessment' => '', 'plan' => ''],
            'instruction' => $cppt['instruction'] ?? '',
            'review' => $cppt['review'] ?? '',
        ]);

        $this->resetValidation();
        $this->incrementVersion('modal-cppt-ugd');
        $this->dispatch('toast', type: 'info', message: 'Mode edit CPPT — ubah lalu klik Perbarui CPPT.');
    }

    /* ── Pemilik entri (petugasCPPTCode === myuser_code), atau role bercentang ugd.cppt.ubahSemua ── */
    private function canEditCppt(array $cppt): bool
    {
        if (auth()->user()->can('ugd.cppt.ubahSemua')) {
            return true;
        }
        $ownerCode = (string) ($cppt['petugasCPPTCode'] ?? '');
        return $ownerCode !== '' && $ownerCode === auth()->user()->myuser_code;
    }

    public function cancelEditCPPT(): void
    {
        $this->editingCpptId = null;
        $this->reset(['formEntryCPPT']);
        $this->isiAssessmentDariDiagnosa();
        $this->resetValidation();
        $this->incrementVersion('modal-cppt-ugd');
    }

    private function updateCPPT(): void
    {
        $this->validateWithToast($this->aturanCPPT(), $this->pesanCPPT());

        try {
            $updated = false;

            DB::transaction(function () use (&$updated) {
                $this->lockUGDRow($this->rjNo);

                $fresh = $this->findDataUGD($this->rjNo) ?? [];
                $cppts = collect($fresh['cppt'] ?? []);
                $idx = $cppts->search(fn($r) => ($r['cpptId'] ?? null) === $this->editingCpptId);
                if ($idx === false) {
                    throw new \RuntimeException('CPPT tidak ditemukan.');
                }

                $row = $cppts->get($idx);

                // Guard ulang terhadap data segar (hindari bypass via state lama).
                if (!$this->canEditCppt($row)) {
                    throw new \RuntimeException('Hanya pemilik entri atau Admin yang dapat mengedit CPPT.');
                }

                // Perbarui konten saja; identitas petugas tetap milik penulis asli.
                $row['tglCPPT'] = $this->formEntryCPPT['tglCPPT'];
                $row['soap'] = $this->formEntryCPPT['soap'];
                $row['instruction'] = $this->formEntryCPPT['instruction'];
                $row['review'] = $this->formEntryCPPT['review'];
                $row['fingerprint'] = md5(json_encode([$row['tglCPPT'], $row['soap']], JSON_UNESCAPED_UNICODE));
                // Konten berubah → review/TTD DPJP sebelumnya tidak lagi valid.
                unset($row['reviewDpjp']);

                $cppts->put($idx, $row);
                $fresh['cppt'] = $cppts->values()->all();

                $this->updateJsonUGD((int) $this->rjNo, $fresh);
                $this->muatDariDokumen($fresh);
                $updated = true;

                $this->appendAdminLogUGD((int) $this->rjNo, 'Edit CPPT UGD — entri ' . ($row['tglCPPT'] ?? '-') . ' (' . ($row['profession'] ?: '-') . ')', 'MR');
            });

            if ($updated) {
                $this->editingCpptId = null;
                $this->reset(['formEntryCPPT']);
                $this->isiAssessmentDariDiagnosa();
                $this->afterSave('CPPT berhasil diperbarui.');
            }
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function removeCPPT(string $cpptId): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'EMR terkunci — data tidak dapat diubah.');
            return;
        }

        // Hapus CPPT: hanya level Manager ke atas — fungsional & Supervisor tidak bisa walau pemilik entri.
        if (!auth()->user()->can('ugd.cppt.hapus')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak menghapus CPPT.');
            return;
        }

        try {
            DB::transaction(function () use ($cpptId) {
                $this->lockUGDRow($this->rjNo);

                $fresh = $this->findDataUGD($this->rjNo) ?? [];
                $cppts = collect($fresh['cppt'] ?? []);
                $idx = $cppts->search(fn($r) => ($r['cpptId'] ?? null) === $cpptId);

                if ($idx === false) {
                    throw new \RuntimeException('CPPT tidak ditemukan.');
                }

                $cpptRow = $cppts->get($idx);
                $cppts->forget($idx);
                $fresh['cppt'] = $cppts->values()->all();

                $this->updateJsonUGD((int) $this->rjNo, $fresh);
                $this->muatDariDokumen($fresh);

                $this->appendAdminLogUGD((int) $this->rjNo, 'Hapus CPPT UGD — entri ' . ($cpptRow['tglCPPT'] ?? '-') . ' oleh ' . ($cpptRow['petugasCPPT'] ?? '-'), 'MR');
            });

            $this->afterSave('CPPT berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ── Review/TTD CPPT — HANYA DPJP (dokter UGD) / role ugd.cppt.reviewSemua ── */
    public function reviewCpptDpjp(string $cpptId): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'EMR terkunci — data tidak dapat diubah.');
            return;
        }

        $dpjpId = $this->dpjpId;
        $dpjpName = $this->dpjpDesc;
        $bolehReviewSemua = auth()->user()->can('ugd.cppt.reviewSemua');

        if ($dpjpId === '') {
            $this->dispatch('toast', type: 'error', message: 'Dokter UGD belum ditentukan pada kunjungan ini.');
            return;
        }
        if (!$bolehReviewSemua && $dpjpId !== auth()->user()->myuser_code) {
            $this->dispatch('toast', type: 'error', message: 'Hanya DPJP UGD / Admin yang dapat me-review CPPT.');
            return;
        }

        try {
            DB::transaction(function () use ($cpptId, $dpjpId, $dpjpName) {
                $this->lockUGDRow($this->rjNo);

                $fresh = $this->findDataUGD($this->rjNo) ?? [];
                $cppts = collect($fresh['cppt'] ?? []);
                $idx = $cppts->search(fn($r) => ($r['cpptId'] ?? null) === $cpptId);
                if ($idx === false) {
                    throw new \RuntimeException('CPPT tidak ditemukan.');
                }

                $row = $cppts->get($idx);
                $row['reviewDpjp'] = [
                    'drId' => $dpjpId,
                    'drName' => $dpjpName ?: auth()->user()->myuser_name,
                    'tglReview' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                ];
                $cppts->put($idx, $row);
                $fresh['cppt'] = $cppts->values()->all();

                $this->updateJsonUGD((int) $this->rjNo, $fresh);
                $this->muatDariDokumen($fresh);

                $this->appendAdminLogUGD((int) $this->rjNo, 'Review CPPT UGD — entri ' . ($row['tglCPPT'] ?? '-') . ' oleh DPJP ' . ($dpjpName ?: '-'), 'MR');
            });

            $this->afterSave('CPPT sudah direview DPJP.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ── Batal review — HANYA DPJP (dokter UGD) / role ugd.cppt.reviewSemua ── */
    public function batalReviewCpptDpjp(string $cpptId): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'EMR terkunci — data tidak dapat diubah.');
            return;
        }

        $dpjpId = $this->dpjpId;
        $bolehReviewSemua = auth()->user()->can('ugd.cppt.reviewSemua');
        if (!$bolehReviewSemua && ($dpjpId === '' || $dpjpId !== auth()->user()->myuser_code)) {
            $this->dispatch('toast', type: 'error', message: 'Hanya DPJP UGD / Admin yang dapat membatalkan review.');
            return;
        }

        try {
            DB::transaction(function () use ($cpptId) {
                $this->lockUGDRow($this->rjNo);

                $fresh = $this->findDataUGD($this->rjNo) ?? [];
                $cppts = collect($fresh['cppt'] ?? []);
                $idx = $cppts->search(fn($r) => ($r['cpptId'] ?? null) === $cpptId);
                if ($idx === false) {
                    throw new \RuntimeException('CPPT tidak ditemukan.');
                }

                $row = $cppts->get($idx);
                unset($row['reviewDpjp']);
                $cppts->put($idx, $row);
                $fresh['cppt'] = $cppts->values()->all();

                $this->updateJsonUGD((int) $this->rjNo, $fresh);
                $this->muatDariDokumen($fresh);

                $this->appendAdminLogUGD((int) $this->rjNo, 'Batal review CPPT UGD — entri ' . ($row['tglCPPT'] ?? '-'), 'MR');
            });

            $this->afterSave('Review DPJP dibatalkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ── Cetak PDF satu entri CPPT ── */
    public function printCppt(string $cpptId): mixed
    {
        $cppt = collect($this->daftarCppt)->first(fn($r) => ($r['cpptId'] ?? null) === $cpptId);
        if (empty($cppt)) {
            $this->dispatch('toast', type: 'error', message: 'CPPT tidak ditemukan.');
            return null;
        }

        $regNo = (string) ($this->regNoPasien ?? '');
        $pasienData = $regNo !== '' ? $this->findDataMasterPasien($regNo) : [];
        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        $pdf = Pdf::loadView('pages.components.rekam-medis.ugd.cetak-cppt.cetak-cppt-ugd-print', [
            'cppt' => $cppt,
            'dataPasien' => $pasienData,
            // Dokumen dibaca ULANG di sini, bukan disimpan di properti publik: cetak itu aksi
            // jarang, dan nilainya justru paling segar. Cetakan hanya perlu rjDate & drDesc.
            'dataDaftarUGD' => $this->findDataUGD($this->rjNo) ?? [],
        ])->setPaper('A4');

        $filename = 'cppt-ugd-' . ($regNo !== '' ? $regNo : $this->rjNo) . '-' . substr($cpptId, 0, 8) . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename);
    }

    public function copyCPPT(string $cpptId): void
    {
        $cppt = collect($this->daftarCppt)->first(fn($r) => ($r['cpptId'] ?? null) === $cpptId);

        if (!$cppt) {
            $this->dispatch('toast', type: 'error', message: 'CPPT tidak ditemukan.');
            return;
        }

        // Copy hanya bisa dilakukan oleh profesi yang sama, kecuali role bercentang ugd.cppt.copySemua.
        if (!auth()->user()->can('ugd.cppt.copySemua')) {
            if ($this->profesiSaya() !== ($cppt['profession'] ?? '')) {
                $this->dispatch('toast', type: 'error', message: 'Hanya bisa copy CPPT dari profesi yang sama.');
                return;
            }
        }

        // Copy = entri baru → keluar dari mode edit bila sedang aktif.
        $this->editingCpptId = null;

        $this->formEntryCPPT = array_merge($this->formEntryCPPT, [
            'tglCPPT' => '',
            'petugasCPPT' => '',
            'petugasCPPTCode' => '',
            'profession' => '',
            'soap' => $cppt['soap'] ?? ['subjective' => '', 'objective' => '', 'assessment' => '', 'plan' => ''],
            'instruction' => $cppt['instruction'] ?? '',
            'review' => $cppt['review'] ?? '',
        ]);

        $this->incrementVersion('modal-cppt-ugd');
        $this->dispatch('toast', type: 'success', message: 'CPPT dicopy ke form.');
    }

    public function getCpptCount(string $profession): int
    {
        if ($profession === 'Semua') {
            return count($this->daftarCppt);
        }
        return collect($this->daftarCppt)->where('profession', $profession)->count();
    }

    /* ── Nama role bisa punya spasi ekor (data legacy) → selalu trim ── */
    private function profesiSaya(): string
    {
        return trim((string) auth()->user()->profesiKlinis());
    }

    private function aturanCPPT(): array
    {
        return [
            'formEntryCPPT.tglCPPT' => 'required|date_format:d/m/Y H:i:s',
            'formEntryCPPT.soap.subjective' => 'required|string|max:2000',
            'formEntryCPPT.soap.objective' => 'required|string|max:2000',
            'formEntryCPPT.soap.assessment' => 'required|string|max:2000',
            'formEntryCPPT.soap.plan' => 'required|string|max:2000',
        ];
    }

    private function pesanCPPT(): array
    {
        return [
            'formEntryCPPT.tglCPPT.required' => 'Tanggal CPPT wajib diisi.',
            'formEntryCPPT.tglCPPT.date_format' => 'Tanggal CPPT harus format dd/mm/yyyy HH:ii:ss.',
            'formEntryCPPT.soap.subjective.required' => 'Subjective (S) wajib diisi.',
            'formEntryCPPT.soap.objective.required' => 'Objective (O) wajib diisi.',
            'formEntryCPPT.soap.assessment.required' => 'Assessment (A) wajib diisi.',
            'formEntryCPPT.soap.plan.required' => 'Plan (P) wajib diisi.',
        ];
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-cppt-ugd');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->daftarCppt = [];
        $this->regNoPasien = null;
        $this->dpjpId = '';
        $this->dpjpDesc = '';
        $this->diagnosaAwal = '';
        $this->activeProfession = 'Semua';
        $this->editingCpptId = null;
        $this->reset(['formEntryCPPT']);
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-cppt-ugd', [$rjNo ?? 'new']) }}">

    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 mb-2 rounded-lg
                    bg-amber-50 border border-amber-200 text-amber-800
                    dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300 text-sm">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            EMR terkunci — form dalam mode <strong>read-only</strong>.
        </div>
    @endif

    {{-- ── CPPT CONTENT ── --}}
    <div class="space-y-4">

        {{-- FORM ENTRY --}}
        @if (!$isFormLocked)
            <div class="space-y-3">

                @if ($editingCpptId)
                    <div
                        class="flex items-center justify-between gap-3 px-4 py-2.5 rounded-lg
                                bg-indigo-50 border border-indigo-200 text-indigo-800
                                dark:bg-indigo-900/20 dark:border-indigo-700 dark:text-indigo-300 text-sm">
                        <span class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Mengedit CPPT milik <strong>{{ $formEntryCPPT['petugasCPPT'] ?: '-' }}</strong> — ubah lalu klik
                            <strong>Perbarui CPPT</strong>.
                        </span>
                        <x-secondary-button type="button" wire:click="cancelEditCPPT" wire:loading.attr="disabled"
                            class="shrink-0">
                            <x-ikon nama="batal" />
                            Batal
                        </x-secondary-button>
                    </div>
                @endif

                <div class="flex items-end gap-3">
                    <div class="flex-1">
                        <x-input-label value="Tanggal CPPT *" />
                        <x-text-input wire:model="formEntryCPPT.tglCPPT" class="w-full mt-1 font-mono"
                            placeholder="dd/mm/yyyy hh:mm:ss" :error="$errors->has('formEntryCPPT.tglCPPT')" />
                        <x-input-error :messages="$errors->get('formEntryCPPT.tglCPPT')" class="mt-1" />
                    </div>
                    <x-now-button wire:click="setTglCPPT" />
                </div>

                <div class="grid grid-cols-4 gap-2">
                    @foreach ([['subjective', 'S — Subjective *'], ['objective', 'O — Objective *'], ['assessment', 'A — Assessment *'], ['plan', 'P — Plan *']] as [$key, $label])
                        <div>
                            <x-input-label value="{{ $label }}" />
                            <x-textarea wire:model="formEntryCPPT.soap.{{ $key }}" class="w-full mt-1" rows="3"
                                :error="$errors->has('formEntryCPPT.soap.' . $key)" placeholder="{{ $label }}..." />
                            <x-input-error :messages="$errors->get('formEntryCPPT.soap.' . $key)" class="mt-1" />
                        </div>
                    @endforeach
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label value="Instruksi" />
                        <x-textarea wire:model="formEntryCPPT.instruction" class="w-full mt-1" rows="2" />
                    </div>
                    <div>
                        <x-input-label value="Review" />
                        <x-textarea wire:model="formEntryCPPT.review" class="w-full mt-1" rows="2" />
                    </div>
                </div>

                {{-- Tombol simpan milik komponen (footer EMR UGD hanya menyimpan SOAP) --}}
                <div class="flex justify-end">
                    <x-primary-button type="button" wire:click.prevent="addCPPT" wire:loading.attr="disabled"
                        wire:target="addCPPT" class="gap-2">
                        <span wire:loading.remove wire:target="addCPPT" class="flex items-center gap-1">
                            <x-ikon nama="simpan" />
                            {{ $editingCpptId ? 'Perbarui CPPT' : 'Tambah CPPT' }}
                        </span>
                        <span wire:loading wire:target="addCPPT" class="flex items-center gap-1">
                            <x-loading class="w-4 h-4" /> Menyimpan...
                        </span>
                    </x-primary-button>
                </div>

            </div>
        @endif

        {{-- RIWAYAT CPPT --}}
        <x-border-form title="Riwayat CPPT" align="start" bgcolor="bg-surface-soft">
            <div class="mt-2">

                {{-- Tab Profesi (sticky atas agar mudah ganti tab saat data banyak) --}}
                <div
                    class="sticky top-0 z-20 -mx-4 -mt-2 px-4 pt-2 bg-surface-soft dark:bg-gray-900 border-b border-hairline dark:border-gray-700 mb-3">
                    <ul class="flex flex-wrap -mb-px text-sm font-medium">
                        @foreach ($professionTabs as $prof)
                            @php
                                $count = $this->getCpptCount($prof);
                                $isActive = $activeProfession === $prof;
                                // Warna per profesi (selaras badge kartu). Class ditulis literal agar tidak ke-purge Tailwind.
                                $tab = match ($prof) {
                                    'Dokter' => [
                                        'active' => 'text-blue-700 border-blue-500 bg-blue-50 dark:text-blue-300 dark:border-blue-400 dark:bg-blue-900/20',
                                        'inactive' => 'text-blue-500/80 border-transparent hover:text-blue-700 hover:bg-blue-50/60 hover:border-blue-300 dark:text-blue-400/70',
                                        'badgeOn' => 'bg-blue-600 text-white',
                                        'badgeOff' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
                                    ],
                                    'Perawat' => [
                                        'active' => 'text-green-700 border-green-500 bg-green-50 dark:text-green-300 dark:border-green-400 dark:bg-green-900/20',
                                        'inactive' => 'text-green-600/80 border-transparent hover:text-green-700 hover:bg-green-50/60 hover:border-green-300 dark:text-green-400/70',
                                        'badgeOn' => 'bg-green-600 text-white',
                                        'badgeOff' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
                                    ],
                                    'Apoteker' => [
                                        'active' => 'text-error border-rose-500 bg-rose-50 dark:text-rose-300 dark:border-rose-400 dark:bg-rose-900/20',
                                        'inactive' => 'text-error/80 border-transparent hover:text-error hover:bg-rose-50/60 hover:border-rose-300 dark:text-rose-400/70',
                                        'badgeOn' => 'bg-rose-600 text-white',
                                        'badgeOff' => 'bg-rose-100 text-error dark:bg-rose-900/40 dark:text-rose-300',
                                    ],
                                    'Gizi' => [
                                        'active' => 'text-orange-700 border-orange-500 bg-orange-50 dark:text-orange-300 dark:border-orange-400 dark:bg-orange-900/20',
                                        'inactive' => 'text-orange-500/80 border-transparent hover:text-orange-700 hover:bg-orange-50/60 hover:border-orange-300 dark:text-orange-400/70',
                                        'badgeOn' => 'bg-orange-600 text-white',
                                        'badgeOff' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
                                    ],
                                    'Penunjang' => [
                                        'active' => 'text-cyan-700 border-cyan-500 bg-cyan-50 dark:text-cyan-300 dark:border-cyan-400 dark:bg-cyan-900/20',
                                        'inactive' => 'text-cyan-600/80 border-transparent hover:text-cyan-700 hover:bg-cyan-50/60 hover:border-cyan-300 dark:text-cyan-400/70',
                                        'badgeOn' => 'bg-cyan-600 text-white',
                                        'badgeOff' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300',
                                    ],
                                    default => [
                                        'active' => 'text-slate-700 border-slate-500 bg-slate-100 dark:text-slate-200 dark:border-slate-400 dark:bg-slate-700/40',
                                        'inactive' => 'text-slate-500 border-transparent hover:text-slate-700 hover:bg-surface-soft hover:border-slate-300 dark:text-slate-400',
                                        'badgeOn' => 'bg-slate-600 text-white',
                                        'badgeOff' => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
                                    ],
                                };
                            @endphp
                            <li class="mr-0.5">
                                <button type="button" wire:click="$set('activeProfession', '{{ $prof }}')"
                                    class="inline-flex items-center gap-1.5 px-4 py-3 border-b-2 rounded-t-lg transition-colors
                                        {{ $isActive ? $tab['active'] : $tab['inactive'] }}">
                                    {{ $prof }}
                                    @if ($count > 0)
                                        <span
                                            class="inline-flex items-center justify-center w-4 h-4 text-sm font-bold rounded-full
                                            {{ $isActive ? $tab['badgeOn'] : $tab['badgeOff'] }}">
                                            {{ $count }}
                                        </span>
                                    @endif
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- List CPPT --}}
                <div class="space-y-3">
                    @php
                        // Urut tanggal CPPT desc (terbaru di atas) untuk semua tab profesi.
                        $allCppt = collect($daftarCppt)
                            ->sortByDesc(fn($c) => Carbon::createFromFormat('d/m/Y H:i:s', ($c['tglCPPT'] ?? '') ?: '01/01/2000 00:00:00')->timestamp)
                            ->values()
                            ->all();
                        $filtered =
                            $activeProfession === 'Semua'
                                ? $allCppt
                                : array_values(
                                    array_filter($allCppt, fn($c) => ($c['profession'] ?? '') === $activeProfession),
                                );

                        // DPJP UGD = dokter kunjungan. Hanya dia (atau Admin) yang boleh review/TTD CPPT.
                        $isDpjpUgd = $dpjpId !== '' && $dpjpId === auth()->user()->myuser_code;
                        $canReviewDpjp = $dpjpId !== '' && ($isDpjpUgd || auth()->user()->can('ugd.cppt.reviewSemua'));
                    @endphp

                    @forelse ($filtered as $idx => $cppt)
                        <div wire:key="cppt-ugd-{{ $cppt['cpptId'] ?? $idx }}-{{ $this->renderKey('modal-cppt-ugd') }}"
                            class="border rounded-lg overflow-hidden bg-canvas dark:bg-gray-800
                                {{ $editingCpptId === ($cppt['cpptId'] ?? null) ? 'border-indigo-400 ring-2 ring-indigo-300 dark:border-indigo-500 dark:ring-indigo-700' : 'border-hairline dark:border-gray-700' }}">

                            <div
                                class="flex items-center justify-between px-4 py-2.5
                                    bg-surface-soft dark:bg-gray-700/60 border-b border-hairline-soft dark:border-gray-700">
                                <div class="flex items-center gap-2 text-sm">
                                    @php
                                        $profColor = match ($cppt['profession'] ?? '') {
                                            'Dokter' => 'bg-blue-100 text-blue-700',
                                            'Perawat' => 'bg-green-100 text-green-700',
                                            'Apoteker' => 'bg-rose-100 text-error',
                                            'Gizi' => 'bg-orange-100 text-orange-700',
                                            default => 'bg-surface-soft text-muted',
                                        };
                                    @endphp
                                    <span class="px-2 py-0.5 rounded-full text-sm font-bold {{ $profColor }}">
                                        {{ $cppt['profession'] ?? '-' }}
                                    </span>
                                    <span class="font-semibold text-body dark:text-gray-200">
                                        {{ $cppt['petugasCPPT'] ?? '-' }}
                                    </span>
                                    <span class="font-mono text-muted dark:text-gray-300">{{ $cppt['tglCPPT'] ?? '-' }}</span>
                                </div>

                                <div class="flex items-center gap-2">
                                    @if (!empty($cppt['reviewDpjp']['drName']))
                                        <div class="flex flex-col items-end leading-tight">
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-sm font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
                                                title="Direview DPJP: {{ $cppt['reviewDpjp']['drName'] }} — {{ $cppt['reviewDpjp']['tglReview'] ?? '' }}">
                                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                Sudah direview oleh {{ $cppt['reviewDpjp']['drName'] }}
                                            </span>
                                            @if (!empty($cppt['reviewDpjp']['tglReview']))
                                                <span
                                                    class="text-sm font-mono text-muted-soft dark:text-gray-500 mt-0.5">{{ $cppt['reviewDpjp']['tglReview'] }}</span>
                                            @endif
                                        </div>
                                    @endif

                                    @unless (auth()->user()->hasRole('Dokter'))
                                        <x-cetak-button wire:click="printCppt('{{ $cppt['cpptId'] ?? '' }}')" title="Cetak CPPT" />
                                    @endunless

                                    @if (!$isFormLocked)
                                        @php
                                            $myRole = trim((string) auth()->user()->profesiKlinis());
                                            $cpptRole = $cppt['profession'] ?? '';
                                            // Hapus CPPT: hanya level Manager ke atas — fungsional & Supervisor tidak bisa, walau pemilik entri.
                                            $canDelete = auth()->user()->can('ugd.cppt.hapus');
                                            $canCopy = auth()->user()->can('ugd.cppt.copySemua') || $myRole === $cpptRole;
                                            // Edit CPPT: HANYA pemilik entri (petugasCPPTCode), atau role bercentang ugd.cppt.ubahSemua.
                                            $ownerCode = (string) ($cppt['petugasCPPTCode'] ?? '');
                                            $isOwner = $ownerCode !== '' && $ownerCode === auth()->user()->myuser_code;
                                            $canEdit = auth()->user()->can('ugd.cppt.ubahSemua') || $isOwner;
                                        @endphp
                                        <div class="flex gap-1.5">
                                            @if ($canEdit)
                                                <x-outline-button type="button"
                                                    wire:click="editCPPT('{{ $cppt['cpptId'] ?? '' }}')"
                                                    wire:loading.attr="disabled"
                                                    class="!text-indigo-600 !bg-indigo-50 !border-indigo-200 hover:!bg-indigo-100 hover:!text-indigo-700 hover:!border-indigo-300 dark:!text-indigo-400 dark:!bg-indigo-900/20 dark:!border-indigo-800/30 dark:hover:!bg-indigo-900/30 dark:hover:!text-indigo-300"
                                                    title="Edit CPPT">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </x-outline-button>
                                            @endif
                                            @if ($canReviewDpjp)
                                                @if (empty($cppt['reviewDpjp']['drName']))
                                                    <x-outline-button type="button"
                                                        wire:click="reviewCpptDpjp('{{ $cppt['cpptId'] ?? '' }}')"
                                                        wire:confirm="Review & TTD CPPT ini sebagai DPJP?"
                                                        wire:loading.attr="disabled" title="Review / TTD DPJP">
                                                        <span class="inline-flex items-center gap-1">
                                                            <x-ikon nama="setujui" ukuran="w-5 h-5" />
                                                            <span class="text-sm font-semibold">Review DPJP</span>
                                                        </span>
                                                    </x-outline-button>
                                                @else
                                                    <x-secondary-button type="button"
                                                        wire:click="batalReviewCpptDpjp('{{ $cppt['cpptId'] ?? '' }}')"
                                                        wire:confirm="Batalkan review DPJP pada CPPT ini?"
                                                        wire:loading.attr="disabled" title="Batal review DPJP">
                                                        <span class="inline-flex items-center gap-1">
                                                            <x-ikon nama="batal" ukuran="w-5 h-5" />
                                                            <span class="text-sm font-semibold">Batal Review</span>
                                                        </span>
                                                    </x-secondary-button>
                                                @endif
                                            @endif
                                            @if ($canCopy)
                                                <x-outline-button type="button"
                                                    wire:click="copyCPPT('{{ $cppt['cpptId'] ?? '' }}')"
                                                    wire:loading.attr="disabled"
                                                    class="!text-blue-600 !bg-blue-50 !border-blue-200 hover:!bg-blue-100 hover:!text-blue-700 hover:!border-blue-300 dark:!text-blue-400 dark:!bg-blue-900/20 dark:!border-blue-800/30 dark:hover:!bg-blue-900/30 dark:hover:!text-blue-300"
                                                    title="Copy ke form">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                    </svg>
                                                </x-outline-button>
                                            @endif
                                            @if ($canDelete)
                                                <x-hapus-button wire:click="removeCPPT('{{ $cppt['cpptId'] ?? '' }}')"
                                                    confirm="Yakin hapus CPPT ini?" />
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="px-4 py-3 space-y-2 text-sm">
                                <div class="grid grid-cols-2 gap-3">
                                    @php
                                        $soapStyles = [
                                            'subjective' => ['lbl' => 'S', 'name' => 'Subjective', 'wrap' => 'border-l-4 border-blue-500 bg-blue-50/40 dark:bg-blue-900/10', 'text' => 'text-blue-700 dark:text-blue-400'],
                                            'objective' => ['lbl' => 'O', 'name' => 'Objective', 'wrap' => 'border-l-4 border-emerald-500 bg-emerald-50/40 dark:bg-emerald-900/10', 'text' => 'text-success dark:text-success'],
                                            'assessment' => ['lbl' => 'A', 'name' => 'Assessment', 'wrap' => 'border-l-4 border-amber-500 bg-amber-50/40 dark:bg-amber-900/10', 'text' => 'text-amber-700 dark:text-amber-400'],
                                            'plan' => ['lbl' => 'P', 'name' => 'Plan', 'wrap' => 'border-l-4 border-rose-500 bg-rose-50/40 dark:bg-rose-900/10', 'text' => 'text-error dark:text-rose-400'],
                                        ];
                                    @endphp
                                    @foreach ($soapStyles as $k => $s)
                                        <div class="{{ $s['wrap'] }} pl-3 py-1 rounded-r-md">
                                            <span class="font-bold {{ $s['text'] }}">{{ $s['lbl'] }}</span>
                                            <span class="text-muted"> — {{ $s['name'] }}</span>
                                            <p class="mt-0.5 text-body dark:text-gray-300 whitespace-pre-wrap leading-relaxed">{{ trim($cppt['soap'][$k] ?? '') ?: '-' }}</p>
                                        </div>
                                    @endforeach

                                    @if (!empty($cppt['instruction']))
                                        <div>
                                            <span class="font-semibold text-body dark:text-gray-300">Instruksi:</span>
                                            <p class="mt-0.5 text-body dark:text-gray-300 whitespace-pre-wrap">{{ $cppt['instruction'] }}</p>
                                        </div>
                                    @endif
                                    @if (!empty($cppt['review']))
                                        <div>
                                            <span class="font-semibold text-body dark:text-gray-300">Review:</span>
                                            <p class="mt-0.5 text-body dark:text-gray-300 whitespace-pre-wrap">{{ $cppt['review'] }}</p>
                                        </div>
                                    @endif
                                </div>
                            </div>

                        </div>
                    @empty
                        <p wire:key="cppt-ugd-empty-{{ $activeProfession }}-{{ $this->renderKey('modal-cppt-ugd') }}"
                            class="text-sm text-center text-muted-soft py-6">
                            @if ($activeProfession === 'Semua')
                                Belum ada CPPT.
                            @else
                                Belum ada CPPT dari <strong>{{ $activeProfession }}</strong>.
                            @endif
                        </p>
                    @endforelse

                </div>

            </div>
        </x-border-form>

    </div>{{-- end cppt content --}}

</div>
