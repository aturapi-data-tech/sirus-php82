<?php

namespace App\Http\Traits\SATUSEHAT;

use Carbon\Carbon;

trait ProcedureTrait
{
    use SatuSehatTrait;

    /**
     * Kirim Tindakan/Prosedur Medis pasien (Procedure resource)
     *
     * @param array $data
     *  - patientId           (string)
     *  - encounterId         (string)
     *  - primarySystem       (string) system utama coding, default SNOMED CT
     *  - primaryCode         (string) kode utama
     *  - primaryDisplay      (string) deskripsi utama
     *  - secondarySystem     (string) system sekunder, default ICD-9-CM (optional)
     *  - secondaryCode       (string) kode sekunder (optional)
     *  - secondaryDisplay    (string) display sekunder (optional)
     *  - status              (string) completed|in-progress|entered-in-error, default completed
     *  - categorySystem      (string) system kategori, default FHIR procedure-category
     *  - categoryCode        (string) kode kategori, default 'procedure'
     *  - categoryDisplay     (string) display kategori, default 'Procedure'
     *  - performedDateTime   (string) ISO datetime, default now
     *  - performedPeriod     (array) ['start'=>ISO,'end'=>ISO] optional
     *  - performerId         (string) Practitioner ID (optional)
     *  - performerRole       (string) peran performer (optional)
     *  - reasonCodes         (array) list of ['system'=>...,'code'=>...,'display'=>...] (optional)
     *  - bodySite            (array) list of ['system'=>...,'code'=>...,'display'=>...] (optional)
     *  - note                (string) catatan klinis (optional)
     *
     * @return array
     */
    public function createProcedure(array $data): array
    {
        $payload = [
            'resourceType'      => 'Procedure',
            'status'            => $data['status'] ?? 'completed',
            'category'          => [
                'coding' => [[
                    'system'  => 'http://snomed.info/sct',
                    'code'    => '71388002',
                    'display' => 'Procedure (procedure)',
                ]],
                'text' => 'Procedure',
            ],
            'code'              => [
                'coding' => [[
                    'system'  => $data['codeSystem'] ?? 'http://snomed.info/sct',
                    'code'    => $data['code'],      // pastikan non-empty
                    'display' => $data['display'],   // pastikan non-empty
                ]],
                'text'   => $data['display'],
            ],
            'subject'           => ['reference' => 'Patient/'    . $data['patientId']],
            'encounter'         => ['reference' => 'Encounter/' . $data['encounterId']],
            'performedDateTime' => $data['performedDateTime'] ?? now()->toIso8601String(),
        ];

        if (!empty($data['performerId'])) {
            $payload['performer'] = [[
                'actor' => ['reference' => 'Practitioner/' . $data['performerId']],
                'function'  => ['text'      => $data['performerRole'] ?? ''],
            ]];
        }

        return $this->makeRequest('post', '/Procedure', $payload);
    }




    /**
     * Update Tindakan/Prosedur Medis (PUT Procedure/{id})
     */
    public function updateProcedure(string $procedureId, array $data): array
    {
        $data['resourceType'] = 'Procedure';
        return $this->makeRequest('put', "Procedure/{$procedureId}", $data);
    }

    /**
     * Cari Prosedur Medis berdasarkan pasien
     */
    public function searchProcedureByPatient(string $patientId, string $code = ''): array
    {
        $url = 'Procedure?subject=Patient/' . $patientId;
        if ($code !== '') {
            $url .= '&code=' . urlencode($code);
        }
        return $this->makeRequest('get', $url);
    }
}
