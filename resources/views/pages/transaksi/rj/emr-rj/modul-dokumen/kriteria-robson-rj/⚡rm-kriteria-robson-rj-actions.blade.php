<?php
// resources/views/pages/transaksi/rj/emr-rj/modul-dokumen/kriteria-robson-rj/rm-kriteria-robson-rj-actions.blade.php
// Kriteria Robson (Klasifikasi 10 Kelompok, WHO 2017) — jalur Rawat Jalan; port dari RI (kriteria-robson-ri).
// Blade cetak DIPAKAI BERSAMA dengan RI (satu formulir satu kode RM-05.11).
// Pola: multi-entri append-only (Draft + Lanjutkan Pengisian + TTD-Kunci + Lihat read-only + tabel expandable),
// disimpan ke datadaftarrj_json. Kunci entri stabil = createdAt.
// Kelompok Robson TIDAK dipilih petugas — diturunkan dari enam variabel obstetri oleh
// App\Support\Options\KriteriaRobsonOptions::tentukanKelompok(), lalu DISIMPAN di entri.
// TTD = stempel nama user login (ttdSaya = FINALIZE/kunci), tanpa TTD gambar.

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
use App\Support\Options\KriteriaRobsonOptions;
use App\Support\TtdUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public ?string $regNo = null;
    public bool $disabled = false;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-kriteria-robson-rj'];

    /** Key penyimpanan di datadaftarrj_json */
    private string $jsonKey = 'kriteriaRobsonRJ';

    public array $newForm = [
        'tglPersalinan'       => '', // d/m/Y H:i:s
        'paritas'             => '', // nulipara | multipara
        'riwayatSc'           => '', // tidak | satu | duaAtauLebih
        'awalPersalinan'      => '', // spontan | induksi | scSebelumPersalinan
        'jumlahJanin'         => '', // tunggal | ganda
        'usiaKehamilanMinggu' => '', // minggu lengkap
        'presentasiJanin'     => '', // kepala | bokong | lintangOblik
        'caraPersalinan'      => '', // pervaginam | sc
        'catatan'             => '',
        'kelompok'            => '', // '1'..'10' — hasil tentukanKelompok(), disimpan
        'subKelompok'         => '', // '2a' | '2b' | '4a' | '4b' | '5.1' | '5.2' | ''
        'ttd'                 => '', // nama penanda-tangan (myuser_name)
        'ttdDate'             => '', // tgl/jam TTD (d/m/Y H:i:s)
        'ttdCode'             => '', // myuser_code penanda-tangan
    ];

    public array $entriList = [];

    // Kunci entri yang sedang diedit (createdAt = kunci stabil, di-set saat entri pertama dibuat).
    // null = sedang membuat entri baru.
    public ?string $editingKey = null;

    // Layar aktif di modal: 'daftar' (grid entri) atau 'form' (tambah/edit/lihat).
    public string $layar = 'daftar';

    // true = entri terkunci sedang ditampilkan di form dalam mode read-only (lihat saja, tak bisa edit).
    public bool $viewOnly = false;

    /** Dokumen dibaca sebagai variabel LOKAL; hanya irisan di bawah ini yang disimpan. */
    private function muatDariDokumen(array $data): void
    {
        $this->regNo = $data['regNo'] ?? null;
        $this->entriList = is_array($data[$this->jsonKey] ?? null) ? $data[$this->jsonKey] : [];
    }

    /** Peta label opsi — satu sumber dengan cetak & viewer Rekam Medis. */
    public function opsiLabel(): array
    {
        return KriteriaRobsonOptions::labels();
    }

    /** Aturan saat TTD (kunci): seluruh variabel Robson wajib lengkap. */
    protected function rules(): array
    {
        return [
            'newForm.tglPersalinan'       => 'required|date_format:d/m/Y H:i:s',
            'newForm.paritas'             => ['required', Rule::in(array_keys(KriteriaRobsonOptions::PARITAS))],
            'newForm.riwayatSc'           => ['required', Rule::in(array_keys(KriteriaRobsonOptions::RIWAYAT_SC))],
            'newForm.awalPersalinan'      => ['required', Rule::in(array_keys(KriteriaRobsonOptions::AWAL_PERSALINAN))],
            'newForm.jumlahJanin'         => ['required', Rule::in(array_keys(KriteriaRobsonOptions::JUMLAH_JANIN))],
            'newForm.usiaKehamilanMinggu' => 'required|integer|between:20,45',
            'newForm.presentasiJanin'     => ['required', Rule::in(array_keys(KriteriaRobsonOptions::PRESENTASI_JANIN))],
            'newForm.caraPersalinan'      => ['required', Rule::in(array_keys(KriteriaRobsonOptions::CARA_PERSALINAN))],
        ];
    }

    /** Aturan saat Simpan Draft: boleh belum lengkap, tapi yang terisi harus berformat benar. */
    private function rulesDraft(): array
    {
        return [
            'newForm.tglPersalinan'       => 'nullable|date_format:d/m/Y H:i:s',
            'newForm.usiaKehamilanMinggu' => 'nullable|integer|between:20,45',
        ];
    }

    protected function messages(): array
    {
        return [
            'newForm.tglPersalinan.required'       => 'Tanggal / jam persalinan harus diisi.',
            'newForm.tglPersalinan.date_format'    => 'Format tanggal / jam persalinan: dd/mm/yyyy HH:mm:ss.',
            'newForm.paritas.required'             => 'Paritas harus dipilih.',
            'newForm.riwayatSc.required'           => 'Riwayat SC harus dipilih.',
            'newForm.awalPersalinan.required'      => 'Awal persalinan harus dipilih.',
            'newForm.jumlahJanin.required'         => 'Jumlah janin harus dipilih.',
            'newForm.usiaKehamilanMinggu.required' => 'Usia kehamilan (minggu) harus diisi.',
            'newForm.usiaKehamilanMinggu.integer'  => 'Usia kehamilan diisi angka minggu lengkap.',
            'newForm.usiaKehamilanMinggu.between'  => 'Usia kehamilan harus antara 20 sampai 45 minggu.',
            'newForm.presentasiJanin.required'     => 'Presentasi janin harus dipilih.',
            'newForm.caraPersalinan.required'      => 'Cara persalinan harus dipilih.',
        ];
    }

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?int $rjNo = null, bool $disabled = false): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-kriteria-robson-rj']);
        $this->resetNewForm();

        if ($this->rjNo) {
            $data = $this->findDataRJ($this->rjNo);
            if ($data) {
                $this->muatDariDokumen($data);
                $this->isFormLocked = $this->checkEmrRJStatus($this->rjNo) || $disabled;
            }
        }
    }

    /* ===============================
     | OPEN / CLOSE MODAL
     =============================== */
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

        $this->muatDariDokumen($data);
        $this->isFormLocked = $this->checkEmrRJStatus($this->rjNo) || $this->disabled;

        $this->incrementVersion('modal-kriteria-robson-rj');
        $this->layar = 'daftar';
        $this->dispatch('open-modal', name: 'kriteria-robson-rj');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'kriteria-robson-rj');
    }

    /* ===============================
     | SET TANGGAL / JAM SEKARANG
     =============================== */
    // Kolom tanggal+jam (tombol x-now-button) — format seragam repo 'dd/mm/yyyy HH:mm:ss'.
    public function setNow(string $field): void
    {
        if ($this->isFormLocked || $this->viewOnly || $field !== 'tglPersalinan') {
            return;
        }
        $this->newForm[$field] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | HELPER — kelompok, status & bentuk entri
     =============================== */
    /** Kelompok dari isi formulir SAAT INI (pratinjau di layar); null = variabel belum cukup / mustahil. */
    public function kelompokTerhitung(): ?array
    {
        return KriteriaRobsonOptions::tentukanKelompok($this->newForm);
    }

    /** "Kelompok 2a" / "Kelompok 5.1" / "-" — dari kelompok yang TERSIMPAN di entri. */
    public function teksKelompok(array $entri): string
    {
        return KriteriaRobsonOptions::teksKelompok($entri['kelompok'] ?? '', $entri['subKelompok'] ?? '');
    }

    public function kombinasiMustahil(): bool
    {
        return KriteriaRobsonOptions::kombinasiMustahil($this->newForm);
    }

    // Entri dianggap FINAL/terkunci bila flag finalized true.
    public function entryIsFinal(array $entri): bool
    {
        return (bool) ($entri['finalized'] ?? false);
    }

    // Susun array entri dari state form. $key = createdAt (kunci stabil); $finalized = status kunci.
    // Draft tidak pernah membawa stempel TTD — stempel hanya sah bersama finalized.
    private function buildEntry(string $key, bool $finalized): array
    {
        $kelompokRobson = $this->kelompokTerhitung();

        return [
            'tglPersalinan'       => $this->newForm['tglPersalinan'] ?? '',
            'paritas'             => $this->newForm['paritas'] ?? '',
            'riwayatSc'           => $this->newForm['riwayatSc'] ?? '',
            'awalPersalinan'      => $this->newForm['awalPersalinan'] ?? '',
            'jumlahJanin'         => $this->newForm['jumlahJanin'] ?? '',
            'usiaKehamilanMinggu' => (string) ($this->newForm['usiaKehamilanMinggu'] ?? ''),
            'presentasiJanin'     => $this->newForm['presentasiJanin'] ?? '',
            'caraPersalinan'      => $this->newForm['caraPersalinan'] ?? '',
            'catatan'             => $this->newForm['catatan'] ?? '',
            'kelompok'            => $kelompokRobson['kelompok'] ?? '',
            'subKelompok'         => $kelompokRobson['subKelompok'] ?? '',
            'ttd'                 => $finalized ? ($this->newForm['ttd'] ?? '') : '',
            'ttdCode'             => $finalized ? ($this->newForm['ttdCode'] ?? '') : '',
            'ttdDate'             => $finalized ? ($this->newForm['ttdDate'] ?? '') : '',
            'createdAt'           => $key,
            'finalized'           => $finalized,
        ];
    }

    // Cek: minimal satu variabel Robson terisi.
    private function adaIsiInti(): bool
    {
        return collect(['paritas', 'riwayatSc', 'awalPersalinan', 'jumlahJanin', 'usiaKehamilanMinggu', 'presentasiJanin', 'caraPersalinan'])
            ->contains(fn($field) => filled($this->newForm[$field] ?? null));
    }

    // Simpan entri (add/update by createdAt) dengan status $finalized. Dipakai draft & kunci.
    private function persistEntry(string $key, bool $finalized, string $logVerb): void
    {
        $entry = $this->buildEntry($key, $finalized);

        DB::transaction(function () use ($entry, $key, $logVerb) {
            $this->lockRJRow($this->rjNo);

            $fresh = $this->findDataRJ($this->rjNo) ?: [];
            if (empty($fresh)) {
                throw new \RuntimeException('Data RJ tidak ditemukan, simpan dibatalkan.');
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
            $this->muatDariDokumen($fresh);

            $this->appendAdminLogRJ(
                (int) $this->rjNo,
                $logVerb . ' Kriteria Robson — ' . KriteriaRobsonOptions::teksKelompok($entry['kelompok'], $entry['subKelompok']) . ' — ' . (($entry['ttd'] ?? '') ?: '-') . ' (' . $key . ')',
                'MR',
            );
        });
    }

    /* ===============================
     | SIMPAN DRAFT (nyicil, tanpa wajib lengkap)
     =============================== */
    public function saveDraft(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }
        if (!$this->adaIsiInti()) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal salah satu variabel Robson sebelum menyimpan draft.');
            return;
        }

        $this->resetValidation();
        $this->validateWithToast($this->rulesDraft());

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->resetNewForm();
            $this->editingKey = null;
            $this->incrementVersion('modal-kriteria-robson-rj');
            $this->dispatch('toast', type: 'success', message: 'Draft tersimpan — ada di daftar. Klik Lanjutkan Pengisian untuk meneruskan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TTD PETUGAS = FINALIZE (kunci entri)
     | validate() DULU, baru stempel — stempel tak pernah tertinggal di form saat validasi gagal.
     =============================== */
    public function ttdSaya(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        $this->validateWithToast();

        if ($this->kombinasiMustahil()) {
            $this->addError('newForm.riwayatSc', 'Nulipara tidak mungkin mempunyai bekas SC — periksa Paritas / Riwayat SC.');
            $this->dispatch('toast', type: 'error', message: 'Nulipara tidak mungkin mempunyai bekas SC — periksa Paritas / Riwayat SC.');
            return;
        }
        if ($this->kelompokTerhitung() === null) {
            $this->dispatch('toast', type: 'error', message: 'Kelompok Robson belum dapat ditentukan — lengkapi variabelnya.');
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
            $this->incrementVersion('modal-kriteria-robson-rj');
            $this->dispatch('toast', type: 'success', message: 'Kriteria Robson ditandatangani & terkunci.');
        } catch (\Throwable $e) {
            // Gagal tersimpan → cabut stempel, supaya form tidak tampak sudah bertanda tangan.
            $this->hapusTtd();
            $pesan = $e instanceof \RuntimeException ? $e->getMessage() : 'Gagal mengunci: ' . $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $pesan);
        }
    }

    /** Cabut stempel TTD pada form (belum tersimpan). */
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
                $this->muatDariDokumen($fresh);

                $pembukaKunci = auth()->user()->myuser_name ?? '-';
                $this->appendAdminLogRJ((int) $this->rjNo, 'Buka kunci Kriteria Robson (' . $createdAt . ') oleh ' . $pembukaKunci . ' — TTD petugas dicabut', 'MR');
            });

            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }
            $this->incrementVersion('modal-kriteria-robson-rj');
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
    // Muat 1 entri ke form (dipakai edit draft & lihat entri terkunci).
    private function hydrateFormFromEntry(array $entry, string $key): void
    {
        foreach ($this->newForm as $field => $nilaiBawaan) {
            $this->newForm[$field] = (string) ($entry[$field] ?? '');
        }

        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-kriteria-robson-rj');
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

    // Lihat entri terkunci: muat ke form dalam mode read-only.
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
        $this->incrementVersion('modal-kriteria-robson-rj');
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
        foreach ($this->newForm as $field => $nilaiBawaan) {
            $this->newForm[$field] = '';
        }
        $this->layar = 'daftar';   // mengosongkan formulir = kembali ke daftar
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
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
                $this->lockRJRow($this->rjNo);

                $fresh = $this->findDataRJ($this->rjNo) ?: [];
                $fresh[$this->jsonKey] = collect($fresh[$this->jsonKey] ?? [])
                    ->reject(fn($entri) => ($entri['createdAt'] ?? null) === $createdAt)
                    ->values()
                    ->all();

                $this->updateJsonRJ((int) $this->rjNo, $fresh);
                $this->muatDariDokumen($fresh);

                $this->appendAdminLogRJ((int) $this->rjNo, 'Hapus Kriteria Robson — ' . $createdAt, 'MR');
            });

            // Jika entri yang dihapus sedang di form, kosongkan form.
            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }

            $this->incrementVersion('modal-kriteria-robson-rj');
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
            $this->dispatch('toast', type: 'error', message: 'Data Kriteria Robson tidak ditemukan.');
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

            $data = array_merge($pasien, [
                'ttdPath'     => TtdUser::pathBerkasDariKode($entry['ttdCode'] ?? null),
                'dataRi'      => $this->findDataRJ($this->rjNo) ?: [],
                'form'        => $entry,
                'opsiLabel'   => KriteriaRobsonOptions::labels(),
                'identitasRs' => $identitasRs,
                'tglCetak'    => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);
            $pdf = Pdf::loadView('pages.components.modul-dokumen.ri.kriteria-robson-ri.cetak-kriteria-robson-ri-print', ['data' => $data])->setPaper('A4');

            return response()->streamDownload(fn() => print $pdf->output(), 'kriteria-robson-rj-' . ($pasien['regNo'] ?? $this->rjNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline di tab) ══ --}}
    <x-modul-dokumen.kartu judul="Kriteria Robson"
        :jumlah="count($entriList ?? [])"
        satuan="entri"
        :nonaktif="$disabled || !$rjNo">
        <x-slot:deskripsi>Klasifikasi 10 kelompok Robson (WHO) — kelompok ditentukan otomatis dari enam variabel obstetri.</x-slot:deskripsi>
        <div class="overflow-x-auto rounded-2xl border border-hairline dark:border-gray-700">
            <table class="min-w-full text-sm">
                <thead class="bg-surface-card dark:bg-gray-800">
                    <tr class="text-xs font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                        <th class="px-3 py-2 border-b">Tgl Persalinan</th>
                        <th class="px-3 py-2 border-b">Kelompok Robson</th>
                        <th class="px-3 py-2 border-b">Petugas (TTD)</th>
                        <th class="px-3 py-2 text-center border-b">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (collect($entriList)->sortByDesc(fn($entri) => strtotime(strtr(($entri['tglPersalinan'] ?? '') ?: ($entri['createdAt'] ?? ''), '/', '-')))->values()->take(3)->all() as $entri)
                        <tr class="border-b border-hairline dark:border-gray-700">
                            <td class="px-3 py-2 font-medium text-ink dark:text-gray-200">{{ ($entri['tglPersalinan'] ?? '') ?: ($entri['createdAt'] ?? '-') }}</td>
                            <td class="px-3 py-2 text-ink dark:text-gray-200">{{ $this->teksKelompok($entri) }}</td>
                            <td class="px-3 py-2 text-muted dark:text-gray-400">
                                <x-modul-dokumen.status-ttd :nama="$this->entryIsFinal($entri) ? ($entri['ttd'] ?? '') : ''" gaya="polos" />
                            </td>
                            <td class="px-3 py-2 text-center">
                                <x-modul-dokumen.status-entri :final="$this->entryIsFinal($entri)" />
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
    <x-modal name="kriteria-robson-rj" size="full" height="full" focusable>
        <div class="flex flex-col min-h-full"
             wire:key="{{ $this->renderKey('modal-kriteria-robson-rj', [$rjNo ?? 'new']) }}">
            <x-modul-dokumen.header judul="Kriteria Robson"
                ikon="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                jalur="RJ" :jumlah="count($entriList ?? [])" :readOnly="$isFormLocked">
                Klasifikasi 10 kelompok Robson (WHO) — tiap entri = satu persalinan. Diisi Dokter / Bidan.
            </x-modul-dokumen.header>

            {{-- DISPLAY PASIEN — paling atas, mengikuti pola EMR --}}
            <div class="px-4 pt-2">
                <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                    wire:key="kriteria-robson-rj-display-pasien-{{ $rjNo }}" />
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    @php
                        $formReadOnly = $isFormLocked || $viewOnly;
                        $opsiLabel = $this->opsiLabel();
                    @endphp

                    @if ($isFormLocked)
                        <x-modul-dokumen.banner jenis="terkunci" />
                    @endif

                    @if ($viewOnly)
                        <x-modul-dokumen.banner jenis="lihat" />
                    @elseif ($editingKey && !$isFormLocked)
                        <x-modul-dokumen.banner jenis="lanjut" />
                    @endif

                    {{-- ── FORM ENTRI ── --}}
                    @if ($this->diForm())
                    <fieldset @disabled($formReadOnly) class="space-y-4">

                        {{-- 1. Data Persalinan --}}
                        <x-border-form title="1. Data Persalinan">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <x-input-label value="Tgl / Jam Persalinan" />
                                    <div class="flex gap-1 mt-1">
                                        <x-text-input wire:model="newForm.tglPersalinan" placeholder="dd/mm/yyyy HH:mm:ss" class="w-full"
                                            :error="$errors->has('newForm.tglPersalinan')" />
                                        <x-now-button wire:click="setNow('tglPersalinan')" :disabled="$formReadOnly" />
                                    </div>
                                    <x-input-error :messages="$errors->get('newForm.tglPersalinan')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Usia Kehamilan (minggu lengkap)" />
                                    <x-text-input type="number" min="20" max="45" wire:model.live.debounce.500ms="newForm.usiaKehamilanMinggu"
                                        placeholder="mis. 38" class="w-full mt-1"
                                        :error="$errors->has('newForm.usiaKehamilanMinggu')" />
                                    <x-input-error :messages="$errors->get('newForm.usiaKehamilanMinggu')" class="mt-1" />
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 2. Variabel Obstetri (penentu kelompok) --}}
                        <x-border-form title="2. Variabel Obstetri">
                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                                @foreach ([
                                    'paritas' => 'Paritas',
                                    'riwayatSc' => 'Riwayat Sectio Caesarea',
                                    'jumlahJanin' => 'Jumlah Janin',
                                    'presentasiJanin' => 'Presentasi / Letak Janin',
                                    'awalPersalinan' => 'Awal Persalinan',
                                ] as $field => $judulField)
                                    <div>
                                        <x-input-label :value="$judulField" />
                                        <div class="mt-1 space-y-2">
                                            @foreach ($opsiLabel[$field] as $kode => $label)
                                                <x-radio-button name="newForm.{{ $field }}" value="{{ $kode }}"
                                                    :checked="($newForm[$field] ?? '') === (string) $kode"
                                                    :disabled="$formReadOnly"
                                                    label="{{ $label }}" />
                                            @endforeach
                                        </div>
                                        <x-input-error :messages="$errors->get('newForm.' . $field)" class="mt-1" />
                                    </div>
                                @endforeach
                            </div>
                        </x-border-form>

                        {{-- 3. Kelompok Robson (otomatis) --}}
                        @php
                            $kelompokRobson = $viewOnly
                                ? (filled($newForm['kelompok'] ?? '') ? ['kelompok' => $newForm['kelompok'], 'subKelompok' => $newForm['subKelompok'] ?? ''] : null)
                                : $this->kelompokTerhitung();
                        @endphp
                        <x-border-form title="3. Kelompok Robson (ditentukan otomatis)">
                            @if (!$viewOnly && $this->kombinasiMustahil())
                                <div class="px-4 py-3 text-sm font-medium text-red-700 border border-red-200 rounded-lg bg-red-50 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
                                    Nulipara tidak mungkin mempunyai bekas SC — periksa kembali Paritas / Riwayat SC.
                                </div>
                            @elseif ($kelompokRobson)
                                <div class="flex items-start gap-4 px-4 py-3 border rounded-lg border-brand-green/40 bg-brand-green/5 dark:bg-brand-lime/5">
                                    <div class="text-3xl font-bold leading-none text-brand-green dark:text-brand-lime whitespace-nowrap">
                                        {{ filled($kelompokRobson['subKelompok']) ? $kelompokRobson['subKelompok'] : $kelompokRobson['kelompok'] }}
                                    </div>
                                    <div class="text-sm text-ink dark:text-gray-200">
                                        <div class="font-semibold">Kelompok {{ $kelompokRobson['kelompok'] }}</div>
                                        <div>{{ $opsiLabel['kelompok'][$kelompokRobson['kelompok']] ?? '-' }}</div>
                                        @if (filled($kelompokRobson['subKelompok']))
                                            <div class="mt-1 text-muted dark:text-gray-400">Sub-kelompok {{ $kelompokRobson['subKelompok'] }}: {{ $opsiLabel['subKelompok'][$kelompokRobson['subKelompok']] ?? '-' }}</div>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <p class="text-sm text-muted dark:text-gray-400">Kelompok muncul di sini setelah variabel obstetri & usia kehamilan cukup terisi.</p>
                            @endif
                        </x-border-form>

                        {{-- 4. Cara Persalinan & Catatan --}}
                        <x-border-form title="4. Cara Persalinan">
                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Cara Persalinan" />
                                    <div class="mt-1 space-y-2">
                                        @foreach ($opsiLabel['caraPersalinan'] as $kode => $label)
                                            <x-radio-button name="newForm.caraPersalinan" value="{{ $kode }}"
                                                :checked="($newForm['caraPersalinan'] ?? '') === (string) $kode"
                                                :disabled="$formReadOnly"
                                                label="{{ $label }}" />
                                        @endforeach
                                    </div>
                                    <x-input-error :messages="$errors->get('newForm.caraPersalinan')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Catatan" />
                                    <x-textarea wire:model="newForm.catatan" rows="4" class="w-full mt-1" placeholder="Catatan tambahan (opsional), mis. indikasi SC" />
                                </div>
                            </div>
                        </x-border-form>

                        {{-- ══ TTD PETUGAS & KUNCI ══ --}}
                        <x-signature.ttd-petugas :ttd="$newForm['ttd']" :code="$newForm['ttdCode'] ?? ''"
                            :date="$newForm['ttdDate'] ?? ''" :locked="$formReadOnly" sign="ttdSaya" clear="hapusTtd"
                            title="Tanda Tangan Petugas"
                            nameLabel="Petugas (Dokter / Bidan)" dateLabel="Waktu TTD"
                            signLabel="TTD Petugas & Kunci" clearLabel="Batal TTD" />
                        @if (!$formReadOnly)
                            <p class="-mt-2 text-xs text-center text-muted">Menandatangani = mengunci Kriteria Robson ini.</p>
                        @endif
                    </fieldset>

                    {{-- ── DAFTAR ENTRI TERSIMPAN (expandable) ── --}}
                    @endif
                    @unless ($this->diForm())
                    <x-modul-dokumen.tabel-daftar :kolom="['', 'Tgl Persalinan', 'Kelompok Robson', 'Petugas (TTD)', 'Status' => 'text-center', 'Aksi' => 'text-center']">
                                    @forelse (collect($entriList)->sortByDesc(fn($entri) => strtotime(strtr(($entri['tglPersalinan'] ?? '') ?: ($entri['createdAt'] ?? ''), '/', '-')))->values()->all() as $entri)
                                        @php
                                            $isFinal = $this->entryIsFinal($entri);
                                            $rowKey = $entri['createdAt'] ?? '';
                                            $kelompokEntri = (string) ($entri['kelompok'] ?? '');
                                        @endphp
                                        <tbody x-data="{ open: false }" class="border-b border-hairline dark:border-gray-700">
                                            <tr @click="open = !open"
                                                class="cursor-pointer hover:bg-surface-soft dark:hover:bg-gray-800">
                                                <td class="px-2 py-3 text-center align-middle">
                                                    <svg class="w-4 h-4 mx-auto transition-transform text-muted" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </td>
                                                <td class="px-4 py-3 font-semibold align-middle text-ink dark:text-gray-100">
                                                    {{ ($entri['tglPersalinan'] ?? '') ?: ($rowKey ?: '-') }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    <span class="font-semibold whitespace-nowrap text-ink dark:text-gray-100">{{ $this->teksKelompok($entri) }}</span>
                                                    <span class="block text-xs text-muted-soft">{{ $opsiLabel['caraPersalinan'][$entri['caraPersalinan'] ?? ''] ?? 'Cara persalinan belum diisi' }}</span>
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    <x-modul-dokumen.status-ttd :nama="$isFinal ? ($entri['ttd'] ?? '') : ''" />
                                                </td>
                                                <td class="px-4 py-3 text-center align-middle">
                                                    <x-modul-dokumen.status-entri :final="$isFinal" />
                                                </td>
                                                <td class="px-4 py-3 text-center align-middle whitespace-nowrap" @click.stop>
                                                    <x-modul-dokumen.aksi-entri kunci="{{ $rowKey }}" :final="$isFinal" :terkunci="$isFormLocked"
                                                        judulLihat="Lihat detail (read-only)"
                                                        judulBukaKunci="Buka Kunci Kriteria Robson"
                                                        pesanBukaKunci="TTD petugas akan dicabut & entri kembali menjadi Draft — proses TTD diulang dari awal. Lanjutkan?"
                                                        konfirmasiHapus="Yakin hapus entri Kriteria Robson ini?" />
                                                </td>
                                            </tr>

                                            {{-- DETAIL (expand) --}}
                                            <tr x-show="open" x-cloak>
                                                <td colspan="6" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                                    <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-3">
                                                        <div class="md:col-span-3">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Kelompok Robson</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">
                                                                @if ($kelompokEntri !== '')
                                                                    <span class="font-semibold">{{ $this->teksKelompok($entri) }}</span>
                                                                    — {{ $opsiLabel['kelompok'][$kelompokEntri] ?? '-' }}
                                                                @else
                                                                    Belum dapat ditentukan (variabel belum lengkap)
                                                                @endif
                                                            </dd>
                                                        </div>
                                                        @foreach ([
                                                            'paritas' => 'Paritas',
                                                            'riwayatSc' => 'Riwayat SC',
                                                            'jumlahJanin' => 'Jumlah Janin',
                                                            'presentasiJanin' => 'Presentasi / Letak Janin',
                                                            'awalPersalinan' => 'Awal Persalinan',
                                                            'caraPersalinan' => 'Cara Persalinan',
                                                        ] as $field => $judulField)
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">{{ $judulField }}</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $opsiLabel[$field][$entri[$field] ?? ''] ?? '-' }}</dd>
                                                            </div>
                                                        @endforeach
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Usia Kehamilan</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ filled($entri['usiaKehamilanMinggu'] ?? '') ? $entri['usiaKehamilanMinggu'] . ' minggu' : '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Dicatat</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $rowKey ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Petugas (TTD)</dt>
                                                            <dd class="mt-0.5">
                                                                <x-modul-dokumen.status-ttd :nama="$isFinal ? ($entri['ttd'] ?? '') : ''" :waktu="($entri['ttdDate'] ?? '') ?: '-'" gaya="biasa" />
                                                            </dd>
                                                        </div>
                                                        <div class="md:col-span-3">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Catatan</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ ($entri['catatan'] ?? '') ?: '-' }}</dd>
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

            {{-- FOOTER --}}
            <x-modul-dokumen.footer :formulir="$this->diForm()" :terkunci="$isFormLocked"
                :lihat="$viewOnly"
                :mengedit="$editingKey">
                Simpan draft dulu, lalu <strong>kunci</strong> lewat tombol <strong>TTD Petugas &amp; Kunci</strong>.
            </x-modul-dokumen.footer>

        </div>
    </x-modal>
</div>
