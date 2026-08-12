<?php
// Viewer read-only "Laporan Tindakan Persalinan" (RM 44.c) — display Rekam Medis RI.
// Payload cetak seragam (dataRi/form/identitasRs/ttdPath) → pakai helper generik trait.

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?string $riHdrNo = null;
    public array $list = [];
    public ?array $selected = null;
    public string $previewHtml = '';

    private string $printView = 'laporan-persalinan-ri.cetak-laporan-persalinan-ri-print';
    private string $filePrefix = 'laporan-persalinan-ri';
    private string $ttdKey = 'ttdPath';
    private ?string $ttdCodeField = 'ttdCode';

    public function mount(?string $riHdrNo = null, array $entries = []): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->list = array_values($entries);
        $this->navField = 'createdAt';
    }

    public function lihat(string $id): void
    {
        $this->selected = collect($this->list)->firstWhere('createdAt', $id) ?: null;
        if (!$this->selected) {
            $this->dispatch('toast', type: 'error', message: 'Data laporan persalinan tidak ditemukan.');
            return;
        }
        $this->previewHtml = $this->previewDokumenRi($this->selected, $this->printView, $this->ttdKey, $this->ttdCodeField);
        $this->dispatch('open-modal', name: "view-laporan-persalinan-ri-{$this->riHdrNo}");
    }

    public function cetak(string $id): mixed
    {
        return $this->streamCetakDokumenRi(collect($this->list)->firstWhere('createdAt', $id), $this->printView, $this->filePrefix, $this->ttdKey, $this->ttdCodeField);
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

    <x-rm.dokumen-view-modal name="view-laporan-persalinan-ri-{{ $riHdrNo }}" title="Laporan Tindakan Persalinan"
        :subtitle="$selected ? trim((string) data_get($selected, 'createdAt') . ' · ' . (data_get($selected, 'jenisPartus') ?: '-'), ' ·') : null"
        :cetakId="data_get($selected, 'createdAt')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
