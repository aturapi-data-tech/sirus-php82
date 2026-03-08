<?php

declare(strict_types=1);

namespace App\Http\Traits\Master\MasterPasien;

use Illuminate\Support\Facades\DB;
use Throwable;

trait MasterPasienTrait
{
    /**
     * Ambil master pasien.
     * - Jika meta_data_pasien_json ada & valid: pakai itu.
     * - Jika null / invalid: fallback rakit dari kolom-kolom master.
     */
    protected function findDataMasterPasien(string $regNo): array
    {
        try {
            $row = DB::table('rsmst_pasiens')
                ->select('meta_data_pasien_json')
                ->where('reg_no', $regNo)
                ->first();

            if (!$row) {
                return ["errorMessages" => "Pasien tidak ditemukan untuk reg_no: {$regNo}"];
            }

            $json = $row->meta_data_pasien_json ?? null;

            // 1) Jika ada JSON dan valid -> gunakan
            if (is_string($json) && trim($json) !== '') {
                try {
                    $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        return $decoded;
                    }
                } catch (Throwable $e) {
                    // JSON rusak -> fallback ke kolom master
                }
            }

            // 2) Fallback: build struktur default + isi dari kolom master
            $dataPasien = $this->defaultPasienPayload();

            $findData = DB::table('rsmst_pasiens')
                ->select(
                    DB::raw("to_char(reg_date,'dd/mm/yyyy hh24:mi:ss') as reg_date"),
                    DB::raw("to_char(reg_date,'yyyymmddhh24miss') as reg_date1"),
                    'reg_no',
                    'reg_name',
                    DB::raw("nvl(nokartu_bpjs,'-') as nokartu_bpjs"),
                    DB::raw("nvl(nik_bpjs,'-') as nik_bpjs"),
                    'sex',
                    DB::raw("to_char(birth_date,'dd/mm/yyyy') as birth_date"),
                    DB::raw("(select trunc( months_between( sysdate, birth_date ) /12 ) from dual) as thn"),
                    'bln',
                    'hari',
                    'birth_place',
                    'blood',
                    'marital_status',
                    'rsmst_religions.rel_id as rel_id',
                    'rel_desc',
                    'rsmst_educations.edu_id as edu_id',
                    'edu_desc',
                    'rsmst_jobs.job_id as job_id',
                    'job_name',
                    'kk',
                    'nyonya',
                    'no_kk',
                    'address',
                    'rsmst_desas.des_id as des_id',
                    'des_name',
                    'rt',
                    'rw',
                    'rsmst_kecamatans.kec_id as kec_id',
                    'kec_name',
                    'rsmst_kabupatens.kab_id as kab_id',
                    'kab_name',
                    'rsmst_propinsis.prop_id as prop_id',
                    'prop_name',
                    'phone'
                )
                ->join('rsmst_religions', 'rsmst_religions.rel_id', '=', 'rsmst_pasiens.rel_id')
                ->join('rsmst_educations', 'rsmst_educations.edu_id', '=', 'rsmst_pasiens.edu_id')
                ->join('rsmst_jobs', 'rsmst_jobs.job_id', '=', 'rsmst_pasiens.job_id')
                ->join('rsmst_desas', 'rsmst_desas.des_id', '=', 'rsmst_pasiens.des_id')
                ->join('rsmst_kecamatans', 'rsmst_kecamatans.kec_id', '=', 'rsmst_pasiens.kec_id')
                ->join('rsmst_kabupatens', 'rsmst_kabupatens.kab_id', '=', 'rsmst_pasiens.kab_id')
                ->join('rsmst_propinsis', 'rsmst_propinsis.prop_id', '=', 'rsmst_pasiens.prop_id')
                ->where('reg_no', $regNo)
                ->first();

            if (!$findData) {
                return ["errorMessages" => "Data detail pasien tidak ditemukan untuk reg_no: {$regNo}"];
            }

            // Isi data
            $dataPasien['pasien']['regDate'] = $findData->reg_date ?? '';
            $dataPasien['pasien']['regNo']   = $findData->reg_no ?? '';
            $dataPasien['pasien']['regName'] = $findData->reg_name ?? '';

            // Identitas
            $dataPasien['pasien']['identitas']['idbpjs'] = $findData->nokartu_bpjs ?? '-';
            $dataPasien['pasien']['identitas']['nik']    = $findData->nik_bpjs ?? '-';
            $dataPasien['pasien']['identitas']['alamat'] = $findData->address ?? '';

            $dataPasien['pasien']['identitas']['desaId'] = $findData->des_id ?? '';
            $dataPasien['pasien']['identitas']['desaName'] = $findData->des_name ?? '';
            $dataPasien['pasien']['identitas']['rt'] = $findData->rt ?? '';
            $dataPasien['pasien']['identitas']['rw'] = $findData->rw ?? '';

            $dataPasien['pasien']['identitas']['kecamatanId'] = $findData->kec_id ?? '';
            $dataPasien['pasien']['identitas']['kecamatanName'] = $findData->kec_name ?? '';

            $dataPasien['pasien']['identitas']['kotaId'] = $findData->kab_id ?? '';
            $dataPasien['pasien']['identitas']['kotaName'] = $findData->kab_name ?? '';

            $dataPasien['pasien']['identitas']['propinsiId'] = $findData->prop_id ?? '';
            $dataPasien['pasien']['identitas']['propinsiName'] = $findData->prop_name ?? '';

            // Jenis kelamin
            $isMale = (($findData->sex ?? '') === 'L');
            $dataPasien['pasien']['jenisKelamin']['jenisKelaminId']   = $isMale ? 1 : 2;
            $dataPasien['pasien']['jenisKelamin']['jenisKelaminDesc'] = $isMale ? 'Laki-laki' : 'Perempuan';

            // Lahir
            $dataPasien['pasien']['tglLahir'] = $findData->birth_date ?? '';
            $dataPasien['pasien']['thn'] = $findData->thn ?? '';
            $dataPasien['pasien']['bln'] = $findData->bln ?? '';
            $dataPasien['pasien']['hari'] = $findData->hari ?? '';
            $dataPasien['pasien']['tempatLahir'] = $findData->birth_place ?? '';

            // Agama/Pendidikan/Pekerjaan
            $dataPasien['pasien']['agama']['agamaId']   = (string)($findData->rel_id ?? '');
            $dataPasien['pasien']['agama']['agamaDesc'] = $findData->rel_desc ?? '';

            $dataPasien['pasien']['pendidikan']['pendidikanId']   = (string)($findData->edu_id ?? '');
            $dataPasien['pasien']['pendidikan']['pendidikanDesc'] = $findData->edu_desc ?? '';

            $dataPasien['pasien']['pekerjaan']['pekerjaanId']   = (string)($findData->job_id ?? '');
            $dataPasien['pasien']['pekerjaan']['pekerjaanDesc'] = $findData->job_name ?? '';

            // Kontak
            $dataPasien['pasien']['kontak']['nomerTelponSelulerPasien'] = $findData->phone ?? '';

            // Hubungan
            $dataPasien['pasien']['hubungan']['namaPenanggungJawab'] = $findData->kk ?? '';
            $dataPasien['pasien']['hubungan']['namaIbu'] = $findData->nyonya ?? '';

            // TODO (opsional): mapping blood/marital_status bila kamu punya tabel mappingnya

            return $dataPasien;
        } catch (Throwable $e) {
            return ["errorMessages" => $e->getMessage()];
        }
    }

    /**
     * Update JSON master pasien secara atomic (anti race condition).
     * Lock row dulu -> update.
     */
    public static function updateJsonMasterPasien(string $regNo, array $payload): void
    {
        DB::transaction(function () use ($regNo, $payload) {

            // Lock row: mencegah 2 request update di waktu bersamaan untuk reg_no sama
            $row = DB::table('rsmst_pasiens')
                ->select('reg_no') // ringan aja
                ->where('reg_no', $regNo)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                throw new \RuntimeException("Pasien tidak ditemukan untuk reg_no: {$regNo}");
            }

            $json = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );

            DB::table('rsmst_pasiens')
                ->where('reg_no', $regNo)
                ->update([
                    'meta_data_pasien_json' => $json,
                ]);
        }, 3); // retry 3x untuk deadlock sementara (kalau DB support)
    }

    /**
     * Optional: contoh update JSON dengan "read-modify-write" yang tetap aman.
     * (Misal kamu mau merge sebagian field, bukan replace semuanya)
     */
    public static function patchJsonMasterPasien(string $regNo, array $patch): array
    {
        return DB::transaction(function () use ($regNo, $patch) {

            $row = DB::table('rsmst_pasiens')
                ->select('meta_data_pasien_json')
                ->where('reg_no', $regNo)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                throw new \RuntimeException("Pasien tidak ditemukan untuk reg_no: {$regNo}");
            }

            $current = [];
            $json = $row->meta_data_pasien_json ?? null;
            if (is_string($json) && trim($json) !== '') {
                try {
                    $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) $current = $decoded;
                } catch (Throwable $e) {
                    $current = [];
                }
            }

            // Merge rekursif sederhana (patch override)
            $merged = self::arrayMergeRecursiveDistinct($current, $patch);

            $encoded = json_encode(
                $merged,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );

            DB::table('rsmst_pasiens')
                ->where('reg_no', $regNo)
                ->update(['meta_data_pasien_json' => $encoded]);

            return $merged;
        }, 3);
    }

    private function defaultPasienPayload(): array
    {
        return [
            "pasien" => [
                "pasientidakdikenal" => false,
                "regNo" => "",
                "gelarDepan" => "",
                "regName" => "",
                "gelarBelakang" => "",
                "namaPanggilan" => "",
                "tempatLahir" => "",
                "tglLahir" => "",
                "thn" => "",
                "bln" => "",
                "hari" => "",
                "jenisKelamin" => [
                    "jenisKelaminId" => 1,
                    "jenisKelaminDesc" => "Laki-laki",
                    "jenisKelaminOptions" => [
                        ["jenisKelaminId" => 0, "jenisKelaminDesc" => "Tidak diketaui"],
                        ["jenisKelaminId" => 1, "jenisKelaminDesc" => "Laki-laki"],
                        ["jenisKelaminId" => 2, "jenisKelaminDesc" => "Perempuan"],
                        ["jenisKelaminId" => 3, "jenisKelaminDesc" => "Tidak dapat di tentukan"],
                        ["jenisKelaminId" => 4, "jenisKelaminDesc" => "Tidak Mengisi"],
                    ],
                ],
                "agama" => [
                    "agamaId" => "1",
                    "agamaDesc" => "Islam",
                    "agamaOptions" => [
                        ["agamaId" => 1, "agamaDesc" => "Islam"],
                        ["agamaId" => 2, "agamaDesc" => "Kristen (Protestan)"],
                        ["agamaId" => 3, "agamaDesc" => "Katolik"],
                        ["agamaId" => 4, "agamaDesc" => "Hindu"],
                        ["agamaId" => 5, "agamaDesc" => "Budha"],
                        ["agamaId" => 6, "agamaDesc" => "Konghucu"],
                        ["agamaId" => 7, "agamaDesc" => "Penghayat"],
                        ["agamaId" => 8, "agamaDesc" => "Lain-lain"],
                    ],
                ],
                "statusPerkawinan" => [
                    "statusPerkawinanId" => "1",
                    "statusPerkawinanDesc" => "Belum Kawin",
                    "statusPerkawinanOptions" => [
                        ["statusPerkawinanId" => 1, "statusPerkawinanDesc" => "Belum Kawin"],
                        ["statusPerkawinanId" => 2, "statusPerkawinanDesc" => "Kawin"],
                        ["statusPerkawinanId" => 3, "statusPerkawinanDesc" => "Cerai Hidup"],
                        ["statusPerkawinanId" => 4, "statusPerkawinanDesc" => "Cerai Mati"],
                    ],
                ],
                "pendidikan" => [
                    "pendidikanId" => "3",
                    "pendidikanDesc" => "SLTA Sederajat",
                    "pendidikanOptions" => [
                        ["pendidikanId" => 0, "pendidikanDesc" => "Tidak Sekolah"],
                        ["pendidikanId" => 1, "pendidikanDesc" => "SD"],
                        ["pendidikanId" => 2, "pendidikanDesc" => "SLTP Sederajat"],
                        ["pendidikanId" => 3, "pendidikanDesc" => "SLTA Sederajat"],
                        ["pendidikanId" => 4, "pendidikanDesc" => "D1-D3"],
                        ["pendidikanId" => 5, "pendidikanDesc" => "D4"],
                        ["pendidikanId" => 6, "pendidikanDesc" => "S1"],
                        ["pendidikanId" => 7, "pendidikanDesc" => "S2"],
                        ["pendidikanId" => 8, "pendidikanDesc" => "S3"],
                    ],
                ],
                "pekerjaan" => [
                    "pekerjaanId" => "4",
                    "pekerjaanDesc" => "Pegawai Swasta/ Wiraswasta",
                    "pekerjaanOptions" => [
                        ["pekerjaanId" => 0, "pekerjaanDesc" => "Tidak Bekerja"],
                        ["pekerjaanId" => 1, "pekerjaanDesc" => "PNS"],
                        ["pekerjaanId" => 2, "pekerjaanDesc" => "TNI/POLRI"],
                        ["pekerjaanId" => 3, "pekerjaanDesc" => "BUMN"],
                        ["pekerjaanId" => 4, "pekerjaanDesc" => "Pegawai Swasta/ Wiraswasta"],
                        ["pekerjaanId" => 5, "pekerjaanDesc" => "Lain-Lain"],
                    ],
                ],
                "golonganDarah" => [
                    "golonganDarahId" => "13",
                    "golonganDarahDesc" => "Tidak Tahu",
                    "golonganDarahOptions" => [
                        ["golonganDarahId" => 1, "golonganDarahDesc" => "A"],
                        ["golonganDarahId" => 2, "golonganDarahDesc" => "B"],
                        ["golonganDarahId" => 3, "golonganDarahDesc" => "AB"],
                        ["golonganDarahId" => 4, "golonganDarahDesc" => "O"],
                        ["golonganDarahId" => 5, "golonganDarahDesc" => "A+"],
                        ["golonganDarahId" => 6, "golonganDarahDesc" => "A-"],
                        ["golonganDarahId" => 7, "golonganDarahDesc" => "B+"],
                        ["golonganDarahId" => 8, "golonganDarahDesc" => "B-"],
                        ["golonganDarahId" => 9, "golonganDarahDesc" => "AB+"],
                        ["golonganDarahId" => 10, "golonganDarahDesc" => "AB-"],
                        ["golonganDarahId" => 11, "golonganDarahDesc" => "O+"],
                        ["golonganDarahId" => 12, "golonganDarahDesc" => "O-"],
                        ["golonganDarahId" => 13, "golonganDarahDesc" => "Tidak Tahu"],
                        ["golonganDarahId" => 14, "golonganDarahDesc" => "O Rhesus"],
                        ["golonganDarahId" => 15, "golonganDarahDesc" => "#"],
                    ],
                ],
                "kewarganegaraan" => "INDONESIA",
                "suku" => "Jawa",
                "bahasa" => "Indonesia / Jawa",
                "status" => [
                    "statusId" => "1",
                    "statusDesc" => "Aktif / Hidup",
                    "statusOptions" => [
                        ["statusId" => 0, "statusDesc" => "Tidak Aktif / Batal"],
                        ["statusId" => 1, "statusDesc" => "Aktif / Hidup"],
                        ["statusId" => 2, "statusDesc" => "Meninggal"],
                    ],
                ],
                "domisil" => [
                    "samadgnidentitas" => false,
                    "alamat" => "",
                    "rt" => "",
                    "rw" => "",
                    "kodepos" => "",
                    "desaId" => "",
                    "kecamatanId" => "",
                    "kotaId" => "3504",
                    "propinsiId" => "35",
                    "desaName" => "",
                    "kecamatanName" => "",
                    "kotaName" => "TULUNGAGUNG",
                    "propinsiName" => "JAWA TIMUR",
                ],
                "identitas" => [
                    "nik" => "",
                    "idbpjs" => "",
                    "patientUuid" => "",
                    "pasport" => "",
                    "alamat" => "",
                    "rt" => "",
                    "rw" => "",
                    "kodepos" => "",
                    "desaId" => "",
                    "kecamatanId" => "",
                    "kotaId" => "3504",
                    "propinsiId" => "35",
                    "desaName" => "",
                    "kecamatanName" => "",
                    "kotaName" => "TULUNGAGUNG",
                    "propinsiName" => "JAWA TIMUR",
                    "negara" => "ID",
                ],
                "kontak" => [
                    "kodenegara" => "62",
                    "nomerTelponSelulerPasien" => "",
                    "nomerTelponLain" => "",
                ],
                "hubungan" => [
                    "namaAyah" => "",
                    "kodenegaraAyah" => "62",
                    "nomerTelponSelulerAyah" => "",
                    "namaIbu" => "",
                    "kodenegaraIbu" => "62",
                    "nomerTelponSelulerIbu" => "",
                    "namaPenanggungJawab" => "",
                    "kodenegaraPenanggungJawab" => "62",
                    "nomerTelponSelulerPenanggungJawab" => "",
                    "hubunganDgnPasien" => [
                        "hubunganDgnPasienId" => 5,
                        "hubunganDgnPasienDesc" => "Kerabat / Saudara",
                        "hubunganDgnPasienOptions" => [
                            ["hubunganDgnPasienId" => 1, "hubunganDgnPasienDesc" => "Diri Sendiri"],
                            ["hubunganDgnPasienId" => 2, "hubunganDgnPasienDesc" => "Orang Tua"],
                            ["hubunganDgnPasienId" => 3, "hubunganDgnPasienDesc" => "Anak"],
                            ["hubunganDgnPasienId" => 4, "hubunganDgnPasienDesc" => "Suami / Istri"],
                            ["hubunganDgnPasienId" => 5, "hubunganDgnPasienDesc" => "Kerabaat / Saudara"],
                            ["hubunganDgnPasienId" => 6, "hubunganDgnPasienDesc" => "Lain-lain"],
                        ],
                    ],
                ],
            ],
        ];
    }

    private static function arrayMergeRecursiveDistinct(array $base, array $overwrite): array
    {
        foreach ($overwrite as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::arrayMergeRecursiveDistinct($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }
}
