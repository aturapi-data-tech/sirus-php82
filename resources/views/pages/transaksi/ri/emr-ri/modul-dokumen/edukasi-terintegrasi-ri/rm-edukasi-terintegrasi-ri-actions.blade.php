<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/edukasi-terintegrasi-ri/rm-edukasi-terintegrasi-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use App\Support\EdukasiTerintegrasiOptions;
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

    // Signature dari <x-signature.signature-pad /> (TTD gambar pasien/keluarga)
    public string $sasaranEdukasiSignature = '';

    public array $form = [];

    // Kunci entri yang sedang diedit (= id entri). null = membuat entri baru.
    public ?string $editingKey = null;

    // true = entri terkunci ditampilkan di form dalam mode read-only.
    public bool $viewOnly = false;

    // Daftar opsi (key => label) — satu sumber di App\Support\EdukasiTerintegrasiOptions,
    // diisi saat mount; dipakai render checkbox / radio + ringkasan riwayat.
    public array $tujuanList = [];
    public array $kebutuhanList = [];
    public array $metodeList = [];
    public array $prefList = [];
    public array $hasilList = [];
    public array $rujukList = [];
    public array $hubunganOptions = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-edukasi-terintegrasi-ri'];

    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo  = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-edukasi-terintegrasi-ri']);

        $this->tujuanList      = EdukasiTerintegrasiOptions::tujuan();
        $this->kebutuhanList   = EdukasiTerintegrasiOptions::kebutuhan();
        $this->metodeList      = EdukasiTerintegrasiOptions::metode();
        $this->prefList        = EdukasiTerintegrasiOptions::preferensi();
        $this->hasilList       = EdukasiTerintegrasiOptions::hasil();
        $this->rujukList       = EdukasiTerintegrasiOptions::rujuk();
        $this->hubunganOptions = EdukasiTerintegrasiOptions::hubungan();

        $this->form = $this->defaultForm();
        $this->prefillHeader();

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->dataDaftarRi['edukasiPasienTerintegrasi'] ??= [];
                $this->form['sasaran']['nama'] = $data['regName'] ?? '';
                $this->form['ttd']['pasienKeluargaNama'] = $data['regName'] ?? '';
                $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $disabled;
            }
        }
    }

    public function openModal(): void
    {
        if (!$this->riHdrNo || $this->disabled) {
            return;
        }

        $data = $this->findDataRI($this->riHdrNo);
        if ($data) {
            $this->dataDaftarRi = $data;
            $this->regNo = $data['regNo'] ?? $this->regNo;
            $this->dataDaftarRi['edukasiPasienTerintegrasi'] ??= [];
            $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;
        }

        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetFormEdukasi();

        $this->dispatch('open-modal', name: "rm-edukasi-terintegrasi-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-edukasi-terintegrasi-ri-{$this->riHdrNo}");
    }

    private function defaultForm(): array
    {
        return [
            'tglEdukasi'       => '',
            'pemberiInformasi' => ['petugasCode' => '', 'petugasName' => ''],

            // Sasaran = PENERIMA edukasi; bisa berbeda dari penanda tangan (form.ttd.*).
            'sasaran' => ['nama' => '', 'hubungan' => 'pasien'],

            'tujuan'      => ['opsi' => [], 'lainnya' => ''],

            'evaluasiAwal' => [
                'literasi'              => null,
                'bahasaAtauPendidikan'  => '',
                'hambatanEmosional'         => ['ada' => null, 'keterangan' => ''],
                'keterbatasanFisikKognitif' => ['ada' => null, 'keterangan' => ''],
                'nilaiKeyakinanBudaya'      => ['ada' => null, 'deskripsi' => ''],
                'preferensiInformasi'       => ['opsi' => [], 'lainnya' => ''],
            ],

            'kebutuhan'   => ['opsi' => [], 'lainnya' => ''],
            'materi'      => ['topik' => '', 'keterangan' => ''],
            'metodeMedia' => ['opsi' => [], 'lainnya' => ''],

            'hasil' => [
                'paham'             => ['ya' => null, 'keterangan' => ''],
                'mampuMengulang'    => ['ya' => null, 'keterangan' => ''],
                'tunjukkanSkill'    => ['ya' => null, 'keterangan' => ''],
                'sesuaiNilai'       => ['ya' => null, 'keterangan' => ''],
                'perluEdukasiUlang' => ['ya' => null, 'keterangan' => ''],
            ],

            'tindakLanjut' => [
                'edukasiLanjutanTanggal'    => '',
                'edukasiLanjutanKeterangan' => '',
                'dirujukKe'              => [],
                // Default true = belum perlu tindak lanjut; toggle di UI dibalik jadi
                // "Perlu tindak lanjut" (dicentang → false → field tanggal & rujukan tampil).
                'tidakPerluTL'           => true,
            ],

            'ttd' => [
                'pasienKeluargaNama' => '',
                'pasienKeluargaHubungan' => 'pasien',
                'pasienKeluargaTTD'  => '',
            ],
        ];
    }

    private function prefillHeader(): void
    {
        // Petugas TIDAK di-prefill — nama & kode distempel saat TTD Petugas & Kunci (ttdPetugas()).
        if (empty($this->form['tglEdukasi'])) {
            $this->form['tglEdukasi'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        }
    }

    public function setTglEdukasi(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->form['tglEdukasi'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function setEdukasiLanjutanToday(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->form['tindakLanjut']['edukasiLanjutanTanggal']
            = Carbon::now(config('app.timezone'))->format('d/m/Y');
    }

    public function setSasaranSignature(string $dataUrl): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->sasaranEdukasiSignature = $dataUrl;
        $this->incrementVersion('modal-edukasi-terintegrasi-ri');
    }

    public function clearSasaranSignature(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->sasaranEdukasiSignature = '';
        $this->form['ttd']['pasienKeluargaTTD'] = '';
        $this->incrementVersion('modal-edukasi-terintegrasi-ri');
    }

    /* ===============================
     | HELPER — status & bentuk entri
     =============================== */
    // Entri dianggap FINAL/terkunci bila flag finalized true; entri lama (tanpa flag)
    // yang sudah ada TTD gambar pasien dianggap final (kompatibilitas data lama).
    public function entryIsFinal(array $entri): bool
    {
        return array_key_exists('finalized', $entri)
            ? (bool) $entri['finalized']
            : !empty(data_get($entri, 'form.ttd.pasienKeluargaTTD'));
    }

    // Susun array entri dari state form. Pertahankan created_at/created_by saat edit.
    private function buildEntry(string $edukasiId, bool $finalized): array
    {
        $this->normalizeBooleansOnForm();

        $form = $this->form;
        if (!empty($this->sasaranEdukasiSignature)) {
            $form['ttd']['pasienKeluargaTTD'] = $this->sasaranEdukasiSignature;
        }

        $existing = collect($this->dataDaftarRi['edukasiPasienTerintegrasi'] ?? [])->firstWhere('id', $edukasiId);
        $createdAt = $existing['created_at'] ?? Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s');
        $createdBy = $existing['created_by'] ?? [
            'code' => auth()->user()->myuser_code ?? '',
            'name' => auth()->user()->myuser_name ?? '',
        ];

        return [
            'id'         => $edukasiId,
            'created_at' => $createdAt,
            'created_by' => $createdBy,
            'form'       => $form,
            'finalized'  => $finalized,
        ];
    }

    // Simpan entri (add/update by $edukasiId) dengan status $finalized. Dipakai draft & kunci.
    private function persistEntry(string $edukasiId, bool $finalized, string $logVerb): void
    {
        $entry = $this->buildEntry($edukasiId, $finalized);

        DB::transaction(function () use ($entry, $edukasiId, $logVerb) {
            $this->lockRIRow($this->riHdrNo);

            $fresh = $this->findDataRI($this->riHdrNo) ?? [];
            if (!isset($fresh['edukasiPasienTerintegrasi']) || !is_array($fresh['edukasiPasienTerintegrasi'])) {
                $fresh['edukasiPasienTerintegrasi'] = [];
            }

            $list = $fresh['edukasiPasienTerintegrasi'];
            $index = collect($list)->search(fn($entri) => ($entri['id'] ?? null) === $edukasiId);
            if ($index === false) {
                $list[] = $entry;
            } else {
                if ($this->entryIsFinal($list[$index])) {
                    throw new \RuntimeException('Entri sudah terkunci, tidak dapat diubah.');
                }
                $list[$index] = $entry;
            }
            $fresh['edukasiPasienTerintegrasi'] = array_values($list);

            $this->updateJsonRI((int) $this->riHdrNo, $fresh);
            $this->dataDaftarRi = $fresh;

            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Edukasi Terintegrasi — entri ' . ($entry['form']['tglEdukasi'] ?? '-'), 'MR');
        });
    }

    /* ===============================
     | VALIDATION RULES (dipakai ttdPetugas)
     =============================== */
    private function edukasiRules(): array
    {
        $rules = [
            // HEADER
            'form.tglEdukasi'                   => 'required|date_format:d/m/Y H:i:s',
            'form.pemberiInformasi.petugasCode' => 'required|string|max:50',
            'form.pemberiInformasi.petugasName' => 'required|string|max:250',
            'form.sasaran.nama'                 => 'required|string|max:150',
            'form.sasaran.hubungan'             => 'required|string|max:50',

            // 1) Tujuan
            'form.tujuan.opsi'    => 'nullable|array',
            'form.tujuan.opsi.*'  => 'in:' . implode(',', array_keys(EdukasiTerintegrasiOptions::tujuan())),
            'form.tujuan.lainnya' => 'nullable|string|max:200',

            // 2) Evaluasi Awal
            'form.evaluasiAwal.literasi'                              => 'nullable|in:Baik,Cukup,Kurang',
            'form.evaluasiAwal.bahasaAtauPendidikan'                  => 'nullable|string|max:200',
            'form.evaluasiAwal.hambatanEmosional.ada'                 => 'nullable|boolean',
            'form.evaluasiAwal.hambatanEmosional.keterangan'          => 'nullable|string|max:300',
            'form.evaluasiAwal.keterbatasanFisikKognitif.ada'         => 'nullable|boolean',
            'form.evaluasiAwal.keterbatasanFisikKognitif.keterangan'  => 'nullable|string|max:300',
            'form.evaluasiAwal.nilaiKeyakinanBudaya.ada'              => 'nullable|boolean',
            'form.evaluasiAwal.nilaiKeyakinanBudaya.deskripsi'        => 'nullable|string|max:500',
            'form.evaluasiAwal.preferensiInformasi.opsi'              => 'nullable|array',
            'form.evaluasiAwal.preferensiInformasi.opsi.*'            => 'in:' . implode(',', array_keys(EdukasiTerintegrasiOptions::preferensi())),
            'form.evaluasiAwal.preferensiInformasi.lainnya'           => 'nullable|string|max:200',

            // 3) Kebutuhan + materi/topik (gabungan dari form Edukasi Pasien lama)
            'form.kebutuhan.opsi'    => 'nullable|array',
            'form.kebutuhan.opsi.*'  => 'in:' . implode(',', array_keys(EdukasiTerintegrasiOptions::kebutuhan())),
            'form.kebutuhan.lainnya' => 'nullable|string|max:200',
            'form.materi.topik'      => 'required|string|max:150',
            'form.materi.keterangan' => 'nullable|string|max:500',

            // 4) Metode & Media
            'form.metodeMedia.opsi'    => 'nullable|array',
            'form.metodeMedia.opsi.*'  => 'in:' . implode(',', array_keys(EdukasiTerintegrasiOptions::metode())),
            'form.metodeMedia.lainnya' => 'nullable|string|max:200',

            // 5) Hasil
            'form.hasil.*.ya'         => 'nullable|boolean',
            'form.hasil.*.keterangan' => 'nullable|string|max:300',

            // 6) Tindak Lanjut
            'form.tindakLanjut.edukasiLanjutanTanggal'    => 'nullable|date_format:d/m/Y',
            'form.tindakLanjut.edukasiLanjutanKeterangan' => 'nullable|string|max:200',
            'form.tindakLanjut.dirujukKe'              => 'nullable|array',
            'form.tindakLanjut.dirujukKe.*'            => 'string|max:50',
            'form.tindakLanjut.tidakPerluTL'           => 'boolean',

            // 7) TTD
            'form.ttd.pasienKeluargaNama'     => 'required|string|max:150',
            'form.ttd.pasienKeluargaHubungan' => 'required|string|max:50',
        ];

        // Conditional: "lainnya" wajib diisi kalau di-check
        if (in_array('lainnya', $this->form['tujuan']['opsi'] ?? [], true)) {
            $rules['form.tujuan.lainnya'] = 'required|string|max:200';
        }
        if (in_array('lainnya', $this->form['kebutuhan']['opsi'] ?? [], true)) {
            $rules['form.kebutuhan.lainnya'] = 'required|string|max:200';
        }
        if (in_array('lainnya', $this->form['metodeMedia']['opsi'] ?? [], true)) {
            $rules['form.metodeMedia.lainnya'] = 'required|string|max:200';
        }
        if (in_array('lainnya', $this->form['evaluasiAwal']['preferensiInformasi']['opsi'] ?? [], true)) {
            $rules['form.evaluasiAwal.preferensiInformasi.lainnya'] = 'required|string|max:200';
        }

        $attributes = [
            'form.tglEdukasi'                   => 'Tanggal edukasi',
            'form.pemberiInformasi.petugasCode' => 'Kode petugas',
            'form.pemberiInformasi.petugasName' => 'Nama petugas',
            'form.tujuan.lainnya'               => 'Tujuan (lainnya)',
            'form.kebutuhan.lainnya'            => 'Kebutuhan (lainnya)',
            'form.materi.topik'                 => 'Materi / topik edukasi',
            'form.materi.keterangan'            => 'Keterangan edukasi',
            'form.metodeMedia.lainnya'          => 'Metode/media (lainnya)',
            'form.evaluasiAwal.preferensiInformasi.lainnya' => 'Preferensi (lainnya)',
            'form.sasaran.nama'                 => 'Sasaran edukasi (nama penerima)',
            'form.sasaran.hubungan'             => 'Hubungan sasaran dengan pasien',
            'form.ttd.pasienKeluargaNama'       => 'Nama penanda tangan',
            'form.ttd.pasienKeluargaHubungan'   => 'Hubungan penanda tangan dengan pasien',
        ];

        $messages = [
            'required'    => ':attribute wajib diisi.',
            'string'      => ':attribute harus berupa teks.',
            'array'       => ':attribute harus berupa daftar.',
            'boolean'     => ':attribute harus bernilai ya/tidak.',
            'in'          => ':attribute berisi nilai yang tidak valid.',
            'max.string'  => ':attribute maksimal :max karakter.',
            'date_format' => 'Format :attribute tidak sesuai (harus :format).',
        ];

        return [$rules, $messages, $attributes];
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

        if (empty($this->form['tglEdukasi'])) {
            $this->form['tglEdukasi'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        }

        $edukasiId = $this->editingKey ?: (string) Str::uuid();

        try {
            $this->persistEntry($edukasiId, false, 'Simpan draft');
            $this->editingKey = $edukasiId; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-edukasi-terintegrasi-ri');
            $this->dispatch('toast', type: 'success', message: 'Draft tersimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TTD PETUGAS & KUNCI = FINALIZE
     | Stempel nama+kode petugas (user login) → validasi lengkap → kunci entri.
     | Aksi TERAKHIR — tidak ada tombol "Simpan & Kunci" terpisah (aturan modul dokumen).
     =============================== */
    public function ttdPetugas(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        if (empty($this->form['tglEdukasi'])) {
            $this->form['tglEdukasi'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        }

        // TTD gambar pasien/keluarga wajib sebelum mengunci
        if (empty($this->sasaranEdukasiSignature) && empty(data_get($this->form, 'ttd.pasienKeluargaTTD'))) {
            $this->dispatch('toast', type: 'error', message: 'TTD pasien/keluarga wajib sebelum mengunci.');
            return;
        }
        if (!empty($this->sasaranEdukasiSignature)) {
            $this->form['ttd']['pasienKeluargaTTD'] = $this->sasaranEdukasiSignature;
        }

        // Stempel TTD petugas = user login.
        $this->form['pemberiInformasi']['petugasName'] = auth()->user()->myuser_name ?? '';
        $this->form['pemberiInformasi']['petugasCode'] = auth()->user()->myuser_code ?? '';

        $this->normalizeBooleansOnForm();

        [$rules, $messages, $attributes] = $this->edukasiRules();
        $this->validateWithToast($rules, $messages, $attributes);

        $edukasiId = $this->editingKey ?: (string) Str::uuid();

        try {
            $this->persistEntry($edukasiId, true, 'Kunci (TTD)');
            $this->resetFormEdukasi();
            $this->editingKey = null;
            $this->viewOnly = false;
            $this->dispatch('toast', type: 'success', message: 'Edukasi ditandatangani & terkunci.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BUKA KUNCI (unlock) — Gate dokumen.bukaKunci.
     | Mencabut kunci + TTD PETUGAS saja; TTD pasien/keluarga DIPERTAHANKAN,
     | entri kembali jadi draft untuk dikoreksi lalu dikunci ulang oleh petugas.
     =============================== */
    public function bukaKunci(string $edukasiId): void
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
            DB::transaction(function () use ($edukasiId) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $list = $fresh['edukasiPasienTerintegrasi'] ?? [];
                $index = collect($list)->search(fn($entri) => ($entri['id'] ?? null) === $edukasiId);
                if ($index === false) {
                    throw new \RuntimeException('Entri tidak ditemukan.');
                }

                // Cabut kunci + TTD petugas; TTD pasien/keluarga tetap.
                $list[$index]['finalized'] = false;
                $list[$index]['form']['pemberiInformasi']['petugasName'] = '';
                $list[$index]['form']['pemberiInformasi']['petugasCode'] = '';

                $fresh['edukasiPasienTerintegrasi'] = array_values($list);
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;

                $this->appendAdminLogRI(
                    (int) $this->riHdrNo,
                    'Buka kunci Edukasi Terintegrasi — entri ' . ($list[$index]['form']['tglEdukasi'] ?? $edukasiId)
                        . ' (oleh ' . (auth()->user()->myuser_name ?? auth()->user()->name ?? '-') . ')',
                    'MR',
                );
            });

            $this->incrementVersion('modal-edukasi-terintegrasi-ri');
            $this->dispatch('toast', type: 'success', message: 'Kunci dibuka — entri kembali draft & TTD petugas dicabut. Silakan koreksi lalu kunci ulang.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuka kunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | EDIT / LIHAT / BATAL
     =============================== */
    private function hydrateFormFromEntry(array $entri): void
    {
        // array_replace_recursive menjaga agar key nested yang hilang di data lama tetap ada
        $this->form = array_replace_recursive($this->defaultForm(), $entri['form'] ?? []);
        $this->sasaranEdukasiSignature = (string) data_get($entri, 'form.ttd.pasienKeluargaTTD', '');
        $this->editingKey = $entri['id'] ?? null;
        $this->resetValidation();
        $this->incrementVersion('modal-edukasi-terintegrasi-ri');
    }

    public function editEntry(string $edukasiId): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }
        $entri = collect($this->dataDaftarRi['edukasiPasienTerintegrasi'] ?? [])->firstWhere('id', $edukasiId);
        if (!$entri) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }
        if ($this->entryIsFinal($entri)) {
            $this->dispatch('toast', type: 'warning', message: 'Entri sudah terkunci, tidak dapat diedit.');
            return;
        }

        $this->viewOnly = false;
        $this->hydrateFormFromEntry($entri);
        $this->dispatch('toast', type: 'info', message: 'Draft dimuat untuk dilanjutkan.');
    }

    public function viewEntry(string $edukasiId): void
    {
        $entri = collect($this->dataDaftarRi['edukasiPasienTerintegrasi'] ?? [])->firstWhere('id', $edukasiId);
        if (!$entri) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }

        $this->viewOnly = true;
        $this->hydrateFormFromEntry($entri);
        $this->dispatch('toast', type: 'info', message: 'Menampilkan entri terkunci (hanya lihat).');
    }

    public function cancelEdit(): void
    {
        $this->resetFormEdukasi();
        $this->editingKey = null;
        $this->viewOnly = false;
    }

    public function removeEdukasiTerintegrasiById(string $edukasiId): void
    {
        if (!auth()->user()?->can('dokumen.hapus')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang menghapus entri.');
            return;
        }
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        try {
            DB::transaction(function () use ($edukasiId) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $list  = $fresh['edukasiPasienTerintegrasi'] ?? [];

                $deletedRow = collect($list)->firstWhere('id', $edukasiId);
                $newList = array_values(array_filter($list, fn($entri) => ($entri['id'] ?? null) !== $edukasiId));
                if (count($newList) === count($list)) {
                    throw new \RuntimeException('Data tidak ditemukan atau sudah dihapus.');
                }

                $fresh['edukasiPasienTerintegrasi'] = $newList;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Edukasi Terintegrasi — entri ' . ($deletedRow['form']['tglEdukasi'] ?? '-'), 'MR');
            });

            // bila entri yang dihapus sedang dibuka di form, kosongkan form
            if ($this->editingKey === $edukasiId) {
                $this->cancelEdit();
            }
            $this->afterSave('Data edukasi berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function cetak(string $edukasiId)
    {
        $list = $this->dataDaftarRi['edukasiPasienTerintegrasi'] ?? [];
        $entry = collect($list)->firstWhere('id', $edukasiId);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data edukasi tidak ditemukan.');
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

            // TTD petugas pemberi edukasi (dari created_by.code -> users.myuser_ttd_image)
            $ttdPetugasPath = null;
            $petugasCode = $entry['form']['pemberiInformasi']['petugasCode'] ?? ($entry['created_by']['code'] ?? null);
            if ($petugasCode) {
                $ttdPath = DB::table('users')->where('myuser_code', $petugasCode)->value('myuser_ttd_image');
                if (!empty($ttdPath) && file_exists(public_path('storage/' . $ttdPath))) {
                    $ttdPetugasPath = public_path('storage/' . $ttdPath);
                }
            }

            $data = array_merge($pasien, [
                'dataRi' => $this->dataDaftarRi,
                'entry' => $entry,
                'identitasRs' => $identitasRs,
                'ttdPetugasPath' => $ttdPetugasPath,
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);

            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.edukasi-terintegrasi.cetak-edukasi-terintegrasi-ri-print', ['data' => $data])->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak Edukasi Terintegrasi.');
            return response()->streamDownload(fn() => print $pdf->output(), 'edukasi-terintegrasi-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

    // Toggle "Perlu tindak lanjut" (kebalikan flag tersimpan tidakPerluTL —
    // key JSON dipertahankan supaya cetak/riwayat/data lama tidak berubah makna).
    public function togglePerluTindakLanjut(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->form['tindakLanjut']['tidakPerluTL'] = !filter_var($this->form['tindakLanjut']['tidakPerluTL'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    // Checklist Kebutuhan Edukasi difilter mengikuti Tujuan Edukasi terpilih
    // (tanpa tujuan = semua opsi; yang sudah dicentang selalu ikut tampil).
    public function kebutuhanTampil(): array
    {
        return EdukasiTerintegrasiOptions::kebutuhanTampil(
            (array) ($this->form['tujuan']['opsi'] ?? []),
            (array) ($this->form['kebutuhan']['opsi'] ?? []),
        );
    }

    /**
     * Toggle membership di array multi-pilih (untuk x-toggle group).
     * $fullPath: path lengkap mulai dari property root, mis. "form.tujuan.opsi".
     */
    public function toggleArrayOpt(string $fullPath, string $opt): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $current = (array) data_get($this, $fullPath, []);
        if (in_array($opt, $current, true)) {
            $current = array_values(array_filter($current, fn($nilai) => $nilai !== $opt));
        } else {
            $current[] = $opt;
        }
        data_set($this, $fullPath, $current);
    }

    public function resetFormEdukasi(): void
    {
        $this->form = $this->defaultForm();
        $this->form['sasaran']['nama'] = $this->dataDaftarRi['regName'] ?? '';
        $this->form['ttd']['pasienKeluargaNama'] = $this->dataDaftarRi['regName'] ?? '';
        $this->prefillHeader();
        $this->sasaranEdukasiSignature = '';
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();
        $this->incrementVersion('modal-edukasi-terintegrasi-ri');
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-edukasi-terintegrasi-ri');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    private function normalizeBooleansOnForm(): void
    {
        $formData = &$this->form;

        foreach (['hambatanEmosional', 'keterbatasanFisikKognitif', 'nilaiKeyakinanBudaya'] as $key) {
            if (array_key_exists('ada', $formData['evaluasiAwal'][$key] ?? [])) {
                $formData['evaluasiAwal'][$key]['ada'] = filter_var(
                    $formData['evaluasiAwal'][$key]['ada'],
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                );
            }
        }

        if (isset($formData['hasil'])) {
            foreach ($formData['hasil'] as &$hasilItem) {
                if (array_key_exists('ya', $hasilItem)) {
                    $hasilItem['ya'] = filter_var($hasilItem['ya'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }
            }
            unset($hasilItem);
        }

        if (isset($formData['tindakLanjut']['tidakPerluTL'])) {
            $formData['tindakLanjut']['tidakPerluTL'] = (bool) $formData['tindakLanjut']['tidakPerluTL'];
        }
    }
};
?>

<div>
    {{-- RINGKASAN + TOMBOL (pola General Consent) --}}
    @php $jumlahEdukasiTerintegrasi = count($dataDaftarRi['edukasiPasienTerintegrasi'] ?? []); @endphp
    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-2">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Edukasi Terintegrasi</h3>
                    @if ($jumlahEdukasiTerintegrasi > 0)
                        <x-badge variant="success">{{ $jumlahEdukasiTerintegrasi }} entri</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-base text-muted dark:text-gray-400">
                    Pemberian informasi &amp; edukasi pasien/keluarga — satu formulir terintegrasi antar-PPA
                    (dokter, perawat, gizi, farmasi, dll.), menggantikan form Edukasi Pasien lama.
                </p>
                @if (count($dataDaftarRi['edukasiPasien'] ?? []) > 0)
                    <p class="text-sm text-muted-soft">
                        + {{ count($dataDaftarRi['edukasiPasien']) }} entri form Edukasi Pasien lama — lihat &amp; cetak lewat display Rekam Medis.
                    </p>
                @endif
            </div>
            <div class="flex shrink-0">
                <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                    wire:target="openModal" :disabled="!$riHdrNo" class="gap-2">
                    <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        Buka Edukasi Terintegrasi
                    </span>
                    <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                        <x-loading class="w-4 h-4" /> Memuat...
                    </span>
                </x-primary-button>
            </div>
        </div>
    </div>

    {{-- MODAL FORM --}}
    <x-modal name="rm-edukasi-terintegrasi-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]">
            {{-- HEADER MODAL --}}
            <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-hairline bg-surface-soft dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-semibold text-ink dark:text-gray-100">Edukasi Terintegrasi</h2>
                    @if ($jumlahEdukasiTerintegrasi > 0)
                        <x-badge variant="info">{{ $jumlahEdukasiTerintegrasi }} tersimpan</x-badge>
                    @endif
                    @if ($isFormLocked)
                        <x-badge variant="danger">Read Only</x-badge>
                    @endif
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

            {{-- Display Pasien (selaras General Consent) --}}
            <div class="px-4 pt-4">
                <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                    wire:key="edu-ter-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />
            </div>
            <div class="flex-1 p-4 sm:p-6 space-y-4"
                wire:key="{{ $this->renderKey('modal-edukasi-terintegrasi-ri', [$riHdrNo ?? 'new']) }}">

    @php $formReadOnly = $isFormLocked || $viewOnly; @endphp

    @if ($isFormLocked)
        <div class="flex items-center gap-2 px-4 py-2.5 mb-2 rounded-lg
                    bg-amber-50 border border-amber-200 text-amber-800
                    dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300 text-sm">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            Pasien sudah pulang — form dalam mode <strong>read-only</strong>.
        </div>
    @endif

    @if ($viewOnly)
        <div class="flex items-center gap-2 px-4 py-2.5 mb-2 text-sm font-medium text-sky-700 bg-sky-50 border border-sky-200 rounded-lg dark:bg-sky-900/20 dark:border-sky-600 dark:text-sky-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            Menampilkan entri terkunci (hanya lihat) — klik <strong>Selesai Melihat</strong> untuk kembali ke form entri baru.
        </div>
    @elseif ($editingKey && !$isFormLocked)
        <div class="flex items-center gap-2 px-4 py-2.5 mb-2 text-sm font-medium text-brand-green bg-brand-lime/10 border border-brand-lime/40 rounded-lg dark:text-brand-lime dark:bg-brand-lime/5">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Sedang melanjutkan entri draft — <strong>Simpan Draft</strong> menyimpan ke entri ini; klik <strong>Entri Baru</strong> untuk menambah edukasi lain.
        </div>
    @endif

    {{-- ═══════════════ FORM ENTRY ═══════════════ --}}
    @if (!$isFormLocked)
        <x-border-form title="Formulir Edukasi Terintegrasi Pasien & Keluarga" align="start" bgcolor="bg-surface-soft">
            <fieldset @disabled($formReadOnly)>
            <div class="mt-3 space-y-5">

                {{-- ─── HEADER: Waktu & Petugas ─── --}}
                <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                    <div class="md:col-span-4">
                        <x-input-label value="Tanggal Edukasi *" />
                        <div class="flex items-end gap-2 mt-1">
                            <x-text-input wire:model="form.tglEdukasi" class="flex-1 font-mono"
                                placeholder="dd/mm/yyyy hh:ii:ss" readonly
                                :error="$errors->has('form.tglEdukasi')" />
                            <x-now-button wire:click="setTglEdukasi" :disabled="$formReadOnly" />
                        </div>
                        <x-input-error :messages="$errors->get('form.tglEdukasi')" class="mt-1" />
                    </div>
                    <div class="md:col-span-5">
                        <x-input-label value="Sasaran Edukasi (Penerima) *" />
                        <x-text-input wire:model.blur="form.sasaran.nama" class="w-full mt-1"
                            placeholder="Nama pasien/keluarga penerima edukasi"
                            :error="$errors->has('form.sasaran.nama')" :disabled="$formReadOnly" />
                        <x-input-error :messages="$errors->get('form.sasaran.nama')" class="mt-1" />
                    </div>
                    <div class="md:col-span-3">
                        <x-input-label value="Hubungan dengan Pasien *" />
                        <x-select-input wire:model.blur="form.sasaran.hubungan" class="w-full mt-1"
                            :error="$errors->has('form.sasaran.hubungan')" :disabled="$formReadOnly">
                            <option value="">— Pilih hubungan —</option>
                            @foreach ($hubunganOptions as $nilai => $label)
                                <option value="{{ $nilai }}">{{ $label }}</option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->get('form.sasaran.hubungan')" class="mt-1" />
                    </div>
                </div>

                <hr class="border-hairline dark:border-gray-700">

                {{-- ─── 1) TUJUAN EDUKASI ─── --}}
                <div>
                    <h4 class="text-sm font-semibold text-ink dark:text-gray-100 mb-2">
                        1) Tujuan Edukasi <span class="text-xs font-normal text-muted">(boleh lebih dari satu)</span>
                    </h4>
                    <div class="flex flex-wrap gap-x-4 gap-y-2">
                        @foreach ($tujuanList as $key => $label)
                            <div wire:key="tujuan-{{ $key }}">
                                <x-toggle
                                    :current="in_array($key, $form['tujuan']['opsi'] ?? []) ? '1' : '0'"
                                    trueValue="1" falseValue="0"
                                    wireClick="toggleArrayOpt('form.tujuan.opsi', '{{ $key }}')"
                                    :label="$label" :disabled="$formReadOnly" />
                            </div>
                        @endforeach
                    </div>
                    @if (in_array('lainnya', $form['tujuan']['opsi'] ?? []))
                        <x-text-input wire:model.blur="form.tujuan.lainnya" class="w-full mt-2"
                            placeholder="Sebutkan tujuan lainnya" :disabled="$formReadOnly"
                            :error="$errors->has('form.tujuan.lainnya')" />
                        <x-input-error :messages="$errors->get('form.tujuan.lainnya')" class="mt-1" />
                    @endif
                </div>

                <hr class="border-hairline dark:border-gray-700">

                {{-- ─── 2) EVALUASI AWAL & NILAI ─── --}}
                <div class="space-y-3">
                    <h4 class="text-sm font-semibold text-ink dark:text-gray-100">
                        2) Evaluasi Awal Kemampuan & Nilai
                    </h4>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="p-3 border border-hairline rounded-lg bg-canvas dark:bg-gray-800 dark:border-gray-700">
                            <x-input-label value="Kemampuan membaca / menulis" />
                            <div class="flex gap-2 mt-1">
                                @foreach (['Baik', 'Cukup', 'Kurang'] as $opt)
                                    <x-radio-button :label="$opt" :value="$opt" name="literasi"
                                        wire:model.live="form.evaluasiAwal.literasi" :disabled="$formReadOnly" />
                                @endforeach
                            </div>
                        </div>
                        <div class="p-3 border border-hairline rounded-lg bg-canvas dark:bg-gray-800 dark:border-gray-700">
                            <x-input-label value="Bahasa yang digunakan / tingkat pendidikan" />
                            <x-text-input wire:model.blur="form.evaluasiAwal.bahasaAtauPendidikan" :error="$errors->has('form.evaluasiAwal.bahasaAtauPendidikan')"
                                class="w-full mt-1" placeholder="Contoh: Indonesia / SMA"
                                :disabled="$formReadOnly" />
                        </div>
                        <div class="p-3 border border-hairline rounded-lg bg-canvas dark:bg-gray-800 dark:border-gray-700">
                            <x-input-label value="Hambatan emosional / motivasi" />
                            <div class="flex gap-2 mt-1">
                                <x-radio-button label="Ada" value="1" name="hambatanEmo"
                                    wire:model.live="form.evaluasiAwal.hambatanEmosional.ada"
                                    :disabled="$formReadOnly" />
                                <x-radio-button label="Tidak ada" value="0" name="hambatanEmo"
                                    wire:model.live="form.evaluasiAwal.hambatanEmosional.ada"
                                    :disabled="$formReadOnly" />
                            </div>
                            <x-text-input wire:model.blur="form.evaluasiAwal.hambatanEmosional.keterangan" :error="$errors->has('form.evaluasiAwal.hambatanEmosional.keterangan')"
                                class="w-full mt-2" placeholder="Keterangan jika ada hambatan"
                                :disabled="$formReadOnly" />
                        </div>
                        <div class="p-3 border border-hairline rounded-lg bg-canvas dark:bg-gray-800 dark:border-gray-700">
                            <x-input-label value="Keterbatasan fisik / kognitif" />
                            <div class="flex gap-2 mt-1">
                                <x-radio-button label="Ada" value="1" name="keterbatasanFk"
                                    wire:model.live="form.evaluasiAwal.keterbatasanFisikKognitif.ada"
                                    :disabled="$formReadOnly" />
                                <x-radio-button label="Tidak ada" value="0" name="keterbatasanFk"
                                    wire:model.live="form.evaluasiAwal.keterbatasanFisikKognitif.ada"
                                    :disabled="$formReadOnly" />
                            </div>
                            <x-text-input wire:model.blur="form.evaluasiAwal.keterbatasanFisikKognitif.keterangan" :error="$errors->has('form.evaluasiAwal.keterbatasanFisikKognitif.keterangan')"
                                class="w-full mt-2" placeholder="Keterangan jika ada keterbatasan"
                                :disabled="$formReadOnly" />
                        </div>
                        <div class="p-3 border border-hairline rounded-lg bg-canvas dark:bg-gray-800 dark:border-gray-700">
                            <x-input-label value="Nilai, keyakinan, dan budaya yang dianut" />
                            <div class="flex gap-2 mt-1">
                                <x-radio-button label="Ada" value="1" name="nilaiBudaya"
                                    wire:model.live="form.evaluasiAwal.nilaiKeyakinanBudaya.ada"
                                    :disabled="$formReadOnly" />
                                <x-radio-button label="Tidak ada" value="0" name="nilaiBudaya"
                                    wire:model.live="form.evaluasiAwal.nilaiKeyakinanBudaya.ada"
                                    :disabled="$formReadOnly" />
                            </div>
                            <x-text-input wire:model.blur="form.evaluasiAwal.nilaiKeyakinanBudaya.deskripsi" :error="$errors->has('form.evaluasiAwal.nilaiKeyakinanBudaya.deskripsi')"
                                class="w-full mt-2"
                                placeholder="Jelaskan nilai/kepercayaan/budaya yang relevan"
                                :disabled="$formReadOnly" />
                        </div>
                        <div class="p-3 border border-hairline rounded-lg bg-canvas dark:bg-gray-800 dark:border-gray-700">
                            <x-input-label value="Preferensi menerima informasi" />
                            <div class="flex flex-wrap gap-3 mt-1">
                                @foreach ($prefList as $key => $label)
                                    <div wire:key="pref-{{ $key }}">
                                        <x-toggle
                                            :current="in_array($key, $form['evaluasiAwal']['preferensiInformasi']['opsi'] ?? []) ? '1' : '0'"
                                            trueValue="1" falseValue="0"
                                            wireClick="toggleArrayOpt('form.evaluasiAwal.preferensiInformasi.opsi', '{{ $key }}')"
                                            :label="$label" :disabled="$formReadOnly" />
                                    </div>
                                @endforeach
                            </div>
                            @if (in_array('lainnya', $form['evaluasiAwal']['preferensiInformasi']['opsi'] ?? []))
                                <x-text-input wire:model.blur="form.evaluasiAwal.preferensiInformasi.lainnya"
                                    class="w-full mt-2" placeholder="Sebutkan preferensi lainnya"
                                    :error="$errors->has('form.evaluasiAwal.preferensiInformasi.lainnya')"
                                    :disabled="$formReadOnly" />
                                <x-input-error :messages="$errors->get('form.evaluasiAwal.preferensiInformasi.lainnya')" class="mt-1" />
                            @endif
                        </div>
                    </div>
                </div>

                <hr class="border-hairline dark:border-gray-700">

                {{-- ─── 3) KEBUTUHAN EDUKASI ─── --}}
                <div>
                    <h4 class="text-sm font-semibold text-ink dark:text-gray-100 mb-2">
                        3) Kebutuhan Edukasi <span class="text-xs font-normal text-muted">(boleh lebih dari satu)</span>
                    </h4>
                    @if (!empty($form['tujuan']['opsi']))
                        <p class="mb-2 text-xs italic text-muted-soft">
                            Pilihan difilter mengikuti <strong>Tujuan Edukasi</strong> yang dipilih — kosongkan tujuan untuk menampilkan semua opsi.
                        </p>
                    @endif
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 md:grid-cols-4">
                        @foreach ($this->kebutuhanTampil() as $key => $label)
                            <div wire:key="need-{{ $key }}">
                                <x-toggle
                                    :current="in_array($key, $form['kebutuhan']['opsi'] ?? []) ? '1' : '0'"
                                    trueValue="1" falseValue="0"
                                    wireClick="toggleArrayOpt('form.kebutuhan.opsi', '{{ $key }}')"
                                    :label="$label" :disabled="$formReadOnly" />
                            </div>
                        @endforeach
                    </div>
                    @if (in_array('lainnya', $form['kebutuhan']['opsi'] ?? []))
                        <x-text-input wire:model.blur="form.kebutuhan.lainnya" class="w-full mt-2"
                            placeholder="Sebutkan kebutuhan lainnya" :disabled="$formReadOnly"
                            :error="$errors->has('form.kebutuhan.lainnya')" />
                        <x-input-error :messages="$errors->get('form.kebutuhan.lainnya')" class="mt-1" />
                    @endif

                    <div class="grid grid-cols-1 gap-3 mt-3 md:grid-cols-2">
                        <div>
                            <x-input-label value="Materi / Topik Edukasi *" />
                            <x-text-input wire:model.blur="form.materi.topik" class="w-full mt-1"
                                placeholder="Mis. Cara minum obat antihipertensi, Diet rendah garam..."
                                :error="$errors->has('form.materi.topik')" :disabled="$formReadOnly" />
                            <x-input-error :messages="$errors->get('form.materi.topik')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Keterangan / Catatan Edukasi" />
                            <x-text-input wire:model.blur="form.materi.keterangan" class="w-full mt-1"
                                placeholder="Penjelasan edukasi yang diberikan..."
                                :error="$errors->has('form.materi.keterangan')" :disabled="$formReadOnly" />
                            <x-input-error :messages="$errors->get('form.materi.keterangan')" class="mt-1" />
                        </div>
                    </div>
                </div>

                <hr class="border-hairline dark:border-gray-700">

                {{-- ─── 4) METODE & MEDIA ─── --}}
                <div>
                    <h4 class="text-sm font-semibold text-ink dark:text-gray-100 mb-2">
                        4) Metode & Media Edukasi
                    </h4>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 md:grid-cols-5">
                        @foreach ($metodeList as $key => $label)
                            @continue($key === 'lainnya')
                            <div wire:key="metode-{{ $key }}">
                                <x-toggle
                                    :current="in_array($key, $form['metodeMedia']['opsi'] ?? []) ? '1' : '0'"
                                    trueValue="1" falseValue="0"
                                    wireClick="toggleArrayOpt('form.metodeMedia.opsi', '{{ $key }}')"
                                    :label="$label" :disabled="$formReadOnly" />
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2" wire:key="metode-lainnya">
                        <x-toggle
                            :current="in_array('lainnya', $form['metodeMedia']['opsi'] ?? []) ? '1' : '0'"
                            trueValue="1" falseValue="0"
                            wireClick="toggleArrayOpt('form.metodeMedia.opsi', 'lainnya')"
                            :label="$metodeList['lainnya'] ?? 'Lainnya'" :disabled="$formReadOnly" />
                    </div>
                    @if (in_array('lainnya', $form['metodeMedia']['opsi'] ?? []))
                        <x-text-input wire:model.blur="form.metodeMedia.lainnya" class="w-full mt-2"
                            placeholder="Sebutkan metode/media lainnya" :disabled="$formReadOnly"
                            :error="$errors->has('form.metodeMedia.lainnya')" />
                        <x-input-error :messages="$errors->get('form.metodeMedia.lainnya')" class="mt-1" />
                    @endif
                </div>

                <hr class="border-hairline dark:border-gray-700">

                {{-- ─── 5) HASIL EDUKASI ─── --}}
                <div class="space-y-2">
                    <h4 class="text-sm font-semibold text-ink dark:text-gray-100">5) Hasil Edukasi</h4>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($hasilList as $key => $label)
                            <div class="p-3 border border-hairline rounded-lg bg-canvas dark:bg-gray-800 dark:border-gray-700"
                                wire:key="hasil-{{ $key }}">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm">{{ $label }}</span>
                                    <div class="flex gap-2">
                                        <x-radio-button label="Ya" value="1" name="hasil-{{ $key }}"
                                            wire:model.live="form.hasil.{{ $key }}.ya"
                                            :disabled="$formReadOnly" />
                                        <x-radio-button label="Tidak" value="0" name="hasil-{{ $key }}"
                                            wire:model.live="form.hasil.{{ $key }}.ya"
                                            :disabled="$formReadOnly" />
                                    </div>
                                </div>
                                <x-text-input wire:model.blur="form.hasil.{{ $key }}.keterangan"
                                    class="w-full mt-2" placeholder="Keterangan"
                                    :disabled="$formReadOnly" />
                            </div>
                        @endforeach
                    </div>
                </div>

                <hr class="border-hairline dark:border-gray-700">

                {{-- ─── 6) TINDAK LANJUT ─── --}}
                <div class="space-y-2">
                    <h4 class="text-sm font-semibold text-ink dark:text-gray-100">6) Tindak Lanjut</h4>
                    <x-toggle
                        :current="!filter_var($form['tindakLanjut']['tidakPerluTL'] ?? true, FILTER_VALIDATE_BOOLEAN) ? '1' : '0'"
                        trueValue="1" falseValue="0"
                        wireClick="togglePerluTindakLanjut"
                        label="Perlu tindak lanjut"
                        :disabled="$formReadOnly" />
                    @if (!filter_var($form['tindakLanjut']['tidakPerluTL'] ?? true, FILTER_VALIDATE_BOOLEAN))
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div>
                                <x-input-label value="Edukasi lanjutan (dd/mm/yyyy)" />
                                <div class="flex items-end gap-2 mt-1">
                                    <x-text-input wire:model="form.tindakLanjut.edukasiLanjutanTanggal"
                                        class="flex-1 font-mono" placeholder="dd/mm/yyyy"
                                        :error="$errors->has('form.tindakLanjut.edukasiLanjutanTanggal')"
                                        :disabled="$formReadOnly" />
                                    <x-secondary-button wire:click="setEdukasiLanjutanToday" type="button"
                                        :disabled="$formReadOnly">Hari Ini</x-secondary-button>
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <x-input-label value="Keterangan edukasi lanjutan" />
                                <x-text-input wire:model.blur="form.tindakLanjut.edukasiLanjutanKeterangan"
                                    class="w-full mt-1" placeholder="Tentang apa edukasi lanjutannya..."
                                    :error="$errors->has('form.tindakLanjut.edukasiLanjutanKeterangan')"
                                    :disabled="$formReadOnly" />
                                <x-input-error :messages="$errors->get('form.tindakLanjut.edukasiLanjutanKeterangan')" class="mt-1" />
                            </div>
                        </div>
                    @endif
                </div>

                <hr class="border-hairline dark:border-gray-700">

                {{-- ─── 7) TANDA TANGAN — dua kolom berbingkai sama tinggi (pola Akhir Hayat) ─── --}}
                <div class="space-y-3">
                    <h4 class="text-sm font-semibold text-ink dark:text-gray-100">7) Tanda Tangan</h4>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 items-stretch">

                        {{-- Pasien / Keluarga --}}
                        <div class="flex flex-col h-full p-3 border rounded-lg border-hairline bg-surface-soft/60 dark:bg-gray-900/40 dark:border-gray-700">
                            <p class="mb-2 text-xs font-semibold tracking-wide text-center uppercase text-muted dark:text-gray-400">
                                Pasien / Keluarga *
                            </p>
                            <div class="flex-1">
                                @if (!empty($sasaranEdukasiSignature))
                                    <x-signature.signature-result :signature="$sasaranEdukasiSignature" :date="''"
                                        :disabled="$formReadOnly" wireMethod="clearSasaranSignature" />
                                @elseif (!$formReadOnly)
                                    <x-signature.signature-pad wireMethod="setSasaranSignature" />
                                @else
                                    <p class="py-8 text-sm italic text-center text-muted-soft">Belum ditandatangani.</p>
                                @endif
                            </div>
                            <div class="mt-3 space-y-2">
                                <div>
                                    <x-input-label value="Nama Penanda Tangan *" />
                                    <x-text-input wire:model.blur="form.ttd.pasienKeluargaNama" class="w-full mt-1"
                                        placeholder="Nama yang menandatangani"
                                        :error="$errors->has('form.ttd.pasienKeluargaNama')" :disabled="$formReadOnly" />
                                    <x-input-error :messages="$errors->get('form.ttd.pasienKeluargaNama')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Hubungan dengan Pasien *" />
                                    <x-select-input wire:model.blur="form.ttd.pasienKeluargaHubungan" class="w-full mt-1"
                                        :error="$errors->has('form.ttd.pasienKeluargaHubungan')" :disabled="$formReadOnly">
                                        <option value="">— Pilih hubungan —</option>
                                        @foreach ($hubunganOptions as $nilai => $label)
                                            <option value="{{ $nilai }}">{{ $label }}</option>
                                        @endforeach
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('form.ttd.pasienKeluargaHubungan')" class="mt-1" />
                                </div>
                                <p class="text-xs text-muted-soft">
                                    Penanda tangan boleh berbeda dari <strong>Sasaran Edukasi</strong> di header
                                    (mis. edukasi ke pasien, yang TTD keluarganya).
                                </p>
                            </div>
                        </div>

                        {{-- Petugas — judul kolom sudah ada di atas, komponen cukup label "Nama" --}}
                        <div class="flex flex-col h-full p-3 border rounded-lg border-hairline bg-surface-soft/60 dark:bg-gray-900/40 dark:border-gray-700">
                            <p class="mb-2 text-xs font-semibold tracking-wide text-center uppercase text-muted dark:text-gray-400">
                                Petugas (Pemberi Informasi)
                            </p>
                            <div class="flex-1 flex flex-col justify-center">
                                <x-signature.ttd-petugas :framed="false"
                                    :ttd="$form['pemberiInformasi']['petugasName'] ?? ''"
                                    :code="$form['pemberiInformasi']['petugasCode'] ?? ''"
                                    :date="$form['tglEdukasi'] ?? ''"
                                    :locked="$formReadOnly" :allowClear="false" sign="ttdPetugas"
                                    nameLabel="Nama" dateLabel="Tanggal Edukasi" signLabel="TTD Petugas & Kunci"
                                    emptyText="Menunggu TTD petugas." />
                            </div>
                            <p class="mt-3 text-xs text-muted dark:text-gray-400">
                                Petugas menandatangani <strong>paling akhir</strong> — setelah pasien/keluarga TTD.
                                Menandatangani = memvalidasi &amp; <strong>mengunci</strong> entri ini.
                            </p>
                        </div>
                    </div>
                </div>

            </div>
            </fieldset>
        </x-border-form>
    @endif

    {{-- ═══════════════ LIST RIWAYAT (expandable) ═══════════════ --}}
    <x-border-form title="Riwayat Edukasi Terintegrasi" align="start" bgcolor="bg-surface-soft">
        @php $list = $dataDaftarRi['edukasiPasienTerintegrasi'] ?? []; @endphp
        <div class="mt-3 overflow-x-auto bg-canvas border border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between gap-2 px-4 pt-3">
                <span class="text-sm font-semibold text-body dark:text-gray-300">Daftar Edukasi Tersimpan</span>
                <span class="text-xs italic text-muted-soft">Klik baris untuk lihat detail lengkap</span>
            </div>
            <table class="min-w-full mt-2 text-sm">
                <thead class="bg-surface-soft dark:bg-gray-800">
                    <tr class="text-left">
                        <th class="w-8 px-2 py-3 border-b border-hairline dark:border-gray-700"></th>
                        <th class="px-4 py-3 text-sm font-medium text-muted dark:text-gray-400 border-b border-hairline dark:border-gray-700">Tanggal</th>
                        <th class="px-4 py-3 text-sm font-medium text-muted dark:text-gray-400 border-b border-hairline dark:border-gray-700">Pasien / Keluarga</th>
                        <th class="px-4 py-3 text-sm font-medium text-muted dark:text-gray-400 border-b border-hairline dark:border-gray-700">Petugas (TTD)</th>
                        <th class="px-4 py-3 text-sm font-medium text-center text-muted dark:text-gray-400 border-b border-hairline dark:border-gray-700">Status</th>
                        <th class="px-4 py-3 text-sm font-medium text-center text-muted dark:text-gray-400 border-b border-hairline dark:border-gray-700 w-64">Aksi</th>
                    </tr>
                </thead>
                @forelse (array_reverse($list) as $entri)
                    @php
                        $entriForm  = $entri['form'] ?? [];
                        $edukasiId    = $entri['id'] ?? null;
                        $tglEdukasi   = $entriForm['tglEdukasi'] ?? '-';
                        $petugasName = data_get($entriForm, 'pemberiInformasi.petugasName', '-') ?: '-';
                        $pasienNama  = data_get($entriForm, 'ttd.pasienKeluargaNama', '-') ?: '-';
                        $isFinal     = $this->entryIsFinal($entri);
                        $hasTtd      = !empty(data_get($entriForm, 'ttd.pasienKeluargaTTD'));

                        $hambatanEmosionalAda = data_get($entriForm, 'evaluasiAwal.hambatanEmosional.ada');
                        $keterbatasanFisikAda  = data_get($entriForm, 'evaluasiAwal.keterbatasanFisikKognitif.ada');
                        $adaHambatanEmosional       = in_array($hambatanEmosionalAda, [true, 1, '1'], true);
                        $adaKeterbatasanFisik        = in_array($keterbatasanFisikAda, [true, 1, '1'], true);
                        $isPahamTidak = in_array(data_get($entriForm, 'hasil.paham.ya'), [false, 0, '0'], true);
                        $alertRow    = $isPahamTidak || $adaHambatanEmosional || $adaKeterbatasanFisik;

                        // ringkasan join label
                        $tujuanTeks = collect($entriForm['tujuan']['opsi'] ?? [])->map(fn($k) => $tujuanList[$k] ?? $k)->implode(', ');
                        if (!empty($entriForm['tujuan']['lainnya'])) {
                            $tujuanTeks = trim($tujuanTeks . ($tujuanTeks ? ', ' : '') . $entriForm['tujuan']['lainnya']);
                        }
                        $kebutuhanTeks = collect($entriForm['kebutuhan']['opsi'] ?? [])->map(fn($k) => $kebutuhanList[$k] ?? $k)->implode(', ');
                        if (!empty($entriForm['kebutuhan']['lainnya'])) {
                            $kebutuhanTeks = trim($kebutuhanTeks . ($kebutuhanTeks ? ', ' : '') . $entriForm['kebutuhan']['lainnya']);
                        }
                        $metodeTeks = collect($entriForm['metodeMedia']['opsi'] ?? [])->map(fn($k) => $metodeList[$k] ?? $k)->implode(', ');
                        if (!empty($entriForm['metodeMedia']['lainnya'])) {
                            $metodeTeks = trim($metodeTeks . ($metodeTeks ? ', ' : '') . $entriForm['metodeMedia']['lainnya']);
                        }
                        $rujukTeks = collect($entriForm['tindakLanjut']['dirujukKe'] ?? [])->map(fn($k) => $rujukList[$k] ?? $k)->implode(', ');
                        $literasi = data_get($entriForm, 'evaluasiAwal.literasi') ?: '-';
                        $hubunganLabel = $hubunganOptions[data_get($entriForm, 'ttd.pasienKeluargaHubungan')] ?? data_get($entriForm, 'ttd.pasienKeluargaHubungan', '');
                        // Entri lama belum punya node sasaran → fallback ke penanda tangan.
                        $sasaranNama = data_get($entriForm, 'sasaran.nama') ?: $pasienNama;
                        $sasaranHubunganLabel = $hubunganOptions[data_get($entriForm, 'sasaran.hubungan')] ?? (data_get($entriForm, 'sasaran.hubungan') ?: $hubunganLabel);
                        $tindakLanjutTanggal    = data_get($entriForm, 'tindakLanjut.edukasiLanjutanTanggal') ?: '-';
                        $tindakLanjutKeterangan = data_get($entriForm, 'tindakLanjut.edukasiLanjutanKeterangan') ?: '';
                        $tidakPerluTL = (bool) data_get($entriForm, 'tindakLanjut.tidakPerluTL');
                    @endphp

                    <tbody wire:key="edu-terint-{{ $edukasiId ?: $loop->index }}"
                        x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }"
                        class="border-b border-hairline dark:border-gray-700">
                        <tr @click="open = !open"
                            class="cursor-pointer align-top hover:bg-surface-soft dark:hover:bg-gray-800/60 {{ $editingKey && $editingKey === $edukasiId ? 'bg-brand-lime/10 dark:bg-brand-lime/5' : ($alertRow ? 'bg-red-50/50 dark:bg-red-900/10' : '') }}">
                            <td class="px-2 py-3 text-center align-middle">
                                <svg class="w-4 h-4 mx-auto text-muted transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </td>
                            <td class="px-4 py-3 font-mono text-muted whitespace-nowrap align-middle dark:text-gray-300">{{ $tglEdukasi }}</td>
                            <td class="px-4 py-3 font-medium text-ink align-middle dark:text-white">{{ $pasienNama }}</td>
                            <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                @if ($petugasName !== '-')
                                    <span class="font-medium text-ink dark:text-gray-200">{{ $petugasName }}</span>
                                @else
                                    <x-badge variant="danger">Belum TTD</x-badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center align-middle">
                                <div class="flex flex-col items-center gap-1">
                                    @if ($isFinal)
                                        <x-badge variant="info">Terkunci</x-badge>
                                    @else
                                        <x-badge variant="warning">Draft</x-badge>
                                    @endif
                                    @if ($alertRow)
                                        <x-badge variant="danger">⚠ Risiko</x-badge>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center align-middle" @click.stop>
                                <div class="flex flex-col items-center gap-2">
                                    {{-- Baris atas: aksi non-destruktif (Lanjut/Lihat/Cetak) --}}
                                    <div class="flex items-center justify-center gap-2">
                                    @if (!$isFinal && !$isFormLocked && $edukasiId)
                                        <x-primary-button type="button" wire:click="editEntry('{{ $edukasiId }}')"
                                            wire:loading.attr="disabled" wire:target="editEntry('{{ $edukasiId }}')"
                                            class="gap-1.5 whitespace-nowrap" title="Lanjutkan mengisi entri ini">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            Lanjut Isi
                                        </x-primary-button>
                                    @endif
                                    @if ($isFinal && $edukasiId)
                                        <x-secondary-button type="button" wire:click="viewEntry('{{ $edukasiId }}')"
                                            wire:loading.attr="disabled" wire:target="viewEntry('{{ $edukasiId }}')"
                                            class="gap-1.5" title="Lihat detail (read-only) di form atas">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            Lihat
                                        </x-secondary-button>
                                    @endif
                                    @if ($edukasiId)
                                        <x-secondary-button wire:click="cetak('{{ $edukasiId }}')"
                                            wire:loading.attr="disabled" wire:target="cetak('{{ $edukasiId }}')"
                                            class="gap-1.5">
                                            <span wire:loading.remove wire:target="cetak('{{ $edukasiId }}')" class="flex items-center gap-1.5">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                </svg>
                                                Cetak
                                            </span>
                                            <span wire:loading wire:target="cetak('{{ $edukasiId }}')" class="flex items-center gap-1.5"><x-loading class="w-5 h-5" /> Mencetak...</span>
                                        </x-secondary-button>
                                    @endif
                                    </div>

                                    {{-- Baris bawah: aksi terkunci/destruktif (Buka Kunci + Hapus) --}}
                                    @if (!$isFormLocked && $edukasiId)
                                        <div class="flex items-center justify-center gap-2">
                                        @if ($isFinal)
                                            @can('dokumen.bukaKunci')
                                                <x-confirm-button action="bukaKunci('{{ $edukasiId }}')"
                                                    title="Buka Kunci Edukasi Terintegrasi"
                                                    message="TTD petugas akan dicabut & entri kembali menjadi draft untuk dikoreksi. TTD pasien/keluarga tetap. Lanjutkan?"
                                                    confirmText="Ya, Buka Kunci" class="gap-1.5 whitespace-nowrap">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M8 11V7a4 4 0 118 0m-8 4h10a2 2 0 012 2v5a2 2 0 01-2 2H8a2 2 0 01-2-2v-5a2 2 0 012-2z" />
                                                    </svg>
                                                    Buka Kunci
                                                </x-confirm-button>
                                            @endcan
                                        @endif
                                        @can('dokumen.hapus')
                                        <x-outline-button type="button"
                                            wire:click.prevent="removeEdukasiTerintegrasiById('{{ $edukasiId }}')"
                                            wire:confirm="Hapus data edukasi terintegrasi ini?"
                                            wire:loading.attr="disabled"
                                            class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300 !px-2 !py-1"
                                            title="Hapus">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
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
                                    <div class="md:col-span-2">
                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tujuan Edukasi</dt>
                                        <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $tujuanTeks ?: '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Evaluasi Awal</dt>
                                        <dd class="mt-0.5 text-ink dark:text-gray-200">
                                            Literasi: {{ $literasi }};
                                            Hambatan emosional: {{ $adaHambatanEmosional ? 'Ada' : 'Tidak' }};
                                            Keterbatasan fisik/kognitif: {{ $adaKeterbatasanFisik ? 'Ada' : 'Tidak' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Kebutuhan Edukasi</dt>
                                        <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $kebutuhanTeks ?: '-' }}</dd>
                                    </div>
                                    <div class="md:col-span-2">
                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Materi / Topik Edukasi</dt>
                                        <dd class="mt-0.5 text-ink dark:text-gray-200">
                                            {{ data_get($entriForm, 'materi.topik') ?: '-' }}
                                            @if (filled(data_get($entriForm, 'materi.keterangan')))
                                                <div class="whitespace-pre-line text-muted dark:text-gray-300">{{ data_get($entriForm, 'materi.keterangan') }}</div>
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Metode & Media</dt>
                                        <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $metodeTeks ?: '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Hasil Edukasi</dt>
                                        <dd class="mt-0.5 text-ink dark:text-gray-200">
                                            @foreach ($hasilList as $hasilKey => $hasilLabel)
                                                @php
                                                    $hasilNilai = data_get($entriForm, "hasil.$hasilKey.ya");
                                                    $hasilKeterangan = data_get($entriForm, "hasil.$hasilKey.keterangan");
                                                @endphp
                                                @if (!is_null($hasilNilai) && $hasilNilai !== '')
                                                    <div>
                                                        {{ $hasilLabel }}: <strong>{{ in_array($hasilNilai, [true, 1, '1'], true) ? 'Ya' : 'Tidak' }}</strong>
                                                        @if (filled($hasilKeterangan)) &mdash; {{ $hasilKeterangan }} @endif
                                                    </div>
                                                @endif
                                            @endforeach
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tindak Lanjut</dt>
                                        <dd class="mt-0.5 text-ink dark:text-gray-200">
                                            @if ($tidakPerluTL)
                                                Tidak diperlukan tindak lanjut
                                            @else
                                                Edukasi lanjutan: {{ $tindakLanjutTanggal }}@if ($tindakLanjutKeterangan) &mdash; {{ $tindakLanjutKeterangan }}@endif @if ($rujukTeks); Rujuk ke: {{ $rujukTeks }}@endif
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Sasaran Edukasi</dt>
                                        <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $sasaranNama }}@if ($sasaranHubunganLabel) <span class="text-muted">({{ $sasaranHubunganLabel }})</span>@endif</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Penanda Tangan</dt>
                                        <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $pasienNama }}@if ($hubunganLabel) <span class="text-muted">({{ $hubunganLabel }})</span>@endif</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">TTD Pasien / Keluarga</dt>
                                        <dd class="mt-0.5">
                                            @if ($hasTtd)
                                                <span class="text-success-deep dark:text-green-300">Sudah TTD</span>
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
                            <td colspan="6" class="px-4 py-8 text-center text-muted-soft">Belum ada data edukasi terintegrasi.</td>
                        </tr>
                    </tbody>
                @endforelse
            </table>
        </div>
    </x-border-form>

            </div>{{-- /konten flex-1 --}}

            {{-- ══ FOOTER STICKY (anak langsung modal-body → selalu terlihat) ══ --}}
            <div class="sticky bottom-0 z-10 px-6 py-3 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
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
                            <span>Simpan draft dulu; setelah TTD pasien/keluarga, <strong>kunci</strong> lewat tombol <strong>TTD Petugas &amp; Kunci</strong>.</span>
                        </p>
                    @else
                        <span></span>
                    @endif

                    <div class="flex flex-wrap items-center justify-end gap-2">
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
                                    title="Kosongkan form untuk menambah edukasi lain — entri yang sudah tersimpan tidak berubah">
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
