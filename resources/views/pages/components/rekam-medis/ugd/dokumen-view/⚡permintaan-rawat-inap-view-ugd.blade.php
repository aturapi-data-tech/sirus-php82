<?php
// Viewer read-only "Surat Permintaan Rawat Inap" — display Rekam Medis UGD.
// Pembeda entri = signatureDate. Payload dibangun sendiri (pola second-opinion-view-ugd)
// karena helper seragam DokumenViewSupportTrait berbasis RI.

use Livewire\Component;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Support\TtdUser;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?int $rjNo = null;
    public array $list = [];
    public ?array $selected = null;
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.ugd.permintaan-rawat-inap.cetak-permintaan-rawat-inap-print';

    public function mount(?int $rjNo = null, array $entries = []): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->list = array_values($entries);
        $this->navField = 'signatureDate';
    }

    /** Payload cetak identik dgn aksi cetak() di komponen modul-dokumen Permintaan Rawat Inap UGD. */
    private function buatData(string $signatureDate): ?array
    {
        $entry = collect($this->list)->firstWhere('signatureDate', $signatureDate);
        if (empty($entry)) {
            $this->dispatch('toast', type: 'error', message: 'Data surat permintaan rawat inap tidak ditemukan.');
            return null;
        }

        $dataUGD = $this->rjNo ? ($this->findDataUGD($this->rjNo) ?: []) : [];
        $pasien = $this->pasienDokumen($dataUGD['regNo'] ?? '');

        return array_merge($pasien, [
            'form' => $entry,
            'identitasRs' => $this->identitasRsDokumen(),
            'ttdDokterPath' => TtdUser::pathBerkasDariKode($entry['dokterIgdCode'] ?? null),
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
        $this->dispatch('open-modal', name: "view-permintaan-rawat-inap-ugd-{$this->rjNo}");
    }

    public function cetak(string $id): mixed
    {
        $data = $this->buatData($id);
        if (!$data) {
            return null;
        }
        set_time_limit(300);
        $pdf = Pdf::loadView($this->printView, ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'permintaan-rawat-inap-ugd-' . ($data['regNo'] ?? $this->rjNo) . '.pdf');
    }
};
?>

<div>
    <x-border-form title="Surat Permintaan Rawat Inap">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'signatureDate')))->values() as $entri)
            <x-rm.doc-list-row :id="data_get($entri, 'signatureDate')" :title="filled(data_get($entri, 'dpjpName')) ? 'DPJP: ' . data_get($entri, 'dpjpName') : 'Permintaan Rawat Inap'"
                :date="data_get($entri, 'tglPermintaan')"
                :sub="filled(data_get($entri, 'diagnosis')) ? \Illuminate\Support\Str::limit(data_get($entri, 'diagnosis'), 90) : null" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-permintaan-rawat-inap-ugd-{{ $rjNo }}" title="Surat Permintaan Rawat Inap"
        :subtitle="$selected ? ((data_get($selected, 'dpjpName') ?: '-') . ' · ' . (data_get($selected, 'tglPermintaan') ?: '-')) : null"
        :cetakId="data_get($selected, 'signatureDate')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
