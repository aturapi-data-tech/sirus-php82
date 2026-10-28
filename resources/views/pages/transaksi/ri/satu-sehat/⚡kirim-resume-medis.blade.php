<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-resume-medis.blade.php
// Kirim Resume Medis RI (Composition, type 18842-5 Discharge summary) — Playbook RI bab 29, docs §9.4.
// Port kartu RJ: jalur 'ri' di ResumeMedisSection, identifier 'RI-{riHdrNo}' (nomor RI bisa sama dgn nomor RJ).
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
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Support\AnamnesaRi;
use App\Http\Traits\SATUSEHAT\CompositionTrait;
use App\Support\Terminologi\ResumeMedisSection;

new class extends Component {
    use EmrRITrait, CompositionTrait;

    public ?string $riHdrNo = null;

    /** Mode pratinjau saja (layar Coding SATUSEHAT RI): tombol Kirim disembunyikan, kirim ditolak. */
    public bool $hanyaPratinjau = false;
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
        if (empty($this->riHdrNo)) {
            return [];
        }

        $satuSehat = $this->findDataRI($this->riHdrNo)['satusehat'] ?? [];
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

    public function mount(?string $riHdrNo = null, bool $hanyaPratinjau = false): void
    {
        $this->hanyaPratinjau = $hanyaPratinjau;
        $this->pratinjauTerbuka = $hanyaPratinjau;
        $this->riHdrNo = $riHdrNo;
        $this->sectionTotal = count(ResumeMedisSection::daftarKunci('ri'));
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
        $dataRI = $this->findDataRI($this->riHdrNo);
        if (empty($dataRI)) {
            return;
        }

        $satuSehat = $dataRI['satusehat'] ?? [];
        $this->encounterFinished = !empty($satuSehat['encounterFinished']);
        $this->count = !empty($satuSehat['compositionId']) ? 1 : 0;

        $entri = $this->petaEntri($satuSehat);
        $this->sectionTerisi = $this->sectionTotal
            - count($this->sectionCompositionKosong($entri, $this->susunNarasi($dataRI)));
    }

    public function kirimForCurrent(): void
    {
        if ($this->hanyaPratinjau) {
            return;
        }
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
    #[On('ss-resume-medis-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        if ($this->hanyaPratinjau) {
            return;
        }
        $this->kirimInti($riHdrNo);
        $this->dispatch('ri-satu-sehat.langkah-selesai', langkah: 'resume-medis');
    }

    public function kirimInti(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $satuSehat = $dataRI['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (empty($satuSehat['encounterFinished'])) { $this->dispatch('toast', type: 'error', message: 'Selesaikan Encounter dulu — resume medis dikirim setelah kunjungan selesai.'); return; }
            if (!empty($satuSehat['compositionId'])) { $this->dispatch('toast', type: 'info', message: 'Resume medis sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $authorId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRI['drId'] ?? '')->value('dr_uuid') ?? '');
            if (empty($authorId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong.'); return; }

            $entri = $this->petaEntri($satuSehat);
            $narasi = $this->susunNarasi($dataRI);
            $kosong = $this->sectionCompositionKosong($entri, $narasi);
            if (count($kosong) >= $this->sectionTotal) {
                $this->dispatch('toast', type: 'error', message: 'Belum ada resource yang bisa diringkas — kirim data kunjungan lebih dulu.');
                return;
            }

            $respons = $this->createComposition([
                'identifier' => 'RI-' . $riHdrNo,
                'jalur' => 'ri',
                'patientId' => $patientId,
                'patientName' => (string) ($dataRI['regName'] ?? ''),
                'encounterId' => $satuSehat['encounterId'],
                'authorId' => $authorId,
                'authorName' => (string) ($dataRI['drDesc'] ?? ''),
                'date' => $this->parseDate($dataRI['entryDate'] ?? '')->toIso8601String(),
                'entri' => $entri,
                'narasi' => $narasi,
            ]);

            if (empty($respons['id'])) { $this->dispatch('toast', type: 'error', message: 'Resume medis gagal: respons tanpa id.'); return; }

            $satuSehat['compositionId'] = $respons['id'];
            $this->saveResult($riHdrNo, $satuSehat);

            $terisi = $this->sectionTotal - count($kosong);
            $pesan = "Resume medis terkirim — {$terisi} dari {$this->sectionTotal} bagian terisi.";
            if ($kosong !== []) {
                $pesan .= ' Tidak terisi: ' . implode(', ', array_slice($kosong, 0, 4))
                    . (count($kosong) > 4 ? ', dan ' . (count($kosong) - 4) . ' lainnya.' : '.');
            }
            $this->dispatch('toast', type: 'success', message: $pesan);
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Resume medis gagal: ' . $e->getMessage());
        }
    }

    /**
     * Node satusehat → slot section resume medis.
     *
     * RI: tanda vital = pengkajian awal + tiap entri observasi lanjutan (+ observationIds record lama);
     * CPPT ikut diagnosis akhir (asesmen harian DPJP), diet = NutritionOrder, riwayat perjalanan = Riwayat
     * Penyakit Sekarang (pola section alur Rawat Inap apikesehatan.com). satusehat.observasiLanjutanIds
     * (kartu Observasi Lanjutan) sengaja TIDAK dimasukkan: isinya campuran MedicationAdministration &
     * Observation tanpa penanda jenis, jadi referensinya tak bisa ditulis dengan benar. Belum ada sumber:
     * obat pulang, rekomendasi diet.
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
            'keluhanPenyerta' => $ref('Condition', $satuSehat['keluhanPenyertaIds'] ?? []),
            'riwayatPenyakitSekarang' => array_merge(
                $ref('Condition', $satuSehat['riwayatPenyakitSekarangIds'] ?? []),
                $ref('ClinicalImpression', $satuSehat['riwayatPerjalananId'] ?? []), // bab 6
            ),
            'riwayatPenyakitTerdahulu' => $ref('Condition', $satuSehat['riwayatPenyakitDahuluIds'] ?? []),
            'riwayatPenyakitKeluarga' => $ref('FamilyMemberHistory', $satuSehat['riwayatPenyakitKeluargaIds'] ?? []),
            'riwayatPengobatan' => $ref('MedicationStatement', $satuSehat['riwayatPengobatanIds'] ?? []),
            'riwayatAlergi' => array_merge($ref('AllergyIntolerance', $satuSehat['allergyId'] ?? []), $ref('AllergyIntolerance', $satuSehat['allergyIds'] ?? [])),
            'tandaVital' => array_merge(
                $ref('Observation', $satuSehat['pemeriksaanAwalIds'] ?? []),
                $ref('Observation', $satuSehat['observationIds'] ?? []),
                $ref('Observation', collect($satuSehat['tandaVitalLanjutanIds'] ?? [])->flatten()->all()),
            ),
            // Risiko jatuh & skrining gizi = asesmen fungsional berskala; playbook
            // mencontohkan status psikologis, tapi slot LOINC 47420-5 inilah rumahnya.
            'pemeriksaanFungsional' => array_merge(
                $ref('Observation', $satuSehat['penilaianObservationIds'] ?? []),
                $ref('Observation', $satuSehat['statusPsikologisIds'] ?? []), // bab 5b: status psikologis
            ),
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
            // Diagnosa RI dipakai sebagai diagnosis akhir — konsisten dengan Encounter.diagnosis saat finish.
            'diagnosisAkhir' => array_merge(
                $ref('Condition', $satuSehat['conditionIds'] ?? []),
                $ref('ClinicalImpression', $satuSehat['cpptClinicalImpressionIds'] ?? []),
            ),
            'dietDiberikan' => $ref('NutritionOrder', $satuSehat['nutritionOrderId'] ?? []),
            'edukasi' => $ref('Procedure', $satuSehat['edukasiIds'] ?? []), // bab 21
            'perencanaanPerawatan' => $ref('CarePlan', $satuSehat['perencanaanPulangId'] ?? []), // bab 22b
            'tindakan' => $ref('Procedure', $satuSehat['procedureIds'] ?? []),
            'kondisiPulang' => $ref('Condition', $satuSehat['kondisiPulangId'] ?? []),
            'rencanaTindakLanjut' => $ref('ServiceRequest', $satuSehat['rencanaTindakLanjutId'] ?? []),
            'obatSaatKunjungan' => array_merge(
                $ref('MedicationRequest', $satuSehat['medicationRequestIds'] ?? []),
                $ref('MedicationDispense', $satuSehat['medicationDispenseIds'] ?? []),
            ),
        ];
    }

    /** Narasi section "Perjalanan Kunjungan Pasien" (satu-satunya yang naratif). */
    private function susunNarasi(array $dataRI): string
    {
        $bagian = [];

        if (($keluhan = AnamnesaRi::keluhanUtama($dataRI)) !== '') {
            $bagian[] = 'Keluhan utama: ' . $keluhan . '.';
        }
        if (($keluhanPenyerta = AnamnesaRi::keluhanPenyerta($dataRI)) !== '') {
            $bagian[] = 'Keluhan penyerta: ' . $keluhanPenyerta . '.';
        }

        // Diagnosa RI tersimpan di rstxn_ridtls (bukan JSON) — sumber sama dengan kartu Condition RI.
        $diagnosaList = DB::table('rstxn_ridtls as d')
            ->join('rsmst_mstdiags as m', 'd.diag_id', '=', 'm.diag_id')
            ->where('d.rihdr_no', $dataRI['riHdrNo'] ?? '')
            ->select('m.icdx', 'm.diag_desc')
            ->get()
            ->map(fn($row) => trim(trim((string) $row->icdx) . ' - ' . trim((string) $row->diag_desc), ' -'))
            ->filter()
            ->all();
        if ($diagnosaList !== []) {
            $bagian[] = 'Diagnosis: ' . implode('; ', $diagnosaList) . '.';
        }

        $tindakanList = collect($dataRI['procedure'] ?? [])->map(fn($tindakan) => trim((string) ($tindakan['procedureDesc'] ?? '')))->filter()->all();
        if ($tindakanList !== []) {
            $bagian[] = 'Tindakan: ' . implode('; ', $tindakanList) . '.';
        }

        $tindakLanjut = trim((string) ($dataRI['perencanaan']['tindakLanjut']['tindakLanjut'] ?? ''));
        if ($tindakLanjut !== '') {
            $bagian[] = 'Pulang: ' . (\App\Support\Terminologi\DischargeDisposition::label($tindakLanjut) ?: $tindakLanjut)
                . (filled($dataRI['exitDate'] ?? null) ? ' (' . $dataRI['exitDate'] . ')' : '') . '.';
        }

        return implode(' ', $bagian);
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
        if (empty($teksTanggal)) throw new \RuntimeException('tanggal sumber kosong — lengkapi datanya di EMR dulu (tidak diisi jam sekarang).');
        try { return Carbon::createFromFormat('d/m/Y H:i:s', $teksTanggal); } catch (\Throwable) {
            try { return Carbon::parse($teksTanggal); } catch (\Throwable) { throw new \RuntimeException("tanggal '{$teksTanggal}' tidak terbaca — betulkan datanya di EMR dulu (tidak diisi jam sekarang)."); }
        }
    }
};
?>

<div class="{{ $hanyaPratinjau
    ? 'p-3 border border-brand-green/30 rounded-xl bg-brand-green/5 dark:bg-brand-green/10 dark:border-brand-green/50'
    : 'p-4 bg-canvas border-2 border-teal-300 shadow-sm rounded-xl dark:bg-gray-900 dark:border-teal-700' }}">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            @unless ($hanyaPratinjau)
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ $count > 0 ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-teal-100 text-teal-600 dark:bg-teal-900/30 dark:text-teal-400' }}">
                <span class="text-sm font-bold">27</span>
            </div>
            @endunless
            <div>
                <div class="{{ $hanyaPratinjau ? 'text-xs font-semibold tracking-wide uppercase text-brand-green dark:text-brand-lime' : 'font-semibold text-ink dark:text-gray-100' }}">Resume Medis</div>
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
        @unless ($hanyaPratinjau)
        <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled"
            :disabled="!$encounterFinished"
            class="{{ $count > 0 ? '!bg-emerald-600' : '!bg-teal-600 hover:!bg-teal-700' }}">
            <span wire:loading.remove wire:target="kirimForCurrent,kirim">
                    <span class="inline-flex items-center gap-1.5">
                        <x-satu-sehat.ikon-tombol :selesai="$count > 0" jenis="kirim" />
                        {{ $count > 0 ? 'Terkirim' : 'Kirim' }}
                    </span>
                </span>
            <span wire:loading wire:target="kirimForCurrent,kirim"><x-loading />...</span>
        </x-primary-button>
        @endunless
    </div>

    <x-satu-sehat.pratinjau :terbuka="$pratinjauTerbuka" :ringkas="$hanyaPratinjau"
        :baris="$pratinjauTerbuka ? $this->pratinjau : []"
        kosong="Belum ada resource terkirim untuk dirangkum — kirim langkah-langkah di atas dulu." />
</div>
