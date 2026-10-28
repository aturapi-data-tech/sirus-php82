<?php
// resources/views/pages/transaksi/ri/coding-fhir-ri/pemeriksaan/coding-pemeriksaan-ri.blade.php
// Coding SATUSEHAT RI — kelompok O. Baca saja: pemeriksaan awal dari Pengkajian Awal Rawat Inap (tanda
// vital, BB/TB, kesadaran) + jumlah entri Observasi Lanjutan — tiap baris dengan KODE yang dikirim
// kirim-observation RI (peta sama: TandaVitalObservationMap, NyeriKesadaranObservationMap::kesadaranRi).

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Support\Options\PengkajianAwalRiOptions;
use App\Support\Terminologi\TandaVitalObservationMap;
use App\Support\Terminologi\NyeriKesadaranObservationMap;
use App\Support\Terminologi\TampilanObservasiSatuSehat;

new class extends Component {
    use EmrRITrait;

    public ?string $riHdrNo = null;

    /** [['label', 'isi', 'kode'], ...] siap tampil. */
    public array $baris = [];

    /** Tabel hanya-baca data yang dikirim otomatis kartu SATUSEHAT RI (TampilanObservasiSatuSehat). */
    public array $observasiBaris = [];
    public array $obatBaris = [];
    public array $oksigenUrinBaris = [];

    public function mount(?string $riHdrNo = null): void
    {
        $this->riHdrNo = $riHdrNo;
        $dataRI = !empty($riHdrNo) ? ($this->findDataRI($riHdrNo) ?? []) : [];
        $fisik = $dataRI['pengkajianAwalPasienRawatInap']['bagian4PemeriksaanFisik'] ?? [];
        $tandaVital = (array) ($fisik['tandaVital'] ?? []);
        $angkaAtauKosong = fn($nilai) => in_array(trim((string) $nilai), ['', '0'], true) ? '' : trim((string) $nilai);

        $tekananDarah = TandaVitalObservationMap::TEKANAN_DARAH;
        $adaTekananDarah = filled($tandaVital['sistolik'] ?? null) && filled($tandaVital['distolik'] ?? null);
        $baris = [
            ['label' => 'Tekanan Darah',
             'isi' => $adaTekananDarah ? $tandaVital['sistolik'] . ' / ' . $tandaVital['distolik'] . ' mmHg' : '',
             'kode' => 'LOINC ' . $tekananDarah['panel']['code'] . ' (' . $tekananDarah['sistolik']['code'] . ' / ' . $tekananDarah['distolik']['code'] . ') · Blood pressure'
                 . ($adaTekananDarah ? '' : ' — kosong, tidak dikirim')],
        ];
        foreach ([...TandaVitalObservationMap::VITAL_TUNGGAL, ...TandaVitalObservationMap::ANTROPOMETRI] as $kunci => $peta) {
            $nilai = $angkaAtauKosong($tandaVital[$kunci] ?? '');
            $baris[] = [
                'label' => $peta['label'],
                'isi' => $nilai !== '' ? $nilai . ' ' . $peta['satuanTampil'] : '',
                'kode' => 'LOINC ' . $peta['loinc'] . ' · ' . $peta['display'] . ($nilai !== '' ? '' : ' — kosong, tidak dikirim'),
            ];
        }

        $kunciKesadaran = (string) ($fisik['pemeriksaanSistemOrgan']['neurologi']['tingkatKesadaran']['pilihan'] ?? '');
        $labelKesadaran = PengkajianAwalRiOptions::TINGKAT_KESADARAN[$kunciKesadaran] ?? $kunciKesadaran;
        $nilaiKesadaran = NyeriKesadaranObservationMap::kesadaranRi($kunciKesadaran, $labelKesadaran)[0]['valueCodeableConcept'] ?? null;
        $baris[] = [
            'label' => 'Tingkat Kesadaran',
            'isi' => $labelKesadaran . (filled($fisik['pemeriksaanSistemOrgan']['neurologi']['gcs'] ?? null) ? ' (GCS ' . $fisik['pemeriksaanSistemOrgan']['neurologi']['gcs'] . ')' : ''),
            'kode' => $nilaiKesadaran === null ? 'LOINC 67775-7 · Level of responsiveness — kosong, tidak dikirim'
                : 'LOINC 67775-7 · Level of responsiveness' . (isset($nilaiKesadaran['code']) ? ' · nilai SNOMED ' . $nilaiKesadaran['code'] . ' ' . $nilaiKesadaran['display'] : ' · nilai dikirim sebagai teks (tanpa kode)'),
        ];

        $this->baris = $baris;

        // Status terkirim dari catatan yang SAMA dengan kartu kirim: kartu Observation (tandaVitalLanjutanIds;
        // record lama = observationIds tanpa peta → semua terkirim) & kartu Observasi Lanjutan (sekali kirim).
        $satuSehat = $dataRI['satusehat'] ?? [];
        $tandaVitalTerkirim = (!isset($satuSehat['tandaVitalLanjutanIds']) && !empty($satuSehat['observationIds']))
            ? true : (array) ($satuSehat['tandaVitalLanjutanIds'] ?? []);
        $observasi = (array) ($dataRI['observasi'] ?? []);
        $this->observasiBaris = TampilanObservasiSatuSehat::barisTandaVital((array) ($observasi['observasiLanjutan']['tandaVital'] ?? []), $tandaVitalTerkirim);
        $this->obatBaris = TampilanObservasiSatuSehat::barisPemberianObat((array) ($observasi['obatDanCairan']['pemberianObatDanCairan'] ?? []), !empty($satuSehat['observasiLanjutanIds']));
        $this->oksigenUrinBaris = TampilanObservasiSatuSehat::barisOksigenDanUrin(
            (array) ($observasi['pemakaianOksigen']['pemakaianOksigenData'] ?? []),
            (array) ($observasi['pengeluaranCairan']['pengeluaranCairan'] ?? []),
            !empty($satuSehat['observasiLanjutanIds']),
        );
    }
};
?>
<div class="space-y-3">

<x-border-form title="Pemeriksaan Fisik (Pengkajian Awal)">
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
    <x-satu-sehat.tabel-observasi judul="Oksigen & Pengeluaran Urin" :baris="$oksigenUrinBaris"
        kosong="Belum ada pemakaian oksigen / pengeluaran cairan" />
</div>
