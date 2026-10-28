<?php

namespace App\Http\Traits\SATUSEHAT;

/**
 * FHIR CarePlan (SATUSEHAT) — Perencanaan Pemulangan Pasien (Playbook RI bab 22b), disamakan dgn alur
 * Rawat Inap apikesehatan.com: status active, intent plan, category SNOMED 736372004 Discharge care plan
 * (diverifikasi tx.fhir.org 2026-10-28), title, description.
 *
 * Tambahan dari desain discharge planning RI: tiap entri terstruktur → activity.detail (kind
 * ServiceRequest untuk pelayanan berkelanjutan, DeviceRequest untuk alat bantu). Constraint FHIR cpl-3:
 * activity memakai `detail` ATAU `reference`, tak boleh dua-duanya — di sini selalu `detail`.
 */
trait CarePlanTrait
{
    use SatuSehatTrait;

    /**
     * @param array $data
     *   - patientId    (string)  required
     *   - encounterId  (string)  required
     *   - authorId     (string)  optional — Practitioner IHS penyusun
     *   - created      (string)  ISO8601
     *   - description  (string)  required — rencana pemulangan (naratif)
     *   - periodEnd    (string)  optional — ISO8601 (tanggal pulang)
     *   - aktivitas    (array)   optional — list ['kind' => 'ServiceRequest'|'DeviceRequest',
     *                            'code' => ['system','code','display'], 'text' => string, 'tanggal' => ISO|null]
     */
    public function createCarePlanPemulangan(array $data): array
    {
        $payload = [
            'resourceType' => 'CarePlan',
            'status' => 'active',
            'intent' => 'plan',
            'title' => 'Perencanaan Pemulangan Pasien',
            'category' => [['coding' => [[
                'system' => 'http://snomed.info/sct',
                'code' => '736372004',
                'display' => 'Discharge care plan',
            ]]]],
            'description' => (string) $data['description'],
            'subject' => ['reference' => 'Patient/' . $data['patientId']],
            'encounter' => ['reference' => 'Encounter/' . $data['encounterId']],
            'created' => $data['created'] ?? now()->toIso8601String(),
        ];

        if (filled($data['authorId'] ?? null)) {
            $payload['author'] = ['reference' => 'Practitioner/' . $data['authorId']];
        }
        if (filled($data['periodEnd'] ?? null)) {
            $payload['period'] = ['end' => $data['periodEnd']];
        }

        foreach ((array) ($data['aktivitas'] ?? []) as $aktivitas) {
            $detail = [
                'kind' => $aktivitas['kind'],
                'status' => 'not-started',
                'description' => (string) ($aktivitas['text'] ?? ''),
            ];
            if (!empty($aktivitas['code']['code'])) {
                $detail['code'] = ['coding' => [$aktivitas['code']], 'text' => (string) ($aktivitas['text'] ?? '')];
            }
            if (filled($aktivitas['tanggal'] ?? null)) {
                $detail['scheduledString'] = (string) $aktivitas['tanggal'];
            }
            $payload['activity'][] = ['detail' => $detail];
        }

        return $this->makeRequest('post', '/CarePlan', $payload);
    }
}
