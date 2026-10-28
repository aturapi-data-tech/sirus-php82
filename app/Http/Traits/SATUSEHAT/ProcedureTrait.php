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
     *  - kategori            (array) ['system','code','display'] (optional, aditif) — menggantikan kategori
     *                        default 71388002, mis. 409073007 Education (Playbook RI 21)
     *  - codingTambahan      (array) list coding tambahan di code.coding (optional, aditif), mis. KPTL
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

        // Opsional (aditif) — kategori, coding tambahan, catatan. Tidak diisi = payload lama tak berubah.
        if (!empty($data['kategori']['code'])) {
            $payload['category'] = ['coding' => [array_intersect_key($data['kategori'], array_flip(['system', 'code', 'display']))], 'text' => $data['kategori']['display'] ?? ''];
        }
        foreach ((array) ($data['codingTambahan'] ?? []) as $coding) {
            if (!empty($coding['code'])) {
                $payload['code']['coding'][] = array_intersect_key($coding, array_flip(['system', 'code', 'display']));
            }
        }
        if (filled($data['note'] ?? null)) {
            $payload['note'] = [['text' => (string) $data['note']]];
        }

        if (!empty($data['performerId'])) {
            $performer = ['actor' => ['reference' => 'Practitioner/' . $data['performerId']]];
            // function hanya bila ada isinya — {text: ''} = elemen kosong, rawan ditolak validator.
            if (filled($data['performerRole'] ?? null)) {
                $performer['function'] = ['text' => $data['performerRole']];
            }
            $payload['performer'] = [$performer];
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
