<?php
// app/Http/Traits/SATUSEHATLocationTrait.php

namespace App\Http\Traits\SATUSEHAT;



trait LocationTrait
{
    use SatuSehatTrait;

    /**
     * Membuat lokasi baru di SatuSehat
     *
     * @param array $data
     * @return array
     */
    public function createLocation(array $data): array
    {
        $payload = [
            "resourceType" => "Location",
            "status" => "active",
            "name" => $data['name'] ?? 'Ruang Rawat Jalan',
            "description" => $data['description'] ?? '',
            "mode" => "instance",
            "telecom" => [
                [
                    "system" => "phone",
                    "value" => $data['phone'] ?? '',
                    "use" => "work"
                ]
            ],
            "physicalType" => [
                "coding" => [
                    [
                        "system" => "http://terminology.hl7.org/CodeSystem/location-physical-type",
                        "code" => $data['physical_type_code'] ?? 'ro',
                        "display" => $data['physical_type_display'] ?? 'Ruang Rawat Jalan'
                    ]
                ]
            ],
            "position" => [
                "longitude" => $data['longitude'] ?? 0,
                "latitude" => $data['latitude'] ?? 0,
                "altitude" => $data['altitude'] ?? 0
            ],
            "managingOrganization" => [
                "reference" => "Organization/" . ($data['organization_id'] ?? $this->organizationId)
            ]
        ];

        if (!empty($data['identifier'])) {
            $payload['identifier'] = [
                [
                    "system" => "http://sys-ids.kemkes.go.id/location/" . $this->organizationId,
                    "value" => $data['identifier']
                ]
            ];
        }

        if (!empty($data['address'])) {
            $payload['address'] = [
                "use" => "work",
                "line" => $data['address']['line'] ?? [''],
                "city" => $data['address']['city'] ?? '',
                "postalCode" => $data['address']['postal_code'] ?? '',
                "country" => "ID"
            ];
        }

        return $this->makeRequest('post', 'Location', $payload);
    }

    /**
     * Mencari lokasi berdasarkan identifier
     *
     *
     * @param  array|string  $criteria
     *         – jika string, diasumsikan sebagai identifier
     *         – jika array, gunakan sebagai daftar parameter pencarian
     * resource Location, yaitu:
     * @return array
     */
    // address
    // address-city
    // address-country
    // address-postalcode
    // address-state
    // address-use
    // characteristic
    // contains
    // endpoint
    // mode
    // name
    // near
    // operational-status
    // organization
    // partof
    // status
    // type
    public function searchLocation(array|string $criteria): array
    {
        // Jika user hanya kirim string, jadikan criteria identifier
        $params = is_string($criteria)
            ? ['identifier' => $criteria]
            : $criteria;

        // bangun query (RFC3986) lalu decode spasi biar tampil "bersih"
        $query = urldecode(http_build_query($params, '', '&', PHP_QUERY_RFC3986));

        // Panggil makeRequest dengan path lengkap
        return $this->makeRequest('get', 'Location' . ($query ? "?{$query}" : ''));
    }

    /**
     * Update status lokasi
     *
     * @param string $locationId
     * @param string $status (active | inactive | suspended)
     * @return array
     */
    public function updateLocationStatus(string $locationId, string $status): array
    {
        $payload = [
            "resourceType" => "Location",
            "id" => $locationId,
            "status" => $status
        ];

        return $this->makeRequest('put', 'Location/' . $locationId, $payload);
    }
}
