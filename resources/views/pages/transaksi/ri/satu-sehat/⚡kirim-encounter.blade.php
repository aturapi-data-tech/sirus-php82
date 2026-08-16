<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-encounter.blade.php
//
// Encounter Rawat Inap (class IMP). Port dari RJ (kirim-encounter RJ) dengan beda:
//   - class_code = 'IMP' (inpatient), bukan 'AMB'
//   - sumber data findDataRI (bukan findDataRJ)
//   - lokasi Encounter = room_uuid kamar (bukan poli_uuid)
//   - dukung PINDAH KAMAR: tiap kamar baru ditambahkan sebagai entri location[]
//   - period.start = tgl masuk (entryDate); finish → period.end = tgl pulang (exitDate)

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\EncounterTrait;
use App\Support\Terminologi\DischargeDisposition;

new class extends Component {
    use EmrRITrait, EncounterTrait;

    public ?string $riHdrNo = null;

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
        if (empty($this->riHdrNo)) {
            return [];
        }

        $data = $this->findDataRI($this->riHdrNo);
        $tanggal = trim((string) ($data['entryDate'] ?? ''));

        return [
            ['label' => 'Pasien', 'nilai' => (string) ($data['regName'] ?? '-'),
             'ket' => 'No. RM ' . ($data['regNo'] ?? '-')],
            ['label' => 'Dokter', 'nilai' => (string) ($data['drDesc'] ?? '-')],
            ['label' => 'Waktu mulai (period.start)',
             'nilai' => $tanggal ?: '(KOSONG — Kirim akan ditolak)',
             'ket' => $tanggal ? 'dibekukan di SATUSEHAT begitu Encounter terbentuk' : 'betulkan dulu di pendaftaran'],
            ['label' => 'Kelas kunjungan', 'nilai' => 'IMP (rawat inap)'],
        ];
    }

    public ?string $encounterId = null;
    public bool $encounterInProgress = false;
    public bool $encounterFinished = false;

    // Ringkasan lokasi untuk display
    public array $encounterRooms = [];   // room_id yang sudah tercatat sebagai location[]
    public string $currentRoomId = '';
    public string $currentRoomDesc = '';

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
        $this->encounterId         = $satuSehat['encounterId'] ?? null;
        $this->encounterInProgress = !empty($satuSehat['encounterInProgress']);
        $this->encounterFinished   = !empty($satuSehat['encounterFinished']);
        $this->encounterRooms      = $satuSehat['encounterRooms'] ?? [];
        $this->currentRoomId       = (string) ($data['roomId'] ?? '');
        $this->currentRoomDesc     = (string) ($data['roomDesc'] ?? '');
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->kirim($this->riHdrNo);
        $this->reloadState();
    }

    public function finishForCurrent(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->finish($this->riHdrNo);
        $this->reloadState();
    }

    #[On('ss-encounter-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            [$dataRI, $satuSehat] = $this->loadData($riHdrNo);

            // Ambil 3 IHS dari DB — pola sama RJ.
            $regNo = $dataRI['regNo'] ?? '';
            $patientId = $regNo ? (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '') : '';

            $drId = $dataRI['drId'] ?? '';
            $practitionerId = $drId ? (string) (DB::table('rsmst_doctors')->where('dr_id', $drId)->value('dr_uuid') ?? '') : '';

            $roomId = (string) ($dataRI['roomId'] ?? '');
            $locationId = $roomId ? (string) (DB::table('rsmst_rooms')->where('room_id', $roomId)->value('room_uuid') ?? '') : '';

            if (empty($patientId)) {
                $this->dispatch('toast', type: 'error',
                    message: 'Patient IHS Number kosong. Daftarkan pasien ke SATUSEHAT dulu via Master Pasien (tombol "Update patientUuid").');
                return;
            }
            if (empty($practitionerId)) {
                $this->dispatch('toast', type: 'error', message: 'Dokter (DPJP) IHS Number kosong.');
                return;
            }
            if (empty($locationId)) {
                $this->dispatch('toast', type: 'error',
                    message: 'Kamar belum punya Location UUID. Daftarkan kamar sebagai Location SATUSEHAT dulu di Master Kamar.');
                return;
            }

            // Tanggal masuk kosong = parseDate() diam-diam memakai now(), sehingga
            // period.start terisi jam petugas menekan tombol lalu DIBEKUKAN di SATUSEHAT.
            // (Alasan lengkap di sender Encounter RJ.)
            $tanggalMasuk = trim((string) ($dataRI['entryDate'] ?? ''));
            if ($tanggalMasuk === '') {
                $this->dispatch('toast', type: 'error',
                    message: 'Tanggal masuk rawat inap (entry_date) kosong — betulkan dulu di pendaftaran sebelum kirim Encounter.');
                return;
            }

            $entryDate = $this->parseDate($tanggalMasuk);

            // 1) Buat Encounter (IMP) kalau belum ada
            if (empty($satuSehat['encounterId'])) {
                $respons = $this->createNewEncounter([
                    'encounterId'      => 'RI-' . $riHdrNo,
                    'patientId'        => $patientId,
                    'patientName'      => $dataRI['regName'] ?? '',
                    'practitionerId'   => $practitionerId,
                    'practitionerName' => $dataRI['drDesc'] ?? '',
                    'locationId'       => $locationId,
                    'class_code'       => 'IMP',
                    'startDate'        => $entryDate->toIso8601String(),
                ]);
                $satuSehat['encounterId'] = $respons['id'] ?? null;
            }

            // 2) Set in-progress + catat kamar pertama sebagai location
            if (!empty($satuSehat['encounterId']) && empty($satuSehat['encounterInProgress'])) {
                $this->startRoomEncounter($satuSehat['encounterId'], [
                    'startDate'  => $entryDate->toIso8601String(),
                    'locationId' => $locationId,
                ]);
                $satuSehat['encounterInProgress'] = true;
                $satuSehat['encounterRooms'] = [$roomId];
            }
            // 2b) Pindah kamar → append location baru untuk kamar yang belum tercatat
            elseif (!empty($satuSehat['encounterId']) && $roomId !== '' && !in_array($roomId, $satuSehat['encounterRooms'] ?? [], true)) {
                $this->appendEncounterLocation($satuSehat['encounterId'], $locationId);
                $satuSehat['encounterRooms'][] = $roomId;
            }

            $this->saveResult($riHdrNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'Encounter (Rawat Inap) berhasil dikirim: ' . ($satuSehat['encounterId'] ?? '-'));
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Encounter gagal: ' . $e->getMessage());
        }
    }

    #[On('ss-encounter-ri.finish')]
    public function finish(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            [$dataRI, $satuSehat] = $this->loadData($riHdrNo);

            if (empty($satuSehat['encounterId'])) {
                $this->dispatch('toast', type: 'error', message: 'Encounter belum dibuat.');
                return;
            }
            if (!empty($satuSehat['encounterFinished'])) {
                $this->dispatch('toast', type: 'info', message: 'Encounter sudah finished.');
                return;
            }

            // RI: waktu selesai = tgl pulang (exitDate) — bukan task antrean seperti
            // RJ/UGD, dan bukan now() yang berarti jam klik petugas.
            $exitStr = trim((string) ($dataRI['exitDate'] ?? ''));
            $akhirIso = ($exitStr !== '' ? $this->parseDate($exitStr) : Carbon::now())->toIso8601String();

            // Encounter.diagnosis wajib (RuleNumber 10457) dan harus merujuk Condition
            // yang sudah dikirim — jangan dikirim tanpa itu, pasti ditolak.
            $conditionIdList = $satuSehat['conditionIds'] ?? [];
            if (empty($conditionIdList)) {
                $this->dispatch('toast', type: 'error',
                    message: 'Kirim Diagnosa (Condition) dulu — SATUSEHAT mewajibkan Encounter.diagnosis saat finish.');
                return;
            }

            $encounterTersimpan = $this->siapkanFinishEncounter(
                $this->getEncounter($satuSehat['encounterId']), $akhirIso, $conditionIdList
            );

            // Status pulang → hospitalization.dischargeDisposition (SNOMED, sudah tersimpan di EMR).
            // Kode tak dikenal → field TIDAK diisi, jangan menebak.
            $disposisi = DischargeDisposition::fromKode(
                $dataRI['perencanaan']['tindakLanjut']['tindakLanjutKode'] ?? null
            );
            if ($disposisi !== null) {
                $encounterTersimpan['hospitalization']['dischargeDisposition'] = [
                    'coding' => [[
                        'system'  => 'http://snomed.info/sct',
                        'code'    => $disposisi['code'],
                        'display' => $disposisi['display'],
                    ]],
                    'text' => $disposisi['display'],
                ];
            }

            $this->makeRequest('put', "Encounter/{$satuSehat['encounterId']}", $encounterTersimpan);
            $satuSehat['encounterFinished'] = true;

            $this->saveResult($riHdrNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'Encounter (Rawat Inap) finished.');
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Finish Encounter gagal: ' . $e->getMessage());
        }
    }

    /**
     * Append satu entri location[] (pindah kamar) tanpa mengubah status.
     */
    private function appendEncounterLocation(string $encounterId, string $locationId): void
    {
        $encounterTersimpan = $this->getEncounter($encounterId);
        $encounterTersimpan['location'][] = [
            'location' => ['reference' => 'Location/' . $locationId],
            'status'   => 'active',
            'period'   => ['start' => Carbon::now()->toIso8601String()],
        ];
        $this->makeRequest('put', "Encounter/{$encounterId}", $encounterTersimpan);
    }

    private function loadData(string $riHdrNo): array
    {
        $dataRI = $this->findDataRI($riHdrNo);
        if (empty($dataRI)) {
            throw new \RuntimeException('Data Rawat Inap tidak ditemukan.');
        }
        return [$dataRI, $dataRI['satusehat'] ?? []];
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
        if (empty($teksTanggal)) {
            return Carbon::now();
        }
        try {
            return Carbon::createFromFormat('d/m/Y H:i:s', $teksTanggal);
        } catch (\Throwable) {
            try {
                return Carbon::parse($teksTanggal);
            } catch (\Throwable) {
                return Carbon::now();
            }
        }
    }
};
?>

