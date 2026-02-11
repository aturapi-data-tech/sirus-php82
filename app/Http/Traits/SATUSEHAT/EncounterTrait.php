<?php
// app/Http/Traits/SATUSEHATEncounterTrait.php

namespace App\Http\Traits\SATUSEHAT;

trait EncounterTrait
{
    use SatuSehatTrait;

    /**
     * Membuat encounter baru (Kunjungan Baru)
     *
     * @param array $data  // pastikan 'locationId' disertakan
     * @return array
     */
    public function createNewEncounter(array $data): array
    {
        // Cek wajib: lokasi harus ada
        if (empty($data['locationId'])) {
            throw new \InvalidArgumentException('Parameter locationId wajib disertakan untuk membuat Encounter.');
        }

        // Build payload dasarnya
        $payload = $this->buildBaseEncounterPayload($data);

        // Set status awal dan tambahkan history
        $start = $payload['period']['start'];
        $payload['status'] = 'arrived';
        $payload['statusHistory'][] = [
            'status' => 'arrived',
            'period' => ['start' => $start],
        ];

        // Jalankan request ke SatuSehat
        return $this->makeRequest('post', 'Encounter', $payload);
    }

    /**
     * Update encounter status ke 'in-progress' (Pasien Masuk Ruang)
     */
    public function startRoomEncounter(string $encounterId, array $data = []): array
    {
        // Dapatkan encounter existing
        $existing = $this->getEncounter($encounterId);

        // Mulai periode dari tanggal RJ (ISO8601)
        $start = isset($data['startDate'])
            ? (\Carbon\Carbon::parse($data['startDate']))->toIso8601String()
            : now()->toIso8601String();

        // Update status dan history
        $existing['status'] = 'in-progress';
        $existing['statusHistory'][] = [
            'status' => 'in-progress',
            'period' => ['start' => $start],
        ];

        // Append lokasi if ada
        if (!empty($data['locationId'])) {
            $existing['location'][] = [
                'location' => ['reference' => 'Location/' . $data['locationId']],
                'status'   => 'active',
                'period'   => ['start' => $start],
            ];
        }

        return $this->makeRequest('put', "Encounter/{$encounterId}", $existing);
    }

    /**
     * Get encounter by ID
     */
    public function getEncounter(string $encounterId): array
    {
        return $this->makeRequest('get', "Encounter/{$encounterId}");
    }

    /**
     * Build payload dasar untuk Encounter
     *
     * @param array $data
     * @return array
     */
    protected function buildBaseEncounterPayload(array $data): array
    {
        // Mulai periode dari tanggal RJ (ISO8601)
        $start = isset($data['startDate'])
            ? (\Carbon\Carbon::parse($data['startDate']))->toIso8601String()
            : now()->toIso8601String();

        // Identifier dengan sistem resmi SatuSehat
        $identifierSystem = 'http://sys-ids.kemkes.go.id/encounter/' . $this->organizationId;
        $identifierValue  = $data['encounterId'] ?? uniqid('enc-');

        // Bangun payload
        $payload = [
            'resourceType'  => 'Encounter',
            'identifier'    => [[
                'system' => $identifierSystem,
                'value'  => $identifierValue,
            ]],
            'status'        => 'planned',
            'statusHistory' => [[
                'status' => 'planned',
                'period' => ['start' => $start],
            ]],
            'class'         => [
                'system'  => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                'code'    => $data['class_code'] ?? 'AMB',
                'display' => $this->getClassDisplay($data['class_code'] ?? 'AMB'),
            ],
            'subject'       => [
                'reference' => 'Patient/' . $data['patientId'],
                'display'   => $data['patientName'] ?? '',
            ],
            'participant'   => [[
                'type'      => [[
                    'coding' => [[
                        'system'  => 'http://terminology.hl7.org/CodeSystem/v3-ParticipationType',
                        'code'    => 'ATND',
                        'display' => 'attender',
                    ]],
                ]],
                'individual' => [
                    'reference' => 'Practitioner/' . $data['practitionerId'],
                    'display'  => $data['practitionerName'] ?? '',
                ],
            ]],
            'period'        => ['start' => $start],
            'serviceProvider' => [
                'reference' => 'Organization/' . $this->organizationId,
            ],
            // Lokasi wajib
            'location'      => [[
                'location' => ['reference' => 'Location/' . $data['locationId']],
                'status'   => 'active',
                'period'   => ['start' => $start],
            ]],
        ];

        return $payload;
    }

    /**
     * Get display text untuk encounter class
     */
    protected function getClassDisplay(string $code): string
    {
        $classes = [
            'IMP'  => 'inpatient encounter',
            'AMB'  => 'ambulatory',
            'EMER' => 'emergency',
            'VR'   => 'virtual',
        ];

        return $classes[$code] ?? 'ambulatory';
    }





















    ////////////////////////////////////blm di explore
    /**
     * Search encounter by patient
     */
    public function searchEncounterByPatient(string $patientId, string $status = ''): array
    {
        $endpoint = "Encounter?subject=Patient/{$patientId}";
        if ($status !== '') {
            $endpoint .= '&status=' . $status;
        }

        return $this->makeRequest('get', $endpoint);
    }

    /**
     * Update encounter status
     */
    public function updateEncounterStatus(string $encounterId, string $status): array
    {
        $payload = [
            'resourceType' => 'Encounter',
            'id'           => $encounterId,
            'status'       => $status,
        ];

        return $this->makeRequest('put', "Encounter/{$encounterId}", $payload);
    }
}
