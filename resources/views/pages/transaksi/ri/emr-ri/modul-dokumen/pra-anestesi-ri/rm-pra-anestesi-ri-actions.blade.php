<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/pra-anestesi-ri/rm-pra-anestesi-ri-actions.blade.php
// Pengkajian Pra Anestesi & Pra Sedasi (PAB 4 / RM 50) — dokter anestesi.
// Pola: multi-entri (Draft + Lanjut Isi + TTD-Kunci + Lihat read-only + tabel expandable),
// disimpan ke datadaftarri_json (key: praAnestesiRI). Kunci entri stabil = createdAt.
// TTD PETUGAS (dokter anestesi) = stempel nama user login (setTtd = FINALIZE/kunci).
// TTD GAMBAR pasien (signature-pad) = FIELD ENTRI biasa (diisi saat draft/edit), BUKAN pemicu kunci.

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Support\PraAnestesiOptions;
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
    protected array $renderAreas = ['modal-pra-anestesi-ri'];

    // ── Form entri (Pengkajian Pra Anestesi & Pra Sedasi — PAB 4 / RM 50) ──
    public array $newForm = [
        'tanggal' => '',
        'kriteria' => 'Dewasa',
        'diagnosisPraAnestesi' => '',
        'rencanaTindakan' => '',
        'anamnese' => '',
        'riwayatAnestesi' => false,
        'riwayatAnestesiKet' => '',
        'riwayatAlergi' => false,
        'riwayatAlergiKet' => '',
        'obatDikonsumsi' => '',
        'merokok' => false,
        'merokokKet' => '',
        'alkohol' => false,
        'alkoholKet' => '',
        // Antropometri & TTV — klasifikasi mengikuti EMR RJ: Tanda Vital / Nutrisi (imt auto dari bb/tb)
        'sistolik' => '',
        'diastolik' => '',
        'nadi' => '',
        'rr' => '',
        'suhu' => '',
        'spo2' => '',
        'gda' => '',
        'skorNyeri' => '',
        'bb' => '',
        'tb' => '',
        'imt' => '',
        // Evaluasi jalan nafas
        'jalanNafasBebas' => false,
        'mallampati' => '',
        'alatBantuNafas' => '',
        'bukaMulut' => '',
        'jarakMentohyoid' => '',
        'jarakHyothyroid' => '',
        'gerakLeher' => '',
        'leherPendek' => false,
        'massa' => false,
        'gigiPalsu' => false,
        'obesitas' => false,
        'sulitVentilasi' => false,
        // Sistem organ & penunjang
        // Fungsi Sistem Organ (RM 50) — checklist [key => bool] + "Lain-lain" per grup
        // ([slugGrup => keterangan]); daftar item di PraAnestesiOptions
        'fungsiSistemOrgan' => [],
        'fungsiSistemOrganLainKet' => [],
        'pemeriksaanLab' => '',
        'pemeriksaanPenunjang' => '',
        // Kesimpulan
        'jenisAnestesi' => '',
        'induksiPraAnestesi' => '',
        'psAsa' => '',
        'penyulit' => '',
        'komplikasi' => '',
        'obatAnalgesikPascaOp' => '',
        // TTD petugas (dokter anestesi) — stempel nama user login (FINALIZE)
        'ttd' => '',
        'ttdCode' => '',
        'ttdDate' => '',
    ];

    // TTD GAMBAR pasien/keluarga (signature-pad) — field entri biasa, BUKAN pemicu kunci.
    public string $signaturePasien = '';

    public array $praAnestesiList = [];

    // Kunci entri yang sedang diedit (createdAt = kunci stabil). null = membuat entri baru.
    public ?string $exceptionditingKey = null;
    // true = entri terkunci sedang ditampilkan di form dalam mode read-only.
    public bool $viewOnly = false;

    public array $kriteriaOptions = ['Anak', 'Dewasa', 'Geriatri'];
    public array $mallampatiOptions = ['I', 'II', 'III', 'IV'];
    public array $gerakLeherOptions = ['Bebas', 'Terbatas'];
    public array $asaOptions = ['ASA I', 'ASA II', 'ASA III', 'ASA IV', 'ASA V', 'ASA I-E', 'ASA II-E', 'ASA III-E', 'ASA IV-E', 'ASA V-E'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-pra-anestesi-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->praList = $data['praAnestesiRI'] ?? [];
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
        $this->signaturePasien = '';
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
        if (!isset($this->dataDaftarRi['praAnestesiRI']) || !is_array($this->dataDaftarRi['praAnestesiRI'])) {
            $this->dataDaftarRi['praAnestesiRI'] = [];
        }
        $this->praList = $this->dataDaftarRi['praAnestesiRI'];
        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;
        $this->incrementVersion('modal-pra-anestesi-ri');

        $this->dispatch('open-modal', name: "rm-pra-anestesi-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-pra-anestesi-ri-{$this->riHdrNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'newForm.tanggal' => 'required|date_format:d/m/Y H:i:s',
            'newForm.kriteria' => 'required|string',
            'newForm.diagnosisPraAnestesi' => 'required|string|max:500',
            'newForm.rencanaTindakan' => 'required|string|max:500',
            'newForm.mallampati' => 'required|in:I,II,III,IV',
            'newForm.alatBantuNafas' => 'nullable|string|max:200',
            'newForm.jarakMentohyoid' => 'nullable|string|max:30',
            'newForm.jarakHyothyroid' => 'nullable|string|max:30',
            'newForm.psAsa' => 'required|string',
            'newForm.jenisAnestesi' => 'required|string|max:200',
            'newForm.riwayatAnestesiKet' => 'nullable|string|max:300',
            'newForm.riwayatAlergiKet' => 'nullable|string|max:300',
            'newForm.merokokKet' => 'nullable|string|max:300',
            'newForm.alkoholKet' => 'nullable|string|max:300',
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
            'newForm.kriteria' => 'Kriteria pasien',
            'newForm.diagnosisPraAnestesi' => 'Diagnosis pra anestesi',
            'newForm.rencanaTindakan' => 'Rencana tindakan',
            'newForm.mallampati' => 'Mallampati',
            'newForm.psAsa' => 'PS ASA',
            'newForm.jenisAnestesi' => 'Jenis anestesi',
        ];
    }

    /* ===============================
     | SET TANGGAL/JAM SEKARANG
     =============================== */
    public function setTanggalSekarang(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['tanggal'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | HELPER — status & bentuk entri
     =============================== */
    // Entri dianggap FINAL/terkunci bila flag finalized true; entri lama (tanpa flag) yang sudah
    // ada TTD petugas dianggap final (kompatibilitas data lama).
    public function entryIsFinal(array $entry): bool
    {
        return array_key_exists('finalized', $entry) ? (bool) $entry['finalized'] : !empty($entry['ttd']);
    }

    // Susun array entri dari state form. $key = createdAt (kunci stabil); $finalized = status kunci.
    // Sertakan TTD GAMBAR pasien dari propertinya ($signaturePasien).
    private function buildEntry(string $key, bool $finalized): array
    {
        $entry = $this->newForm;
        $entry['imt'] = $this->imtValue();
        $entry['signaturePasien'] = $this->signaturePasien;
        $entry['createdAt'] = $key;
        $entry['finalized'] = $finalized;
        return $entry;
    }

    /* ===============================
     | IMT — dihitung otomatis dari BB/TB (meniru hitungIMT() EMR RJ)
     =============================== */
    private function imtValue(): string
    {
        $beratBadan = (float) ($this->newForm['bb'] ?? 0);
        $tinggiBadanMeter = ((float) ($this->newForm['tb'] ?? 0)) / 100;

        return $beratBadan > 0 && $tinggiBadanMeter > 0 ? (string) round($beratBadan / ($tinggiBadanMeter * $tinggiBadanMeter), 2) : '';
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['newForm.bb', 'newForm.tb'], true)) {
            $this->newForm['imt'] = $this->imtValue();
        }
    }

    // Cek: minimal salah satu isi inti terisi (untuk draft & sebelum kunci).
    private function adaIsiInti(): bool
    {
        return collect(['diagnosisPraAnestesi', 'rencanaTindakan', 'jenisAnestesi', 'psAsa'])
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
            if (!isset($fresh['praAnestesiRI']) || !is_array($fresh['praAnestesiRI'])) {
                $fresh['praAnestesiRI'] = [];
            }

            $list = $fresh['praAnestesiRI'];
            $index = collect($list)->search(fn($item) => ($item['createdAt'] ?? '') === $key);
            if ($index === false) {
                $list[] = $entry;
            } else {
                if ($this->entryIsFinal($list[$index])) {
                    throw new \RuntimeException('Entri sudah terkunci, tidak dapat diubah.');
                }
                $list[$index] = $entry;
            }
            $fresh['praAnestesiRI'] = array_values($list);

            $this->updateJsonRI((int) $this->riHdrNo, $fresh);
            $this->dataDaftarRi = $fresh;
            $this->praList = $fresh['praAnestesiRI'];

            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Pengkajian Pra Anestesi — ' . ($entry['psAsa'] ?: '-') . ' (' . $key . ')', 'MR');
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
            $this->dispatch('toast', type: 'error', message: 'Isi minimal salah satu: Diagnosis, Rencana Tindakan, Jenis Anestesi, atau PS ASA.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-pra-anestesi-ri');
            $this->dispatch('toast', type: 'success', message: 'Draft tersimpan.');
        } catch (\RuntimeException $exception) {
            $this->dispatch('toast', type: 'error', message: $exception->getMessage());
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $exception->getMessage());
        }
    }

    /* ===============================
     | TTD 2 PIHAK (Dokter Anestesi + Pasien/Keluarga)
     | Konsep kunci ala Pengkajian Pre Operasi: tiap TTD langsung tersimpan ke DB
     | (bisa menyusul antar sesi). Entri otomatis TERKUNCI saat KEDUA TTD terisi
     | (TTD terakhir = finalize).
     =============================== */
    private function semuaTtdTerisi(): bool
    {
        return filled($this->newForm['ttd'] ?? null) && filled($this->signaturePasien);
    }

    public function setTtd(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (!$this->adaIsiInti()) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal salah satu: Diagnosis, Rencana Tindakan, Jenis Anestesi, atau PS ASA sebelum TTD.');
            return;
        }

        // Enforce aturan lengkap sebelum TTD.
        $this->validateWithToast();

        // Stempel TTD dokter anestesi = user login.
        $this->newForm['ttd'] = auth()->user()->myuser_name ?? '';
        $this->newForm['ttdCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['ttdDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $finalized = $this->semuaTtdTerisi();
        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, $finalized, 'TTD Dokter Anestesi' . ($finalized ? ' + Kunci' : ''));
            if ($finalized) {
                $this->resetNewForm();
                $this->signaturePasien = '';
                $this->editingKey = null;
                $this->viewOnly = false;
                $this->dispatch('toast', type: 'success', message: 'Kedua TTD lengkap — pengkajian terkunci.');
            } else {
                $this->editingKey = $key; // lanjut di entri yang sama, TTD pasien menyusul
                $this->dispatch('toast', type: 'success', message: 'TTD Dokter Anestesi tersimpan. Entri terkunci otomatis setelah TTD Pasien/Keluarga terisi.');
            }
            $this->incrementVersion('modal-pra-anestesi-ri');
        } catch (\RuntimeException $exception) {
            $this->dispatch('toast', type: 'error', message: $exception->getMessage());
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan TTD: ' . $exception->getMessage());
        }
    }

    /** Batalkan TTD dokter anestesi pada form (hanya saat entri masih draft). */
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
     | TTD GAMBAR PASIEN/KELUARGA — pemicu kunci bersama TTD dokter
     =============================== */
    public function setSignaturePasien(string $dataUrl): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signaturePasien = $dataUrl;

        // Belum ada isi inti → cukup tersimpan di form, ikut Simpan Draft nanti.
        if (!$this->adaIsiInti()) {
            $this->incrementVersion('modal-pra-anestesi-ri');
            return;
        }

        $finalized = $this->semuaTtdTerisi();
        if ($finalized) {
            // TTD terakhir = finalize → enforce aturan lengkap dulu.
            $this->validateWithToast();
        }
        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, $finalized, 'TTD Pasien/Keluarga' . ($finalized ? ' + Kunci' : ''));
            if ($finalized) {
                $this->resetNewForm();
                $this->signaturePasien = '';
                $this->editingKey = null;
                $this->viewOnly = false;
                $this->dispatch('toast', type: 'success', message: 'Kedua TTD lengkap — pengkajian terkunci.');
            } else {
                $this->editingKey = $key; // TTD dokter menyusul
                $this->dispatch('toast', type: 'success', message: 'TTD Pasien/Keluarga tersimpan. Entri terkunci otomatis setelah TTD Dokter Anestesi terisi.');
            }
            $this->incrementVersion('modal-pra-anestesi-ri');
        } catch (\RuntimeException $exception) {
            $this->dispatch('toast', type: 'error', message: $exception->getMessage());
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan TTD: ' . $exception->getMessage());
        }
    }

    public function clearSignaturePasien(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signaturePasien = '';
        $this->incrementVersion('modal-pra-anestesi-ri');
    }

    /* ===============================
     | BUKA KUNCI — cabut status final + RESET KEDUA TTD.
     | Kunci form ini = kesepakatan 2 pihak, jadi buka kunci mengulang proses TTD.
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
                $list = is_array($fresh['praAnestesiRI'] ?? null) ? $fresh['praAnestesiRI'] : [];
                $index = collect($list)->search(fn($item) => ($item['createdAt'] ?? '') === $createdAt);
                if ($index === false) {
                    throw new \RuntimeException('Entri tidak ditemukan.');
                }

                $list[$index]['finalized'] = false;
                $list[$index]['ttd'] = '';
                $list[$index]['ttdCode'] = '';
                $list[$index]['ttdDate'] = '';
                $list[$index]['signaturePasien'] = '';
                $fresh['praAnestesiRI'] = array_values($list);

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->praList = $fresh['praAnestesiRI'];

                $pembukaKunci = auth()->user()->myuser_name ?? '-';
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Buka kunci Pengkajian Pra Anestesi (' . $createdAt . ') oleh ' . $pembukaKunci . ' — kedua TTD dicabut', 'MR');
            });

            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }
            $this->incrementVersion('modal-pra-anestesi-ri');
            $this->dispatch('toast', type: 'success', message: 'Kunci dibuka — kedua TTD dicabut, entri kembali Draft.');
        } catch (\RuntimeException $exception) {
            $this->dispatch('toast', type: 'error', message: $exception->getMessage());
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuka kunci: ' . $exception->getMessage());
        }
    }

    /* ===============================
     | EDIT / LIHAT / BATAL entri
     =============================== */
    // Muat 1 entri ke form atas (dipakai edit draft & lihat entri terkunci). Termasuk TTD gambar pasien.
    private function hydrateFormFromEntry(array $entry, string $key): void
    {
        foreach ($this->newForm as $k => $v) {
            $this->newForm[$k] = $entry[$k] ?? (is_array($v) ? [] : ($v === false ? false : ''));
        }
        $this->signaturePasien = $entry['signaturePasien'] ?? '';
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-pra-anestesi-ri');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->praList)->firstWhere('createdAt', $key);
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
        $entry = collect($this->praList)->firstWhere('createdAt', $key);
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
        $this->signaturePasien = '';
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();
        $this->incrementVersion('modal-pra-anestesi-ri');
    }

    /* ===============================
     | CETAK (per-entri)
     =============================== */
    public function cetak(string $createdAt)
    {
        $entry = collect($this->praList)->firstWhere('createdAt', $createdAt);
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

            $ttdPath = null;
            $ttdCode = $entry['ttdCode'] ?? null;
            if ($ttdCode) {
                $path = DB::table('users')->where('myuser_code', $ttdCode)->value('myuser_ttd_image');
                if (!empty($path) && file_exists(public_path('storage/' . $path))) {
                    $ttdPath = public_path('storage/' . $path);
                }
            }

            $data = array_merge($pasien, [
                'dataRi' => $this->dataDaftarRi,
                'form' => $entry,
                'identitasRs' => $identitasRs,
                'ttdPath' => $ttdPath,
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);

            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.pra-anestesi-ri.cetak-pra-anestesi-ri-print', ['data' => $data])->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak pengkajian pra anestesi.');
            return response()->streamDownload(fn() => print $pdf->output(), 'pra-anestesi-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $exception->getMessage());
        }
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
                if (!isset($fresh['praAnestesiRI'])) {
                    throw new \RuntimeException('Data pengkajian tidak ditemukan.');
                }

                $fresh['praAnestesiRI'] = collect($fresh['praAnestesiRI'])
                    ->reject(fn($itemem) => ($itemem['createdAt'] ?? '') === $createdAt)
                    ->values()
                    ->toArray();

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->praList = $fresh['praAnestesiRI'];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Pengkajian Pra Anestesi — ' . $createdAt, 'MR');
            });

            // Jika entri yang dihapus sedang di form, kosongkan form.
            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }

            $this->incrementVersion('modal-pra-anestesi-ri');
            $this->dispatch('toast', type: 'success', message: 'Pengkajian pra anestesi berhasil dihapus.');
        } catch (\RuntimeException $exception) {
            $this->dispatch('toast', type: 'error', message: $exception->getMessage());
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $exception->getMessage());
        }
    }

    /* ===============================
     | RESET
     =============================== */
    private function resetNewForm(): void
    {
        $this->newForm = [
            'tanggal' => '', 'kriteria' => 'Dewasa', 'diagnosisPraAnestesi' => '', 'rencanaTindakan' => '',
            'anamnese' => '', 'riwayatAnestesi' => false, 'riwayatAnestesiKet' => '', 'riwayatAlergi' => false,
            'riwayatAlergiKet' => '', 'obatDikonsumsi' => '', 'merokok' => false, 'merokokKet' => '',
            'alkohol' => false, 'alkoholKet' => '',
            'sistolik' => '', 'diastolik' => '', 'nadi' => '', 'rr' => '', 'suhu' => '', 'spo2' => '', 'gda' => '',
            'skorNyeri' => '', 'bb' => '', 'tb' => '', 'imt' => '',
            'jalanNafasBebas' => false, 'mallampati' => '', 'alatBantuNafas' => '', 'bukaMulut' => '', 'jarakMentohyoid' => '',
            'jarakHyothyroid' => '', 'gerakLeher' => '', 'leherPendek' => false, 'massa' => false,
            'gigiPalsu' => false, 'obesitas' => false,
            'sulitVentilasi' => false, 'fungsiSistemOrgan' => [], 'fungsiSistemOrganLainKet' => [],
            'pemeriksaanLab' => '', 'pemeriksaanPenunjang' => '',
            'jenisAnestesi' => '', 'induksiPraAnestesi' => '', 'psAsa' => '', 'penyulit' => '', 'komplikasi' => '',
            'obatAnalgesikPascaOp' => '', 'ttd' => '', 'ttdCode' => '', 'ttdDate' => '',
        ];
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRi = [];
        $this->praList = [];
        $this->resetNewForm();
        $this->signaturePasien = '';
        $this->editingKey = null;
        $this->viewOnly = false;
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD ══ --}}
    @php $entriCount = count($praAnestesiList ?? []); @endphp

    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-2">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Pengkajian Pra Anestesi & Pra Sedasi</h3>
                    @if ($entriCount > 0)
                        <x-badge variant="success">{{ $entriCount }} pengkajian</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-sm text-muted dark:text-gray-400">
                    Asesmen pra anestesi (PAB 4 / RM 50) oleh dokter anestesi: anamnese, jalan nafas (Mallampati),
                    status fisik ASA, rencana teknik anestesi & analgesia pasca-op. Tiap entri = 1 pengkajian.
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

        @if ($entriCount > 0)
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                    <thead class="bg-surface-soft dark:bg-gray-800">
                        <tr class="text-left text-muted dark:text-gray-300">
                            <th class="px-3 py-2 border-b">Tanggal</th>
                            <th class="px-3 py-2 border-b">ASA</th>
                            <th class="px-3 py-2 border-b">Petugas (TTD)</th>
                            <th class="px-3 py-2 text-center border-b">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_reverse($praAnestesiList) as $entry)
                            <tr class="border-b border-hairline dark:border-gray-700">
                                <td class="px-3 py-2 font-medium text-ink dark:text-gray-200">{{ $entry['tanggal'] ?: ($entry['createdAt'] ?? '-') }}</td>
                                <td class="px-3 py-2 text-muted dark:text-gray-400">{{ $entry['psAsa'] ?: '-' }}</td>
                                <td class="px-3 py-2 text-muted dark:text-gray-400">
                                    @if (!empty($entry['ttd'])){{ $entry['ttd'] }}@else<x-badge variant="danger">Belum TTD</x-badge>@endif
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
    <x-modal name="rm-pra-anestesi-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-pra-anestesi-ri', [$riHdrNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-violet-500/10">
                                <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="font-semibold text-2xl text-ink dark:text-gray-100">Pengkajian Pra Anestesi & Pra Sedasi</h2>
                                <p class="mt-0.5 text-base text-muted dark:text-gray-400">PAB 4 / RM 50 — dokter anestesi</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="brand">Rawat Inap</x-badge>
                            @if (count($praAnestesiList) > 0)
                                <x-badge variant="info">{{ count($praAnestesiList) }} tersimpan</x-badge>
                            @endif
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="pra-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />

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
                            Menampilkan entri terkunci <strong>{{ $exceptionditingKey }}</strong> (hanya lihat) — klik <strong>Selesai Melihat</strong> untuk kembali ke form entri baru.
                        </div>
                    @elseif ($exceptionditingKey && !$isFormLocked)
                        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-brand-green bg-brand-lime/10 border border-brand-lime/40 rounded-lg dark:text-brand-lime dark:bg-brand-lime/5">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Sedang melanjutkan entri <strong>{{ $exceptionditingKey }}</strong> — <strong>Simpan Perubahan</strong> menyimpan ke entri ini; klik <strong>Entri Baru</strong> untuk menambah pengkajian lain.
                        </div>
                    @endif

                    <div class="p-6 space-y-6 bg-canvas border border-hairline shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        {{-- ── FORM ENTRI (1 pengkajian) ── --}}
                        <fieldset @disabled($formReadOnly) class="space-y-6">

                            {{-- ══ DATA DASAR ══ --}}
                            <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Tanggal / Jam *" class="mb-1" />
                                    <div class="flex items-center gap-2">
                                        <x-text-input wire:model.live="newForm.tanggal" placeholder="dd/mm/yyyy HH:mm:ss"
                                            :error="$errors->has('newForm.tanggal')" class="w-full" />
                                        @if (!$formReadOnly)
                                            <x-now-button wire:click="setTanggalSekarang" />
                                        @endif
                                    </div>
                                    <x-input-error :messages="$errors->get('newForm.tanggal')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Kriteria Pasien *" class="mb-1" />
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($kriteriaOptions as $opt)
                                            <x-radio-button :label="$opt" :value="$opt" name="kriteria"
                                                wire:model.live="newForm.kriteria" :disabled="$formReadOnly" />
                                        @endforeach
                                    </div>
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
                            </section>

                            {{-- ══ ANAMNESE & RIWAYAT ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <div>
                                    <x-input-label value="Anamnese" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.anamnese" :error="$errors->has('newForm.anamnese')" rows="2" class="w-full" />
                                </div>
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div class="flex items-center gap-3">
                                        <div class="shrink-0">
                                            <x-toggle wire:model.live="newForm.riwayatAnestesi" :trueValue="true" :falseValue="false" label="Ada riwayat anestesi" :disabled="$formReadOnly" />
                                        </div>
                                        @if ($newForm['riwayatAnestesi'])
                                            <x-text-input wire:model.live="newForm.riwayatAnestesiKet" :error="$errors->has('newForm.riwayatAnestesiKet')" placeholder="Keterangan riwayat anestesi" class="w-full" />
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="shrink-0">
                                            <x-toggle wire:model.live="newForm.riwayatAlergi" :trueValue="true" :falseValue="false" label="Ada riwayat alergi" :disabled="$formReadOnly" />
                                        </div>
                                        @if ($newForm['riwayatAlergi'])
                                            <x-text-input wire:model.live="newForm.riwayatAlergiKet" :error="$errors->has('newForm.riwayatAlergiKet')" placeholder="Keterangan alergi" class="w-full" />
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <x-input-label value="Obat yang Sedang Dikonsumsi" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.obatDikonsumsi" :error="$errors->has('newForm.obatDikonsumsi')" class="w-full" />
                                </div>
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div class="flex items-center gap-3">
                                        <div class="shrink-0">
                                            <x-toggle wire:model.live="newForm.merokok" :trueValue="true" :falseValue="false" label="Merokok" :disabled="$formReadOnly" />
                                        </div>
                                        @if ($newForm['merokok'])
                                            <x-text-input wire:model.live="newForm.merokokKet" :error="$errors->has('newForm.merokokKet')" placeholder="Keterangan (jumlah/lama merokok)" class="w-full" />
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="shrink-0">
                                            <x-toggle wire:model.live="newForm.alkohol" :trueValue="true" :falseValue="false" label="Alkohol" :disabled="$formReadOnly" />
                                        </div>
                                        @if ($newForm['alkohol'])
                                            <x-text-input wire:model.live="newForm.alkoholKet" :error="$errors->has('newForm.alkoholKet')" placeholder="Keterangan (jumlah/frekuensi)" class="w-full" />
                                        @endif
                                    </div>
                                </div>
                            </section>

                            {{-- ══ ANTROPOMETRI & TTV — klasifikasi mengikuti EMR RJ (Tanda Vital / Nutrisi) ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Antropometri & Tanda Vital</h3>

                                <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                                <x-border-form :title="__('Tanda Vital')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
                                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
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
                                            <x-input-label value="Nafas (x/mnt)" class="whitespace-nowrap" />
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
                                        <div>
                                            <x-input-label value="Skor Nyeri" class="whitespace-nowrap" />
                                            <x-text-input wire:model.live="newForm.skorNyeri" :error="$errors->has('newForm.skorNyeri')" class="w-full mt-1" />
                                        </div>
                                    </div>
                                </x-border-form>

                                <x-border-form :title="__('Nutrisi')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                        <div>
                                            <x-input-label value="Berat Badan (Kg)" class="whitespace-nowrap" />
                                            <x-text-input wire:model.live="newForm.bb" :error="$errors->has('newForm.bb')" class="w-full mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="Tinggi Badan (Cm)" class="whitespace-nowrap" />
                                            <x-text-input wire:model.live="newForm.tb" :error="$errors->has('newForm.tb')" class="w-full mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="Index Masa Tubuh (Kg/M²)" class="whitespace-nowrap" />
                                            {{-- IMT readonly, dihitung otomatis via updated() saat BB/TB berubah --}}
                                            <div class="flex mt-1">
                                                <div
                                                    class="w-full px-3 py-2 text-base text-ink bg-surface-soft border border-gray-300 rounded-l-lg dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                                                    {{ ($newForm['imt'] ?? '') !== '' ? $newForm['imt'] : '-' }}
                                                </div>
                                                <div
                                                    class="px-3 py-2 text-sm font-semibold text-center text-muted bg-surface-soft border border-l-0 border-gray-300 rounded-r-lg whitespace-nowrap dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                                                    Kg/M²
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </x-border-form>
                                </div>
                            </section>

                            {{-- ══ EVALUASI JALAN NAFAS ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Evaluasi Jalan Nafas</h3>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-6">
                                    <div>
                                        <x-input-label value="Mallampati *" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.mallampati" :error="$errors->has('newForm.mallampati')" class="w-full">
                                            <option value="">— pilih —</option>
                                            @foreach ($mallampatiOptions as $opt)
                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                            @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('newForm.mallampati')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Alat Bantu Nafas" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.alatBantuNafas" :error="$errors->has('newForm.alatBantuNafas')"
                                            placeholder="cth: nasal kanul / NRM / OPA" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Buka Mulut (cm)" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.bukaMulut" :error="$errors->has('newForm.bukaMulut')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Jarak Mentohyoid (cm)" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.jarakMentohyoid" :error="$errors->has('newForm.jarakMentohyoid')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Jarak Hyothyroid (cm)" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.jarakHyothyroid" :error="$errors->has('newForm.jarakHyothyroid')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Gerak Leher" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.gerakLeher" :error="$errors->has('newForm.gerakLeher')" class="w-full">
                                            <option value="">— pilih —</option>
                                            @foreach ($gerakLeherOptions as $opt)
                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                            @endforeach
                                        </x-select-input>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-4">
                                    <x-toggle wire:model.live="newForm.jalanNafasBebas" :trueValue="true" :falseValue="false"
                                        label="Jalan nafas bebas" :disabled="$formReadOnly" />
                                    <x-toggle wire:model.live="newForm.leherPendek" :trueValue="true" :falseValue="false" label="Leher pendek" :disabled="$formReadOnly" />
                                    <x-toggle wire:model.live="newForm.massa" :trueValue="true" :falseValue="false" label="Massa" :disabled="$formReadOnly" />
                                    <x-toggle wire:model.live="newForm.gigiPalsu" :trueValue="true" :falseValue="false" label="Gigi palsu" :disabled="$formReadOnly" />
                                    <x-toggle wire:model.live="newForm.obesitas" :trueValue="true" :falseValue="false" label="Obesitas" :disabled="$formReadOnly" />
                                    <x-toggle wire:model.live="newForm.sulitVentilasi" :trueValue="true" :falseValue="false" label="Prediksi sulit ventilasi" :disabled="$formReadOnly" />
                                </div>
                            </section>

                            {{-- ══ SISTEM ORGAN & PENUNJANG ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Fungsi Sistem Organ</h3>
                                <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                                    @foreach (PraAnestesiOptions::fungsiSistemOrgan() as $organSlug => $organGrup)
                                        <x-border-form :title="$organGrup['label']" :align="__('start')"
                                            :bgcolor="!empty($newForm['fungsiSistemOrgan'][$organSlug . 'Dbn']) ? 'bg-success-tint' : 'bg-error-tint'"
                                            :class="!empty($newForm['fungsiSistemOrgan'][$organSlug . 'Dbn']) ? 'border-success' : 'border-error'">
                                            <div class="space-y-2">
                                                {{-- Status grup: DBN = Dalam Batas Normal --}}
                                                <div class="pb-2 border-b border-hairline-soft dark:border-gray-800">
                                                    <x-toggle wire:model.live="newForm.fungsiSistemOrgan.{{ $organSlug }}Dbn"
                                                        :trueValue="true" :falseValue="false" label="DBN (Dalam Batas Normal)" :disabled="$formReadOnly" />
                                                </div>
                                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                    @foreach ($organGrup['items'] as $organKey => $organLabel)
                                                        <x-toggle wire:model.live="newForm.fungsiSistemOrgan.{{ $organKey }}"
                                                            :trueValue="true" :falseValue="false" :label="$organLabel" :disabled="$formReadOnly" />
                                                    @endforeach
                                                </div>
                                                <div class="flex items-center gap-3 pt-2 border-t border-hairline-soft dark:border-gray-800">
                                                    <div class="shrink-0">
                                                        <x-toggle wire:model.live="newForm.fungsiSistemOrgan.{{ $organSlug }}LainLain"
                                                            :trueValue="true" :falseValue="false" label="Lain-lain" :disabled="$formReadOnly" />
                                                    </div>
                                                    @if (!empty($newForm['fungsiSistemOrgan'][$organSlug . 'LainLain']))
                                                        <x-text-input wire:model.live="newForm.fungsiSistemOrganLainKet.{{ $organSlug }}"
                                                            placeholder="Keterangan lain-lain" class="w-full" />
                                                    @endif
                                                </div>
                                            </div>
                                        </x-border-form>
                                    @endforeach
                                </div>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <x-input-label value="Pemeriksaan Laboratorium" class="mb-1" />
                                        <x-textarea wire:model.live="newForm.pemeriksaanLab" :error="$errors->has('newForm.pemeriksaanLab')" rows="2"
                                            placeholder="cth: Hb/Hct/CBC, fungsi ginjal, fungsi hati, serum elektrolit, faal hemostasis" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Pemeriksaan Penunjang" class="mb-1" />
                                        <x-textarea wire:model.live="newForm.pemeriksaanPenunjang" :error="$errors->has('newForm.pemeriksaanPenunjang')" rows="2"
                                            placeholder="cth: X-Ray, EKG, dll" class="w-full" />
                                    </div>
                                </div>
                            </section>

                            {{-- ══ KESIMPULAN ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Kesimpulan Evaluasi Pra Anestesi</h3>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <x-input-label value="Jenis Anestesi *" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.jenisAnestesi" :error="$errors->has('newForm.jenisAnestesi')" placeholder="cth: GA / Spinal / Sedasi" class="w-full" />
                                        <x-input-error :messages="$errors->get('newForm.jenisAnestesi')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="PS ASA *" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.psAsa" :error="$errors->has('newForm.psAsa')" class="w-full">
                                            <option value="">— pilih —</option>
                                            @foreach ($asaOptions as $opt)
                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                            @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('newForm.psAsa')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Induksi Pra Anestesi" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.induksiPraAnestesi" :error="$errors->has('newForm.induksiPraAnestesi')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Penyulit" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.penyulit" :error="$errors->has('newForm.penyulit')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Komplikasi" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.komplikasi" :error="$errors->has('newForm.komplikasi')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Obat Analgesik Pasca Operasi" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.obatAnalgesikPascaOp" :error="$errors->has('newForm.obatAnalgesikPascaOp')" class="w-full" />
                                    </div>
                                </div>
                            </section>

                            {{-- ══ TTD ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                {{-- Kiri = TTD gambar Pasien/Keluarga (field entri biasa); Kanan = TTD Petugas (kunci) --}}
                                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    {{-- Pasien / Keluarga (KIRI) — TTD gambar; pad hanya saat form aktif, hasil selalu tampil --}}
                                    <div class="border shadow-sm border-hairline rounded-2xl bg-canvas dark:border-gray-700 dark:bg-gray-900">
                                        <div class="p-4">
                                            <div class="mb-4 text-sm font-semibold tracking-wide text-center uppercase ds-caption-up dark:text-gray-400">Pasien / Keluarga</div>
                                            <div class="max-w-xl mx-auto">
                                                @if (!empty($signaturePasien))
                                                    <x-signature.signature-result :signature="$signaturePasien" :date="''" :disabled="$formReadOnly" wireMethod="clearSignaturePasien" />
                                                @elseif (!$formReadOnly)
                                                    <x-signature.signature-pad wireMethod="setSignaturePasien" />
                                                @else
                                                    <p class="py-8 text-base italic text-center text-muted-soft">Belum ditandatangani.</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    {{-- Petugas / Dokter Anestesi (KANAN) — stempel user login --}}
                                    <x-signature.ttd-petugas :framed="true" :ttd="$newForm['ttd']"
                                        :date="$newForm['ttdDate'] ?? ''" :code="$newForm['ttdCode'] ?? ''"
                                        :locked="$formReadOnly" sign="setTtd" clear="clearTtd"
                                        title="Dokter Anestesi" label="Dokter Anestesi"
                                        nameLabel="Dokter Anestesi" dateLabel="Waktu TTD"
                                        signLabel="TTD Dokter Anestesi" clearLabel="Batal TTD" />
                                </div>
                                @if (!$formReadOnly)
                                    <p class="text-xs text-center text-muted">
                                        Tiap TTD langsung tersimpan (bisa menyusul). Entri otomatis <strong>terkunci</strong> saat TTD Dokter Anestesi &amp; TTD Pasien/Keluarga lengkap.
                                    </p>
                                @endif
                            </section>
                        </fieldset>

                        {{-- ── DAFTAR PENGKAJIAN TERSIMPAN (expandable) ── --}}
                        <div class="pt-6 border-t border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-body dark:text-gray-300 pb-2 border-b border-hairline-soft dark:border-gray-800 mb-3">Daftar Pengkajian Tersimpan</h3>
                            @if (count($praAnestesiList ?? []))
                                <p class="mb-3 text-xs italic text-muted-soft">Klik baris untuk lihat detail lengkap</p>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                                        <thead class="bg-surface-soft dark:bg-gray-800">
                                            <tr class="text-left text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-300">
                                                <th class="w-8 px-2 py-3 border-b"></th>
                                                <th class="px-4 py-3 border-b">Tanggal</th>
                                                <th class="px-4 py-3 border-b">ASA</th>
                                                <th class="px-4 py-3 border-b">Jenis Anestesi</th>
                                                <th class="px-4 py-3 border-b">Petugas (TTD)</th>
                                                <th class="px-4 py-3 text-center border-b">Status</th>
                                                <th class="px-4 py-3 text-center border-b">Aksi</th>
                                            </tr>
                                        </thead>
                                        @foreach (array_reverse($praAnestesiList) as $entry)
                                            @php
                                                $isFinal = $this->entryIsFinal($entry);
                                                $rowKey = $entry['createdAt'] ?? '';
                                            @endphp
                                            <tbody x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="border-b border-hairline dark:border-gray-700">
                                                <tr @click="open = !open"
                                                    class="cursor-pointer hover:bg-surface-soft dark:hover:bg-gray-800 {{ $exceptionditingKey && $exceptionditingKey === $rowKey ? 'bg-brand-lime/10 dark:bg-brand-lime/5' : '' }}">
                                                    <td class="px-2 py-3 text-center align-middle">
                                                        <svg class="w-4 h-4 mx-auto transition-transform text-muted" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                        </svg>
                                                    </td>
                                                    <td class="px-4 py-3 font-semibold align-middle text-ink dark:text-gray-100">
                                                        {{ $entry['tanggal'] ?: ($rowKey ?: '-') }}
                                                    </td>
                                                    <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">{{ $entry['psAsa'] ?: '-' }}</td>
                                                    <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">{{ $entry['jenisAnestesi'] ? Str::limit($entry['jenisAnestesi'], 40) : '-' }}</td>
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
                                                            <x-secondary-button type="button" wire:click="cetak('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="cetak('{{ $rowKey }}')" class="gap-1.5" title="Cetak">
                                                                <span wire:loading.remove wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5">
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
                                                                            title="Buka Kunci Pengkajian Pra Anestesi"
                                                                            message="KEDUA TTD (Dokter Anestesi & Pasien/Keluarga) akan dicabut & entri kembali menjadi Draft — proses TTD diulang dari awal. Lanjutkan?"
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
                                                                <x-outline-button type="button" wire:click.prevent="hapus('{{ $rowKey }}')" wire:confirm="Yakin hapus pengkajian ini?"
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
                                                    <td colspan="7" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                                        <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tanggal / Jam</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['tanggal'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Kriteria Pasien</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['kriteria'] ?: '-' }}</dd>
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
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Anamnese</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['anamnese'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Riwayat Anestesi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['riwayatAnestesi'] ?? false) ? 'Ya' : 'Tidak' }}{{ !empty($entry['riwayatAnestesiKet']) ? ' — ' . $entry['riwayatAnestesiKet'] : '' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Riwayat Alergi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['riwayatAlergi'] ?? false) ? 'Ya' : 'Tidak' }}{{ !empty($entry['riwayatAlergiKet']) ? ' — ' . $entry['riwayatAlergiKet'] : '' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Obat Dikonsumsi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['obatDikonsumsi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Merokok / Alkohol</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['merokok'] ?? false) ? 'Merokok' . (!empty($entry['merokokKet']) ? ' (' . $entry['merokokKet'] . ')' : '') : '—' }} / {{ ($entry['alkohol'] ?? false) ? 'Alkohol' . (!empty($entry['alkoholKet']) ? ' (' . $entry['alkoholKet'] . ')' : '') : '—' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">BB / TB / IMT</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['bb'] ?: '-' }} kg / {{ $entry['tb'] ?: '-' }} cm / {{ ($entry['imt'] ?? '') ?: '-' }} kg/m²</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tensi / Nadi / Nafas / Suhu</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['sistolik'] ?? '') ?: '-' }}/{{ ($entry['diastolik'] ?? '') ?: '-' }} mmHg · {{ $entry['nadi'] ?: '-' }} · {{ $entry['rr'] ?: '-' }} · {{ $entry['suhu'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">SPO2 / GDA</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['spo2'] ?? '') ?: '-' }} % · {{ ($entry['gda'] ?? '') ?: '-' }} g/dl</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Skor Nyeri</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['skorNyeri'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jalan Nafas Bebas</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['jalanNafasBebas'] ?? false) ? 'Ya' : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Mallampati</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['mallampati'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Alat Bantu Nafas</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['alatBantuNafas'] ?? '') ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Buka Mulut (cm)</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['bukaMulut'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jarak Mentohyoid (cm)</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['jarakMentohyoid'] ?? '') ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jarak Hyothyroid (cm)</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['jarakHyothyroid'] ?? '') ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Gerak Leher</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['gerakLeher'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Leher Pendek</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['leherPendek'] ?? false) ? 'Ya' : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Massa</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['massa'] ?? false) ? 'Ya' : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Gigi Palsu</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['gigiPalsu'] ?? false) ? 'Ya' : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Obesitas</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['obesitas'] ?? false) ? 'Ya' : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Prediksi Sulit Ventilasi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['sulitVentilasi'] ?? false) ? 'Ya' : 'Tidak' }}</dd>
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Fungsi Sistem Organ</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">
                                                                    @php $organTerpilih = PraAnestesiOptions::fungsiSistemOrganTerpilih($entry['fungsiSistemOrgan'] ?? [], $entry['fungsiSistemOrganLainKet'] ?? []); @endphp
                                                                    @forelse ($organTerpilih as $organGroupLabel => $organLabels)
                                                                        <div><b>{{ $organGroupLabel }}:</b> {{ implode(', ', $organLabels) }}</div>
                                                                    @empty
                                                                        -
                                                                    @endforelse
                                                                </dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Pemeriksaan Laboratorium</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['pemeriksaanLab'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Pemeriksaan Penunjang</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['pemeriksaanPenunjang'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jenis Anestesi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['jenisAnestesi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">PS ASA</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['psAsa'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Induksi Pra Anestesi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['induksiPraAnestesi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Penyulit</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['penyulit'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Komplikasi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['komplikasi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Obat Analgesik Pasca Op</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['obatAnalgesikPascaOp'] ?: '-' }}</dd>
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
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">TTD Pasien / Keluarga</dt>
                                                                <dd class="mt-0.5">
                                                                    @if (!empty($entry['signaturePasien']))
                                                                        <x-badge variant="success">Ada</x-badge>
                                                                    @else
                                                                        <x-badge variant="warning">Belum ada</x-badge>
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
                            @else
                                <p class="text-sm text-muted dark:text-gray-400">Belum ada pengkajian tersimpan.</p>
                            @endif
                        </div>

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
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
                            <span>Simpan draft dulu; entri otomatis <strong>terkunci</strong> setelah TTD <strong>Dokter Anestesi + Pasien/Keluarga</strong> lengkap.</span>
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
                            @if ($exceptionditingKey)
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
                                    {{ $exceptionditingKey ? 'Simpan Perubahan' : 'Simpan Draft' }}
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