<div class="space-y-3">
    {{-- Step 1: Encounter Rawat Inap --}}
    <div class="p-4 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div
                    class="flex items-center justify-center w-8 h-8 rounded-full {{ !empty($encounterId) ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
                    <span class="text-sm font-bold">1</span>
                </div>
                <div>
                    <div class="font-semibold text-ink dark:text-gray-100">Encounter (Rawat Inap)</div>
                    <div class="text-xs text-muted dark:text-gray-400">
                        Kunjungan rawat inap (class IMP).
                        @if ($currentRoomDesc)
                            Kamar: <span class="font-medium">{{ $currentRoomDesc }}</span>.
                        @endif
                    </div>
                    @if (!empty($encounterId))
                        <div class="mt-1 font-mono text-xs text-success dark:text-success">
                            ID: {{ $encounterId }}
                        </div>
                    @endif
                    @if (count($encounterRooms) > 1)
                        <div class="mt-1 text-xs text-muted dark:text-gray-400">
                            Riwayat kamar terkirim: {{ count($encounterRooms) }} lokasi.
                        </div>
                    @endif
                </div>
            </div>
            <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled"
                class="!bg-teal-600 hover:!bg-teal-700 {{ !empty($encounterId) ? '!bg-emerald-600' : '' }}">
                <span wire:loading.remove wire:target="kirimForCurrent">
                    {{ !empty($encounterId) ? 'Terkirim' : 'Kirim' }}
                </span>
                <span wire:loading wire:target="kirimForCurrent"><x-loading />...</span>
            </x-primary-button>
        </div>

        <x-satu-sehat.pratinjau :terbuka="$pratinjauTerbuka"
            :baris="$pratinjauTerbuka ? $this->pratinjau : []"
            kosong="Data kunjungan belum lengkap — lihat pesan saat menekan Kirim." />
    </div>

    {{-- Selesaikan Encounter (visible setelah encounter ada) --}}
    @if (!empty($encounterId))
        <div class="flex items-center justify-between p-4 bg-canvas border-2 border-teal-300 shadow-sm rounded-xl dark:bg-gray-900 dark:border-teal-700">
            <div class="flex items-center gap-3">
                <div
                    class="flex items-center justify-center w-8 h-8 rounded-full {{ $encounterFinished ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-teal-100 text-teal-600 dark:bg-teal-900/30 dark:text-teal-400' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-ink dark:text-gray-100">Selesaikan Encounter</div>
                    <div class="text-xs text-muted dark:text-gray-400">Update status encounter menjadi finished (saat pasien pulang).</div>
                </div>
            </div>
            <x-primary-button type="button" wire:click="finishForCurrent" wire:loading.attr="disabled"
                class="{{ $encounterFinished ? '!bg-emerald-600' : '!bg-teal-600 hover:!bg-teal-700' }}">
                <span wire:loading.remove wire:target="finishForCurrent">
                    {{ $encounterFinished ? 'Selesai' : 'Finish' }}
                </span>
                <span wire:loading wire:target="finishForCurrent"><x-loading />...</span>
            </x-primary-button>
        </div>
    @endif
</div>
