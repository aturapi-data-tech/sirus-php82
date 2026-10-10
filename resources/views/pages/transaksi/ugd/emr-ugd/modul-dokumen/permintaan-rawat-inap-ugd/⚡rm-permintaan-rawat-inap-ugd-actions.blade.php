<?php
// resources/views/pages/transaksi/ugd/emr-ugd/modul-dokumen/permintaan-rawat-inap-ugd/rm-permintaan-rawat-inap-ugd-actions.blade.php
// Surat Permintaan Rawat Inap (RM-08.03) — dokter IGD meminta pasien dirawat inap,
// pasien/keluarga menandatangani "Mengetahui". Pola multi-entri Second Opinion UGD.

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Support\TtdUser;
use App\Support\TtdPasien;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public ?string $regNo = null;
    public bool $disabled = false;
    /** Nama pasien untuk isian awal penanda tangan. */
    public ?string $regName = null;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-permintaan-rawat-inap-ugd'];

    public array $newForm = [
        'tglPermintaan' => '',
        'diagnosis' => '',
        'dpjpId' => '',
        'dpjpName' => '',
        'rencanaTindakan' => '',
        'indikasi' => '',
        'namaPenanda' => '',
        'hubunganPasien' => 'pasien',
        'dokterIgd' => '',
        'dokterIgdCode' => '',
        'dokterIgdDate' => '',
    ];

    public string $signature = '';

    public array $permintaanRawatInapList = [];

    public array $hubunganPasienOptions = [
        ['value' => 'pasien', 'label' => 'Pasien Sendiri'],
        ['value' => 'suami', 'label' => 'Suami'],
        ['value' => 'istri', 'label' => 'Istri'],
        ['value' => 'ayah', 'label' => 'Ayah'],
        ['value' => 'ibu', 'label' => 'Ibu'],
        ['value' => 'anak', 'label' => 'Anak'],
        ['value' => 'saudara', 'label' => 'Saudara'],
        ['value' => 'wali_hukum', 'label' => 'Wali Hukum'],
        ['value' => 'lainnya', 'label' => 'Lainnya'],
    ];

    public ?string $editingKey = null;

    // Layar aktif di modal: 'daftar' (grid entri) atau 'form' (tambah/edit/lihat).
    public string $layar = 'daftar';

    /** Dokumen dibaca sebagai variabel LOKAL; hanya irisan di bawah ini yang disimpan. */
    private function muatDariDokumen(array $data): void
    {
        $this->regNo = $data['regNo'] ?? null;
        $this->permintaanRawatInapList = is_array($data['permintaanRawatInapUGD'] ?? null) ? $data['permintaanRawatInapUGD'] : [];
        $this->regName = $data['regName'] ?? null;
    }
    public bool $viewOnly = false;

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?int $rjNo = null, bool $disabled = false): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-permintaan-rawat-inap-ugd']);

        if ($this->rjNo) {
            $data = $this->findDataUGD($this->rjNo);
            if ($data) {
                $this->muatDariDokumen($data);
                $this->isFormLocked = $this->checkEmrUGDStatus($this->rjNo) || $disabled;
            }
        }
    }

    /* ===============================
     | OPEN MODAL
     =============================== */
    public function openModal(): void
    {
        if (!$this->rjNo || $this->disabled) {
            return;
        }

        $this->resetNewForm();
        $this->signature = '';
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();

        $data = $this->findDataUGD($this->rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        $this->muatDariDokumen($data);
        $this->newForm['namaPenanda'] = $this->regName ?? '';
        $this->isFormLocked = $this->checkEmrUGDStatus($this->rjNo) || $this->disabled;
        $this->incrementVersion('modal-permintaan-rawat-inap-ugd');

        $this->layar = 'daftar';

        $this->dispatch('open-modal', name: "rm-permintaan-rawat-inap-ugd-{$this->rjNo}");
    }

    /* ===============================
     | CLOSE
     =============================== */
    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-permintaan-rawat-inap-ugd-{$this->rjNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'newForm.tglPermintaan' => 'required|date_format:d/m/Y H:i:s',
            'newForm.diagnosis' => 'required|string|max:1000',
            'newForm.dpjpId' => 'required|string|max:50',
            'newForm.rencanaTindakan' => 'required|string|max:1000',
            'newForm.indikasi' => 'required|string|max:1000',
            'newForm.namaPenanda' => 'required|string|max:200',
            'newForm.hubunganPasien' => 'required|string|max:50',
            'signature' => 'required|string',
            'newForm.dokterIgd' => 'required|string',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'date_format' => 'Format :attribute harus dd/mm/yyyy HH:mm:ss (cth: 25/06/2026 11:11:16).',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newForm.tglPermintaan' => 'Tanggal/jam permintaan',
            'newForm.diagnosis' => 'Diagnosis',
            'newForm.dpjpId' => 'DPJP',
            'newForm.rencanaTindakan' => 'Rencana tindakan',
            'newForm.indikasi' => 'Alasan/indikasi rawat inap',
            'newForm.namaPenanda' => 'Nama pasien/keluarga',
            'newForm.hubunganPasien' => 'Hubungan dengan pasien',
            'signature' => 'Tanda tangan pasien/keluarga',
            'newForm.dokterIgd' => 'Tanda tangan dokter IGD',
        ];
    }

    /* ===============================
     | SET TANGGAL SEKARANG
     =============================== */
    public function setNow(string $field): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm[$field] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | LOV DPJP
     =============================== */
    #[On('lov.selected.dpjp-permintaan-rawat-inap-ugd')]
    public function onDpjpSelected(string $target, array $payload): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['dpjpId'] = (string) ($payload['dr_id'] ?? '');
        $this->newForm['dpjpName'] = (string) ($payload['dr_name'] ?? '');
        $this->resetValidation('newForm.dpjpId');
    }

    #[On('lov.cleared.dpjp-permintaan-rawat-inap-ugd')]
    public function onDpjpCleared(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['dpjpId'] = '';
        $this->newForm['dpjpName'] = '';
    }

    /* ===============================
     | SIGNATURE (pasien/keluarga)
     =============================== */
    public function setSignature(string $dataUrl): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signature = TtdPasien::simpan($dataUrl, $this->regNo);   // gambar ke RSTXN_TTDS; di sini cukup referensi "TTD:<no>"
        $this->incrementVersion('modal-permintaan-rawat-inap-ugd');
    }

    public function clearSignature(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signature = '';
        $this->incrementVersion('modal-permintaan-rawat-inap-ugd');
    }

    /* ===============================
     | TTD DOKTER IGD = FINALIZE
     =============================== */
    public function ttdDokterIgd(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (!auth()->user()->hasAnyRole(['Dokter', 'Admin'])) {
            $this->dispatch('toast', type: 'error', message: 'Surat permintaan rawat inap hanya dapat ditandatangani dokter.');
            return;
        }

        // Stempel ditulis SEBELUM validate() supaya rule 'newForm.dokterIgd' lolos; kalau
        // validasi atau simpan gagal, stempel dicabut lagi — jangan tertinggal di form.
        $stempelLama = [
            'dokterIgd' => $this->newForm['dokterIgd'],
            'dokterIgdCode' => $this->newForm['dokterIgdCode'],
            'dokterIgdDate' => $this->newForm['dokterIgdDate'],
        ];
        $this->newForm['dokterIgd'] = auth()->user()->myuser_name ?? '';
        $this->newForm['dokterIgdCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['dokterIgdDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->validateWithToast();
        } catch (ValidationException $e) {
            $this->newForm = array_replace($this->newForm, $stempelLama);
            throw $e;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, true, 'Kunci (TTD Dokter IGD)');
            $this->cancelEdit();
            $this->dispatch('toast', type: 'success', message: 'Surat permintaan rawat inap ditandatangani dokter IGD dan terkunci.');
        } catch (\Throwable $e) {
            $this->newForm = array_replace($this->newForm, $stempelLama);
            $this->dispatch('toast', type: 'error', message: $e instanceof \RuntimeException ? $e->getMessage() : 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HELPER — status & bentuk entri
     =============================== */
    public function entryIsFinal(array $e): bool
    {
        return array_key_exists('finalized', $e) ? (bool) $e['finalized'] : !empty($e['dokterIgd']);
    }

    /** Isian awal Diagnosis dari EMR UGD: daftar ICD-10 + diagnosis teks bebas. */
    private function diagnosisDariEmr(array $data): string
    {
        $baris = collect($data['diagnosis'] ?? [])
            ->map(fn($dx) => trim(($dx['icdX'] ?? '') . ' ' . ($dx['diagDesc'] ?? '')))
            ->filter();
        $diagnosisBebas = trim((string) ($data['diagnosisFreeText'] ?? ''));
        if ($diagnosisBebas !== '') {
            $baris->push($diagnosisBebas);
        }
        return $baris->implode("\n");
    }

    private function buildEntry(string $key, bool $finalized): array
    {
        return [
            'tglPermintaan' => $this->newForm['tglPermintaan'] ?? '',
            'diagnosis' => $this->newForm['diagnosis'] ?? '',
            'dpjpId' => $this->newForm['dpjpId'] ?? '',
            'dpjpName' => $this->newForm['dpjpName'] ?? '',
            'rencanaTindakan' => $this->newForm['rencanaTindakan'] ?? '',
            'indikasi' => $this->newForm['indikasi'] ?? '',
            'namaPenanda' => $this->newForm['namaPenanda'] ?? '',
            'hubunganPasien' => $this->newForm['hubunganPasien'] ?? 'pasien',
            'signature' => $this->signature,
            'signatureDate' => $key,
            'dokterIgd' => $finalized ? ($this->newForm['dokterIgd'] ?? '') : '',
            'dokterIgdCode' => $finalized ? ($this->newForm['dokterIgdCode'] ?? '') : '',
            'dokterIgdDate' => $finalized ? ($this->newForm['dokterIgdDate'] ?? '') : '',
            'finalized' => $finalized,
        ];
    }

    private function persistEntry(string $key, bool $finalized, string $logVerb): void
    {
        $entry = $this->buildEntry($key, $finalized);

        DB::transaction(function () use ($entry, $key, $logVerb) {
            $this->lockUGDRow($this->rjNo);

            $data = $this->findDataUGD($this->rjNo);
            if (empty($data)) {
                throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
            }
            if (!isset($data['permintaanRawatInapUGD']) || !is_array($data['permintaanRawatInapUGD'])) {
                $data['permintaanRawatInapUGD'] = [];
            }

            $list = $data['permintaanRawatInapUGD'];
            $idx = collect($list)->search(fn($it) => ($it['signatureDate'] ?? '') === $key);
            if ($idx === false) {
                $list[] = $entry;
            } else {
                if ($this->entryIsFinal($list[$idx])) {
                    throw new \RuntimeException('Entri sudah terkunci, tidak dapat diubah.');
                }
                $list[$idx] = $entry;
            }
            $data['permintaanRawatInapUGD'] = array_values($list);

            $this->updateJsonUGD($this->rjNo, $data);
            $this->muatDariDokumen($data);

            $this->appendAdminLogUGD((int) $this->rjNo, $logVerb . ' Surat Permintaan Rawat Inap UGD — DPJP "' . ($entry['dpjpName'] ?: '-') . '" (' . $key . ')', 'MR');
        });
    }

    /* ===============================
     | SIMPAN DRAFT
     =============================== */
    public function saveDraft(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }
        if (trim($this->newForm['diagnosis'] ?? '') === '') {
            $this->dispatch('toast', type: 'error', message: 'Diagnosis wajib diisi untuk menyimpan draft.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->cancelEdit();
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
    private function hydrateFormFromEntry(array $entry, string $key): void
    {
        $this->newForm = [
            'tglPermintaan' => $entry['tglPermintaan'] ?? '',
            'diagnosis' => $entry['diagnosis'] ?? '',
            'dpjpId' => $entry['dpjpId'] ?? '',
            'dpjpName' => $entry['dpjpName'] ?? '',
            'rencanaTindakan' => $entry['rencanaTindakan'] ?? '',
            'indikasi' => $entry['indikasi'] ?? '',
            'namaPenanda' => $entry['namaPenanda'] ?? '',
            'hubunganPasien' => $entry['hubunganPasien'] ?? 'pasien',
            'dokterIgd' => $entry['dokterIgd'] ?? '',
            'dokterIgdCode' => $entry['dokterIgdCode'] ?? '',
            'dokterIgdDate' => $entry['dokterIgdDate'] ?? '',
        ];
        $this->signature = $entry['signature'] ?? '';
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-permintaan-rawat-inap-ugd');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->permintaanRawatInapList)->firstWhere('signatureDate', $key);
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
        $entry = collect($this->permintaanRawatInapList)->firstWhere('signatureDate', $key);
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
        $this->newForm['namaPenanda'] = $this->regName ?? '';
        $this->signature = '';
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();
        $this->incrementVersion('modal-permintaan-rawat-inap-ugd');
    }

    /** Layar formulir sedang tampil? Saat terkunci, formulir tak pernah dirender. */
    public function diForm(): bool
    {
        return !$this->isFormLocked && ($this->viewOnly || $this->editingKey !== null || $this->layar === 'form');
    }

    /** Buka formulir kosong untuk entri baru, Diagnosis terisi dari EMR UGD. */
    public function tambahEntri(): void
    {
        if ($this->isFormLocked || $this->disabled) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menambah entri.');
            return;
        }
        $this->cancelEdit();     // kosongkan formulir (sekaligus balik ke daftar)…
        $this->newForm['tglPermintaan'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->newForm['diagnosis'] = $this->diagnosisDariEmr($this->findDataUGD($this->rjNo) ?: []);
        $this->layar = 'form';   // …lalu naikkan formulirnya
    }

    /** Tutup formulir, kembali ke daftar entri. Formulir selalu ditinggalkan kosong. */
    public function kembaliKeDaftar(): void
    {
        $this->cancelEdit();
    }

    /* ===============================
     | CETAK
     =============================== */
    public function cetak(string $signatureDate)
    {
        $entry = collect($this->permintaanRawatInapList)->firstWhere('signatureDate', $signatureDate);
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

            $data = array_merge($pasien, [
                'form' => $entry,
                'identitasRs' => $identitasRs,
                'ttdDokterPath' => TtdUser::pathBerkasDariKode($entry['dokterIgdCode'] ?? null),
            ]);

            set_time_limit(300);

            $pdf = Pdf::loadView('pages.components.modul-dokumen.ugd.permintaan-rawat-inap.cetak-permintaan-rawat-inap-print', ['data' => $data])->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak surat permintaan rawat inap.');
            return response()->streamDownload(fn() => print $pdf->output(), 'permintaan-rawat-inap-ugd-' . ($pasien['regNo'] ?? $this->rjNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HAPUS
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
                $this->lockUGDRow($this->rjNo);

                $data = $this->findDataUGD($this->rjNo);
                if (empty($data) || !isset($data['permintaanRawatInapUGD'])) {
                    throw new \RuntimeException('Data formulir tidak ditemukan.');
                }

                $data['permintaanRawatInapUGD'] = collect($data['permintaanRawatInapUGD'])
                    ->reject(fn($item) => ($item['signatureDate'] ?? '') === $signatureDate)
                    ->values()
                    ->toArray();

                $this->updateJsonUGD($this->rjNo, $data);
                $this->muatDariDokumen($data);

                $this->appendAdminLogUGD((int) $this->rjNo, 'Hapus Surat Permintaan Rawat Inap UGD — ' . $signatureDate, 'MR');
            });

            $this->incrementVersion('modal-permintaan-rawat-inap-ugd');
            $this->dispatch('toast', type: 'success', message: 'Surat permintaan rawat inap berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BUKA KUNCI
     =============================== */
    public function bukaKunci(string $signatureDate): void
    {
        if (!auth()->user()?->can('dokumen.bukaKunci')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang membuka kunci.');
            return;
        }

        try {
            DB::transaction(function () use ($signatureDate) {
                $this->lockUGDRow($this->rjNo);

                $data = $this->findDataUGD($this->rjNo);
                if (empty($data) || !isset($data['permintaanRawatInapUGD'])) {
                    throw new \RuntimeException('Data formulir tidak ditemukan.');
                }

                $list = $data['permintaanRawatInapUGD'];
                $idx = collect($list)->search(fn($it) => ($it['signatureDate'] ?? '') === $signatureDate);
                if ($idx === false) {
                    throw new \RuntimeException('Entri tidak ditemukan.');
                }

                $list[$idx]['finalized'] = false;
                $list[$idx]['dokterIgd'] = '';
                $list[$idx]['dokterIgdCode'] = '';
                $list[$idx]['dokterIgdDate'] = '';

                $data['permintaanRawatInapUGD'] = $list;

                $this->updateJsonUGD($this->rjNo, $data);
                $this->muatDariDokumen($data);

                $pelaku = auth()->user()->myuser_name ?? auth()->user()->name ?? 'unknown';
                $this->appendAdminLogUGD((int) $this->rjNo, 'Buka Kunci Surat Permintaan Rawat Inap UGD — ' . $signatureDate . ' oleh ' . $pelaku, 'MR');
            });

            $this->incrementVersion('modal-permintaan-rawat-inap-ugd');
            $this->dispatch('toast', type: 'success', message: 'Kunci entri dibuka — TTD dokter IGD dicabut, TTD pasien/keluarga dipertahankan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuka kunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | RESET
     =============================== */
    private function resetNewForm(): void
    {
        $this->newForm = [
            'tglPermintaan' => '',
            'diagnosis' => '',
            'dpjpId' => '',
            'dpjpName' => '',
            'rencanaTindakan' => '',
            'indikasi' => '',
            'namaPenanda' => '',
            'hubunganPasien' => 'pasien',
            'dokterIgd' => '',
            'dokterIgdCode' => '',
            'dokterIgdDate' => '',
        ];
        $this->layar = 'daftar';   // mengosongkan formulir = kembali ke daftar
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->permintaanRawatInapList = [];
        $this->resetNewForm();
        $this->signature = '';
        $this->editingKey = null;
        $this->viewOnly = false;
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline) ══ --}}
    @php
        $permintaanRawatInapUrut = collect($permintaanRawatInapList)
            ->sortByDesc(fn($entri) => strtotime(strtr(($entri['tglPermintaan'] ?? '') ?: ($entri['signatureDate'] ?? ''), '/', '-')))
            ->values()
            ->all();
    @endphp

    <x-modul-dokumen.kartu judul="Surat Permintaan Rawat Inap"
        :jumlah="count($permintaanRawatInapList ?? [])"
        satuan="surat"
        :nonaktif="$disabled || !$rjNo">
        <x-slot:deskripsi>Permintaan dokter IGD agar pasien dirawat inap — diagnosis, DPJP, rencana tindakan, dan indikasi rawat inap, diketahui pasien/keluarga.</x-slot:deskripsi>
        <div class="overflow-x-auto rounded-2xl border border-hairline dark:border-gray-700">
            <table class="min-w-full text-sm">
                <thead class="bg-surface-card dark:bg-gray-800">
                    <tr class="text-xs font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                        <th class="px-3 py-2 border-b">Tanggal</th>
                        <th class="px-3 py-2 border-b">DPJP</th>
                        <th class="px-3 py-2 border-b">Dokter IGD</th>
                        <th class="px-3 py-2 border-b text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (array_slice($permintaanRawatInapUrut, 0, 3) as $entry)
                        <tr class="border-b border-hairline dark:border-gray-700">
                            <td class="px-3 py-2 text-muted dark:text-gray-400">{{ ($entry['tglPermintaan'] ?? '') ?: ($entry['signatureDate'] ?? '-') }}</td>
                            <td class="px-3 py-2 font-medium text-ink dark:text-gray-200">
                                {{ Str::limit(($entry['dpjpName'] ?? '') ?: '-', 50) }}
                            </td>
                            <td class="px-3 py-2 text-muted dark:text-gray-400">
                                <x-modul-dokumen.status-ttd :nama="$this->entryIsFinal($entry) ? ($entry['dokterIgd'] ?? '') : ''" gaya="polos" />
                            </td>
                            <td class="px-3 py-2 text-center">
                                <x-modul-dokumen.status-entri :final="$this->entryIsFinal($entry)" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-6 text-center text-muted-soft">Belum ada data tersimpan</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-modul-dokumen.kartu>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="rm-permintaan-rawat-inap-ugd-{{ $rjNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-full"
            wire:key="{{ $this->renderKey('modal-permintaan-rawat-inap-ugd', [$rjNo ?? 'new']) }}">
            <x-modul-dokumen.header judul="Surat Permintaan Rawat Inap"
                ikon="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                jalur="UGD" :jumlah="count($permintaanRawatInapList ?? [])" :readOnly="$isFormLocked">
                Permintaan dokter IGD agar pasien dirawat inap, diketahui pasien/keluarga
            </x-modul-dokumen.header>

            {{-- DISPLAY PASIEN — paling atas, mengikuti pola EMR --}}
            <div class="px-4 pt-2">
                <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                    wire:key="pri-ugd-display-pasien-{{ $rjNo ?? 'init' }}" />
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    <div
                        class="{{ $this->diForm() ? 'p-6 sm:p-8 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700' : '' }} space-y-6">

                        @php $formReadOnly = $isFormLocked || $viewOnly; @endphp

                        @if ($isFormLocked)
                            <x-modul-dokumen.banner jenis="terkunci" />
                        @endif

                        @if ($viewOnly)
                            <x-modul-dokumen.banner jenis="lihat" />
                        @elseif ($editingKey && !$isFormLocked)
                            <x-modul-dokumen.banner jenis="lanjut" />
                        @endif

                        @if ($this->diForm())
                        {{-- ══ TANGGAL & DPJP ══ --}}
                        <section>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Tanggal / Jam Permintaan *" class="mb-1" />
                                    <div class="flex items-center gap-2">
                                        <x-text-input wire:model.live="newForm.tglPermintaan" placeholder="dd/mm/yyyy HH:mm:ss"
                                            :error="$errors->has('newForm.tglPermintaan')" :disabled="$formReadOnly"
                                            class="w-full" />
                                        @if (!$formReadOnly)
                                            <x-now-button wire:click="setNow('tglPermintaan')" :disabled="$formReadOnly" />
                                        @endif
                                    </div>
                                    <x-input-error :messages="$errors->get('newForm.tglPermintaan')" class="mt-1" />
                                </div>
                                <div>
                                    <livewire:lov.dokter.lov-dokter target="dpjp-permintaan-rawat-inap-ugd" label="DPJP Rawat Inap *"
                                        placeholder="Ketik nama/kode dokter..." :initialDrId="$newForm['dpjpId'] ?: null"
                                        :disabled="$formReadOnly"
                                        wire:key="lov-dpjp-pri-ugd-{{ $rjNo }}-{{ $renderVersions['modal-permintaan-rawat-inap-ugd'] ?? 0 }}" />
                                    <x-input-error :messages="$errors->get('newForm.dpjpId')" class="mt-1" />
                                </div>
                            </div>
                        </section>

                        {{-- ══ DIAGNOSIS, RENCANA TINDAKAN, INDIKASI ══ --}}
                        <section class="pt-6 border-t border-hairline dark:border-gray-700">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <x-input-label value="Diagnosis *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.diagnosis" :error="$errors->has('newForm.diagnosis')" rows="4"
                                        placeholder="Terisi dari diagnosis EMR UGD, boleh disunting..."
                                        :disabled="$formReadOnly" class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.diagnosis')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Rencana Tindakan *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.rencanaTindakan" :error="$errors->has('newForm.rencanaTindakan')" rows="4"
                                        placeholder="Mis. MRS, observasi, rencana operasi..."
                                        :disabled="$formReadOnly" class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.rencanaTindakan')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Alasan / Indikasi Rawat Inap *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.indikasi" :error="$errors->has('newForm.indikasi')" rows="4"
                                        placeholder="Mis. observasi ketat, terapi parenteral..."
                                        :disabled="$formReadOnly" class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.indikasi')" class="mt-1" />
                                </div>
                            </div>
                        </section>

                        {{-- ══ TANDA TANGAN ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                                Tanda Tangan
                            </h3>

                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                {{-- Pasien / Keluarga (Mengetahui) --}}
                                <div class="flex flex-col">
                                    <div
                                        class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                                        Mengetahui, Pasien / Keluarga
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

                                    <div class="mt-3">
                                        <x-input-label value="Nama Pasien / Keluarga *" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.namaPenanda" :error="$errors->has('newForm.namaPenanda')"
                                            placeholder="Nama penanda tangan..." :disabled="$formReadOnly"
                                            class="w-full" />
                                        <x-input-error :messages="$errors->get('newForm.namaPenanda')" class="mt-1" />
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

                                {{-- Dokter IGD --}}
                                <div class="flex flex-col">
                                    <div
                                        class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                                        Dokter IGD
                                    </div>
                                    @if (empty($newForm['dokterIgd']))
                                        @if (!$formReadOnly)
                                            <div
                                                class="flex flex-col items-center justify-center flex-1 gap-2 p-6 border-2 border-dashed rounded-xl {{ $errors->has('newForm.dokterIgd') ? 'border-red-400' : 'border-gray-300 dark:border-gray-700' }}">
                                                <x-primary-button wire:click.prevent="ttdDokterIgd"
                                                    wire:loading.attr="disabled" wire:target="ttdDokterIgd"
                                                    class="gap-2">
                                                    <span wire:loading.remove wire:target="ttdDokterIgd"
                                                        class="flex items-center gap-1.5">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z" />
                                                        </svg>
                                                        TTD Dokter IGD &amp; Kunci
                                                    </span>
                                                    <span wire:loading wire:target="ttdDokterIgd">
                                                        <x-loading class="w-4 h-4" /> Mengunci...
                                                    </span>
                                                </x-primary-button>
                                                <p class="text-xs text-center text-muted">Menandatangani = validasi &amp; mengunci surat ini. Hanya untuk dokter.</p>
                                            </div>
                                            <x-input-error :messages="$errors->get('newForm.dokterIgd')" class="mt-1" />
                                        @else
                                            <p class="py-8 text-base italic text-center text-muted-soft">Belum
                                                ditandatangani.</p>
                                        @endif
                                    @else
                                        <x-signature.ttd-petugas :framed="false" :ttd="$newForm['dokterIgd']" :code="$newForm['dokterIgdCode'] ?? ''"
                                            :date="$newForm['dokterIgdDate'] ?? ''" :locked="true" nameLabel="Nama Dokter IGD" />
                                    @endif
                                </div>
                            </div>
                        </section>
                        @endif

                        {{-- ══ DAFTAR TERSIMPAN ══ --}}
                        @unless ($this->diForm())
                            <x-modul-dokumen.tabel-daftar :kolom="['', 'Tanggal', 'DPJP', 'Dokter IGD (TTD)', 'Status' => 'text-center', 'Aksi' => 'text-center']">
                                    @forelse ($permintaanRawatInapUrut as $entry)
                                        @php
                                            $entry = array_replace([
                                                'tglPermintaan' => '', 'diagnosis' => '', 'dpjpId' => '', 'dpjpName' => '',
                                                'rencanaTindakan' => '', 'indikasi' => '', 'namaPenanda' => '', 'hubunganPasien' => '',
                                                'dokterIgd' => '', 'dokterIgdCode' => '', 'dokterIgdDate' => '',
                                                'signature' => '', 'signatureDate' => '',
                                            ], $entry);
                                            $isFinal = $this->entryIsFinal($entry);
                                            $rowKey = $entry['signatureDate'] ?? '';
                                            $hubLabel = collect($hubunganPasienOptions)->firstWhere('value', $entry['hubunganPasien'] ?? '')['label'] ?? ($entry['hubunganPasien'] ?? '');
                                        @endphp
                                        <tbody x-data="{ open: false }" class="border-b border-hairline dark:border-gray-700">
                                            <tr @click="open = !open"
                                                class="cursor-pointer hover:bg-surface-soft dark:hover:bg-gray-800 {{ $editingKey && $editingKey === $rowKey ? 'bg-brand-lime/10 dark:bg-brand-lime/5' : '' }}">
                                                <td class="px-2 py-3 text-center align-middle">
                                                    <svg class="w-4 h-4 mx-auto text-muted transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </td>
                                                <td class="px-4 py-3 align-middle text-sm tabular-nums text-muted dark:text-gray-400">
                                                    {{ $entry['tglPermintaan'] ?: ($rowKey ?: '-') }}
                                                </td>
                                                <td class="px-4 py-3 align-middle font-semibold text-ink dark:text-gray-100">
                                                    {{ Str::limit($entry['dpjpName'] ?: '(DPJP belum dipilih)', 50) }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    <x-modul-dokumen.status-ttd :nama="$isFinal ? $entry['dokterIgd'] : ''" />
                                                </td>
                                                <td class="px-4 py-3 align-middle text-center">
                                                    <x-modul-dokumen.status-entri :final="$isFinal" />
                                                </td>
                                                <td class="px-4 py-3 align-middle text-center whitespace-nowrap" @click.stop>
                                                    <x-modul-dokumen.aksi-entri kunci="{{ $rowKey }}" :final="$isFinal" :terkunci="$isFormLocked"
                                                        :cetakHanyaFinal="true"
                                                        pesanBukaKunci="Yakin buka kunci entri ini? TTD dokter IGD akan dicabut."
                                                        konfirmasiHapus="Yakin hapus surat permintaan rawat inap ini?" />
                                                </td>
                                            </tr>

                                            {{-- DETAIL (expand) --}}
                                            <tr x-show="open" x-cloak>
                                                <td colspan="6" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                                    <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Diagnosis</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['diagnosis'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Rencana Tindakan</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['rencanaTindakan'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Alasan / Indikasi Rawat Inap</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['indikasi'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Pasien / Keluarga</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['namaPenanda'] ?: '-' }}@if ($hubLabel) <span class="text-muted">({{ $hubLabel }})</span>@endif</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">TTD Pasien / Keluarga</dt>
                                                            <dd class="mt-0.5">
                                                                <x-modul-dokumen.status-ttd :sudah="!empty($entry['signature'])" :waktu="$entry['signatureDate'] ?? '-'" gaya="biasa" />
                                                            </dd>
                                                        </div>
                                                    </dl>
                                                </td>
                                            </tr>
                                        </tbody>
                                    @empty
                                        <x-modul-dokumen.baris-kosong :kolom="6" />
                                    @endforelse
                            </x-modul-dokumen.tabel-daftar>
                        @endunless

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <x-modul-dokumen.footer :formulir="$this->diForm()" :terkunci="$isFormLocked"
                :lihat="$viewOnly"
                :bisaSimpan="$rjNo && !$isFormLocked"
                :mengedit="$editingKey">
                Simpan draft dulu, lalu <strong>kunci</strong> lewat tombol <strong>TTD Dokter IGD &amp; Kunci</strong> di kolom Dokter IGD.
            </x-modul-dokumen.footer>

        </div>
    </x-modal>
</div>
