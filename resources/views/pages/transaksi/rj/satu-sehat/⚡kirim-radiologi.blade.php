<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-radiologi.blade.php
// Step 10: Kirim Penunjang Radiologi — ServiceRequest (order) + DiagnosticReport (pelaporan).
//
// GAP: master radiologi (rsmst_radiologis) TAK punya kode LOINC/ICD-9, hasil = PDF upload
//   (rsview_rads.rad_upload_pdf), dan tak ada DICOM → ImagingStudy DILEWATI.
// MVP: pakai kode generik "Diagnostic imaging study" (LOINC 18748-4) utk SR & DR;
//   DR minimal (basedOn SR, tanpa Observation) — perlu validasi sandbox.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\ServiceRequestTrait;
use App\Support\PenanggungJawabPenunjang;
use App\Http\Traits\SATUSEHAT\DiagnosticReportTrait;

new class extends Component {
    use EmrRJTrait, ServiceRequestTrait, DiagnosticReportTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;   // jumlah DiagnosticReport terkirim

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

        $order = DB::table('rstxn_rjrads as a')
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

    #[On('rj-satu-sehat.refresh')]
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
        $data = $this->findDataRJ($this->rjNo);
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
    #[On('ss-radiologi-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('rj-satu-sehat.langkah-selesai', langkah: 'radiologi');
    }

    public function kirimInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.'); return; }

            $satuSehat = $dataRJ['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['radDiagnosticReportIds'])) { $this->dispatch('toast', type: 'info', message: 'Radiologi sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRJ['drId'] ?? '')->value('dr_uuid') ?? '');
            if (empty($practitionerId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong.'); return; }

            $orgId       = env('SATUSEHAT_ORGANIZATION_ID');
            $encounterId = $satuSehat['encounterId'];
            $drDesc      = $dataRJ['drDesc'] ?? '';

            // performer ServiceRequest = dokter penanggung jawab unit penunjang.
            // Array kosong bila dr_uuid-nya belum diisi; ServiceRequestTrait lalu
            // memakai dokter pengirim sebagai pengganti (lihat catatan di sana).
            $pjPenunjang = PenanggungJawabPenunjang::practitionerRef(PenanggungJawabPenunjang::POLI_RADIOLOGI);
            $waktu        = $this->parseDate($dataRJ['rjDate'] ?? '')->toIso8601String();

            $orders = DB::table('rstxn_rjrads as a')
                ->leftJoin('rsmst_radiologis as m', 'a.rad_id', '=', 'm.rad_id')
                ->where('a.rj_no', $rjNo)
                ->select('a.rad_dtl', 'a.rad_id', 'm.rad_desc')
                ->get();
            if ($orders->isEmpty()) { $this->dispatch('toast', type: 'error', message: 'Tidak ada order radiologi untuk dikirim.'); return; }

            $satuSehat['radServiceRequestIds']   = $satuSehat['radServiceRequestIds']   ?? [];
            $satuSehat['radDiagnosticReportIds'] = $satuSehat['radDiagnosticReportIds'] ?? [];

            foreach ($orders as $order) {
                $nomorDetail  = trim((string) ($order->rad_dtl ?? ''));
                $deskripsi = trim((string) ($order->rad_desc ?? 'Pemeriksaan Radiologi'));
                $key  = "{$rjNo}-{$nomorDetail}";

                // 1) ServiceRequest — order radiologi (kode generik imaging).
                $serviceRequest = $this->postServiceRequest([
                    'identifier' => ['system' => "http://sys-ids.kemkes.go.id/servicerequest/{$orgId}", 'value' => "rad-{$key}"],
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

                // 2) DiagnosticReport — pelaporan (generik; hasil = PDF terlampir, tanpa ImagingStudy/Observation).
                $dokter = $this->createDiagnosticReport([
                    'identifier' => [['system' => "http://sys-ids.kemkes.go.id/diagnostic/{$orgId}", 'use' => 'official', 'value' => "rad-{$key}"]],
                    'status' => 'final', 'categoryCode' => 'RAD', 'categoryDisplay' => 'Radiology',
                    'codeSystem' => 'http://loinc.org', 'code' => '18748-4', 'display' => $deskripsi,
                    'patientId' => $patientId, 'encounterId' => $encounterId,
                    'effectiveDate' => $waktu, 'issued' => $waktu,
                    'performer' => ["Practitioner/{$practitionerId}"], 'basedOn' => [$serviceRequestId],
                ]);
                if (!empty($dokter['id'])) { $satuSehat['radDiagnosticReportIds'][] = $dokter['id']; }
            }

            if (empty($satuSehat['radServiceRequestIds'])) { $this->dispatch('toast', type: 'error', message: 'Tidak ada order radiologi yang bisa dikirim.'); return; }

            $this->saveResult($rjNo, $satuSehat);
            $srCount = count($satuSehat['radServiceRequestIds']);
            $drCount = count($satuSehat['radDiagnosticReportIds']);
            $this->dispatch('toast', type: 'success', message: "Radiologi terkirim: {$srCount} order, {$drCount} laporan (ImagingStudy dilewati — no DICOM).");
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
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
            $this->lockRJRow($rjNo);
            $data = $this->findDataRJ($rjNo);
            $data['satusehat'] = $satuSehat;
            $this->updateJsonRJ($rjNo, $data);
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
                <span class="text-sm font-bold">10</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Penunjang Radiologi</div>
                <div class="text-xs text-muted dark:text-gray-400">ServiceRequest + DiagnosticReport (ImagingStudy dilewati — no DICOM).</div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        {{ $count }} laporan terkirim
                    </div>
                @endif
            </div>
        </div>
        <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter"
            class="!bg-teal-600 hover:!bg-teal-700 {{ $count > 0 ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="kirimForCurrent">
                    <span class="inline-flex items-center gap-1.5">
                        <x-satu-sehat.ikon-tombol :selesai="$count > 0" jenis="kirim" />
                        {{ $count > 0 ? 'Terkirim' : 'Kirim' }}
                    </span>
                </span>
            <span wire:loading wire:target="kirimForCurrent"><x-loading />...</span>
        </x-primary-button>
    </div>

    <x-satu-sehat.pratinjau :terbuka="$pratinjauTerbuka"
        :baris="$pratinjauTerbuka ? $this->pratinjau : []"
        kosong="Belum ada order radiologi untuk kunjungan ini — Kirim akan ditolak." />
</div>
