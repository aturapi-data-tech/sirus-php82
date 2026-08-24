<?php

namespace App\Http\Traits\SATUSEHAT;

/**
 * ImagingStudy — metadata studi pencitraan (BUKAN berkas gambarnya).
 *
 * ⚠️ BACA DULU SEBELUM MEMAKAI DI PRODUKSI.
 *
 * FHIR mewajibkan identitas DICOM: Study Instance UID, Series Instance UID, dan
 * SOP Instance UID. UID itu diterbitkan MODALITAS/PACS saat gambar dibuat.
 * Modul radiologi kita berbasis unggah PDF — gambarnya tidak pernah melewati
 * PACS, dan penyisiran seluruh basis data memastikan TIDAK ADA kolom UID DICOM
 * (yang ada hanya UUID/DR_UUID/PATIENT_UUID/POLI_UUID milik SATUSEHAT).
 *
 * Karena itu `uidStudi()` MENURUNKAN UID dari nomor order kita sendiri di bawah
 * arc OID sementara. Konsekuensinya jujur: UID yang terkirim TIDAK menunjuk
 * objek DICOM mana pun. Bentuknya sah, isinya tidak bisa ditelusuri ke PACS.
 *
 * Aman dipakai di STAGING untuk menguji apakah validator menerima bentuk minimal
 * kita. Untuk PRODUKSI, tunggu PACS/modality worklist yang menerbitkan UID nyata
 * — atau OID resmi milik RS yang didaftarkan, supaya UID-nya bermakna.
 *
 * Arc OID: 2.25.<integer> adalah arc UUID-berbasis yang SAH tanpa perlu
 * pendaftaran (ITU-T X.667) — dipilih supaya kita tidak menyerobot arc milik
 * organisasi lain.
 */
trait ImagingStudyTrait
{
    use SatuSehatTrait;

    /**
     * Turunkan Study/Series/SOP Instance UID yang stabil dari kunci order kita.
     * Stabil = kunci sama menghasilkan UID sama, jadi kiriman ulang tidak
     * melahirkan studi kembar.
     */
    public function uidStudi(string $kunci): string
    {
        // sha1 dipakai sebagai pengganti UUID v5 (Str::uuid5 tak ada di Laravel 12,
        // ekstensi gmp juga tidak terpasang) — yang dibutuhkan cuma turunan
        // deterministik, bukan UUID yang sah menurut RFC.
        $heks = substr(sha1('sirus|imagingstudy|' . $kunci), 0, 32);

        // heksa → desimal dengan bcmath; UID DICOM hanya boleh angka & titik,
        // dan komponennya tak boleh berawalan nol.
        $desimal = '0';
        foreach (str_split($heks) as $digit) {
            $desimal = bcadd(bcmul($desimal, '16'), (string) hexdec($digit));
        }

        // Batas UID DICOM 64 karakter; "2.25." memakan 5.
        return '2.25.' . substr(ltrim($desimal, '0') ?: '0', 0, 59);
    }

