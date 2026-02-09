<?php

namespace App\Http\Livewire\Pages\Master\MasterPasien;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use MasterPasienTrait;

    public string $formMode = 'create'; // create|edit
    public string $isOpenMode = 'insert'; // insert|update|tampil
    public bool $disabledProperty = false;

    // Data utama sesuai struktur dari contoh
    public array $dataPasien = [
        'pasien' => [
            'pasientidakdikenal' => [],
            'regNo' => '',
            'gelarDepan' => '',
            'regName' => '',
            'gelarBelakang' => '',
            'namaPanggilan' => '',
            'tempatLahir' => '',
            'tglLahir' => '',
            'thn' => '',
            'bln' => '',
            'hari' => '',
            'jenisKelamin' => [
                'jenisKelaminId' => 1,
                'jenisKelaminDesc' => 'Laki-laki',
                'jenisKelaminOptions' => [['jenisKelaminId' => 0, 'jenisKelaminDesc' => 'Tidak diketaui'], ['jenisKelaminId' => 1, 'jenisKelaminDesc' => 'Laki-laki'], ['jenisKelaminId' => 2, 'jenisKelaminDesc' => 'Perempuan'], ['jenisKelaminId' => 3, 'jenisKelaminDesc' => 'Tidak dapat di tentukan'], ['jenisKelaminId' => 4, 'jenisKelaminDesc' => 'Tidak Mengisi']],
            ],
            'agama' => [
                'agamaId' => '1',
                'agamaDesc' => 'Islam',
                'agamaOptions' => [['agamaId' => 1, 'agamaDesc' => 'Islam'], ['agamaId' => 2, 'agamaDesc' => 'Kristen (Protestan)'], ['agamaId' => 3, 'agamaDesc' => 'Katolik'], ['agamaId' => 4, 'agamaDesc' => 'Hindu'], ['agamaId' => 5, 'agamaDesc' => 'Budha'], ['agamaId' => 6, 'agamaDesc' => 'Konghucu'], ['agamaId' => 7, 'agamaDesc' => 'Penghayat'], ['agamaId' => 8, 'agamaDesc' => 'Lain-lain']],
            ],
            'statusPerkawinan' => [
                'statusPerkawinanId' => '1',
                'statusPerkawinanDesc' => 'Belum Kawin',
                'statusPerkawinanOptions' => [['statusPerkawinanId' => 1, 'statusPerkawinanDesc' => 'Belum Kawin'], ['statusPerkawinanId' => 2, 'statusPerkawinanDesc' => 'Kawin'], ['statusPerkawinanId' => 3, 'statusPerkawinanDesc' => 'Cerai Hidup'], ['statusPerkawinanId' => 4, 'statusPerkawinanDesc' => 'Cerai Mati']],
            ],
            'pendidikan' => [
                'pendidikanId' => '3',
                'pendidikanDesc' => 'SLTA Sederajat',
                'pendidikanOptions' => [['pendidikanId' => 0, 'pendidikanDesc' => 'Tidak Sekolah'], ['pendidikanId' => 1, 'pendidikanDesc' => 'SD'], ['pendidikanId' => 2, 'pendidikanDesc' => 'SLTP Sederajat'], ['pendidikanId' => 3, 'pendidikanDesc' => 'SLTA Sederajat'], ['pendidikanId' => 4, 'pendidikanDesc' => 'D1-D3'], ['pendidikanId' => 5, 'pendidikanDesc' => 'D4'], ['pendidikanId' => 6, 'pendidikanDesc' => 'S1'], ['pendidikanId' => 7, 'pendidikanDesc' => 'S2'], ['pendidikanId' => 8, 'pendidikanDesc' => 'S3']],
            ],
            'pekerjaan' => [
                'pekerjaanId' => '4',
                'pekerjaanDesc' => 'Pegawai Swasta/ Wiraswasta',
                'pekerjaanOptions' => [['pekerjaanId' => 0, 'pekerjaanDesc' => 'Tidak Bekerja'], ['pekerjaanId' => 1, 'pekerjaanDesc' => 'PNS'], ['pekerjaanId' => 2, 'pekerjaanDesc' => 'TNI/POLRI'], ['pekerjaanId' => 3, 'pekerjaanDesc' => 'BUMN'], ['pekerjaanId' => 4, 'pekerjaanDesc' => 'Pegawai Swasta/ Wiraswasta'], ['pekerjaanId' => 5, 'pekerjaanDesc' => 'Lain-Lain']],
            ],
            'golonganDarah' => [
                'golonganDarahId' => '13',
                'golonganDarahDesc' => 'Tidak Tahu',
                'golonganDarahOptions' => [['golonganDarahId' => 1, 'golonganDarahDesc' => 'A'], ['golonganDarahId' => 2, 'golonganDarahDesc' => 'B'], ['golonganDarahId' => 3, 'golonganDarahDesc' => 'AB'], ['golonganDarahId' => 4, 'golonganDarahDesc' => 'O'], ['golonganDarahId' => 5, 'golonganDarahDesc' => 'A+'], ['golonganDarahId' => 6, 'golonganDarahDesc' => 'A-'], ['golonganDarahId' => 7, 'golonganDarahDesc' => 'B+'], ['golonganDarahId' => 8, 'golonganDarahDesc' => 'B-'], ['golonganDarahId' => 9, 'golonganDarahDesc' => 'AB+'], ['golonganDarahId' => 10, 'golonganDarahDesc' => 'AB-'], ['golonganDarahId' => 11, 'golonganDarahDesc' => 'O+'], ['golonganDarahId' => 12, 'golonganDarahDesc' => 'O-'], ['golonganDarahId' => 13, 'golonganDarahDesc' => 'Tidak Tahu'], ['golonganDarahId' => 14, 'golonganDarahDesc' => 'O Rhesus'], ['golonganDarahId' => 15, 'golonganDarahDesc' => '#']],
            ],
            'kewarganegaraan' => 'INDONESIA',
            'suku' => 'Jawa',
            'bahasa' => 'Indonesia / Jawa',
            'status' => [
                'statusId' => '1',
                'statusDesc' => 'Aktif / Hidup',
                'statusOptions' => [['statusId' => 0, 'statusDesc' => 'Tidak Aktif / Batal'], ['statusId' => 1, 'statusDesc' => 'Aktif / Hidup'], ['statusId' => 2, 'statusDesc' => 'Meninggal']],
            ],
            'domisil' => [
                'samadgnidentitas' => [],
                'alamat' => '',
                'rt' => '',
                'rw' => '',
                'kodepos' => '',
                'desaId' => '',
                'kecamatanId' => '',
                'kotaId' => '3504',
                'propinsiId' => '35',
                'desaName' => '',
                'kecamatanName' => '',
                'kotaName' => 'TULUNGAGUNG',
                'propinsiName' => 'JAWA TIMUR',
            ],
            'identitas' => [
                'nik' => '',
                'idbpjs' => '',
                'patientUuid' => '',
                'pasport' => '',
                'alamat' => '',
                'rt' => '',
                'rw' => '',
                'kodepos' => '',
                'desaId' => '',
                'kecamatanId' => '',
                'kotaId' => '3504',
                'propinsiId' => '35',
                'desaName' => '',
                'kecamatanName' => '',
                'kotaName' => 'TULUNGAGUNG',
                'propinsiName' => 'JAWA TIMUR',
                'negara' => 'ID',
            ],
            'kontak' => [
                'kodenegara' => '62',
                'nomerTelponSelulerPasien' => '',
                'nomerTelponLain' => '',
            ],
            'hubungan' => [
                'namaAyah' => '',
                'kodenegaraAyah' => '62',
                'nomerTelponSelulerAyah' => '',
                'namaIbu' => '',
                'kodenegaraIbu' => '62',
                'nomerTelponSelulerIbu' => '',
                'namaPenanggungJawab' => '',
                'kodenegaraPenanggungJawab' => '62',
                'nomerTelponSelulerPenanggungJawab' => '',
                'hubunganDgnPasien' => [
                    'hubunganDgnPasienId' => 5,
                    'hubunganDgnPasienDesc' => 'Kerabat / Saudara',
                    'hubunganDgnPasienOptions' => [['hubunganDgnPasienId' => 1, 'hubunganDgnPasienDesc' => 'Diri Sendiri'], ['hubunganDgnPasienId' => 2, 'hubunganDgnPasienDesc' => 'Orang Tua'], ['hubunganDgnPasienId' => 3, 'hubunganDgnPasienDesc' => 'Anak'], ['hubunganDgnPasienId' => 4, 'hubunganDgnPasienDesc' => 'Suami / Istri'], ['hubunganDgnPasienId' => 5, 'hubunganDgnPasienDesc' => 'Kerabaat / Saudara'], ['hubunganDgnPasienId' => 6, 'hubunganDgnPasienDesc' => 'Lain-lain']],
                ],
            ],
        ],
    ];

    // Variabel untuk LOV
    public array $jenisKelaminLov = [];
    public bool $jenisKelaminLovStatus = false;
    public string $jenisKelaminLovSearch = '';

    public array $agamaLov = [];
    public bool $agamaLovStatus = false;
    public string $agamaLovSearch = '';

    public array $statusPerkawinanLov = [];
    public bool $statusPerkawinanLovStatus = false;
    public string $statusPerkawinanLovSearch = '';

    public array $pendidikanLov = [];
    public bool $pendidikanLovStatus = false;
    public string $pendidikanLovSearch = '';

    public array $pekerjaanLov = [];
    public bool $pekerjaanLovStatus = false;
    public string $pekerjaanLovSearch = '';

    public array $golonganDarahLov = [];
    public bool $golonganDarahLovStatus = false;
    public string $golonganDarahLovSearch = '';

    public array $hubunganDgnPasienLov = [];
    public bool $hubunganDgnPasienLovStatus = false;
    public string $hubunganDgnPasienLovSearch = '';

    /* -------------------------
     | Open modal handlers
     * ------------------------- */
    #[On('master.pasien.openCreate')]
    public function openCreate(): void
    {
        $this->resetFormFields();
        $this->formMode = 'create';
        $this->isOpenMode = 'insert';
        $this->disabledProperty = false;
        $this->dataPasien['pasien']['regDate'] = date('d/m/Y H:i:s');

        // Generate regNo baru
        $jmlpasien = DB::table('rsmst_pasiens')->count();
        $this->dataPasien['pasien']['regNo'] = sprintf('%07s', $jmlpasien + 1) . 'Z';

        $this->dispatch('open-modal', name: 'master-pasien-actions');
    }

    #[On('master.pasien.openEdit')]
    public function openEdit(string $regNo): void
    {
        // Menggunakan trait untuk mendapatkan data pasien
        $pasienData = $this->findDataMasterPasien($regNo);

        if (isset($pasienData['errorMessages'])) {
            $this->dispatch('toast', type: 'error', message: 'Gagal memuat data pasien: ' . $pasienData['errorMessages']);
            return;
        }

        $this->resetFormFields();
        $this->formMode = 'edit';
        $this->isOpenMode = 'update';
        $this->disabledProperty = false;
        $this->dataPasien = $pasienData;

        $this->dispatch('open-modal', name: 'master-pasien-actions');
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'master-pasien-actions');
    }

    /* -------------------------
     | Helpers
     * ------------------------- */
    protected function resetFormFields(): void
    {
        $this->reset(['dataPasien', 'jenisKelaminLov', 'jenisKelaminLovStatus', 'jenisKelaminLovSearch', 'agamaLov', 'agamaLovStatus', 'agamaLovSearch', 'statusPerkawinanLov', 'statusPerkawinanLovStatus', 'statusPerkawinanLovSearch', 'pendidikanLov', 'pendidikanLovStatus', 'pendidikanLovSearch', 'pekerjaanLov', 'pekerjaanLovStatus', 'pekerjaanLovSearch', 'golonganDarahLov', 'golonganDarahLovStatus', 'golonganDarahLovSearch', 'hubunganDgnPasienLov', 'hubunganDgnPasienLovStatus', 'hubunganDgnPasienLovSearch']);
        $this->resetValidation();
    }

    protected function fillFormFromRow(object $row): void
    {
        // Fungsi ini diisi oleh trait MasterPasienTrait melalui findDataMasterPasien
    }

    /* -------------------------
     | LOV Handlers
     * ------------------------- */
    public function clickJeniskelaminlov(): void
    {
        $this->jenisKelaminLovStatus = true;
        $this->jenisKelaminLov = $this->dataPasien['pasien']['jenisKelamin']['jenisKelaminOptions'];
    }

    public function clickagamalov(): void
    {
        $this->agamaLovStatus = true;
        $this->agamaLov = $this->dataPasien['pasien']['agama']['agamaOptions'];
    }

    public function clickstatusPerkawinanlov(): void
    {
        $this->statusPerkawinanLovStatus = true;
        $this->statusPerkawinanLov = $this->dataPasien['pasien']['statusPerkawinan']['statusPerkawinanOptions'];
    }

    public function clickpendidikanlov(): void
    {
        $this->pendidikanLovStatus = true;
        $this->pendidikanLov = $this->dataPasien['pasien']['pendidikan']['pendidikanOptions'];
    }

    public function clickpekerjaanlov(): void
    {
        $this->pekerjaanLovStatus = true;
        $this->pekerjaanLov = $this->dataPasien['pasien']['pekerjaan']['pekerjaanOptions'];
    }

    public function clickgolonganDarahlov(): void
    {
        $this->golonganDarahLovStatus = true;
        $this->golonganDarahLov = $this->dataPasien['pasien']['golonganDarah']['golonganDarahOptions'];
    }

    public function clickhubunganDgnPasienlov(): void
    {
        $this->hubunganDgnPasienLovStatus = true;
        $this->hubunganDgnPasienLov = $this->dataPasien['pasien']['hubungan']['hubunganDgnPasien']['hubunganDgnPasienOptions'];
    }

    // LOV selection methods
    public function setMyjenisKelaminLov($id, $name): void
    {
        $this->dataPasien['pasien']['jenisKelamin']['jenisKelaminId'] = $id;
        $this->dataPasien['pasien']['jenisKelamin']['jenisKelaminDesc'] = $name;
        $this->jenisKelaminLovStatus = false;
        $this->jenisKelaminLovSearch = '';
    }

    public function setMyagamaLov($id, $name): void
    {
        $this->dataPasien['pasien']['agama']['agamaId'] = $id;
        $this->dataPasien['pasien']['agama']['agamaDesc'] = $name;
        $this->agamaLovStatus = false;
        $this->agamaLovSearch = '';
    }

    public function setMystatusPerkawinanLov($id, $name): void
    {
        $this->dataPasien['pasien']['statusPerkawinan']['statusPerkawinanId'] = $id;
        $this->dataPasien['pasien']['statusPerkawinan']['statusPerkawinanDesc'] = $name;
        $this->statusPerkawinanLovStatus = false;
        $this->statusPerkawinanLovSearch = '';
    }

    public function setMypendidikanLov($id, $name): void
    {
        $this->dataPasien['pasien']['pendidikan']['pendidikanId'] = $id;
        $this->dataPasien['pasien']['pendidikan']['pendidikanDesc'] = $name;
        $this->pendidikanLovStatus = false;
        $this->pendidikanLovSearch = '';
    }

    public function setMypekerjaanLov($id, $name): void
    {
        $this->dataPasien['pasien']['pekerjaan']['pekerjaanId'] = $id;
        $this->dataPasien['pasien']['pekerjaan']['pekerjaanDesc'] = $name;
        $this->pekerjaanLovStatus = false;
        $this->pekerjaanLovSearch = '';
    }

    public function setMygolonganDarahLov($id, $name): void
    {
        $this->dataPasien['pasien']['golonganDarah']['golonganDarahId'] = $id;
        $this->dataPasien['pasien']['golonganDarah']['golonganDarahDesc'] = $name;
        $this->golonganDarahLovStatus = false;
        $this->golonganDarahLovSearch = '';
    }

    public function setMyhubunganDgnPasienLov($id, $name): void
    {
        $this->dataPasien['pasien']['hubungan']['hubunganDgnPasien']['hubunganDgnPasienId'] = $id;
        $this->dataPasien['pasien']['hubungan']['hubunganDgnPasien']['hubunganDgnPasienDesc'] = $name;
        $this->hubunganDgnPasienLovStatus = false;
        $this->hubunganDgnPasienLovSearch = '';
    }

    /* -------------------------
     | Validation
     * ------------------------- */
    protected function rules(): array
    {
        return [
            'dataPasien.pasien.regNo' => ['required', 'string', 'max:50', $this->formMode === 'create' ? Rule::unique('rsmst_pasiens', 'reg_no') : Rule::unique('rsmst_pasiens', 'reg_no')->ignore($this->dataPasien['pasien']['regNo'] ?? null, 'reg_no')],
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
        ];
    }

    protected function messages(): array
    {
        return [
            '*.required' => ':attribute wajib diisi.',
            '*.unique' => ':attribute sudah digunakan.',
            '*.max' => ':attribute maksimal :max karakter.',
            '*.min' => ':attribute minimal :min karakter.',
            '*.numeric' => ':attribute harus berupa angka.',
            '*.digits' => ':attribute harus :digits digit.',
            '*.digits_between' => ':attribute harus antara :min sampai :max digit.',
            '*.date_format' => ':attribute harus dalam format :format.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'dataPasien.pasien.regNo' => 'ID Pasien',
            'dataPasien.pasien.regName' => 'Nama Pasien',
            'dataPasien.pasien.tempatLahir' => 'Tempat Lahir',
            'dataPasien.pasien.tglLahir' => 'Tanggal Lahir',
            'dataPasien.pasien.jenisKelamin.jenisKelaminId' => 'Jenis Kelamin',
            'dataPasien.pasien.agama.agamaId' => 'Agama',
            'dataPasien.pasien.statusPerkawinan.statusPerkawinanId' => 'Status Perkawinan',
            'dataPasien.pasien.pendidikan.pendidikanId' => 'Pendidikan',
            'dataPasien.pasien.pekerjaan.pekerjaanId' => 'Pekerjaan',
            'dataPasien.pasien.identitas.nik' => 'NIK',
            'dataPasien.pasien.identitas.alamat' => 'Alamat',
            'dataPasien.pasien.identitas.rt' => 'RT',
            'dataPasien.pasien.identitas.rw' => 'RW',
            'dataPasien.pasien.kontak.nomerTelponSelulerPasien' => 'No. HP Pasien',
            'dataPasien.pasien.hubungan.namaPenanggungJawab' => 'Nama Penanggung Jawab',
            'dataPasien.pasien.hubungan.nomerTelponSelulerPenanggungJawab' => 'No. HP Penanggung Jawab',
        ];
    }

    /* -------------------------
     | Save
     * ------------------------- */
    public function save(): void
    {
        $this->validate();

        try {
            // Konversi data untuk disimpan ke database
            $saveData = [
                'reg_no' => $this->dataPasien['pasien']['regNo'],
                'reg_name' => strtoupper($this->dataPasien['pasien']['regName']),
                'sex' => $this->dataPasien['pasien']['jenisKelamin']['jenisKelaminId'] == 1 ? 'L' : 'P',
                'birth_date' => DB::raw("to_date('" . $this->dataPasien['pasien']['tglLahir'] . "','dd/mm/yyyy')"),
                'birth_place' => strtoupper($this->dataPasien['pasien']['tempatLahir']),
                'nik_bpjs' => $this->dataPasien['pasien']['identitas']['nik'],
                'nokartu_bpjs' => $this->dataPasien['pasien']['identitas']['idbpjs'] ?? null,
                'blood' => $this->dataPasien['pasien']['golonganDarah']['golonganDarahId'] ?? null,
                'marital_status' => $this->dataPasien['pasien']['statusPerkawinan']['statusPerkawinanId'] ?? '1',
                'rel_id' => $this->dataPasien['pasien']['agama']['agamaId'] ?? '1',
                'edu_id' => $this->dataPasien['pasien']['pendidikan']['pendidikanId'] ?? '3',
                'job_id' => $this->dataPasien['pasien']['pekerjaan']['pekerjaanId'] ?? '4',
                'kk' => strtoupper($this->dataPasien['pasien']['hubungan']['namaPenanggungJawab']),
                'nyonya' => strtoupper($this->dataPasien['pasien']['hubungan']['namaIbu'] ?? ''),
                'address' => $this->dataPasien['pasien']['identitas']['alamat'],
                'phone' => $this->dataPasien['pasien']['kontak']['nomerTelponSelulerPasien'],
                'rt' => $this->dataPasien['pasien']['identitas']['rt'],
                'rw' => $this->dataPasien['pasien']['identitas']['rw'],
                'des_id' => $this->dataPasien['pasien']['identitas']['desaId'] ?? null,
                'kec_id' => $this->dataPasien['pasien']['identitas']['kecamatanId'] ?? null,
                'kab_id' => $this->dataPasien['pasien']['identitas']['kotaId'] ?? '3504',
                'prop_id' => $this->dataPasien['pasien']['identitas']['propinsiId'] ?? '35',
                'meta_data_pasien_json' => json_encode($this->dataPasien, true),
            ];

            // Tambah tanggal registrasi jika mode create
            if ($this->isOpenMode === 'insert') {
                $saveData['reg_date'] = DB::raw("to_date('" . ($this->dataPasien['pasien']['regDate'] ?? date('d/m/Y H:i:s')) . "','dd/mm/yyyy hh24:mi:ss')");
            }

            if ($this->isOpenMode === 'insert') {
                DB::table('rsmst_pasiens')->insert($saveData);
                $this->dispatch('toast', type: 'success', message: 'Data pasien berhasil disimpan.');
            } else {
                DB::table('rsmst_pasiens')
                    ->where('reg_no', $this->dataPasien['pasien']['regNo'])
                    ->update($saveData);
                $this->dispatch('toast', type: 'success', message: 'Data pasien berhasil diupdate.');
            }

            $this->closeModal();
            $this->dispatch('master.pasien.saved');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }

    /* -------------------------
     | Delete
     * ------------------------- */
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
};
?>

