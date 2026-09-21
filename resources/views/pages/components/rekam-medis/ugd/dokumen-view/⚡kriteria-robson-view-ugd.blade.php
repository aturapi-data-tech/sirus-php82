<?php
// Viewer read-only "Kriteria Robson" — display Rekam Medis UGD.
// Payload cetak seragam (dataRi/form/ttd) + peta label dari KriteriaRobsonOptions
// → dikirim via $extra ke helper generik trait. Blade cetak dipakai bersama dengan RI.

use Livewire\Component;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Support\Options\KriteriaRobsonOptions;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?int $rjNo = null;
    public array $list = [];
    public ?array $selected = null;
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.ri.kriteria-robson-ri.cetak-kriteria-robson-ri-print';
    private string $filePrefix = 'kriteria-robson-ugd';
    private string $ttdKey = 'ttdPath';
    private ?string $ttdCodeField = 'ttdCode';

    public function mount(?int $rjNo = null, array $entries = []): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->list = array_values($entries);
        $this->navField = 'createdAt';
    }

    /** Data kunjungan UGD — sumber regNo utk identitas pasien di payload cetak. */
    private function dataTxn(): array
    {
        return $this->rjNo ? ($this->findDataUGD($this->rjNo) ?: []) : [];
    }

    public function lihat(string $id): void
    {
        $this->selected = collect($this->list)->firstWhere('createdAt', $id) ?: null;
        if (!$this->selected) {
            $this->dispatch('toast', type: 'error', message: 'Data Kriteria Robson tidak ditemukan.');
            return;
        }
        $this->previewHtml = $this->previewDokumenTxn($this->selected, $this->printView, $this->ttdKey, $this->ttdCodeField, $this->dataTxn(), $this->opsiCetak());
        $this->dispatch('open-modal', name: "view-kriteria-robson-ugd-{$this->rjNo}");
    }

    public function cetak(string $id): mixed
    {
        return $this->streamCetakDokumenTxn(collect($this->list)->firstWhere('createdAt', $id), $this->printView, $this->filePrefix, $this->ttdKey, $this->ttdCodeField, $this->dataTxn(), $this->opsiCetak());
    }

    private function opsiCetak(): array
    {
        return ['opsiLabel' => KriteriaRobsonOptions::labels()];
    }

    /** Ringkasan satu baris di daftar: "Kelompok 2a · Sectio Caesarea". */
    public function ringkasEntri(array $entri): string
    {
        $caraPersalinan = KriteriaRobsonOptions::CARA_PERSALINAN[$entri['caraPersalinan'] ?? ''] ?? '';

        return trim(KriteriaRobsonOptions::teksKelompok($entri['kelompok'] ?? '', $entri['subKelompok'] ?? '') . ' · ' . $caraPersalinan, ' ·');
    }
};
?>

<div>
    <x-border-form title="Kriteria Robson">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'createdAt')))->values() as $entri)
            <x-rm.doc-list-row :id="data_get($entri, 'createdAt')" title="Kriteria Robson"
                :date="\Illuminate\Support\Str::before((string) (data_get($entri, 'tglPersalinan') ?: data_get($entri, 'createdAt', '')), ' ')"
                :sub="$this->ringkasEntri($entri)" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-kriteria-robson-ugd-{{ $rjNo }}" title="Kriteria Robson"
        :subtitle="$selected ? trim((string) data_get($selected, 'createdAt') . ' · ' . $this->ringkasEntri($selected), ' ·') : null"
        :cetakId="data_get($selected, 'createdAt')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
