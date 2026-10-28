<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Support\KelengkapanEmr;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Txn\Ugd\EmrCompletenessUGDTrait;

new class extends Component {
    use EmrUGDTrait, EmrCompletenessUGDTrait;

    public ?int $rjNo = null;
    public array $data = [];
    public array $pct = ['emr' => 0, 'sections' => ['s' => 0, 'o' => 0, 'a' => 0, 'p' => 0, 'n' => 0, 't' => 0], 'koding' => null];
    public array $checklist = [];
    public array $snomed = [];

    #[On('open-info-kelengkapan-emr-ugd')]
    public function open(int $rjNo): void
    {
        $this->rjNo = $rjNo;
        $this->data = $this->findDataUGD($rjNo) ?? [];
        $this->pct = $this->calculateEmrPercentUGD($this->data);
        $this->checklist = $this->collectChecklistUGD($this->data);
        $this->snomed = $this->collectSnomedStatus($this->data);
        $this->dispatch('open-modal', name: 'info-kelengkapan-emr-ugd');
    }

    /**
     * Kesiapan Coding SATUSEHAT (Keluhan Utama & Alergi, termasuk daftar alergen layar Coding) —
     * indikator TERPISAH, tidak masuk % EMR. Sumber: App\Support\KelengkapanEmr::kodingSatuSehat().
     */
    private function collectSnomedStatus(array $json): array
    {
        return KelengkapanEmr::kodingSatuSehat($json['anamnesa'] ?? [])['items'];
    }
};

?>

{{--
    Modal panduan kriteria kelengkapan EMR UGD + status pasien aktual.
    Dibuka via event `open-info-kelengkapan-emr-ugd` dengan parameter rjNo.
--}}

<div>
    <x-modal name="info-kelengkapan-emr-ugd" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-4rem)]">

            {{-- HEADER --}}
            <x-modul-dokumen.header :judul="'Kelengkapan EMR — No. RJ ' . ($rjNo ?? '-')" ikon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                jalur="UGD" namaModal="info-kelengkapan-emr-ugd">
                Status pasien saat ini & panduan kriteria 100%.
            </x-modul-dokumen.header>

            {{-- BODY — tema EMR/Coding; isi bersama x-kelengkapan-emr.isi (RJ/UGD/RI) --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                @if ($rjNo)
                    <x-kelengkapan-emr.isi :pct="$pct" :checklist="$checklist" :snomed="$snomed"
                        :nama="['s' => 'Anamnesa', 'o' => 'Pemeriksaan', 'a' => 'Diagnosa', 'p' => 'Perencanaan', 'n' => 'Penilaian', 't' => 'Triase']">
                        <x-slot:catatan>
                            <p><span class="font-semibold">Coding SATUSEHAT</span> (Keluhan Utama &amp; Alergi) <strong>tidak</strong> masuk persentase EMR sejak 28/10/2026 — kode diisi coder di layar Coding SATUSEHAT sesudah pelayanan.</p>
                            <p><span class="font-semibold">Kondisi Saat Keluar</span> (Perencanaan) dinilai untuk kunjungan mulai 28/10/2026.</p>
                            <p>Kolom bertanda <strong>opsional</strong> (riwayat keluarga, status psikologis, kedatangan) tampil sebagai pengingat — <strong>tidak dihitung</strong> ke persentase. Modul observasi/obat-cairan/rujukan/transfer bersifat opsional.</p>
                        </x-slot:catatan>
                    </x-kelengkapan-emr.isi>
                @endif
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 flex justify-end px-6 py-2 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                <x-secondary-button type="button" class="gap-1"
                    x-on:click="$dispatch('close-modal', { name: 'info-kelengkapan-emr-ugd' })">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Mengerti
                </x-secondary-button>
            </div>

        </div>
    </x-modal>
</div>
