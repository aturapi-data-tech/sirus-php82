<?php

namespace App\Http\Traits\SATUSEHAT;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

trait OrthancTrait
{
    protected function orthancClient()
    {
        return Http::timeout(10)
            ->withBasicAuth(env('ORTHANC_USER', 'sirus'), env('ORTHANC_PASSWORD', ''))
            ->baseUrl(env('ORTHANC_URL', 'http://localhost:8042'));
    }

    /**
     * Cari StudyInstanceUID di Orthanc berdasarkan AccessionNumber (= RADNUM_NO).
     * Return string UID atau null kalau tidak ditemukan.
     */
    public function cariStudyUid(string $accessionNumber): ?string
    {
        $response = $this->orthancClient()->post('/tools/find', [
            'Level' => 'Study',
            'Query' => ['AccessionNumber' => $accessionNumber],
        ]);

        if (!$response->successful()) {
            return null;
        }

        $ids = $response->json();
        if (empty($ids)) {
            return null;
        }

        $study = $this->orthancClient()->get("/studies/{$ids[0]}")->json();

        return $study['MainDicomTags']['StudyInstanceUID'] ?? null;
    }

    /**
     * Sinkron STUDY_UID dari Orthanc ke tabel order radiologi.
     * Dipanggil per-row: cari di Orthanc by RADNUM_NO, simpan hasilnya.
     *
     * @param string $tabel  RSTXN_RJRADS | RSTXN_UGDRADS | RSTXN_RIRADIOLOGS
     * @param array  $where  Primary key condition, misal ['rj_no' => '...', 'rad_dtl' => '...']
     * @param string $radnumNo  Nomor radiologi (AccessionNumber DICOM)
     * @return string|null  StudyInstanceUID kalau ketemu
     */
    public function sinkronStudyUid(string $tabel, array $where, string $radnumNo): ?string
    {
        if (empty($radnumNo)) {
            return null;
        }

        $uid = $this->cariStudyUid($radnumNo);

        if ($uid) {
            DB::table($tabel)->where($where)->update(['study_uid' => $uid]);
        }

        return $uid;
    }

    /**
     * Batch sinkron: ambil semua row yang punya RADNUM_NO tapi STUDY_UID masih kosong,
     * query Orthanc satu-satu, simpan yang ketemu.
     *
     * @return int Jumlah row yang berhasil disinkronkan
     */
    public function sinkronStudyUidBatch(string $tabel, string $pkRef, string $pkDtl, int $limit = 100): int
    {
        $rows = DB::table($tabel)
            ->whereNotNull('radnum_no')
            ->where('radnum_no', '!=', '')
            ->where(function ($q) {
                $q->whereNull('study_uid')->orWhere('study_uid', '');
            })
            ->limit($limit)
            ->select($pkRef, $pkDtl, 'radnum_no')
            ->get();

        $synced = 0;
        foreach ($rows as $row) {
            $uid = $this->sinkronStudyUid(
                $tabel,
                [$pkRef => $row->{$pkRef}, $pkDtl => $row->{$pkDtl}],
                $row->radnum_no
            );
            if ($uid) {
                $synced++;
            }
        }

        return $synced;
    }

    /**
     * Upload file (PDF/JPG) ke Orthanc via /tools/create-dicom.
     * Orthanc membungkus file menjadi DICOM Secondary Capture dan menerbitkan StudyInstanceUID.
     *
     * @param string $filePath   Nama file di storage (upload/penunjang/radiologi/{nama})
     * @param array  $tags       DICOM tag: PatientID, PatientName, AccessionNumber, StudyDescription, Modality
     * @return string|null       StudyInstanceUID kalau berhasil, null kalau gagal
     */
    public function uploadKeOrthanc(string $filePath, array $tags): ?string
    {
        $fullPath = 'upload/penunjang/radiologi/' . $filePath;

        if (str_contains($filePath, '/')) {
            if (!Storage::disk('public')->exists($filePath)) {
                return null;
            }
            $content = Storage::disk('public')->get($filePath);
        } else {
            if (!Storage::disk('local')->exists($fullPath)) {
                return null;
            }
            $content = Storage::disk('local')->get($fullPath);
        }

        $mime = str_ends_with(strtolower($filePath), '.pdf') ? 'application/pdf' : 'image/jpeg';
        $dataUri = 'data:' . $mime . ';base64,' . base64_encode($content);

        $response = $this->orthancClient()->post('/tools/create-dicom', [
            'Tags' => [
                'PatientID'        => $tags['PatientID'] ?? '',
                'PatientName'      => $tags['PatientName'] ?? '',
                'AccessionNumber'  => $tags['AccessionNumber'] ?? '',
                'StudyDescription' => $tags['StudyDescription'] ?? 'Radiologi',
                'Modality'         => $tags['Modality'] ?? 'OT',
            ],
            'Content' => $dataUri,
        ]);

        if (!$response->successful()) {
            return null;
        }

        $result = $response->json();
        $parentStudy = $result['ParentStudy'] ?? null;
        if (empty($parentStudy)) {
            return null;
        }

        $study = $this->orthancClient()->get("/studies/{$parentStudy}")->json();

        return $study['MainDicomTags']['StudyInstanceUID'] ?? null;
    }

    /**
     * Proses lengkap saat kirim SATUSEHAT: cek file → upload ke Orthanc → simpan STUDY_UID.
     *
     * @param string $tabel     Nama tabel order (rstxn_rjrads / rstxn_ugdrads / rstxn_riradiologs)
     * @param array  $where     PK condition
     * @param array  $row       Row data: radnum_no, rad_upload_pdf_foto, rad_desc, reg_no, reg_name
     * @return string|null      StudyInstanceUID (dari Orthanc atau derived)
     */
    public function prosesOrthanc(string $tabel, array $where, array $row): ?string
    {
        $radnumNo = $row['radnum_no'] ?? '';

        $existingUid = DB::table($tabel)->where($where)->value('study_uid');
        if (!empty($existingUid)) {
            return $existingUid;
        }

        $file = $row['rad_upload_pdf_foto'] ?? '';
        if (empty($file)) {
            return null;
        }

        if (empty($radnumNo)) {
            return null;
        }

        $uid = $this->uploadKeOrthanc($file, [
            'PatientID'        => $row['reg_no'] ?? '',
            'PatientName'      => $row['reg_name'] ?? '',
            'AccessionNumber'  => $radnumNo,
            'StudyDescription' => $row['rad_desc'] ?? 'Radiologi',
            'Modality'         => $row['modality'] ?? 'OT',
        ]);

        if ($uid) {
            DB::table($tabel)->where($where)->update(['study_uid' => $uid]);
        }

        return $uid;
    }
}
