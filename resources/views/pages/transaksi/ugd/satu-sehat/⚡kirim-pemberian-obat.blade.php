<?php
// resources/views/pages/transaksi/ugd/satu-sehat/kirim-pemberian-obat.blade.php
// Kirim Pemberian Obat (MedicationAdministration, KFA) — UGD, Playbook IGD bab 21.
// Sumber: observasi.obatDanCairan.pemberianObatDanCairan[] (modul Obat & Cairan UGD) — bentuknya SAMA
// dengan RI, jadi dosis & rute memakai App\Support\Terminologi\ObservasiLanjutanMap yang juga dipakai
// kirim-observasi-lanjutan RI (jebakan satuan "gr" = gram & aturan mad-1 ikut terbawa).
// Hasil: satusehat.pemberianObatIds = [id entri => id MedicationAdministration]; entri yang sudah
// terkirim tak dikirim ulang. Baris tanpa productId/KFA DILEWATI tapi dihitung & dilaporkan.
// Disamakan dgn alur apikesehatan.com: identifier resmi (UGD-{rjNo}-{id entri}), category inpatient,
// dan request → MedicationRequest bila obat yang sama ada di resep terkirim (medicationRequestItems).
// OPSIONAL — tanpa entri ber-KFA = dilewati.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\SATUSEHAT\MedicationAdministrationTrait;
use App\Support\Terminologi\ObservasiLanjutanMap;

