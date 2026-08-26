<?php
// resources/views/pages/transaksi/ugd/satu-sehat/kirim-encounter.blade.php
// Step 1: Kirim Kunjungan UGD (Encounter, class EMER).
// BEDA dari RJ: class EMER; rstxn_ugdhdrs.poli_id TIDAK dipakai untuk lokasi —
//   kolomnya kosong 85% baris dan yang terisi menunjuk macam-macam poli asal
//   pasien, bukan "pasien sedang di UGD". Lokasi selalu poli UGD di rsmst_polis
//   (satu baris tetap, poli_desc = 'UGD'), env SATUSEHAT_IGD_LOCATION_ID boleh
//   override untuk sandbox.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\SATUSEHAT\EncounterTrait;
use App\Http\Traits\SATUSEHAT\SatuSehatRujukanTrait;
use App\Http\Traits\Txn\RujukanMasuk\RujukanMasukTrait;

new class extends Component {
    use EmrUGDTrait, EncounterTrait, SatuSehatRujukanTrait, RujukanMasukTrait;

    public ?string $rjNo = null;

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat halaman belasan kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /**
     * Identitas kunjungan yang AKAN dikirim. Tanggal ditampilkan apa adanya dari
     * basis data — kalau kosong, di situlah Kirim akan berhenti, dan pratinjau ini
     * memperlihatkan sebabnya sebelum tombol ditekan.
     */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->rjNo)) {
            return [];
        }

        $data = $this->findDataUGD($this->rjNo);
        $tanggal = trim((string) ($data['rjDate'] ?? ''));

        return [
            ['label' => 'Pasien', 'nilai' => (string) ($data['regName'] ?? '-'),
             'ket' => 'No. RM ' . ($data['regNo'] ?? '-')],
            ['label' => 'Dokter', 'nilai' => (string) ($data['drDesc'] ?? '-')],
            ['label' => 'Waktu mulai (period.start)',
             'nilai' => $tanggal ?: '(KOSONG — Kirim akan ditolak)',
             'ket' => $tanggal ? 'dibekukan di SATUSEHAT begitu Encounter terbentuk' : 'betulkan dulu di pendaftaran'],
            ['label' => 'Kelas kunjungan', 'nilai' => 'EMER (gawat darurat)'],
        ];
    }

    public ?string $encounterId = null;
    public bool $encounterInProgress = false;
    public bool $encounterFinished = false;

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
        $this->encounterId = $satuSehat['encounterId'] ?? null;
        $this->encounterInProgress = !empty($satuSehat['encounterInProgress']);
        $this->encounterFinished = !empty($satuSehat['encounterFinished']);
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    public function finishForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->finish($this->rjNo);
        $this->reloadState();
    }

    /**
     * Pembungkus untuk rantai "Kirim Semua": apa pun hasilnya — berhasil, ditolak
     * SATUSEHAT, atau berhenti di guard — langkah ini WAJIB memberi kabar, supaya
     * orkestrator bisa melanjutkan. Tanpa ini rantai menggantung diam-diam pada
     * langkah pertama yang gagal, dan petugas cuma melihat modal yang membeku.
     */
    #[On('ss-encounter-ugd.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('ugd-satu-sehat.langkah-selesai', langkah: 'encounter');
    }

    public function kirimInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            if (empty($dataUGD)) { $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.'); return; }
            $satuSehat = $dataUGD['satusehat'] ?? [];

            $regNo = $dataUGD['regNo'] ?? '';
            $patientId = $regNo ? (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '') : '';
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS kosong. Daftarkan pasien ke SATUSEHAT via Master Pasien.'); return; }

            $drId = $dataUGD['drId'] ?? '';
            $practitionerId = $drId ? (string) (DB::table('rsmst_doctors')->where('dr_id', $drId)->value('dr_uuid') ?? '') : '';
            if (empty($practitionerId)) { $this->dispatch('toast', type: 'error', message: 'Dokter IHS (dr_uuid) kosong.'); return; }

            // Lokasi UGD: selalu poli UGD di rsmst_polis; env boleh override.
            $locationId = $this->resolveUgdLocation();
            if (empty($locationId)) { $this->dispatch('toast', type: 'error', message: 'Location IHS IGD kosong. Set env SATUSEHAT_IGD_LOCATION_ID atau poli_uuid IGD.'); return; }

            // Tanggal masuk kosong = parseDate() diam-diam memakai now(), sehingga
            // period.start terisi jam petugas menekan tombol lalu DIBEKUKAN di SATUSEHAT.
            // (Alasan lengkap di sender Encounter RJ.)
            $tanggalMasuk = trim((string) ($dataUGD['rjDate'] ?? ''));
            if ($tanggalMasuk === '') {
                $this->dispatch('toast', type: 'error',
                    message: 'Tanggal masuk UGD (rj_date) kosong — betulkan dulu di pendaftaran sebelum kirim Encounter.');
                return;
            }

            $ugdDate = $this->parseDate($tanggalMasuk);

            if (empty($satuSehat['encounterId'])) {
                $respons = $this->createNewEncounter([
                    'encounterId' => 'UGD-' . $rjNo,
                    'patientId' => $patientId,
                    'patientName' => $dataUGD['regName'] ?? '',
                    'practitionerId' => $practitionerId,
                    'practitionerName' => $dataUGD['drDesc'] ?? '',
                    'locationId' => $locationId,
                    'class_code' => 'EMER',
                    'startDate' => $ugdDate->toIso8601String(),
                    'serviceRequestId' => $this->serviceRequestRujukan($rjNo, $dataUGD),
                ]);
                $satuSehat['encounterId'] = $respons['id'] ?? null;
            }

            if (!empty($satuSehat['encounterId']) && empty($satuSehat['encounterInProgress'])) {
                $this->startRoomEncounter($satuSehat['encounterId'], [
                    'startDate' => $ugdDate->toIso8601String(),
                    'locationId' => $locationId,
                ]);
                $satuSehat['encounterInProgress'] = true;
            }

            $this->saveResult($rjNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'Encounter UGD dikirim: ' . ($satuSehat['encounterId'] ?? '-'));
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Encounter gagal: ' . $e->getMessage());
        }
    }

    /** Pembungkus rantai — lihat catatan di kirim(). */
    #[On('ss-encounter-ugd.finish')]
    public function finish(string $rjNo): void
    {
        $this->finishInti($rjNo);
        $this->dispatch('ugd-satu-sehat.langkah-selesai', langkah: 'encounter-selesai');
    }

    public function finishInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            $satuSehat = $dataUGD['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Encounter belum dibuat.'); return; }
            if (!empty($satuSehat['encounterFinished'])) { $this->dispatch('toast', type: 'info', message: 'Encounter sudah finished.'); return; }

            // Waktu selesai = jam layanan berakhir, bukan now() (jam petugas mengklik).
            // UGD TIDAK memakai task 5 — probe 150 kunjungan: task5 terisi 0x, sedangkan
            // "Selesai Pemeriksaan" (perencanaan.pengkajianMedis) terisi 91x.
            $task = $dataUGD['taskIdPelayanan'] ?? [];
            $waktuSelesai = trim((string) ($task['taskId7'] ?? ''))
                ?: trim((string) ($dataUGD['perencanaan']['pengkajianMedis']['selesaiPemeriksaan'] ?? ''));
            $akhirIso = $waktuSelesai !== ''
                ? $this->parseDate($waktuSelesai)->toIso8601String()
                : now()->toIso8601String();

            // Encounter.diagnosis wajib (RuleNumber 10457) dan harus merujuk Condition yang
            // sudah dikirim — tolak lebih dulu, jangan kirim lalu pasti ditolak server.
            $conditionIdList = $satuSehat['conditionIds'] ?? [];
            if (empty($conditionIdList)) {
                $this->dispatch('toast', type: 'error',
                    message: 'Kirim Diagnosa (Condition) dulu — SATUSEHAT mewajibkan Encounter.diagnosis saat finish.');
                return;
            }

            // statusHistory tiap entri wajib start+end (Rule 10122) — dirapikan di trait.
            $encounterTersimpan = $this->siapkanFinishEncounter(
                $this->getEncounter($satuSehat['encounterId']), $akhirIso, $conditionIdList
            );
            $this->makeRequest('put', "Encounter/{$satuSehat['encounterId']}", $encounterTersimpan);

            $satuSehat['encounterFinished'] = true;

            $this->saveResult($rjNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'Encounter finished.');
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Finish Encounter gagal: ' . $e->getMessage());
        }
    }

    private function resolveUgdLocation(): string
    {
        $poliUuid = (string) (DB::table('rsmst_polis')->whereRaw('UPPER(poli_desc) = ?', ['UGD'])->value('poli_uuid') ?? '');

        return (string) (env('SATUSEHAT_IGD_LOCATION_ID') ?: $poliUuid);
    }

    /**
     * Nomor ServiceRequest rujukan untuk `Encounter.basedOn` — dipungut di sini,
     * bukan diterima saat pendaftaran.
     *
     * Pasien rujukan masuk WAJIB punya `basedOn` yang menunjuk rujukan resminya
     * (aturan Kemkes 19/08/26), tapi rujukan itu baru diterbitkan perujuk SESUDAH
     * kita menyetujui permintaannya — bisa jadi setelah pasiennya sudah didaftarkan.
     * Karena itu pencariannya ditunda sampai detik Encounter dibuat: itu saat
     * paling akhir yang masih berguna, sekaligus paling besar peluang rujukannya
     * sudah terbit.
     *
     * KUNJUNGAN BIASA TIDAK TERSENTUH: tanpa node `rujukanMasuk` (pasien datang
     * sendiri) fungsi ini langsung mengembalikan string kosong tanpa satu pun
     * panggilan API.
     *
     * Kalau rujukan resminya belum terbit, Encounter TETAP dikirim tanpa basedOn —
     * menahan kunjungan karena dokumen di sistem RS lain belum ada akan menghentikan
     * pelayanan pasien yang sudah ada di depan mata. Konsekuensinya disebut apa
     * adanya lewat toast: basedOn tak bisa ditambal belakangan tanpa PUT Encounter,
     * dan itu belum dibangun.
     */
    private function serviceRequestRujukan(string $rjNo, array $dataUGD): string
    {
        $rujukanMasuk = (array) ($dataUGD['rujukanMasuk'] ?? []);

        if ($rujukanMasuk === []) {
            return '';
        }

        $tersimpan = trim((string) ($rujukanMasuk['serviceRequestId'] ?? ''));

        if ($tersimpan !== '') {
            return $tersimpan;
        }

        $hasil = $this->rujukanCariServiceRequestMasuk([
            'rencanaId' => (string) ($rujukanMasuk['rencanaId'] ?? ''),
            'taskId' => (string) ($rujukanMasuk['taskId'] ?? ''),
            'pasienIhs' => (string) ($rujukanMasuk['pasienIhs'] ?? ''),
        ]);

        if (! $hasil['ditemukan']) {
            $this->dispatch('toast', type: 'warning', message: 'Rujukan resmi belum bisa dipungut: ' . $hasil['pesan'] . ' Encounter dikirim TANPA basedOn — hubungi RS perujuk bila rujukannya memang belum diterbitkan.', duration: 10000);

            return '';
        }

        // Dua tempat, dua kegunaan: di kunjungan supaya pengiriman berikutnya tak
        // mencari lagi, di janji rujukan supaya jejaknya tetap ada walau kunjungannya
        // kelak dihapus.
        try {
            DB::transaction(function () use ($rjNo, $hasil): void {
                $this->lockUGDRow($rjNo);
                $data = $this->findDataUGD($rjNo);
                $data['rujukanMasuk']['serviceRequestId'] = $hasil['serviceRequestId'];
                $data['rujukanMasuk']['noRujukan'] = $hasil['noRujukan'];
                $this->updateJsonUGD((int) $rjNo, $data);
            });
        } catch (\Throwable $e) {
            // Gagal menyimpan bukan alasan membatalkan basedOn yang sudah di tangan:
            // nomornya benar, cuma tak tercatat — pencarian berikutnya mengulang.
            $this->dispatch('toast', type: 'warning', message: 'Rujukan resmi ditemukan tapi gagal dicatat di kunjungan: ' . $e->getMessage());
        }

        $nomorJanji = (int) ($rujukanMasuk['rujukanMasukNo'] ?? 0);

        if ($nomorJanji > 0) {
            $this->simpanRujukanResmiJanji($nomorJanji, $hasil['serviceRequestId'], $hasil['noRujukan']);
        }

        return $hasil['serviceRequestId'];
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
                class="flex items-center justify-center w-8 h-8 rounded-full {{ $encounterId ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">1</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Encounter <span class="text-xs font-normal text-muted">(EMER)</span></div>
                <div class="text-xs text-muted dark:text-gray-400">Kunjungan UGD — akar, wajib pertama.</div>
                @if ($encounterId)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        {{ $encounterFinished ? 'finished' : ($encounterInProgress ? 'in-progress' : 'arrived') }}
                    </div>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-2">
            <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled"
                class="!bg-teal-600 hover:!bg-teal-700 {{ $encounterId ? '!bg-emerald-600' : '' }}">
                <span wire:loading.remove wire:target="kirimForCurrent,kirim">
                    <span class="inline-flex items-center gap-1.5">
                        <x-satu-sehat.ikon-tombol :selesai="$encounterId" jenis="kirim" />
                        {{ $encounterId ? 'Terkirim' : 'Kirim' }}
                    </span>
                </span>
                <span wire:loading wire:target="kirimForCurrent,kirim"><x-loading />...</span>
            </x-primary-button>
            @if ($encounterId && !$encounterFinished)
                <x-secondary-button type="button" wire:click="finishForCurrent" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="finishForCurrent,finish"><span class="inline-flex items-center gap-1.5"><x-satu-sehat.ikon-tombol jenis="finish" />Finish</span></span>
                    <span wire:loading wire:target="finishForCurrent,finish"><x-loading />...</span>
                </x-secondary-button>
            @endif
        </div>
    </div>

    <x-satu-sehat.pratinjau :terbuka="$pratinjauTerbuka"
        :baris="$pratinjauTerbuka ? $this->pratinjau : []"
        kosong="Data kunjungan belum lengkap — lihat pesan saat menekan Kirim." />
</div>
