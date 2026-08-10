<?php
// Viewer read-only "Surgical Safety Checklist" — display Rekam Medis UGD.

use Livewire\Component;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?int $rjNo = null;
    public array $list = [];
    public ?array $selected = null;
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.u-g-d.surgical-safety-checklist-ugd.cetak-surgical-safety-checklist-ugd-print';
    private string $filePrefix = 'surgical-safety-checklist-ugd';
    private string $ttdKey = 'ttdPath';
    private ?string $ttdCodeField = 'ttdOperatorCode';

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
            $this->dispatch('toast', type: 'error', message: 'Data Surgical Safety Checklist tidak ditemukan.');
            return;
        }
        $this->previewHtml = $this->previewDokumenTxn($this->selected, $this->printView, $this->ttdKey, $this->ttdCodeField, $this->dataTxn());
        $this->dispatch('open-modal', name: "view-surgical-safety-checklist-ugd-{$this->rjNo}");
    }

    public function cetak(string $id): mixed
    {
        return $this->streamCetakDokumenTxn(collect($this->list)->firstWhere('createdAt', $id), $this->printView, $this->filePrefix, $this->ttdKey, $this->ttdCodeField, $this->dataTxn());
    }
};
?>

<div>
    <x-border-form title="Surgical Safety Checklist">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'createdAt')))->values() as $entri)
            <x-rm.doc-list-row :id="data_get($entri, 'createdAt')" :title="data_get($entri, 'tindakan') ?: 'Surgical Safety Checklist'"
                :date="data_get($entri, 'tanggal')"
                :sub="filled(data_get($entri, 'diagnosa')) ? 'Diagnosa: ' . data_get($entri, 'diagnosa') : null" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-surgical-safety-checklist-ugd-{{ $rjNo }}" title="Surgical Safety Checklist"
        :subtitle="$selected ? (data_get($selected, 'tanggal') ?: null) : null"
        :cetakId="data_get($selected, 'createdAt')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
