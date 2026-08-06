<?php
// Viewer read-only "Surat Pernyataan Penolakan Pengobatan / Obat Tertentu" — display Rekam Medis UGD.
// Pembeda entri = signatureDate. Payload dibangun sendiri (pola inform-consent-view-ugd)
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

    private string $printView = 'pages.components.modul-dokumen.u-g-d.penolakan-obat.cetak-penolakan-obat-print';

    public function mount(?int $rjNo = null, array $entries = []): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->list = array_values($entries);
        $this->navField = 'signatureDate';
    }

    /** Payload cetak identik dgn aksi cetak() di komponen modul-dokumen Penolakan Obat UGD. */
    private function buatData(string $signatureDate): ?array
    {
        $entry = collect($this->list)->firstWhere('signatureDate', $signatureDate);
        if (empty($entry)) {
            $this->dispatch('toast', type: 'error', message: 'Data penolakan obat tidak ditemukan.');
            return null;
        }

        $dataUGD = $this->rjNo ? ($this->findDataUGD($this->rjNo) ?: []) : [];
        $pasien = $this->dvPasien($dataUGD['regNo'] ?? '');

        return array_merge($pasien, [
            'dataUGD' => $dataUGD,
            'form' => $entry,
            'identitasRs' => $this->dvIdentitasRs(),
            'ttdPetugasPath' => $this->dvTtdPath($entry['petugasCode'] ?? null),
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
        $this->dispatch('open-modal', name: "view-penolakan-obat-ugd-{$this->rjNo}");
    }

    public function cetak(string $id): mixed
    {
        $data = $this->buatData($id);
        if (!$data) {
            return null;
        }
        set_time_limit(300);
        $pdf = Pdf::loadView($this->printView, ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'penolakan-obat-ugd-' . ($data['regNo'] ?? $this->rjNo) . '.pdf');
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

    <x-rm.dokumen-view-modal name="view-penolakan-obat-ugd-{{ $rjNo }}" title="Penolakan Pengobatan / Obat Tertentu"
        :subtitle="$selected ? ((data_get($selected, 'pembuatNama') ?: '-') . ' · ' . (data_get($selected, 'namaObat') ?: '-')) : null"
        :cetakId="data_get($selected, 'signatureDate')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
