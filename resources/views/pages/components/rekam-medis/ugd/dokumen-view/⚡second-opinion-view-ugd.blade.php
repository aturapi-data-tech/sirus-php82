<?php
// Viewer read-only "Permintaan Second Opinion" — display Rekam Medis UGD.
// Pembeda entri = signatureDate. Payload dibangun sendiri (pola penolakan-obat-view-ugd)
// karena helper seragam DokumenViewSupportTrait berbasis RI.

use Livewire\Component;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?int $rjNo = null;
    public array $list = [];
    public ?array $selected = null;
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.ugd.second-opinion.cetak-second-opinion-print';

    public function mount(?int $rjNo = null, array $entries = []): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->list = array_values($entries);
        $this->navField = 'signatureDate';
    }

    /** Payload cetak identik dgn aksi cetak() di komponen modul-dokumen Second Opinion UGD. */
    private function buatData(string $signatureDate): ?array
    {
        $entry = collect($this->list)->firstWhere('signatureDate', $signatureDate);
        if (empty($entry)) {
            $this->dispatch('toast', type: 'error', message: 'Data second opinion tidak ditemukan.');
            return null;
        }

        $dataUGD = $this->rjNo ? ($this->findDataUGD($this->rjNo) ?: []) : [];
        $pasien = $this->dvPasien($dataUGD['regNo'] ?? '');

        return array_merge($pasien, [
            'dataUGD' => $dataUGD,
            'form' => $entry,
            'identitasRs' => $this->dvIdentitasRs(),
            'ttdPemberiPath' => $this->dvTtdPath($entry['pemberiInfoCode'] ?? null),
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);
    }

    public function lihat(string $id): void
    {
        $this->selected = collect($this->list)->firstWhere('signatureDate', $id) ?: null;
        $data = $this->buatData($id);
        if (!$data) {
            return;
        }
        $this->previewHtml = $this->renderDokumenPreview($this->printView, $data);
        $this->dispatch('open-modal', name: "view-second-opinion-ugd-{$this->rjNo}");
    }

    public function cetak(string $id): mixed
    {
        $data = $this->buatData($id);
        if (!$data) {
            return null;
        }
        set_time_limit(300);
        $pdf = Pdf::loadView($this->printView, ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'second-opinion-ugd-' . ($data['regNo'] ?? $this->rjNo) . '.pdf');
    }
};
?>

<div>
    <x-border-form title="Permintaan Second Opinion">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'signatureDate')))->values() as $entri)
            <x-rm.doc-list-row :id="data_get($entri, 'signatureDate')" :title="data_get($entri, 'kategori') ?: 'Second Opinion'"
                :date="data_get($entri, 'tglPermintaan')"
                :sub="filled(data_get($entri, 'uraian')) ? \Illuminate\Support\Str::limit(data_get($entri, 'uraian'), 90) : null" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-second-opinion-ugd-{{ $rjNo }}" title="Permintaan Second Opinion"
        :subtitle="$selected ? ((data_get($selected, 'kategori') ?: '-') . ' · ' . (data_get($selected, 'tglPermintaan') ?: '-')) : null"
        :cetakId="data_get($selected, 'signatureDate')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
