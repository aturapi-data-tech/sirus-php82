<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-lab.blade.php
// Kirim Penunjang Lab RI — ServiceRequest → Specimen → Observation → DiagnosticReport.
// Sumber: lbtxn_checkuphdrs.status_rjri='RI', ref_no=rihdr_no, checkup_status<>'P'.
// Item tanpa LOINC (lbmst_clabitems.loinc_code) di-skip.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\ServiceRequestTrait;
use App\Support\PenanggungJawabPenunjang;
use App\Http\Traits\SATUSEHAT\SpecimenTrait;
use App\Http\Traits\SATUSEHAT\ObservationTrait;
use App\Http\Traits\SATUSEHAT\DiagnosticReportTrait;

new class extends Component {
    use EmrRITrait, ServiceRequestTrait, SpecimenTrait, ObservationTrait, DiagnosticReportTrait;

    public ?string $riHdrNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat halaman belasan kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /**
     * Paket lab yang AKAN dikirim, memakai kriteria yang SAMA dengan kirim():
     * paket milik kunjungan ini yang statusnya BUKAN 'P' (masih proses).
     * Item tanpa LOINC ikut dihitung karena kirim() melewatinya diam-diam —
     * itu justru yang perlu dilihat petugas sebelum menekan tombol.
     */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->riHdrNo)) {
            return [];
        }

        $paket = DB::table('lbtxn_checkuphdrs')
            ->where('ref_no', $this->riHdrNo)
            ->where('status_rjri', 'RI')
            ->where('checkup_status', '<>', 'P')
            ->orderBy('checkup_no')
            ->get(['checkup_no', 'checkup_date']);

        $baris = [];
        foreach ($paket as $satu) {
            $item = DB::table('lbtxn_checkupdtls as b')
                ->join('lbmst_clabitems as d', 'b.clabitem_id', '=', 'd.clabitem_id')
                ->where('b.checkup_no', $satu->checkup_no)
                ->whereRaw("nvl(d.hidden_status,'N') = 'N'")
                ->whereRaw("nvl(d.is_group,'N') <> 'Y'")
                ->get(['d.loinc_code']);

            $berLoinc = $item->filter(fn ($x) => trim((string) ($x->loinc_code ?? '')) !== '')->count();
            $tanpa = $item->count() - $berLoinc;

            $baris[] = [
                'label' => 'Paket ' . $satu->checkup_no,
                'nilai' => $berLoinc . ' pemeriksaan ber-LOINC',
                'ket' => trim(($satu->checkup_date ?? '') . ($tanpa > 0 ? " · {$tanpa} item tanpa LOINC DILEWATI" : '')),
            ];
        }

        return $baris;
    }


    public function mount(?string $riHdrNo = null): void
    {
        $this->riHdrNo = $riHdrNo;
        $this->reloadState();
    }

    #[On('ri-satu-sehat.refresh')]
    public function onRefresh(string $riHdrNo): void
    {
        if ((string) $this->riHdrNo !== $riHdrNo) {
            return;
        }
        $this->reloadState();
    }

    private function reloadState(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $data = $this->findDataRI($this->riHdrNo);
        if (empty($data)) {
            return;
        }
        $satuSehat = $data['satusehat'] ?? [];
        $this->hasEncounter = !empty($satuSehat['encounterId']);
        $this->count = count($satuSehat['labDiagnosticReportIds'] ?? []);
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->kirim($this->riHdrNo);
        $this->reloadState();
    }

    /**
     * Pembungkus untuk rantai "Kirim Semua": apa pun hasilnya — berhasil, ditolak
     * SATUSEHAT, atau berhenti di guard — langkah ini WAJIB memberi kabar, supaya
     * orkestrator bisa melanjutkan. Tanpa ini rantai menggantung diam-diam pada
     * langkah pertama yang gagal, dan petugas cuma melihat modal yang membeku.
     */
    #[On('ss-lab-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        $this->kirimInti($riHdrNo);
        $this->dispatch('ri-satu-sehat.langkah-selesai', langkah: 'lab');
    }

    public function kirimInti(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $satuSehat = $dataRI['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['labDiagnosticReportIds'])) { $this->dispatch('toast', type: 'info', message: 'Lab sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRI['drId'] ?? '')->value('dr_uuid') ?? '');
            if (empty($practitionerId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong.'); return; }

            $orgId       = env('SATUSEHAT_ORGANIZATION_ID');
            $encounterId = $satuSehat['encounterId'];
            $drDesc      = $dataRI['drDesc'] ?? '';

            // performer ServiceRequest = dokter penanggung jawab unit penunjang.
            // Array kosong bila dr_uuid-nya belum diisi; ServiceRequestTrait lalu
            // memakai dokter pengirim sebagai pengganti (lihat catatan di sana).
            $pjPenunjang = PenanggungJawabPenunjang::practitionerRef(PenanggungJawabPenunjang::POLI_LABORATORIUM);

            $checkups = DB::table('lbtxn_checkuphdrs')
                ->where('ref_no', $riHdrNo)
                ->where('status_rjri', 'RI')
                ->where('checkup_status', '<>', 'P')
                ->select('checkup_no', 'checkup_date')
                ->get();
            if ($checkups->isEmpty()) { $this->dispatch('toast', type: 'error', message: 'Tidak ada hasil lab RI (paket selesai) untuk dikirim.'); return; }

            $satuSehat['labServiceRequestIds']   = $satuSehat['labServiceRequestIds']   ?? [];
            $satuSehat['labSpecimenIds']         = $satuSehat['labSpecimenIds']         ?? [];
            $satuSehat['labObservationIds']      = $satuSehat['labObservationIds']      ?? [];
            $satuSehat['labDiagnosticReportIds'] = $satuSehat['labDiagnosticReportIds'] ?? [];

            $skippedNoLoinc = 0;
            $totalObs = 0;

            foreach ($checkups as $checkup) {
                $checkupNo = trim((string) $checkup->checkup_no);
                $waktu = $this->parseDate($checkup->checkup_date ?? ($dataRI['entryDate'] ?? ''))->toIso8601String();

                $itemList = DB::table('lbtxn_checkupdtls as b')
                    ->join('lbmst_clabitems as d', 'b.clabitem_id', '=', 'd.clabitem_id')
                    ->where('b.checkup_no', $checkup->checkup_no)
                    ->whereRaw("nvl(d.hidden_status,'N') = 'N'")
                    ->whereRaw("nvl(d.is_group,'N') <> 'Y'")
                    ->select('d.clabitem_desc', 'd.loinc_code', 'd.loinc_display', 'd.unit_desc', 'b.lab_result')
                    ->get();

                $itemsWithLoinc = $itemList->filter(fn ($itemLab) => trim((string) ($itemLab->loinc_code ?? '')) !== '');
                $skippedNoLoinc += $itemList->count() - $itemsWithLoinc->count();
                if ($itemsWithLoinc->isEmpty()) { continue; }

                $serviceRequest = $this->postServiceRequest([
                    'identifier' => ['system' => "http://sys-ids.kemkes.go.id/servicerequest/{$orgId}", 'value' => "ri-{$riHdrNo}-{$checkupNo}"],
                    'status' => 'active', 'intent' => 'original-order', 'priority' => 'routine',
                    'category' => ['system' => 'http://snomed.info/sct', 'code' => '108252007', 'display' => 'Laboratory procedure'],
                    'code' => ['system' => 'http://loinc.org', 'code' => '26436-6', 'display' => 'Laboratory studies'],
                    'subject' => "Patient/{$patientId}", 'encounter' => "Encounter/{$encounterId}",
                    'occurrenceDateTime' => $waktu, 'authoredOn' => $waktu,
                    'requester' => "Practitioner/{$practitionerId}", 'requesterDisplay' => $drDesc,
                    'performer' => $pjPenunjang['reference'] ?? null,
                    'performerDisplay' => $pjPenunjang['display'] ?? null,
                ]);
                $serviceRequestId = $serviceRequest['id'] ?? null;
                if (empty($serviceRequestId)) { continue; }
                $satuSehat['labServiceRequestIds'][] = $serviceRequestId;

                $specimen = $this->postSpecimen([
                    'identifier' => ['system' => "http://sys-ids.kemkes.go.id/specimen/{$orgId}", 'value' => "ri-{$riHdrNo}-{$checkupNo}", 'assigner' => "Organization/{$orgId}"],
                    'status' => 'available', 'subject' => "Patient/{$patientId}",
                    'type' => ['system' => 'http://snomed.info/sct', 'code' => '119297000', 'display' => 'Blood specimen'],
                    'collection' => ['collectedDateTime' => $waktu, 'method' => ['system' => 'http://snomed.info/sct', 'code' => '129300006', 'display' => 'Puncture - action']],
                    'receivedTime' => $waktu, 'request' => ["ServiceRequest/{$serviceRequestId}"],
                ]);
                $specimenId = $specimen['id'] ?? null;
                if (!empty($specimenId)) { $satuSehat['labSpecimenIds'][] = $specimenId; }

                $obsIdsThisPaket = [];
                foreach ($itemsWithLoinc as $item) {
                    $loinc = trim((string) $item->loinc_code);
                    $result = trim((string) ($item->lab_result ?? ''));
                    if ($result === '') { continue; }

                    $obsData = [
                        'patientId' => $patientId, 'encounterId' => $encounterId, 'performerId' => $practitionerId,
                        'effectiveDate' => $waktu,
                        'category' => [['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/observation-category', 'code' => 'laboratory', 'display' => 'Laboratory']]]],
                        'code' => ['system' => 'http://loinc.org', 'code' => $loinc, 'display' => $item->loinc_display ?: $item->clabitem_desc],
                    ];
                    if (is_numeric(str_replace(',', '.', $result))) {
                        $unit = trim((string) ($item->unit_desc ?? '')) ?: '1';
                        $obsData['valueQuantity'] = ['value' => (float) str_replace(',', '.', $result), 'unit' => $unit, 'system' => 'http://unitsofmeasure.org', 'code' => $unit];
                    } else {
                        $obsData['valueString'] = $result;
                    }

                    $observation = $this->createObservation($obsData);
                    if (!empty($observation['id'])) { $obsIdsThisPaket[] = $observation['id']; $satuSehat['labObservationIds'][] = $observation['id']; $totalObs++; }
                }

                if (empty($obsIdsThisPaket)) { continue; }

                $dokter = $this->createDiagnosticReport([
                    'identifier' => [['system' => "http://sys-ids.kemkes.go.id/diagnostic/{$orgId}", 'use' => 'official', 'value' => "ri-{$riHdrNo}-{$checkupNo}"]],
                    'status' => 'final', 'categoryCode' => 'LAB', 'categoryDisplay' => 'Laboratory',
                    'codeSystem' => 'http://loinc.org', 'code' => '26436-6', 'display' => 'Laboratory studies',
                    'patientId' => $patientId, 'encounterId' => $encounterId,
                    'effectiveDate' => $waktu, 'issued' => $waktu,
                    'performer' => ["Practitioner/{$practitionerId}"],
                    'specimen' => $specimenId ? ["Specimen/{$specimenId}"] : [],
                    'observationIds' => $obsIdsThisPaket, 'basedOn' => [$serviceRequestId],
                ]);
                if (!empty($dokter['id'])) { $satuSehat['labDiagnosticReportIds'][] = $dokter['id']; }
            }

            if (empty($satuSehat['labDiagnosticReportIds'])) {
                $pesan = $skippedNoLoinc > 0
                    ? "Gagal: {$skippedNoLoinc} item lab belum punya kode LOINC di Master Lab."
                    : 'Tidak ada hasil lab yang bisa dikirim.';
                $this->dispatch('toast', type: 'error', message: $pesan);
                return;
            }

            $this->saveResult($riHdrNo, $satuSehat);
            $drCount = count($satuSehat['labDiagnosticReportIds']);
            $note = $skippedNoLoinc > 0 ? " ({$skippedNoLoinc} item tanpa LOINC dilewati)" : '';
            $this->dispatch('toast', type: 'success', message: "Lab terkirim: {$drCount} laporan, {$totalObs} observasi{$note}.");
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            // Simpan dulu yang sudah TERLANJUR terbentuk di SATUSEHAT sebelum melapor
            // gagal. Tanpa ini id-nya hangus padahal resource-nya SUDAH ada di sana,
            // lalu percobaan berikutnya menumpuk resource yatim — persis penyebab
            // diagnosa macet permanen dulu (lihat sender Condition). Dibungkus try
            // sendiri supaya kegagalan menyimpan tidak menutupi error aslinya.
            try { if (isset($satuSehat)) { $this->saveResult($riHdrNo, $satuSehat); } } catch (\Throwable) {}
            $this->dispatch('toast', type: 'error', message: 'Lab gagal: ' . $e->getMessage());
        }
    }

    private function getPatientIHS(string $regNo): string
    {
        if (empty($regNo)) return '';
        return (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '');
    }

    private function saveResult(string $riHdrNo, array $satuSehat): void
    {
        DB::transaction(function () use ($riHdrNo, $satuSehat) {
            $this->lockRIRow($riHdrNo);
            $data = $this->findDataRI($riHdrNo);
            $data['satusehat'] = $satuSehat;
            $this->updateJsonRI((int) $riHdrNo, $data);
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
                <div class="font-semibold text-ink dark:text-gray-100">Penunjang Lab</div>
                <div class="text-xs text-muted dark:text-gray-400">ServiceRequest · Specimen · Observation · DiagnosticReport.</div>
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
        kosong="Belum ada paket lab selesai (checkup_status masih P) — Kirim akan ditolak." />
</div>
