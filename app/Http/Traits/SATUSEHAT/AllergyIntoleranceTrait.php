<?php

namespace App\Http\Traits\SATUSEHAT;


trait AllergyIntoleranceTrait
{
    use SatuSehatTrait;

    /**
     * Mengirim data riwayat alergi pasien ke SATUSEHAT
     *
     * @param array $data
     * @return array
     */
    public function createAllergyIntolerance(array $data): array
    {
        // validasi wajib
        if (empty($data['patientId'])) {
            throw new \InvalidArgumentException('Patient ID wajib diset.');
        }
        if (empty($data['encounterId'])) {
            throw new \InvalidArgumentException('Encounter ID wajib diset.');
        }
        if (empty($data['code'])) {
            throw new \InvalidArgumentException('SNOMED code alergi wajib diset.');
        }
        if (empty($data['recorderId'])) {
            throw new \InvalidArgumentException('Recorder (Practitioner ID) wajib diset.');
        }

        $payload = [
            "resourceType"       => "AllergyIntolerance",
            "clinicalStatus"     => [
                "coding" => [[
                    "system" => "http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical",
                    "code"   => "active"
                ]]
            ],
            "verificationStatus" => [
                "coding" => [[
                    "system" => "http://terminology.hl7.org/CodeSystem/allergyintolerance-verification",
                    "code"   => "confirmed"
                ]]
            ],
            "type"               => "allergy",
            "category"           => [$data['category'] ?? 'medication'],
            "criticality"        => $data['criticality'] ?? 'low',
            "code"               => [
                "coding" => [[
                    "system"  => "http://snomed.info/sct",
                    "code"    => $data['code'],
                    "display" => $data['display']
                ]],
                "text"   => $data['display']
            ],
            "patient"            => [
                "reference" => "Patient/{$data['patientId']}"
            ],
            // **wajib**: encounter reference
            "encounter"          => [
                "reference" => "Encounter/{$data['encounterId']}"
            ],
            // **wajib**: who recorded
            "recorder"           => [
                "reference" => "Practitioner/{$data['recorderId']}"
            ],
            "onsetDateTime"      => $data['onset']   ?? now()->toIso8601String(),
            "note"               => [["text" => $data['note']  ?? '']],
        ];

        return $this->makeRequest('post', '/AllergyIntolerance', $payload);
    }

    public function fetchAllergyIntoleranceByPatient(string $patientId): array
    {
        $this->initializeSatuSehat();

        // Gunakan makeRequest untuk GET dengan query patient
        $endpoint = "AllergyIntolerance?patient=Patient/{$patientId}";
        return $this->makeRequest('get', $endpoint);
    }
}
