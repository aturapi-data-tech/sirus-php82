<?php

namespace App\Http\Traits\SATUSEHAT;

use App\Support\RiwayatPenyakitKeluarga;

trait FamilyMemberHistoryTrait
{
    use SatuSehatTrait;

    /**
     * Riwayat penyakit keluarga (Playbook RJ bab 3e) — satu FamilyMemberHistory per baris
     * (hubungan + penyakit). Disamakan dengan alur Rawat Jalan apikesehatan.com: relationship
     * v3-RoleCode, condition[0].code SNOMED, keterangan → condition[0].onsetString.
     * FHIR R4 FamilyMemberHistory tidak punya elemen encounter.
     *
     * @param array $data patientId, hubungan (v3-RoleCode), snomedCode, snomedDisplay, teks,
     *                    keterangan?, tanggalIso
     */
    public function createFamilyMemberHistory(array $data): array
    {
        if (empty($data['snomedCode']) || empty($data['hubungan'])) {
            throw new \InvalidArgumentException('Hubungan keluarga & kode SNOMED penyakit wajib diisi.');
        }

        $kondisi = [
            'code' => [
                'coding' => [[
                    'system'  => 'http://snomed.info/sct',
                    'code'    => $data['snomedCode'],
                    'display' => $data['snomedDisplay'] ?? '',
                ]],
                'text' => ($data['teks'] ?? '') ?: ($data['snomedDisplay'] ?? ''),
            ],
        ];
        if (filled($data['keterangan'] ?? null)) {
            $kondisi['onsetString'] = $data['keterangan'];
        }

        $payload = [
            'resourceType' => 'FamilyMemberHistory',
            'status'       => 'completed',
            'patient'      => ['reference' => 'Patient/' . $data['patientId']],
            'date'         => $data['tanggalIso'],
            'relationship' => [
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/v3-RoleCode',
                    'code'    => $data['hubungan'],
                    'display' => RiwayatPenyakitKeluarga::HUBUNGAN[$data['hubungan']]['display'] ?? '',
                ]],
            ],
            'condition' => [$kondisi],
        ];

        return $this->makeRequest('post', 'FamilyMemberHistory', $payload);
    }
}
