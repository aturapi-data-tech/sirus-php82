<?php
// Viewer read-only "Pengkajian Awal Bayi" (RM 14 e.3) — display Rekam Medis UGD.
// Port dari viewer RI; blade cetak dipakai bersama dengan RI.
// Payload cetak seragam (dataRi/form/identitasRs/ttdPath) → pakai helper generik trait.

use Livewire\Component;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Dokumen\DokumenViewSupportTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, DokumenViewSupportTrait;

    public ?int $rjNo = null;
    public array $list = [];
    public ?array $selected = null;
    public string $previewHtml = '';

    private string $printView = 'pages.components.modul-dokumen.ri.pengkajian-awal-bayi-ri.cetak-pengkajian-awal-bayi-ri-print';
    private string $filePrefix = 'pengkajian-awal-bayi-ugd';
    private string $ttdKey = 'ttdPath';
    private ?string $ttdCodeField = 'ttdCode';

    public function mount(?int $rjNo = null, array $entries = []): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->list = array_values($entries);
        $this->navField = 'createdAt';
    }

    /** Total APGAR per menit (5 komponen, nilai 0-2); null bila belum diisi sama sekali. */
    private function totalApgar(?array $entri, string $menit): ?int
    {
        $komponen = ['warnaKulit', 'reflek', 'denyutJantung', 'tonus', 'usahaNafas'];
        $terisi = false;
        $total = 0;
        foreach ($komponen as $nama) {
            $nilai = data_get($entri, $nama . $menit, '');
            if ($nilai !== '' && $nilai !== null) {
                $terisi = true;
                $total += (int) $nilai;
            }
        }
        return $terisi ? $total : null;
    }

    /** Ringkasan baris list: tgl/jam lahir · BB lahir · APGAR 1'/5'/10'. */
    public function ringkasEntri(?array $entri): string
    {
        $bagian = [];

        // Tgl & jam lahir sudah digabung di satu kolom 'tglLahir' (d/m/Y H:i:s).
        $tglLahir = trim((string) data_get($entri, 'tglLahir', ''));
        if ($tglLahir !== '') {
            $bagian[] = 'Lahir ' . $tglLahir;
        }

        $beratBadan = trim((string) data_get($entri, 'beratBadan', ''));
        if ($beratBadan !== '') {
            $bagian[] = 'BB ' . $beratBadan;
        }

        $apgar = [];
        foreach (['1', '5', '10'] as $menit) {
            $nilai = $this->totalApgar($entri, $menit);
            if ($nilai !== null) {
                $apgar[] = (string) $nilai;
            }
        }
        if (!empty($apgar)) {
            $bagian[] = 'APGAR ' . implode('-', $apgar);
        }

        $diagnosa = trim((string) data_get($entri, 'diagnosaUtama', ''));
        if ($diagnosa !== '') {
            $bagian[] = \Illuminate\Support\Str::limit($diagnosa, 50);
        }

        return empty($bagian) ? '-' : implode(' · ', $bagian);
    }

    /** Data kunjungan UGD — sumber regNo utk identitas pasien di payload cetak. */
    private function dataTxn(): array
    {
        return $this->rjNo ? ($this->findDataUGD($this->rjNo) ?: []) : [];
    }

    public function lihat(string $id): void
    {
        $this->selected = collect($this->list)->firstWhere('createdAt', $id) ?: null;
        if (!$this->selected) {
            $this->dispatch('toast', type: 'error', message: 'Data pengkajian awal bayi tidak ditemukan.');
            return;
        }
        $this->previewHtml = $this->previewDokumenTxn($this->selected, $this->printView, $this->ttdKey, $this->ttdCodeField, $this->dataTxn());
        $this->dispatch('open-modal', name: "view-pengkajian-awal-bayi-ugd-{$this->rjNo}");
    }

    public function cetak(string $id): mixed
    {
        return $this->streamCetakDokumenTxn(collect($this->list)->firstWhere('createdAt', $id), $this->printView, $this->filePrefix, $this->ttdKey, $this->ttdCodeField, $this->dataTxn());
    }
};
?>

<div>
    <x-border-form title="Pengkajian Awal Bayi">
        @forelse (collect($list)->filter(fn($entri) => filled(data_get($entri, 'createdAt')))->values() as $entri)
            <x-rm.doc-list-row :id="data_get($entri, 'createdAt')" title="Pengkajian Awal Bayi"
                :date="\Illuminate\Support\Str::before((string) data_get($entri, 'createdAt', ''), ' ')"
                :sub="$this->ringkasEntri($entri)" />
        @empty
            <x-rm.doc-empty />
        @endforelse
    </x-border-form>

    <x-rm.dokumen-view-modal name="view-pengkajian-awal-bayi-ugd-{{ $rjNo }}" title="Pengkajian Awal Bayi"
        :subtitle="$selected ? trim((string) data_get($selected, 'createdAt') . ' · ' . $this->ringkasEntri($selected), ' ·') : null"
        :cetakId="data_get($selected, 'createdAt')" :previewHtml="$previewHtml"
        :navTotal="$this->navTotal()" :navPos="$this->navPos()" />
</div>
