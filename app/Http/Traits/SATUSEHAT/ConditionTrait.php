<?php
// app/Http/Traits/SATUSEHAT/ConditionTrait.php

namespace App\Http\Traits\SATUSEHAT;

use Carbon\Carbon;

trait ConditionTrait
{
    use SatuSehatTrait;

    /**
     * Membuat Keluhan Utama (Chief Complaint)
     *
     * Minimal dikirim:
     *   - patientId (string)
     *   - encounterId (string)
     *   - snomed_code (string, SNOMED CT code untuk keluhan)
     *   - complaint_text (string)
     *
     * Opsional:
     *   - snomed_display (string)
     *   - onsetDate (ISO8601)
     *   - recordedDate (ISO8601)
     *     onsetDateTime = “mulai merasa”
     *     recordedDate = “mulai mencatat”

     *   - severity_code (string, SNOMED CT code untuk tingkat keparahan)
     *   - severity_display (string)
     *
     * Contoh penggunaan:
     * <code>
     * $this->createChiefComplaint([
     *   'patientId'       => 'P123456789',
     *   'encounterId'     => 'E987654321',
     *   'snomed_code'     => '21522001',              // Abdominal pain (finding)
     *   'snomed_display'  => 'Abdominal pain (finding)',
     *   'complaint_text'  => 'Nyeri perut hebat sejak pagi',
     *   'onsetDate'       => '2025-04-29T10:00:00+07:00',
     *   'recordedDate'    => '2025-04-29T11:00:00+07:00',
     *   'severity_code'   => '255604002',             // Mild
     *   'severity_display'=> 'Mild',
     * ]);
     * </code>
     *
     * @param  array  $data
     * @return array
     */

