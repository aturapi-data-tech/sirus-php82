<?php
// resources/views/pages/transaksi/ugd/satu-sehat/kirim-encounter.blade.php
// Step 1: Kirim Kunjungan UGD (Encounter, class EMER).
// BEDA dari RJ: class EMER; UGD tanpa poli (insert poli_id=null) → lokasi dari
//   rstxn_ugdhdrs.poli_id bila ada, jika tidak dari env SATUSEHAT_IGD_LOCATION_ID.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\EncounterTrait;
use App\Http\Traits\SATUSEHAT\RujukanIgdTrait;
use App\Support\Terminologi\DischargeDisposition;
use App\Support\Terminologi\KedatanganIgd;

new class extends Component {
    // EmrRJTrait HANYA untuk RujukanIgdTrait (baca/tulis dokumen RJ asal) — pola sama
    // transfer-rj-ke-ugd-actions yang memakai EmrRJTrait + EmrUGDTrait bersamaan.
    use EmrUGDTrait, EmrRJTrait, EncounterTrait, RujukanIgdTrait;

    /** Kelas layanan IGD — WAJIB di Playbook IGD (serviceClass bertanda *); IGD selalu reguler. */
    private const KELAS_LAYANAN_IGD = [
        'system' => 'http://terminology.kemkes.go.id/CodeSystem/locationServiceClass-Outpatient',
        'code' => 'reguler',
        'display' => 'Kelas Reguler',
    ];

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
    /**
     * Cara keluar yang akan ikut saat Finish (dari Tindak Lanjut di Perencanaan UGD).
     * null = belum diisi → kartu memperingatkan SEBELUM tombol Finish ditekan.
     */
    #[Computed]
    public function caraKeluar(): ?string
    {
        if (empty($this->rjNo)) {
            return null;
        }

        $tindakLanjut = $this->findDataUGD($this->rjNo)['perencanaan']['tindakLanjut'] ?? [];

        return DischargeDisposition::gawatDarurat(
            $tindakLanjut['tindakLanjut'] ?? null, $tindakLanjut['keteranganTindakLanjut'] ?? null
        )['text'] ?? null;
    }

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
    public bool $encounterTriaged = false;
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
        $this->encounterTriaged = !empty($satuSehat['encounterTriaged']);
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

            // Lokasi UGD: coba poli_id dari header (kadang null) → poli_uuid; fallback env IGD.
            $locationId = $this->resolveUgdLocation($rjNo);
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

            // Cek tautan transfer kasir dulu: pasien pindahan RJ → basedOn rujukan ke IGD dari
            // Encounter RJ; tidak → Encounter biasa. Pola sama Encounter RI ← UGD.
            $catatanTautan = '';
            if (empty($satuSehat['encounterId'])) {
                $tautan = $this->rujukanIgdDariRj($rjNo, $this->waktuMasukUgdIso($dataUGD, $ugdDate), $practitionerId, (string) ($dataUGD['drDesc'] ?? ''));
                $catatanTautan = $tautan['catatan'];
                if ($tautan['id'] !== null) {
                    $satuSehat['rujukanIgdId'] = $tautan['id'];
                    $catatanTautan = 'tertaut ke Rawat Jalan (RJ #' . $tautan['rjNo'] . ')';
                }

                $respons = $this->createNewEncounter([
                    'basedOn' => $tautan['id'],
                    'encounterId' => 'UGD-' . $rjNo,
                    'patientId' => $patientId,
                    'patientName' => $dataUGD['regName'] ?? '',
                    'practitionerId' => $practitionerId,
                    'practitionerName' => $dataUGD['drDesc'] ?? '',
                    'locationId' => $locationId,
                    'class_code' => 'EMER',
                    'startDate' => $ugdDate->toIso8601String(),
                    'kelasLayanan' => self::KELAS_LAYANAN_IGD,
                ]);
                $satuSehat['encounterId'] = $respons['id'] ?? null;
            }

            $catatanTriase = '';
            if (!empty($satuSehat['encounterId']) && empty($satuSehat['encounterInProgress'])) {
                $waktuDatangIso = $ugdDate->toIso8601String();

                // Status triaged = penanda khas IGD (arrived → triaged → in-progress → finished).
                // Jam = TTD Screening (instrumen triase); cadangan jam datang Pengkajian Perawat —
                // jam yang sama dipakai Observation triase di kartu Kedatangan. Urutan dijaga:
                // tak boleh mendahului arrived. Tanpa tingkat/jam triase → dilewati + dilaporkan.
                $waktuTriaseIso = '';
                if (empty($satuSehat['encounterTriaged'])) {
                    $tingkatTriase = KedatanganIgd::tingkatTriase($dataUGD)['tingkat'];
                    $jamTriase = trim((string) ($dataUGD['screening']['tanggalPelayanan'] ?? ''))
                        ?: trim((string) ($dataUGD['anamnesa']['pengkajianPerawatan']['jamDatang'] ?? ''));
                    if ($tingkatTriase !== '' && $jamTriase !== '') {
                        $waktuTriaseIso = $this->waktuTerakhir($waktuDatangIso, $this->parseDate($jamTriase)->toIso8601String());
                        $this->tambahStatusEncounter($satuSehat['encounterId'], 'triaged', $waktuTriaseIso);
                        $satuSehat['encounterTriaged'] = true;
                        // Simpan segera: bila in-progress di bawah gagal, kirim ulang tak menambah triaged dobel.
                        $this->saveResult($rjNo, $satuSehat);
                    } else {
                        $catatanTriase = ' — TANPA status triaged: tingkat/jam triase belum diisi (Screening / Pengkajian Perawat).';
                    }
                }

                // in-progress = jam dokter memeriksa; belum ada → jam triase/datang. Tak boleh mendahului triaged.
                $jamPeriksa = trim((string) ($dataUGD['perencanaan']['pengkajianMedis']['waktuPemeriksaan'] ?? ''));
                $waktuMulaiIso = $this->waktuTerakhir(
                    $waktuTriaseIso ?: $waktuDatangIso,
                    $jamPeriksa !== '' ? $this->parseDate($jamPeriksa)->toIso8601String() : ''
                );
                $this->startRoomEncounter($satuSehat['encounterId'], [
                    'startDate' => $waktuMulaiIso,
                    'locationId' => $locationId,
                ]);
                $satuSehat['encounterInProgress'] = true;
            }

            $this->saveResult($rjNo, $satuSehat);
            $tautanBelum = $catatanTautan !== '' && empty($satuSehat['rujukanIgdId']);
            $this->dispatch('toast', type: ($catatanTriase === '' && !$tautanBelum) ? 'success' : 'warning',
                message: 'Encounter UGD dikirim: ' . ($satuSehat['encounterId'] ?? '-') . $catatanTriase
                    . ($catatanTautan !== '' ? ' — ' . $catatanTautan : ''));
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
            if ($waktuSelesai === '') {
                $this->dispatch('toast', type: 'error',
                    message: 'Jam selesai layanan (task 7 / Selesai Pemeriksaan) belum tercatat — Encounter tidak di-finish (jam sekarang tidak relevan).');
                return;
            }
            $akhirIso = $this->parseDate($waktuSelesai)->toIso8601String();

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
                $this->getEncounter($satuSehat['encounterId']), $akhirIso, $conditionIdList,
                $satuSehat['chiefComplaintId'] ?? null
            );
            // Encounter UGD yang dibuat sebelum tautan ada (atau saat Encounter RJ belum dikirim):
            // coba tautkan sekarang. PUT di bawah membawa seluruh Encounter, termasuk basedOn.
            if (empty($encounterTersimpan['basedOn'])) {
                $dokterUgdId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataUGD['drId'] ?? '')->value('dr_uuid') ?? '');
                $tautan = $this->rujukanIgdDariRj($rjNo,
                    $this->waktuMasukUgdIso($dataUGD, $this->parseDate((string) ($dataUGD['rjDate'] ?? ''))), $dokterUgdId, (string) ($dataUGD['drDesc'] ?? ''));
                if ($tautan['id'] !== null) {
                    $encounterTersimpan['basedOn'] = [['reference' => 'ServiceRequest/' . $tautan['id']]];
                    $satuSehat['rujukanIgdId'] = $tautan['id'];
                }
            }

            // Encounter lama (dibuat sebelum kelas layanan dipasang) dilengkapi di sini.
            $encounterTersimpan = $this->pasangKelasLayanan($encounterTersimpan,
                self::KELAS_LAYANAN_IGD['system'], self::KELAS_LAYANAN_IGD['code'], self::KELAS_LAYANAN_IGD['display']);

            // Cara keluar (Playbook IGD bab 26–27) dari Tindak Lanjut di Perencanaan.
            // Kosong/tak dikenal → field TIDAK diisi (jangan menebak), tapi dilaporkan.
            $tindakLanjut = $dataUGD['perencanaan']['tindakLanjut'] ?? [];
            $caraKeluar = DischargeDisposition::gawatDarurat(
                $tindakLanjut['tindakLanjut'] ?? null, $tindakLanjut['keteranganTindakLanjut'] ?? null
            );
            if ($caraKeluar !== null) {
                $encounterTersimpan['hospitalization']['dischargeDisposition'] = $caraKeluar;
            }

            $this->makeRequest('put', "Encounter/{$satuSehat['encounterId']}", $encounterTersimpan);

            $satuSehat['encounterFinished'] = true;

            $this->saveResult($rjNo, $satuSehat);
            $caraKeluar !== null
                ? $this->dispatch('toast', type: 'success', message: 'Encounter finished — cara keluar: ' . $caraKeluar['text'] . '.')
                : $this->dispatch('toast', type: 'warning', message: 'Encounter finished, tapi TANPA cara keluar: Tindak Lanjut di Perencanaan belum diisi.');
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Finish Encounter gagal: ' . $e->getMessage());
        }
    }

    // Location IGD (UGD tak punya poli) — di-HARDCODE. Env boleh override utk sandbox.
    private const UGD_LOCATION_UUID = '7cfc8905-ed98-4077-afcd-e2c48890b765';

    private function resolveUgdLocation(string $rjNo): string
    {
        return (string) (env('SATUSEHAT_IGD_LOCATION_ID') ?: self::UGD_LOCATION_UUID);
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

    /**
     * Jam pasien tiba di IGD. Pasien pindahan RJ punya rj_date UGD yang DISALIN dari RJ (kasir tak
     * menyimpan jam transfer), jadi Jam Datang Pengkajian Perawat lebih tepat; kosong → rj_date.
     */
    private function waktuMasukUgdIso(array $dataUGD, Carbon $ugdDate): string
    {
        $jamDatang = trim((string) ($dataUGD['anamnesa']['pengkajianPerawatan']['jamDatang'] ?? ''));

        return $jamDatang !== '' ? $this->parseDate($jamDatang)->toIso8601String() : $ugdDate->toIso8601String();
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
                        {{ $encounterFinished ? 'finished' : ($encounterInProgress ? 'in-progress' : ($encounterTriaged ? 'triaged' : 'arrived')) }}
                        @if ($encounterInProgress && !$encounterTriaged)
                            <span class="text-warning-deep dark:text-amber-200">· tanpa triaged</span>
                        @endif
                    </div>
                    @if (!$encounterFinished)
                        @if ($this->caraKeluar)
                            <div class="text-xs text-body dark:text-gray-300">Cara keluar: {{ $this->caraKeluar }}</div>
                        @else
                            <div class="text-xs text-warning-deep dark:text-amber-200">Cara keluar kosong — isi Tindak Lanjut di Perencanaan EMR dulu.</div>
                        @endif
                    @endif
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
