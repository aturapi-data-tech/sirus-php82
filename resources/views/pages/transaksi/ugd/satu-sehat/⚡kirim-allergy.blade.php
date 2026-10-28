<?php
// resources/views/pages/transaksi/ugd/satu-sehat/kirim-allergy.blade.php
// Kirim Alergi (AllergyIntolerance) — UGD, Playbook IGD bab 4c (port RJ 3c), disamakan dgn alur apikesehatan.com.
// Sumber: anamnesa.alergi —
//   "Tidak ada alergi" → satu pernyataan 716186003 (milik EMR)              → satusehat.allergyId
//   "Ada alergi"       → alergenList[] (layar Coding SATUSEHAT): satu AllergyIntolerance per alergen,
//                        Obat = KFA, Makanan/Lingkungan = SNOMED, criticality = keparahan
//                                                                           → satusehat.allergyIds[jenis:kode]
//   "Ada" tanpa daftar tapi punya kode tunggal LAMA (snomedCode) → dikirim seperti dulu (allergyId).
// createAllergyIntolerance WAJIB: patientId, encounterId, code, recorderId(Practitioner IHS).

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\SATUSEHAT\AllergyIntoleranceTrait;
use App\Support\Terminologi\AlergiSnomed;
use App\Support\AlergiAlergen;

