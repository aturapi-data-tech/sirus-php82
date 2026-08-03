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

    /**
     * Siapkan Encounter untuk di-finish: status, period.end, statusHistory yang lengkap,
     * dan diagnosis. Dipakai bersama RJ/UGD/RI supaya aturannya tak beda-beda.
     *
     * Dua aturan SATUSEHAT yang gampang kena:
     *  - "every statusHistory period start and end must be filled (Rule 10122)" — entri
     *    yang ditulis createNewEncounter()/startRoomEncounter() hanya punya `start`,
     *    jadi `end` tiap entri diisi dari `start` entri BERIKUTNYA (entri terakhir
     *    memakai waktu selesai).
     *  - "Element not found: Encounter.diagnosis (RuleNumber: 10457)" — wajib merujuk
     *    Condition yang sudah dikirim; pemanggil harus menolak lebih dulu bila
     *    conditionIdList kosong, jangan mengirim tanpa diagnosis.
     *
     * @param  array  $encounter  hasil getEncounter()
     * @param  string $akhirIso   waktu pasien benar-benar selesai dilayani
     * @param  array  $conditionIdList  id Condition hasil kirim diagnosa
     */
    public function siapkanFinishEncounter(array $encounter, string $akhirIso, array $conditionIdList): array
    {
        $encounter['status'] = 'finished';
        $encounter['period']['end'] = $akhirIso;

        $riwayat = array_values(array_filter($encounter['statusHistory'] ?? [], 'is_array'));
        $riwayat[] = ['status' => 'finished', 'period' => ['start' => $akhirIso]];

        foreach ($riwayat as $indeks => $entri) {
            $mulai = $entri['period']['start'] ?? $akhirIso;
            $selesai = $entri['period']['end'] ?? null;
            if (empty($selesai)) {
                // Satu status berakhir saat status berikutnya dimulai.
                $selesai = $riwayat[$indeks + 1]['period']['start'] ?? $akhirIso;
            }
            // Jaga urutan: end tak boleh mendahului start (data waktu bisa tak rapi).
            $riwayat[$indeks]['period'] = ['start' => $mulai, 'end' => max($mulai, $selesai)];
        }
        $encounter['statusHistory'] = $riwayat;

        $encounter['diagnosis'] = [];
        foreach (array_values($conditionIdList) as $indeks => $conditionId) {
            $encounter['diagnosis'][] = [
                'condition' => ['reference' => "Condition/{$conditionId}"],
                'use' => [
                    'coding' => [[
                        'system' => 'http://terminology.hl7.org/CodeSystem/diagnosis-role',
                        'code' => 'DD',
                        'display' => 'Discharge diagnosis',
                    ]],
                ],
                'rank' => $indeks + 1,
            ];
        }

        return $encounter;
    }
}