    public function createChiefComplaint(array $data): array
    {
        // Validasi wajib SNOMED CT
        if (empty($data['snomed_code'])) {
            throw new \InvalidArgumentException(
                'SNOMED CT code untuk keluhan utama wajib diset.'
            );
        }

        $payload = [
            'resourceType'       => 'Condition',
            'clinicalStatus'     => [
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                    'code'    => 'active',
                    'display' => 'Active',
                ]],
            ],
            'verificationStatus' => [
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/condition-ver-status',
                    'code'    => 'confirmed',
                    'display' => 'Confirmed',
                ]],
            ],
            'category'           => [[
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/condition-category',
                    'code'    => 'problem-list-item',
                    'display' => 'Problem List Item',
                ]],
            ]],
            'code'               => [
                'coding' => [[
                    'system'  => 'http://snomed.info/sct',
                    'code'    => $data['snomed_code'],
                    'display' => $data['snomed_display'] ?? '',
                ]],
                // Text lokal sebagai fallback/human-readable
                'text'   => $data['complaint_text'] ?? ($data['snomed_display'] ?? ''),
            ],
            'subject'            => ['reference' => 'Patient/' . $data['patientId']],
            'encounter'          => ['reference' => 'Encounter/' . $data['encounterId']],
            'recordedDate'       => $data['recordedDate'] ?? now()->toIso8601String(),
        ];

        if (!empty($data['onsetDate'])) {
            $payload['onsetDateTime'] = $data['onsetDate'];
        }

        // Optional severity
        if (!empty($data['severity_code'])) {
            $payload['severity'] = [
                'coding' => [[
                    'system'  => 'http://snomed.info/sct',
                    'code'    => $data['severity_code'],
                    'display' => $data['severity_display'] ?? '',
                ]],
            ];
        }

        return $this->makeRequest('post', 'Condition', $payload);
    }

    /**
     * Create a FHIR Condition resource for Riwayat Penyakit Sekarang current condition (chief complaint or active finding)
     *
     * @param array $data
     *   - patientId (string)
     *   - encounterId (string)
     *   - snomed_code (string)
     *   - snomed_display (string)
     *   - complaint_text (string)
     *   - recordedDate (ISO8601 string)
     *   - onsetDate (ISO8601 string)
     *   - note (string)
     *
     * @return array FHIR response JSON
     *
     * @throws \InvalidArgumentException
     * @throws \Exception on HTTP error
     */
    public function createCurrentCondition(array $data): array
    {
        // SNOMED CT code mandatory
        if (empty($data['snomed_code'])) {
            throw new \InvalidArgumentException(
                'SNOMED CT code untuk kondisi sekarang wajib diset.'
            );
        }
        // Encounter reference mandatory
        if (empty($data['encounterId'])) {
            throw new \InvalidArgumentException(
                'Encounter ID untuk kondisi sekarang wajib diset.'
            );
        }

        // Build payload
        $payload = [
            'resourceType'       => 'Condition',
            'clinicalStatus'     => [
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                    'code'    => 'active',
                    'display' => 'Active',
                ]],
            ],
            'verificationStatus' => [
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/condition-ver-status',
                    'code'    => 'confirmed',
                    'display' => 'Confirmed',
                ]],
            ],
            'category'           => [[
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/condition-category',
                    'code'    => 'problem-list-item',
                    'display' => 'Problem List Item',
                ]],
            ]],
            'code'               => [
                'coding' => [[
                    'system'  => 'http://snomed.info/sct',
                    'code'    => $data['snomed_code'],
                    'display' => $data['snomed_display'] ?? '',
                ]],
                'text'   => $data['complaint_text'] ?? ($data['snomed_display'] ?? ''),
            ],
            'subject'            => [
                'reference' => 'Patient/' . $data['patientId'],
            ],
            'encounter'          => [
                'reference' => 'Encounter/' . $data['encounterId'],
            ],
            'recordedDate'       => $data['recordedDate'] ?? Carbon::now()->toIso8601String(),
        ];

        // Optional: onsetDateTime
        if (!empty($data['onsetDate'])) {
            $payload['onsetDateTime'] = $data['onsetDate'];
        }

        // Optional: note
        if (!empty($data['note'])) {
            $payload['note'] = [['text' => $data['note']]];
        }

        // Send request
        return $this->makeRequest('post', 'Condition', $payload);
    }


    /**
     * Buat resource Condition untuk riwayat penyakit dahulu (past medical history)
     *
     * @param array $data
     *   - patientId (string)
     *   - encounterId (string)
     *   - snomed_code (string)
     *   - snomed_display (string)
     *   - history_text (string)
     *   - recordedDate (ISO8601 string)
     *   - onsetDate (ISO8601 string)
     *   - abatementDate (ISO8601 string)
     *   - note (string)
     *
     * @return array FHIR response JSON
     *
     * @throws \InvalidArgumentException
     * @throws \Exception on HTTP error
     */
    public function createPastMedicalHistory(array $data): array
    {
        // SNOMED CT code mandatory
        if (empty($data['snomed_code'])) {
            throw new \InvalidArgumentException(
                'SNOMED CT code untuk riwayat penyakit dahulu wajib diset.'
            );
        }
        // Encounter reference mandatory
        if (empty($data['encounterId'])) {
            throw new \InvalidArgumentException(
                'Encounter ID untuk riwayat penyakit dahulu wajib diset.'
            );
        }

        // Build payload
        $payload = [
            'resourceType'       => 'Condition',
            'clinicalStatus'     => [
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                    'code'    => 'resolved',
                    'display' => 'Resolved',
                ]],
            ],
            'verificationStatus' => [
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/condition-ver-status',
                    'code'    => 'confirmed',
                    'display' => 'Confirmed',
                ]],
            ],
            'category'           => [[
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/condition-category',
                    'code'    => 'problem-list-item',
                    'display' => 'Problem List Item',
                ]],
            ]],
            'code'               => [
                'coding' => [[
                    'system'  => 'http://snomed.info/sct',
                    'code'    => $data['snomed_code'],
                    'display' => $data['snomed_display'] ?? '',
                ]],
                'text'   => $data['history_text'] ?? ($data['snomed_display'] ?? ''),
            ],
            'subject'            => [
                'reference' => 'Patient/' . $data['patientId'],
            ],
            'encounter'          => [
                'reference' => 'Encounter/' . $data['encounterId'],
            ],
            'recordedDate'       => $data['recordedDate'] ?? Carbon::now()->toIso8601String(),
        ];

        // Optional: onsetDateTime
        if (!empty($data['onsetDate'])) {
            $payload['onsetDateTime'] = $data['onsetDate'];
        }

        // Optional: abatementDateTime
        if (!empty($data['abatementDate'])) {
            $payload['abatementDateTime'] = $data['abatementDate'];
        }

        // Optional: note
        if (!empty($data['note'])) {
            $payload['note'] = [['text' => $data['note']]];
        }

        // Send request
        return $this->makeRequest('post', 'Condition', $payload);
    }


    /**
     * Membuat keluhan penyerta (Additional Complaint)
     *
     * @param array $data
     *   - mainConditionId, additionalConditionId, dan data createChiefComplaint
     * @return array
     */
    public function createAdditionalComplaint(array $data): array
    {
        // Ambil payload dasar dari createChiefComplaint
        $payload = [
            'resourceType'       => 'Condition',
            'clinicalStatus'     => [
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                    'code'    => 'active',
                    'display' => 'Active',
                ]]
            ],
            'verificationStatus' => [
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/condition-ver-status',
                    'code'    => 'confirmed',
                    'display' => 'Confirmed',
                ]]
            ],
            'category'           => [[
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/condition-category',
                    'code'    => 'complaint',
                    'display' => 'Complaint',
                ]]
            ]],
            'code'               => $data['codePayload'],
            'subject'            => ['reference' => 'Patient/' . $data['patientId']],
            'encounter'          => ['reference' => 'Encounter/' . $data['encounterId']],
            'onsetDateTime'      => $data['onsetDate'] ?? Carbon::now()->toIso8601String(),
            'recordedDate'       => Carbon::now()->toIso8601String(),
            'severity'           => $data['severityPayload'] ?? [],
            'extension'          => [[
                'url'                => 'http://hl7.org/fhir/StructureDefinition/condition-related',
                'valueReference'     => [
                    'reference' => 'Condition/' . $data['mainConditionId'],
                    'display'   => 'Keluhan Utama',
                ],
            ]],
        ];

        return $this->makeRequest('put', "Condition/{$data['additionalConditionId']}", $payload);
    }


    /**
     * Mencari Conditions berdasarkan pasien dan kategori
     *
     * @param string $patientId
     * @param string $category  // 'problem-list-item' atau 'encounter-diagnosis'
     * @return array
     */
    public function searchConditionsByPatient(string $patientId, string $category = 'problem-list-item'): array
    {
        $endpoint = "Condition?subject=Patient/{$patientId}";
        $endpoint .= '&category=' . urlencode('http://terminology.hl7.org/CodeSystem/condition-category|' . $category);
        return $this->makeRequest('get', $endpoint);
    }

    /**
     * Mencari Conditions berdasarkan encounter
     *
     * @param string $encounterId
     * @return array
     */
    public function searchConditionsByEncounter(string $encounterId): array
    {
        $endpoint = "Condition?encounter=Encounter/{$encounterId}";
        return $this->makeRequest('get', $endpoint);
    }

    /**
     * Update status Condition
     *
     * @param string $conditionId
     * @param string $status    // active|recurrence|relapse|inactive|remission|resolved
     * @return array
     */
    public function updateConditionStatus(string $conditionId, string $status): array
    {
        $payload = [
            'resourceType'   => 'Condition',
            'id'             => $conditionId,
            'clinicalStatus' => [
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                    'code'    => $status,
                    'display' => ucfirst($status),
                ]]
            ],
        ];

        return $this->makeRequest('put', "Condition/{$conditionId}", $payload);
    }

    /**
     * Mendapatkan data Condition by ID
     *
     * @param string $conditionId
     * @return array
     */
    public function getCondition(string $conditionId): array
    {
        return $this->makeRequest('get', "Condition/{$conditionId}");
    }









    public function createFinalDiagnosis(array $data): array
    {
        // Setup coding entries
        $codings = [];
        // ICD-10 must
        $codings[] = [
            'system'  => 'http://hl7.org/fhir/sid/icd-10',
            'code'    => $data['icd10_code'],
            'display' => $data['icd10_display'],
        ];
        // SNOMED if provided
        if (!empty($data['snomed_code']) && !empty($data['snomed_display'])) {
            $codings[] = [
                'system'  => 'http://snomed.info/sct',
                'code'    => $data['snomed_code'],
                'display' => $data['snomed_display'],
            ];
        }

        // ClinicalStatus
        $clinical = $data['clinicalStatus'] ?? ['code' => 'active', 'display' => 'Active'];
        // VerificationStatus
        $verification = $data['verificationStatus'] ?? ['code' => 'confirmed', 'display' => 'Confirmed'];

        // Build payload
        $payload = [
            'resourceType'       => 'Condition',
            'clinicalStatus'     => [
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                    'code'    => $clinical['code'],
                    'display' => $clinical['display'],
                ]],
                'text' => $clinical['display'],
            ],
            'verificationStatus' => [
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/condition-ver-status',
                    'code'    => $verification['code'],
                    'display' => $verification['display'],
                ]],
                'text' => $verification['display'],
            ],
            'category'           => [[
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/condition-category',
                    'code'    => 'encounter-diagnosis',
                    'display' => 'Encounter Diagnosis',
                ]],
            ]],
            'code'               => [
                'coding' => $codings,
                'text'   => $data['diagnosis_text'],
            ],
            'subject'            => [
                'reference' => 'Patient/' . $data['patientId'],
                'display'   => $data['patientDisplay'] ?? null,
            ],
            'encounter'          => [
                'reference' => 'Encounter/' . $data['encounterId'],
            ],
        ];

        // OnsetDateTime if provided
        if (!empty($data['onsetDateTime'])) {
            $payload['onsetDateTime'] = $data['onsetDateTime'];
        }
        // RecordedDate if provided
        if (!empty($data['recordedDate'])) {
            $payload['recordedDate'] = $data['recordedDate'];
        }

        // Stage assessment if provided
        if (!empty($data['stageAssessment'])) {
            $payload['stage'] = [[
                'assessment' => [[
                    'reference' => 'ClinicalImpression/' . $data['stageAssessment'],
                ]],
            ]];
        }

        return $this->makeRequest('post', '/Condition', $payload);
    }
}
