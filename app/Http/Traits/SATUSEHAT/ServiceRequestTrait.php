<?php

namespace App\Http\Traits\SATUSEHAT;

trait ServiceRequestTrait
{
    use SatuSehatTrait;

    /**
     * Build payload for a SatuSehat ServiceRequest
     *
     * @param array $data  Required keys:
     *- identifier: [ 'system' => string, 'value' => string ]
     *- status: string (e.g. 'active')
     *- intent: string (e.g. 'original-order')
     *- priority: string (e.g. 'routine')
     *- category: [ 'system' => string, 'code' => string, 'display' => string ]
     *- code: [ 'system' => string, 'code' => string, 'display' => string ]
     *- subject: string (reference, e.g. 'Patient/{id}')
     *- encounter: string (reference, e.g. 'Encounter/{id}')
     *- occurrenceDateTime: string (ISO8601)
     *- authoredOn: string (ISO8601)
     *- requester: string (reference, e.g. 'Practitioner/{id}')
     *- performer (optional): array of references
     *- reasonCode (optional): array of either text or coding arrays
     *
     * @return array
     */
    protected function buildServiceRequest(array $data)
    {
        $payload = [
            'resourceType' => 'ServiceRequest',
            'identifier' => [[
                'system' => $data['identifier']['system'],
                'value'  => $data['identifier']['value'],
            ]],
            'status' => $data['status'] ?? 'active',
            'intent' => $data['intent'] ?? 'original-order',
            'priority'  => $data['priority'] ?? 'routine',
            'category'  => [[
                'coding' => [$data['category']],
            ]],
            'code' => [
                'coding' => [$data['code']],
                'text' => $data['code']['display'] ?? null,
            ],
            'subject' => ['reference' => $data['subject']],
            'encounter' => ['reference' => $data['encounter']],
            'occurrenceDateTime' => $data['occurrenceDateTime'] ?? now()->toIso8601String(),
            'authoredOn' => $data['authoredOn'] ?? now()->toIso8601String(),
            'requester' => ['reference' => $data['requester'], 'display' => $data['requesterDisplay']],
        ];

        if (!empty($data['performer'])) {
            $payload['performer'] = [
                ['reference' => $data['performer'], 'display' => $data['performerDisplay']]
            ];
        }

        if (!empty($data['reasonCode'])) {
            $payload['reasonCode'] = $data['reasonCode'];
        }

        return $payload;
    }

    /**
     * Create a ServiceRequest in SatuSehat
     *
     * @param array $data  Build parameters for the payload
     * @return array
     * @throws \Exception on API error
     */
    public function postServiceRequest(array $data)
    {
        $payload = $this->buildServiceRequest($data);
        return $this->makeRequest('post', '/ServiceRequest', $payload);
    }

    /**
     * Retrieve a ServiceRequest by its UUID
     *
     * @param string $id
     * @return array
     */
    public function getServiceRequest(string $id)
    {
        return $this->makeRequest('get', "/ServiceRequest/{$id}");
    }

    /**
     * Search for ServiceRequests with query parameters
     *
     * @param array $params  e.g. ['patient' => '{id}', 'date' => '2025-05-07']
     * @return array
     */
    public function searchServiceRequest(array $params = []): array
    {
        return $this->makeRequest('get', '/ServiceRequest', [], $params);
    }
}
