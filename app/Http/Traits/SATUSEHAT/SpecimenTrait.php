<?php

namespace App\Http\Traits\SATUSEHAT;

use Carbon\Carbon;

trait SpecimenTrait
{
    use SatuSehatTrait;

    /**
     * Build payload for a SatuSehat Specimen
     *
     * @param array $data  Required keys:
     *  - identifier: [
     *        'system'   => string,
     *        'value'    => string,
     *        'assigner' => string (reference, e.g. 'Organization/{id}')
     *    ]
     *  - status: string (e.g. 'available')
     *  - subject: string (reference, e.g. 'Patient/{id}')
     *  - type: [ 'system' => string, 'code' => string, 'display' => string ]
     *  - collection: [
     *        'collectedDateTime' => string (ISO8601),
     *        'method' => [ 'system' => string, 'code' => string, 'display' => string ]
     *    ]
     *  - receivedTime: string (ISO8601)
     *  - request: array of references (e.g. [ 'ServiceRequest/{id}' ])
     *
     * @return array
     */
    protected function buildSpecimen(array $data): array
    {
        $payload = [
            'resourceType' => 'Specimen',
            'identifier'   => [[
                'system'   => $data['identifier']['system'],
                'value'    => $data['identifier']['value'],
                'assigner' => ['reference' => $data['identifier']['assigner']],
            ]],
            'status'       => $data['status'] ?? 'available',
            'subject'      => ['reference' => $data['subject']],
            'type'         => [
                'coding' => [[
                    'system'  => $data['type']['system'],
                    'code'    => $data['type']['code'],
                    'display' => $data['type']['display'],
                ]]
            ],
            'collection'   => [
                'collectedDateTime' => $data['collection']['collectedDateTime'] ?? Carbon::now()->toIso8601String(),
                'method' => [
                    'coding' => [[
                        'system'  => $data['collection']['method']['system'],
                        'code'    => $data['collection']['method']['code'],
                        'display' => $data['collection']['method']['display'],
                    ]]
                ],
            ],
            'receivedTime' => $data['receivedTime'] ?? Carbon::now()->toIso8601String(),
            'request'      => array_map(function ($ref) {
                return ['reference' => $ref];
            }, $data['request'] ?? []),
        ];

        return $payload;
    }

    /**
     * Create a Specimen in SatuSehat
     *
     * @param array $data  Parameters to build the payload
     * @return array
     * @throws \Exception on API error
     */
    public function postSpecimen(array $data): array
    {
        $payload = $this->buildSpecimen($data);
        return $this->makeRequest('post', '/Specimen', $payload);
    }

    /**
     * Retrieve a Specimen by its UUID
     *
     * @param string $id
     * @return array
     */
    public function getSpecimen(string $id): array
    {
        return $this->makeRequest('get', "/Specimen/{$id}");
    }

    /**
     * Search for Specimens with query parameters
     *
     * @param array $params  e.g. ['patient' => '{id}', 'date' => '2022-06-14']
     * @return array
     */
    public function searchSpecimen(array $params = []): array
    {
        return $this->makeRequest('get', '/Specimen', [], $params);
    }
}