new class extends Component {
    use EmrUGDTrait, AllergyIntoleranceTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;
    public int $jumlahTarget = 0;

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat halaman belasan kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /**
     * Apa yang AKAN dikirim — dari sumber yang SAMA dengan kirim(). "Tidak ada alergi" pun
     * DIKIRIM (pernyataan negatif yang sah), jadi ikut ditampilkan.
     *
     * @return array{mode: string, alergi: array, alergenList: array}
     *   mode: 'tidak-ada' | 'daftar' | 'kode-lama' | 'kosong'
     */
    private function rencanaKirim(array $dataUGD): array
    {
        $alergi = AlergiSnomed::normalisasi($dataUGD['anamnesa']['alergi'] ?? []);
        $alergenList = array_values((array) ($alergi['alergenList'] ?? []));
        $kodeLama = trim((string) ($alergi['snomedCode'] ?? ''));

        $mode = match (true) {
            ($alergi['adaAlergi'] ?? '') !== 'Ya' => 'tidak-ada',
            $alergenList !== [] => 'daftar',
            $kodeLama !== '' => 'kode-lama',
            default => 'kosong',
        };

        return ['mode' => $mode, 'alergi' => $alergi, 'alergenList' => $alergenList];
    }

    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->rjNo)) {
            return [];
        }

        $rencana = $this->rencanaKirim($this->findDataUGD($this->rjNo) ?? []);
        $alergi = $rencana['alergi'];

        return match ($rencana['mode']) {
            'tidak-ada' => [['label' => 'Alergi', 'nilai' => 'Pernyataan TIDAK ADA alergi', 'ket' => 'SNOMED ' . ($alergi['snomedCode'] ?? '716186003')]],
            'daftar' => collect($rencana['alergenList'])->map(fn($baris) => [
                'label' => AlergiAlergen::labelJenis($baris['jenis'] ?? '') . ' — ' . AlergiAlergen::namaBaris($baris),
                'nilai' => (($baris['sistem'] ?? '') === AlergiAlergen::SISTEM_KFA ? 'KFA ' : 'SNOMED ') . ($baris['kode'] ?? ''),
                'ket' => AlergiAlergen::labelKeparahan($baris['keparahan'] ?? ''),
            ])->all(),
            'kode-lama' => [['label' => 'Alergi', 'nilai' => 'SNOMED ' . $alergi['snomedCode'], 'ket' => 'kode tunggal lama — buka Coding SATUSEHAT untuk melengkapi']],
            default => [['label' => 'Alergi', 'nilai' => '(daftar alergen kosong — Kirim akan ditolak)', 'ket' => 'isi lewat menu Coding SATUSEHAT']],
        };
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
        $this->count = (!empty($satuSehat['allergyId']) ? 1 : 0) + count($satuSehat['allergyIds'] ?? []);
        $rencana = $this->rencanaKirim($data);
        $this->jumlahTarget = $rencana['mode'] === 'daftar' ? count($rencana['alergenList']) : 1;
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
    #[On('ss-allergy-ugd.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('ugd-satu-sehat.langkah-selesai', langkah: 'allergy');
    }

    public function kirimInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            if (empty($dataUGD)) { $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.'); return; }

            $satuSehat = $dataUGD['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            $patientId = $this->getPatientIHS($dataUGD['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $recorderId = $this->getDoctorIHS($dataUGD['drId'] ?? '');
            if (empty($recorderId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong — lengkapi di master dokter.'); return; }

            $rencana = $this->rencanaKirim($dataUGD);
            $alergi = $rencana['alergi'];
            $alergiText = trim((string) ($alergi['alergi'] ?? ''));
            $onset = $this->parseDate($dataUGD['rjDate'] ?? '')->toIso8601String();

            if ($rencana['mode'] === 'kosong') { $this->dispatch('toast', type: 'error', message: 'EMR menyatakan ada alergi, tapi daftar alergen belum diisi — isi lewat menu Coding SATUSEHAT.'); return; }

            // ── Satu resource: pernyataan "tidak ada alergi" ATAU kode tunggal lama ──
            if ($rencana['mode'] !== 'daftar') {
                if (!empty($satuSehat['allergyId'])) { $this->dispatch('toast', type: 'info', message: 'Alergi sudah pernah dikirim.'); return; }
                $snomedCode = trim((string) ($alergi['snomedCode'] ?? ''));
                $tidakAdaAlergi = AlergiSnomed::adalahTidakAdaAlergi($snomedCode);

                $respons = $this->createAllergyIntolerance([
                    'patientId'   => $patientId,
                    'encounterId' => $satuSehat['encounterId'],
                    'recorderId'  => $recorderId,
                    'code'        => $snomedCode,
                    'display'     => $alergi['snomedDisplayEn'] ?? ($alergi['snomedDisplayId'] ?? $alergiText),
                    // "Tidak ada alergi" (mis. 716186003) = pernyataan TIADA alergi → type &
                    // criticality DIHILANGKAN (null), keduanya atribut alergi yang ADA.
                    // category TETAP dikirim: SATUSEHAT mewajibkannya (RuleNumber 10075),
                    // pemetaannya di AlergiSnomed::kategoriFhir().
                    'type'        => $tidakAdaAlergi ? null : 'allergy',
                    'category'    => AlergiSnomed::kategoriFhir($snomedCode),
                    'criticality' => $tidakAdaAlergi ? null : 'low',
                    'note'        => $alergiText,
                    'onset'       => $onset,
                ]);

                if (empty($respons['id'])) { $this->dispatch('toast', type: 'error', message: 'Alergi gagal: respons tanpa id.'); return; }

                $satuSehat['allergyId'] = $respons['id'];
                $this->saveResult($rjNo, $satuSehat);
                $this->dispatch('toast', type: 'success', message: 'Alergi berhasil dikirim.');
                $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
                return;
            }

            // ── Daftar alergen: satu AllergyIntolerance per alergen ──
            $terkirim = $satuSehat['allergyIds'] ?? [];
            $belumTerkirim = collect($rencana['alergenList'])->filter(fn($baris) => !isset($terkirim[AlergiAlergen::kunci($baris)]));
            if ($belumTerkirim->isEmpty()) { $this->dispatch('toast', type: 'info', message: 'Semua alergen sudah pernah dikirim.'); return; }

            $gagalList = [];
            foreach ($belumTerkirim as $baris) {
                $namaAlergen = AlergiAlergen::namaBaris($baris);
                try {
                    $respons = $this->createAllergyIntolerance([
                        'patientId'   => $patientId,
                        'encounterId' => $satuSehat['encounterId'],
                        'recorderId'  => $recorderId,
                        'codeSystem'  => $baris['sistem'] ?? AlergiAlergen::SISTEM_SNOMED,
                        'code'        => $baris['kode'] ?? '',
                        'display'     => ($baris['display'] ?? '') ?: $namaAlergen,
                        'text'        => $namaAlergen,
                        'type'        => 'allergy',
                        'category'    => $baris['jenis'] ?? 'medication',
                        'criticality' => $baris['keparahan'] ?? 'low',
                        'note'        => $alergiText,
                        'onset'       => $onset,
                    ]);
                    if (empty($respons['id'])) {
                        $gagalList[] = $namaAlergen . ' (respons tanpa id)';
                        continue;
                    }
                    $terkirim[AlergiAlergen::kunci($baris)] = $respons['id'];
                } catch (\Throwable $e) {
                    $gagalList[] = $namaAlergen . ' (' . $e->getMessage() . ')';
                }
            }

            // Yang berhasil tetap disimpan walau sebagian gagal — kirim ulang tak menggandakan.
            $satuSehat['allergyIds'] = $terkirim;
            $this->saveResult($rjNo, $satuSehat);

            empty($gagalList)
                ? $this->dispatch('toast', type: 'success', message: 'Alergi berhasil dikirim (' . count($terkirim) . ' alergen).')
                : $this->dispatch('toast', type: 'error', message: 'Sebagian alergi gagal: ' . implode('; ', $gagalList));
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Alergi gagal: ' . $e->getMessage());
        }
    }

    private function getPatientIHS(string $regNo): string
    {
        if (empty($regNo)) return '';
        return (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '');
    }

    private function getDoctorIHS(string $drId): string
    {
        if (empty($drId)) return '';
        return (string) (DB::table('rsmst_doctors')->where('dr_id', $drId)->value('dr_uuid') ?? '');
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
                <span class="text-sm font-bold">12</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Allergy Intolerance</div>
                <div class="text-xs text-muted dark:text-gray-400">Riwayat alergi pasien (Obat: KFA · Makanan/Lingkungan: SNOMED CT).</div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        {{ $count }}/{{ $jumlahTarget }} terkirim
                    </div>
                @endif
            </div>
        </div>
        <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter"
            class="!bg-teal-600 hover:!bg-teal-700 {{ $count > 0 && $count >= $jumlahTarget ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="kirimForCurrent,kirim">
                    <span class="inline-flex items-center gap-1.5">
                        <x-satu-sehat.ikon-tombol :selesai="$count > 0 && $count >= $jumlahTarget" jenis="kirim" />
                        {{ $count > 0 && $count >= $jumlahTarget ? 'Terkirim' : 'Kirim' }}
                    </span>
                </span>
            <span wire:loading wire:target="kirimForCurrent,kirim"><x-loading />...</span>
        </x-primary-button>
    </div>

    <x-satu-sehat.pratinjau :terbuka="$pratinjauTerbuka"
        :baris="$pratinjauTerbuka ? $this->pratinjau : []"
        kosong="Riwayat alergi belum diisi — Kirim akan ditolak." />
</div>
