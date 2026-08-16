<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-observation.blade.php
// Step 5 (RI): Kirim Tanda Vital (Observation).
//
// Sumber = datadaftarri_json → observasi.observasiLanjutan.tandaVital[] (MULTI-ENTRI).
// Beda dari RJ (1x vital): RI punya banyak entri sepanjang rawat inap → 1 Observation
// per vital PER entri. Effective time = waktuPemeriksaan tiap entri.
//
// Key per entri (ejaan non-standar dari EMR): sistolik, distolik (diastolik),
// frekuensiNadi, frekuensiNafas, suhu, spo2.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\ObservationTrait;

new class extends Component {
    use EmrRITrait, ObservationTrait;

    public ?string $riHdrNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;      // jumlah Observation terkirim
    public int $entryCount = 0; // jumlah entri waktu vital tersedia

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat halaman belasan kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /**
     * Isi yang AKAN dikirim, memanggil tandaVitalEntries() yang SAMA dengan kirim().
     * RI punya BANYAK waktu ukur (observasi lanjutan), jadi tiap baris diberi awalan
     * jam pengukurannya — tanpa itu lima "Nadi" berturut-turut tak bisa dibedakan.
     */
    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->riHdrNo)) {
            return [];
        }

        $dataRI = $this->findDataRI($this->riHdrNo);
        $baris = [];
        foreach ($this->tandaVitalEntries($dataRI) as $entri) {
            $waktu = trim((string) ($entri['waktuPemeriksaan'] ?? ''));
            $awalan = $waktu !== '' ? $waktu . ' · ' : '';

            $sistolik = $entri['sistolik'] ?? null;
            $distolik = $entri['distolik'] ?? null;
            if (!empty($sistolik) && !empty($distolik)) {
                $baris[] = ['label' => $awalan . 'Tekanan darah', 'nilai' => "{$sistolik}/{$distolik} mm[Hg]", 'ket' => 'LOINC 85354-9'];
            }

            foreach ([
                ['frekuensiNadi', 'Nadi', 'x/menit', '8867-4'],
                ['suhu', 'Suhu', '°C', '8310-5'],
                ['frekuensiNafas', 'Pernapasan', 'x/menit', '9279-1'],
                ['spo2', 'Saturasi O₂', '%', '59408-5'],
            ] as [$key, $label, $satuan, $loinc]) {
                $nilai = $entri[$key] ?? null;
                if (empty($nilai)) {
                    continue;
                }
                $baris[] = ['label' => $awalan . $label, 'nilai' => "{$nilai} {$satuan}", 'ket' => 'LOINC ' . $loinc];
            }
        }

        return $baris;
    }


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
        $this->count = count($satuSehat['observationIds'] ?? []);
        $this->entryCount = count($this->tandaVitalEntries($data));
    }

    /** @return array<int, array> daftar entri tandaVital */
    private function tandaVitalEntries(array $data): array
    {
        return $data['observasi']['observasiLanjutan']['tandaVital'] ?? [];
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->kirim($this->riHdrNo);
        $this->reloadState();
    }

    #[On('ss-observation-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $satuSehat = $dataRI['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['observationIds'])) { $this->dispatch('toast', type: 'info', message: 'Tanda vital sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRI['drId'] ?? '')->value('dr_uuid') ?? '');

            $entries = $this->tandaVitalEntries($dataRI);
            if (empty($entries)) { $this->dispatch('toast', type: 'error', message: 'Tidak ada data tanda vital (Observasi Lanjutan).'); return; }

            $satuSehat['observationIds'] = [];
            foreach ($entries as $entri) {
                $waktu = trim((string) ($entri['waktuPemeriksaan'] ?? ''));
                $isoDate = ($waktu !== '' ? $this->parseDate($waktu) : $this->parseDate($dataRI['entryDate'] ?? ''))->toIso8601String();
                $payloadDasar = ['patientId' => $patientId, 'encounterId' => $satuSehat['encounterId'], 'performerId' => $practitionerId, 'effectiveDate' => $isoDate];

                // Tekanan Darah (panel)
                $sistole = $entri['sistolik'] ?? null;
                $diastole = $entri['distolik'] ?? null;
                if (!empty($sistole) && !empty($diastole)) {
                    $respons = $this->createObservation(array_merge($payloadDasar, [
                        'code' => ['system' => 'http://loinc.org', 'code' => '85354-9', 'display' => 'Blood pressure panel with all children optional'],
                        'components' => [
                            ['code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8480-6', 'display' => 'Systolic blood pressure']]], 'valueQuantity' => ['value' => (float) $sistole, 'unit' => 'mm[Hg]', 'system' => 'http://unitsofmeasure.org', 'code' => 'mm[Hg]']],
                            ['code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8462-4', 'display' => 'Diastolic blood pressure']]], 'valueQuantity' => ['value' => (float) $diastole, 'unit' => 'mm[Hg]', 'system' => 'http://unitsofmeasure.org', 'code' => 'mm[Hg]']],
                        ],
                    ]));
                    if (!empty($respons['id'])) $satuSehat['observationIds'][] = $respons['id'];
                }

                // Vital tunggal
                $vitalTunggal = [
                    ['val' => $entri['frekuensiNadi'] ?? null,  'loinc' => '8867-4', 'display' => 'Heart rate',        'unit' => 'beats/minute',   'ucum' => '/min'],
                    ['val' => $entri['suhu'] ?? null,           'loinc' => '8310-5', 'display' => 'Body temperature',  'unit' => 'C',              'ucum' => 'Cel'],
                    ['val' => $entri['frekuensiNafas'] ?? null, 'loinc' => '9279-1', 'display' => 'Respiratory rate',  'unit' => 'breaths/minute', 'ucum' => '/min'],
                    ['val' => $entri['spo2'] ?? null,           'loinc' => '59408-5','display' => 'Oxygen saturation in Arterial blood by Pulse oximetry', 'unit' => '%', 'ucum' => '%'],
                ];
                foreach ($vitalTunggal as $vital) {
                    if (empty($vital['val'])) continue;
                    $respons = $this->createObservation(array_merge($payloadDasar, [
                        'code' => ['system' => 'http://loinc.org', 'code' => $vital['loinc'], 'display' => $vital['display']],
                        'valueQuantity' => ['value' => (float) $vital['val'], 'unit' => $vital['unit'], 'system' => 'http://unitsofmeasure.org', 'code' => $vital['ucum']],
                    ]));
                    if (!empty($respons['id'])) $satuSehat['observationIds'][] = $respons['id'];
                }
            }

            if (empty($satuSehat['observationIds'])) { $this->dispatch('toast', type: 'error', message: 'Tidak ada nilai vital valid untuk dikirim.'); return; }

            $this->saveResult($riHdrNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'Tanda vital berhasil dikirim (' . count($satuSehat['observationIds']) . ' observation dari ' . count($entries) . ' waktu).');
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            // Simpan dulu yang sudah TERLANJUR terbentuk di SATUSEHAT sebelum melapor
            // gagal. Tanpa ini id-nya hangus padahal resource-nya SUDAH ada di sana,
            // lalu percobaan berikutnya menumpuk resource yatim — persis penyebab
            // diagnosa macet permanen dulu (lihat sender Condition). Dibungkus try
            // sendiri supaya kegagalan menyimpan tidak menutupi error aslinya.
            try { if (isset($satuSehat)) { $this->saveResult($riHdrNo, $satuSehat); } } catch (\Throwable) {}
            $this->dispatch('toast', type: 'error', message: 'Tanda vital gagal: ' . $e->getMessage());
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
                <span class="text-sm font-bold">5</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Observation</div>
                <div class="text-xs text-muted dark:text-gray-400">
                    Tanda vital (TD, nadi, suhu, RR, SpO₂).
                    @if ($entryCount > 0)
                        <span class="text-muted-soft">{{ $entryCount }} waktu ukur.</span>
                    @endif
                </div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        {{ $count }} terkirim
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
        <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter"
            class="!bg-teal-600 hover:!bg-teal-700 {{ $count > 0 ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="kirimForCurrent">{{ $count > 0 ? 'Terkirim' : 'Kirim' }}</span>
            <span wire:loading wire:target="kirimForCurrent"><x-loading />...</span>
        </x-primary-button>
    </div>

    @if ($pratinjauTerbuka)
        <x-satu-sehat.pratinjau :baris="$this->pratinjau"
            kosong="Belum ada tanda vital (Observasi Lanjutan) — Kirim akan ditolak sampai diisi." />
    @endif
</div>
