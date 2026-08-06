<?php
// Viewer read-only "Surat Pernyataan Penolakan Pengobatan / Obat Tertentu" — display Rekam Medis RI.
// Pembeda entri = signatureDate (bukan createdAt). Payload seragam (dataRi/form/ttd)
// → pakai DokumenViewSupportTrait langsung, pola permintaan-kerohanian-view-ri.

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

    private string $printView = 'penolakan-obat-ri.cetak-penolakan-obat-ri-print';
    private string $filePrefix = 'penolakan-obat-ri';
    private string $ttdKey = 'ttdPetugasPath';
    private ?string $ttdCodeField = 'petugasCode';

    public function mount(?string $riHdrNo = null, array $entries = []): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->list = array_values($entries);
        $this->navField = 'signatureDate';
    }

    public function lihat(string $id): void
    {
        $this->selected = collect($this->list)->firstWhere('signatureDate', $id) ?: null;
        if (!$this->selected) {
            $this->dispatch('toast', type: 'error', message: 'Data penolakan obat tidak ditemukan.');
            return;
        }
        $this->previewHtml = $this->previewDokumenRi($this->selected, $this->printView, $this->ttdKey, $this->ttdCodeField);
        $this->dispatch('open-modal', name: "view-penolakan-obat-ri-{$this->riHdrNo}");
    }

    public function cetak(string $id): mixed
    {
        return $this->streamCetakDokumenRi(collect($this->list)->firstWhere('signatureDate', $id), $this->printView, $this->filePrefix, $this->ttdKey, $this->ttdCodeField);
    }
};
?>

<div>
    <x-border-form title="Penolakan Pengobatan / Obat Tertentu">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'signatureDate')))->values() as $entri)
            <x-rm.doc-list-row :id="data_get($entri, 'signatureDate')" :title="data_get($entri, 'pembuatNama') ?: 'Penolakan Obat'"
                :date="data_get($entri, 'signatureDate')"
                :sub="filled(data_get($entri, 'namaObat')) ? ('Obat: ' . data_get($entri, 'namaObat')) : null" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-penolakan-obat-ri-{{ $riHdrNo }}" title="Penolakan Pengobatan / Obat Tertentu"
        :subtitle="$selected ? ((data_get($selected, 'pembuatNama') ?: '-') . ' · ' . (data_get($selected, 'namaObat') ?: '-')) : null"
        :cetakId="data_get($selected, 'signatureDate')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
