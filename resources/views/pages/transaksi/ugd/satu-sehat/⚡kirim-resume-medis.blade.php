<?php
// resources/views/pages/transaksi/ugd/satu-sehat/kirim-resume-medis.blade.php
// Step 13 (penutup): Kirim Resume Medis IGD (Composition) — playbook IGD, docs §9.4.
//
// SUSUNAN SECTION IGD BEDA DARI RJ: diawali Asesmen Awal IGD + Skrining, dan TIDAK
// punya section Diet maupun Edukasi. Tipe dokumennya pun beda (97663-9). Semua itu
// diurus ResumeMedisSection lewat parameter jalur — kartu ini cukup mengirim 'ugd'.
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
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\SATUSEHAT\CompositionTrait;
use App\Support\Terminologi\ResumeMedisSection;

new class extends Component {
    use EmrUGDTrait, CompositionTrait;

    public ?string $rjNo = null;
    public bool $encounterFinished = false;
    public int $count = 0;

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat halaman belasan kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /**
     * Bagian Composition yang AKAN terisi, memakai petaEntri() yang SAMA dengan
     * kirim(). Resume Medis tidak mengambil data EMR sendiri — ia merangkum
     * resource yang SUDAH terkirim, jadi yang ditampilkan di sini adalah jumlah
     * rujukan per bagian, bukan isi klinisnya.
     */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->rjNo)) {
            return [];
        }

        $satuSehat = $this->findDataUGD($this->rjNo)['satusehat'] ?? [];
        $baris = [];
        foreach ($this->petaEntri($satuSehat) as $bagian => $rujukan) {
            $jumlah = count($rujukan);
            $baris[] = [
                'label' => $bagian,
                'nilai' => $jumlah > 0 ? $jumlah . ' resource' : '(belum ada — bagian dikosongkan)',
                'ket' => $jumlah > 0 ? implode(', ', array_slice($rujukan, 0, 3)) . ($jumlah > 3 ? ' …' : '') : '',
            ];
        }

        return $baris;
    }

    public int $sectionTerisi = 0;
    public int $sectionTotal = 0;

    public function mount(?string $rjNo = null): void
    {
        $this->rjNo = $rjNo;
        $this->sectionTotal = count(ResumeMedisSection::daftarKunci('ugd'));
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
        $dataUGD = $this->findDataUGD($this->rjNo);
        if (empty($dataUGD)) {
            return;
        }

        $satuSehat = $dataUGD['satusehat'] ?? [];
        $this->encounterFinished = !empty($satuSehat['encounterFinished']);
        $this->count = !empty($satuSehat['compositionId']) ? 1 : 0;

        $entri = $this->petaEntri($satuSehat);
        $this->sectionTerisi = $this->sectionTotal
            - count($this->sectionCompositionKosong($entri, $this->susunNarasi($dataUGD), 'ugd'));
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    #[On('ss-resume-medis-ugd.kirim')]
    public function kirim(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            if (empty($dataUGD)) { $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.'); return; }

            $satuSehat = $dataUGD['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (empty($satuSehat['encounterFinished'])) { $this->dispatch('toast', type: 'error', message: 'Selesaikan Encounter dulu — resume medis dikirim setelah kunjungan selesai.'); return; }
            if (!empty($satuSehat['compositionId'])) { $this->dispatch('toast', type: 'info', message: 'Resume medis sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataUGD['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $authorId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataUGD['drId'] ?? '')->value('dr_uuid') ?? '');
            if (empty($authorId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong.'); return; }

            $entri = $this->petaEntri($satuSehat);
            $narasi = $this->susunNarasi($dataUGD);
            $kosong = $this->sectionCompositionKosong($entri, $narasi, 'ugd');
            if (count($kosong) >= $this->sectionTotal) {
                $this->dispatch('toast', type: 'error', message: 'Belum ada resource yang bisa diringkas — kirim data kunjungan lebih dulu.');
                return;
            }

            $respons = $this->createComposition([
                'identifier' => $rjNo,
                'patientId' => $patientId,
                'patientName' => (string) ($dataUGD['regName'] ?? ''),
                'encounterId' => $satuSehat['encounterId'],
                'authorId' => $authorId,
                'authorName' => (string) ($dataUGD['drDesc'] ?? ''),
                'date' => $this->parseDate($dataUGD['rjDate'] ?? '')->toIso8601String(),
                'jalur' => 'ugd',
                'title' => 'Resume Medis Gawat Darurat',
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
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Resume medis gagal: ' . $e->getMessage());
        }
    }

    /**
     * Node satusehat → slot section resume medis.
     *
     * Yang belum punya sumber di UGD sengaja tidak diisi: Asesmen Awal IGD & Skrining
     * (data triase belum dikirim sebagai Observation), keluhan penyerta, riwayat
     * penyakit pribadi/keluarga, riwayat pengobatan (FamilyMemberHistory &
     * MedicationStatement belum dibangun), obat pulang, kondisi pulang, dan rencana
     * tindak lanjut. Slot Diet/Edukasi tidak ada di susunan IGD.
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
    private function susunNarasi(array $dataUGD): string
    {
        $bagian = [];

        $keluhan = trim((string) ($dataUGD['anamnesa']['keluhanUtama']['keluhanUtama'] ?? ''));
        if ($keluhan !== '') {
            $bagian[] = 'Keluhan utama: ' . $keluhan . '.';
        }

        $diagnosaList = [];
        foreach ($dataUGD['diagnosis'] ?? [] as $diagnosa) {
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
        foreach ($dataUGD['procedure'] ?? [] as $tindakan) {
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

<div class="p-4 bg-canvas border-2 border-teal-300 shadow-sm rounded-xl dark:bg-gray-900 dark:border-teal-700">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ $count > 0 ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-teal-100 text-teal-600 dark:bg-teal-900/30 dark:text-teal-400' }}">
                <span class="text-sm font-bold">13</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Resume Medis IGD</div>
                <div class="text-xs text-muted dark:text-gray-400">
                    Ringkasan kunjungan gawat darurat (Composition) — merangkum resource yang sudah terkirim.
                </div>
                <div class="mt-1 text-xs {{ $count > 0 ? 'text-success' : 'text-muted-soft' }}">
                    {{ $count > 0 ? 'terkirim · ' : '' }}{{ $sectionTerisi }} dari {{ $sectionTotal }} bagian terisi
                    @if (!$encounterFinished)
                        · <span class="text-warning-deep dark:text-amber-300">menunggu Encounter diselesaikan</span>
                    @endif
                </div>
                {{-- wire:click, bukan x-show Alpine: kartu ini ikut di-morph tiap kali
                     daftar langkah disegarkan, dan state Alpine bisa putus di situ. --}}
                <button type="button" wire:click="togglePratinjau" wire:loading.attr="disabled"
                    wire:target="togglePratinjau"
                    class="mt-1 text-xs font-medium underline text-info-deep hover:no-underline dark:text-blue-300">
                    {{ $pratinjauTerbuka ? 'Sembunyikan data' : 'Lihat data yang akan dikirim' }}
                </button>
            </div>
        </div>
        <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled"
            :disabled="!$encounterFinished"
            class="{{ $count > 0 ? '!bg-emerald-600' : '!bg-teal-600 hover:!bg-teal-700' }}">
            <span wire:loading.remove wire:target="kirimForCurrent">{{ $count > 0 ? 'Terkirim' : 'Kirim' }}</span>
            <span wire:loading wire:target="kirimForCurrent"><x-loading />...</span>
        </x-primary-button>
    </div>

    @if ($pratinjauTerbuka)
        <x-satu-sehat.pratinjau :baris="$this->pratinjau"
            kosong="Belum ada resource terkirim untuk dirangkum — kirim langkah-langkah di atas dulu." />
    @endif
</div>
