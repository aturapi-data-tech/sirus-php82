<?php
// resources/views/pages/transaksi/rj/coding-fhir-rj/perencanaan/coding-perencanaan-rj.blade.php
// Coding SATUSEHAT — kelompok P (Perencanaan). Baca saja: terapi, tindak lanjut, kontrol, dan
// cara keluar (Encounter.hospitalization.dischargeDisposition) yang dikirim OTOMATIS dari
// Tindak Lanjut lewat DischargeDisposition::rawatJalan() — sama dengan kirim-encounter.

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Support\Terminologi\DischargeDisposition;
use App\Support\Terminologi\KondisiPulang;
use App\Support\Terminologi\Prognosis;

new class extends Component {
    use EmrRJTrait;

    public ?string $rjNo = null;

    /** [label => teks] siap tampil. */
    public array $baris = [];

    public function mount(?string $rjNo = null): void
    {
        $this->rjNo = $rjNo;
        $dataRJ = !empty($rjNo) ? ($this->findDataRJ($rjNo) ?? []) : [];
        $tindakLanjut = $dataRJ['perencanaan']['tindakLanjut'] ?? [];
        $keterangan = trim((string) ($tindakLanjut['keteranganTindakLanjut'] ?? ''));
        $caraKeluar = DischargeDisposition::rawatJalan($tindakLanjut['tindakLanjut'] ?? null, $keterangan);

        $this->baris = [
            'Terapi' => (string) ($dataRJ['perencanaan']['terapi']['terapi'] ?? ''),
            'Tindak Lanjut' => trim(($tindakLanjut['tindakLanjut'] ?? '') . ($keterangan !== '' ? ' / ' . $keterangan : '')),
            'Tanggal Kontrol' => (string) ($dataRJ['kontrol']['tglKontrol'] ?? ''),
            'Prognosis (Satu Sehat)' => $this->teksPrognosis($dataRJ['perencanaan']['prognosis'] ?? []),
            'Rencana Tindak Lanjut (Satu Sehat)' => filled($dataRJ['kontrol']['tglKontrol'] ?? null)
                ? 'Kontrol ' . $dataRJ['kontrol']['tglKontrol'] . ' · SNOMED 185389009 Follow-up visit (otomatis dari SKDP)'
                : '- (tidak ada jadwal kontrol — tidak dikirim, opsional)',
            'Kondisi Saat Pulang (Satu Sehat)' => $this->teksKondisiPulang((string) ($dataRJ['perencanaan']['kondisiPulang']['kondisiPulang'] ?? '')),
            'Cara Keluar (Satu Sehat)' => $caraKeluar !== null
                ? $caraKeluar['text'] . ' · ' . $caraKeluar['coding'][0]['code'] . ' (otomatis dari Tindak Lanjut)'
                : '- (isi Tindak Lanjut di EMR)',
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
            : '- (isi Kondisi Saat Pulang di Perencanaan EMR — wajib Satu Sehat)';
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