<div>
    <x-modal name="master-pasien-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="master-pasien-actions-{{ $formMode }}-{{ $dataPasien['pasien']['regNo'] ?? 'new' }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div
                            class="flex items-center justify-center flex-shrink-0 w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                            <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                class="block w-6 h-6 dark:hidden" />
                            <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                class="hidden w-6 h-6 dark:block" />
                        </div>

                        <div>
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                {{ $isOpenMode === 'update' ? 'Ubah Data Pasien' : 'Tambah Data Pasien' }}
                            </h2>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                Lengkapi informasi pasien untuk kebutuhan aplikasi.
                            </p>

                            <div class="mt-3">
                                <x-badge :variant="$isOpenMode === 'update' ? 'warning' : 'success'" class="inline-flex">
                                    {{ $isOpenMode === 'update' ? 'Mode: Edit' : 'Mode: Tambah' }}
                                </x-badge>
                            </div>
                        </div>
                    </div>

                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2" aria-label="Tutup modal">
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
                <form wire:submit.prevent="store" id="form-pasien">
                    <div class="max-w-full mx-auto">
                        <div
                            class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                            <div class="p-5 space-y-5">
                                {{-- Data Diri Pasien --}}
                                <x-border-form :title="__('Data Diri Pasien')" :align="__('start')" :bgcolor="__('bg-gray-50')">
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        {{-- ID Pasien --}}
                                        <div>
                                            <x-input-label for="regNo" :value="__('ID Pasien')" :required="true" />
                                            <x-text-input id="regNo" placeholder="ID Pasien"
                                                class="w-full mt-1 uppercase" :errorshas="$errors->has('dataPasien.pasien.regNo')" :disabled="$disabledProperty || $isOpenMode === 'update'"
                                                wire:model.live="dataPasien.pasien.regNo" />
                                            <x-input-error :messages="$errors->get('dataPasien.pasien.regNo')" class="mt-1" />
                                        </div>

                                        {{-- Nama Pasien --}}
                                        <div class="sm:col-span-2">
                                            <x-input-label for="regName" :value="__('Nama Pasien')" :required="true" />
                                            <x-text-input id="regName" placeholder="Nama Lengkap"
                                                class="w-full mt-1 uppercase" :errorshas="$errors->has('dataPasien.pasien.regName')" :disabled="$disabledProperty"
                                                wire:model.live="dataPasien.pasien.regName" />
                                            <x-input-error :messages="$errors->get('dataPasien.pasien.regName')" class="mt-1" />
                                        </div>

                                        {{-- Tempat Lahir --}}
                                        <div>
                                            <x-input-label for="tempatLahir" :value="__('Tempat Lahir')" :required="true" />
                                            <x-text-input id="tempatLahir" placeholder="Tempat Lahir"
                                                class="w-full mt-1 uppercase" :errorshas="$errors->has('dataPasien.pasien.tempatLahir')" :disabled="$disabledProperty"
                                                wire:model.live="dataPasien.pasien.tempatLahir" />
                                            <x-input-error :messages="$errors->get('dataPasien.pasien.tempatLahir')" class="mt-1" />
                                        </div>

                                        {{-- Tanggal Lahir --}}
                                        <div>
                                            <x-input-label for="tglLahir" :value="__('Tanggal Lahir')" :required="true" />
                                            <x-text-input id="tglLahir" type="text" placeholder="DD/MM/YYYY"
                                                class="w-full mt-1 date-input" :errorshas="$errors->has('dataPasien.pasien.tglLahir')" :disabled="$disabledProperty"
                                                wire:model.live="dataPasien.pasien.tglLahir" x-mask="99/99/9999" />
                                            <x-input-error :messages="$errors->get('dataPasien.pasien.tglLahir')" class="mt-1" />
                                        </div>

                                        {{-- Jenis Kelamin --}}
                                        <div>
                                            <x-input-label for="jenisKelamin" :value="__('Jenis Kelamin')" :required="true" />
                                            <div class="mt-1">
                                                <div class="flex">
                                                    <x-text-input id="jenisKelamin"
                                                        class="flex-1 sm:rounded-none sm:rounded-l-lg"
                                                        :errorshas="$errors->has(
                                                            'dataPasien.pasien.jenisKelamin.jenisKelaminId',
                                                        )" :disabled="true" :value="$dataPasien['pasien']['jenisKelamin'][
                                                            'jenisKelaminId'
                                                        ] .
                                                            '. ' .
                                                            $dataPasien['pasien']['jenisKelamin']['jenisKelaminDesc']"
                                                        aria-describedby="jenisKelamin-desc" />
                                                    <x-primary-button type="button" :disabled="$disabledProperty"
                                                        class="sm:rounded-none sm:rounded-r-lg !py-2 px-3"
                                                        wire:click.prevent="clickJeniskelaminlov()"
                                                        aria-label="Pilih jenis kelamin">
                                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"
                                                            aria-hidden="true">
                                                            <path clip-rule="evenodd" fill-rule="evenodd"
                                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                                        </svg>
                                                    </x-primary-button>
                                                </div>
                                                @error('dataPasien.pasien.jenisKelamin.jenisKelaminId')
                                                    <x-input-error :messages="$message" class="mt-1" />
                                                @enderror
                                            </div>
                                        </div>

                                        {{-- Agama --}}
                                        <div>
                                            <x-input-label for="agama" :value="__('Agama')" :required="true" />
                                            <div class="mt-1">
                                                <div class="flex">
                                                    <x-text-input id="agama"
                                                        class="flex-1 sm:rounded-none sm:rounded-l-lg"
                                                        :errorshas="$errors->has('dataPasien.pasien.agama.agamaId')" :disabled="true" :value="$dataPasien['pasien']['agama']['agamaId'] .
                                                            '. ' .
                                                            $dataPasien['pasien']['agama']['agamaDesc']" />
                                                    <x-primary-button type="button" :disabled="$disabledProperty"
                                                        class="sm:rounded-none sm:rounded-r-lg !py-2 px-3"
                                                        wire:click.prevent="clickagamalov()" aria-label="Pilih agama">
                                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"
                                                            aria-hidden="true">
                                                            <path clip-rule="evenodd" fill-rule="evenodd"
                                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                                        </svg>
                                                    </x-primary-button>
                                                </div>
                                                @error('dataPasien.pasien.agama.agamaId')
                                                    <x-input-error :messages="$message" class="mt-1" />
                                                @enderror
                                            </div>
                                        </div>

                                        {{-- Status Perkawinan --}}
                                        <div>
                                            <x-input-label for="statusPerkawinan" :value="__('Status Perkawinan')"
                                                :required="true" />
                                            <div class="mt-1">
                                                <div class="flex">
                                                    <x-text-input id="statusPerkawinan"
                                                        class="flex-1 sm:rounded-none sm:rounded-l-lg"
                                                        :errorshas="$errors->has(
                                                            'dataPasien.pasien.statusPerkawinan.statusPerkawinanId',
                                                        )" :disabled="true" :value="$dataPasien['pasien']['statusPerkawinan'][
                                                            'statusPerkawinanId'
                                                        ] .
                                                            '. ' .
                                                            $dataPasien['pasien']['statusPerkawinan'][
                                                                'statusPerkawinanDesc'
                                                            ]" />
                                                    <x-primary-button type="button" :disabled="$disabledProperty"
                                                        class="sm:rounded-none sm:rounded-r-lg !py-2 px-3"
                                                        wire:click.prevent="clickstatusPerkawinanlov()"
                                                        aria-label="Pilih status perkawinan">
                                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"
                                                            aria-hidden="true">
                                                            <path clip-rule="evenodd" fill-rule="evenodd"
                                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                                        </svg>
                                                    </x-primary-button>
                                                </div>
                                                @error('dataPasien.pasien.statusPerkawinan.statusPerkawinanId')
                                                    <x-input-error :messages="$message" class="mt-1" />
                                                @enderror
                                            </div>
                                        </div>

                                        {{-- Pendidikan --}}
                                        <div>
                                            <x-input-label for="pendidikan" :value="__('Pendidikan')" :required="true" />
                                            <div class="mt-1">
                                                <div class="flex">
                                                    <x-text-input id="pendidikan"
                                                        class="flex-1 sm:rounded-none sm:rounded-l-lg"
                                                        :errorshas="$errors->has(
                                                            'dataPasien.pasien.pendidikan.pendidikanId',
                                                        )" :disabled="true" :value="$dataPasien['pasien']['pendidikan']['pendidikanId'] .
                                                            '. ' .
                                                            $dataPasien['pasien']['pendidikan']['pendidikanDesc']" />
                                                    <x-primary-button type="button" :disabled="$disabledProperty"
                                                        class="sm:rounded-none sm:rounded-r-lg !py-2 px-3"
                                                        wire:click.prevent="clickpendidikanlov()"
                                                        aria-label="Pilih pendidikan">
                                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"
                                                            aria-hidden="true">
                                                            <path clip-rule="evenodd" fill-rule="evenodd"
                                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                                        </svg>
                                                    </x-primary-button>
                                                </div>
                                                @error('dataPasien.pasien.pendidikan.pendidikanId')
                                                    <x-input-error :messages="$message" class="mt-1" />
                                                @enderror
                                            </div>
                                        </div>

                                        {{-- Pekerjaan --}}
                                        <div>
                                            <x-input-label for="pekerjaan" :value="__('Pekerjaan')" :required="true" />
                                            <div class="mt-1">
                                                <div class="flex">
                                                    <x-text-input id="pekerjaan"
                                                        class="flex-1 sm:rounded-none sm:rounded-l-lg"
                                                        :errorshas="$errors->has(
                                                            'dataPasien.pasien.pekerjaan.pekerjaanId',
                                                        )" :disabled="true" :value="$dataPasien['pasien']['pekerjaan']['pekerjaanId'] .
                                                            '. ' .
                                                            $dataPasien['pasien']['pekerjaan']['pekerjaanDesc']" />
                                                    <x-primary-button type="button" :disabled="$disabledProperty"
                                                        class="sm:rounded-none sm:rounded-r-lg !py-2 px-3"
                                                        wire:click.prevent="clickpekerjaanlov()"
                                                        aria-label="Pilih pekerjaan">
                                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"
                                                            aria-hidden="true">
                                                            <path clip-rule="evenodd" fill-rule="evenodd"
                                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                                        </svg>
                                                    </x-primary-button>
                                                </div>
                                                @error('dataPasien.pasien.pekerjaan.pekerjaanId')
                                                    <x-input-error :messages="$message" class="mt-1" />
                                                @enderror
                                            </div>
                                        </div>

                                        {{-- Golongan Darah --}}
                                        <div>
                                            <x-input-label for="golonganDarah" :value="__('Golongan Darah')" />
                                            <div class="mt-1">
                                                <div class="flex">
                                                    <x-text-input id="golonganDarah"
                                                        class="flex-1 sm:rounded-none sm:rounded-l-lg"
                                                        :errorshas="$errors->has(
                                                            'dataPasien.pasien.golonganDarah.golonganDarahId',
                                                        )" :disabled="true" :value="$dataPasien['pasien']['golonganDarah'][
                                                            'golonganDarahId'
                                                        ] .
                                                            '. ' .
                                                            $dataPasien['pasien']['golonganDarah']['golonganDarahDesc']" />
                                                    <x-primary-button type="button" :disabled="$disabledProperty"
                                                        class="sm:rounded-none sm:rounded-r-lg !py-2 px-3"
                                                        wire:click.prevent="clickgolonganDarahlov()"
                                                        aria-label="Pilih golongan darah">
                                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"
                                                            aria-hidden="true">
                                                            <path clip-rule="evenodd" fill-rule="evenodd"
                                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                                        </svg>
                                                    </x-primary-button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </x-border-form>

                                {{-- Data Identitas --}}
                                <x-border-form :title="__('Data Identitas')" :align="__('start')" :bgcolor="__('bg-gray-50')">
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        {{-- NIK --}}
                                        <div>
                                            <x-input-label for="nik" :value="__('NIK')" :required="true" />
                                            <x-text-input id="nik" placeholder="16 Digit NIK"
                                                class="w-full mt-1" :errorshas="$errors->has('dataPasien.pasien.identitas.nik')" :disabled="$disabledProperty"
                                                wire:model.live="dataPasien.pasien.identitas.nik"
                                                x-mask="9999999999999999" />
                                            <x-input-error :messages="$errors->get('dataPasien.pasien.identitas.nik')" class="mt-1" />
                                        </div>

                                        {{-- No BPJS --}}
                                        <div>
                                            <x-input-label for="idbpjs" :value="__('No BPJS')" />
                                            <x-text-input id="idbpjs" placeholder="13 Digit No BPJS"
                                                class="w-full mt-1" :errorshas="$errors->has('dataPasien.pasien.identitas.idbpjs')" :disabled="$disabledProperty"
                                                wire:model.live="dataPasien.pasien.identitas.idbpjs"
                                                x-mask="9999999999999" />
                                            <x-input-error :messages="$errors->get('dataPasien.pasien.identitas.idbpjs')" class="mt-1" />
                                        </div>

                                        {{-- Alamat --}}
                                        <div class="lg:col-span-2">
                                            <x-input-label for="alamat" :value="__('Alamat')" :required="true" />
                                            <x-text-input id="alamat" placeholder="Alamat Lengkap"
                                                class="w-full mt-1" :errorshas="$errors->has('dataPasien.pasien.identitas.alamat')" :disabled="$disabledProperty"
                                                wire:model.live="dataPasien.pasien.identitas.alamat" />
                                            <x-input-error :messages="$errors->get('dataPasien.pasien.identitas.alamat')" class="mt-1" />
                                        </div>

                                        {{-- RT --}}
                                        <div>
                                            <x-input-label for="rt" :value="__('RT')" :required="true" />
                                            <x-text-input id="rt" placeholder="RT" class="w-full mt-1"
                                                :errorshas="$errors->has('dataPasien.pasien.identitas.rt')" :disabled="$disabledProperty"
                                                wire:model.live="dataPasien.pasien.identitas.rt" x-mask="999" />
                                            <x-input-error :messages="$errors->get('dataPasien.pasien.identitas.rt')" class="mt-1" />
                                        </div>

                                        {{-- RW --}}
                                        <div>
                                            <x-input-label for="rw" :value="__('RW')" :required="true" />
                                            <x-text-input id="rw" placeholder="RW" class="w-full mt-1"
                                                :errorshas="$errors->has('dataPasien.pasien.identitas.rw')" :disabled="$disabledProperty"
                                                wire:model.live="dataPasien.pasien.identitas.rw" x-mask="999" />
                                            <x-input-error :messages="$errors->get('dataPasien.pasien.identitas.rw')" class="mt-1" />
                                        </div>
                                    </div>
                                </x-border-form>

                                {{-- Kontak --}}
                                <x-border-form :title="__('Kontak')" :align="__('start')" :bgcolor="__('bg-gray-50')">
                                    <div class="space-y-4">
                                        <div>
                                            <x-input-label for="nohpPasien" :value="__('No HP Pasien')" :required="true" />
                                            <div class="flex gap-2 mt-1">
                                                <x-text-input placeholder="+62" class="w-20" :disabled="$disabledProperty"
                                                    wire:model.live="dataPasien.pasien.kontak.kodenegara" />
                                                <x-text-input id="nohpPasien" placeholder="81234567890"
                                                    class="flex-1" :errorshas="$errors->has(
                                                        'dataPasien.pasien.kontak.nomerTelponSelulerPasien',
                                                    )" :disabled="$disabledProperty"
                                                    wire:model.live="dataPasien.pasien.kontak.nomerTelponSelulerPasien"
                                                    x-mask="999999999999999" />
                                            </div>
                                            @error('dataPasien.pasien.kontak.nomerTelponSelulerPasien')
                                                <x-input-error :messages="$message" class="mt-1" />
                                            @enderror
                                        </div>

                                        <div>
                                            <x-input-label for="nohpTelponLain" :value="__('No Lain')" />
                                            <div class="flex gap-2 mt-1">
                                                <x-text-input placeholder="+62" class="w-20" :disabled="$disabledProperty"
                                                    wire:model.live="dataPasien.pasien.kontak.kodenegara" />
                                                <x-text-input id="nohpTelponLain" placeholder="81234567890"
                                                    class="flex-1" :errorshas="$errors->has('dataPasien.pasien.kontak.nomerTelponLain')" :disabled="$disabledProperty"
                                                    wire:model.live="dataPasien.pasien.kontak.nomerTelponLain"
                                                    x-mask="999999999999999" />
                                            </div>
                                        </div>
                                    </div>
                                </x-border-form>

                                {{-- Hubungan --}}
                                <x-border-form :title="__('Hubungan')" :align="__('start')" :bgcolor="__('bg-gray-50')">
                                    <div class="space-y-4">
                                        {{-- Penanggung Jawab --}}
                                        <x-border-form :title="__('Penanggung Jawab')" :align="__('start')" :bgcolor="__('bg-yellow-100')">
                                            <div class="space-y-3">
                                                <div>
                                                    <x-input-label for="namaPenanggungJawab" :value="__('Nama Penanggung Jawab')"
                                                        :required="true" />
                                                    <x-text-input id="namaPenanggungJawab"
                                                        placeholder="Nama Penanggung Jawab"
                                                        class="w-full mt-1 uppercase" :errorshas="$errors->has(
                                                            'dataPasien.pasien.hubungan.namaPenanggungJawab',
                                                        )"
                                                        :disabled="$disabledProperty"
                                                        wire:model.live="dataPasien.pasien.hubungan.namaPenanggungJawab" />
                                                    @error('dataPasien.pasien.hubungan.namaPenanggungJawab')
                                                        <x-input-error :messages="$message" class="mt-1" />
                                                    @enderror
                                                </div>

                                                <div>
                                                    <x-input-label for="nomerTelponSelulerPenanggungJawab"
                                                        :value="__('No HP Penanggung Jawab')" />
                                                    <div class="flex gap-2 mt-1">
                                                        <x-text-input placeholder="+62" class="w-20"
                                                            :disabled="$disabledProperty"
                                                            wire:model.live="dataPasien.pasien.hubungan.kodenegaraPenanggungJawab" />
                                                        <x-text-input id="nomerTelponSelulerPenanggungJawab"
                                                            placeholder="81234567890" class="flex-1"
                                                            :errorshas="$errors->has(
                                                                'dataPasien.pasien.hubungan.nomerTelponSelulerPenanggungJawab',
                                                            )" :disabled="$disabledProperty"
                                                            wire:model.live="dataPasien.pasien.hubungan.nomerTelponSelulerPenanggungJawab"
                                                            x-mask="999999999999999" />
                                                    </div>
                                                    @error('dataPasien.pasien.hubungan.nomerTelponSelulerPenanggungJawab')
                                                        <x-input-error :messages="$message" class="mt-1" />
                                                    @enderror
                                                </div>

                                                <div>
                                                    <x-input-label for="hubunganDgnPasien" :value="__('Hubungan dgn Pasien')"
                                                        :required="true" />
                                                    <div class="mt-1">
                                                        <div class="flex">
                                                            <x-text-input id="hubunganDgnPasien"
                                                                class="flex-1 sm:rounded-none sm:rounded-l-lg"
                                                                :errorshas="$errors->has(
                                                                    'dataPasien.pasien.hubungan.hubunganDgnPasien.hubunganDgnPasienId',
                                                                )" :disabled="true"
                                                                :value="$dataPasien['pasien']['hubungan'][
                                                                    'hubunganDgnPasien'
                                                                ]['hubunganDgnPasienId'] .
                                                                    '. ' .
                                                                    $dataPasien['pasien']['hubungan'][
                                                                        'hubunganDgnPasien'
                                                                    ]['hubunganDgnPasienDesc']" />
                                                            <x-primary-button type="button" :disabled="$disabledProperty"
                                                                class="sm:rounded-none sm:rounded-r-lg !py-2 px-3"
                                                                wire:click.prevent="clickhubunganDgnPasienlov()"
                                                                aria-label="Pilih hubungan dengan pasien">
                                                                <svg class="w-5 h-5" fill="currentColor"
                                                                    viewBox="0 0 20 20" aria-hidden="true">
                                                                    <path clip-rule="evenodd" fill-rule="evenodd"
                                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                                                </svg>
                                                            </x-primary-button>
                                                        </div>
                                                        @error('dataPasien.pasien.hubungan.hubunganDgnPasien.hubunganDgnPasienId')
                                                            <x-input-error :messages="$message" class="mt-1" />
                                                        @enderror
                                                    </div>
                                                </div>
                                            </div>
                                        </x-border-form>

                                        {{-- Data Ayah --}}
                                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <div>
                                                <x-input-label for="namaAyah" :value="__('Nama Ayah')" />
                                                <x-text-input id="namaAyah" placeholder="Nama Ayah"
                                                    class="w-full mt-1 uppercase" :errorshas="$errors->has('dataPasien.pasien.hubungan.namaAyah')"
                                                    :disabled="$disabledProperty"
                                                    wire:model.live="dataPasien.pasien.hubungan.namaAyah" />
                                            </div>
                                            <div>
                                                <x-input-label for="nomerTelponSelulerAyah" :value="__('No HP Ayah')" />
                                                <div class="flex gap-2 mt-1">
                                                    <x-text-input placeholder="+62" class="w-20" :disabled="$disabledProperty"
                                                        wire:model.live="dataPasien.pasien.hubungan.kodenegaraAyah" />
                                                    <x-text-input id="nomerTelponSelulerAyah"
                                                        placeholder="81234567890" class="flex-1" :errorshas="$errors->has(
                                                            'dataPasien.pasien.hubungan.nomerTelponSelulerAyah',
                                                        )"
                                                        :disabled="$disabledProperty"
                                                        wire:model.live="dataPasien.pasien.hubungan.nomerTelponSelulerAyah"
                                                        x-mask="999999999999999" />
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Data Ibu --}}
                                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <div>
                                                <x-input-label for="namaIbu" :value="__('Nama Ibu')" />
                                                <x-text-input id="namaIbu" placeholder="Nama Ibu"
                                                    class="w-full mt-1 uppercase" :errorshas="$errors->has('dataPasien.pasien.hubungan.namaIbu')"
                                                    :disabled="$disabledProperty"
                                                    wire:model.live="dataPasien.pasien.hubungan.namaIbu" />
                                            </div>
                                            <div>
                                                <x-input-label for="nomerTelponSelulerIbu" :value="__('No HP Ibu')" />
                                                <div class="flex gap-2 mt-1">
                                                    <x-text-input placeholder="+62" class="w-20" :disabled="$disabledProperty"
                                                        wire:model.live="dataPasien.pasien.hubungan.kodenegaraIbu" />
                                                    <x-text-input id="nomerTelponSelulerIbu" placeholder="81234567890"
                                                        class="flex-1" :errorshas="$errors->has(
                                                            'dataPasien.pasien.hubungan.nomerTelponSelulerIbu',
                                                        )" :disabled="$disabledProperty"
                                                        wire:model.live="dataPasien.pasien.hubungan.nomerTelponSelulerIbu"
                                                        x-mask="999999999999999" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </x-border-form>
                            </div>
                        </div>
                    </div>

                    {{-- FOOTER --}}
                    <div
                        class="sticky bottom-0 px-4 py-3 bg-gray-50/80 backdrop-blur-sm sm:px-6 sm:flex sm:flex-row-reverse sm:gap-3">
                        @if ($isOpenMode !== 'tampil')
                            <x-primary-button :disabled="$disabledProperty" type="submit" form="form-pasien"
                                class="sm:w-auto">
                                Simpan
                            </x-primary-button>
                        @endif
                        <x-secondary-button type="button" wire:click="closeModal">
                            Batal
                        </x-secondary-button>
                    </div>
                </form>
            </div>
        </div>
    </x-modal>
</div>
