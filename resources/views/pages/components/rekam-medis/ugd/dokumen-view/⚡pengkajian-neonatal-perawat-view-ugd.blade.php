<?php
// Viewer read-only "Pengkajian Keperawatan Neonatal" — display Rekam Medis UGD.
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

    private string $printView = 'pages.components.modul-dokumen.ri.pengkajian-neonatal-perawat-ri.cetak-pengkajian-neonatal-perawat-ri-print';
    private string $filePrefix = 'pengkajian-neonatal-perawat-ugd';
    private string $ttdKey = 'ttdPath';
    private ?string $ttdCodeField = 'ttdCode';

    public function mount(?int $rjNo = null, array $entries = []): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->list = array_values($entries);
        $this->navField = 'createdAt';
    }

    /** Ringkasan baris list: diagnosa keperawatan (checkbox, array) + interpretasi nyeri NIPS. */
    public function ringkasEntri(?array $entri): string
    {
        $diagnosa = data_get($entri, 'diagnosaKeperawatan', []);
        $diagnosa = is_array($diagnosa) ? $diagnosa : [$diagnosa];
        $diagnosa = collect($diagnosa)
            ->map(fn($item) => trim((string) $item))
            ->filter()
            ->implode(', ');

        $bagian = [];
        if ($diagnosa !== '') {
            $bagian[] = \Illuminate\Support\Str::limit($diagnosa, 70);
        }

        $nyeri = trim((string) data_get($entri, 'nipsInterpretasi', ''));
        if ($nyeri !== '') {
            $bagian[] = 'NIPS ' . $nyeri;
        }

        return empty($bagian) ? '-' : implode(' · ', $bagian);
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
            $this->dispatch('toast', type: 'error', message: 'Data pengkajian keperawatan neonatal tidak ditemukan.');
            return;
        }
        $this->previewHtml = $this->previewDokumenTxn($this->selected, $this->printView, $this->ttdKey, $this->ttdCodeField, $this->dataTxn());
        $this->dispatch('open-modal', name: "view-pengkajian-neonatal-perawat-ugd-{$this->rjNo}");
    }

    public function cetak(string $id): mixed
    {
        return $this->streamCetakDokumenTxn(collect($this->list)->firstWhere('createdAt', $id), $this->printView, $this->filePrefix, $this->ttdKey, $this->ttdCodeField, $this->dataTxn());
    }
};
?>

<div>
    <x-border-form title="Pengkajian Keperawatan Neonatal">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'createdAt')))->values() as $entri)
            <x-rm.doc-list-row :id="data_get($entri, 'createdAt')" title="Pengkajian Keperawatan Neonatal"
                :date="\Illuminate\Support\Str::before((string) data_get($entri, 'createdAt', ''), ' ')"
                :sub="$this->ringkasEntri($entri)" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-pengkajian-neonatal-perawat-ugd-{{ $rjNo }}" title="Pengkajian Keperawatan Neonatal"
        :subtitle="$selected ? trim((string) data_get($selected, 'createdAt') . ' · ' . $this->ringkasEntri($selected), ' ·') : null"
        :cetakId="data_get($selected, 'createdAt')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
