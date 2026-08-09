<?php
// Viewer read-only "Pengkajian Pre Operasi" (gabung Site Marking) — display Rekam Medis UGD.
// Print dipakai bersama dgn RI: cetak-pengkajian-pre-op-ri-print (payload form + 3 path TTD).

use Livewire\Component;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?int $rjNo = null;
    public array $list = [];
    public ?array $selected = null;
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.r-i.pengkajian-pre-op-ri.cetak-pengkajian-pre-op-ri-print';

    public function mount(?int $rjNo = null, array $entries = []): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->list = array_values($entries);
        $this->navField = 'createdAt';
    }

    /** Payload cetak identik dgn aksi cetak() di komponen EMR Pengkajian Pre Operasi UGD. */
    private function buildData(array $entry): array
    {
        $dataUgd = $this->rjNo ? ($this->findDataUGD($this->rjNo) ?: []) : [];
        $pasien = $this->dvPasien($dataUgd['regNo'] ?? '');

        $ttdPaths = [];
        foreach (['ttdPerawatRuangan', 'ttdPerawatKamarBedah', 'ttdDokterOperator'] as $ttdField) {
            $ttdPaths[$ttdField . 'Path'] = $this->dvTtdPath($entry[$ttdField . 'Code'] ?? null);
        }

        return array_merge($pasien, $ttdPaths, [
            'dataRi' => $dataUgd,
            'form' => $entry,
            'identitasRs' => $this->dvIdentitasRs(),
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);
    }

    public function lihat(string $id): void
    {
        $this->selected = collect($this->list)->firstWhere('createdAt', $id) ?: null;
        if (!$this->selected) {
            $this->dispatch('toast', type: 'error', message: 'Data pengkajian pre operasi tidak ditemukan.');
            return;
        }
        $this->previewHtml = $this->renderDokumenPreview($this->printView, $this->buildData($this->selected));
        $this->dispatch('open-modal', name: "view-pengkajian-pre-op-ugd-{$this->rjNo}");
    }

    public function cetak(string $id): mixed
    {
        $entry = collect($this->list)->firstWhere('createdAt', $id);
        if (empty($entry)) {
            $this->dispatch('toast', type: 'error', message: 'Data pengkajian pre operasi tidak ditemukan.');
            return null;
        }
        $data = $this->buildData($entry);
        set_time_limit(300);
        $pdf = Pdf::loadView($this->printView, ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'pengkajian-pre-op-ugd-' . ($data['regNo'] ?? $this->rjNo) . '.pdf');
    }
};
?>

<div>
    <x-border-form title="Pengkajian Pre Operasi (Site Marking)">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'createdAt')))->values() as $entri)
            <x-rm.doc-list-row :id="data_get($entri, 'createdAt')" :title="data_get($entri, 'rencanaOperasi') ?: 'Pengkajian Pre Operasi'"
                :date="data_get($entri, 'tanggalOperasi')"
                :sub="'Operator: ' . (data_get($entri, 'dokterOperator') ?: '-') . (data_get($entri, 'finalized') ? ' · Terkunci' : ' · Draft')" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-pengkajian-pre-op-ugd-{{ $rjNo }}" title="Pengkajian Pre Operasi"
        :subtitle="$selected ? ((data_get($selected, 'tanggalOperasi') ?: '-') . ' · ' . (data_get($selected, 'dokterOperator') ?: '-')) : null"
        :cetakId="data_get($selected, 'createdAt')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
