<?php
// app/Http/Traits/SATUSEHAT/PatientTrait.php

namespace App\Http\Traits\SATUSEHAT;

trait PatientTrait
{
    use SatuSehatTrait;

    /**
     * Mendaftarkan pasien baru
     */
    public function createPatient(array $data): array
    {
        $payload = $this->buildPatientPayload($data);
        return $this->makeRequest('post', '/Patient', $payload);
    }

    /**
     * Update data pasien
     */
    public function updatePatient(string $patientId, array $data): array
    {
        $payload = $this->buildPatientPayload($data, $patientId);
        return $this->makeRequest('put', "/Patient/{$patientId}", $payload);
    }

    /**
     * Bangun payload Patient untuk Create atau Update
     *
     * @param  array        $data
     * @param  string|null  $id    null untuk create, string untuk update
     * @return array
     */
    private function buildPatientPayload(array $data, ?string $id = null): array
    {
        $payload = [
            'resourceType' => 'Patient',
            // hanya sertakan id saat update
            'id'           => $id,
            'identifier'   => [],
            'name'         => [[
                'use'    => 'official',
                'text'   => $data['name'] ?? '',
                'family' => $data['family_name'] ?? '',
                'given'  => [$data['given_name'] ?? $data['name'] ?? ''],
            ]],
            'telecom'      => [[
                'system' => 'phone',
                'value'  => $data['phone'] ?? '',
                'use'    => 'mobile',
            ]],
            'gender'       => $data['gender']     ?? 'unknown',
            'birthDate'    => $data['birth_date'] ?? null,
            'address'      => $data['address']    ?? [],
            'maritalStatus' => [
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/v3-MaritalStatus',
                    'code'   => $data['marital_status'] ?? 'U',
                    'display' => $this->getMaritalStatusDisplay($data['marital_status'] ?? 'U'),
                ]]
            ],
        ];

        // Tambah identifier NIK & BPJS jika ada
        if (!empty($data['nik'])) {
            $payload['identifier'][] = [
                'system' => 'https://fhir.kemkes.go.id/id/nik',
                'value'  => $data['nik'],
            ];
        }
        if (!empty($data['bpjs_number'])) {
            $payload['identifier'][] = [
                'system' => 'https://fhir.kemkes.go.id/id/bpjs',
                'value'  => $data['bpjs_number'],
            ];
        }

        return $payload;
    }

    private function getMaritalStatusDisplay(string $code): string
    {
        $map = [
            'A' => 'Annulled',
            'D' => 'Divorced',
            'I' => 'Interlocutory',
            'L' => 'Legally Separated',
            'M' => 'Married',
            'P' => 'Polygamous',
            'S' => 'Never Married',
            'T' => 'Domestic Partner',
            'U' => 'Unmarried',
            'W' => 'Widowed',
        ];
        return $map[$code] ?? 'Unknown';
    }

    public function searchPatient(array $searchCriteria): array
    {
        // Daftar filter yang diperbolehkan
        $allowedFilters   = ['identifier', 'name', 'birthdate', 'gender', 'nik', 'bpjs', 'mother_nik'];
        $queryParameters  = [];

        foreach ($searchCriteria as $filterKey => $filterValue) {
            // Lewati jika bukan filter yang valid atau nilainya kosong/null
            if (
                ! in_array($filterKey, $allowedFilters, true)
                || $filterValue === null
                || $filterValue === ''
            ) {
                continue;
            }

            // Mapping khusus untuk NIK pasien / BPJS
            if ($filterKey === 'nik' || $filterKey === 'bpjs') {
                $identifierSystem = $filterKey === 'nik'
                    ? 'https://fhir.kemkes.go.id/id/nik'
                    : 'https://fhir.kemkes.go.id/id/bpjs';
                $queryParameters['identifier'] = "{$identifierSystem}|{$filterValue}";
            }
            // Mapping untuk NIK ibu (bayi baru lahir)
            elseif ($filterKey === 'mother_nik') {
                $identifierSystem = 'https://fhir.kemkes.go.id/id/nik-ibu';
                $queryParameters['identifier'] = "{$identifierSystem}|{$filterValue}";
            }
            // Filter lain langsung jadi query parameter
            else {
                $queryParameters[$filterKey] = $filterValue;
            }
        }

        // Bangun query string
        $queryString = http_build_query(
            $queryParameters,
            '',
            '&',
            PHP_QUERY_RFC3986
        );
        return $this->makeRequest('get', '/Patient?' . $queryString);
    }
}
