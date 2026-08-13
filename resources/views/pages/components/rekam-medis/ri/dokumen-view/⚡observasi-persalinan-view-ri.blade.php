<?php
// Viewer read-only "Observasi Persalinan" — display Rekam Medis RI.
// Satu entri = satu LEMBAR berisi banyak baris titik-waktu (key 'rows'), jadi viewer
// ini per-entri. Payload cetak bespoke (rows + diagnosa + ttd) meniru cetakLembar()
// di komponen EMR, dan kertasnya A4 potret.

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

    private string $printView = 'pages.components.modul-dokumen.ri.observasi-persalinan-ri.cetak-observasi-persalinan-ri-print';

    public function mount(?string $riHdrNo = null, array $entries = []): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->list = array_values($entries);
        $this->navField = 'createdAt';
    }

    /**
     * Baris titik-waktu satu lembar, urut kronologis.
     * Entri lama (sebelum rombakan) datar tanpa 'rows' — dibaca sebagai satu baris.
     */
    public function barisLembar(?array $entry): array
    {
        $rows = is_array($entry['rows'] ?? null)
            ? $entry['rows']
            : (collect(['jam', 'sistolik', 'diastolik', 'nadi', 'rr', 'suhu', 'djj', 'his', 'ewsScore', 'obatKeterangan'])
                ->contains(fn($field) => filled($entry[$field] ?? null)) ? [$entry] : []);

        return collect($rows)
            ->sortBy(function ($baris) {
                try {
                    return Carbon::createFromFormat('d/m/Y H:i:s', $baris['jam'] ?? '')->timestamp;
                } catch (\Throwable) {
                    return 0;
                }
            })
            ->values()
            ->all();
    }

    /** Payload cetak identik dgn aksi cetakLembar() di komponen EMR. */
    private function buildData(array $entry): array
    {
        $dataRi = $this->riHdrNo ? ($this->findDataRI($this->riHdrNo) ?: []) : [];
        $pasien = $this->dvPasien($dataRi['regNo'] ?? '');

        return array_merge($pasien, [
            'ttdPath' => $this->dvTtdPath($entry['ttdCode'] ?? null),
            'dataRi' => $dataRi,
            'rows' => $this->barisLembar($entry),
            'diagnosa' => $entry['diagnosa'] ?? '',
            'ttd' => $entry['ttd'] ?? '',
            'ttdDate' => $entry['ttdDate'] ?? '',
            'identitasRs' => $this->dvIdentitasRs(),
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);
    }

    /** Ringkasan baris daftar: cacah titik-waktu + rentang waktunya. */
    public function ringkasEntri(?array $entry): string
    {
        $rows = $this->barisLembar($entry);
        if (empty($rows)) {
            return 'Belum ada titik-waktu';
        }

        $jam = collect($rows)->pluck('jam')->filter()->values();
        $periode = $jam->isEmpty() ? '' : ($jam->count() === 1 ? $jam->first() : $jam->first() . ' – ' . $jam->last());

        return trim(count($rows) . ' titik-waktu' . ($periode ? ' · ' . $periode : ''));
    }

    public function lihat(string $id): void
    {
        $this->selected = collect($this->list)->firstWhere('createdAt', $id) ?: null;
        if (!$this->selected) {
            $this->dispatch('toast', type: 'error', message: 'Lembar observasi persalinan tidak ditemukan.');
            return;
        }
        $this->previewHtml = $this->renderDokumenPreview($this->printView, $this->buildData($this->selected));
        $this->dispatch('open-modal', name: "view-observasi-persalinan-ri-{$this->riHdrNo}");
    }

    public function cetak(string $id): mixed
    {
        $entry = collect($this->list)->firstWhere('createdAt', $id);
        if (empty($entry)) {
            $this->dispatch('toast', type: 'error', message: 'Lembar observasi persalinan tidak ditemukan.');
            return null;
        }
        $data = $this->buildData($entry);
        set_time_limit(300);
        $pdf = Pdf::loadView($this->printView, ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'observasi-persalinan-' . ($data['regNo'] ?? $this->riHdrNo) . '.pdf');
    }
};
?>

<div>
    <x-border-form title="Observasi Persalinan">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'createdAt')))->values() as $entri)
            <x-rm.doc-list-row :id="data_get($entri, 'createdAt')" title="Lembar Observasi Persalinan"
                :date="\Illuminate\Support\Str::before((string) data_get($entri, 'createdAt', ''), ' ')"
                :sub="$this->ringkasEntri($entri) . (data_get($entri, 'finalized') ? ' · Terkunci' : ' · Draft')" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-observasi-persalinan-ri-{{ $riHdrNo }}" title="Observasi Persalinan"
        :subtitle="$selected ? $this->ringkasEntri($selected) : null"
        :cetakId="data_get($selected, 'createdAt')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
