<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-encounter.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\SATUSEHAT\EncounterTrait;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait, EncounterTrait;

    public ?string $rjNo = null;
    public ?string $encounterId = null;
    public bool $encounterInProgress = false;
    public bool $encounterFinished = false;

    /**
     * Kartu mana yang dirender: 'kirim' (langkah 1, paling atas) atau 'selesai'
     * (Selesaikan Encounter, dipasang paling bawah setelah semua resource dikirim).
     * Satu komponen dua kartu supaya logika Encounter tetap di satu berkas —
     * induk merender komponen ini dua kali dengan $bagian berbeda.
     */
    public string $bagian = 'kirim';

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

        $data = $this->findDataRJ($this->rjNo);
        $tanggal = trim((string) ($data['rjDate'] ?? ''));

        return [
            ['label' => 'Pasien', 'nilai' => (string) ($data['regName'] ?? '-'),
             'ket' => 'No. RM ' . ($data['regNo'] ?? '-')],
            ['label' => 'Dokter', 'nilai' => (string) ($data['drDesc'] ?? '-')],
            ['label' => 'Waktu mulai (period.start)',
             'nilai' => $tanggal ?: '(KOSONG — Kirim akan ditolak)',
             'ket' => $tanggal ? 'dibekukan di SATUSEHAT begitu Encounter terbentuk' : 'betulkan dulu di pendaftaran'],
            ['label' => 'Kelas kunjungan', 'nilai' => 'AMB (rawat jalan)'],
        ];
    }


    public function mount(?string $rjNo = null, string $bagian = 'kirim'): void
    {
        $this->rjNo = $rjNo;
        $this->bagian = $bagian;
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

    #[On('ss-encounter-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            [$dataRJ, $pasien, $satuSehat] = $this->loadData($rjNo);

            // Ambil 3 IHS (patient/dokter/poli) dari DB — pola sama rujukan-kompetensi.
            // Patient UUID registration (SATUSEHAT) di-handle di master-pasien,
            // BUKAN di sini. Kalau kosong, arahkan user ke master-pasien dulu.
            $regNo = $dataRJ['regNo'] ?? '';
            $patientId = $regNo ? (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '') : '';

            $drId = $dataRJ['drId'] ?? '';
            $practitionerId = $drId ? (string) (DB::table('rsmst_doctors')->where('dr_id', $drId)->value('dr_uuid') ?? '') : '';

            $poliId = $dataRJ['poliId'] ?? '';
            $locationId = $poliId ? (string) (DB::table('rsmst_polis')->where('poli_id', $poliId)->value('poli_uuid') ?? '') : '';

            if (empty($patientId)) {
                $this->dispatch('toast', type: 'error',
                    message: 'Patient IHS Number kosong. Daftarkan pasien ke SATUSEHAT dulu via Master Pasien (tombol "Update patientUuid").');
                return;
            }
            if (empty($practitionerId)) {
                $this->dispatch('toast', type: 'error', message: 'Dokter IHS Number kosong.');
                return;
            }
            if (empty($locationId)) {
                $this->dispatch('toast', type: 'error', message: 'Poli IHS Number kosong.');
                return;
            }

            // Tanggal kunjungan kosong = parseDate() diam-diam memakai now(), sehingga
            // period.start terisi JAM PETUGAS MENEKAN TOMBOL, bukan jam kunjungan. Waktu
            // itu lalu DIBEKUKAN di SATUSEHAT begitu Encounter terbentuk dan tak bisa
            // dikoreksi belakangan. Akibatnya berantai: Finish memakai jam layanan
            // sesungguhnya (taskId7/taskId5) yang bisa lebih awal → melanggar constraint
            // start<=end, dan seluruh resource yang menempel ikut salah waktu.
            // Lebih baik ditolak di sini supaya tanggalnya dibetulkan lebih dulu.
            $tanggalKunjungan = trim((string) ($dataRJ['rjDate'] ?? ''));
            if ($tanggalKunjungan === '') {
                $this->dispatch('toast', type: 'error',
                    message: 'Tanggal kunjungan (rj_date) kosong — betulkan dulu di pendaftaran sebelum kirim Encounter.');
                return;
            }

            $rjDate = $this->parseDate($tanggalKunjungan);

            if (empty($satuSehat['encounterId'])) {
                $respons = $this->createNewEncounter([
                    'encounterId' => 'RJ-' . $rjNo,
                    'patientId' => $patientId,
                    'patientName' => $pasien['regName'] ?? '',
                    'practitionerId' => $practitionerId,
                    'practitionerName' => $dataRJ['drDesc'] ?? '',
                    'locationId' => $locationId,
                    'class_code' => 'AMB',
                    'startDate' => $rjDate->toIso8601String(),
                ]);
                $satuSehat['encounterId'] = $respons['id'] ?? null;
            }

            if (!empty($satuSehat['encounterId']) && empty($satuSehat['encounterInProgress'])) {
                $this->startRoomEncounter($satuSehat['encounterId'], [
                    'startDate' => $rjDate->toIso8601String(),
                    'locationId' => $locationId,
                ]);
                $satuSehat['encounterInProgress'] = true;
            }

            $this->saveResult($rjNo, $dataRJ, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'Encounter berhasil dikirim: ' . ($satuSehat['encounterId'] ?? '-'));
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Encounter gagal: ' . $e->getMessage());
        }
    }

    #[On('ss-encounter-rj.finish')]
    public function finish(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            [$dataRJ, , $satuSehat] = $this->loadData($rjNo);

            if (empty($satuSehat['encounterId'])) {
                $this->dispatch('toast', type: 'error', message: 'Encounter belum dibuat.');
                return;
            }
            if (!empty($satuSehat['encounterFinished'])) {
                $this->dispatch('toast', type: 'info', message: 'Encounter sudah finished.');
                return;
            }

            // Waktu selesai = jam layanan berakhir, bukan now() (jam petugas mengklik):
            // task 7 (obat diserahkan), atau task 5 (keluar poli) bila tak ada obat.
            $task = $dataRJ['taskIdPelayanan'] ?? [];
            $waktuSelesai = trim((string) ($task['taskId7'] ?? '')) ?: trim((string) ($task['taskId5'] ?? ''));
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

            $this->saveResult($rjNo, $dataRJ, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'Encounter finished.');
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Finish Encounter gagal: ' . $e->getMessage());
        }
    }

    private function loadData(string $rjNo): array
    {
        $dataRJ = $this->findDataRJ($rjNo);
        if (empty($dataRJ)) {
            throw new \RuntimeException('Data RJ tidak ditemukan.');
        }
        $pasienData = $this->findDataMasterPasien($dataRJ['regNo'] ?? '');
        $pasien = $pasienData['pasien'] ?? [];
        return [$dataRJ, $pasien, $dataRJ['satusehat'] ?? []];
    }

    private function saveResult(string $rjNo, array $dataRJ, array $satuSehat): void
    {
        DB::transaction(function () use ($rjNo, $satuSehat) {
            $this->lockRJRow($rjNo);
            $data = $this->findDataRJ($rjNo);
            $data['satusehat'] = $satuSehat;
            $this->updateJsonRJ($rjNo, $data);
        });
    }

    private function getIHS(string $table, string $col, string $nilai): string
    {
        if (empty($nilai)) {
            return '';
        }
        $uuidCol = match ($table) {
            'rsmst_doctors' => 'dr_uuid',
            'rsmst_polis' => 'poli_uuid',
            'rsmst_pasiens' => 'patient_uuid',
            default => 'dr_uuid',
        };
        return (string) (DB::table($table)->where($col, $nilai)->value($uuidCol) ?? '');
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
    @if ($bagian !== 'selesai')
    {{-- Step 1: Encounter --}}
    <div class="p-4 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div
                    class="flex items-center justify-center w-8 h-8 rounded-full {{ !empty($encounterId) ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
                    <span class="text-sm font-bold">1</span>
                </div>
                <div>
                    <div class="font-semibold text-ink dark:text-gray-100">Encounter</div>
                    <div class="text-xs text-muted dark:text-gray-400">Kunjungan pasien ke RS.</div>
                    @if (!empty($encounterId))
                        <div class="mt-1 font-mono text-xs text-success dark:text-success">
                            ID: {{ $encounterId }}
                        </div>
                    @endif
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
                class="!bg-teal-600 hover:!bg-teal-700 {{ !empty($encounterId) ? '!bg-emerald-600' : '' }}">
                <span wire:loading.remove wire:target="kirimForCurrent">
                    {{ !empty($encounterId) ? 'Terkirim' : 'Kirim' }}
                </span>
                <span wire:loading wire:target="kirimForCurrent"><x-loading />...</span>
            </x-primary-button>
        </div>

        @if ($pratinjauTerbuka)
            <x-satu-sehat.pratinjau :baris="$this->pratinjau"
                kosong="Data kunjungan belum lengkap — lihat pesan saat menekan Kirim." />
        @endif
    </div>
    @endif

    {{-- Selesaikan Encounter — kartu penutup, dirender oleh instance $bagian='selesai'
         yang dipasang paling bawah di modal (baru muncul setelah encounter ada). --}}
    @if ($bagian === 'selesai' && !empty($encounterId))
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
                    <div class="text-xs text-muted dark:text-gray-400">Update status encounter menjadi finished.</div>
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
