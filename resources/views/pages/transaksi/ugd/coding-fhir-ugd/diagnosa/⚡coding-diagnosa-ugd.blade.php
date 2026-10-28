<?php
// resources/views/pages/transaksi/ugd/coding-fhir-ugd/diagnosa/coding-diagnosa-ugd.blade.php
// Coding SATUSEHAT — kelompok A (Diagnosis). Baca saja: ICD-10 & ICD-9-CM diisi dokter di EMR.
// Bila yang ada hanya teks bebas, ditandai supaya coder tahu kodenya belum diisi.

use Livewire\Component;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;

new class extends Component {
    use EmrUGDTrait;

    public ?string $rjNo = null;

    /** [label => teks] siap tampil. */
    public array $baris = [];

    public function mount(?string $rjNo = null): void
    {
        $this->rjNo = $rjNo;
        $dataUGD = !empty($rjNo) ? ($this->findDataUGD($rjNo) ?? []) : [];

        $this->baris = [
            'Diagnosis (ICD-10)' => $this->kodeAtauTeks($dataUGD['diagnosis'] ?? [], 'icdX', 'diagDesc', (string) ($dataUGD['diagnosisFreeText'] ?? ''), 'ICD-10'),
            'Prosedur (ICD-9-CM)' => $this->kodeAtauTeks($dataUGD['procedure'] ?? [], 'procedureId', 'procedureDesc', (string) ($dataUGD['procedureFreeText'] ?? ''), 'ICD-9-CM'),
        ];
    }

    private function kodeAtauTeks(array $daftar, string $kunciKode, string $kunciNama, string $teksBebas, string $jenisKode): string
    {
        $daftarKode = collect($daftar)
            ->map(fn($entri) => trim(($entri[$kunciKode] ?? '') . ' — ' . ($entri[$kunciNama] ?? ''), ' —'))
            ->filter()
            ->implode("\n");
        if ($daftarKode !== '') {
            return $daftarKode;
        }

        return trim($teksBebas) !== '' ? trim($teksBebas) . "\n(belum ada kode {$jenisKode} — diisi dokter di EMR)" : '';
    }
};
?>

<x-border-form title="Diagnosis & Prosedur">
    <div class="space-y-2.5">
        @foreach ($baris as $label => $isi)
            <x-satu-sehat.baris-baca :label="$label" :isi="$isi" />
        @endforeach
        <p class="text-xs text-muted dark:text-gray-400">ICD-10 / ICD-9-CM diisi dokter di EMR.</p>
    </div>
</x-border-form>
