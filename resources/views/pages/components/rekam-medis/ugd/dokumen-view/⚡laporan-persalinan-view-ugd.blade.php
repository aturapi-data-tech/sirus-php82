<?php
// Viewer read-only "Laporan Tindakan Persalinan" (RM 44.c) — display Rekam Medis UGD.
// Port dari viewer RI; blade cetak dipakai bersama dengan RI.
// Payload cetak seragam (dataRi/form/identitasRs/ttdPath) → pakai helper generik trait.

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

    private string $printView = 'pages.components.modul-dokumen.ri.laporan-persalinan-ri.cetak-laporan-persalinan-ri-print';
    private string $filePrefix = 'laporan-persalinan-ugd';
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
            $this->dispatch('toast', type: 'error', message: 'Data laporan persalinan tidak ditemukan.');
            return;
        }
        $this->previewHtml = $this->previewDokumenTxn($this->selected, $this->printView, $this->ttdKey, $this->ttdCodeField, $this->dataTxn());
        $this->dispatch('open-modal', name: "view-laporan-persalinan-ugd-{$this->rjNo}");
    }

    public function cetak(string $id): mixed
    {
        return $this->streamCetakDokumenTxn(collect($this->list)->firstWhere('createdAt', $id), $this->printView, $this->filePrefix, $this->ttdKey, $this->ttdCodeField, $this->dataTxn());
    }
};
?>

<div>
    <x-border-form title="Laporan Tindakan Persalinan">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'createdAt')))->values() as $entri)
            <x-rm.doc-list-row :id="data_get($entri, 'createdAt')" title="Laporan Tindakan Persalinan"
                :date="\Illuminate\Support\Str::before((string) (data_get($entri, 'bayi.0.lahir') ?: (data_get($entri, 'bayiLahirTgl') ?: data_get($entri, 'createdAt', ''))), ' ')"
                :sub="'Jenis partus: ' . (data_get($entri, 'jenisPartus') ?: '-') . (count((array) data_get($entri, 'bayi', [])) > 1 ? ' · ' . count((array) data_get($entri, 'bayi', [])) . ' bayi (kembar)' : '')" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-laporan-persalinan-ugd-{{ $rjNo }}" title="Laporan Tindakan Persalinan"
        :subtitle="$selected ? trim((string) data_get($selected, 'createdAt') . ' · ' . (data_get($selected, 'jenisPartus') ?: '-'), ' ·') : null"
        :cetakId="data_get($selected, 'createdAt')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
