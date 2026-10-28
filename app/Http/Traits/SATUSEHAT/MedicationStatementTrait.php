<?php

namespace App\Http\Traits\SATUSEHAT;

trait MedicationStatementTrait
{
    use SatuSehatTrait;

    /**
     * Riwayat pengobatan (Playbook RJ bab 3f) — satu MedicationStatement per obat. Disamakan
     * dengan alur Rawat Jalan apikesehatan.com: category `community`, obat KFA
     * (medicationCodeableConcept), context = Encounter, informationSource = dokter.
     * Bentuk baris: App\Support\RiwayatPengobatan.
     *
     * @param array $data patientId, encounterId, status, kfaCode, kfaDisplay, namaObat,
     *                    aturanPakai?, waktuIso, practitionerId?
     */
    public function createMedicationStatement(array $data): array
    {
        if (empty($data['kfaCode'])) {
            throw new \InvalidArgumentException('Kode KFA obat wajib diisi.');
        }

        $payload = [
            'resourceType' => 'MedicationStatement',
            'status'       => $data['status'] ?: 'active',
            'category'     => [
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/medication-statement-category',
                    'code'    => 'community',
                    'display' => 'Community',
                ]],
            ],
            'medicationCodeableConcept' => [
                'coding' => [[
                    'system'  => 'http://sys-ids.kemkes.go.id/kfa',
                    'code'    => $data['kfaCode'],
                    'display' => ($data['kfaDisplay'] ?? '') ?: ($data['namaObat'] ?? ''),
                ]],
                'text' => $data['namaObat'] ?? '',
            ],
            'subject'         => ['reference' => 'Patient/' . $data['patientId']],
            'context'         => ['reference' => 'Encounter/' . $data['encounterId']],
            'dateAsserted'    => $data['waktuIso'],
            'effectivePeriod' => ['start' => $data['waktuIso']],
        ];

        if (!empty($data['practitionerId'])) {
            $payload['informationSource'] = ['reference' => 'Practitioner/' . $data['practitionerId']];
        }
        if (filled($data['aturanPakai'] ?? null)) {
            $payload['dosage'] = [['text' => $data['aturanPakai']]];
        }

        return $this->makeRequest('post', 'MedicationStatement', $payload);
    }
}
