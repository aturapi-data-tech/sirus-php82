<?php
// app/Http/Traits/SATUSEHAT/PractitionerTrait.php

namespace App\Http\Traits\SATUSEHAT;


trait PractitionerTrait
{
    use SatuSehatTrait;


    /**
     * Pencarian Practitioner generic berdasarkan array filter FHIR.
     *
     * Supported search parameters:
     *  - nik          → maps to identifier=https://fhir.kemkes.go.id/id/nik|{value}
     *  - ibp          → maps to identifier=https://fhir.kemkes.go.id/id/ibp|{value}
     *  - sipp         → maps to identifier=https://fhir.kemkes.go.id/id/sipp|{value}
     *  - identifier   → bebas, misal kode internal lain
     *  - name         → nama (family/given/prefix/text)
     *  - gender       → male | female
     *  - birthdate    → YYYY[-MM[-DD]]
     *  - address      → cari di line/city/state/country/postalCode/text
     *  - telecom      → cari di phone/email
     *  - organization → ID organisasi (PractitionerRole → organization)
     *  - role         → code PractitionerRole (contoh: dokter, perawat)
     *  - specialty    → code specialty (FHIR Practitioner.specialty)
     *  - location     → ID lokasi kerja (PractitionerRole → location)
     *
     * @param  array  $searchCriteria  misal ['nik'=>'1234','name'=>'andi','gender'=>'male']
     * @return array
     */
    public function searchPractitioner(array $filters): array
    {
        // Daftar filter yang diizinkan
        $permittedFilters = [
            'identifier',
            'nik',
            'ibp',
            'sipp',
            'name',
            'gender',
            'birthdate',
            'address',
            'telecom',
            'organization',
            'role',
            'specialty',
            'location',
        ];

        // Akan menampung parameter untuk query string
        $queryParams = [];

        foreach ($filters as $filterKey => $filterValue) {
            // Lewati filter yang tidak valid atau nilainya kosong
            if (
                ! in_array($filterKey, $permittedFilters, true)
                || $filterValue === null
                || $filterValue === ''
            ) {
                continue;
            }

            // Mapping khusus untuk identifier berbasis system code
            if (in_array($filterKey, ['nik', 'ibp', 'sipp'], true)) {
                $identifierSystems = [
                    'nik'  => 'https://fhir.kemkes.go.id/id/nik',
                    'ibp'  => 'https://fhir.kemkes.go.id/id/ibp',
                    'sipp' => 'https://fhir.kemkes.go.id/id/sipp',
                ];
                $queryParams['identifier'] = "{$identifierSystems[$filterKey]}|{$filterValue}";
            }
            // Filter lain langsung dipakai sebagai key=value
            else {
                $queryParams[$filterKey] = $filterValue;
            }
        }

        // Bangun query string yang ter-encode sesuai RFC3986
        $queryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        return $this->makeRequest('get', '/Practitioner?' . $queryString);
    }



    /**
     * Mendaftarkan tenaga kesehatan baru
     * @param array $data
     * @return array
     */
    public function createPractitioner(array $data): array
    {
        $payload = [
            "resourceType" => "Practitioner",
            "identifier" => [],
            "active" => true,
            "name" => [
                [
                    "use" => "official",
                    "text" => $data['name'],
                    "family" => $data['family_name'] ?? '',
                    "given" => [$data['given_name'] ?? $data['name']],
                    "prefix" => [$data['prefix'] ?? '']
                ]
            ],
            "telecom" => [
                [
                    "system" => "phone",
                    "value" => $data['phone'] ?? '',
                    "use" => "work"
                ]
            ],
            "gender" => $data['gender'] ?? 'unknown',
            "birthDate" => $data['birth_date'] ?? '',
            "qualification" => [
                [
                    "identifier" => [
                        [
                            "system" => "https://fhir.kemkes.go.id/id/str",
                            "value" => $data['str_number'] ?? ''
                        ]
                    ],
                    "code" => [
                        "coding" => [
                            [
                                "system" => "https://fhir.kemkes.go.id/CodeSystem/practitioner-qualification-type",
                                "code" => $data['qualification_code'] ?? '',
                                "display" => $data['qualification_display'] ?? ''
                            ]
                        ]
                    ],
                    "period" => [
                        "start" => $data['qualification_start'] ?? '',
                        "end" => $data['qualification_end'] ?? ''
                    ],
                    "issuer" => $data['qualification_issuer'] ?? ''
                ]
            ]
        ];

        // Tambahkan identifier NIK jika ada
        if (!empty($data['nik'])) {
            $payload['identifier'][] = [
                "system" => "https://fhir.kemkes.go.id/id/nik",
                "value" => $data['nik']
            ];
        }

        // Tambahkan identifier IBP jika ada
        if (!empty($data['ibp'])) {
            $payload['identifier'][] = [
                "system" => "https://fhir.kemkes.go.id/id/ibp",
                "value" => $data['ibp']
            ];
        }

        // Tambahkan identifier SIPP jika ada
        if (!empty($data['sipp'])) {
            $payload['identifier'][] = [
                "system" => "https://fhir.kemkes.go.id/id/sipp",
                "value" => $data['sipp']
            ];
        }

        return $this->makeRequest('post', '/Practitioner', $payload);
    }

    /**
     * Update data tenaga kesehatan
     * @param string $practitionerId
     * @param array $data
     * @return array
     */
    public function updatePractitioner(string $practitionerId, array $data): array
    {
        $payload = [
            "resourceType" => "Practitioner",
            "id" => $practitionerId,
            "name" => [
                [
                    "use" => "official",
                    "text" => $data['name'],
                    "family" => $data['family_name'] ?? '',
                    "given" => [$data['given_name'] ?? $data['name']],
                    "prefix" => [$data['prefix'] ?? '']
                ]
            ],
            "active" => $data['active'] ?? true
        ];

        return $this->makeRequest('put', '/Practitioner/' . $practitionerId, $payload);
    }
}
