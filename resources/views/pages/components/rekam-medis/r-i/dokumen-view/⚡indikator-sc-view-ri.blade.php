<?php
// Viewer read-only "Indikator Proses SC" — display Rekam Medis RI.
// Print butuh daftar 15 pertanyaan indikator & label klasifikasi Robson
// (konstanta komponen EMR) → dikirim via $extra ke helper generik trait.

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?string $riHdrNo = null;
    public array $list = [];
    public ?array $selected = null;
    public string $previewHtml = '';

    private string $printView = 'indikator-sc-ri.cetak-indikator-sc-ri-print';
    private string $filePrefix = 'indikator-sc-ri';
    private string $ttdKey = 'ttdPath';
    private ?string $ttdCodeField = 'ttdCode';

    public function mount(?string $riHdrNo = null, array $entries = []): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->list = array_values($entries);
        $this->navField = 'createdAt';
    }

    public function lihat(string $id): void
    {
        $this->selected = collect($this->list)->firstWhere('createdAt', $id) ?: null;
        if (!$this->selected) {
            $this->dispatch('toast', type: 'error', message: 'Data indikator SC tidak ditemukan.');
            return;
        }
        $this->previewHtml = $this->previewDokumenRi($this->selected, $this->printView, $this->ttdKey, $this->ttdCodeField, $this->opsiIndikator());
        $this->dispatch('open-modal', name: "view-indikator-sc-ri-{$this->riHdrNo}");
    }

    public function cetak(string $id): mixed
    {
        return $this->streamCetakDokumenRi(collect($this->list)->firstWhere('createdAt', $id), $this->printView, $this->filePrefix, $this->ttdKey, $this->ttdCodeField, $this->opsiIndikator());
    }

    /** 15 pertanyaan indikator + klasifikasi Robson (cerminan konstanta komponen EMR indikator SC). */
    private function opsiIndikator(): array
    {
        return [
            'indikatorPertanyaan' => [
                'Pasien melakukan ANC minimal 3x di RS tersebut',
                'Pasien memiliki & membawa buku pink KIA sebelum SC',
                'Pasien datang dengan KU baik sebelum tindakan SC',
                'Pasien datang dengan GCS normal (14-15) sebelum SC',
                'Perubahan TD sistolik >30 mnt sebelum & sesudah SC disertai gejala syok',
                'Diperiksa darah lengkap sebelum SC (Hb, Leukosit, Trombosit, Ht)',
                'Diperiksa darah lengkap setelah SC (Hb, Leukosit, Trombosit, Ht)',
                'Diperiksa PT/APTT atau CT/BT sebelum SC',
                'Dicatat golongan darah sebelum SC',
                'Dilakukan transfusi sesuai indikasi dan/atau Hb <8 g/dl sebelum SC',
                'Diperiksa urinalisis sebelum SC',
                'Memiliki data USG sebelum SC',
                'Memiliki data laboratorium HIV sebelum SC',
                'Memiliki data laboratorium Hepatitis sebelum SC',
                'Asesmen persalinan menggunakan partograf ditulis lengkap sebelum SC',
            ],
            'klasifikasiOptions' => [
                'a' => 'Nulipara tunggal presentasi kepala >=37mgg spontan',
                'b' => 'Nulipara tunggal presentasi kepala >=37mgg induksi',
                'c' => 'Multipara tanpa riwayat perlukaan uterus tunggal presentasi kepala >=37mgg spontan',
                'd' => 'Multipara tanpa riwayat perlukaan uterus tunggal presentasi kepala >=37mgg induksi/SC',
                'e' => 'Multipara riwayat perlukaan uterus tunggal presentasi kepala >=37mgg',
                'f' => 'Nulipara tunggal sungsang',
                'g' => 'Multipara tunggal sungsang riwayat perlukaan uterus',
                'h' => 'Kehamilan multipel riwayat perlukaan uterus',
                'i' => 'Tunggal oblik/melintang riwayat perlukaan uterus',
                'j' => 'Tunggal presentasi kepala <36mgg riwayat perlukaan uterus',
            ],
        ];
    }
};
?>

<div>
    <x-border-form title="Indikator Proses SC">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'createdAt')))->values() as $entri)
            <x-rm.doc-list-row :id="data_get($entri, 'createdAt')" title="Indikator Proses SC"
                :date="\Illuminate\Support\Str::before((string) data_get($entri, 'createdAt', ''), ' ')"
                :sub="\Illuminate\Support\Str::limit('Indikasi SC: ' . (implode(', ', array_filter((array) data_get($entri, 'indikasiSc', []))) ?: (data_get($entri, 'indikasiScLain') ?: '-')), 80)" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-indikator-sc-ri-{{ $riHdrNo }}" title="Indikator Proses SC"
        :subtitle="$selected ? trim((string) data_get($selected, 'createdAt') . ' · Klasifikasi ' . (strtoupper((string) data_get($selected, 'diagnosisKlasifikasi')) ?: '-'), ' ·') : null"
        :cetakId="data_get($selected, 'createdAt')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
