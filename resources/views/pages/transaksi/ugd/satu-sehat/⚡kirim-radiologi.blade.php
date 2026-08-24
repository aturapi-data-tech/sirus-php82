<?php
// resources/views/pages/transaksi/ugd/satu-sehat/kirim-radiologi.blade.php
// Step 8: Kirim Penunjang Radiologi UGD — ServiceRequest + DiagnosticReport (kode generik).
// BEDA RJ: order dari rstxn_ugdrads. ImagingStudy DILEWATI (master tanpa kode, no DICOM).

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\SATUSEHAT\ServiceRequestTrait;
use App\Support\PenanggungJawabPenunjang;
use App\Http\Traits\SATUSEHAT\DiagnosticReportTrait;
use App\Http\Traits\SATUSEHAT\ImagingStudyTrait;
use App\Http\Traits\SATUSEHAT\OrthancTrait;

new class extends Component {
    use EmrUGDTrait, ServiceRequestTrait, DiagnosticReportTrait, ImagingStudyTrait, OrthancTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat halaman belasan kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /** Order radiologi yang AKAN dikirim, dari tabel & kolom yang SAMA dengan kirim(). */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->rjNo)) {
            return [];
        }

        $order = DB::table('rstxn_ugdrads as a')
            ->leftJoin('rsmst_radiologis as m', 'a.rad_id', '=', 'm.rad_id')
            ->where('a.rj_no', $this->rjNo)
            ->orderBy('a.rad_dtl')
            ->get(['a.rad_dtl as urutan', 'a.rad_id', 'm.rad_desc']);

        $baris = [];
        foreach ($order as $satu) {
            $baris[] = [
                'label' => 'Pemeriksaan ' . $satu->urutan,
                'nilai' => (string) ($satu->rad_desc ?? '-'),
                'ket' => 'kode ' . ($satu->rad_id ?? '-'),
            ];
        }

        return $baris;
    }


    public function mount(?string $rjNo = null): void
    {
        $this->rjNo = $rjNo;
        $this->reloadState();
    }

    #[On('ugd-satu-sehat.refresh')]
    public function onRefresh(string $rjNo): void
    {
        if ((string) $this->rjNo !== $rjNo) {
            return;
        }
        $this->reloadState();
    }

    private function reloadState(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $data = $this->findDataUGD($this->rjNo);
        if (empty($data)) {
            return;
        }
        $satuSehat = $data['satusehat'] ?? [];
        $this->hasEncounter = !empty($satuSehat['encounterId']);
        $this->count = count($satuSehat['radDiagnosticReportIds'] ?? []);
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    /**
     * Pembungkus untuk rantai "Kirim Semua": apa pun hasilnya — berhasil, ditolak
     * SATUSEHAT, atau berhenti di guard — langkah ini WAJIB memberi kabar, supaya
     * orkestrator bisa melanjutkan. Tanpa ini rantai menggantung diam-diam pada
     * langkah pertama yang gagal, dan petugas cuma melihat modal yang membeku.
     */
    #[On('ss-radiologi-ugd.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('ugd-satu-sehat.langkah-selesai', langkah: 'radiologi');
    }

    public function kirimInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            if (empty($dataUGD)) { $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.'); return; }

            $satuSehat = $dataUGD['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['radDiagnosticReportIds']) || !empty($satuSehat['radServiceRequestIds'])) { $this->dispatch('toast', type: 'info', message: 'Radiologi sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataUGD['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataUGD['drId'] ?? '')->value('dr_uuid') ?? '');
            if (empty($practitionerId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong.'); return; }

            $orgId       = env('SATUSEHAT_ORGANIZATION_ID');
            $encounterId = $satuSehat['encounterId'];
            $drDesc      = $dataUGD['drDesc'] ?? '';

            // performer ServiceRequest = dokter penanggung jawab unit penunjang.
            // Array kosong bila dr_uuid-nya belum diisi; ServiceRequestTrait lalu
            // memakai dokter pengirim sebagai pengganti (lihat catatan di sana).
            $pjPenunjang = PenanggungJawabPenunjang::practitionerRef(PenanggungJawabPenunjang::POLI_RADIOLOGI);
            $waktu        = $this->parseDate($dataUGD['rjDate'] ?? '')->toIso8601String();

            $orders = DB::table('rstxn_ugdrads as a')
                ->leftJoin('rsmst_radiologis as m', 'a.rad_id', '=', 'm.rad_id')
                ->where('a.rj_no', $rjNo)
                ->select('a.rad_dtl', 'a.rad_id', 'm.rad_desc', 'a.radnum_no', 'a.rad_upload_pdf_foto', 'a.study_uid')
                ->get();

            $regNo   = $dataUGD['regNo'] ?? '';
            $regName = $dataUGD['regName'] ?? '';
            if ($orders->isEmpty()) { $this->dispatch('toast', type: 'error', message: 'Tidak ada order radiologi UGD untuk dikirim.'); return; }

            $satuSehat['radServiceRequestIds']   = $satuSehat['radServiceRequestIds']   ?? [];
            $satuSehat['radDiagnosticReportIds'] = $satuSehat['radDiagnosticReportIds'] ?? [];

            foreach ($orders as $order) {
                $nomorDetail  = trim((string) ($order->rad_dtl ?? ''));
                $deskripsi = trim((string) ($order->rad_desc ?? 'Pemeriksaan Radiologi'));
                $key  = "{$rjNo}-{$nomorDetail}";

                $serviceRequest = $this->postServiceRequest([
                    'identifier' => ['system' => "http://sys-ids.kemkes.go.id/servicerequest/{$orgId}", 'value' => "ugd-rad-{$key}"],
                    'status' => 'active', 'intent' => 'original-order', 'priority' => 'routine',
                    'category' => ['system' => 'http://snomed.info/sct', 'code' => '363679005', 'display' => 'Imaging'],
                    'code' => ['system' => 'http://loinc.org', 'code' => '18748-4', 'display' => $deskripsi],
                    'subject' => "Patient/{$patientId}", 'encounter' => "Encounter/{$encounterId}",
                    'occurrenceDateTime' => $waktu, 'authoredOn' => $waktu,
                    'requester' => "Practitioner/{$practitionerId}", 'requesterDisplay' => $drDesc,
                    'performer' => $pjPenunjang['reference'] ?? null,
                    'performerDisplay' => $pjPenunjang['display'] ?? null,
                ]);
                $serviceRequestId = $serviceRequest['id'] ?? null;
                if (empty($serviceRequestId)) { continue; }
                $satuSehat['radServiceRequestIds'][] = $serviceRequestId;

                $dokter = $this->createDiagnosticReport([
                    'identifier' => [['system' => "http://sys-ids.kemkes.go.id/diagnostic/{$orgId}", 'use' => 'official', 'value' => "ugd-rad-{$key}"]],
                    'status' => 'final', 'categoryCode' => 'RAD', 'categoryDisplay' => 'Radiology',
                    'codeSystem' => 'http://loinc.org', 'code' => '18748-4', 'display' => $deskripsi,
                    'patientId' => $patientId, 'encounterId' => $encounterId,
                    'effectiveDate' => $waktu, 'issued' => $waktu,
                    'performer' => ["Practitioner/{$practitionerId}"], 'basedOn' => [$serviceRequestId],
                ]);
                if (!empty($dokter['id'])) { $satuSehat['radDiagnosticReportIds'][] = $dokter['id']; }

                // 3) ImagingStudy — kirim kalau ada file foto/PDF yang sudah diupload.
                $fileFoto = $order->rad_upload_pdf_foto ?? '';
                $radnumNo = $order->radnum_no ?? '';
                if (!empty($fileFoto) && !empty($radnumNo)) {
                    $modalitas = $this->modalitasDariDeskripsi($deskripsi);

                    $studyUid = $this->prosesOrthanc(
                        'rstxn_ugdrads',
                        ['rj_no' => $rjNo, 'rad_dtl' => $nomorDetail],
                        [
                            'radnum_no'          => $radnumNo,
                            'rad_upload_pdf_foto' => $fileFoto,
                            'rad_desc'           => $deskripsi,
                            'reg_no'             => $regNo,
                            'reg_name'           => $regName,
                            'modality'           => $modalitas['code'],
                        ]
                    );

                    $imagingStudy = $this->postImagingStudy([
                        'kunci'            => "ugd-rad-{$key}",
                        'studyUid'         => $studyUid,
                        'patientId'        => $patientId,
                        'encounterId'      => $encounterId,
                        'started'          => $waktu,
                        'modalityCode'     => $modalitas['code'],
                        'modalityDisplay'  => $modalitas['display'],
                        'procedureCode'    => '18748-4',
                        'procedureDisplay' => $deskripsi,
                        'referrerId'       => $practitionerId,
                        'basedOn'          => $serviceRequestId,
                        'description'      => $deskripsi,
                    ]);
                    if (!empty($imagingStudy['id'])) {
                        $satuSehat['radImagingStudyIds']   = $satuSehat['radImagingStudyIds'] ?? [];
                        $satuSehat['radImagingStudyIds'][] = $imagingStudy['id'];
                    }
                }
            }

            if (empty($satuSehat['radServiceRequestIds'])) { $this->dispatch('toast', type: 'error', message: 'Tidak ada order radiologi yang bisa dikirim.'); return; }

            $this->saveResult($rjNo, $satuSehat);
            $srCount = count($satuSehat['radServiceRequestIds']);
            $drCount = count($satuSehat['radDiagnosticReportIds']);
            $isCount = count($satuSehat['radImagingStudyIds'] ?? []);
            $isInfo  = $isCount > 0 ? ", {$isCount} ImagingStudy" : ' (ImagingStudy dilewati — belum ada foto)';
            $this->dispatch('toast', type: 'success', message: "Radiologi terkirim: {$srCount} order, {$drCount} laporan{$isInfo}.");
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            // Simpan dulu yang sudah TERLANJUR terbentuk di SATUSEHAT sebelum melapor
            // gagal. Tanpa ini id-nya hangus padahal resource-nya SUDAH ada di sana,
            // lalu percobaan berikutnya menumpuk resource yatim — persis penyebab
            // diagnosa macet permanen dulu (lihat sender Condition). Dibungkus try
            // sendiri supaya kegagalan menyimpan tidak menutupi error aslinya.
            try { if (isset($satuSehat)) { $this->saveResult($rjNo, $satuSehat); } } catch (\Throwable) {}
            $this->dispatch('toast', type: 'error', message: 'Radiologi gagal: ' . $e->getMessage());
        }
    }

    private function getPatientIHS(string $regNo): string
    {
        if (empty($regNo)) return '';
        return (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '');
    }

    private function saveResult(string $rjNo, array $satuSehat): void
    {
        DB::transaction(function () use ($rjNo, $satuSehat) {
            $this->lockUGDRow($rjNo);
            $data = $this->findDataUGD($rjNo);
            $data['satusehat'] = $satuSehat;
            $this->updateJsonUGD((int) $rjNo, $data);
        });
    }

    private function parseDate(string $teksTanggal): Carbon
    {
        if (empty($teksTanggal)) return Carbon::now();
        try { return Carbon::createFromFormat('d/m/Y H:i:s', $teksTanggal); } catch (\Throwable) {
            try { return Carbon::parse($teksTanggal); } catch (\Throwable) { return Carbon::now(); }
        }
    }
};
?>

<div class="p-4 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ $count > 0 ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">8</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Penunjang Radiologi</div>
                <div class="text-xs text-muted dark:text-gray-400">ServiceRequest + DiagnosticReport + ImagingStudy (kalau ada foto).</div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        {{ $count }} laporan terkirim
                    </div>
                @endif
            </div>
        </div>
        <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter"
            class="!bg-teal-600 hover:!bg-teal-700 {{ $count > 0 ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="kirimForCurrent,kirim">
                    <span class="inline-flex items-center gap-1.5">
                        <x-satu-sehat.ikon-tombol :selesai="$count > 0" jenis="kirim" />
                        {{ $count > 0 ? 'Terkirim' : 'Kirim' }}
                    </span>
                </span>
            <span wire:loading wire:target="kirimForCurrent,kirim"><x-loading />...</span>
        </x-primary-button>
    </div>

    <x-satu-sehat.pratinjau :terbuka="$pratinjauTerbuka"
        :baris="$pratinjauTerbuka ? $this->pratinjau : []"
        kosong="Belum ada order radiologi untuk kunjungan ini — Kirim akan ditolak." />
</div>
