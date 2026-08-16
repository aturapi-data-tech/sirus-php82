<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-observasi-lanjutan.blade.php
// Step 13 (RI): Kirim Observasi Lanjutan — pemberian obat/cairan, oksigen, pengeluaran cairan.
//
// Sumber = datadaftarri_json → observasi.{obatDanCairan, pemakaianOksigen, pengeluaranCairan}
//   obatDanCairan.pemberianObatDanCairan[] → MedicationAdministration (KFA via productId)
//   pemakaianOksigen.pemakaianOksigenData[] → Observation (alat + laju aliran)
//   pengeluaranCairan.pengeluaranCairan[]   → Observation (volume urine)
//
// Tanda vital (observasi.observasiLanjutan.tandaVital[]) TIDAK di sini — sudah dikirim kartu 5.
// Pemetaan kode ada di App\Support\Terminologi\ObservasiLanjutanMap.
//
// CATATAN DATA: hanya ~31% baris pemberian obat punya productId (cairan sering diketik
// bebas tanpa pilih master obat) → baris tanpa productId/KFA DILEWATI tapi DIHITUNG &
// dilaporkan ke user, jangan hilang diam-diam.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\ObservationTrait;
use App\Http\Traits\SATUSEHAT\MedicationAdministrationTrait;
use App\Support\Terminologi\ObservasiLanjutanMap;

