<?php
// resources/views/pages/transaksi/ri/coding-fhir-ri/perencanaan/coding-perencanaan-ri.blade.php
// Coding SATUSEHAT RI — kelompok P (Perencanaan). Baca saja: terapi (Pengkajian Dokter), status pulang,
// kontrol (SKDP), prognosis, kondisi saat keluar, dan cara keluar yang dikirim OTOMATIS dari status pulang
// lewat DischargeDisposition::rawatInap() (HL7) — sama dengan kirim-encounter RI.

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Support\Terminologi\DischargeDisposition;
use App\Support\Terminologi\KondisiPulang;
use App\Support\Terminologi\Prognosis;

new class extends Component {
    use EmrRITrait;

    public ?string $riHdrNo = null;

    /** [label => teks] siap tampil. */
    public array $baris = [];

    public function mount(?string $riHdrNo = null): void
    {
        $this->riHdrNo = $riHdrNo;
        $dataRI = !empty($riHdrNo) ? ($this->findDataRI($riHdrNo) ?? []) : [];
        $tindakLanjut = $dataRI['perencanaan']['tindakLanjut'] ?? [];
        $keterangan = trim((string) ($tindakLanjut['keteranganTindakLanjut'] ?? ''));
        $caraKeluar = DischargeDisposition::rawatInap($tindakLanjut['tindakLanjutKode'] ?? null, $keterangan);

        $this->baris = [
            'Terapi' => (string) ($dataRI['pengkajianDokter']['rencana']['terapi'] ?? ''),
            'Status Pulang' => trim(DischargeDisposition::label($tindakLanjut['tindakLanjutKode'] ?? '') . ($keterangan !== '' ? ' / ' . $keterangan : ''))
                . (filled($dataRI['exitDate'] ?? null) ? ' · keluar ' . $dataRI['exitDate'] : ''),
            'Tanggal Kontrol' => (string) ($dataRI['kontrol']['tglKontrol'] ?? ''),
            'Prognosis (Satu Sehat)' => $this->teksPrognosis($dataRI['perencanaan']['prognosis'] ?? []),
            'Rencana Tindak Lanjut (Satu Sehat)' => filled($dataRI['kontrol']['tglKontrol'] ?? null)
                ? 'Kontrol ' . $dataRI['kontrol']['tglKontrol'] . ' · SNOMED 185389009 Follow-up visit (otomatis dari SKDP)'
                : '- (tidak ada jadwal kontrol — tidak dikirim, opsional)',
            'Kondisi Saat Keluar (Satu Sehat)' => $this->teksKondisiPulang((string) ($dataRI['perencanaan']['kondisiPulang']['kondisiPulang'] ?? '')),
            'Cara Keluar (Satu Sehat)' => $caraKeluar !== null
                ? $caraKeluar['text'] . ' · HL7 ' . $caraKeluar['coding'][0]['code'] . ' (otomatis dari status pulang)'
                : '- (isi status pulang di Perencanaan EMR)',
        ];
    }

    /** Prognosis + kode SNOMED tetapnya (Prognosis) — dipilih dokter di EMR, tak perlu coding. */
    private function teksPrognosis(array $prognosis): string
    {
        $label = trim((string) ($prognosis['prognosis'] ?? ''));
        $kode = Prognosis::kode($label);

        return $kode !== null
            ? $label . ' · SNOMED ' . $kode['code'] . ' ' . $kode['display'] . ' (otomatis dari EMR)'
            : '- (pilih Prognosis di Perencanaan EMR — opsional)';
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
