<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/surveilans-hap-ri/rm-surveilans-hap-ri-actions.blade.php
// Surveilans HAIs — Pneumonia Non-Ventilator (HAP), Formulir Surveilans HIPPII F/011/001/R/03.
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
    protected array $renderAreas = ['modal-surveilans-hap-ri'];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'surveilansHapRI';

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
        return [
            // ── Data dasar surveilans ──
            'tanggal' => '',
            'diagnosisAkhir' => '',
            'faktorRisiko' => array_fill_keys(array_keys(SurveilansHaisOptions::FAKTOR_RISIKO), false),

            // ── Pneumonia Non-Ventilator (HAP) ──
            'tglMulaiTirahBaring' => '',
            'tglSelesaiTirahBaring' => '',
            'demam' => '',
            'demamHariKe' => '',
            'leukosit' => '',
            'sputumPurulen' => '',
            'sputumKeterangan' => '',
            'kulturSputumHasil' => [],

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
        $this->registerAreas(['modal-surveilans-hap-ri']);

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

        $this->incrementVersion('modal-surveilans-hap-ri');
        $this->layar = 'daftar';
        $this->dispatch('open-modal', name: "rm-surveilans-hap-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-surveilans-hap-ri-{$this->riHdrNo}");
    }

    /* ===============================
     | VALIDASI (minimal)
     =============================== */
    protected function rules(): array
    {
        return [
            'newForm.tanggal' => 'required|date_format:d/m/Y H:i:s',
            'newForm.tglMulaiTirahBaring' => 'required|date_format:d/m/Y H:i:s',
            'newForm.leukosit' => 'nullable|numeric|min:0',
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
            'newForm.tglMulaiTirahBaring' => 'Tanggal mulai tirah baring',
            'newForm.leukosit' => 'Leukosit',
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
        return filled($this->newForm['tanggal'] ?? null)
            || filled($this->newForm['tglMulaiTirahBaring'] ?? null)
            || filled($this->newForm['demam'] ?? null);
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

            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Surveilans Pneumonia Non-Ventilator — ' . ($entry['tanggal'] ?: '-') . ' (' . $key . ')', 'MR');
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
            $this->dispatch('toast', type: 'error', message: 'Isi minimal tanggal surveilans / tanggal mulai tirah baring terlebih dahulu.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key;
            $this->incrementVersion('modal-surveilans-hap-ri');
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
            $this->incrementVersion('modal-surveilans-hap-ri');
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
        $this->incrementVersion('modal-surveilans-hap-ri');
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
        $this->incrementVersion('modal-surveilans-hap-ri');
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

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Buka kunci Surveilans Pneumonia Non-Ventilator (' . $key . ') oleh ' . (auth()->user()->myuser_name ?? '-'), 'MR');
            });

            $this->incrementVersion('modal-surveilans-hap-ri');
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

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Surveilans Pneumonia Non-Ventilator — ' . $key, 'MR');
            });

            if ($this->editingKey === $key) {
                $this->cancelEdit();
            }

            $this->incrementVersion('modal-surveilans-hap-ri');
            $this->dispatch('toast', type: 'success', message: 'Entri surveilans dihapus.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HASIL KULTUR / PEMERIKSAAN (daftar dinamis, pola Leveling Dokter)
     =============================== */
    /** Daftar list hasil yang boleh disentuh aksi di bawah — penjaga argumen dari blade. */
    private array $daftarKultur = ['kulturSputumHasil'];

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
            $pdf = Pdf::loadView('pages.components.modul-dokumen.ri.surveilans-hap-ri.cetak-surveilans-hap-ri-print', ['data' => $data])->setPaper('A4');

            return response()->streamDownload(fn() => print $pdf->output(), 'surveilans-hap-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }
};
?>

@php
    $opsiFaktorRisiko = \App\Support\Options\SurveilansHaisOptions::FAKTOR_RISIKO;
    $opsiFotoToraks = \App\Support\Options\SurveilansHaisOptions::FOTO_TORAKS;
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
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Surveilans Pneumonia Non-Ventilator (HAP)</h3>
                    @if ($jumlahEntri > 0)
                        <x-badge variant="success">{{ $jumlahEntri }} entri</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-sm text-muted dark:text-gray-400">
                    Pemantauan pneumonia yang timbul selama perawatan TANPA ventilator — lama tirah baring,
                    demam, leukosit, onset baru sputum purulen, serta kultur sputum.
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
    <x-modal name="rm-surveilans-hap-ri-{{ $riHdrNo }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-surveilans-hap-ri', [$riHdrNo ?? 'new', $editingKey ?? 'baru']) }}">

            {{-- HEADER --}}
            <div class="px-6 py-4 border-b shrink-0 bg-surface-soft border-hairline dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    @if ($this->diForm())
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                            <svg class="w-6 h-6 text-brand-green dark:text-brand-lime" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4l2 5 4-10 2 5h6" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Surveilans Pneumonia Non-Ventilator (HAP)</h2>
                            <p class="mt-0.5 text-sm text-muted dark:text-gray-400">Formulir Surveilans HAIs — diisi IPCLN / Perawat ruangan.</p>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Tutup</span>
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="w-full space-y-4">

                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="surveilans-hap-display-pasien-{{ $riHdrNo }}" />

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
                                Kriteria Kasus HAP — Kapan Dihitung Insiden
                            </span>
                            <svg class="w-4 h-4 text-blue-600 transition-transform" :class="showKriteria && 'rotate-180'" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div x-show="showKriteria" x-collapse style="display:none" class="px-4 pb-4 space-y-3">
                        <div>
                            <p class="mb-1.5 text-sm font-semibold text-ink dark:text-gray-200">Definisi:</p>
                            <p class="text-sm text-body dark:text-gray-300">Pneumonia yang muncul <b>48 jam atau lebih setelah pasien masuk</b> rumah sakit dan tidak sedang dalam masa inkubasi saat masuk. Bedanya dengan VAP: pasien <b>tidak</b> memakai ventilasi mekanik.</p>
                        </div>
                        <div class="pt-2 border-t border-blue-200/60 dark:border-blue-700/60">
                            <p class="mb-1.5 text-sm font-semibold text-ink dark:text-gray-200">Kriteria klinis:</p>
                            <ul class="pl-5 space-y-1 text-sm list-disc text-body dark:text-gray-300">
                                <li>Demam &ge;38&deg;C tanpa penyebab lain, <b>atau</b> leukopeni &lt;4.000/mm&sup3; / leukositosis &ge;12.000/mm&sup3;.</li>
                                <li><b>Dan</b> disertai timbulnya onset baru sputum purulen atau perubahan sifat sputum.</li>
                            </ul>
                        </div>
                        <div class="pt-2 border-t border-blue-200/60 dark:border-blue-700/60">
                            <p class="mb-1.5 text-sm font-semibold text-ink dark:text-gray-200">Cara entri ini dihitung di Laporan Surveilans HAIs:</p>
                            <ul class="pl-5 space-y-1 text-sm list-disc text-body dark:text-gray-300">
                                <li><b>Insiden HAP</b> bila: tirah baring <b>&ge; 3 hari kalender</b> (hari mulai = hari ke-1, mewakili syarat &ge;48 jam sejak masuk) + (<b>demam &ge;38&deg;C = Ya</b> <b>atau</b> leukosit di luar 4.000&ndash;12.000/mm&sup3;) + <b>sputum purulen = Ya</b>.</li>
                                <li>Tanggal tirah baring di sini dipakai untuk <b>syarat &ge;3 hari</b> dan menentukan bulan kasus. <b>Penyebutnya</b> (hari tirah baring) diambil dari <b>Observasi &rarr; Alat Invasif &amp; Tirah Baring</b>, yang diisi perawat ruangan untuk semua pasien — pastikan entri di sana juga ada.</li>
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

                        {{-- 3. TIRAH BARING & TANDA KLINIS --}}
                        <x-border-form title="3. Tirah Baring & Tanda Klinis" :collapsible="true" :open="true">
                            {{-- Dikelompokkan mengikuti kriteria HAP: lama tirah baring (mewakili
                                 syarat >=48 jam sejak masuk) · tanda sistemik · sputum. --}}
                            {{-- TANPA items-start: biarkan grid stretch supaya ketiga kotak
                                 sama tinggi walau isinya beda (hint leukosit bikin kolom tengah lebih panjang). --}}
                            <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">

                                <x-border-form title="Lama Tirah Baring" bgcolor="bg-surface-soft" class="!shadow-none lg:col-span-5" padding="p-3">
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div>
                                            <x-input-label value="Tanggal / Jam Mulai *" />
                                            <div class="flex gap-1 mt-1">
                                                <x-text-input wire:model="newForm.tglMulaiTirahBaring" class="w-full" placeholder="dd/mm/yyyy HH:mm:ss"
                                                    :error="$errors->has('newForm.tglMulaiTirahBaring')" />
                                                <x-now-button wire:click="setNow('tglMulaiTirahBaring')" :disabled="$formReadOnly" />
                                            </div>
                                            <x-input-error :messages="$errors->get('newForm.tglMulaiTirahBaring')" class="mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="s/d Tanggal / Jam Selesai" />
                                            <div class="flex gap-1 mt-1">
                                                <x-text-input wire:model="newForm.tglSelesaiTirahBaring" class="w-full" placeholder="dd/mm/yyyy HH:mm:ss" />
                                                <x-now-button wire:click="setNow('tglSelesaiTirahBaring')" :disabled="$formReadOnly" />
                                            </div>
                                        </div>
                                    </div>
                                </x-border-form>

                                <x-border-form title="Tanda Sistemik" bgcolor="bg-surface-soft" class="!shadow-none lg:col-span-4" padding="p-3">
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                        <div>
                                            <x-input-label value="Demam ≥ 38 °C" />
                                            <div class="mt-2">
                                                <x-toggle wire:model="newForm.demam" trueValue="Ya" falseValue="Tidak"
                                                    :label="filled($newForm['demam'] ?? null) ? $newForm['demam'] : 'Belum diisi'" :disabled="$formReadOnly" />
                                            </div>
                                        </div>
                                        <div>
                                            <x-input-label value="Demam Hari Ke" />
                                            <x-text-input wire:model="newForm.demamHariKe" class="w-full mt-1" placeholder="mis. 3" />
                                        </div>
                                        <div>
                                            <x-input-label value="Leukosit (/mm³)" />
                                            <x-text-input wire:model="newForm.leukosit" class="w-full mt-1" placeholder="mis. 13500"
                                                :error="$errors->has('newForm.leukosit')" />
                                            @php $leukositAbnormal = \App\Support\Options\SurveilansHaisOptions::leukositAbnormal($newForm['leukosit'] ?? null); @endphp
                                            <p class="mt-1 text-xs {{ $leukositAbnormal ? 'font-semibold text-rose-700 dark:text-rose-400' : 'text-muted dark:text-gray-400' }}">
                                                {{ $leukositAbnormal ? 'Memenuhi kriteria.' : 'Kriteria: <4.000 atau ≥12.000.' }}
                                            </p>
                                            <x-input-error :messages="$errors->get('newForm.leukosit')" class="mt-1" />
                                        </div>
                                    </div>
                                </x-border-form>

                                <x-border-form title="Sputum" bgcolor="bg-surface-soft" class="!shadow-none lg:col-span-3" padding="p-3">
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div>
                                            <x-input-label value="Sputum Purulen / Berubah Sifat" />
                                            <div class="mt-2">
                                                <x-toggle wire:model="newForm.sputumPurulen" trueValue="Ya" falseValue="Tidak"
                                                    :label="filled($newForm['sputumPurulen'] ?? null) ? $newForm['sputumPurulen'] : 'Belum diisi'" :disabled="$formReadOnly" />
                                            </div>
                                        </div>
                                        <div>
                                            <x-input-label value="Keterangan Sputum" />
                                            <x-text-input wire:model="newForm.sputumKeterangan" class="w-full mt-1" placeholder="mis. kental kehijauan" />
                                        </div>
                                    </div>
                                </x-border-form>

                            </div>
                        </x-border-form>

                        {{-- 4. KULTUR SPUTUM --}}
                        <x-border-form title="4. Kultur Sputum" :collapsible="true" :open="true">
                            <div class="space-y-3">
                                {{-- Tak ada lagi toggle "Dilakukan": ada-tidaknya baris hasil di bawah sudah menjawabnya. --}}
                                <x-surveilans.kultur-list namaDaftar="kulturSputumHasil" title="Hasil Kultur Sputum"
                                    :barisList="$newForm['kulturSputumHasil'] ?? []" :barisBaru="$barisKultur['kulturSputumHasil'] ?? []"
                                    :formReadOnly="$formReadOnly" hasilLabel="Hasil" hasilPlaceholder="Hasil kultur sputum"
                                    kosongTeks="Belum ada hasil kultur sputum." />
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
                    <x-border-form title="Riwayat Surveilans Pneumonia Non-Ventilator">
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
                                        Tirah baring: {{ $entri['tglMulaiTirahBaring'] ?: '-' }}
                                        · Sputum purulen: {{ $entri['sputumPurulen'] ?: '-' }}
                                        · Petugas: {{ $entri['ttd'] ?: '-' }}
                                    </div>
                                </div>
                                <div class="flex flex-col items-center gap-2">
                                    <div class="flex items-center justify-center gap-2">
                                        @if ($rowFinal)
                                            <x-secondary-button type="button" wire:click="viewEntry('{{ $rowKey }}')" class="px-3 py-1.5 text-sm">Lihat</x-secondary-button>
                                        @else
                                            <x-secondary-button type="button" wire:click="editEntry('{{ $rowKey }}')" class="px-3 py-1.5 text-sm">Lanjut Isi</x-secondary-button>
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
                            <p class="text-sm text-muted dark:text-gray-400">Belum ada entri surveilans pneumonia non-ventilator.</p>
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
                                Tambah Entri
                            </x-primary-button>
                        @endunless
                    </div>
                @endif
            </div>

        </div>
    </x-modal>
</div>