new class extends Component {
    use EmrRITrait, ObservationTrait, MedicationAdministrationTrait;

    public ?string $riHdrNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;        // total resource terkirim

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat halaman belasan kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /**
     * Entri yang AKAN dikirim, memakai helper yang SAMA dengan kirim().
     * Ketiga jenis ditampilkan terpisah karena masing-masing jadi resource sendiri.
     */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->riHdrNo)) {
            return [];
        }

        $data = $this->findDataRI($this->riHdrNo);
        $baris = [];

        foreach ($this->obatEntries($data) as $urutan => $entri) {
            $baris[] = [
                'label' => 'Obat/cairan ' . ($urutan + 1),
                'nilai' => trim((string) ($entri['namaObatAtauJenisCairan'] ?? '-')),
                'ket' => trim(implode(' · ', array_filter([
                    trim((string) ($entri['dosis'] ?? '')),
                    trim((string) ($entri['rute'] ?? '')),
                    trim((string) ($entri['waktuPemberian'] ?? '')),
                ]))),
            ];
        }
        foreach ($this->oksigenEntries($data) as $urutan => $entri) {
            $baris[] = [
                'label' => 'Oksigen ' . ($urutan + 1),
                'nilai' => trim(trim((string) ($entri['jenisAlatOksigen'] ?? '-')) . ' ' . trim((string) ($entri['dosisOksigen'] ?? ''))),
                'ket' => trim((string) ($entri['tanggalWaktuMulai'] ?? '')),
            ];
        }
        foreach ($this->keluarEntries($data) as $urutan => $entri) {
            $baris[] = [
                'label' => 'Pengeluaran cairan ' . ($urutan + 1),
                'nilai' => trim(trim((string) ($entri['jenisCairan'] ?? ($entri['jenis'] ?? '-'))) . ' ' . trim((string) ($entri['jumlah'] ?? ''))),
                'ket' => trim((string) ($entri['waktuPemberian'] ?? ($entri['tanggalWaktu'] ?? ''))),
            ];
        }

        return $baris;
    }

    public int $obatCount = 0;    // baris pemberian obat siap kirim (ber-KFA)
    public int $obatSkipped = 0;  // baris dilewati (tanpa productId / tanpa KFA)
    public int $oksigenCount = 0;
    public int $keluarCount = 0;

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
        $this->count = count($satuSehat['observasiLanjutanIds'] ?? []);

        $obat = $this->obatEntries($data);
        $siap = $this->enrichKfa($obat);
        $this->obatCount = count($siap);
        $this->obatSkipped = count($obat) - count($siap);
        $this->oksigenCount = count($this->oksigenEntries($data));
        $this->keluarCount = count($this->keluarEntries($data));
    }

    /** @return array<int, array> */
    private function obatEntries(array $data): array
    {
        return $data['observasi']['obatDanCairan']['pemberianObatDanCairan'] ?? [];
    }

    /** @return array<int, array> */
    private function oksigenEntries(array $data): array
    {
        return $data['observasi']['pemakaianOksigen']['pemakaianOksigenData'] ?? [];
    }

    /** @return array<int, array> */
    private function keluarEntries(array $data): array
    {
        return $data['observasi']['pengeluaranCairan']['pengeluaranCairan'] ?? [];
    }

    /**
     * Sisakan hanya baris yang punya productId DAN produknya ber-KFA, lalu lampirkan kode KFA.
     *
     * @return array<int, array>
     */
    private function enrichKfa(array $rows): array
    {
        $productIdList = [];
        foreach ($rows as $entriRiwayat) {
            $productId = trim((string) ($entriRiwayat['productId'] ?? ''));
            if ($productId !== '') {
                $productIdList[$productId] = true;
            }
        }
        if ($productIdList === []) {
            return [];
        }

        $master = DB::table('immst_products')
            ->whereIn('product_id', array_keys($productIdList))
            ->whereRaw('product_id_satusehat IS NOT NULL AND LENGTH(TRIM(product_id_satusehat)) > 0')
            ->get(['product_id', 'product_id_satusehat', 'product_name_satusehat'])
            ->keyBy('product_id');

        $entriList = [];
        foreach ($rows as $entriRiwayat) {
            $productId = trim((string) ($entriRiwayat['productId'] ?? ''));
            if ($productId === '' || !$master->has($productId)) {
                continue;
            }
            $masterObat = $master->get($productId);
            $entriRiwayat['_kfaCode'] = (string) $masterObat->product_id_satusehat;
            $entriRiwayat['_kfaName'] = (string) ($masterObat->product_name_satusehat ?: ($entriRiwayat['namaObatAtauJenisCairan'] ?? ''));
            $entriList[] = $entriRiwayat;
        }

        return $entriList;
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
    #[On('ss-observasi-lanjutan-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        $this->kirimInti($riHdrNo);
        $this->dispatch('ri-satu-sehat.langkah-selesai', langkah: 'observasi-lanjutan');
    }

    public function kirimInti(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $satuSehat = $dataRI['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['observasiLanjutanIds'])) { $this->dispatch('toast', type: 'info', message: 'Observasi lanjutan sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $orgId = (string) env('SATUSEHAT_ORGANIZATION_ID', '');
            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRI['drId'] ?? '')->value('dr_uuid') ?? '');
            $patientName = (string) ($dataRI['regName'] ?? '');

            $obat = $this->enrichKfa($this->obatEntries($dataRI));
            $oksigen = $this->oksigenEntries($dataRI);
            $keluar = $this->keluarEntries($dataRI);

            if (empty($obat) && empty($oksigen) && empty($keluar)) {
                $this->dispatch('toast', type: 'error', message: 'Tidak ada data observasi lanjutan ber-KFA untuk dikirim.');
                return;
            }

            $idList = [];

            // 1) Pemberian obat & cairan → MedicationAdministration
            foreach ($obat as $indeks => $entriObat) {
                $waktu = $this->parseDate((string) ($entriObat['waktuPemberian'] ?? ''))->toIso8601String();
                $dosis = ObservasiLanjutanMap::dosis((string) ($entriObat['dosis'] ?? ''));
                $rute = ObservasiLanjutanMap::rute((string) ($entriObat['rute'] ?? ''));

                $payload = [
                    'medContainedId'    => 'medadm-' . ($entriObat['id'] ?? $indeks),
                    'orgId'             => $orgId,
                    'medicationCode'    => $entriObat['_kfaCode'],
                    'medicationDisplay' => $entriObat['_kfaName'],
                    'patientId'         => $patientId,
                    'patientName'       => $patientName,
                    'encounterId'       => $satuSehat['encounterId'],
                    'effectiveDate'     => $waktu,
                    'performerId'       => $practitionerId,
                ];
                // mad-1: route hanya ikut bila dose ada (dosage wajib punya dose/rate).
                if ($dosis !== null) {
                    $payload['dose'] = $dosis;
                    $payload['dosageText'] = trim((string) ($entriObat['dosis'] ?? '')) ?: null;
                    if ($rute !== null) {
                        $payload['routeCode'] = $rute['code'];
                        $payload['routeDisplay'] = $rute['display'];
                    }
                }
                $respons = $this->createMedicationAdministration($payload);
                if (!empty($respons['id'])) $idList[] = $respons['id'];
            }

            // 2) Oksigen & 3) pengeluaran cairan → Observation
            foreach ([[$oksigen, 'oksigen', 'tanggalWaktuMulai'], [$keluar, 'pengeluaran', 'waktuPengeluaran']] as [$rows, $jenis, $waktuKey]) {
                foreach ($rows as $entri) {
                    $waktu = $this->parseDate((string) ($entri[$waktuKey] ?? ''))->toIso8601String();
                    $payloadDasar = [
                        'patientId'     => $patientId,
                        'encounterId'   => $satuSehat['encounterId'],
                        'performerId'   => $practitionerId,
                        'effectiveDate' => $waktu,
                    ];
                    $obsList = $jenis === 'oksigen'
                        ? ObservasiLanjutanMap::oksigen($entri)
                        : ObservasiLanjutanMap::pengeluaran($entri);
                    foreach ($obsList as $observation) {
                        $respons = $this->createObservation(array_merge($payloadDasar, $observation));
                        if (!empty($respons['id'])) $idList[] = $respons['id'];
                    }
                }
            }

            if (empty($idList)) { $this->dispatch('toast', type: 'error', message: 'Tidak ada nilai observasi lanjutan valid untuk dikirim.'); return; }

            $satuSehat['observasiLanjutanIds'] = $idList;
            $this->saveResult($riHdrNo, $satuSehat);

            $pesan = 'Observasi lanjutan berhasil dikirim (' . count($idList) . ' resource).';
            if ($this->obatSkipped > 0) {
                $pesan .= ' ' . $this->obatSkipped . ' baris obat dilewati (tanpa productId/KFA).';
            }
            $this->dispatch('toast', type: 'success', message: $pesan);
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            // Simpan dulu yang sudah TERLANJUR terbentuk di SATUSEHAT sebelum melapor
            // gagal. Tanpa ini id-nya hangus padahal resource-nya SUDAH ada di sana,
            // lalu percobaan berikutnya menumpuk resource yatim — persis penyebab
            // diagnosa macet permanen dulu (lihat sender Condition). Dibungkus try
            // sendiri supaya kegagalan menyimpan tidak menutupi error aslinya.
            try { if (isset($satuSehat)) { $this->saveResult($riHdrNo, $satuSehat); } } catch (\Throwable) {}
            $this->dispatch('toast', type: 'error', message: 'Observasi lanjutan gagal: ' . $e->getMessage());
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
                <span class="text-sm font-bold">13</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Observasi Lanjutan</div>
                <div class="text-xs text-muted dark:text-gray-400">
                    Pemberian obat &amp; cairan, oksigen, pengeluaran cairan.
                    @if ($obatCount > 0 || $oksigenCount > 0 || $keluarCount > 0)
                        <span class="text-muted-soft">{{ $obatCount }} obat, {{ $oksigenCount }} oksigen, {{ $keluarCount }} pengeluaran.</span>
                    @endif
                    @if ($obatSkipped > 0)
                        <span class="text-amber-600 dark:text-amber-400">{{ $obatSkipped }} baris obat tanpa KFA dilewati.</span>
                    @endif
                </div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        {{ $count }} terkirim
                    </div>
                @endif
            </div>
        </div>
        <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter"
            class="!bg-teal-600 hover:!bg-teal-700 {{ $count > 0 ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="kirimForCurrent">{{ $count > 0 ? 'Terkirim' : 'Kirim' }}</span>
            <span wire:loading wire:target="kirimForCurrent"><x-loading />...</span>
        </x-primary-button>
    </div>

    <x-satu-sehat.pratinjau :terbuka="$pratinjauTerbuka"
        :baris="$pratinjauTerbuka ? $this->pratinjau : []"
        kosong="Belum ada entri observasi lanjutan (obat/cairan, oksigen, pengeluaran) — Kirim akan ditolak." />
</div>