    /**
     * @param array $data
     *  - kunci            => penanda order kita (mis. "rad-673349-11408"), dipakai menurunkan UID
     *  - patientId        => IHS pasien (tanpa prefix)
     *  - encounterId      => uuid Encounter
     *  - started          => ISO 8601
     *  - modalityCode     => kode DICOM (DX/CR/CT/US/MR/XA/MG), lihat modalitasDariDeskripsi()
     *  - modalityDisplay  => nama modalitas
     *  - procedureCode    => LOINC pemeriksaan
     *  - procedureDisplay => nama pemeriksaan
     *  - referrerId       => uuid Practitioner pengirim (opsional)
     *  - basedOn          => id ServiceRequest (opsional)
     *  - description      => keterangan bebas
     */
    public function postImagingStudy(array $data): array
    {
        $studyUid  = !empty($data['studyUid']) ? $data['studyUid'] : $this->uidStudi($data['kunci']);
        $seriesUid = $this->uidStudi($data['kunci'] . '-series-1');
        $sopUid    = $this->uidStudi($data['kunci'] . '-instance-1');

        $modalityCode = $data['modalityCode'] ?? 'OT';
        $modality = [
            'system' => 'http://dicom.nema.org/resources/ontology/DCM',
            'code' => $modalityCode,
            'display' => $data['modalityDisplay'] ?? $modalityCode,
        ];

        $payload = [
            'resourceType' => 'ImagingStudy',
            'identifier' => [[
                'system' => 'urn:dicom:uid',
                'value' => 'urn:oid:' . $studyUid,
            ]],
            'status' => 'available',
            'modality' => [$modality],
            'subject' => ['reference' => 'Patient/' . $data['patientId']],
            'encounter' => ['reference' => 'Encounter/' . $data['encounterId']],
            'started' => $data['started'],
            'numberOfSeries' => 1,
            'numberOfInstances' => 1,
            'series' => [[
                'uid' => $seriesUid,
                'number' => 1,
                'modality' => $modality,
                'numberOfInstances' => 1,
                'bodySite' => null,
                'instance' => [[
                    'uid' => $sopUid,
                    'sopClass' => [
                        'system' => 'urn:ietf:rfc:3986',
                        // Secondary Capture: gambar yang TIDAK berasal dari modalitas
                        // DICOM asli — paling jujur untuk hasil berupa unggahan.
                        'code' => 'urn:oid:1.2.840.10008.5.1.4.1.1.7',
                        'display' => 'Secondary Capture Image Storage',
                    ],
                    'number' => 1,
                ]],
            ]],
        ];

        // Field opsional hanya dikirim bila terisi — SATUSEHAT menolak objek kosong
        // (lihat feedback_satusehat_field_objek_kosong).
        if (filled($data['procedureCode'] ?? '')) {
            $payload['procedureCode'] = [[
                'coding' => [[
                    'system' => 'http://loinc.org',
                    'code' => $data['procedureCode'],
                    'display' => $data['procedureDisplay'] ?? '',
                ]],
            ]];
        }
        if (filled($data['referrerId'] ?? '')) {
            $payload['referrer'] = ['reference' => 'Practitioner/' . $data['referrerId']];
        }
        if (filled($data['basedOn'] ?? '')) {
            $payload['basedOn'] = [['reference' => 'ServiceRequest/' . $data['basedOn']]];
        }
        if (filled($data['description'] ?? '')) {
            $payload['description'] = $data['description'];
        }

        // bodySite null di atas cuma penanda bentuk; buang sebelum kirim.
        unset($payload['series'][0]['bodySite']);

        return $this->makeRequest('post', '/ImagingStudy', $payload);
    }

    /**
     * Tebak modalitas DICOM dari nama pemeriksaan / display LOINC.
     * Sengaja konservatif: yang tidak dikenali jadi 'OT' (Other) — menebak
     * modalitas yang salah lebih buruk daripada mengaku tidak tahu.
     */
    public function modalitasDariDeskripsi(string $teks): array
    {
        $t = strtoupper($teks);
        $peta = [
            'CT' => ['CT SCAN', 'CT-SCAN', ' CT '],
            'MR' => ['MRI', ' MR '],
            'US' => ['USG', 'ULTRASOUND', 'ULTRASONO'],
            'MG' => ['MAMMO'],
            'XA' => ['ANGIO'],
            'DX' => ['XR ', 'X-RAY', 'RONTGEN', 'THORAX', 'FOTO'],
        ];
        foreach ($peta as $kode => $kunciList) {
            foreach ($kunciList as $kunci) {
                if (str_contains($t, $kunci)) {
                    return ['code' => $kode, 'display' => $this->namaModalitas($kode)];
                }
            }
        }

        return ['code' => 'OT', 'display' => 'Other'];
    }

    private function namaModalitas(string $kode): string
    {
        return [
            'DX' => 'Digital Radiography',
            'CT' => 'Computed Tomography',
            'MR' => 'Magnetic Resonance',
            'US' => 'Ultrasound',
            'MG' => 'Mammography',
            'XA' => 'X-Ray Angiography',
            'OT' => 'Other',
        ][$kode] ?? 'Other';
    }
}
