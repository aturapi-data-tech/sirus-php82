<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Support\AnamnesaRi;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Txn\Ri\EmrCompletenessRITrait;

new class extends Component {
    use EmrRITrait, EmrCompletenessRITrait;

    public ?int $riHdrNo = null;
    public array $data = [];
    public array $pct = ['emr' => 0, 'sections' => ['s' => 0, 'o' => 0, 'a' => 0, 'p' => 0, 'n' => 0, 'c' => 0, 'k' => 0]];
    public array $checklist = [];
    public array $snomed = [];

    #[On('open-info-kelengkapan-emr-ri')]
    public function open(int $riHdrNo): void
    {
        $this->riHdrNo = $riHdrNo;
        $this->data = $this->findDataRI($riHdrNo) ?? [];
        $this->pct = $this->calculateEmrPercentRI($this->data);
        $this->checklist = $this->collectChecklistRI($this->data);
        $this->snomed = $this->collectSnomedStatus($this->data);
        $this->dispatch('open-modal', name: 'info-kelengkapan-emr-ri');
    }

    /**
     * Kesiapan Coding SATUSEHAT RI — kode di node akar kodingSatuSehat (layar Coding RI), teks dari
     * App\Support\AnamnesaRi (dokter dulu, cadangan perawat). Info saja, tidak masuk %.
     */
    private function collectSnomedStatus(array $json): array
    {
        $kodingKeluhan = AnamnesaRi::koding($json, 'keluhanUtama');
        $alergi = AnamnesaRi::alergi($json);
        $alergenList = (array) (AnamnesaRi::koding($json, 'alergi')['alergenList'] ?? []);
        $kodeAlergen = collect($alergenList)->pluck('kode')->filter()->implode(', ');
        $kodeAlergi = ($alergi['adaAlergi'] ?? '') === 'Ya' ? $kodeAlergen : (string) ($alergi['snomedCode'] ?? '');

        return [
            [
                'label' => 'Keluhan Utama',
                'value' => AnamnesaRi::keluhanUtama($json),
                'code' => (string) ($kodingKeluhan['snomedCode'] ?? ''),
                'displayId' => (string) ($kodingKeluhan['snomedDisplayId'] ?? ''),
                'terkode' => filled($kodingKeluhan['snomedCode'] ?? null),
            ],
            [
                'label' => 'Alergi',
                'value' => (string) ($alergi['alergi'] ?? ''),
                'code' => $kodeAlergi,
                'displayId' => ($alergi['adaAlergi'] ?? '') === 'Ya' ? '' : (string) ($alergi['snomedDisplayId'] ?? ''),
                'terkode' => $kodeAlergi !== '',
            ],
        ];
    }
};

?>

{{--
    Modal panduan kriteria kelengkapan EMR Rawat Inap + status pasien aktual.
    Dibuka via event `open-info-kelengkapan-emr-ri` dengan parameter riHdrNo.

    Sumber bobot & field WAJIB: App\Http\Traits\Txn\Ri\EmrCompletenessRITrait
    RI punya 2 section khas (C=CPPT, K=Askep) di luar S/O/A/P/N RJ/UGD.
--}}

<div>
    <x-modal name="info-kelengkapan-emr-ri" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-4rem)]">

            {{-- HEADER --}}
            <x-modul-dokumen.header :judul="'Kelengkapan EMR — No. RI ' . ($riHdrNo ?? '-')" ikon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                jalur="RI" namaModal="info-kelengkapan-emr-ri">
                Status pasien saat ini & panduan kriteria 100%.
            </x-modul-dokumen.header>

            {{-- BODY — tema EMR/Coding; isi bersama x-kelengkapan-emr.isi (RJ/UGD/RI) --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                @if ($riHdrNo)
                    <x-kelengkapan-emr.isi :pct="$pct" :checklist="$checklist" :snomed="$snomed"
                        :nama="['s' => 'Pengkajian Awal', 'o' => 'Tanda Vital', 'a' => 'Diagnosa', 'p' => 'Perencanaan', 'n' => 'Penilaian', 'c' => 'CPPT', 'k' => 'Asuhan Keperawatan']">
                        <x-slot:catatan>
                            <p><span class="font-semibold">Coding SATUSEHAT</span> tidak masuk perhitungan persentase, hanya info kesiapan kirim.</p>
                            <p><span class="font-semibold">Kondisi Saat Pulang</span> (Perencanaan) dinilai untuk pasien yang pulang mulai 28/10/2026.</p>
                            <p>Kolom bertanda <strong>opsional</strong> (keluhan penyerta, riwayat keluarga, status emosional, prognosis, diet, edukasi) tampil sebagai pengingat — <strong>tidak dihitung</strong> ke persentase. Modul observasi/SKDP/modul-dokumen bersifat opsional.</p>
                        </x-slot:catatan>
                    </x-kelengkapan-emr.isi>
                @endif
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 flex justify-end px-6 py-2 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                <x-secondary-button type="button" class="gap-1"
                    x-on:click="$dispatch('close-modal', { name: 'info-kelengkapan-emr-ri' })">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Mengerti
                </x-secondary-button>
            </div>

        </div>
    </x-modal>
</div>
