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
use App\Http\Traits\SATUSEHAT\ObservationTrait;
use App\Http\Traits\SATUSEHAT\ImagingStudyTrait;
use App\Http\Traits\SATUSEHAT\OrthancTrait;
use App\Http\Traits\SATUSEHAT\PenunjangKirimTrait;

new class extends Component {
    use EmrRJTrait, ServiceRequestTrait, DiagnosticReportTrait, ObservationTrait, ImagingStudyTrait, OrthancTrait, PenunjangKirimTrait;

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
                ->select('a.rad_dtl', 'a.rad_id', 'm.rad_desc', 'm.loinc_code', 'm.loinc_display', 'a.radnum_no', 'a.rad_upload_pdf_foto', 'a.study_uid')
                ->get();

            $regNo   = $dataRJ['regNo'] ?? '';
            $regName = $dataRJ['regName'] ?? '';
            if ($orders->isEmpty()) { $this->dispatch('toast', type: 'error', message: 'Tidak ada order radiologi untuk dikirim.'); return; }

            $satuSehat['radServiceRequestIds']   = $satuSehat['radServiceRequestIds']   ?? [];
            $satuSehat['radObservationIds']      = $satuSehat['radObservationIds']      ?? [];
            $satuSehat['radDiagnosticReportIds'] = $satuSehat['radDiagnosticReportIds'] ?? [];

            // Indeks per-order — penentu order mana yang masih bolong. Lihat PenunjangKirimTrait.
            $sistemSr = "http://sys-ids.kemkes.go.id/servicerequest/{$orgId}";
            $sistemDr = "http://sys-ids.kemkes.go.id/diagnostic/{$orgId}/rad";
            // Sebelum RuleNumber 10432 identifier DiagnosticReport TANPA akhiran. Record yang
            // sudah terkirim memakai system lama itu, jadi pemulihan harus mencoba keduanya —
            // kalau tidak, DR lama tak ketemu lalu dibuatkan DR KEDUA di SATUSEHAT.
            $sistemDrLama = "http://sys-ids.kemkes.go.id/diagnostic/{$orgId}";
            $indeks   = $this->indeksKirim($satuSehat, 'radKirim');
            $pulihkan = $this->perluPulihIndeks($satuSehat, 'radKirim', ['radServiceRequestIds', 'radDiagnosticReportIds']);

            $orderBaru = 0;   // order yang benar-benar dikirim putaran ini
            $disusul   = 0;   // order lama yang bagian bolongnya baru dilengkapi sekarang
            $tuntas    = 0;   // order yang memang sudah lengkap — dilewati tanpa memanggil API
            $gagalSr   = 0;   // ServiceRequest tak terbentuk → order ini tak bisa dilanjut
            $takTerpetakan = 0;   // record lama yang id-nya tak ketemu saat dipulihkan

            foreach ($orders as $order) {
                $nomorDetail = trim((string) ($order->rad_dtl ?? ''));
                $deskripsi   = trim((string) ($order->rad_desc ?? 'Pemeriksaan Radiologi'));
                $key         = "{$rjNo}-{$nomorDetail}";
                $kunciOrder  = "rad-{$key}";

                $loincCode    = trim((string) ($order->loinc_code ?? ''));
                $loincDisplay = trim((string) ($order->loinc_display ?? ''));
                if ($loincCode === '') {
                    $loincCode = '18748-4';
                    $loincDisplay = $deskripsi;
                }

                // ImagingStudy hanya relevan kalau fotonya memang sudah ada.
                $fileFoto = $order->rad_upload_pdf_foto ?? '';
                $radnumNo = $order->radnum_no ?? '';
                $adaFoto  = filled($fileFoto) && filled($radnumNo);

                $bagianWajib = $adaFoto ? ['sr', 'dr', 'is'] : ['sr', 'dr'];

                // Record lama belum punya indeks — pulihkan id yang SUDAH ada di SATUSEHAT
                // lewat identifier, supaya yang tersisa saja yang dikirim ulang.
                if ($pulihkan) {
                    $this->catatKirim($indeks, $kunciOrder, 'sr', $this->cariIdLewatIdentifier('ServiceRequest', $sistemSr, $kunciOrder));
                    $this->catatKirim($indeks, $kunciOrder, 'dr',
                        $this->cariIdLewatIdentifier('DiagnosticReport', $sistemDr, $kunciOrder)
                        ?? $this->cariIdLewatIdentifier('DiagnosticReport', $sistemDrLama, $kunciOrder));
                    if ($adaFoto) {
                        $uidTersimpan = trim((string) ($order->study_uid ?? '')) ?: $this->uidStudi($kunciOrder);
                        $this->catatKirim($indeks, $kunciOrder, 'is', $this->cariIdLewatIdentifier('ImagingStudy', 'urn:dicom:uid', 'urn:oid:' . $uidTersimpan));
                    }
                }

                if ($this->orderTuntas($indeks, $kunciOrder, $bagianWajib)) { $tuntas++; continue; }

                // Record lama yang id-nya TAK berhasil dipetakan — jangan dikirim ulang.
                // Array datar sudah membuktikan order ini pernah terkirim; POST ulang cuma
                // akan ditolak duplikat (20002), persis kemacetan yang sedang diperbaiki.
                // Sikap ini sama dengan guard lama, jadi record lama tak pernah lebih buruk.
                if ($pulihkan && blank($this->idKirim($indeks, $kunciOrder, 'sr'))) { $takTerpetakan++; continue; }

                $sudahAdaSebagian = filled($this->idKirim($indeks, $kunciOrder, 'sr'));

                // 1) ServiceRequest — order radiologi (kode generik imaging).
                $serviceRequestId = $this->idKirim($indeks, $kunciOrder, 'sr');
                if (empty($serviceRequestId)) {
                    $serviceRequest = $this->postServiceRequest([
                        'identifier' => ['system' => $sistemSr, 'value' => $kunciOrder],
                        'status' => 'active', 'intent' => 'original-order', 'priority' => 'routine',
                        'category' => ['system' => 'http://snomed.info/sct', 'code' => '363679005', 'display' => 'Imaging'],
                        'code' => ['system' => 'http://loinc.org', 'code' => $loincCode, 'display' => $loincDisplay ?: $deskripsi],
                        'subject' => "Patient/{$patientId}", 'encounter' => "Encounter/{$encounterId}",
                        'occurrenceDateTime' => $waktu, 'authoredOn' => $waktu,
                        'requester' => "Practitioner/{$practitionerId}", 'requesterDisplay' => $drDesc,
                        'performer' => $pjPenunjang['reference'] ?? null,
                        'performerDisplay' => $pjPenunjang['display'] ?? null,
                    ]);
                    $serviceRequestId = $serviceRequest['id'] ?? null;
                    if (empty($serviceRequestId)) { $gagalSr++; continue; }
                    $satuSehat['radServiceRequestIds'][] = $serviceRequestId;
                    $this->catatKirim($indeks, $kunciOrder, 'sr', $serviceRequestId);
                }

                // 2) DiagnosticReport — pelaporan (generik; hasil = PDF terlampir).
                if (empty($this->idKirim($indeks, $kunciOrder, 'dr'))) {
                    // Observation dibuat DI DALAM cabang ini, bukan di luar: DiagnosticReport.result
                    // wajib (RuleNumber 10385), tapi Observation tak punya identifier sehingga tak
                    // bisa dipulihkan maupun ditolak duplikat. Kalau dibuat di luar, laporan yang
                    // SUDAH ada akan ditinggali observasi yatim tiap kali tombol kirim ditekan.
                    $obsId = $this->idKirim($indeks, $kunciOrder, 'obs');
                    if (empty($obsId)) {
                        $observation = $this->createObservation([
                            'patientId' => $patientId, 'encounterId' => $encounterId, 'performerId' => $practitionerId,
                            'effectiveDate' => $waktu,
                            'category' => [['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/observation-category', 'code' => 'imaging', 'display' => 'Imaging']]]],
                            'code' => ['system' => 'http://loinc.org', 'code' => $loincCode, 'display' => $loincDisplay ?: $deskripsi],
                            'valueString' => 'Lihat hasil pada lampiran radiologi',
                        ]);
                        $obsId = $observation['id'] ?? null;
                        if (!empty($obsId)) {
                            $satuSehat['radObservationIds'][] = $obsId;
                            $this->catatKirim($indeks, $kunciOrder, 'obs', $obsId);
                        }
                    }

                    $drPayload = [
                        'identifier' => [['system' => $sistemDr, 'use' => 'official', 'value' => $kunciOrder]],
                        'status' => 'final', 'categoryCode' => 'RAD', 'categoryDisplay' => 'Radiology',
                        'codeSystem' => 'http://loinc.org', 'code' => $loincCode, 'display' => $loincDisplay ?: $deskripsi,
                        'patientId' => $patientId, 'encounterId' => $encounterId,
                        'effectiveDate' => $waktu, 'issued' => $waktu,
                        'performer' => ["Practitioner/{$practitionerId}"], 'basedOn' => [$serviceRequestId],
                    ];
                    if (!empty($obsId)) { $drPayload['observationIds'] = [$obsId]; }
                    $dokter = $this->createDiagnosticReport($drPayload);
                    if (!empty($dokter['id'])) {
                        $satuSehat['radDiagnosticReportIds'][] = $dokter['id'];
                        $this->catatKirim($indeks, $kunciOrder, 'dr', $dokter['id']);
                    }
                }

                // 3) ImagingStudy — kirim kalau ada file foto/PDF yang sudah diupload.
                if ($adaFoto && empty($this->idKirim($indeks, $kunciOrder, 'is'))) {
                    $modalitas = $this->modalitasDariDeskripsi($deskripsi);

                    $studyUid = $this->prosesOrthanc(
                        'rstxn_rjrads',
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
                        'kunci'            => $kunciOrder,
                        'studyUid'         => $studyUid,
                        'patientId'        => $patientId,
                        'encounterId'      => $encounterId,
                        'started'          => $waktu,
                        'modalityCode'     => $modalitas['code'],
                        'modalityDisplay'  => $modalitas['display'],
                        'procedureCode'    => $loincCode,
                        'procedureDisplay' => $loincDisplay ?: $deskripsi,
                        'referrerId'       => $practitionerId,
                        'basedOn'          => $serviceRequestId,
                        'description'      => $deskripsi,
                    ]);
                    if (!empty($imagingStudy['id'])) {
                        $satuSehat['radImagingStudyIds']   = $satuSehat['radImagingStudyIds'] ?? [];
                        $satuSehat['radImagingStudyIds'][] = $imagingStudy['id'];
                        $this->catatKirim($indeks, $kunciOrder, 'is', $imagingStudy['id']);
                    }
                }

                $sudahAdaSebagian ? $disusul++ : $orderBaru++;
            }

            $satuSehat['radKirim'] = $indeks;

            // Tak ada yang dikerjakan DAN tak ada yang gagal → memang semuanya sudah pernah
            // dikirim. Indeks tetap disimpan supaya record lama tak perlu dipulihkan lagi.
            if ($orderBaru === 0 && $disusul === 0 && $gagalSr === 0) {
                $this->saveResult($rjNo, $satuSehat);
                $this->dispatch('toast', type: 'info', message: 'Radiologi sudah pernah dikirim.');
                return;
            }

            if (empty($satuSehat['radServiceRequestIds'])) { $this->dispatch('toast', type: 'error', message: 'Tidak ada order radiologi yang bisa dikirim.'); return; }

            $this->saveResult($rjNo, $satuSehat);
            $srCount = count($satuSehat['radServiceRequestIds']);
            $drCount = count($satuSehat['radDiagnosticReportIds']);
            $isCount = count($satuSehat['radImagingStudyIds'] ?? []);
            $isInfo  = $isCount > 0 ? ", {$isCount} ImagingStudy" : ' (ImagingStudy dilewati — belum ada foto)';
            $lanjutan = $disusul > 0 ? " — {$disusul} order lama dilengkapi" : '';
            $lewat    = $tuntas > 0 ? ", {$tuntas} sudah lengkap dilewati" : '';
            $gagal    = $gagalSr > 0 ? ", {$gagalSr} order GAGAL (ServiceRequest tak terbentuk)" : '';
            $takPeta  = $takTerpetakan > 0 ? ", {$takTerpetakan} order lama tak terpetakan (dilewati)" : '';
            $this->dispatch(
                'toast',
                type: $gagalSr > 0 ? 'warning' : 'success',
                message: "Radiologi terkirim: {$srCount} order, {$drCount} laporan{$isInfo}{$lanjutan}{$lewat}{$gagal}{$takPeta}.",
            );
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            // Simpan dulu yang sudah TERLANJUR terbentuk di SATUSEHAT sebelum melapor
            // gagal. Tanpa ini id-nya hangus padahal resource-nya SUDAH ada di sana,
            // lalu percobaan berikutnya menumpuk resource yatim — persis penyebab
            // diagnosa macet permanen dulu (lihat sender Condition). Indeks per-order
            // ikut disimpan supaya percobaan berikutnya tahu persis mana yang bolong.
            // Dibungkus try sendiri supaya kegagalan menyimpan tidak menutupi error aslinya.
            try {
                if (isset($satuSehat)) {
                    if (isset($indeks)) { $satuSehat['radKirim'] = $indeks; }
                    $this->saveResult($rjNo, $satuSehat);
                }
            } catch (\Throwable) {}
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
