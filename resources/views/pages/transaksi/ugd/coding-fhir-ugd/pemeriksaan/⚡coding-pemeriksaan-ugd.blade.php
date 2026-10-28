<?php
// resources/views/pages/transaksi/ugd/coding-fhir-ugd/pemeriksaan/coding-pemeriksaan-ugd.blade.php
// Coding SATUSEHAT — kelompok O (Pemeriksaan). Baca saja: tanda vital & nutrisi dari EMR, tiap
// baris dengan KODE yang dikirim — dari peta yang SAMA dengan sendernya:
//   tanda vital → TandaVitalObservationMap (kirim-observation)
//   kesadaran   → NyeriKesadaranObservationMap::kesadaran() (kirim-nyeri-kesadaran UGD; sumbernya
//                 screening.kesadaran — lima pilihan skrining gawat darurat, beda dari RJ)
// Yang tidak dikirim (keadaan umum, nilai kosong) ditandai, bukan disembunyikan.

use Livewire\Component;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Support\Terminologi\TandaVitalObservationMap;
use App\Support\Terminologi\NyeriKesadaranObservationMap;
use App\Support\Terminologi\TampilanObservasiSatuSehat;

new class extends Component {
    use EmrUGDTrait;

    public ?string $rjNo = null;

    /** [['label', 'isi', 'kode'], ...] siap tampil. */
    public array $baris = [];

    /** Tabel hanya-baca data yang dikirim otomatis kartu SATUSEHAT UGD (TampilanObservasiSatuSehat). */
    public array $observasiBaris = [];
    public array $obatBaris = [];

    public function mount(?string $rjNo = null): void
    {
        $this->rjNo = $rjNo;
        $dataUGD = !empty($rjNo) ? ($this->findDataUGD($rjNo) ?? []) : [];
        $tandaVital = $dataUGD['pemeriksaan']['tandaVital'] ?? [];
        $nutrisi = $dataUGD['pemeriksaan']['nutrisi'] ?? [];
        $angkaAtauKosong = fn($nilai) => in_array(trim((string) $nilai), ['', '0'], true) ? '-' : trim((string) $nilai);
        $tidakDikirim = 'tidak dikirim ke Satu Sehat';

        $tekananDarah = TandaVitalObservationMap::TEKANAN_DARAH;
        $adaTekananDarah = filled($tandaVital['sistolik'] ?? null) && filled($tandaVital['distolik'] ?? null);

        $baris = [
            ['label' => 'Keadaan Umum', 'isi' => (string) ($tandaVital['keadaanUmum'] ?? ''), 'kode' => $tidakDikirim],
            ['label' => 'Tekanan Darah',
             'isi' => $adaTekananDarah ? $tandaVital['sistolik'] . ' / ' . $tandaVital['distolik'] . ' mmHg' : '',
             'kode' => 'LOINC ' . $tekananDarah['panel']['code'] . ' (' . $tekananDarah['sistolik']['code'] . ' / ' . $tekananDarah['distolik']['code'] . ') · Blood pressure'
                 . ($adaTekananDarah ? '' : ' — kosong, tidak dikirim')],
        ];

        foreach (TandaVitalObservationMap::VITAL_TUNGGAL as $kunci => $peta) {
            $nilai = trim((string) ($tandaVital[$kunci] ?? ''));
            $baris[] = [
                'label' => $peta['label'],
                'isi' => $nilai !== '' ? $nilai . ' ' . $peta['satuanTampil'] : '',
                'kode' => 'LOINC ' . $peta['loinc'] . ' · ' . $peta['display'] . ($nilai !== '' ? '' : ' — kosong, tidak dikirim'),
            ];
        }

        // Kesadaran — sama dengan kirim-nyeri-kesadaran UGD: dari skrining (screening.kesadaran).
        $kesadaranSkrining = trim((string) ($dataUGD['screening']['kesadaran'] ?? ''));
        $observasiKesadaran = NyeriKesadaranObservationMap::kesadaran($kesadaranSkrining)[0] ?? null;
        $nilaiKesadaran = $observasiKesadaran['valueCodeableConcept'] ?? [];
        $baris[] = [
            'label' => 'Tingkat Kesadaran (skrining)',
            'isi' => $kesadaranSkrining,
            'kode' => $observasiKesadaran === null
                ? 'LOINC 67775-7 · Level of responsiveness — kosong, tidak dikirim'
                : 'LOINC ' . $observasiKesadaran['code']['code'] . ' · ' . $observasiKesadaran['code']['display']
                    . (isset($nilaiKesadaran['code']) ? ' · nilai SNOMED ' . $nilaiKesadaran['code'] . ' ' . $nilaiKesadaran['display'] : ' · nilai dikirim sebagai teks (belum ada kode SNOMED)'),
        ];

        foreach (TandaVitalObservationMap::ANTROPOMETRI as $kunci => $peta) {
            $nilai = $angkaAtauKosong($nutrisi[$kunci] ?? '');
            $baris[] = [
                'label' => $peta['label'],
                'isi' => $nilai !== '-' ? $nilai . ' ' . $peta['satuanTampil'] : '',
                'kode' => 'LOINC ' . $peta['loinc'] . ' · ' . $peta['display'] . ($nilai !== '-' ? '' : ' — kosong, tidak dikirim'),
            ];
        }

        $this->baris = $baris;

        // Status terkirim dari catatan yang SAMA dengan kartu: Observation (observasiLanjutanIds[jam]) &
        // Pemberian Obat (pemberianObatIds[id entri]).
        $satuSehat = $dataUGD['satusehat'] ?? [];
        $this->observasiBaris = TampilanObservasiSatuSehat::barisTandaVital((array) ($dataUGD['observasi']['observasiLanjutan']['tandaVital'] ?? []), (array) ($satuSehat['observasiLanjutanIds'] ?? []));
        $this->obatBaris = TampilanObservasiSatuSehat::barisPemberianObat((array) ($dataUGD['observasi']['obatDanCairan']['pemberianObatDanCairan'] ?? []), (array) ($satuSehat['pemberianObatIds'] ?? []));
    }
};
?>
<div class="space-y-3">

<x-border-form title="Tanda Vital & Nutrisi">
    <div class="space-y-2.5">
        @foreach ($baris as $item)
            <x-satu-sehat.baris-baca :label="$item['label']" :isi="$item['isi']" :kode="$item['kode']" />
        @endforeach
    </div>
</x-border-form>

    <x-satu-sehat.tabel-observasi judul="Observasi Lanjutan — tanda vital per waktu ukur" :baris="$observasiBaris"
        kosong="Belum ada entri Observasi Lanjutan" />
    <x-satu-sehat.tabel-observasi judul="Pemberian Obat & Cairan (MedicationAdministration)" :baris="$obatBaris"
        kosong="Belum ada pemberian obat / cairan" />
</div>
