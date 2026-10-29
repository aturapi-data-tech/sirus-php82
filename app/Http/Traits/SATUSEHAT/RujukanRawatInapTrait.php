<?php

namespace App\Http\Traits\SATUSEHAT;

use Illuminate\Support\Facades\DB;

/**
 * Tautan IGD → Rawat Inap di SATUSEHAT (PDF Petirs hal. 30; Tabel 27 Playbook IGD).
 *
 * IGD → RI WAJIB dua Encounter (EMER lalu IMP). Supaya pasien tidak "menghilang" di antara
 * keduanya, Encounter UGD memancarkan ServiceRequest rujukan internal Rawat Inap
 * (category 3457005 Patient referral, code 737481003 Inpatient care management — keduanya
 * diverifikasi tx.fhir.org 2026-10-29), lalu Encounter RI menunjuknya lewat basedOn.
 *
 * Sumber tautan = tabel transfer kasir `rstxn_ribiayaselamadugds` (rj_no UGD → ugd_no_rsri RI),
 * ditulis transfer-ugd-ke-ri-actions. Tak ada baris = pasien tidak dari UGD → Encounter biasa.
 *
 * Host WAJIB memakai EmrUGDTrait (findDataUGD, lockUGDRow, updateJsonUGD).
 */
trait RujukanRawatInapTrait
{
    use ServiceRequestTrait;

    /**
     * Cari (atau buat bila belum ada) ServiceRequest rujukan rawat inap dari UGD asal kunjungan RI ini.
     *
     * @param  string $waktuMasukRiIso  jam masuk RI (entryDate) — occurrence & authoredOn SR
     * @param  string $dpjpRiId         IHS DPJP RI → performer (yang menerima rujukan)
     * @return array{id: ?string, ugdRjNo: ?string, catatan: string}
     *         id null + catatan kosong = bukan pasien dari UGD (Encounter biasa);
     *         id null + catatan terisi = dari UGD tapi tautan belum bisa dibuat (dilaporkan).
     */
    protected function rujukanRawatInapDariUgd(string $riHdrNo, string $waktuMasukRiIso, string $dpjpRiId = '', string $dpjpRiNama = ''): array
    {
        $ugdRjNo = DB::table('rstxn_ribiayaselamadugds')->where('ugd_no_rsri', $riHdrNo)->value('rj_no');
        if (blank($ugdRjNo)) {
            return ['id' => null, 'ugdRjNo' => null, 'catatan' => ''];
        }
        $ugdRjNo = (string) $ugdRjNo;

        $dataUGD = $this->findDataUGD($ugdRjNo);
        $satuSehatUgd = $dataUGD['satusehat'] ?? [];

        // Sudah pernah dibuat (oleh kiriman RI sebelumnya) → pakai ulang, jangan POST lagi.
        if (!empty($satuSehatUgd['rujukanRawatInapId'])) {
            return ['id' => (string) $satuSehatUgd['rujukanRawatInapId'], 'ugdRjNo' => $ugdRjNo, 'catatan' => ''];
        }

        // SR harus menempel ke Encounter UGD; belum dikirim = tak bisa ditautkan sekarang.
        if (empty($satuSehatUgd['encounterId'])) {
            return ['id' => null, 'ugdRjNo' => $ugdRjNo,
                'catatan' => "pasien pindahan UGD #{$ugdRjNo}, tapi Encounter UGD-nya belum dikirim — tautan IGD→RI belum dibuat (kirim Encounter UGD, lalu Finish Encounter RI memasangnya)"];
        }

        $regNo = (string) ($dataUGD['regNo'] ?? '');
        $patientId = $regNo !== '' ? (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '') : '';
        $dokterUgdId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataUGD['drId'] ?? '')->value('dr_uuid') ?? '');
        if ($patientId === '' || $dokterUgdId === '') {
            return ['id' => null, 'ugdRjNo' => $ugdRjNo,
                'catatan' => "pasien pindahan UGD #{$ugdRjNo}, tapi IHS pasien / dokter UGD kosong — tautan IGD→RI belum dibuat"];
        }

        $respons = $this->postServiceRequest([
            'identifier' => ['system' => 'http://sys-ids.kemkes.go.id/servicerequest/' . env('SATUSEHAT_ORGANIZATION_ID'), 'value' => 'UGD-' . $ugdRjNo . '-RAWAT-INAP'],
            'status' => 'active', 'intent' => 'original-order', 'priority' => 'routine',
            'category' => ['system' => 'http://snomed.info/sct', 'code' => '3457005', 'display' => 'Patient referral'],
            'code' => ['system' => 'http://snomed.info/sct', 'code' => '737481003', 'display' => 'Inpatient care management'],
            'codeText' => 'Permintaan rawat inap dari IGD — RI #' . $riHdrNo,
            'subject' => "Patient/{$patientId}", 'encounter' => "Encounter/{$satuSehatUgd['encounterId']}",
            'occurrenceDateTime' => $waktuMasukRiIso, 'authoredOn' => $waktuMasukRiIso,
            'requester' => "Practitioner/{$dokterUgdId}", 'requesterDisplay' => (string) ($dataUGD['drDesc'] ?? ''),
            // performer = DPJP RI yang menerima; tak ada → buildServiceRequest memakai requester.
            'performer' => $dpjpRiId !== '' ? "Practitioner/{$dpjpRiId}" : null,
            'performerDisplay' => $dpjpRiId !== '' ? $dpjpRiNama : null,
        ]);
        if (empty($respons['id'])) {
            return ['id' => null, 'ugdRjNo' => $ugdRjNo, 'catatan' => 'ServiceRequest rujukan rawat inap gagal: respons tanpa id'];
        }

        // Simpan di dokumen UGD (pemilik SR) — kiriman RI berikutnya memakai ulang, tak POST dobel.
        DB::transaction(function () use ($ugdRjNo, $respons) {
            $this->lockUGDRow($ugdRjNo);
            $data = $this->findDataUGD($ugdRjNo);
            $data['satusehat']['rujukanRawatInapId'] = $respons['id'];
            $this->updateJsonUGD((int) $ugdRjNo, $data);
        });

        return ['id' => (string) $respons['id'], 'ugdRjNo' => $ugdRjNo, 'catatan' => ''];
    }
}
