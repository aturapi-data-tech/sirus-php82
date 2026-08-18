<?php

namespace App\Http\Traits\SATUSEHAT;

trait DiagnosticReportTrait
{
    use SatuSehatTrait;


    /**
     * Kirim hasil pemeriksaan penunjang (lab / radiologi / mikrobiologi) ke SATUSEHAT
     *
     * @param array $data  Contoh struktur data:
     *  - identifier       => [ ['system'=>..., 'use'=>..., 'value'=>...], ... ]
     *  - status           => 'final'
     *  - categoryCode     => 'LAB' atau 'MB' atau 'RAD'
     *  - categoryDisplay  => 'Laboratory' atau 'Microbiology' atau 'Radiology'
     *  - codeSystem       => 'http://loinc.org'
     *  - code             => kode LOINC atau lokal
     *  - display          => nama pemeriksaan
     *  - patientId        => Patient/{id}
     *  - encounterId      => Encounter/{uuid}
     *  - effectiveDate    => YYYY-MM-DDThh:mm:ss+ZZ:zz
     *  - issued           => YYYY-MM-DDThh:mm:ss+ZZ:zz
     *  - performer        => ['Practitioner/ID1', 'Organization/ID2']
     *  - specimen         => ['Specimen/UUID1', ...]
     *  - observationIds   => ['obs-id-1', 'obs-id-2']
     *  - conclusionCode   => [ ['coding'=>[ ['system'=>..., 'code'=>..., 'display'=>...] ] ], ... ]
     *
     * @return array
     */
    public function createDiagnosticReport(array $data): array
    {
        $payload = [
            'resourceType'      => 'DiagnosticReport',
            'status'            => $data['status']           ?? 'final',
            'category'          => [[
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/v2-0074',
                    'code'    => $data['categoryCode']    ?? 'MB',  // Ganti dengan kategori yang sesuai: 'LAB', 'MB', atau 'RAD'
                    'display' => $data['categoryDisplay'] ?? 'Microbiology',  // Ganti dengan kategori display yang sesuai
                ]]
            ]],
            'code'  => [
                'coding' => [[
                    'system'  => $data['codeSystem'] ?? 'http://loinc.org',
                    'code'    => $data['code'],
                    'display' => $data['display'],
                ]],
                'text'   => $data['display'],
            ],
            'subject'           => ['reference' => 'Patient/' . $data['patientId']],
            'encounter'         => ['reference' => 'Encounter/' . $data['encounterId']],
            'effectiveDateTime' => $data['effectiveDate']   ?? now()->toIso8601String(),
            'issued'            => $data['issued']          ?? now()->toIso8601String(),
        ];

        // Identifier (optional)
        if (!empty($data['identifier']) && is_array($data['identifier'])) {
            $payload['identifier'] = $data['identifier'];
        }

        // Performer (optional)
        if (!empty($data['performer']) && is_array($data['performer'])) {
            $payload['performer'] = array_map(function ($ref) {
                return ['reference' => $ref];
            }, $data['performer']);
        }

        // Specimen (optional)
        if (!empty($data['specimen']) && is_array($data['specimen'])) {
            $payload['specimen'] = array_map(function ($ref) {
                return ['reference' => $ref];
            }, $data['specimen']);
        }

        // Results Observasi (optional)
        if (!empty($data['observationIds']) && is_array($data['observationIds'])) {
            $payload['result'] = array_map(function ($id) {
                return ['reference' => 'Observation/' . $id];
            }, $data['observationIds']);
        }

        // BasedOn (optional) - Referensi ke ServiceRequest
        if (!empty($data['basedOn']) && is_array($data['basedOn'])) {
            $payload['basedOn'] = array_map(function ($ref) {
                return ['reference' => 'ServiceRequest/'  . $ref];
            }, $data['basedOn']);
        }

        // Conclusion Code (optional)
        if (!empty($data['conclusionCode']) && is_array($data['conclusionCode'])) {
            $payload['conclusionCode'] = $data['conclusionCode'];
        }
        return $this->makeRequest('post', '/DiagnosticReport', $payload);
    }
}
