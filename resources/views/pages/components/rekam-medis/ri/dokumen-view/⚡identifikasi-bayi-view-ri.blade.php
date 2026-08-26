<?php
// Viewer read-only "Identifikasi Bayi" (VK/Kebidanan) — display Rekam Medis RI.
// Payload cetak seragam (dataRi/form/identitasRs/ttdPath), TTD tunggal 'ttdCode' —
// field serah-terima (penolong/pemasang gelang/menyerahkan/menerima/saksi) hanya teks
// di dalam 'form', bukan path gambar → helper generik trait sudah cukup.

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

    private string $printView = 'identifikasi-bayi-ri.cetak-identifikasi-bayi-ri-print';
    private string $filePrefix = 'identifikasi-bayi-ri';
    private string $ttdKey = 'ttdPath';
    private ?string $ttdCodeField = 'ttdCode';

    public function mount(?string $riHdrNo = null, array $entries = []): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->list = array_values($entries);
        $this->navField = 'createdAt';
    }

    /** Ringkasan baris list: nama bayi · warna gelang · no register bayi. */
    public function ringkasEntri(?array $entri): string
    {
        $bagian = [];

        $namaBayi = trim((string) data_get($entri, 'namaBayi', ''));
        if ($namaBayi !== '') {
            $bagian[] = \Illuminate\Support\Str::limit($namaBayi, 40);
        }

        $warnaGelang = trim((string) data_get($entri, 'warnaGelang', ''));
        if ($warnaGelang !== '') {
            $bagian[] = 'Gelang ' . $warnaGelang;
        }

        $noRegisterBayi = trim((string) data_get($entri, 'noRegisterBayi', ''));
        if ($noRegisterBayi !== '') {
            $bagian[] = 'No. Reg ' . $noRegisterBayi;
        }

        return empty($bagian) ? '-' : implode(' · ', $bagian);
    }

    public function lihat(string $id): void
    {
        $this->selected = collect($this->list)->firstWhere('createdAt', $id) ?: null;
        if (!$this->selected) {
            $this->dispatch('toast', type: 'error', message: 'Data identifikasi bayi tidak ditemukan.');
            return;
        }
        $this->previewHtml = $this->previewDokumenRi($this->selected, $this->printView, $this->ttdKey, $this->ttdCodeField);
        $this->dispatch('open-modal', name: "view-identifikasi-bayi-ri-{$this->riHdrNo}");
    }

    public function cetak(string $id): mixed
    {
        return $this->streamCetakDokumenRi(collect($this->list)->firstWhere('createdAt', $id), $this->printView, $this->filePrefix, $this->ttdKey, $this->ttdCodeField);
    }
};
?>

<div>
    <x-border-form title="Identifikasi Bayi">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'createdAt')))->values() as $entri)
            <x-rm.doc-list-row :id="data_get($entri, 'createdAt')" title="Identifikasi Bayi"
                :date="\Illuminate\Support\Str::before((string) data_get($entri, 'createdAt', ''), ' ')"
                :sub="$this->ringkasEntri($entri)" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-identifikasi-bayi-ri-{{ $riHdrNo }}" title="Identifikasi Bayi"
        :subtitle="$selected ? trim((string) data_get($selected, 'createdAt') . ' · ' . $this->ringkasEntri($selected), ' ·') : null"
        :cetakId="data_get($selected, 'createdAt')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
