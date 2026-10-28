<?php
// resources/views/pages/transaksi/ugd/coding-fhir-ugd/perencanaan/coding-perencanaan-ugd.blade.php
// Coding SATUSEHAT — kelompok P (Perencanaan). Baca saja: terapi, tindak lanjut, kontrol, kondisi
// saat keluar, dan cara keluar (Encounter.hospitalization.dischargeDisposition) yang dikirim OTOMATIS
// dari Tindak Lanjut lewat DischargeDisposition::gawatDarurat() — sama dengan kirim-encounter UGD.
// Prognosis TIDAK ada: alur IGD tak memuatnya (beda dari RJ bab 22).

use Livewire\Component;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Support\Terminologi\DischargeDisposition;
use App\Support\Terminologi\KondisiPulang;

new class extends Component {
    use EmrUGDTrait;

    public ?string $rjNo = null;

    /** [label => teks] siap tampil. */
    public array $baris = [];

    public function mount(?string $rjNo = null): void
    {
        $this->rjNo = $rjNo;
        $dataUGD = !empty($rjNo) ? ($this->findDataUGD($rjNo) ?? []) : [];
        $tindakLanjut = $dataUGD['perencanaan']['tindakLanjut'] ?? [];
        $keterangan = trim((string) ($tindakLanjut['keteranganTindakLanjut'] ?? ''));
        $caraKeluar = DischargeDisposition::gawatDarurat($tindakLanjut['tindakLanjut'] ?? null, $keterangan);

        $this->baris = [
            'Terapi' => (string) ($dataUGD['perencanaan']['terapi']['terapi'] ?? ''),
            'Tindak Lanjut' => trim(($tindakLanjut['tindakLanjut'] ?? '') . ($keterangan !== '' ? ' / ' . $keterangan : '')),
            'Tanggal Kontrol' => (string) ($dataUGD['kontrol']['tglKontrol'] ?? ''),
            'Rencana Tindak Lanjut (Satu Sehat)' => filled($dataUGD['kontrol']['tglKontrol'] ?? null)
                ? 'Kontrol ' . $dataUGD['kontrol']['tglKontrol'] . ' · SNOMED 185389009 Follow-up visit (otomatis dari SKDP)'
                : '- (tidak ada jadwal kontrol — tidak dikirim, opsional)',
            'Kondisi Saat Keluar (Satu Sehat)' => $this->teksKondisiPulang((string) ($dataUGD['perencanaan']['kondisiPulang']['kondisiPulang'] ?? '')),
            'Cara Keluar (Satu Sehat)' => $caraKeluar !== null
                ? $caraKeluar['text'] . ' · ' . $caraKeluar['coding'][0]['code'] . ' (otomatis dari Tindak Lanjut)'
                : '- (isi Tindak Lanjut di EMR)',
        ];
    }

    /** Kondisi pulang + kode SNOMED tetapnya (KondisiPulang) — diisi dokter di EMR, tak perlu coding. */
    private function teksKondisiPulang(string $label): string
    {
        $kode = KondisiPulang::kode($label);

        return $kode !== null
            ? $label . ' · SNOMED ' . $kode['code'] . ' ' . $kode['display'] . ' (otomatis dari EMR)'
            : '- (isi Kondisi Saat Keluar di Perencanaan EMR — wajib Satu Sehat)';
    }
};
?>

<x-border-form title="Perencanaan">
    <div class="space-y-2.5">
        @foreach ($baris as $label => $isi)
            <x-satu-sehat.baris-baca :label="$label" :isi="$isi" />
        @endforeach
    </div>
</x-border-form>
