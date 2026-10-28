<?php
// resources/views/pages/transaksi/ri/coding-fhir-ri/diagnosa/coding-diagnosa-ri.blade.php
// Coding SATUSEHAT RI — kelompok A (Diagnosis). Baca saja: ICD-10 (rstxn_ridtls) & ICD-9-CM (procedure[]) dari EMR.
// Bila yang ada hanya teks bebas, ditandai supaya coder tahu kodenya belum diisi.

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;

new class extends Component {
    use EmrRITrait;

    public ?string $riHdrNo = null;

    /** [label => teks] siap tampil. */
    public array $baris = [];

    public function mount(?string $riHdrNo = null): void
    {
        $this->riHdrNo = $riHdrNo;
        $dataRI = !empty($riHdrNo) ? ($this->findDataRI($riHdrNo) ?? []) : [];

        // Diagnosa RI tersimpan di rstxn_ridtls (bukan JSON) — sumber sama dengan kirim-condition RI.
        $diagnosaList = !empty($riHdrNo) ? DB::table('rstxn_ridtls as d')
            ->join('rsmst_mstdiags as m', 'd.diag_id', '=', 'm.diag_id')
            ->where('d.rihdr_no', $riHdrNo)
            ->orderBy('d.ridtl_dtl')
            ->get(['m.icdx', 'm.diag_desc'])
            ->map(fn($row) => ['icdX' => trim((string) $row->icdx), 'diagDesc' => trim((string) $row->diag_desc)])
            ->all() : [];

        $this->baris = [
            'Diagnosis (ICD-10)' => $this->kodeAtauTeks($diagnosaList, 'icdX', 'diagDesc', '', 'ICD-10'),
            'Prosedur (ICD-9-CM)' => $this->kodeAtauTeks($dataRI['procedure'] ?? [], 'procedureId', 'procedureDesc', (string) ($dataRI['procedureFreeText'] ?? ''), 'ICD-9-CM'),
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
