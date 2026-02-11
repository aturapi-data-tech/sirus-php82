<?php
// app/Http/Traits/SATUSEHAT/OrganizationTrait.php

namespace App\Http\Traits\SATUSEHAT;



trait OrganizationTrait
{
    use SatuSehatTrait;

    /**
     * Membuat organisasi baru di SatuSehat
     *
     * @param array $data
     * @return array
     */
    public function createOrganization(array $data): array
    {
        $payload = [
            "resourceType" => "Organization",
            "active" => true,
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/organization/" . $this->organizationId,
                    "value" => $data['organization_code'] ?? $this->organizationId
                ]
            ],
            "type" => [
                [
                    "coding" => [
                        [
                            "system" => "http://terminology.hl7.org/CodeSystem/organization-type",
                            "code" => $data['type_code'] ?? 'prov',
                            "display" => $data['type_display'] ?? 'Healthcare Provider'
                        ]
                    ]
                ]
            ],
            "name" => $data['name'] ?? $this->organizationName,
            "alias" => $data['alias'] ?? [$this->organizationName],
            "telecom" => [
                [
                    "system" => "phone",
                    "value" => $data['phone'] ?? '',
                    "use" => "work"
                ],
                [
                    "system" => "email",
                    "value" => $data['email'] ?? '',
                    "use" => "work"
                ]
            ],
            "address" => [
                [
                    "use" => "work",
                    "line" => $data['address']['line'] ?? [''],
                    "city" => $data['address']['city'] ?? '',
                    "postalCode" => $data['address']['postal_code'] ?? '',
                    "country" => "ID"
                ]
            ],
            "partOf" => [
                "reference" => "Organization/" . ($data['parent_organization_id'] ?? '')
            ]
        ];

        return $this->makeRequest('post', '/Organization', $payload);
    }

    /**
     * Mencari organisasi berdasarkan identifier
     *
     * @param string $identifier
     * @return array
     */
    public function searchOrganization(string $identifier): array
    {
        return $this->makeRequest('get', '/Organization?identifier=' . urlencode($identifier));
    }

    /**
     * Update data organisasi
     *
     * @param string $organizationId
     * @param array $data
     * @return array
     */
    public function updateOrganization(string $organizationId, array $data): array
    {
        $payload = [
            "resourceType" => "Organization",
            "id" => $organizationId,
            "name" => $data['name'] ?? $this->organizationName,
            "active" => $data['active'] ?? true
        ];

        return $this->makeRequest('put', '/Organization/' . $organizationId, $payload);
    }
}
