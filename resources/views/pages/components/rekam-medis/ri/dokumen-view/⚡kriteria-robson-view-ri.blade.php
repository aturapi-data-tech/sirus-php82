<?php
// Viewer read-only "Kriteria Robson" — display Rekam Medis RI.
// Payload cetak seragam (dataRi/form/ttd) + peta label dari KriteriaRobsonOptions
// → dikirim via $extra ke helper generik trait.

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Support\Options\KriteriaRobsonOptions;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?string $riHdrNo = null;
    public array $list = [];
    public ?array $selected = null;
    public string $previewHtml = '';

    private string $printView = 'kriteria-robson-ri.cetak-kriteria-robson-ri-print';
    private string $filePrefix = 'kriteria-robson-ri';
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
            $this->dispatch('toast', type: 'error', message: 'Data Kriteria Robson tidak ditemukan.');
            return;
        }
        $this->previewHtml = $this->previewDokumenRi($this->selected, $this->printView, $this->ttdKey, $this->ttdCodeField, $this->opsiCetak());
        $this->dispatch('open-modal', name: "view-kriteria-robson-ri-{$this->riHdrNo}");
    }

    public function cetak(string $id): mixed
    {
        return $this->streamCetakDokumenRi(collect($this->list)->firstWhere('createdAt', $id), $this->printView, $this->filePrefix, $this->ttdKey, $this->ttdCodeField, $this->opsiCetak());
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

    <x-rm.dokumen-view-modal name="view-kriteria-robson-ri-{{ $riHdrNo }}" title="Kriteria Robson"
        :subtitle="$selected ? trim((string) data_get($selected, 'createdAt') . ' · ' . $this->ringkasEntri($selected), ' ·') : null"
        :cetakId="data_get($selected, 'createdAt')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
