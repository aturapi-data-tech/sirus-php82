<?php
// Viewer read-only "Catatan Terapi & Perencanaan Neonatal" — display Rekam Medis RI.
// Dokumen ini dicetak AGREGAT (semua entri jadi satu lembar, dipecah dua daftar:
// terapiDokter & perencanaan), bukan per-entri → payload bespoke meniru cetakSemua()
// di komponen EMR, sehingga helper generik streamCetakDokumenRi tak dipakai.

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

    private string $printView = 'pages.components.modul-dokumen.ri.catatan-terapi-neonatal-ri.cetak-catatan-terapi-neonatal-ri-print';

    public function mount(?string $riHdrNo = null, array $entries = []): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->list = array_values($entries);
    }

    /** Entri jenis "Terapi Dokter" (urutan asli, seperti aksi cetakSemua()). */
    private function terapiDokter(): array
    {
        return collect($this->list)->filter(fn($entri) => ($entri['jenis'] ?? '') === 'Terapi Dokter')->values()->all();
    }

    /** Entri jenis "Perencanaan Keperawatan" (urutan asli, seperti aksi cetakSemua()). */
    private function perencanaan(): array
    {
        return collect($this->list)->filter(fn($entri) => ($entri['jenis'] ?? '') === 'Perencanaan Keperawatan')->values()->all();
    }

    /** Ringkasan baris daftar: cacah entri per jenis. */
    public function ringkasEntri(): string
    {
        return count($this->terapiDokter()) . ' terapi dokter · ' . count($this->perencanaan()) . ' perencanaan keperawatan';
    }

    /** Payload cetak identik dgn aksi cetakSemua() di komponen EMR Catatan Terapi Neonatal. */
    private function buildData(): array
    {
        $dataRi = $this->riHdrNo ? ($this->findDataRI($this->riHdrNo) ?: []) : [];
        $pasien = $this->dvPasien($dataRi['regNo'] ?? '');

        return array_merge($pasien, [
            'ttdPath' => $this->dvTtdPath(collect($this->list)->pluck('ttdCode')->filter()->last()),
            'dataRi' => $dataRi,
            'terapiDokter' => $this->terapiDokter(),
            'perencanaan' => $this->perencanaan(),
            'identitasRs' => $this->dvIdentitasRs(),
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);
    }

    public function lihat(string $id): void
    {
        if (empty($this->list)) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada catatan terapi & perencanaan neonatal.');
            return;
        }
        $this->selected = ['id' => 'lembar'];
        $this->previewHtml = $this->renderDokumenPreview($this->printView, $this->buildData());
        $this->dispatch('open-modal', name: "view-catatan-terapi-neonatal-ri-{$this->riHdrNo}");
    }

    public function cetak(string $id): mixed
    {
        if (empty($this->list)) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada catatan terapi & perencanaan neonatal.');
            return null;
        }
        $data = $this->buildData();
        set_time_limit(300);
        $pdf = Pdf::loadView($this->printView, ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'catatan-terapi-neonatal-' . ($data['regNo'] ?? $this->riHdrNo) . '.pdf');
    }
};
?>

<div>
    <x-border-form title="Catatan Terapi & Perencanaan Neonatal">
        @if (count($list) > 0)
            <x-rm.doc-list-row id="lembar" title="Catatan Terapi & Perencanaan Neonatal"
                :date="\Illuminate\Support\Str::before((string) data_get(collect($list)->last(), 'tglJam', ''), ' ')"
                :sub="$this->ringkasEntri()" />
        @else
            <x-rm.doc-empty />
        @endif
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-catatan-terapi-neonatal-ri-{{ $riHdrNo }}"
        title="Catatan Terapi & Perencanaan Neonatal" :subtitle="$this->ringkasEntri()"
        cetakId="lembar" :previewHtml="$previewHtml" />
</div>
