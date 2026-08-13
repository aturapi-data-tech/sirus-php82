<?php
// Viewer read-only "Pra-Anestesi" — display Rekam Medis RJ.

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?int $rjNo = null;
    public array $list = [];
    public ?array $selected = null;
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.ri.pra-anestesi-ri.cetak-pra-anestesi-ri-print';
    private string $filePrefix = 'pra-anestesi-rj';
    private string $ttdKey = 'ttdPath';
    private ?string $ttdCodeField = 'ttdCode';

    public function mount(?int $rjNo = null, array $entries = []): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->list = array_values($entries);
        $this->navField = 'createdAt';
    }

    /** Data kunjungan RJ — sumber regNo utk identitas pasien di payload cetak. */
    private function dataTxn(): array
    {
        return $this->rjNo ? ($this->findDataRJ($this->rjNo) ?: []) : [];
    }

    public function lihat(string $id): void
    {
        $this->selected = collect($this->list)->firstWhere('createdAt', $id) ?: null;
        if (!$this->selected) {
            $this->dispatch('toast', type: 'error', message: 'Data pra-anestesi tidak ditemukan.');
            return;
        }
        $this->previewHtml = $this->previewDokumenTxn($this->selected, $this->printView, $this->ttdKey, $this->ttdCodeField, $this->dataTxn());
        $this->dispatch('open-modal', name: "view-pra-anestesi-rj-{$this->rjNo}");
    }

    public function cetak(string $id): mixed
    {
        return $this->streamCetakDokumenTxn(collect($this->list)->firstWhere('createdAt', $id), $this->printView, $this->filePrefix, $this->ttdKey, $this->ttdCodeField, $this->dataTxn());
    }
};
?>

<div>
    <x-border-form title="Pra-Anestesi">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'createdAt')))->values() as $entri)
            <x-rm.doc-list-row :id="data_get($entri, 'createdAt')" :title="data_get($entri, 'rencanaTindakan') ?: 'Pra-Anestesi'"
                :date="data_get($entri, 'tanggal')"
                :sub="filled(data_get($entri, 'diagnosisPraAnestesi')) ? 'Diagnosis: ' . data_get($entri, 'diagnosisPraAnestesi') : null" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-pra-anestesi-rj-{{ $rjNo }}" title="Pra-Anestesi"
        :subtitle="$selected ? (data_get($selected, 'tanggal') ?: null) : null"
        :cetakId="data_get($selected, 'createdAt')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
