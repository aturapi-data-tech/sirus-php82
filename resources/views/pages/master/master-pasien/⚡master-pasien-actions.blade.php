<?php

namespace App\Http\Livewire\Pages\Master\MasterPasien;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\SATUSEHAT\PatientTrait;

new class extends Component {
    use MasterPasienTrait, PatientTrait;

    public string $formMode = 'create'; // create|edit
    public string $regNo = '';

    // Data utama pasien
    public array $dataPasien = [];

    // Data LOV
    public array $jenisKelaminOptions = [];
    public array $agamaOptions = [];
    public array $statusPerkawinanOptions = [];
    public array $pendidikanOptions = [];
    public array $pekerjaanOptions = [];
    public array $golonganDarahOptions = [];
    public array $hubunganDgnPasienOptions = [];
    public array $statusOptions = [];

    public int $domisilSyncTick = 0;

    // Variabel untuk field tambahan di blade
    public string $bpjspasienCode = '';
    public string $pasienUuid = '';

    public function mount(): void
    {
        $this->initializeLOVOptions();
    }

    protected function initializeLOVOptions(): void
    {
        $this->jenisKelaminOptions = [['id' => 0, 'desc' => 'Tidak diketahui'], ['id' => 1, 'desc' => 'Laki-laki'], ['id' => 2, 'desc' => 'Perempuan'], ['id' => 3, 'desc' => 'Tidak dapat ditentukan'], ['id' => 4, 'desc' => 'Tidak Mengisi']];

        $this->agamaOptions = [['id' => 1, 'desc' => 'Islam'], ['id' => 2, 'desc' => 'Kristen (Protestan)'], ['id' => 3, 'desc' => 'Katolik'], ['id' => 4, 'desc' => 'Hindu'], ['id' => 5, 'desc' => 'Budha'], ['id' => 6, 'desc' => 'Konghucu'], ['id' => 7, 'desc' => 'Penghayat'], ['id' => 8, 'desc' => 'Lain-lain']];

        $this->statusPerkawinanOptions = [['id' => 1, 'desc' => 'Belum Kawin'], ['id' => 2, 'desc' => 'Kawin'], ['id' => 3, 'desc' => 'Cerai Hidup'], ['id' => 4, 'desc' => 'Cerai Mati']];

        $this->pendidikanOptions = [['id' => 0, 'desc' => 'Tidak Sekolah'], ['id' => 1, 'desc' => 'SD'], ['id' => 2, 'desc' => 'SLTP Sederajat'], ['id' => 3, 'desc' => 'SLTA Sederajat'], ['id' => 4, 'desc' => 'D1-D3'], ['id' => 5, 'desc' => 'D4'], ['id' => 6, 'desc' => 'S1'], ['id' => 7, 'desc' => 'S2'], ['id' => 8, 'desc' => 'S3']];

        $this->pekerjaanOptions = [['id' => 0, 'desc' => 'Tidak Bekerja'], ['id' => 1, 'desc' => 'PNS'], ['id' => 2, 'desc' => 'TNI/POLRI'], ['id' => 3, 'desc' => 'BUMN'], ['id' => 4, 'desc' => 'Pegawai Swasta/ Wiraswasta'], ['id' => 5, 'desc' => 'Lain-Lain']];

        $this->golonganDarahOptions = [['id' => 1, 'desc' => 'A'], ['id' => 2, 'desc' => 'B'], ['id' => 3, 'desc' => 'AB'], ['id' => 4, 'desc' => 'O'], ['id' => 13, 'desc' => 'Tidak Tahu']];

        $this->hubunganDgnPasienOptions = [['id' => 1, 'desc' => 'Diri Sendiri'], ['id' => 2, 'desc' => 'Orang Tua'], ['id' => 3, 'desc' => 'Anak'], ['id' => 4, 'desc' => 'Suami / Istri'], ['id' => 5, 'desc' => 'Kerabat / Saudara'], ['id' => 6, 'desc' => 'Lain-lain']];

        $this->statusOptions = [['id' => 0, 'desc' => 'Tidak Aktif / Batal'], ['id' => 1, 'desc' => 'Aktif / Hidup'], ['id' => 2, 'desc' => 'Meninggal']];
    }

    #[On('master.pasien.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode = 'create';

        // Isi data default dari template trait
        $this->dataPasien = $this->getDefaultPasienTemplate();
        // Generate regNo baru
        $jmlPasien = DB::table('rsmst_pasiens')->count();
        $this->dataPasien['pasien']['regNo'] = sprintf('%07s', $jmlPasien + 1) . 'Z';
        $this->regNo = $this->dataPasien['pasien']['regNo'];
        $this->dispatch('open-modal', name: 'master-pasien-actions');
    }

    #[On('master.pasien.openEdit')]
    public function openEdit(string $regNo): void
    {
        $this->resetForm();
        $this->formMode = 'edit';
        $this->regNo = $regNo;

        // Menggunakan trait untuk mendapatkan data pasien
        $this->dataPasien = $this->findDataMasterPasien($regNo);
        $this->dispatch('open-modal', name: 'master-pasien-actions');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-pasien-actions');
    }

    protected function resetForm(): void
    {
        $this->reset(['dataPasien', 'regNo', 'bpjspasienCode', 'pasienUuid']);

        $this->resetValidation();
    }

    // Validation Rules - SUDAH BENAR
    protected function rules(): array
    {
        return [
            'dataPasien.pasien.regNo' => ['required', 'string', 'max:50', $this->formMode === 'create' ? Rule::unique('rsmst_pasiens', 'reg_no') : Rule::unique('rsmst_pasiens', 'reg_no')->ignore($this->dataPasien['pasien']['regNo'] ?? '', 'reg_no')],
            'dataPasien.pasien.regName' => ['required', 'string', 'min:3', 'max:200'],
            'dataPasien.pasien.tempatLahir' => ['required', 'string', 'max:100'],
            'dataPasien.pasien.tglLahir' => ['required', 'date_format:d/m/Y'],
            'dataPasien.pasien.jenisKelamin.jenisKelaminId' => ['required', 'numeric'],
            'dataPasien.pasien.agama.agamaId' => ['required', 'numeric'],
            'dataPasien.pasien.statusPerkawinan.statusPerkawinanId' => ['required', 'numeric'],
            'dataPasien.pasien.pendidikan.pendidikanId' => ['required', 'numeric'],
            'dataPasien.pasien.pekerjaan.pekerjaanId' => ['required', 'numeric'],
            'dataPasien.pasien.identitas.nik' => ['required', 'digits:16'],
            'dataPasien.pasien.identitas.alamat' => ['required', 'string', 'max:500'],
            'dataPasien.pasien.identitas.rt' => ['required', 'string', 'max:10'],
            'dataPasien.pasien.identitas.rw' => ['required', 'string', 'max:10'],
            'dataPasien.pasien.kontak.nomerTelponSelulerPasien' => ['required', 'digits_between:6,15'],
            'dataPasien.pasien.hubungan.namaPenanggungJawab' => ['required', 'string', 'min:3', 'max:200'],
            'dataPasien.pasien.hubungan.nomerTelponSelulerPenanggungJawab' => ['required', 'digits_between:6,15'],
            'dataPasien.pasien.hubungan.namaAyah' => ['required', 'string', 'min:3', 'max:200'],
            'dataPasien.pasien.hubungan.nomerTelponSelulerAyah' => ['required', 'digits_between:6,15'],
            'dataPasien.pasien.hubungan.namaIbu' => ['required', 'string', 'min:3', 'max:200'],
            'dataPasien.pasien.hubungan.nomerTelponSelulerIbu' => ['required', 'digits_between:6,15'],
        ];
    }

    protected function messages(): array
    {
        return [
            // RegNo
            'dataPasien.pasien.regNo.required' => 'ID Pasien wajib diisi.',
            'dataPasien.pasien.regNo.unique' => 'ID Pasien sudah digunakan, silakan gunakan ID lain.',
            'dataPasien.pasien.regNo.max' => 'ID Pasien maksimal :max karakter.',

            // RegName
            'dataPasien.pasien.regName.required' => 'Nama Pasien wajib diisi.',
            'dataPasien.pasien.regName.min' => 'Nama Pasien minimal :min karakter.',
            'dataPasien.pasien.regName.max' => 'Nama Pasien maksimal :max karakter.',

            // Tempat Lahir
            'dataPasien.pasien.tempatLahir.required' => 'Tempat Lahir wajib diisi.',
            'dataPasien.pasien.tempatLahir.max' => 'Tempat Lahir maksimal :max karakter.',

            // Tanggal Lahir
            'dataPasien.pasien.tglLahir.required' => 'Tanggal Lahir wajib diisi.',
            'dataPasien.pasien.tglLahir.date_format' => 'Format Tanggal Lahir harus dd/mm/yyyy.',

            // Jenis Kelamin
            'dataPasien.pasien.jenisKelamin.jenisKelaminId.required' => 'Jenis Kelamin wajib dipilih.',
            'dataPasien.pasien.jenisKelamin.jenisKelaminId.numeric' => 'Jenis Kelamin harus berupa angka.',

            // Agama
            'dataPasien.pasien.agama.agamaId.required' => 'Agama wajib dipilih.',
            'dataPasien.pasien.agama.agamaId.numeric' => 'Agama harus berupa angka.',

            // Status Perkawinan
            'dataPasien.pasien.statusPerkawinan.statusPerkawinanId.required' => 'Status Perkawinan wajib dipilih.',
            'dataPasien.pasien.statusPerkawinan.statusPerkawinanId.numeric' => 'Status Perkawinan harus berupa angka.',

            // Pendidikan
            'dataPasien.pasien.pendidikan.pendidikanId.required' => 'Pendidikan wajib dipilih.',
            'dataPasien.pasien.pendidikan.pendidikanId.numeric' => 'Pendidikan harus berupa angka.',

            // Pekerjaan
            'dataPasien.pasien.pekerjaan.pekerjaanId.required' => 'Pekerjaan wajib dipilih.',
            'dataPasien.pasien.pekerjaan.pekerjaanId.numeric' => 'Pekerjaan harus berupa angka.',

            // Identitas - NIK
            'dataPasien.pasien.identitas.nik.required' => 'NIK wajib diisi.',
            'dataPasien.pasien.identitas.nik.digits' => 'NIK harus 16 digit.',

            // Identitas - Alamat
            'dataPasien.pasien.identitas.alamat.required' => 'Alamat wajib diisi.',
            'dataPasien.pasien.identitas.alamat.max' => 'Alamat maksimal :max karakter.',

            // Identitas - RT
            'dataPasien.pasien.identitas.rt.required' => 'RT wajib diisi.',
            'dataPasien.pasien.identitas.rt.max' => 'RT maksimal :max karakter.',

            // Identitas - RW
            'dataPasien.pasien.identitas.rw.required' => 'RW wajib diisi.',
            'dataPasien.pasien.identitas.rw.max' => 'RW maksimal :max karakter.',

            // Kontak - No HP Pasien
            'dataPasien.pasien.kontak.nomerTelponSelulerPasien.required' => 'No. HP Pasien wajib diisi.',
            'dataPasien.pasien.kontak.nomerTelponSelulerPasien.digits_between' => 'No. HP Pasien harus antara :min sampai :max digit.',

            // Hubungan - Nama Penanggung Jawab
            'dataPasien.pasien.hubungan.namaPenanggungJawab.required' => 'Nama Penanggung Jawab wajib diisi.',
            'dataPasien.pasien.hubungan.namaPenanggungJawab.min' => 'Nama Penanggung Jawab minimal :min karakter.',
            'dataPasien.pasien.hubungan.namaPenanggungJawab.max' => 'Nama Penanggung Jawab maksimal :max karakter.',

            // Hubungan - No HP Penanggung Jawab
            'dataPasien.pasien.hubungan.nomerTelponSelulerPenanggungJawab.required' => 'No. HP Penanggung Jawab wajib diisi.',
            'dataPasien.pasien.hubungan.nomerTelponSelulerPenanggungJawab.digits_between' => 'No. HP Penanggung Jawab harus antara :min sampai :max digit.',

            // Hubungan - Nama Ayah
            'dataPasien.pasien.hubungan.namaAyah.required' => 'Nama Ayah wajib diisi.',
            'dataPasien.pasien.hubungan.namaAyah.min' => 'Nama Ayah minimal :min karakter.',
            'dataPasien.pasien.hubungan.namaAyah.max' => 'Nama Ayah maksimal :max karakter.',

            // Hubungan - No HP Ayah
            'dataPasien.pasien.hubungan.nomerTelponSelulerAyah.required' => 'No. HP Ayah wajib diisi.',
            'dataPasien.pasien.hubungan.nomerTelponSelulerAyah.digits_between' => 'No. HP Ayah harus antara :min sampai :max digit.',

            // Hubungan - Nama Ibu
            'dataPasien.pasien.hubungan.namaIbu.required' => 'Nama Ibu wajib diisi.',
            'dataPasien.pasien.hubungan.namaIbu.min' => 'Nama Ibu minimal :min karakter.',
            'dataPasien.pasien.hubungan.namaIbu.max' => 'Nama Ibu maksimal :max karakter.',

            // Hubungan - No HP Ibu
            'dataPasien.pasien.hubungan.nomerTelponSelulerIbu.required' => 'No. HP Ibu wajib diisi.',
            'dataPasien.pasien.hubungan.nomerTelponSelulerIbu.digits_between' => 'No. HP Ibu harus antara :min sampai :max digit.',
        ];
    }

    // Save Data - SUDAH DIPERBAIKI
    public function save(): void
    {
        $this->validate();
        try {
            // Prepare data for database - SUDAH DIPERBAIKI
            $saveData = [
                'reg_no' => $this->dataPasien['pasien']['regNo'],
                'reg_name' => strtoupper($this->dataPasien['pasien']['regName']),
                'sex' => ($this->dataPasien['pasien']['jenisKelamin']['jenisKelaminId'] ?? 0) == 1 ? 'L' : 'P',
                'birth_date' => DB::raw("to_date('" . ($this->dataPasien['pasien']['tglLahir'] ?? '') . "','dd/mm/yyyy')"),
                'birth_place' => strtoupper($this->dataPasien['pasien']['tempatLahir'] ?? ''),
                'nik_bpjs' => $this->dataPasien['pasien']['identitas']['nik'] ?? '',
                'nokartu_bpjs' => $this->dataPasien['pasien']['identitas']['idbpjs'] ?? null,
                'blood' => $this->dataPasien['pasien']['golonganDarah']['golonganDarahId'] ?? null,
                'marital_status' => ($this->dataPasien['pasien']['statusPerkawinan']['statusPerkawinanId'] ?? 1) == 1 ? 'S' : (($this->dataPasien['pasien']['statusPerkawinan']['statusPerkawinanId'] ?? 1) == 2 ? 'M' : (($this->dataPasien['pasien']['statusPerkawinan']['statusPerkawinanId'] ?? 1) == 3 ? 'D' : 'W')),
                'rel_id' => $this->dataPasien['pasien']['agama']['agamaId'] ?? '1',
                'edu_id' => $this->dataPasien['pasien']['pendidikan']['pendidikanId'] ?? '3',
                'job_id' => $this->dataPasien['pasien']['pekerjaan']['pekerjaanId'] ?? '4',
                'kk' => strtoupper($this->dataPasien['pasien']['hubungan']['namaPenanggungJawab'] ?? ''),
                'nyonya' => strtoupper($this->dataPasien['pasien']['hubungan']['namaIbu'] ?? ''),
                'address' => $this->dataPasien['pasien']['identitas']['alamat'] ?? '',
                'phone' => $this->dataPasien['pasien']['kontak']['nomerTelponSelulerPasien'] ?? '',
                'rt' => $this->dataPasien['pasien']['identitas']['rt'] ?? '',
                'rw' => $this->dataPasien['pasien']['identitas']['rw'] ?? '',
                'kab_id' => $this->dataPasien['pasien']['identitas']['kotaId'] ?? '3504',
                'prop_id' => $this->dataPasien['pasien']['identitas']['propinsiId'] ?? '35',
            ];

            // Tambahkan field tambahan jika ada
            if (!empty($this->bpjspasienCode)) {
                $saveData['bpjspasien_code'] = $this->bpjspasienCode;
            }

            if (!empty($this->pasienUuid)) {
                $saveData['pasien_uuid'] = $this->pasienUuid;
            }

            if ($this->formMode === 'create') {
                $saveData['reg_date'] = DB::raw('SYSDATE');
                DB::table('rsmst_pasiens')->insert($saveData);

                // Langsung auto-save JSON menggunakan trait
                $pasienData = $this->findDataMasterPasien($this->dataPasien['pasien']['regNo']);
                if (!isset($pasienData['errorMessages'])) {
                    $this->autoSaveToJson($this->dataPasien['pasien']['regNo'], $pasienData);
                }

                $this->dispatch('toast', type: 'success', message: 'Data pasien berhasil disimpan.');
            } else {
                DB::table('rsmst_pasiens')
                    ->where('reg_no', $this->dataPasien['pasien']['regNo'])
                    ->update($saveData);

                // Langsung update JSON menggunakan trait
                $pasienData = $this->findDataMasterPasien($this->dataPasien['pasien']['regNo']);
                if (!isset($pasienData['errorMessages'])) {
                    $this->updateJsonMasterPasien($this->dataPasien['pasien']['regNo'], $pasienData);
                }

                $this->dispatch('toast', type: 'success', message: 'Data pasien berhasil diupdate.');
            }

            $this->closeModal();
            $this->dispatch('master.pasien.saved');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan data: ' . $e->getMessage());
            \Log::error('Error saving pasien: ' . $e->getMessage());
        }
    }

    // Delete Handler
    #[On('master.pasien.requestDelete')]
    public function deleteFromGrid(string $regNo): void
    {
        try {
            // Cek apakah pasien sudah punya transaksi
            $isUsed = DB::table('rstxn_rjhdrs')->where('reg_no', $regNo)->exists() || DB::table('rstxn_igdhdrs')->where('reg_no', $regNo)->exists() || DB::table('rstxn_rihdrs')->where('reg_no', $regNo)->exists();

            if ($isUsed) {
                $this->dispatch('toast', type: 'error', message: 'Pasien sudah dipakai pada transaksi, tidak bisa dihapus.');
                return;
            }

            $deleted = DB::table('rsmst_pasiens')->where('reg_no', $regNo)->delete();

            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Data pasien berhasil dihapus.');
            $this->dispatch('master.pasien.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Pasien tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }

            throw $e;
        }
    }

    public function updated($name, $value)
    {
        if ($name !== 'dataPasien.pasien.domisil.samadgnidentitas') {
            return;
        }

        $checked = !empty($value) && in_array('1', $value);

        if ($checked) {
            $this->dataPasien['pasien']['domisil']['alamat'] = $this->dataPasien['pasien']['identitas']['alamat'] ?? '';
            $this->dataPasien['pasien']['domisil']['rt'] = $this->dataPasien['pasien']['identitas']['rt'] ?? '';
            $this->dataPasien['pasien']['domisil']['rw'] = $this->dataPasien['pasien']['identitas']['rw'] ?? '';
            $this->dataPasien['pasien']['domisil']['kodepos'] = $this->dataPasien['pasien']['identitas']['kodepos'] ?? '';

            // penting: kota dulu, baru desa (biar nggak ke-reset)
            $this->dataPasien['pasien']['domisil']['kotaId'] = $this->dataPasien['pasien']['identitas']['kotaId'] ?? '';
            $this->dataPasien['pasien']['domisil']['kotaName'] = $this->dataPasien['pasien']['identitas']['kotaName'] ?? '';
            $this->dataPasien['pasien']['domisil']['propinsiId'] = $this->dataPasien['pasien']['identitas']['propinsiId'] ?? '';
            $this->dataPasien['pasien']['domisil']['propinsiName'] = $this->dataPasien['pasien']['identitas']['propinsiName'] ?? '';

            $this->dataPasien['pasien']['domisil']['desaId'] = $this->dataPasien['pasien']['identitas']['desaId'] ?? '';
            $this->dataPasien['pasien']['domisil']['desaName'] = $this->dataPasien['pasien']['identitas']['desaName'] ?? '';
            $this->dataPasien['pasien']['domisil']['kecamatanid'] = $this->dataPasien['pasien']['identitas']['kecamatanid'] ?? '';
            $this->dataPasien['pasien']['domisil']['kecamatanName'] = $this->dataPasien['pasien']['identitas']['kecamatanName'] ?? '';

            $this->dataPasien['pasien']['domisil']['negara'] = $this->dataPasien['pasien']['identitas']['negara'] ?? '';
        } else {
            $this->dataPasien['pasien']['domisil']['alamat'] = '';
            $this->dataPasien['pasien']['domisil']['rt'] = '';
            $this->dataPasien['pasien']['domisil']['rw'] = '';
            $this->dataPasien['pasien']['domisil']['kodepos'] = '';
            $this->dataPasien['pasien']['domisil']['desaId'] = '';
            $this->dataPasien['pasien']['domisil']['desaName'] = '';
            $this->dataPasien['pasien']['domisil']['kecamatanid'] = '';
            $this->dataPasien['pasien']['domisil']['kecamatanName'] = '';
            $this->dataPasien['pasien']['domisil']['kotaId'] = '';
            $this->dataPasien['pasien']['domisil']['kotaName'] = '';
            $this->dataPasien['pasien']['domisil']['propinsiId'] = '';
            $this->dataPasien['pasien']['domisil']['propinsiName'] = '';
            $this->dataPasien['pasien']['domisil']['negara'] = '';
        }

        $this->domisilSyncTick++;
    }

    #[On('lov.selected')]
    public function handleLovSelected(string $target, array $payload): void
    {
        // Handle DESA IDENTITAS
        if ($target === 'desa_identitas') {
            $this->dataPasien['pasien']['identitas']['desaId'] = $payload['des_id'] ?? '';
            $this->dataPasien['pasien']['identitas']['desaName'] = $payload['des_name'] ?? '';
            $this->dataPasien['pasien']['identitas']['kecamatanId'] = $payload['kec_id'] ?? '';
            $this->dataPasien['pasien']['identitas']['kecamatanName'] = $payload['kec_name'] ?? '';
            return;
        }

        // Handle DESA DOMISILI
        if ($target === 'desa_domisil') {
            $this->dataPasien['pasien']['domisil']['desaId'] = $payload['des_id'] ?? '';
            $this->dataPasien['pasien']['domisil']['desaName'] = $payload['des_name'] ?? '';
            $this->dataPasien['pasien']['domisil']['kecamatanId'] = $payload['kec_id'] ?? '';
            $this->dataPasien['pasien']['domisil']['kecamatanName'] = $payload['kec_name'] ?? '';
            return;
        }
    }

    /**
     * Update atau generate UUID Pasien dari SATUSEHAT berdasarkan NIK
     */
    public function UpdatepatientUuid(string $nik = ''): void
    {
        // Validasi NIK
        if (empty($nik)) {
            $this->dispatch('toast', type: 'warning', message: 'NIK pasien wajib diisi terlebih dahulu.');
            return;
        }

        if (strlen($nik) !== 16) {
            $this->dispatch('toast', type: 'warning', message: 'NIK harus 16 digit.');
            return;
        }

        try {
            // 1. Inisialisasi koneksi SATUSEHAT
            $this->initializeSatuSehat();

            // 2. Cari Patient berdasarkan NIK
            $searchResult = $this->searchPatient(['nik' => $nik]);
            $entries = collect($searchResult['entry'] ?? []);

            // 3. Jika tidak ada, buat pasien baru
            if ($entries->isEmpty()) {
                $this->dispatch('toast', type: 'warning', message: "Tidak ada pasien ditemukan dengan NIK: {$nik}. Persiapan create pasien baru.");

                // Siapkan data untuk create patient
                $patientData = [
                    'name' => $this->dataPasien['pasien']['regName'] ?? '',
                    'given_name' => $this->dataPasien['pasien']['regName'] ?? '',
                    'family_name' => '',
                    'birth_date' => $this->formatDateToYmd($this->dataPasien['pasien']['tglLahir'] ?? ''),
                    'gender' => $this->mapGenderToSatusehat($this->dataPasien['pasien']['jenisKelamin']['jenisKelaminId'] ?? 0),
                    'phone' => $this->dataPasien['pasien']['kontak']['nomerTelponSelulerPasien'] ?? '',
                    'nik' => $nik,
                    'bpjs_number' => $this->dataPasien['pasien']['identitas']['idbpjs'] ?? null,
                    'marital_status' => $this->mapMaritalStatusToSatusehat($this->dataPasien['pasien']['statusPerkawinan']['statusPerkawinanId'] ?? 1),
                    'address' => $this->buildAddressPayload($this->dataPasien['pasien']['identitas'] ?? []),
                ];

                // Create patient ke SATUSEHAT
                $result = $this->createPatient($patientData);
                $createdUuid = $result['id'] ?? null;

                if ($createdUuid) {
                    // Simpan UUID ke data pasien
                    $this->dataPasien['pasien']['identitas']['patientUuid'] = $createdUuid;
                    $this->pasienUuid = $createdUuid;

                    $this->dispatch('toast', type: 'success', message: "Pasien baru berhasil dibuat di SATUSEHAT (UUID: {$createdUuid})");
                } else {
                    $this->dispatch('toast', type: 'error', message: 'Gagal membuat pasien baru di SATUSEHAT.');
                }

                return;
            }

            // 4. Ambil UUID Patient pertama dari hasil pencarian
            $newUuid = $entries->pluck('resource.id')->first();
            $currentUuid = $this->dataPasien['pasien']['identitas']['patientUuid'] ?? null;

            // 5. Jika belum ada UUID tersimpan, set dan notify
            if (empty($currentUuid)) {
                $this->dataPasien['pasien']['identitas']['patientUuid'] = $newUuid;
                $this->pasienUuid = $newUuid;

                $this->dispatch('toast', type: 'success', message: "patientUuid di-set ke {$newUuid}");
                return;
            }

            // 6. Jika UUID sudah sama, beri info
            if ($currentUuid === $newUuid) {
                $this->dispatch('toast', type: 'info', message: 'patientUuid sudah sesuai dengan data terbaru');
                return;
            }

            // 7. Jika berbeda, cek apakah UUID lama masih ada dalam hasil pencarian
            $oldStillExists = $entries->pluck('resource.id')->contains($currentUuid);

            if ($oldStillExists) {
                $this->dispatch('toast', type: 'success', message: "patientUuid lama ({$currentUuid}) masih ditemukan");
            } else {
                $this->dispatch('toast', type: 'warning', message: "patientUuid lama ({$currentUuid}) tidak ada di hasil terbaru, disarankan update ke UUID baru: {$newUuid}");

                // Optional: Auto update ke UUID baru
                // $this->dataPasien['pasien']['identitas']['patientUuid'] = $newUuid;
                // $this->pasienUuid = $newUuid;
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error saat memproses UUID: ' . $e->getMessage());
            \Log::error('Error UpdatepatientUuid: ' . $e->getMessage());
        }
    }

    /**
     * Format tanggal dari dd/mm/yyyy ke yyyy-mm-dd
     */
    private function formatDateToYmd(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            $parts = explode('/', $date);
            if (count($parts) === 3) {
                return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * Map gender ID ke kode SATUSEHAT
     */
    private function mapGenderToSatusehat(int $genderId): string
    {
        return match ($genderId) {
            1 => 'male',
            2 => 'female',
            default => 'unknown',
        };
    }

    /**
     * Map status perkawinan ID ke kode SATUSEHAT
     */
    private function mapMaritalStatusToSatusehat(int $statusId): string
    {
        return match ($statusId) {
            1 => 'S', // Belum Kawin -> Never Married
            2 => 'M', // Kawin -> Married
            3 => 'D', // Cerai Hidup -> Divorced
            4 => 'W', // Cerai Mati -> Widowed
            default => 'U', // Unknown
        };
    }

    /**
     * Build address payload untuk SATUSEHAT
     */
    private function buildAddressPayload(array $identitas): array
    {
        $address = [];

        if (!empty($identitas['alamat'])) {
            $address['line'] = [$identitas['alamat']];
        }

        if (!empty($identitas['desaName'])) {
            $address['city'] = $identitas['desaName'];
        }

        if (!empty($identitas['kecamatanName'])) {
            $address['district'] = $identitas['kecamatanName'];
        }

        if (!empty($identitas['kotaName'])) {
            $address['city'] = $identitas['kotaName'];
        }

        if (!empty($identitas['propinsiName'])) {
            $address['state'] = $identitas['propinsiName'];
        }

        if (!empty($identitas['kodepos'])) {
            $address['postalCode'] = $identitas['kodepos'];
        }

        $address['country'] = 'ID';
        $address['use'] = 'home';

        return $address;
    }
};

?>


<div>
    <x-modal name="master-pasien-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="master-pasien-actions-{{ $formMode }}-{{ $regNo ?? 'new' }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>

                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Data pasien' : 'Tambah Data pasien' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Lengkapi informasi pasien untuk kebutuhan
                                    aplikasi.{{ $this->dataPasien['pasien']['regNo'] ?? '' }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>

                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <div class="w-full">
                    <div
                        class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="p-5 space-y-5">

                            {{-- CONTENT AREA --}}
                            <div class="flex-1 overflow-y-auto">
                                <div class="px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                                    <div class="w-full mx-auto space-y-4">

                                        {{-- SECTION: TIDAK DIKENAL CHECKBOX --}}
                                        @if (isset($dataPasien['pasien']['pasientidakdikenal']))
                                            <div class="flex justify-end mb-4">
                                                <x-check-box value='1' :label="__('Pasien Tidak Dikenal')"
                                                    wire:model.live="dataPasien.pasien.pasientidakdikenal" />
                                            </div>
                                        @endif

                                        {{-- SECTION: DATA DASAR --}}
                                        <x-border-form :title="__('Data Dasar Pasien')" :align="__('start')" :bgcolor="__('bg-white')">
                                            <div class="space-y-5">
                                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                    {{-- Reg No --}}
                                                    <div>
                                                        <x-input-label value="Reg No Pasien" :required="true" />
                                                        <x-text-input wire:model.live="dataPasien.pasien.regNo"
                                                            :disabled="$formMode === 'edit'" :error="$errors->has('dataPasien.pasien.regNo')" class="w-full mt-1" />
                                                        <x-input-error :messages="$errors->get('dataPasien.pasien.regNo')" class="mt-1" />
                                                    </div>

                                                    {{-- Gelar Depan + Nama + Gelar Belakang + Nama Panggilan --}}
                                                    <div class="col-span-1 sm:col-span-2">
                                                        <x-input-label value="Nama Pasien" :required="true" />
                                                        <div class="grid grid-cols-1 gap-2 mt-1 sm:grid-cols-4">
                                                            <x-text-input placeholder="Gelar depan"
                                                                wire:model.live="dataPasien.pasien.gelarDepan"
                                                                :error="$errors->has('dataPasien.pasien.gelarDepan')" class="w-full" />
                                                            <x-text-input placeholder="Nama"
                                                                wire:model.live="dataPasien.pasien.regName"
                                                                :error="$errors->has('dataPasien.pasien.regName')" class="w-full sm:col-span-2"
                                                                style="text-transform:uppercase" />
                                                            <x-text-input placeholder="Gelar Belakang"
                                                                wire:model.live="dataPasien.pasien.gelarBelakang"
                                                                :error="$errors->has(
                                                                    'dataPasien.pasien.gelarBelakang',
                                                                )" class="w-full" />
                                                        </div>
                                                        <div class="flex items-center gap-2 mt-2">
                                                            <span class="text-gray-500">{{ ' / ' }}</span>
                                                            <x-text-input placeholder="Nama Panggilan"
                                                                wire:model.live="dataPasien.pasien.namaPanggilan"
                                                                :error="$errors->has(
                                                                    'dataPasien.pasien.namaPanggilan',
                                                                )" class="w-full" />
                                                        </div>
                                                        <x-input-error :messages="$errors->get('dataPasien.pasien.regName')" class="mt-1" />
                                                    </div>
                                                </div>

                                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                    {{-- Tempat Lahir --}}
                                                    <div>
                                                        <x-input-label value="Tempat Lahir" :required="true" />
                                                        <x-text-input wire:model.live="dataPasien.pasien.tempatLahir"
                                                            :error="$errors->has('dataPasien.pasien.tempatLahir')" class="w-full mt-1"
                                                            style="text-transform:uppercase" />
                                                        <x-input-error :messages="$errors->get('dataPasien.pasien.tempatLahir')" class="mt-1" />
                                                    </div>

                                                    {{-- Tanggal Lahir + Umur --}}
                                                    <div>
                                                        <x-input-label value="Tanggal Lahir & Umur"
                                                            :required="true" />
                                                        <div class="grid grid-cols-1 gap-2 mt-1 sm:grid-cols-5">
                                                            <x-text-input wire:model.live="dataPasien.pasien.tglLahir"
                                                                placeholder="dd/mm/yyyy" :error="$errors->has('dataPasien.pasien.tglLahir')"
                                                                class="w-full sm:col-span-2" />
                                                            <x-text-input wire:model.live="dataPasien.pasien.thn"
                                                                placeholder="Thn" :error="$errors->has('dataPasien.pasien.thn')" class="w-full" />
                                                            <x-text-input wire:model.live="dataPasien.pasien.bln"
                                                                placeholder="Bln" :error="$errors->has('dataPasien.pasien.bln')" class="w-full" />
                                                            <x-text-input wire:model.live="dataPasien.pasien.hari"
                                                                placeholder="Hari" :error="$errors->has('dataPasien.pasien.hari')" class="w-full" />
                                                        </div>
                                                        <x-input-error :messages="$errors->get('dataPasien.pasien.tglLahir')" class="mt-1" />
                                                    </div>
                                                </div>
                                            </div>
                                        </x-border-form>

                                        {{-- SECTION: DATA SOSIAL --}}
                                        <x-border-form :title="__('Data Sosial')" :align="__('start')" :bgcolor="__('bg-white')">
                                            <div class="space-y-5">
                                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-5">
                                                    {{-- Jenis Kelamin --}}
                                                    <div>
                                                        <x-input-label value="Jenis Kelamin" :required="true" />
                                                        <x-select-input
                                                            wire:model.live="dataPasien.pasien.jenisKelamin.jenisKelaminId"
                                                            :error="$errors->has(
                                                                'dataPasien.pasien.jenisKelamin.jenisKelaminId',
                                                            )">
                                                            <option value="">-- Pilih Jenis Kelamin --</option>
                                                            @foreach ($jenisKelaminOptions as $option)
                                                                <option value="{{ $option['id'] }}"
                                                                    {{ ($dataPasien['pasien']['jenisKelamin']['jenisKelaminId'] ?? '') == $option['id'] ? 'selected' : '' }}>
                                                                    {{ $option['id'] }}. {{ $option['desc'] }}
                                                                </option>
                                                            @endforeach
                                                        </x-select-input>
                                                        <x-input-error :messages="$errors->get(
                                                            'dataPasien.pasien.jenisKelamin.jenisKelaminId',
                                                        )" class="mt-1" />
                                                    </div>

                                                    {{-- Agama --}}
                                                    <div>
                                                        <x-input-label value="Agama" :required="true" />
                                                        <x-select-input
                                                            wire:model.live="dataPasien.pasien.agama.agamaId"
                                                            :error="$errors->has('dataPasien.pasien.agama.agamaId')">
                                                            <option value="">-- Pilih Agama --</option>
                                                            @foreach ($agamaOptions as $option)
                                                                <option value="{{ $option['id'] }}"
                                                                    {{ ($dataPasien['pasien']['agama']['agamaId'] ?? '') == $option['id'] ? 'selected' : '' }}>
                                                                    {{ $option['id'] }}. {{ $option['desc'] }}
                                                                </option>
                                                            @endforeach
                                                        </x-select-input>
                                                        <x-input-error :messages="$errors->get('dataPasien.pasien.agama.agamaId')" class="mt-1" />
                                                    </div>

                                                    {{-- Status Perkawinan --}}
                                                    <div>
                                                        <x-input-label value="Status Perkawinan" :required="true" />
                                                        <x-select-input
                                                            wire:model.live="dataPasien.pasien.statusPerkawinan.statusPerkawinanId"
                                                            :error="$errors->has(
                                                                'dataPasien.pasien.statusPerkawinan.statusPerkawinanId',
                                                            )">
                                                            <option value="">-- Pilih Status Perkawinan --
                                                            </option>
                                                            @foreach ($statusPerkawinanOptions as $option)
                                                                <option value="{{ $option['id'] }}"
                                                                    {{ ($dataPasien['pasien']['statusPerkawinan'][
                                                                        '
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            statusPerkawinanId'
                                                                    ] ??
                                                                        '') ==
                                                                    $option['id']
                                                                        ? 'selected'
                                                                        : '' }}>
                                                                    {{ $option['id'] }}. {{ $option['desc'] }}
                                                                </option>
                                                            @endforeach
                                                        </x-select-input>
                                                        <x-input-error :messages="$errors->get(
                                                            'dataPasien.pasien.statusPerkawinan.statusPerkawinanId',
                                                        )" class="mt-1" />
                                                    </div>

                                                    {{-- Pendidikan --}}
                                                    <div>
                                                        <x-input-label value="Pendidikan" :required="true" />
                                                        <x-select-input
                                                            wire:model.live="dataPasien.pasien.pendidikan.pendidikanId"
                                                            :error="$errors->has(
                                                                'dataPasien.pasien.pendidikan.pendidikanId',
                                                            )">
                                                            <option value="">-- Pilih Pendidikan --</option>
                                                            @foreach ($pendidikanOptions as $option)
                                                                <option value="{{ $option['id'] }}"
                                                                    {{ ($dataPasien['pasien']['pendidikan']['pendidikanId'] ?? '') == $option['id'] ? 'selected' : '' }}>
                                                                    {{ $option['id'] }}. {{ $option['desc'] }}
                                                                </option>
                                                            @endforeach
                                                        </x-select-input>
                                                        <x-input-error :messages="$errors->get(
                                                            'dataPasien.pasien.pendidikan.pendidikanId',
                                                        )" class="mt-1" />
                                                    </div>

                                                    {{-- Pekerjaan --}}
                                                    <div>
                                                        <x-input-label value="Pekerjaan" :required="true" />
                                                        <x-select-input
                                                            wire:model.live="dataPasien.pasien.pekerjaan.pekerjaanId"
                                                            :error="$errors->has(
                                                                'dataPasien.pasien.pekerjaan.pekerjaanId',
                                                            )">
                                                            <option value="">-- Pilih Pekerjaan --</option>
                                                            @foreach ($pekerjaanOptions as $option)
                                                                <option value="{{ $option['id'] }}"
                                                                    {{ ($dataPasien['pasien']['pekerjaan']['pekerjaanId'] ?? '') == $option['id'] ? 'selected' : '' }}>
                                                                    {{ $option['id'] }}. {{ $option['desc'] }}
                                                                </option>
                                                            @endforeach
                                                        </x-select-input>
                                                        <x-input-error :messages="$errors->get(
                                                            'dataPasien.pasien.pekerjaan.pekerjaanId',
                                                        )" class="mt-1" />
                                                    </div>


                                                </div>
                                            </div>
                                        </x-border-form>

                                        {{-- SECTION: DATA BUDAYA --}}
                                        <x-border-form :title="__('Data Budaya')" :align="__('start')" :bgcolor="__('bg-white')">
                                            <div class="space-y-5">
                                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-5">
                                                    {{-- Golongan Darah --}}
                                                    <div>
                                                        <x-input-label value="Golongan Darah" />
                                                        <x-select-input
                                                            wire:model.live="dataPasien.pasien.golonganDarah.golonganDarahId"
                                                            :error="$errors->has(
                                                                'dataPasien.pasien.golonganDarah.golonganDarahId',
                                                            )">
                                                            <option value="">-- Pilih Golongan Darah --</option>
                                                            @foreach ($golonganDarahOptions as $option)
                                                                <option value="{{ $option['id'] }}"
                                                                    {{ ($dataPasien['pasien']['golonganDarah']['golonganDarahId'] ?? '') == $option['id'] ? 'selected' : '' }}>
                                                                    {{ $option['id'] }}. {{ $option['desc'] }}
                                                                </option>
                                                            @endforeach
                                                        </x-select-input>
                                                        <x-input-error :messages="$errors->get(
                                                            'dataPasien.pasien.golonganDarah.golonganDarahId',
                                                        )" class="mt-1" />
                                                    </div>

                                                    {{-- Kewarganegaraan --}}
                                                    <div>
                                                        <x-input-label value="Kewarganegaraan" />
                                                        <x-text-input
                                                            wire:model.live="dataPasien.pasien.kewarganegaraan"
                                                            :error="$errors->has(
                                                                'dataPasien.pasien.kewarganegaraan',
                                                            )" class="w-full mt-1" />
                                                        <x-input-error :messages="$errors->get('dataPasien.pasien.kewarganegaraan')" class="mt-1" />
                                                    </div>

                                                    {{-- Suku --}}
                                                    <div>
                                                        <x-input-label value="Suku" />
                                                        <x-text-input wire:model.live="dataPasien.pasien.suku"
                                                            :error="$errors->has('dataPasien.pasien.suku')" class="w-full mt-1" />
                                                        <x-input-error :messages="$errors->get('dataPasien.pasien.suku')" class="mt-1" />
                                                    </div>

                                                    {{-- Bahasa --}}
                                                    <div>
                                                        <x-input-label value="Bahasa" />
                                                        <x-text-input wire:model.live="dataPasien.pasien.bahasa"
                                                            :error="$errors->has('dataPasien.pasien.bahasa')" placeholder="Bahasa yang digunakan"
                                                            class="w-full mt-1" />
                                                        <x-input-error :messages="$errors->get('dataPasien.pasien.bahasa')" class="mt-1" />
                                                    </div>

                                                    {{-- Status --}}
                                                    <div>
                                                        <x-input-label value="Status" />
                                                        <x-select-input
                                                            wire:model.live="dataPasien.pasien.status.statusId"
                                                            :error="$errors->has(
                                                                'dataPasien.pasien.status.statusId',
                                                            )">
                                                            <option value="">-- Pilih Status --</option>
                                                            @foreach ($statusOptions as $option)
                                                                <option value="{{ $option['id'] }}"
                                                                    {{ ($dataPasien['pasien']['status']['statusId'] ?? '') == $option['id'] ? 'selected' : '' }}>
                                                                    {{ $option['id'] }}. {{ $option['desc'] }}
                                                                </option>
                                                            @endforeach
                                                        </x-select-input>
                                                        <x-input-error :messages="$errors->get('dataPasien.pasien.status.statusId')" class="mt-1" />
                                                    </div>

                                                </div>
                                            </div>
                                        </x-border-form>

                                        <div class="grid grid-cols-1 gap-2 ">
                                            {{-- SECTION: IDENTITAS --}}
                                            <x-border-form :title="__('Identitas')" :align="__('start')" :bgcolor="__('bg-white')">
                                                <div class="space-y-5">
                                                    {{-- Patient UUID --}}
                                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                                                        <div class="sm:col-span-3">
                                                            <x-input-label value="Patient UUID" :required="true" />
                                                            <x-text-input
                                                                wire:model.live="dataPasien.pasien.identitas.patientUuid"
                                                                :error="$errors->has(
                                                                    'dataPasien.pasien.identitas.patientUuid',
                                                                )" class="w-full mt-1" />
                                                            <x-input-error :messages="$errors->get(
                                                                'dataPasien.pasien.identitas.patientUuid',
                                                            )" class="mt-1" />
                                                        </div>
                                                        <div class="flex items-end">
                                                            <x-primary-button
                                                                wire:click.prevent="UpdatepatientUuid('{{ $dataPasien['pasien']['identitas']['nik'] ?? '' }}')"
                                                                type="button" wire:loading.remove class="w-full">
                                                                UUID Pasien
                                                            </x-primary-button>
                                                            <div wire:loading wire:target="UpdatepatientUuid">
                                                                <x-loading />
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                        {{-- NIK --}}
                                                        <div>
                                                            <x-input-label value="NIK" :required="true" />
                                                            <x-text-input
                                                                wire:model.live="dataPasien.pasien.identitas.nik"
                                                                :error="$errors->has(
                                                                    'dataPasien.pasien.identitas.nik',
                                                                )" class="w-full mt-1" />
                                                            <x-input-error :messages="$errors->get('dataPasien.pasien.identitas.nik')" class="mt-1" />
                                                        </div>

                                                        {{-- ID BPJS --}}
                                                        <div>
                                                            <x-input-label value="ID BPJS" />
                                                            <x-text-input
                                                                wire:model.live="dataPasien.pasien.identitas.idbpjs"
                                                                placeholder="13 digit" class="w-full mt-1" />
                                                        </div>

                                                        {{-- Paspor --}}
                                                        <div>
                                                            <x-input-label value="Paspor" />
                                                            <x-text-input
                                                                wire:model.live="dataPasien.pasien.identitas.pasport"
                                                                placeholder="untuk WNA / WNI" class="w-full mt-1" />
                                                        </div>
                                                    </div>

                                                    {{-- Catatan --}}
                                                    <div class="p-3 rounded-lg bg-yellow-50">
                                                        <p class="text-sm text-gray-600">
                                                            <strong>Catatan:</strong><br>
                                                            1. Jika Pasien (Tidak dikenal) NIK diisi Kosong<br>
                                                            2. Isi alamat sesuai dengan ditemukannya pasien<br>
                                                            3. Untuk Pasien Bayi Baru lahir:<br>
                                                            &nbsp;&nbsp;&nbsp;- Isi NIK dengan "NIK Ibu bayi"<br>
                                                            &nbsp;&nbsp;&nbsp;- Nama bayi dengan format "Bayi Ny(Nama
                                                            Ibu)"
                                                        </p>
                                                    </div>
                                                </div>
                                            </x-border-form>
                                        </div>

                                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">


                                            {{-- SECTION: ALAMAT  --}}
                                            <x-border-form :title="__('Alamat ')" :align="__('start')" :bgcolor="__('bg-white')">
                                                <div class="mt-16 space-y-5">
                                                    {{-- Alamat --}}
                                                    <div>
                                                        <x-input-label value="Alamat" :required="true" />
                                                        <x-textarea
                                                            wire:model.live="dataPasien.pasien.identitas.alamat"
                                                            :error="$errors->has(
                                                                'dataPasien.pasien.identitas.alamat',
                                                            )" class="w-full mt-1" rows="2" />
                                                        <x-input-error :messages="$errors->get('dataPasien.pasien.identitas.alamat')" class="mt-1" />
                                                    </div>

                                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                                        {{-- RT --}}
                                                        <div>
                                                            <x-input-label value="RT" :required="true" />
                                                            <x-text-input
                                                                wire:model.live="dataPasien.pasien.identitas.rt"
                                                                placeholder="3 digit" :error="$errors->has(
                                                                    'dataPasien.pasien.identitas.rt',
                                                                )"
                                                                class="w-full mt-1" />
                                                            <x-input-error :messages="$errors->get('dataPasien.pasien.identitas.rt')" class="mt-1" />
                                                        </div>

                                                        {{-- RW --}}
                                                        <div>
                                                            <x-input-label value="RW" :required="true" />
                                                            <x-text-input
                                                                wire:model.live="dataPasien.pasien.identitas.rw"
                                                                placeholder="3 digit" :error="$errors->has(
                                                                    'dataPasien.pasien.identitas.rw',
                                                                )"
                                                                class="w-full mt-1" />
                                                            <x-input-error :messages="$errors->get('dataPasien.pasien.identitas.rw')" class="mt-1" />
                                                        </div>

                                                        {{-- Kode Pos --}}
                                                        <div>
                                                            <x-input-label value="Kode Pos" />
                                                            <x-text-input
                                                                wire:model.live="dataPasien.pasien.identitas.kodepos"
                                                                class="w-full mt-1" />
                                                        </div>
                                                    </div>

                                                    {{-- Alamat Lengkap --}}
                                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-1">
                                                        {{-- Desa --}}
                                                        <div>
                                                            @if ($formMode == 'create')
                                                                <livewire:lov.desa.lov-desa target="desa_identitas"
                                                                    :propinsiId="$dataPasien['pasien']['identitas'][
                                                                        'propinsiId'
                                                                    ] ?? null" :kotaId="$dataPasien['pasien']['identitas'][
                                                                        'kotaId'
                                                                    ] ?? null" />
                                                            @else
                                                                <livewire:lov.desa.lov-desa target="desa_identitas"
                                                                    :propinsiId="$dataPasien['pasien']['identitas'][
                                                                        'propinsiId'
                                                                    ] ?? null" :kotaId="$dataPasien['pasien']['identitas'][
                                                                        'kotaId'
                                                                    ] ?? null"
                                                                    :initialDesaId="$dataPasien['pasien']['identitas'][
                                                                        'desaId'
                                                                    ] ?? null" />
                                                            @endif
                                                            <x-input-error :messages="$errors->get(
                                                                'dataPasien.pasien.identitas.desaId',
                                                            )" class="mt-1" />
                                                        </div>

                                                        {{-- Kota --}}
                                                        <div>
                                                            @if ($formMode == 'create')
                                                                <livewire:lov.kabupaten.lov-kabupaten
                                                                    target="kota_identitas" :propinsiId="$dataPasien['pasien']['identitas'][
                                                                        'propinsiId'
                                                                    ] ?? null"
                                                                    :initialKabId="$dataPasien['pasien']['identitas'][
                                                                        'kotaId'
                                                                    ] ?? null" {{-- Akan terisi 3504 --}}
                                                                    :showAsInput="true" />
                                                            @else
                                                                <livewire:lov.kabupaten.lov-kabupaten
                                                                    target="kota_identitas" :propinsiId="$dataPasien['pasien']['identitas'][
                                                                        'propinsiId'
                                                                    ] ?? null"
                                                                    :initialKabId="$dataPasien['pasien']['identitas'][
                                                                        'kotaId'
                                                                    ] ?? null" :showAsInput="true" />
                                                            @endif

                                                            <x-input-error :messages="$errors->get(
                                                                'dataPasien.pasien.identitas.kotaId',
                                                            )" class="mt-1" />
                                                        </div>



                                                        {{-- Negara --}}
                                                        <div>
                                                            <x-input-label value="Negara" />
                                                            <x-text-input
                                                                wire:model.live="dataPasien.pasien.identitas.negara"
                                                                placeholder="isi dengan ID" class="w-full mt-1" />
                                                        </div>
                                                    </div>
                                                </div>
                                            </x-border-form>

                                            {{-- SECTION: ALAMAT DOMISILI --}}
                                            <x-border-form :title="__('Alamat Domisili')" :align="__('start')" :bgcolor="__('bg-white')">
                                                <div class="space-y-5">

                                                    {{-- Checkbox Sama dengan Identitas --}}
                                                    <div class="flex justify-end">
                                                        <x-check-box value='1' :label="__('Sama dengan Identitas')"
                                                            wire:model.live="dataPasien.pasien.domisil.samadgnidentitas" />
                                                    </div>

                                                    {{-- Alamat Domisili --}}
                                                    <div>
                                                        <x-input-label value="Alamat Domisili" :required="true" />

                                                        <x-textarea wire:key="domisil-alamat-{{ $domisilSyncTick }}"
                                                            wire:model.live="dataPasien.pasien.domisil.alamat"
                                                            :error="$errors->has('dataPasien.pasien.domisil.alamat')" class="w-full mt-1" rows="2" />

                                                        <x-input-error :messages="$errors->get('dataPasien.pasien.domisil.alamat')" class="mt-1" />
                                                    </div>

                                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

                                                        {{-- RT Domisili --}}
                                                        <div wire:key="domisil-rt-{{ $domisilSyncTick }}">
                                                            <x-input-label value="RT Domisili" :required="true" />
                                                            <x-text-input
                                                                wire:model.live="dataPasien.pasien.domisil.rt"
                                                                placeholder="3 digit" :error="$errors->has('dataPasien.pasien.domisil.rt')"
                                                                class="w-full mt-1" />
                                                            <x-input-error :messages="$errors->get('dataPasien.pasien.domisil.rt')" class="mt-1" />
                                                        </div>

                                                        {{-- RW Domisili --}}
                                                        <div wire:key="domisil-rw-{{ $domisilSyncTick }}">
                                                            <x-input-label value="RW Domisili" :required="true" />
                                                            <x-text-input
                                                                wire:model.live="dataPasien.pasien.domisil.rw"
                                                                placeholder="3 digit" :error="$errors->has('dataPasien.pasien.domisil.rw')"
                                                                class="w-full mt-1" />
                                                            <x-input-error :messages="$errors->get('dataPasien.pasien.domisil.rw')" class="mt-1" />
                                                        </div>

                                                        {{-- Kode Pos Domisili --}}
                                                        <div wire:key="domisil-kodepos-{{ $domisilSyncTick }}">
                                                            <x-input-label value="Kode Pos Domisili" />
                                                            <x-text-input
                                                                wire:model.live="dataPasien.pasien.domisil.kodepos"
                                                                class="w-full mt-1" />
                                                        </div>

                                                    </div>


                                                    {{-- Alamat Lengkap Domisili --}}
                                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-1">
                                                        {{-- Desa Domisili --}}
                                                        <div>
                                                            @if ($formMode == 'create')
                                                                <livewire:lov.desa.lov-desa target="desa_domisil"
                                                                    :propinsiId="$dataPasien['pasien']['domisil'][
                                                                        'propinsiId'
                                                                    ] ?? null" :kotaId="$dataPasien['pasien']['domisil'][
                                                                        'kotaId'
                                                                    ] ?? null" />
                                                            @else
                                                                <livewire:lov.desa.lov-desa target="desa_domisil"
                                                                    :propinsiId="$dataPasien['pasien']['domisil'][
                                                                        'propinsiId'
                                                                    ] ?? null" :kotaId="$dataPasien['pasien']['domisil'][
                                                                        'kotaId'
                                                                    ] ?? null"
                                                                    :initialDesaId="$dataPasien['pasien']['domisil'][
                                                                        'desaId'
                                                                    ] ?? null" />
                                                            @endif
                                                            <x-input-error :messages="$errors->get('dataPasien.pasien.domisil.desaId')" class="mt-1" />
                                                        </div>



                                                        {{-- Kota Domisili --}}
                                                        <div>
                                                            {{-- Mode CREATE --}}
                                                            @if ($formMode == 'create')
                                                                <livewire:lov.kabupaten.lov-kabupaten
                                                                    target="kota_domisil" target="kota_domisil"
                                                                    :propinsiId="$dataPasien['pasien']['domisil'][
                                                                        'propinsiId'
                                                                    ] ?? null" :initialKabId="$dataPasien['pasien']['domisil'][
                                                                        'kotaId'
                                                                    ] ?? null"
                                                                    :showAsInput="true" />
                                                            @else
                                                                <livewire:lov.kabupaten.lov-kabupaten
                                                                    target="kota_domisil" :propinsiId="$dataPasien['pasien']['domisil'][
                                                                        'propinsiId'
                                                                    ] ?? null"
                                                                    :initialKabId="$dataPasien['pasien']['domisil'][
                                                                        'kotaId'
                                                                    ] ?? null" :showAsInput="true" />
                                                            @endif

                                                            <x-input-error :messages="$errors->get('dataPasien.pasien.domisil.kotaId')" class="mt-1" />
                                                        </div>


                                                        {{-- Negara Domisili --}}
                                                        <div>
                                                            <x-input-label value="Negara Domisili" />
                                                            <x-text-input
                                                                wire:model.live="dataPasien.pasien.domisil.negara"
                                                                placeholder="isi dengan ID" class="w-full mt-1" />
                                                        </div>
                                                    </div>
                                                </div>
                                            </x-border-form>
                                        </div>

                                        {{-- SECTION: KONTAK --}}
                                        <x-border-form :title="__('Kontak')" :align="__('start')" :bgcolor="__('bg-white')">
                                            <div class="space-y-5">
                                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                    {{-- No HP Pasien --}}
                                                    <div>
                                                        <x-input-label value="No HP Pasien" :required="true" />
                                                        <div class="flex gap-2 mt-1">
                                                            <x-text-input
                                                                wire:model.live="dataPasien.pasien.kontak.kodenegara"
                                                                placeholder="Kode" class="w-20" />
                                                            <x-text-input
                                                                wire:model.live="dataPasien.pasien.kontak.nomerTelponSelulerPasien"
                                                                :error="$errors->has(
                                                                    'dataPasien.pasien.kontak.nomerTelponSelulerPasien',
                                                                )" class="flex-1" />
                                                        </div>
                                                        <x-input-error :messages="$errors->get(
                                                            'dataPasien.pasien.kontak.nomerTelponSelulerPasien',
                                                        )" class="mt-1" />




                                                    </div>

                                                    {{-- No HP Lain --}}
                                                    <div>
                                                        <x-input-label value="No HP Lain" />
                                                        <div class="flex gap-2 mt-1">
                                                            <x-text-input
                                                                wire:model.live="dataPasien.pasien.kontak.kodenegara"
                                                                placeholder="Kode" class="w-20" />
                                                            <x-text-input
                                                                wire:model.live="dataPasien.pasien.kontak.nomerTelponLain"
                                                                class="flex-1" />
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </x-border-form>

                                        {{-- SECTION: HUBUNGAN KELUARGA --}}
                                        <x-border-form :title="__('Hubungan Keluarga')" :align="__('start')" :bgcolor="__('bg-white')">
                                            <div class="space-y-5">
                                                {{-- Subsection: Penanggung Jawab --}}
                                                <div class="p-4 rounded-lg bg-yellow-50">
                                                    <h4 class="mb-3 font-semibold text-yellow-800">Penanggung Jawab
                                                    </h4>

                                                    <div class="grid grid-cols-1 gap-4">
                                                        {{-- Nama Penanggung Jawab + No HP --}}
                                                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                                            <div class="sm:col-span-2">
                                                                <x-input-label value="Nama Penanggung Jawab"
                                                                    :required="true" />
                                                                <x-text-input
                                                                    wire:model.live="dataPasien.pasien.hubungan.namaPenanggungJawab"
                                                                    :error="$errors->has(
                                                                        'dataPasien.pasien.hubungan.namaPenanggungJawab',
                                                                    )" class="w-full mt-1"
                                                                    style="text-transform:uppercase" />
                                                                <x-input-error :messages="$errors->get(
                                                                    'dataPasien.pasien.hubungan.namaPenanggungJawab',
                                                                )" class="mt-1" />
                                                            </div>
                                                            <div class="grid grid-cols-2 gap-2">
                                                                <div>
                                                                    <x-input-label value="Kode Negara" />
                                                                    <x-text-input
                                                                        wire:model.live="dataPasien.pasien.hubungan.kodenegaraPenanggungJawab"
                                                                        class="w-full mt-1" />
                                                                </div>
                                                                <div>
                                                                    <x-input-label value="No HP"
                                                                        :required="true" />
                                                                    <x-text-input
                                                                        wire:model.live="dataPasien.pasien.hubungan.nomerTelponSelulerPenanggungJawab"
                                                                        :error="$errors->has(
                                                                            'dataPasien.pasien.hubungan.nomerTelponSelulerPenanggungJawab',
                                                                        )" class="w-full mt-1" />
                                                                    <x-input-error :messages="$errors->get(
                                                                        'dataPasien.pasien.hubungan.nomerTelponSelulerPenanggungJawab',
                                                                    )"
                                                                        class="mt-1" />
                                                                </div>
                                                            </div>
                                                        </div>

                                                        {{-- Hubungan dengan Pasien --}}
                                                        <div class="sm:w-1/2">
                                                            <x-input-label value="Hubungan dengan Pasien"
                                                                :required="true" />
                                                            <x-select-input
                                                                wire:model.live="dataPasien.pasien.hubungan.hubunganDgnPasien.hubunganDgnPasienId"
                                                                :error="$errors->has(
                                                                    'dataPasien.pasien.hubungan.hubunganDgnPasien.hubunganDgnPasienId',
                                                                )">
                                                                <option value="">-- Pilih Hubungan --</option>
                                                                @foreach ($hubunganDgnPasienOptions as $option)
                                                                    <option value="{{ $option['id'] }}"
                                                                        {{ ($dataPasien['pasien']['hubungan']['hubunganDgnPasien']['hubunganDgnPasienId'] ?? '') == $option['id'] ? 'selected' : '' }}>
                                                                        {{ $option['id'] }}. {{ $option['desc'] }}
                                                                    </option>
                                                                @endforeach
                                                            </x-select-input>
                                                            <x-input-error :messages="$errors->get(
                                                                'dataPasien.pasien.hubungan.hubunganDgnPasien.hubunganDgnPasienId',
                                                            )" class="mt-1" />
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Subsection: Ayah --}}
                                                <div>
                                                    <h4 class="mb-3 font-semibold text-gray-700">Data Ayah</h4>
                                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                                        <div class="sm:col-span-2">
                                                            <x-input-label value="Nama Ayah" :required="true" />
                                                            <x-text-input
                                                                wire:model.live="dataPasien.pasien.hubungan.namaAyah"
                                                                :error="$errors->has(
                                                                    'dataPasien.pasien.hubungan.namaAyah',
                                                                )" class="w-full mt-1"
                                                                style="text-transform:uppercase" />
                                                            <x-input-error :messages="$errors->get(
                                                                'dataPasien.pasien.hubungan.namaAyah',
                                                            )" class="mt-1" />
                                                        </div>
                                                        <div class="grid grid-cols-2 gap-2">
                                                            <div>
                                                                <x-input-label value="Kode Negara" />
                                                                <x-text-input
                                                                    wire:model.live="dataPasien.pasien.hubungan.kodenegaraAyah"
                                                                    class="w-full mt-1" />
                                                            </div>
                                                            <div>
                                                                <x-input-label value="No HP Ayah"
                                                                    :required="true" />
                                                                <x-text-input
                                                                    wire:model.live="dataPasien.pasien.hubungan.nomerTelponSelulerAyah"
                                                                    :error="$errors->has(
                                                                        'dataPasien.pasien.hubungan.nomerTelponSelulerAyah',
                                                                    )" class="w-full mt-1" />
                                                                <x-input-error :messages="$errors->get(
                                                                    'dataPasien.pasien.hubungan.nomerTelponSelulerAyah',
                                                                )" class="mt-1" />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Subsection: Ibu --}}
                                                <div>
                                                    <h4 class="mb-3 font-semibold text-gray-700">Data Ibu</h4>
                                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                                        <div class="sm:col-span-2">
                                                            <x-input-label value="Nama Ibu" :required="true" />
                                                            <x-text-input
                                                                wire:model.live="dataPasien.pasien.hubungan.namaIbu"
                                                                :error="$errors->has(
                                                                    'dataPasien.pasien.hubungan.namaIbu',
                                                                )" class="w-full mt-1"
                                                                style="text-transform:uppercase" />
                                                            <x-input-error :messages="$errors->get(
                                                                'dataPasien.pasien.hubungan.namaIbu',
                                                            )" class="mt-1" />
                                                        </div>
                                                        <div class="grid grid-cols-2 gap-2">
                                                            <div>
                                                                <x-input-label value="Kode Negara" />
                                                                <x-text-input
                                                                    wire:model.live="dataPasien.pasien.hubungan.kodenegaraIbu"
                                                                    class="w-full mt-1" />
                                                            </div>
                                                            <div>
                                                                <x-input-label value="No HP Ibu" :required="true" />
                                                                <x-text-input
                                                                    wire:model.live="dataPasien.pasien.hubungan.nomerTelponSelulerIbu"
                                                                    :error="$errors->has(
                                                                        'dataPasien.pasien.hubungan.nomerTelponSelulerIbu',
                                                                    )" class="w-full mt-1" />
                                                                <x-input-error :messages="$errors->get(
                                                                    'dataPasien.pasien.hubungan.nomerTelponSelulerIbu',
                                                                )" class="mt-1" />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </x-border-form>

                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>


            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        @if ($formMode === 'create')
                            Pastikan semua data diisi dengan benar sebelum menyimpan.
                        @else
                            Periksa perubahan data sebelum menyimpan.
                        @endif
                    </div>

                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">
                            Batal
                        </x-secondary-button>

                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove>Simpan</span>
                            <span wire:loading>
                                <svg class="w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                        stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                Menyimpan...
                            </span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
