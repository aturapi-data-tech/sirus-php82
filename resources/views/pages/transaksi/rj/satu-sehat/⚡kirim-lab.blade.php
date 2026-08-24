<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-lab.blade.php
// Step 9: Kirim Penunjang Lab — rantai ServiceRequest → Specimen → Observation(laboratory) → DiagnosticReport.
//
// Sumber (DB, bukan JSON): lbtxn_checkuphdrs(ref_no=rj_no) → lbtxn_checkupdtls(clabitem_id, lab_result)
//   → lbmst_clabitems(loinc_code, loinc_display, unit_desc). Satu paket checkup = satu SR + Specimen + DR;
//   tiap item ber-loinc_code + hasil = satu Observation. Item TANPA loinc_code di-skip (butuh diisi di Master Lab).
//
// ASUMSI MVP (perlu validasi sandbox): code panel SR/DR = LOINC generik 26436-6 (Laboratory studies);
//   Specimen = darah (SNOMED 119297000) metode venipuncture; nilai numerik → valueQuantity, selain itu valueString.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\ServiceRequestTrait;
use App\Support\PenanggungJawabPenunjang;
use App\Http\Traits\SATUSEHAT\SpecimenTrait;
use App\Http\Traits\SATUSEHAT\PenunjangKirimTrait;
use App\Http\Traits\SATUSEHAT\ObservationTrait;
use App\Http\Traits\SATUSEHAT\DiagnosticReportTrait;