new class extends Component {
    use EmrUGDTrait, MedicationAdministrationTrait;

    public ?string $rjNo = null;

    /** Mode pratinjau saja (layar Coding SATUSEHAT): tombol Kirim disembunyikan, kirim ditolak. */
    public bool $hanyaPratinjau = false;
    public bool $hasEncounter = false;
    public int $count = 0;
    public int $siapKirim = 0;
    public int $dilewati = 0;

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat halaman belasan kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /** Isi yang AKAN dikirim, dari sumber & penyaring yang SAMA dengan kirim(). */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->rjNo)) {
            return [];
        }

        $data = $this->findDataUGD($this->rjNo);
        $terkirim = $data['satusehat']['pemberianObatIds'] ?? [];
        $siap = $this->denganKfa($this->entriObat($data));
        $baris = [];
        foreach ($this->entriObat($data) as $urutan => $entri) {
            $kunci = $this->kunciEntri($entri, $urutan);
            $baris[] = [
                'label' => trim((string) ($entri['namaObatAtauJenisCairan'] ?? '-')),
                'nilai' => trim(implode(' · ', array_filter([trim((string) ($entri['dosis'] ?? '')), trim((string) ($entri['rute'] ?? ''))]))),
                'ket' => trim((string) ($entri['waktuPemberian'] ?? '')) . ' — ' . match (true) {
                    isset($terkirim[$kunci]) => 'sudah terkirim',
                    isset($siap[$kunci]) => 'akan dikirim (KFA ' . $siap[$kunci]['_kfaCode'] . ')',
                    default => 'dilewati: tanpa obat master ber-KFA',
                },
            ];
        }

        return $baris;
    }

    public function mount(?string $rjNo = null, bool $hanyaPratinjau = false): void
    {
        $this->hanyaPratinjau = $hanyaPratinjau;
        $this->pratinjauTerbuka = $hanyaPratinjau;
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
        $this->count = count($satuSehat['pemberianObatIds'] ?? []);
        $semua = $this->entriObat($data);
        $this->siapKirim = count($this->denganKfa($semua));
        $this->dilewati = count($semua) - $this->siapKirim;
    }

    /** @return array<int, array> */
    private function entriObat(array $data): array
    {
        return array_values((array) ($data['observasi']['obatDanCairan']['pemberianObatDanCairan'] ?? []));
    }

    /** Kunci pencatatan per entri: id entri (selalu ada sejak modul Obat & Cairan), cadangan urutan. */
    private function kunciEntri(array $entri, int $urutan): string
    {
        return (string) (($entri['id'] ?? '') ?: 'urutan-' . $urutan);
    }

    /**
     * Entri yang punya productId DAN produknya ber-KFA, dengan kode KFA terlampir.
     *
     * @return array<string, array> kunci = kunciEntri()
     */
    private function denganKfa(array $entriList): array
    {
        $productIdList = collect($entriList)->pluck('productId')->map(fn($id) => trim((string) $id))->filter()->unique()->values()->all();
        if ($productIdList === []) {
            return [];
        }

        $master = DB::table('immst_products')
            ->whereIn('product_id', $productIdList)
            ->whereRaw('product_id_satusehat IS NOT NULL AND LENGTH(TRIM(product_id_satusehat)) > 0')
            ->get(['product_id', 'product_id_satusehat', 'product_name_satusehat'])
            ->keyBy('product_id');

        $hasil = [];
        foreach ($entriList as $urutan => $entri) {
            $productId = trim((string) ($entri['productId'] ?? ''));
            if ($productId === '' || !$master->has($productId)) {
                continue;
            }
            $masterObat = $master->get($productId);
            $hasil[$this->kunciEntri($entri, $urutan)] = [
                ...$entri,
                '_kfaCode' => (string) $masterObat->product_id_satusehat,
                '_kfaName' => (string) ($masterObat->product_name_satusehat ?: ($entri['namaObatAtauJenisCairan'] ?? '')),
            ];
        }

        return $hasil;
    }

    public function kirimForCurrent(): void
    {
        if ($this->hanyaPratinjau) {
            return;
        }
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    /**
     * Pembungkus untuk rantai "Kirim Semua": apa pun hasilnya langkah ini WAJIB memberi
     * kabar, supaya orkestrator bisa melanjutkan ke langkah berikutnya.
     */
    #[On('ss-pemberian-obat-ugd.kirim')]
    public function kirim(string $rjNo): void
    {
        if ($this->hanyaPratinjau) {
            return;
        }
        $this->kirimInti($rjNo);
        $this->dispatch('ugd-satu-sehat.langkah-selesai', langkah: 'pemberian-obat');
    }

    public function kirimInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            if (empty($dataUGD)) { $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.'); return; }

            $satuSehat = $dataUGD['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }

            $semua = $this->entriObat($dataUGD);
            $terkirim = $satuSehat['pemberianObatIds'] ?? [];
            $antrean = array_diff_key($this->denganKfa($semua), $terkirim);
            $jumlahDilewati = count($semua) - count($this->denganKfa($semua));
            if (empty($antrean)) {
                $this->dispatch('toast', type: 'info', message: match (true) {
                    $terkirim !== [] => 'Semua pemberian obat ber-KFA sudah pernah dikirim.',
                    $semua !== [] => 'Pemberian obat belum punya kode KFA (pilih obat dari master ber-KFA) — dilewati.',
                    default => 'Belum ada pemberian obat di modul Obat & Cairan (opsional), dilewati.',
                });
                return;
            }

            $patientId = $this->getPatientIHS($dataUGD['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $orgId = (string) env('SATUSEHAT_ORGANIZATION_ID', '');
            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataUGD['drId'] ?? '')->value('dr_uuid') ?? '');
            $patientName = (string) ($dataUGD['regName'] ?? '');
            // Resep non-racikan terkirim, berkunci productId → id MedicationRequest.
            $resepPerProduk = collect($satuSehat['medicationRequestItems'] ?? [])
                ->filter(fn($item) => ($item['jenis'] ?? '') === 'nonRacikan' && filled($item['id'] ?? null))
                ->mapWithKeys(fn($item) => [(string) ($item['kunci'] ?? '') => (string) $item['id']])
                ->all();

            $gagalList = [];
            foreach ($antrean as $kunci => $entri) {
                $namaObat = trim((string) ($entri['namaObatAtauJenisCairan'] ?? '')) ?: $entri['_kfaName'];
                $dosis = ObservasiLanjutanMap::dosis((string) ($entri['dosis'] ?? ''));
                $rute = ObservasiLanjutanMap::rute((string) ($entri['rute'] ?? ''));

                $payload = [
                    'medContainedId'    => 'medadm-' . $kunci,
                    'orgId'             => $orgId,
                    'medicationCode'    => $entri['_kfaCode'],
                    'medicationDisplay' => $entri['_kfaName'],
                    'patientId'         => $patientId,
                    'patientName'       => $patientName,
                    'encounterId'       => $satuSehat['encounterId'],
                    'effectiveDate'     => $this->parseDate((string) ($entri['waktuPemberian'] ?? ''))->toIso8601String(),
                    'performerId'       => $practitionerId,
                    'identifierValue'   => 'UGD-' . $rjNo . '-' . $kunci,
                    'category'          => 'inpatient',
                    'requestId'         => $resepPerProduk[trim((string) ($entri['productId'] ?? ''))] ?? null,
                ];
                // mad-1: route hanya ikut bila dose ada (dosage wajib punya dose/rate).
                if ($dosis !== null) {
                    $payload['dose'] = $dosis;
                    $payload['dosageText'] = trim((string) ($entri['dosis'] ?? '')) ?: null;
                    if ($rute !== null) {
                        $payload['routeCode'] = $rute['code'];
                        $payload['routeDisplay'] = $rute['display'];
                    }
                }

                try {
                    $respons = $this->createMedicationAdministration($payload);
                    if (empty($respons['id'])) {
                        $gagalList[] = $namaObat . ' (respons tanpa id)';
                        continue;
                    }
                    $terkirim[$kunci] = $respons['id'];
                } catch (\Throwable $e) {
                    $gagalList[] = $namaObat . ' (' . $e->getMessage() . ')';
                }
            }

            // Yang berhasil tetap disimpan walau sebagian gagal — kirim ulang tak menggandakan.
            $satuSehat['pemberianObatIds'] = $terkirim;
            $this->saveResult($rjNo, $satuSehat);

            $catatanLewat = $jumlahDilewati > 0 ? ' ' . $jumlahDilewati . ' baris tanpa KFA dilewati.' : '';
            empty($gagalList)
                ? $this->dispatch('toast', type: 'success', message: 'Pemberian obat berhasil dikirim (' . count($antrean) . ' entri).' . $catatanLewat)
                : $this->dispatch('toast', type: 'error', message: 'Sebagian pemberian obat gagal: ' . implode('; ', $gagalList) . '.' . $catatanLewat);
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Pemberian obat gagal: ' . $e->getMessage());
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

<div class="{{ $hanyaPratinjau
    ? 'p-3 border border-brand-green/30 rounded-xl bg-brand-green/5 dark:bg-brand-green/10 dark:border-brand-green/50'
    : 'p-4 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700' }}">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            @unless ($hanyaPratinjau)
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ $count > 0 ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">14</span>
            </div>
            @endunless
            <div>
                <div class="{{ $hanyaPratinjau ? 'text-xs font-semibold tracking-wide uppercase text-brand-green dark:text-brand-lime' : 'font-semibold text-ink dark:text-gray-100' }}">Pemberian Obat</div>
                <div class="text-xs text-muted dark:text-gray-400">
                    Obat &amp; cairan yang diberikan di UGD (KFA), opsional.
                    @if ($dilewati > 0)
                        <span class="text-warning-deep dark:text-amber-200">{{ $dilewati }} baris tanpa KFA dilewati.</span>
                    @endif
                </div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        {{ $count }}/{{ $siapKirim }} entri terkirim
                    </div>
                @endif
            </div>
        </div>
        @unless ($hanyaPratinjau)
        <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter"
            class="!bg-teal-600 hover:!bg-teal-700 {{ $count > 0 && $count >= $siapKirim ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="kirimForCurrent,kirim">
                <span class="inline-flex items-center gap-1.5">
                    <x-satu-sehat.ikon-tombol :selesai="$count > 0 && $count >= $siapKirim" jenis="kirim" />
                    {{ $count > 0 && $count >= $siapKirim ? 'Terkirim' : 'Kirim' }}
                </span>
            </span>
            <span wire:loading wire:target="kirimForCurrent,kirim"><x-loading />...</span>
        </x-primary-button>
        @endunless
    </div>

    <x-satu-sehat.pratinjau :terbuka="$pratinjauTerbuka" :ringkas="$hanyaPratinjau"
        :baris="$pratinjauTerbuka ? $this->pratinjau : []"
        kosong="Belum ada pemberian obat di modul Obat dan Cairan UGD (opsional)." />
</div>
