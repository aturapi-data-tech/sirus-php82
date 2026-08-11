<?php
// Viewer read-only "Observasi Nifas" — display Rekam Medis RI.
// Dokumen ini dicetak per-LEMBAR (semua titik-waktu jadi satu tabel), bukan per-entri,
// jadi payload cetak bespoke (rows tanpa diagnosa/ttd) meniru cetakLembar() di komponen EMR.
// Kertas A4 LANDSCAPE → helper generik streamCetakDokumenRi (selalu portrait) tak dipakai.

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?string $riHdrNo = null;
    public array $list = [];
    public ?array $selected = null;
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.r-i.observasi-nifas-ri.cetak-observasi-nifas-ri-print';

    public function mount(?string $riHdrNo = null, array $entries = []): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->list = array_values($entries);
    }

    /** Baris observasi urut kronologis (string dd/mm/yyyy tak bisa di-sort leksikografis). */
    private function rows(): array
    {
        return collect($this->list)
            ->sortBy(function ($entri) {
                try {
                    return Carbon::createFromFormat('d/m/Y H:i:s', $entri['tglJam'] ?? '')->timestamp;
                } catch (\Throwable) {
                    return 0;
                }
            })
            ->values()
            ->all();
    }

    /** Payload cetak identik dgn aksi cetakLembar() di komponen EMR Observasi Nifas. */
    private function buildData(array $rows): array
    {
        $dataRi = $this->riHdrNo ? ($this->findDataRI($this->riHdrNo) ?: []) : [];
        $pasien = $this->dvPasien($dataRi['regNo'] ?? '');

        return array_merge($pasien, [
            'ttdPath' => $this->dvTtdPath(collect($rows)->pluck('ttdCode')->filter()->last()),
            'dataRi' => $dataRi,
            'rows' => $rows,
            'identitasRs' => $this->dvIdentitasRs(),
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);
    }

    public function lihat(string $id): void
    {
        $rows = $this->rows();
        if (empty($rows)) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada baris observasi nifas.');
            return;
        }
        $this->selected = ['id' => 'lembar'];
        $this->previewHtml = $this->renderDokumenPreview($this->printView, $this->buildData($rows));
        $this->dispatch('open-modal', name: "view-observasi-nifas-ri-{$this->riHdrNo}");
    }

    public function cetak(string $id): mixed
    {
        $rows = $this->rows();
        if (empty($rows)) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada baris observasi nifas.');
            return null;
        }
        $data = $this->buildData($rows);
        set_time_limit(300);
        $pdf = Pdf::loadView($this->printView, ['data' => $data])->setPaper('A4', 'landscape');
        return response()->streamDownload(fn() => print $pdf->output(), 'observasi-nifas-' . ($data['regNo'] ?? $this->riHdrNo) . '.pdf');
    }
};
?>

<div>
    <x-border-form title="Observasi Nifas">
        @if (count($list) > 0)
            <x-rm.doc-list-row id="lembar" title="Lembar Observasi Nifas"
                :date="\Illuminate\Support\Str::before((string) data_get(collect($list)->last(), 'tglJam', ''), ' ')"
                :sub="count($list) . ' titik pemantauan'" />
        @else
            <x-rm.doc-empty />
        @endif
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-observasi-nifas-ri-{{ $riHdrNo }}" title="Observasi Nifas"
        :subtitle="count($list) . ' titik pemantauan'"
        cetakId="lembar" :previewHtml="$previewHtml" />
</div>
