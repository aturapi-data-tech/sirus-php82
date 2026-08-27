<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/pelaporan-eso-ri/rm-pelaporan-eso-ri-actions.blade.php
//
// RM 37 — Formulir Pelaporan Efek Samping Obat (ESO), Rawat Inap.
// Multi-entri: satu pasien bisa mengalami lebih dari satu ESO dalam satu perawatan.
//
// TTD Pelapor = aksi TERAKHIR yang sekaligus MENGUNCI entri (aturan modul-dokumen #1);
// tidak ada TTD pasien/keluarga di formulir ini. Footer cukup "Simpan Draft".
//
// Bentuk kolom mengikuti Form Kuning MESO BPOM 2026, bukan RM 37 cetakan lama —
// lihat App\Support\Options\EsoOptions untuk daftar pilihan & alasannya.

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
use App\Support\Options\EsoOptions;
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

    public array $form = [];

    public ?string $editingKey = null;   // id entri yang sedang diedit; null = entri baru

    // Layar aktif di modal: 'daftar' (grid entri) atau 'form' (tambah/edit/lihat).
    // Formulir sengaja tidak nongkrong bersama daftarnya: dulu ia ikut tampil terus lalu
    // dikosongkan diam-diam sesudah tersimpan, dan petugas yang mengira itu masih formulir
    // yang tadi diisi mengetik ulang — tersimpan sebagai draft baru.
    public string $layar = 'daftar';
    public bool $viewOnly = false;       // entri terkunci ditampilkan read-only

    /**
     * Entri baris obat — pola "form entri di atas, daftar di bawah" seperti
     * Rekonsiliasi Obat. Baris hanya masuk daftar lewat tombol Tambah, jadi tidak
     * ada baris kosong menggantung di tabel yang lolos sampai cetak.
     */
    public array $formEntryObat = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-pelaporan-eso-ri'];

    /* Opsi dari sumber tunggal — dipasang ke properti supaya markup tidak perlu
       menyebut nama class (aturan naming-conventions §2). */
    public array $kesudahanOptions = EsoOptions::KESUDAHAN;
    public array $kelaminOptions = EsoOptions::KELAMIN;
    public array $statusKehamilanOptions = EsoOptions::STATUS_KEHAMILAN;
    public array $kondisiMenyertaiOptions = EsoOptions::KONDISI_MENYERTAI;
    public array $caraPemberianOptions = EsoOptions::CARA_PEMBERIAN;
    public array $bentukSediaanOptions = EsoOptions::BENTUK_SEDIAAN;

    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-pelaporan-eso-ri']);

        $this->form = $this->defaultForm();
        $this->formEntryObat = EsoOptions::barisObatKosong();

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->dataDaftarRi['pelaporanEsoRI'] ??= [];
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
            $this->dataDaftarRi['pelaporanEsoRI'] ??= [];
            $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;
        }

        $this->resetFormEso();
        $this->prefillDariEmr();

        $this->layar = 'daftar';

        $this->dispatch('open-modal', name: "rm-pelaporan-eso-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-pelaporan-eso-ri-{$this->riHdrNo}");
    }

    /* ===============================
     | BENTUK FORM
     =============================== */
    private function defaultForm(): array
    {
        return [
            'tglLaporan' => '',

            'penderita' => [
                'namaSingkatan' => '',
                'alamat' => '',
                'umur' => '',
                'suku' => '',
                'beratBadan' => '',
                'pekerjaan' => '',
                'tglMrs' => '',
                'kelamin' => '',
                'statusKehamilan' => '',
                'penyakitUtama' => '',
                'kesudahanPenyakitUtama' => '',
                'kondisiMenyertai' => [],
                'kondisiMenyertaiLainnya' => '',
            ],

            'eso' => [
                'manifestasi' => '',
                'masalahMutuProduk' => '',
                'tglMulaTerjadi' => '',
                'kesudahanEso' => '',
                'tglKesudahanEso' => '',
                'riwayatEso' => '',
            ],

            'obat' => [],

            'keteranganTambahan' => '',
            'dataLaboratorium' => '',
            'tglPemeriksaanLab' => '',

            'pengirim' => [
                'nama' => '',
                'keahlian' => '',
                'instansi' => '',
                'alamat' => '',
                'telepon' => '',
            ],

            'ttd' => [
                'petugasName' => '',
                'petugasCode' => '',
                'petugasDate' => '',
            ],
        ];
    }

    /**
     * Identitas penderita (nama, alamat, umur, suku, pekerjaan, kelamin) TIDAK diketik
     * petugas — semuanya sudah ada di master pasien & data RI, sama seperti yang tampil
     * di kartu Display Pasien di atas modal.
     *
     * TAPI hasilnya tetap DI-SNAPSHOT ke entri, bukan dibaca ulang saat cetak. Laporan
     * ESO adalah dokumen bertanda tangan: kalau alamat/pekerjaan pasien berubah tahun
     * depan, cetak ulang laporan lama harus tetap menampilkan data SAAT laporan dibuat.
     * Membaca master langsung di blade cetak akan diam-diam mengubah isi dokumen yang
     * sudah ditandatangani.
     *
     * Dipanggil tiap simpan (draft & kunci), jadi selalu memotret kondisi terkini
     * sampai entri dikunci.
     */
    private function snapshotIdentitasPenderita(): void
    {
        $pasien = $this->findDataMasterPasien($this->regNo ?? '')['pasien'] ?? [];

        $potretRekamMedis = fn(string $path, ?string $nilai) => data_set($this->form, $path, trim((string) $nilai));

        $potretRekamMedis('penderita.namaSingkatan', data_get($pasien, 'namaPanggilan') ?: data_get($pasien, 'regName', ''));
        $potretRekamMedis('penderita.suku', data_get($pasien, 'suku', ''));
        $potretRekamMedis('penderita.pekerjaan', data_get($pasien, 'pekerjaan.pekerjaanDesc', ''));

        $potretRekamMedis('penderita.alamat', collect([data_get($pasien, 'identitas.alamat'), data_get($pasien, 'identitas.desaName'), data_get($pasien, 'identitas.kecamatanName'), data_get($pasien, 'identitas.kotaName')])
            ->filter(fn($bagian) => filled($bagian))
            ->implode(', '));

        $potretRekamMedis('penderita.umur', trim(implode(' ', array_filter([
            filled(data_get($pasien, 'thn')) ? data_get($pasien, 'thn') . ' Thn' : null,
            filled(data_get($pasien, 'bln')) ? data_get($pasien, 'bln') . ' Bln' : null,
        ]))));

        // jenisKelaminId master: 1 = Laki-laki, 2 = Perempuan. Nilai lain (0/3/4)
        // sengaja TIDAK dipetakan — formulir BPOM cuma mengenal Pria/Wanita, dan
        // menebak di sini berarti melaporkan jenis kelamin yang tidak diketahui.
        $jenisKelaminId = (string) data_get($pasien, 'jenisKelamin.jenisKelaminId', '');
        if ($jenisKelaminId === '1') {
            $potretRekamMedis('penderita.kelamin', 'Pria');
        } elseif ($jenisKelaminId === '2') {
            $potretRekamMedis('penderita.kelamin', 'Wanita');
        }

        $potretRekamMedis('penderita.tglMrs', data_get($this->dataDaftarRi, 'entryDate', ''));
    }

    /**
     * Prefill kolom yang MASIH diketik petugas — fill-only supaya koreksi manual
     * tidak ditimpa saat modal dibuka ulang.
     */
    private function prefillDariEmr(): void
    {
        $isiJikaKosong = function (string $path, string $nilai): void {
            if (trim((string) data_get($this->form, $path, '')) === '' && trim($nilai) !== '') {
                data_set($this->form, $path, trim($nilai));
            }
        };

        $isiJikaKosong('penderita.penyakitUtama', (string) data_get($this->dataDaftarRi, 'pengkajianAwalPasienRawatInap.bagian1DataUmum.diagnosaMasuk', ''));
        $isiJikaKosong('penderita.beratBadan', (string) data_get($this->dataDaftarRi, 'pengkajianAwalPasienRawatInap.bagian4PemeriksaanFisik.tandaVital.bb', ''));

        // Pengirim = petugas yang membuat laporan + identitas RS
        $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_address', 'int_city', 'int_phone1')->first();
        $isiJikaKosong('pengirim.nama', (string) (auth()->user()->myuser_name ?? ''));
        $isiJikaKosong('pengirim.keahlian', (string) (auth()->user()->myuser_profesi ?? ''));
        $isiJikaKosong('pengirim.instansi', (string) ($identitasRs->int_name ?? ''));
        $isiJikaKosong('pengirim.alamat', trim(($identitasRs->int_address ?? '') . ' ' . ($identitasRs->int_city ?? '')));
        $isiJikaKosong('pengirim.telepon', (string) ($identitasRs->int_phone1 ?? ''));

        if (empty($this->form['tglLaporan'])) {
            $this->form['tglLaporan'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        }

        // Identitas ikut dipotret sejak awal supaya panel ringkasan di form
        // menampilkan persis apa yang nanti tercetak.
        $this->snapshotIdentitasPenderita();
    }

    public function setTglLaporan(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->form['tglLaporan'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | TABEL OBAT
     =============================== */
    public function addBarisObat(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }

        // Yang WAJIB dipilih dari sudut pandang penilaian kausalitas ESO (WHO-UMC):
        // obat apa (nama), seberapa banyak & lewat mana (dosis + cara), dan KAPAN
        // mulai dipakai — hubungan waktu itu inti penilaiannya. Tanpa keempatnya,
        // laporan tidak bisa dievaluasi Pusat MESO.
        //
        // SENGAJA tidak wajib: Bentuk Sediaan & No. Bets (hanya penting kalau
        // masalahnya mutu produk), Tgl. Akhir (sering belum ada karena obat masih
        // berjalan saat ESO dilaporkan), dan Indikasi (bisa disimpulkan dari
        // penyakit utama). Mewajibkannya cuma memancing petugas mengisi asal.
        //
        // validate() didahulukan supaya field kosong tetap ditandai merah
        // (guard/early-return sebelum validate bikin border error tak muncul).
        $this->validateWithToast(
            [
                'formEntryObat.namaObat' => ['required', 'string', 'max:200'],
                'formEntryObat.cara' => ['required', 'string'],
                'formEntryObat.dosisWaktu' => ['required', 'string', 'max:100'],
                'formEntryObat.tglMula' => ['required', 'date_format:d/m/Y'],
                'formEntryObat.tglAkhir' => ['nullable', 'date_format:d/m/Y', 'after_or_equal:formEntryObat.tglMula'],
            ],
            [
                'formEntryObat.tglMula.date_format' => 'Format Tgl. Mula harus dd/mm/yyyy.',
                'formEntryObat.tglAkhir.date_format' => 'Format Tgl. Akhir harus dd/mm/yyyy.',
                'formEntryObat.tglAkhir.after_or_equal' => 'Tgl. Akhir tidak boleh sebelum Tgl. Mula.',
            ],
            [
                'formEntryObat.namaObat' => 'Nama Obat',
                'formEntryObat.cara' => 'Cara Pemberian',
                'formEntryObat.dosisWaktu' => 'Dosis / Waktu',
                'formEntryObat.tglMula' => 'Tgl. Mula',
                'formEntryObat.tglAkhir' => 'Tgl. Akhir',
            ],
        );

        $namaObatBaru = strtolower(trim((string) $this->formEntryObat['namaObat']));
        $sudahAda = collect($this->form['obat'] ?? [])
            ->contains(fn($barisObat) => strtolower(trim((string) ($barisObat['namaObat'] ?? ''))) === $namaObatBaru);

        if ($sudahAda) {
            $this->dispatch('toast', type: 'error', message: 'Obat sudah ada dalam daftar.');
            return;
        }

        // array_replace_recursive: baris selalu berbentuk lengkap walau entri
        // kehilangan key (mis. setelah struktur EsoOptions bertambah).
        $this->form['obat'][] = array_replace_recursive(EsoOptions::barisObatKosong(), $this->formEntryObat);
        $this->formEntryObat = EsoOptions::barisObatKosong();
    }

    public function removeBarisObat(int $index): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        if (!isset($this->form['obat'][$index])) {
            return;
        }
        unset($this->form['obat'][$index]);
        $this->form['obat'] = array_values($this->form['obat']);
    }

    /** Toggle keanggotaan kondisi menyertai (multi-pilih). */
    public function toggleKondisiMenyertai(string $kodeKondisi): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $terpilih = (array) data_get($this->form, 'penderita.kondisiMenyertai', []);
        $terpilih = in_array($kodeKondisi, $terpilih, true)
            ? array_values(array_filter($terpilih, fn($nilai) => $nilai !== $kodeKondisi))
            : array_merge($terpilih, [$kodeKondisi]);
        data_set($this->form, 'penderita.kondisiMenyertai', $terpilih);
    }

    /* ===============================
     | SIKLUS ENTRI
     =============================== */
    public function entryIsFinal(array $entri): bool
    {
        return (bool) ($entri['finalized'] ?? false);
    }

    private function esoRules(): array
    {
        return [
            [
                'form.tglLaporan' => ['required', 'date_format:d/m/Y H:i:s'],
                'form.penderita.namaSingkatan' => ['required', 'string', 'max:150'],
                'form.penderita.kelamin' => ['required', 'string'],
                'form.eso.manifestasi' => ['required', 'string', 'max:2000'],
                'form.eso.tglMulaTerjadi' => ['required', 'date_format:d/m/Y'],
                'form.eso.tglKesudahanEso' => ['nullable', 'date_format:d/m/Y', 'after_or_equal:form.eso.tglMulaTerjadi'],
                'form.tglPemeriksaanLab' => ['nullable', 'date_format:d/m/Y'],
                'form.obat' => ['required', 'array', 'min:1'],
                'form.pengirim.nama' => ['required', 'string', 'max:150'],
            ],
            [
                'form.obat.required' => 'Minimal satu obat harus diisi — laporan ESO tanpa obat tidak bisa dievaluasi.',
                'form.obat.min' => 'Minimal satu obat harus diisi — laporan ESO tanpa obat tidak bisa dievaluasi.',
                'form.penderita.namaSingkatan.required' => 'Nama pasien belum ada di Master Pasien.',
                'form.penderita.kelamin.required' => 'Jenis kelamin pasien belum diisi di Master Pasien.',
                'form.tglLaporan.date_format' => 'Format Tanggal Laporan harus dd/mm/yyyy hh:mm:ss.',
                'form.eso.tglMulaTerjadi.date_format' => 'Format tanggal harus dd/mm/yyyy.',
                'form.eso.tglKesudahanEso.date_format' => 'Format tanggal harus dd/mm/yyyy.',
                'form.eso.tglKesudahanEso.after_or_equal' => 'Tgl. Kesudahan ESO tidak boleh sebelum Tgl. Mula Terjadi.',
                'form.tglPemeriksaanLab.date_format' => 'Format tanggal harus dd/mm/yyyy.',
            ],
            [
                'form.tglLaporan' => 'Tanggal Laporan',
                'form.penderita.namaSingkatan' => 'Nama (Singkatan)',
                'form.penderita.kelamin' => 'Kelamin',
                'form.eso.manifestasi' => 'Bentuk / Manifestasi ESO',
                'form.eso.tglMulaTerjadi' => 'Saat / Tanggal Mula Terjadi',
                'form.eso.tglKesudahanEso' => 'Tgl. Kesudahan ESO',
                'form.tglPemeriksaanLab' => 'Tgl. Pemeriksaan Lab',
                'form.pengirim.nama' => 'Nama Pengirim',
            ],
        ];
    }

    private function buildEntry(string $id, bool $finalized): array
    {
        $existing = collect($this->dataDaftarRi['pelaporanEsoRI'] ?? [])->firstWhere('id', $id);

        return [
            'id' => $id,
            'created_at' => $existing['created_at'] ?? Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s'),
            'created_by' => $existing['created_by'] ?? [
                'code' => auth()->user()->myuser_code ?? '',
                'name' => auth()->user()->myuser_name ?? '',
            ],
            'form' => $this->form,
            'finalized' => $finalized,
        ];
    }

    private function persistEntry(string $id, bool $finalized, string $logVerb): void
    {
        $entry = $this->buildEntry($id, $finalized);

        DB::transaction(function () use ($entry, $id, $logVerb) {
            $this->lockRIRow($this->riHdrNo);

            $fresh = $this->findDataRI($this->riHdrNo) ?? [];
            if (!isset($fresh['pelaporanEsoRI']) || !is_array($fresh['pelaporanEsoRI'])) {
                $fresh['pelaporanEsoRI'] = [];
            }

            $daftarEntri = $fresh['pelaporanEsoRI'];
            $index = collect($daftarEntri)->search(fn($item) => ($item['id'] ?? null) === $id);
            if ($index === false) {
                $daftarEntri[] = $entry;
            } else {
                if ($this->entryIsFinal($daftarEntri[$index])) {
                    throw new \RuntimeException('Entri sudah terkunci, tidak dapat diubah.');
                }
                $daftarEntri[$index] = $entry;
            }
            $fresh['pelaporanEsoRI'] = array_values($daftarEntri);

            $this->updateJsonRI((int) $this->riHdrNo, $fresh);
            $this->dataDaftarRi = $fresh;

            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Pelaporan ESO — ' . ($entry['form']['tglLaporan'] ?? '-'), 'MR');
        });
    }

    public function saveDraft(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        if (empty($this->form['tglLaporan'])) {
            $this->form['tglLaporan'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        }

        $this->snapshotIdentitasPenderita();

        try {
            $id = $this->editingKey ?: (string) Str::uuid();
            $this->persistEntry($id, false, 'Simpan draft');
            $this->editingKey = $id;
            $this->incrementVersion('modal-pelaporan-eso-ri');
            $this->dispatch('toast', type: 'success', message: 'Draft laporan ESO tersimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /** TTD Pelapor = aksi terakhir; sekaligus mengunci entri. */
    public function ttdPetugas(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        if (empty($this->form['tglLaporan'])) {
            $this->form['tglLaporan'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        }

        // validate() DIDAHULUKAN supaya field kosong ditandai merah — jangan
        // short-circuit sebelum validate.
        [$rules, $messages, $attributes] = $this->esoRules();
        $this->validateWithToast($rules, $messages, $attributes);

        $this->form['ttd']['petugasName'] = auth()->user()->myuser_name ?? '';
        $this->form['ttd']['petugasCode'] = auth()->user()->myuser_code ?? '';
        $this->form['ttd']['petugasDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $this->snapshotIdentitasPenderita();

        try {
            $id = $this->editingKey ?: (string) Str::uuid();
            $this->persistEntry($id, true, 'Kunci (TTD Pelapor)');
            $this->resetFormEso();
            $this->incrementVersion('modal-pelaporan-eso-ri');
            $this->dispatch('toast', type: 'success', message: 'Laporan ESO ditandatangani pelapor dan terkunci.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    private function hydrateFormFromEntry(array $entri): void
    {
        // array_replace_recursive: record lama yang belum punya key baru tetap aman.
        $this->form = array_replace_recursive($this->defaultForm(), (array) ($entri['form'] ?? []));
        $this->form['obat'] = array_values((array) data_get($entri, 'form.obat', []));
    }

    public function editEntry(string $id): void
    {
        $entri = collect($this->dataDaftarRi['pelaporanEsoRI'] ?? [])->firstWhere('id', $id);
        if (!$entri) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }
        if ($this->entryIsFinal($entri)) {
            $this->dispatch('toast', type: 'error', message: 'Entri sudah terkunci — gunakan Lihat, atau minta Buka Kunci.');
            return;
        }

        $this->hydrateFormFromEntry($entri);
        $this->editingKey = $id;
        $this->viewOnly = false;
        $this->resetValidation();
        $this->incrementVersion('modal-pelaporan-eso-ri');
    }

    public function viewEntry(string $id): void
    {
        $entri = collect($this->dataDaftarRi['pelaporanEsoRI'] ?? [])->firstWhere('id', $id);
        if (!$entri) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }

        $this->hydrateFormFromEntry($entri);
        $this->editingKey = $id;
        $this->viewOnly = true;
        $this->resetValidation();
        $this->incrementVersion('modal-pelaporan-eso-ri');
    }

    public function cancelEdit(): void
    {
        $this->resetFormEso();
        $this->incrementVersion('modal-pelaporan-eso-ri');
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

    public function removeEntry(string $id): void
    {
        // Guard SERVER — guard blade saja bisa ditembus lewat wire:click.
        if (!auth()->user()?->can('dokumen.hapus')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang menghapus entri.');
            return;
        }
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        try {
            DB::transaction(function () use ($id) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $daftarEntri = $fresh['pelaporanEsoRI'] ?? [];

                $entriDihapus = collect($daftarEntri)->firstWhere('id', $id);
                $daftarBaru = array_values(array_filter($daftarEntri, fn($item) => ($item['id'] ?? null) !== $id));
                if (count($daftarBaru) === count($daftarEntri)) {
                    throw new \RuntimeException('Data tidak ditemukan atau sudah dihapus.');
                }

                $fresh['pelaporanEsoRI'] = $daftarBaru;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Pelaporan ESO — ' . ($entriDihapus['form']['tglLaporan'] ?? '-'), 'MR');
            });

            if ($this->editingKey === $id) {
                $this->cancelEdit();
            }
            $this->incrementVersion('modal-pelaporan-eso-ri');
            $this->dispatch('toast', type: 'success', message: 'Laporan ESO berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BUKA KUNCI — hanya Admin / Manager (Gate dokumen.bukaKunci).
     | Mencabut kunci + TTD pelapor; entri kembali draft untuk dikoreksi.
     =============================== */
    private function bolehBukaKunci(): bool
    {
        return (bool) auth()->user()?->can('dokumen.bukaKunci');
    }

    public function bukaKunci(string $id): void
    {
        if (!$this->bolehBukaKunci()) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Admin / Manager yang dapat membuka kunci.');
            return;
        }
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang — form read-only.');
            return;
        }

        try {
            DB::transaction(function () use ($id) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $daftarEntri = $fresh['pelaporanEsoRI'] ?? [];
                $index = collect($daftarEntri)->search(fn($item) => ($item['id'] ?? null) === $id);
                if ($index === false) {
                    throw new \RuntimeException('Entri tidak ditemukan.');
                }

                $daftarEntri[$index]['finalized'] = false;
                $daftarEntri[$index]['form']['ttd']['petugasName'] = '';
                $daftarEntri[$index]['form']['ttd']['petugasCode'] = '';
                $daftarEntri[$index]['form']['ttd']['petugasDate'] = '';

                $fresh['pelaporanEsoRI'] = array_values($daftarEntri);
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;

                $this->appendAdminLogRI(
                    (int) $this->riHdrNo,
                    'Buka kunci Pelaporan ESO — entri ' . ($daftarEntri[$index]['form']['tglLaporan'] ?? $id) . ' (oleh ' . (auth()->user()->myuser_name ?? '-') . ')',
                    'MR',
                );
            });

            $this->incrementVersion('modal-pelaporan-eso-ri');
            $this->dispatch('toast', type: 'success', message: 'Kunci dibuka — entri kembali draft & TTD pelapor dicabut.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuka kunci: ' . $e->getMessage());
        }
    }

    public function cetak(string $id)
    {
        $entry = collect($this->dataDaftarRi['pelaporanEsoRI'] ?? [])->firstWhere('id', $id);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data laporan tidak ditemukan.');
            return;
        }

        try {
            $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
            $pasien = $this->findDataMasterPasien($this->regNo ?? '')['pasien'] ?? [];

            $ttdPetugasPath = null;
            $petugasCode = data_get($entry, 'form.ttd.petugasCode') ?: data_get($entry, 'created_by.code');
            if ($petugasCode) {
                $ttdRelativePath = DB::table('users')->where('myuser_code', $petugasCode)->value('myuser_ttd_image');
                if (!empty($ttdRelativePath) && file_exists(public_path('storage/' . $ttdRelativePath))) {
                    $ttdPetugasPath = public_path('storage/' . $ttdRelativePath);
                }
            }

            $data = array_merge($pasien, [
                'dataRi' => $this->dataDaftarRi,
                'entry' => $entry,
                'identitasRs' => $identitasRs,
                'ttdPetugasPath' => $ttdPetugasPath,
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
                'opsiLabel' => EsoOptions::labels(),
            ]);

            set_time_limit(300);

            $pdf = Pdf::loadView('pages.components.modul-dokumen.ri.pelaporan-eso-ri.cetak-pelaporan-eso-ri-print', ['data' => $data])->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak Pelaporan ESO.');
            return response()->streamDownload(fn() => print $pdf->output(), 'pelaporan-eso-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

    public function resetFormEso(): void
    {
        $this->form = $this->defaultForm();
        $this->formEntryObat = EsoOptions::barisObatKosong();
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();
        $this->layar = 'daftar';   // mengosongkan formulir = kembali ke daftar
    }
};
?>

<div>
    {{-- ══ RINGKASAN + TOMBOL ══ --}}
    @php $esoCount = count($dataDaftarRi['pelaporanEsoRI'] ?? []); @endphp
    <div class="p-5 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-2">
                {{-- JUDUL KARTU SEBARIS — judul · badge · deskripsi --}}
                <div class="flex items-baseline flex-1 gap-2 min-w-0">
                    <h3 class="truncate shrink-0 text-base font-semibold text-ink dark:text-gray-200">Pelaporan Efek Samping Obat</h3>
                    <x-badge variant="info">RM 37</x-badge>
                    @if ($esoCount > 0)
                        <x-badge variant="success">{{ $esoCount }} entri</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                    <p class="hidden truncate text-sm text-muted sm:block dark:text-gray-400">Formulir kuning MESO: data penderita, manifestasi efek samping, daftar obat yang dicurigai, lalu ditandatangani pelapor. Bentuk kolom mengikuti Form Kuning BPOM 2026 supaya bisa langsung dilaporkan ke e-MESO.</p>
                </div>
            </div>
            <div class="flex shrink-0">
                <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                    wire:target="openModal" :disabled="!$riHdrNo" class="gap-2">
                    <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        Buka Pelaporan ESO
                    </span>
                    <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                        <x-loading class="w-4 h-4" /> Memuat...
                    </span>
                </x-primary-button>
            </div>
        </div>
        {{-- PRATINJAU ENTRI DI KARTU — ringkasan entri terbaru, tanpa perlu membuka modal --}}
            <div class="mt-3 overflow-x-auto rounded-2xl border border-hairline dark:border-gray-700">
                <table class="ds-table">
                    <thead class="bg-surface-card dark:bg-gray-800">
                        <tr class="text-xs font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                            <th>Tgl. Laporan</th>
                            <th>Manifestasi ESO</th>
                            <th class="ds-c w-24">Jml Obat</th>
                            <th>Pelapor</th>
                            <th class="ds-c w-24">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (array_slice(array_reverse($dataDaftarRi['pelaporanEsoRI'] ?? [] ?? []), 0, 3) as $indexEntri)
                            @php
                                $idEntri = $entri['id'] ?? null;
                                $isFinal = (bool) ($entri['finalized'] ?? false);
                                $manifestasi = (string) data_get($entri, 'form.eso.manifestasi', '');
                                $jumlahObat = count((array) data_get($entri, 'form.obat', []));
                                $obatDicurigai = collect((array) data_get($entri, 'form.obat', []))
                                    ->filter(fn($baris) => ($baris['dicurigai'] ?? 'Tidak') === 'Ya')
                                    ->count();
                            @endphp
                            <tr class="border-t border-hairline dark:border-gray-800">
                                <td class="ds-td-strong">{{ data_get($entri, 'form.tglLaporan', '-') }}</td>
                                <td>
                                    <div class="max-w-md truncate">{{ $manifestasi !== '' ? $manifestasi : '-' }}</div>
                                </td>
                                <td class="ds-c">
                                    {{ $jumlahObat }}
                                    @if ($obatDicurigai > 0)
                                        <div class="text-muted dark:text-gray-400">{{ $obatDicurigai }} dicurigai</div>
                                    @endif
                                </td>
                                <td>{{ data_get($entri, 'form.ttd.petugasName') ?: data_get($entri, 'created_by.name', '-') }}</td>
                                <td class="ds-c">
                                    @if ($isFinal)
                                        <x-badge variant="success">Terkunci</x-badge>
                                    @else
                                        <x-badge variant="warning">Draft</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-6 text-center text-muted-soft">Belum ada data tersimpan</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (count($dataDaftarRi['pelaporanEsoRI'] ?? []) > 3)
                <p class="mt-2 text-xs italic text-muted-soft">+{{ count($dataDaftarRi['pelaporanEsoRI'] ?? []) - 3 }} entri lain — buka untuk melihat semua.</p>
            @endif
    </div>

    {{-- ══ MODAL ══ --}}
    <x-modal name="rm-pelaporan-eso-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]">
            {{-- JUDUL + TOMBOL TUTUP SEBARIS — judul di kiri, X di kanan, paling atas modal --}}
            <div
                class="flex items-center justify-between gap-4 px-6 py-4 border-b border-hairline bg-surface-soft dark:border-gray-700">
                <div class="flex items-center gap-2.5">
                    <h2 class="text-sm truncate shrink-0 font-semibold text-ink dark:text-gray-100">
                        Formulir Pelaporan Efek Samping Obat
                        <span class="block text-sm font-normal text-muted dark:text-gray-400">
                            RM 37 &middot; mengikuti Form Kuning MESO BPOM
                        </span>
                    </h2>
                    @if ($esoCount > 0)
                        <x-badge variant="info">{{ $esoCount }} tersimpan</x-badge>
                    @endif
                    @if ($isFormLocked)
                        <x-badge variant="danger">Read Only</x-badge>
                    @endif
                <x-icon-button color="gray" type="button" wire:click="closeModal" class="ml-2 shrink-0">
                    <span class="sr-only">Close</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </x-icon-button>
                </div>
            </div>

            {{-- DISPLAY PASIEN — paling atas, mengikuti pola EMR --}}
            <div class="px-4 pt-2">
                <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                    wire:key="pelaporan-eso-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />
            </div>

            <div class="flex-1 p-4 space-y-4 sm:p-6"
                wire:key="{{ $this->renderKey('modal-pelaporan-eso-ri', [$riHdrNo ?? 'new']) }}">

                @php $formReadOnly = $isFormLocked || $viewOnly; @endphp

                @if ($isFormLocked)
                    <div
                        class="flex items-center gap-2 px-4 py-2.5 text-sm border rounded-lg bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                        Pasien sudah pulang — form dalam mode <strong>read-only</strong>.
                    </div>
                @endif

                @if ($viewOnly)
                    <div
                        class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium border rounded-lg text-sky-700 bg-sky-50 border-sky-200 dark:bg-sky-900/20 dark:border-sky-600 dark:text-sky-300">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Menampilkan entri terkunci (hanya lihat) — klik <strong>Selesai Melihat</strong> untuk kembali
                        ke entri baru.
                    </div>
                @elseif ($editingKey && !$isFormLocked)
                    <div
                        class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium border rounded-lg text-blue-700 bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-600 dark:text-blue-300">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5" />
                        </svg>
                        Melanjutkan entri draft — klik <strong>Batal Edit</strong> untuk memulai entri baru.
                    </div>
                @endif

                {{-- ══════ BLOK 1 — PENDERITA ══════ --}}
                @if ($this->diForm())
                <x-border-form title="Penderita" align="start" bgcolor="bg-surface-soft" :collapsible="true"
                    :open="true">
                    <div class="mt-3 space-y-3">

                        {{-- Identitas penderita (nama, umur, suku, pekerjaan, kelamin, alamat,
                             tgl MRS) TIDAK ditampilkan di sini: sudah ada di kartu Display
                             Pasien di atas modal. Nilainya tetap dipotret ke entri lewat
                             snapshotIdentitasPenderita() supaya cetak ulang tetap setia. --}}
                        @if ($errors->has('form.penderita.namaSingkatan') || $errors->has('form.penderita.kelamin'))
                            <div
                                class="flex items-start gap-2 px-4 py-2.5 text-sm border rounded-lg bg-red-50 border-red-200 text-red-700 dark:bg-red-900/20 dark:border-red-700 dark:text-red-300">
                                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                                </svg>
                                <span>
                                    Data pasien di master belum lengkap (nama / jenis kelamin), jadi laporan belum
                                    bisa ditandatangani. Lengkapi dulu lewat <strong>Master Pasien</strong>, lalu
                                    buka ulang formulir ini.
                                </span>
                            </div>
                        @endif

                        {{-- Baris 1 — waktu & kondisi fisik. Status kehamilan ikut di sini
                             karena sama-sama data singkat; kolomnya hanya muncul untuk
                             pasien perempuan, jadi tidak ada kolom kosong yang mubazir. --}}
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <x-input-label value="Tanggal Laporan" :required="true" />
                                <div class="flex items-center gap-2 mt-1">
                                    <x-text-input wire:model="form.tglLaporan" :disabled="$formReadOnly"
                                        :error="$errors->has('form.tglLaporan')" class="w-full px-2" />
                                    @unless ($formReadOnly)
                                        <x-now-button wire:click="setTglLaporan" />
                                    @endunless
                                </div>
                                <x-input-error :messages="$errors->get('form.tglLaporan')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label value="Berat Badan (kg)" />
                                <x-text-input wire:model="form.penderita.beratBadan" :disabled="$formReadOnly"
                                    class="w-full px-2 mt-1" />
                            </div>

                            @if (($form['penderita']['kelamin'] ?? '') === 'Wanita')
                                <div class="lg:col-span-2">
                                    <x-input-label value="Status Kehamilan" />
                                    <div class="flex flex-wrap items-center gap-4 mt-2">
                                        @foreach ($statusKehamilanOptions as $opsiHamil)
                                            <x-radio-button :label="$opsiHamil" :value="$opsiHamil"
                                                name="esoStatusKehamilan"
                                                wire:model.live="form.penderita.statusKehamilan"
                                                :disabled="$formReadOnly" />
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Satu baris: penyakit utama, kesudahannya, dan kondisi penyerta.
                             Ketiganya menggambarkan KONDISI pasien saat ESO terjadi, jadi
                             dibaca sekali pandang tanpa perlu menggulung layar. --}}
                        <div class="pt-3 border-t border-hairline dark:border-gray-700">
                            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

                                <div>
                                    <x-input-label value="Penyakit Utama" />
                                    <x-textarea wire:model="form.penderita.penyakitUtama" :disabled="$formReadOnly"
                                        rows="9" class="w-full mt-1" />
                                </div>

                                <div>
                                    <x-input-label value="Kesudahan Penyakit Utama" />
                                    <div class="mt-2 space-y-1.5">
                                        @foreach ($kesudahanOptions as $opsiKesudahan)
                                            <x-radio-button :label="$opsiKesudahan" :value="$opsiKesudahan"
                                                name="esoKesudahanPenyakit"
                                                wire:model.live="form.penderita.kesudahanPenyakitUtama"
                                                :disabled="$formReadOnly" />
                                        @endforeach
                                    </div>
                                </div>

                                <div>
                                    <x-input-label value="Penyakit / Kondisi Lain yang Menyertai" />
                                    {{-- Multi-pilih pakai x-toggle Mode 2 (:current + wireClick):
                                         keanggotaan array dihitung di server, jadi tidak ada
                                         state Alpine lokal yang bisa melenceng dari data. --}}
                                    <div class="mt-2 space-y-1.5">
                                        @foreach ($kondisiMenyertaiOptions as $kunciKondisi => $labelKondisi)
                                            <div
                                                class="flex items-center justify-between gap-3 px-3 py-1.5 border rounded-lg bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                                                <span
                                                    class="text-sm text-body dark:text-gray-300">{{ $labelKondisi }}</span>
                                                <x-toggle :current="in_array($kunciKondisi, $form['penderita']['kondisiMenyertai'] ?? [], true) ? 'Ya' : 'Tidak'"
                                                    trueValue="Ya" falseValue="Tidak"
                                                    :label="in_array($kunciKondisi, $form['penderita']['kondisiMenyertai'] ?? [], true) ? 'Ya' : 'Tidak'"
                                                    :wireClick="'toggleKondisiMenyertai(\'' . $kunciKondisi . '\')'"
                                                    :disabled="$formReadOnly" />
                                            </div>
                                        @endforeach
                                    </div>

                                    @if (in_array('lainLain', $form['penderita']['kondisiMenyertai'] ?? [], true))
                                        <x-text-input wire:model="form.penderita.kondisiMenyertaiLainnya"
                                            :disabled="$formReadOnly" placeholder="Sebutkan kondisi lain-lain..."
                                            class="w-full px-2 mt-2" />
                                    @endif
                                </div>

                            </div>
                        </div>
                    </div>
                </x-border-form>

                {{-- ══════ BLOK 2 — EFEK SAMPING OBAT ══════ --}}
                <x-border-form title="Efek Samping Obat" align="start" bgcolor="bg-surface-soft" :collapsible="true"
                    :open="true">
                    <div class="mt-3 space-y-3">
                        {{-- Satu baris 3 kolom, sebangun dengan blok Penderita di atas:
                             kiri = uraian kejadian, tengah = catatan pendukung,
                             kanan = waktu & kesudahan. Sekat di dalam blok dibuang —
                             kalau sudah sebaris, garis justru memecah yang mestinya
                             dibaca sekali pandang. --}}
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

                            <div>
                                <x-input-label value="Bentuk / Manifestasi ESO yang Terjadi / Keluhan Lain"
                                    :required="true" />
                                <x-textarea wire:model="form.eso.manifestasi" :disabled="$formReadOnly" rows="10"
                                    :error="$errors->has('form.eso.manifestasi')" class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('form.eso.manifestasi')" class="mt-1" />
                            </div>

                            <div class="space-y-3">
                                <div>
                                    <x-input-label value="Masalah pada Mutu / Kualitas Produk Obat" />
                                    <x-textarea wire:model="form.eso.masalahMutuProduk" :disabled="$formReadOnly"
                                        rows="4" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Riwayat ESO yang Pernah Dialami" />
                                    <x-textarea wire:model="form.eso.riwayatEso" :disabled="$formReadOnly" rows="4"
                                        class="w-full mt-1" />
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <x-input-label value="Saat / Tgl. Mula Terjadi" :required="true" />
                                        <x-text-input wire:model="form.eso.tglMulaTerjadi" :disabled="$formReadOnly"
                                            :error="$errors->has('form.eso.tglMulaTerjadi')" placeholder="dd/mm/yyyy"
                                            class="w-full px-2 mt-1" />
                                        <x-input-error :messages="$errors->get('form.eso.tglMulaTerjadi')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Tgl. Kesudahan ESO" />
                                        <x-text-input wire:model="form.eso.tglKesudahanEso" :disabled="$formReadOnly"
                                            placeholder="dd/mm/yyyy" class="w-full px-2 mt-1" />
                                    </div>
                                </div>

                                <div>
                                    <x-input-label value="Kesudahan ESO" />
                                    <div class="mt-2 space-y-1.5">
                                        @foreach ($kesudahanOptions as $opsiKesudahanEso)
                                            <x-radio-button :label="$opsiKesudahanEso" :value="$opsiKesudahanEso"
                                                name="esoKesudahanEso" wire:model.live="form.eso.kesudahanEso"
                                                :disabled="$formReadOnly" />
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </x-border-form>

                {{-- ══════ BLOK 3 — OBAT ══════ --}}
                <x-border-form title="Obat" align="start" bgcolor="bg-surface-soft" :collapsible="true"
                    :open="true">
                    <div class="mt-3 space-y-4">
                        <x-input-error :messages="$errors->get('form.obat')" class="mb-1" />

                        {{-- ── FORM ENTRI (atas) ── pola sama dgn Rekonsiliasi Obat:
                             baris baru dirakit di sini lalu ditekan Tambah, bukan diketik
                             langsung di tabel. --}}
                        @unless ($formReadOnly)
                            <div class="p-3 space-y-3 border rounded-xl bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-12">
                                    <div class="lg:col-span-3">
                                        <x-input-label value="Nama Obat" :required="true"
                                            class="truncate whitespace-nowrap" />
                                        <x-rekonsiliasi-obat-combobox wire-model="formEntryObat.namaObat"
                                            enter-action="$wire.addBarisObat()" :error="$errors->has('formEntryObat.namaObat')"
                                            placeholder="Nama dagang / generik / industri farmasi"
                                            class="w-full px-2 mt-1" />
                                        <x-input-error :messages="$errors->get('formEntryObat.namaObat')" class="mt-1" />
                                    </div>

                                    <div class="lg:col-span-2">
                                        <x-input-label value="Bentuk Sediaan" class="truncate whitespace-nowrap" />
                                        <x-select-input wire:model="formEntryObat.bentukSediaan"
                                            :title="$formEntryObat['bentukSediaan'] ?: 'Bentuk sediaan'"
                                            class="w-full px-2 mt-1">
                                            <option value="">&mdash;</option>
                                            @foreach ($bentukSediaanOptions as $opsiSediaan)
                                                <option value="{{ $opsiSediaan }}">{{ $opsiSediaan }}</option>
                                            @endforeach
                                        </x-select-input>
                                    </div>

                                    <div class="lg:col-span-1">
                                        <x-input-label value="No. Bets" class="truncate whitespace-nowrap" />
                                        <x-text-input wire:model="formEntryObat.noBets"
                                            wire:keydown.enter.prevent="addBarisObat" class="w-full px-2 mt-1" />
                                    </div>

                                    <div class="lg:col-span-1">
                                        <x-input-label value="Cara" :required="true"
                                            class="truncate whitespace-nowrap" />
                                        <x-select-input wire:model="formEntryObat.cara" :title="$formEntryObat['cara'] ?: 'Cara pemberian'"
                                            :error="$errors->has('formEntryObat.cara')" class="w-full px-2 mt-1">
                                            <option value="">&mdash;</option>
                                            @foreach ($caraPemberianOptions as $opsiCara)
                                                <option value="{{ $opsiCara }}">{{ $opsiCara }}</option>
                                            @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('formEntryObat.cara')" class="mt-1" />
                                    </div>

                                    <div class="lg:col-span-1">
                                        <x-input-label value="Dosis / Waktu" :required="true"
                                            class="truncate whitespace-nowrap" />
                                        <x-text-input wire:model="formEntryObat.dosisWaktu"
                                            wire:keydown.enter.prevent="addBarisObat" placeholder="1x1 tab"
                                            :error="$errors->has('formEntryObat.dosisWaktu')" class="w-full px-2 mt-1" />
                                        <x-input-error :messages="$errors->get('formEntryObat.dosisWaktu')" class="mt-1" />
                                    </div>

                                    <div class="lg:col-span-2">
                                        <x-input-label value="Tgl. Mula" :required="true"
                                            class="truncate whitespace-nowrap" />
                                        <x-text-input wire:model="formEntryObat.tglMula"
                                            wire:keydown.enter.prevent="addBarisObat" placeholder="dd/mm/yyyy"
                                            :error="$errors->has('formEntryObat.tglMula')" class="w-full px-2 mt-1" />
                                        <x-input-error :messages="$errors->get('formEntryObat.tglMula')" class="mt-1" />
                                    </div>

                                    <div class="lg:col-span-2">
                                        <x-input-label value="Tgl. Akhir" class="truncate whitespace-nowrap" />
                                        <x-text-input wire:model="formEntryObat.tglAkhir"
                                            wire:keydown.enter.prevent="addBarisObat" placeholder="dd/mm/yyyy"
                                            class="w-full px-2 mt-1" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-2 lg:grid-cols-2">
                                    <div>
                                        <x-input-label value="Indikasi Penggunaan" class="truncate whitespace-nowrap" />
                                        <x-text-input wire:model="formEntryObat.indikasi"
                                            wire:keydown.enter.prevent="addBarisObat" class="w-full px-2 mt-1" />
                                    </div>
                                    <div class="pt-1 space-y-2 border-t border-hairline lg:border-t-0 lg:pt-0 dark:border-gray-700">
                                        <div class="flex items-center justify-between gap-3">
                                            <x-input-label value="Obat JKN" :required="false" />
                                            <x-toggle wire:model.live="formEntryObat.obatJkn" trueValue="Ya"
                                                falseValue="Tidak"
                                                :label="($formEntryObat['obatJkn'] ?? 'Tidak') === 'Ya' ? 'Ya' : 'Tidak'" />
                                        </div>
                                        <div class="flex items-center justify-between gap-3">
                                            <x-input-label value="Obat yang Dicurigai" :required="false" />
                                            <x-toggle wire:model.live="formEntryObat.dicurigai" trueValue="Ya"
                                                falseValue="Tidak" onColor="bg-error"
                                                :label="($formEntryObat['dicurigai'] ?? 'Tidak') === 'Ya' ? 'Ya' : 'Tidak'" />
                                        </div>
                                    </div>
                                </div>

                                <x-primary-button type="button" wire:click="addBarisObat" wire:loading.attr="disabled"
                                    wire:target="addBarisObat" class="justify-center gap-1.5 w-full">
                                    <span wire:loading.remove wire:target="addBarisObat" class="flex items-center gap-1.5">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                        </svg>
                                        Tambah Obat
                                    </span>
                                    <span wire:loading wire:target="addBarisObat" class="flex items-center gap-1.5">
                                        <x-loading class="w-4 h-4" /> Menambah...
                                    </span>
                                </x-primary-button>
                            </div>
                        @endunless

                        {{-- ── DAFTAR (bawah) ── data saja; salah isi = hapus baris, tambah ulang. --}}
                        <div class="overflow-x-auto border bg-canvas rounded-2xl border-hairline dark:border-gray-700">
                            <table class="ds-table">
                                <thead>
                                    <tr>
                                        <th class="ds-c w-10">No</th>
                                        <th>Obat (Sediaan &middot; No. Bets)</th>
                                        <th>Pemberian</th>
                                        <th>Indikasi</th>
                                        <th class="w-32">Keterangan</th>
                                        <th class="ds-c w-14">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($form['obat'] ?? [] as $indexObat => $barisObat)
                                        <tr wire:key="eso-obat-{{ $riHdrNo ?? 'new' }}-{{ $indexObat }}">
                                            @php
                                                $sediaanBets = collect([$barisObat['bentukSediaan'] ?? null, $barisObat['noBets'] ?? null])
                                                    ->filter(fn($bagian) => filled($bagian))
                                                    ->implode(' · ');
                                                $pemberian = collect([$barisObat['cara'] ?? null, $barisObat['dosisWaktu'] ?? null])
                                                    ->filter(fn($bagian) => filled($bagian))
                                                    ->implode(' · ');
                                                $rentangTanggal = collect([$barisObat['tglMula'] ?? null, $barisObat['tglAkhir'] ?? null])
                                                    ->filter(fn($bagian) => filled($bagian))
                                                    ->implode(' s/d ');
                                            @endphp
                                            <td class="ds-c ds-td-meta">{{ $indexObat + 1 }}</td>
                                            <td>
                                                <div class="ds-td-strong">{{ $barisObat['namaObat'] ?? '-' }}</div>
                                                @if ($sediaanBets)
                                                    <div class="text-muted dark:text-gray-400">{{ $sediaanBets }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <div>{{ $pemberian ?: '-' }}</div>
                                                @if ($rentangTanggal)
                                                    <div class="text-muted dark:text-gray-400">{{ $rentangTanggal }}</div>
                                                @endif
                                            </td>
                                            <td>{{ $barisObat['indikasi'] ?: '-' }}</td>
                                            <td>
                                                <div class="space-y-1.5">
                                                    @foreach ([['obatJkn', 'Obat JKN'], ['dicurigai', 'Dicurigai']] as [$kolomObat, $judulObat])
                                                        @php $nilaiObat = ($barisObat[$kolomObat] ?? 'Tidak') === 'Ya' ? 'Ya' : 'Tidak'; @endphp
                                                        <div class="flex items-center justify-between gap-2">
                                                            <span class="text-muted dark:text-gray-400">{{ $judulObat }}</span>
                                                            <span
                                                                class="font-medium {{ $nilaiObat === 'Ya' ? ($kolomObat === 'dicurigai' ? 'text-error dark:text-rose-400' : 'text-success-deep dark:text-success') : 'text-muted-soft' }}">
                                                                {{ $nilaiObat }}
                                                            </span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td class="ds-c">
                                                @unless ($formReadOnly)
                                                    <x-confirm-button variant="danger-soft"
                                                        :action="'removeBarisObat(' . $indexObat . ')'" title="Hapus Obat"
                                                        :message="'Yakin hapus ' . ($barisObat['namaObat'] ?? 'obat ini') . ' dari daftar?'"
                                                        confirmText="Ya, hapus" cancelText="Batal" class="px-2 py-1">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </x-confirm-button>
                                                @else
                                                    <span class="text-muted-soft">&mdash;</span>
                                                @endunless
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="italic ds-c text-muted-soft">
                                                Belum ada obat. Isi form di atas lalu klik <strong>Tambah Obat</strong>
                                                &mdash; minimal satu obat wajib ada sebelum laporan bisa
                                                ditandatangani.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </x-border-form>

                {{-- ══════ BLOK 4 — PENUTUP ══════ --}}
                <x-border-form title="Keterangan Tambahan & Laboratorium" align="start"
                    bgcolor="bg-surface-soft" :collapsible="true" :open="false">
                    <div class="mt-3">
                        {{-- Blok PENGIRIM tidak ditampilkan: nama & keahlian diambil dari user
                             yang login, instansi/alamat/telepon dari identitas RS. Tetap
                             dipotret ke entri lewat prefillDariEmr() supaya lembar cetak untuk
                             Pusat MESO tetap lengkap. --}}
                        @if ($errors->has('form.pengirim.nama'))
                            <div
                                class="flex items-start gap-2 px-4 py-2.5 mb-3 text-sm border rounded-lg bg-red-50 border-red-200 text-red-700 dark:bg-red-900/20 dark:border-red-700 dark:text-red-300">
                                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                                </svg>
                                <span>Nama pengirim tidak terbaca dari akun Anda. Hubungi Admin untuk melengkapi
                                    data user, laporan belum bisa ditandatangani.</span>
                            </div>
                        @endif

                        {{-- Tiga kolom sebaris; lebar dibagi menurut panjang isi:
                             keterangan 6 · laboratorium 4 · tanggal 2. --}}
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
                            <div class="lg:col-span-6">
                                <x-input-label value="Keterangan Tambahan" />
                                <p class="mb-1 text-xs text-muted dark:text-gray-400">
                                    Mis. kecepatan timbulnya ESO, reaksi setelah obat dihentikan, pengobatan yang
                                    diberikan untuk mengatasi ESO.
                                </p>
                                <x-textarea wire:model="form.keteranganTambahan" :disabled="$formReadOnly" rows="6"
                                    class="w-full" />
                            </div>

                            <div class="lg:col-span-4">
                                <x-input-label value="Data Laboratorium (bila ada)" />
                                <p class="mb-1 text-xs text-muted dark:text-gray-400">
                                    Mis. fungsi hati (SGOT/SGPT), fungsi ginjal (ureum/kreatinin), hematologi, atau
                                    kadar obat dalam darah &mdash; yang menunjang dugaan ESO.
                                </p>
                                <x-textarea wire:model="form.dataLaboratorium" :disabled="$formReadOnly" rows="6"
                                    class="w-full" />
                            </div>

                            <div class="lg:col-span-2">
                                <x-input-label value="Tgl. Pemeriksaan" />
                                <p class="mb-1 text-xs text-muted dark:text-gray-400">
                                    Tanggal sampel laboratorium di samping diperiksa.
                                </p>
                                <x-text-input wire:model="form.tglPemeriksaanLab" :disabled="$formReadOnly"
                                    placeholder="dd/mm/yyyy" class="w-full px-2" />
                            </div>
                        </div>
                    </div>
                </x-border-form>

                {{-- ══════ TTD PELAPOR ══════ --}}
                <x-border-form title="Tanda Tangan Pelapor" align="start" bgcolor="bg-surface-soft">
                    <div class="mt-3">
                        <x-signature.ttd-petugas :framed="false" :allowClear="false" :ttd="$form['ttd']['petugasName'] ?? ''"
                            :date="$form['ttd']['petugasDate'] ?? ''" :code="$form['ttd']['petugasCode'] ?? ''" :locked="$formReadOnly" :canSign="!$formReadOnly"
                            sign="ttdPetugas" nameLabel="Pelapor" dateLabel="Jam TTD"
                            signLabel="TTD & Kunci Laporan" />
                        <p class="mt-2 text-xs text-muted dark:text-gray-400">
                            Tanda tangan pelapor adalah aksi terakhir &mdash; setelah ini entri
                            <strong>terkunci</strong> dan hanya bisa dikoreksi lewat Buka Kunci oleh Admin / Manager.
                        </p>
                    </div>
                </x-border-form>

                {{-- ══════ FOOTER AKSI ══════ --}}
                <div class="flex flex-wrap items-center justify-end gap-2 pt-2 border-t border-hairline dark:border-gray-700">
                    <x-secondary-button type="button" wire:click="kembaliKeDaftar">Kembali ke Daftar</x-secondary-button>
                    @if (!$viewOnly)
                        @unless ($isFormLocked)
                            <x-primary-button type="button" wire:click="saveDraft" wire:loading.attr="disabled"
                                wire:target="saveDraft" class="gap-1.5">
                                <span wire:loading.remove wire:target="saveDraft"
                                    class="flex items-center gap-1.5">Simpan Draft</span>
                                <span wire:loading wire:target="saveDraft" class="flex items-center gap-1.5">
                                    <x-loading class="w-4 h-4" /> Menyimpan...
                                </span>
                            </x-primary-button>
                        @endunless
                    @endif
                </div>

                {{-- ══════ DAFTAR ENTRI TERSIMPAN ══════ --}}
                @endif
                @unless ($this->diForm())
                <x-border-form padding="p-0">
                    <div class="mt-3 overflow-x-auto border bg-canvas rounded-2xl border-hairline dark:border-gray-700">
                        <table class="ds-table">
                            <thead class="sticky top-0 z-10 bg-surface-card dark:bg-gray-800">
                                <tr class="text-xs font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                    <th class="ds-c w-10 bg-surface-card dark:bg-gray-800">No</th>
                                    <th class="bg-surface-card dark:bg-gray-800">Tgl. Laporan</th>
                                    <th class="bg-surface-card dark:bg-gray-800">Manifestasi ESO</th>
                                    <th class="ds-c w-24 bg-surface-card dark:bg-gray-800">Jml Obat</th>
                                    <th class="bg-surface-card dark:bg-gray-800">Pelapor</th>
                                    <th class="ds-c w-24 bg-surface-card dark:bg-gray-800">Status</th>
                                    <th class="ds-c w-56 bg-surface-card dark:bg-gray-800">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($dataDaftarRi['pelaporanEsoRI'] ?? [] as $indexEntri => $entri)
                                    @php
                                        $idEntri = $entri['id'] ?? null;
                                        $isFinal = (bool) ($entri['finalized'] ?? false);
                                        $manifestasi = (string) data_get($entri, 'form.eso.manifestasi', '');
                                        $jumlahObat = count((array) data_get($entri, 'form.obat', []));
                                        $obatDicurigai = collect((array) data_get($entri, 'form.obat', []))
                                            ->filter(fn($baris) => ($baris['dicurigai'] ?? 'Tidak') === 'Ya')
                                            ->count();
                                    @endphp
                                    <tr wire:key="eso-entri-{{ $riHdrNo ?? 'new' }}-{{ $idEntri ?? $indexEntri }}">
                                        <td class="ds-c ds-td-meta">{{ $indexEntri + 1 }}</td>
                                        <td class="ds-td-strong">{{ data_get($entri, 'form.tglLaporan', '-') }}</td>
                                        <td>
                                            <div class="max-w-md truncate">{{ $manifestasi !== '' ? $manifestasi : '-' }}</div>
                                        </td>
                                        <td class="ds-c">
                                            {{ $jumlahObat }}
                                            @if ($obatDicurigai > 0)
                                                <div class="text-muted dark:text-gray-400">{{ $obatDicurigai }} dicurigai</div>
                                            @endif
                                        </td>
                                        <td>{{ data_get($entri, 'form.ttd.petugasName') ?: data_get($entri, 'created_by.name', '-') }}</td>
                                        <td class="ds-c">
                                            @if ($isFinal)
                                                <x-badge variant="success">Terkunci</x-badge>
                                            @else
                                                <x-badge variant="warning">Draft</x-badge>
                                            @endif
                                        </td>
                                        <td class="ds-c">
                                            <div class="flex flex-wrap items-center justify-center gap-1.5">
                                                {{-- Baris atas: aksi non-destruktif --}}
                                                <div class="flex items-center justify-center gap-2">
                                                    @if (!$isFinal && !$isFormLocked && $idEntri)
                                                        <x-primary-button type="button"
                                                            wire:click="editEntry('{{ $idEntri }}')"
                                                            wire:loading.attr="disabled" class="gap-1.5"
                                                            title="Lanjutkan mengisi entri ini">Lanjutkan Pengisian</x-primary-button>
                                                    @endif
                                                    @if ($isFinal && $idEntri)
                                                        <x-secondary-button type="button"
                                                            wire:click="viewEntry('{{ $idEntri }}')"
                                                            wire:loading.attr="disabled" class="gap-1.5"
                                                            title="Lihat entri terkunci">Lihat</x-secondary-button>
                                                    @endif
                                                    @if ($idEntri)
                                                        <x-info-button type="button" wire:click="cetak('{{ $idEntri }}')"
                                                            wire:loading.attr="disabled"
                                                            wire:target="cetak('{{ $idEntri }}')" class="gap-1.5"
                                                            title="Cetak laporan ESO">
                                                            <span wire:loading.remove wire:target="cetak('{{ $idEntri }}')"
                                                                class="flex items-center gap-1.5">Cetak</span>
                                                            <span wire:loading wire:target="cetak('{{ $idEntri }}')"
                                                                class="flex items-center gap-1.5"><x-loading class="w-5 h-5" />
                                                                Mencetak...</span>
                                                        </x-info-button>
                                                    @endif
                                                </div>

                                                {{-- Baris bawah: aksi terkunci/destruktif --}}
                                                @if (!$isFormLocked)
                                                    <div class="flex items-center justify-center gap-2">
                                                        @if ($isFinal && $idEntri)
                                                            @can('dokumen.bukaKunci')
                                                                <x-confirm-button action="bukaKunci('{{ $idEntri }}')"
                                                                    title="Buka Kunci Laporan ESO"
                                                                    message="TTD pelapor akan dicabut & entri kembali menjadi draft untuk dikoreksi. Lanjutkan?"
                                                                    confirmText="Ya, Buka Kunci" class="gap-1.5">
                                                                    Buka Kunci
                                                                </x-confirm-button>
                                                            @endcan
                                                        @endif
                                                        @if ($idEntri)
                                                            @can('dokumen.hapus')
                                                                <x-outline-button type="button"
                                                                    wire:click.prevent="removeEntry('{{ $idEntri }}')"
                                                                    wire:confirm="Hapus laporan ESO ini?"
                                                                    wire:loading.attr="disabled"
                                                                    class="!px-2 !py-1 !text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30"
                                                                    title="Hapus laporan">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                        viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                                            stroke-width="2"
                                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                    </svg>
                                                                </x-outline-button>
                                                            @endcan
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="italic ds-c text-muted-soft">
                                            Belum ada laporan efek samping obat.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-border-form>
                    {{-- FOOTER LAYAR DAFTAR — Tutup + Isi Formulir Baru, seragam dengan modul lain --}}
                    <div class="flex flex-wrap items-center justify-end gap-2 pt-3 mt-4 border-t border-hairline dark:border-gray-700">
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
                @endunless

            </div>
        </div>
    </x-modal>
</div>
