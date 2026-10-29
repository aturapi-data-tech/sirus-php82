<?php

namespace App\Http\Traits\SATUSEHAT;

use Illuminate\Support\Facades\DB;

/**
 * Tautan Rawat Jalan → IGD di SATUSEHAT — pasangan RujukanRawatInapTrait (IGD → RI).
 *
 * Polanya: unit TUJUAN mengecek asalnya saat membuat Encounter. Encounter UGD mencari tautan
 * transfer kasir `rstxn_ugdbiayaselamadirjs` (rj_no RJ → rj_no_rsugd UGD, ditulis
 * transfer-rj-ke-ugd-actions). Ada → ServiceRequest rujukan ke IGD di Encounter RJ, lalu
 * Encounter UGD menunjuknya lewat basedOn. Tidak ada → Encounter UGD biasa.
 *
 * Kode: category 3457005 Patient referral (sama dengan rujukan internal Tabel 27 Playbook IGD),
 * code 306104004 Referral to accident and emergency service — diverifikasi tx.fhir.org
 * 2026-10-29. Tabel 27 Kemkes TIDAK punya entri "ke IGD", jadi kode ini SNOMED umum, bukan
 * dari tabel Kemkes.
 *
 * Host WAJIB memakai EmrRJTrait (findDataRJ, lockRJRow, updateJsonRJ).
 */
trait RujukanIgdTrait
{
    use ServiceRequestTrait;

    /**
     * Cari (atau buat bila belum ada) ServiceRequest rujukan ke IGD dari RJ asal kunjungan UGD ini.
     *
     * @param  string $waktuMasukUgdIso  jam pasien tiba di IGD — occurrence & authoredOn SR
     * @param  string $dokterUgdId       IHS dokter UGD → performer (yang menerima rujukan)
     * @return array{id: ?string, rjNo: ?string, catatan: string}
     *         id null + catatan kosong = bukan pasien dari RJ (Encounter biasa);
     *         id null + catatan terisi = dari RJ tapi tautan belum bisa dibuat (dilaporkan).
     */
    protected function rujukanIgdDariRj(string $ugdRjNo, string $waktuMasukUgdIso, string $dokterUgdId = '', string $dokterUgdNama = ''): array
    {
        $rjNo = DB::table('rstxn_ugdbiayaselamadirjs')->where('rj_no_rsugd', $ugdRjNo)->value('rj_no');
        if (blank($rjNo)) {
            return ['id' => null, 'rjNo' => null, 'catatan' => ''];
        }
        $rjNo = (string) $rjNo;

        $dataRJ = $this->findDataRJ($rjNo);
        $satuSehatRj = $dataRJ['satusehat'] ?? [];

        // Sudah pernah dibuat (oleh kiriman UGD sebelumnya) → pakai ulang, jangan POST lagi.
        if (!empty($satuSehatRj['rujukanIgdId'])) {
            return ['id' => (string) $satuSehatRj['rujukanIgdId'], 'rjNo' => $rjNo, 'catatan' => ''];
        }

        // SR harus menempel ke Encounter RJ; belum dikirim = tak bisa ditautkan sekarang.
        if (empty($satuSehatRj['encounterId'])) {
            return ['id' => null, 'rjNo' => $rjNo,
                'catatan' => "pasien pindahan RJ #{$rjNo}, tapi Encounter RJ-nya belum dikirim — tautan RJ→IGD belum dibuat (kirim Encounter RJ, lalu Finish Encounter UGD memasangnya)"];
        }

        $regNo = (string) ($dataRJ['regNo'] ?? '');
        $patientId = $regNo !== '' ? (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '') : '';
        $dokterRjId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRJ['drId'] ?? '')->value('dr_uuid') ?? '');
        if ($patientId === '' || $dokterRjId === '') {
            return ['id' => null, 'rjNo' => $rjNo,
                'catatan' => "pasien pindahan RJ #{$rjNo}, tapi IHS pasien / dokter RJ kosong — tautan RJ→IGD belum dibuat"];
        }

        $respons = $this->postServiceRequest([
            'identifier' => ['system' => 'http://sys-ids.kemkes.go.id/servicerequest/' . env('SATUSEHAT_ORGANIZATION_ID'), 'value' => 'RJ-' . $rjNo . '-IGD'],
            'status' => 'active', 'intent' => 'original-order', 'priority' => 'urgent',
            'category' => ['system' => 'http://snomed.info/sct', 'code' => '3457005', 'display' => 'Patient referral'],
            'code' => ['system' => 'http://snomed.info/sct', 'code' => '306104004', 'display' => 'Referral to accident and emergency service'],
            'codeText' => 'Dipindahkan dari Rawat Jalan ke IGD — UGD #' . $ugdRjNo,
            'subject' => "Patient/{$patientId}", 'encounter' => "Encounter/{$satuSehatRj['encounterId']}",
            'occurrenceDateTime' => $waktuMasukUgdIso, 'authoredOn' => $waktuMasukUgdIso,
            'requester' => "Practitioner/{$dokterRjId}", 'requesterDisplay' => (string) ($dataRJ['drDesc'] ?? ''),
            // performer = dokter UGD yang menerima; tak ada → buildServiceRequest memakai requester.
            'performer' => $dokterUgdId !== '' ? "Practitioner/{$dokterUgdId}" : null,
            'performerDisplay' => $dokterUgdId !== '' ? $dokterUgdNama : null,
        ]);
        if (empty($respons['id'])) {
            return ['id' => null, 'rjNo' => $rjNo, 'catatan' => 'ServiceRequest rujukan ke IGD gagal: respons tanpa id'];
        }

        // Simpan di dokumen RJ (pemilik SR) — kiriman UGD berikutnya memakai ulang, tak POST dobel.
        DB::transaction(function () use ($rjNo, $respons) {
            $this->lockRJRow($rjNo);
            $data = $this->findDataRJ($rjNo);
            $data['satusehat']['rujukanIgdId'] = $respons['id'];
            $this->updateJsonRJ((int) $rjNo, $data);
        });

        return ['id' => (string) $respons['id'], 'rjNo' => $rjNo, 'catatan' => ''];
    }
}
