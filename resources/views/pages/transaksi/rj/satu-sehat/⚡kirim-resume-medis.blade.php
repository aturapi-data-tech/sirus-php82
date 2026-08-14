<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-resume-medis.blade.php
// Step 13 (penutup): Kirim Resume Medis (Composition) — playbook Bab 28, docs §9.4.
//
// Composition adalah INDEKS: isinya referensi resource yang sudah dikirim kartu-kartu
// di atasnya, bukan data baru. Karena itu kartu ini tidak membaca EMR untuk klinisnya —
// semua ID diambil dari node satusehat kunjungan ini. Yang dibaca dari EMR hanya narasi
// "Perjalanan Kunjungan Pasien" (satu-satunya section naratif).
//
// Prasyarat keras: Encounter sudah di-finish. Playbook menempatkan resume medis SETELAH
// kunjungan selesai, dan kartu ini memang dipasang di bawah kartu "Selesaikan Encounter".
//
// Section yang tidak punya sumber TIDAK dikirim (bukan entry kosong — ditolak validator),
// dan jumlahnya dilaporkan di kartu + toast supaya tidak hilang diam-diam.

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\CompositionTrait;
use App\Support\Terminologi\ResumeMedisSection;

new class extends Component {
    use EmrRJTrait, CompositionTrait;

    public ?string $rjNo = null;
    public bool $encounterFinished = false;
    public int $count = 0;
    public int $sectionTerisi = 0;
    public int $sectionTotal = 0;

    public function mount(?string $rjNo = null): void
    {
        $this->rjNo = $rjNo;
        $this->sectionTotal = count(ResumeMedisSection::daftarKunci());
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
        $dataRJ = $this->findDataRJ($this->rjNo);
        if (empty($dataRJ)) {
            return;
        }

        $satuSehat = $dataRJ['satusehat'] ?? [];
        $this->encounterFinished = !empty($satuSehat['encounterFinished']);
        $this->count = !empty($satuSehat['compositionId']) ? 1 : 0;

        $entri = $this->petaEntri($satuSehat);
        $this->sectionTerisi = $this->sectionTotal
            - count($this->sectionCompositionKosong($entri, $this->susunNarasi($dataRJ)));
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    #[On('ss-resume-medis-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.'); return; }

            $satuSehat = $dataRJ['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (empty($satuSehat['encounterFinished'])) { $this->dispatch('toast', type: 'error', message: 'Selesaikan Encounter dulu — resume medis dikirim setelah kunjungan selesai.'); return; }
            if (!empty($satuSehat['compositionId'])) { $this->dispatch('toast', type: 'info', message: 'Resume medis sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $authorId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRJ['drId'] ?? '')->value('dr_uuid') ?? '');
            if (empty($authorId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong.'); return; }

            $entri = $this->petaEntri($satuSehat);
            $narasi = $this->susunNarasi($dataRJ);
            $kosong = $this->sectionCompositionKosong($entri, $narasi);
            if (count($kosong) >= $this->sectionTotal) {
                $this->dispatch('toast', type: 'error', message: 'Belum ada resource yang bisa diringkas — kirim data kunjungan lebih dulu.');
                return;
            }

            $respons = $this->createComposition([
                'identifier' => $rjNo,
                'patientId' => $patientId,
                'patientName' => (string) ($dataRJ['regName'] ?? ''),
                'encounterId' => $satuSehat['encounterId'],
                'authorId' => $authorId,
                'authorName' => (string) ($dataRJ['drDesc'] ?? ''),
                'date' => $this->parseDate($dataRJ['rjDate'] ?? '')->toIso8601String(),
                'entri' => $entri,
                'narasi' => $narasi,
            ]);

            if (empty($respons['id'])) { $this->dispatch('toast', type: 'error', message: 'Resume medis gagal: respons tanpa id.'); return; }

            $satuSehat['compositionId'] = $respons['id'];
            $this->saveResult($rjNo, $satuSehat);

            $terisi = $this->sectionTotal - count($kosong);
            $pesan = "Resume medis terkirim — {$terisi} dari {$this->sectionTotal} bagian terisi.";
            if ($kosong !== []) {
                $pesan .= ' Tidak terisi: ' . implode(', ', array_slice($kosong, 0, 4))
                    . (count($kosong) > 4 ? ', dan ' . (count($kosong) - 4) . ' lainnya.' : '.');
            }
            $this->dispatch('toast', type: 'success', message: $pesan);
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Resume medis gagal: ' . $e->getMessage());
        }
    }

    /**
     * Node satusehat → slot section resume medis.
     *
     * Yang belum punya sumber di RJ sengaja tidak diisi: keluhan penyerta, riwayat
     * penyakit pribadi/keluarga, riwayat pengobatan (FamilyMemberHistory &
     * MedicationStatement belum dibangun), obat pulang, diet, edukasi, kondisi pulang,
     * dan rencana tindak lanjut.
     */
    private function petaEntri(array $satuSehat): array
    {
        $ref = fn(string $tipe, $nilai) => collect(is_array($nilai) ? $nilai : [$nilai])
            ->filter(fn($satu) => is_string($satu) && trim($satu) !== '')
            ->map(fn($satu) => $tipe . '/' . $satu)
            ->values()
            ->all();

        return [
            'keluhanUtama' => $ref('Condition', $satuSehat['chiefComplaintId'] ?? []),
            'riwayatAlergi' => $ref('AllergyIntolerance', $satuSehat['allergyId'] ?? []),
            'tandaVital' => $ref('Observation', $satuSehat['observationIds'] ?? []),
            // Risiko jatuh & skrining gizi = asesmen fungsional berskala; playbook
            // mencontohkan status psikologis, tapi slot LOINC 47420-5 inilah rumahnya.
            'pemeriksaanFungsional' => $ref('Observation', $satuSehat['penilaianObservationIds'] ?? []),
            'hasilLab' => array_merge(
                $ref('ServiceRequest', $satuSehat['labServiceRequestIds'] ?? []),
                $ref('Specimen', $satuSehat['labSpecimenIds'] ?? []),
                $ref('Observation', $satuSehat['labObservationIds'] ?? []),
                $ref('DiagnosticReport', $satuSehat['labDiagnosticReportIds'] ?? []),
            ),
            'hasilRadiologi' => array_merge(
                $ref('ServiceRequest', $satuSehat['radServiceRequestIds'] ?? []),
                $ref('DiagnosticReport', $satuSehat['radDiagnosticReportIds'] ?? []),
            ),
            // RJ tidak memisahkan diagnosis masuk & akhir. Diagnosa yang tercatat dipakai
            // sebagai diagnosis akhir — konsisten dengan Encounter.diagnosis use=DD saat finish.
            'diagnosisAkhir' => array_merge(
                $ref('Condition', $satuSehat['conditionIds'] ?? []),
                $ref('ClinicalImpression', $satuSehat['clinicalImpressionId'] ?? []),
            ),
            'tindakan' => $ref('Procedure', $satuSehat['procedureIds'] ?? []),
            'obatSaatKunjungan' => array_merge(
                $ref('MedicationRequest', $satuSehat['medicationRequestIds'] ?? []),
                $ref('MedicationDispense', $satuSehat['medicationDispenseIds'] ?? []),
            ),
        ];
    }

    /** Narasi section "Perjalanan Kunjungan Pasien" (satu-satunya yang naratif). */
    private function susunNarasi(array $dataRJ): string
    {
        $bagian = [];

        $keluhan = trim((string) ($dataRJ['anamnesa']['keluhanUtama']['keluhanUtama'] ?? ''));
        if ($keluhan !== '') {
            $bagian[] = 'Keluhan utama: ' . $keluhan . '.';
        }

        $diagnosaList = [];
        foreach ($dataRJ['diagnosis'] ?? [] as $diagnosa) {
            $kode = trim((string) ($diagnosa['icdX'] ?? ($diagnosa['diagId'] ?? '')));
            $deskripsi = trim((string) ($diagnosa['diagDesc'] ?? ''));
            if ($kode !== '' || $deskripsi !== '') {
                $diagnosaList[] = trim("{$kode} - {$deskripsi}", ' -');
            }
        }
        if ($diagnosaList !== []) {
            $bagian[] = 'Diagnosis: ' . implode('; ', $diagnosaList) . '.';
        }

        $tindakanList = [];
        foreach ($dataRJ['procedure'] ?? [] as $tindakan) {
            $deskripsi = trim((string) ($tindakan['procedureDesc'] ?? ''));
            if ($deskripsi !== '') {
                $tindakanList[] = $deskripsi;
            }
        }
        if ($tindakanList !== []) {
            $bagian[] = 'Tindakan: ' . implode('; ', $tindakanList) . '.';
        }

        return implode(' ', $bagian);
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

<div
    class="flex items-center justify-between p-4 bg-canvas border-2 border-teal-300 shadow-sm rounded-xl dark:bg-gray-900 dark:border-teal-700">
    <div class="flex items-center gap-3">
        <div
            class="flex items-center justify-center w-8 h-8 rounded-full {{ $count > 0 ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-teal-100 text-teal-600 dark:bg-teal-900/30 dark:text-teal-400' }}">
            <span class="text-sm font-bold">13</span>
        </div>
        <div>
            <div class="font-semibold text-ink dark:text-gray-100">Resume Medis</div>
            <div class="text-xs text-muted dark:text-gray-400">
                Ringkasan kunjungan (Composition) — merangkum resource yang sudah terkirim.
            </div>
            <div class="mt-1 text-xs {{ $count > 0 ? 'text-success' : 'text-muted-soft' }}">
                {{ $count > 0 ? 'terkirim · ' : '' }}{{ $sectionTerisi }} dari {{ $sectionTotal }} bagian terisi
                @if (!$encounterFinished)
                    · <span class="text-warning-deep dark:text-amber-300">menunggu Encounter diselesaikan</span>
                @endif
            </div>
        </div>
    </div>
    <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled"
        :disabled="!$encounterFinished"
        class="{{ $count > 0 ? '!bg-emerald-600' : '!bg-teal-600 hover:!bg-teal-700' }}">
        <span wire:loading.remove wire:target="kirimForCurrent">{{ $count > 0 ? 'Terkirim' : 'Kirim' }}</span>
        <span wire:loading wire:target="kirimForCurrent"><x-loading />...</span>
    </x-primary-button>
</div>
