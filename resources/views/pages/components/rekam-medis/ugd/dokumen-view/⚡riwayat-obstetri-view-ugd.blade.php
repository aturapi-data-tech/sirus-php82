<?php
// Viewer read-only "Riwayat Obstetri" (RM 44.b) — display Rekam Medis UGD.
// Port dari viewer RI; blade cetak dipakai bersama dengan RI.
// Payload cetak sebenarnya seragam (dataRi/form/identitasRs/ttdPath), TAPI kertasnya
// A4 LANDSCAPE sedangkan helper generik streamCetakDokumenRi() selalu portrait →
// viewer ini self-contained: buildData() meniru aksi cetak() di komponen EMR.

use Livewire\Component;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Support\TtdUser;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?int $rjNo = null;
    public array $list = [];
    public ?array $selected = null;
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.ri.riwayat-obstetri-ri.cetak-riwayat-obstetri-ri-print';

    public function mount(?int $rjNo = null, array $entries = []): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->list = array_values($entries);
        $this->navField = 'createdAt';
    }

    /** Payload cetak identik dgn aksi cetak() di komponen EMR Riwayat Obstetri. */
    private function buildData(array $entry): array
    {
        $dataRi = $this->rjNo ? ($this->findDataUGD($this->rjNo) ?: []) : [];
        $pasien = $this->pasienDokumen($dataRi['regNo'] ?? '');

        return array_merge($pasien, [
            'ttdPath' => TtdUser::pathBerkasDariKode($entry['ttdCode'] ?? null),
            'dataRi' => $dataRi,
            'form' => $entry,
            'identitasRs' => $this->identitasRsDokumen(),
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);
    }

    public function lihat(string $id): void
    {
        $this->selected = collect($this->list)->firstWhere('createdAt', $id) ?: null;
        if (!$this->selected) {
            $this->dispatch('toast', type: 'error', message: 'Data riwayat obstetri tidak ditemukan.');
            return;
        }
        $this->previewHtml = $this->renderDokumenPreview($this->printView, $this->buildData($this->selected));
        $this->dispatch('open-modal', name: "view-riwayat-obstetri-ugd-{$this->rjNo}");
    }

    /**
     * Ringkasan baris daftar: G-P-A + jumlah baris riwayat kehamilan lalu.
     * Nilai dibaca dgn filled(), BUKAN `?:` — abortus/para "0" itu falsy di PHP
     * sehingga G2P1A0 (kasus paling umum) akan salah tampil jadi "A-".
     */
    public function ringkasEntri(array $entri): string
    {
        $angka = fn(string $field) => filled(data_get($entri, $field)) ? (string) data_get($entri, $field) : '-';

        $gravidaParaAbortus = 'G' . $angka('gravida') . ' P' . $angka('para') . ' A' . $angka('abortus');

        return $gravidaParaAbortus . ' · ' . count((array) data_get($entri, 'rows', [])) . ' riwayat kehamilan';
    }

    public function cetak(string $id): mixed
    {
        $entry = collect($this->list)->firstWhere('createdAt', $id);
        if (empty($entry)) {
            $this->dispatch('toast', type: 'error', message: 'Data riwayat obstetri tidak ditemukan.');
            return null;
        }
        $data = $this->buildData($entry);
        set_time_limit(300);
        $pdf = Pdf::loadView($this->printView, ['data' => $data])->setPaper('A4', 'landscape');
        return response()->streamDownload(fn() => print $pdf->output(), 'riwayat-obstetri-ugd-' . ($data['regNo'] ?? $this->rjNo) . '.pdf');
    }
};
?>

<div>
    <x-border-form title="Riwayat Obstetri">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'createdAt')))->values() as $entri)
            <x-rm.doc-list-row :id="data_get($entri, 'createdAt')" title="Riwayat Obstetri"
                :date="\Illuminate\Support\Str::before((string) data_get($entri, 'createdAt', ''), ' ')"
                :sub="$this->ringkasEntri($entri) . (data_get($entri, 'finalized') ? ' · Terkunci' : ' · Draft')" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-riwayat-obstetri-ugd-{{ $rjNo }}" title="Riwayat Obstetri"
        :subtitle="$selected ? (trim((string) data_get($selected, 'createdAt')) . ' · ' . $this->ringkasEntri($selected)) : null"
        :cetakId="data_get($selected, 'createdAt')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