new class extends Component {
    use EmrRJTrait, ServiceRequestTrait, SpecimenTrait, ObservationTrait, DiagnosticReportTrait, PenunjangKirimTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;   // jumlah DiagnosticReport terkirim

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
        if (empty($this->rjNo)) {
            return [];
        }

        $paket = DB::table('lbtxn_checkuphdrs')
            ->where('ref_no', $this->rjNo)
            ->where('status_rjri', 'RJ')
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
        $this->count = count($satuSehat['labDiagnosticReportIds'] ?? []);
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
    #[On('ss-lab-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('rj-satu-sehat.langkah-selesai', langkah: 'lab');
    }

    public function kirimInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.'); return; }

            $satuSehat = $dataRJ['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }

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
            $pjPenunjang = PenanggungJawabPenunjang::practitionerRef(PenanggungJawabPenunjang::POLI_LABORATORIUM);

            // Paket checkup lab internal RJ yang SUDAH ada hasilnya (checkup_status != 'P').
            $checkups = DB::table('lbtxn_checkuphdrs')
                ->where('ref_no', $rjNo)
                ->where('status_rjri', 'RJ')
                ->where('checkup_status', '<>', 'P')
                ->select('checkup_no', 'checkup_date')
                ->get();
            if ($checkups->isEmpty()) { $this->dispatch('toast', type: 'error', message: 'Tidak ada hasil lab (paket selesai) untuk dikirim.'); return; }

            $satuSehat['labServiceRequestIds']    = $satuSehat['labServiceRequestIds']    ?? [];
            $satuSehat['labSpecimenIds']          = $satuSehat['labSpecimenIds']          ?? [];
            $satuSehat['labObservationIds']       = $satuSehat['labObservationIds']       ?? [];
            $satuSehat['labDiagnosticReportIds']  = $satuSehat['labDiagnosticReportIds']  ?? [];

            // Indeks per-paket — penentu paket mana yang masih bolong. Lihat PenunjangKirimTrait.
            $sistemSr = "http://sys-ids.kemkes.go.id/servicerequest/{$orgId}";
            $sistemSp = "http://sys-ids.kemkes.go.id/specimen/{$orgId}";
            $sistemDr = "http://sys-ids.kemkes.go.id/diagnostic/{$orgId}/lab";
            // Sebelum RuleNumber 10432 identifier DiagnosticReport TANPA akhiran. Record yang
            // sudah terkirim memakai system lama itu, jadi pemulihan harus mencoba keduanya —
            // kalau tidak, DR lama tak ketemu lalu dibuatkan DR KEDUA di SATUSEHAT.
            $sistemDrLama = "http://sys-ids.kemkes.go.id/diagnostic/{$orgId}";
            $indeks   = $this->indeksKirim($satuSehat, 'labKirim');
            $pulihkan = $this->perluPulihIndeks($satuSehat, 'labKirim', ['labServiceRequestIds', 'labDiagnosticReportIds']);

            $skippedNoLoinc = 0;
            $totalObs = 0;
            $paketBaru     = 0;   // paket yang benar-benar dikirim putaran ini
            $disusul       = 0;   // paket lama yang bagian bolongnya baru dilengkapi sekarang
            $tuntas        = 0;   // paket yang memang sudah lengkap — dilewati tanpa memanggil API
            $gagalSr       = 0;   // ServiceRequest tak terbentuk → paket ini tak bisa dilanjut
            $takTerpetakan = 0;   // paket lama yang daftar Observation-nya tak diketahui

            foreach ($checkups as $checkup) {
                $checkupNo = trim((string) $checkup->checkup_no);
                $waktu = $this->parseDate($checkup->checkup_date ?? ($dataRJ['rjDate'] ?? ''))->toIso8601String();

                // Item hasil ber-loinc (kecuali group header).
                $itemList = DB::table('lbtxn_checkupdtls as b')
                    ->join('lbmst_clabitems as d', 'b.clabitem_id', '=', 'd.clabitem_id')
                    ->where('b.checkup_no', $checkup->checkup_no)
                    ->whereRaw("nvl(d.hidden_status,'N') = 'N'")
                    ->whereRaw("nvl(d.is_group,'N') <> 'Y'")
                    ->select('d.clabitem_desc', 'd.loinc_code', 'd.loinc_display', 'd.unit_desc', 'b.lab_result')
                    ->get();

                $itemsWithLoinc = $itemList->filter(fn ($itemLab) => trim((string) ($itemLab->loinc_code ?? '')) !== '');
                $skippedNoLoinc += $itemList->count() - $itemsWithLoinc->count();
                if ($itemsWithLoinc->isEmpty()) { continue; }   // paket ini tak punya item ber-LOINC

                $kunciOrder = "{$rjNo}-{$checkupNo}";

                // Record lama belum punya indeks — pulihkan id yang SUDAH ada di SATUSEHAT
                // lewat identifier, supaya yang tersisa saja yang dikirim ulang.
                if ($pulihkan) {
                    $this->catatKirim($indeks, $kunciOrder, 'sr', $this->cariIdLewatIdentifier('ServiceRequest', $sistemSr, $kunciOrder));
                    $this->catatKirim($indeks, $kunciOrder, 'sp', $this->cariIdLewatIdentifier('Specimen', $sistemSp, $kunciOrder));
                    $this->catatKirim($indeks, $kunciOrder, 'dr',
                        $this->cariIdLewatIdentifier('DiagnosticReport', $sistemDr, $kunciOrder)
                        ?? $this->cariIdLewatIdentifier('DiagnosticReport', $sistemDrLama, $kunciOrder));
                }

                if ($this->orderTuntas($indeks, $kunciOrder, ['sr', 'dr'])) { $tuntas++; continue; }

                $srTersimpan  = $this->idKirim($indeks, $kunciOrder, 'sr');
                $obsTersimpan = $this->daftarIdKirim($indeks, $kunciOrder, 'obs');

                // Paket yang ServiceRequest-nya sudah ada tapi daftar Observation-nya TAK
                // diketahui tak bisa dilanjutkan dengan aman: Observation tak punya identifier,
                // jadi mengirim ulang menggandakan hasil lab di SATUSEHAT — lebih merusak
                // daripada menunda. Record lama selalu masuk kategori ini, dan sikapnya sama
                // dengan guard lama, jadi tak pernah lebih buruk dari sebelumnya.
                if (filled($srTersimpan) && empty($obsTersimpan)) { $takTerpetakan++; continue; }

                $sudahAdaSebagian = filled($srTersimpan);

                // 1) ServiceRequest (order) — code panel LOINC generik.
                $serviceRequestId = $srTersimpan;
                if (empty($serviceRequestId)) {
                    $serviceRequest = $this->postServiceRequest([
                        'identifier' => ['system' => $sistemSr, 'value' => $kunciOrder],
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
                    if (empty($serviceRequestId)) { $gagalSr++; continue; }
                    $satuSehat['labServiceRequestIds'][] = $serviceRequestId;
                    $this->catatKirim($indeks, $kunciOrder, 'sr', $serviceRequestId);
                }

                // 2) Specimen — darah, venipuncture.
                $specimenId = $this->idKirim($indeks, $kunciOrder, 'sp');
                if (empty($specimenId)) {
                    $specimen = $this->postSpecimen([
                        'identifier' => ['system' => $sistemSp, 'value' => $kunciOrder, 'assigner' => "Organization/{$orgId}"],
                        'status' => 'available', 'subject' => "Patient/{$patientId}",
                        'type' => ['system' => 'http://snomed.info/sct', 'code' => '119297000', 'display' => 'Blood specimen'],
                        'collection' => ['collectedDateTime' => $waktu, 'method' => ['system' => 'http://snomed.info/sct', 'code' => '129300006', 'display' => 'Puncture - action']],
                        'receivedTime' => $waktu, 'request' => ["ServiceRequest/{$serviceRequestId}"],
                    ]);
                    $specimenId = $specimen['id'] ?? null;
                    if (!empty($specimenId)) {
                        $satuSehat['labSpecimenIds'][] = $specimenId;
                        $this->catatKirim($indeks, $kunciOrder, 'sp', $specimenId);
                    }
                }

                // 3) Observation per item ber-loinc.
                // Sudah pernah terbentuk → pakai id yang itu, jangan buat ulang (tak ada
                // identifier yang bisa dipakai SATUSEHAT untuk menolak duplikat).
                $obsIdsThisPaket = $obsTersimpan;
                if (empty($obsIdsThisPaket)) {
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
                }

                if (empty($obsIdsThisPaket)) { continue; }
                $this->catatKirim($indeks, $kunciOrder, 'obs', $obsIdsThisPaket);

                // 4) DiagnosticReport — merangkum paket.
                if (empty($this->idKirim($indeks, $kunciOrder, 'dr'))) {
                    $dokter = $this->createDiagnosticReport([
                        'identifier' => [['system' => $sistemDr, 'use' => 'official', 'value' => $kunciOrder]],
                        'status' => 'final', 'categoryCode' => 'LAB', 'categoryDisplay' => 'Laboratory',
                        'codeSystem' => 'http://loinc.org', 'code' => '26436-6', 'display' => 'Laboratory studies',
                        'patientId' => $patientId, 'encounterId' => $encounterId,
                        'effectiveDate' => $waktu, 'issued' => $waktu,
                        'performer' => ["Practitioner/{$practitionerId}"],
                        'specimen' => $specimenId ? ["Specimen/{$specimenId}"] : [],
                        'observationIds' => $obsIdsThisPaket, 'basedOn' => [$serviceRequestId],
                    ]);
                    if (!empty($dokter['id'])) {
                        $satuSehat['labDiagnosticReportIds'][] = $dokter['id'];
                        $this->catatKirim($indeks, $kunciOrder, 'dr', $dokter['id']);
                    }
                }

                $sudahAdaSebagian ? $disusul++ : $paketBaru++;
            }

            $satuSehat['labKirim'] = $indeks;

            // Tak ada yang dikerjakan DAN tak ada yang gagal → memang semuanya sudah pernah
            // dikirim. Indeks tetap disimpan supaya record lama tak perlu dipulihkan lagi.
            if ($paketBaru === 0 && $disusul === 0 && $gagalSr === 0) {
                $this->saveResult($rjNo, $satuSehat);
                $this->dispatch('toast', type: 'info', message: 'Lab sudah pernah dikirim.');
                return;
            }

            if (empty($satuSehat['labDiagnosticReportIds'])) {
                $pesan = $skippedNoLoinc > 0
                    ? "Gagal: {$skippedNoLoinc} item lab belum punya kode LOINC di Master Lab."
                    : 'Tidak ada hasil lab yang bisa dikirim.';
                $this->dispatch('toast', type: 'error', message: $pesan);
                return;
            }

            $this->saveResult($rjNo, $satuSehat);
            $drCount = count($satuSehat['labDiagnosticReportIds']);
            $note = $skippedNoLoinc > 0 ? " ({$skippedNoLoinc} item tanpa LOINC dilewati)" : '';
            $lanjutan = $disusul > 0 ? " — {$disusul} paket lama dilengkapi" : '';
            $lewat    = $tuntas > 0 ? ", {$tuntas} sudah lengkap dilewati" : '';
            $gagal    = $gagalSr > 0 ? ", {$gagalSr} paket GAGAL (ServiceRequest tak terbentuk)" : '';
            $takPeta  = $takTerpetakan > 0 ? ", {$takTerpetakan} paket dilewati (daftar observasi tak diketahui)" : '';
            $this->dispatch(
                'toast',
                type: $gagalSr > 0 ? 'warning' : 'success',
                message: "Lab terkirim: {$drCount} laporan, {$totalObs} observasi{$note}{$lanjutan}{$lewat}{$gagal}{$takPeta}.",
            );
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            // Simpan dulu yang sudah TERLANJUR terbentuk di SATUSEHAT sebelum melapor
            // gagal. Tanpa ini id-nya hangus padahal resource-nya SUDAH ada di sana,
            // lalu percobaan berikutnya menumpuk resource yatim — persis penyebab
            // diagnosa macet permanen dulu (lihat sender Condition). Dibungkus try
            // sendiri supaya kegagalan menyimpan tidak menutupi error aslinya.
            try {
                if (isset($satuSehat)) {
                    if (isset($indeks)) { $satuSehat['labKirim'] = $indeks; }
                    $this->saveResult($rjNo, $satuSehat);
                }
            } catch (\Throwable) {}
            $this->dispatch('toast', type: 'error', message: 'Lab gagal: ' . $e->getMessage());
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
                <span class="text-sm font-bold">9</span>
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
