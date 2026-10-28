<?php

namespace App\Http\Traits\SATUSEHAT;

trait ClinicalImpressionTrait
{
    use SatuSehatTrait;

    /**
     * Kirim Impresi Klinik / asesmen ("A" di SOAP) ke SATUSEHAT.
     *
     * @param array $data
     *   - patientId    (string) IHS pasien           [wajib]
     *   - encounterId  (string) IHS encounter         [wajib]
     *   - assessorId   (string) IHS Practitioner      [wajib]
     *   - summary      (string) teks asesmen klinis   [wajib]
     *   - description  (string) deskripsi singkat     (opsional)
     *   - effective    (string) ISO8601              (opsional, default now)
     *   - findings     (array)  list ['code'=>SNOMED,'display'=>...] (opsional)
     *
     * @return array
     */
    public function createClinicalImpression(array $data): array
    {
        foreach (['patientId', 'encounterId', 'assessorId', 'summary'] as $req) {
            if (empty($data[$req])) {
                throw new \InvalidArgumentException("Field '{$req}' wajib diset untuk ClinicalImpression.");
            }
        }

        $payload = [
            'resourceType'      => 'ClinicalImpression',
            'status'            => $data['status'] ?? 'completed',
            'description'       => $data['description'] ?? null,
            'subject'           => ['reference' => 'Patient/' . $data['patientId']],
            'encounter'         => ['reference' => 'Encounter/' . $data['encounterId']],
            'effectiveDateTime' => $data['effective'] ?? throw new \RuntimeException('ClinicalImpression.effective: tanggal tidak dikirim dari data — tidak diisi jam sekarang.'),
            'date'              => $data['date'] ?? throw new \RuntimeException('ClinicalImpression.date: tanggal tidak dikirim dari data — tidak diisi jam sekarang.'),
            'assessor'          => ['reference' => 'Practitioner/' . $data['assessorId']],
            'summary'           => $data['summary'],
        ];

        // Opsional (aditif, pemanggil lama tak berubah) — dipakai RJ bab 6 & 22:
        //   code       → ['system','code','display'] jenis impresi (mis. 312850006 / 20481000)
        //   prognosis  → ['code','display'] SNOMED → prognosisCodeableConcept
        //   problemIds → Condition yang dinilai (problem[])
        if (!empty($data['code'])) {
            $payload['code'] = ['coding' => [[
                'system'  => $data['code']['system'],
                'code'    => $data['code']['code'],
                'display' => $data['code']['display'],
            ]]];
        }
        if (!empty($data['prognosis'])) {
            $payload['prognosisCodeableConcept'] = [[
                'coding' => [[
                    'system'  => 'http://snomed.info/sct',
                    'code'    => $data['prognosis']['code'],
                    'display' => $data['prognosis']['display'],
                ]],
            ]];
        }
        if (!empty($data['problemIds'])) {
            $payload['problem'] = array_map(fn($id) => ['reference' => 'Condition/' . $id], array_values($data['problemIds']));
        }
        if ($payload['description'] === null) {
            unset($payload['description']); // jangan kirim "description": null
        }

        if (!empty($data['findings']) && is_array($data['findings'])) {
            $payload['finding'] = array_map(function ($f) {
                return [
                    'itemCodeableConcept' => [
                        'coding' => [[
                            'system'  => 'http://snomed.info/sct',
                            'code'    => $f['code'],
                            'display' => $f['display'] ?? '',
                        ]],
                    ],
                ];
            }, $data['findings']);
        }

        return $this->makeRequest('post', '/ClinicalImpression', $payload);
    }
}
