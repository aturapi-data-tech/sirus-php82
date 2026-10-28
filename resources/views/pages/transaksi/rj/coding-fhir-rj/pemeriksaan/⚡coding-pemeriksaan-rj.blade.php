<?php
// resources/views/pages/transaksi/rj/coding-fhir-rj/pemeriksaan/coding-pemeriksaan-rj.blade.php
// Coding SATUSEHAT — kelompok O (Pemeriksaan). Baca saja: tanda vital & nutrisi dari EMR, tiap
// baris dengan KODE yang dikirim — dari peta yang SAMA dengan sendernya:
//   tanda vital → TandaVitalObservationMap (kirim-observation)
//   kesadaran   → NyeriKesadaranObservationMap (kirim-nyeri-kesadaran; sumbernya screening.kesadaran)
// Yang tidak dikirim (keadaan umum, nilai kosong) ditandai, bukan disembunyikan.

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Support\Terminologi\TandaVitalObservationMap;
use App\Support\Terminologi\NyeriKesadaranObservationMap;

new class extends Component {
    use EmrRJTrait;

    public ?string $rjNo = null;

    /** [['label', 'isi', 'kode'], ...] siap tampil. */
    public array $baris = [];

    public function mount(?string $rjNo = null): void
    {
        $this->rjNo = $rjNo;
        $dataRJ = !empty($rjNo) ? ($this->findDataRJ($rjNo) ?? []) : [];
        $tandaVital = $dataRJ['pemeriksaan']['tandaVital'] ?? [];
        $nutrisi = $dataRJ['pemeriksaan']['nutrisi'] ?? [];
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

        // Kesadaran — sama dengan kirim-nyeri-kesadaran: tingkat kesadaran pemeriksaan (AVPU) dulu,
        // skrining awal sebagai cadangan (NyeriKesadaranObservationMap::kesadaranRj).
        $tingkatKesadaran = trim((string) ($tandaVital['tingkatKesadaran'] ?? ''));
        $kesadaranSkrining = trim((string) ($dataRJ['screening']['kesadaran'] ?? ''));
        $observasiKesadaran = NyeriKesadaranObservationMap::kesadaranRj($tingkatKesadaran, $kesadaranSkrining)[0] ?? null;
        $nilaiKesadaran = $observasiKesadaran['valueCodeableConcept'] ?? [];
        $baris[] = [
            'label' => 'Tingkat Kesadaran',
            'isi' => $tingkatKesadaran !== '' ? $tingkatKesadaran : ($kesadaranSkrining !== '' ? $kesadaranSkrining . ' (skrining)' : ''),
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
    }
};
?>

<x-border-form title="Tanda Vital & Nutrisi">
    <div class="space-y-2.5">
        @foreach ($baris as $item)
            <x-satu-sehat.baris-baca :label="$item['label']" :isi="$item['isi']" :kode="$item['kode']" />
        @endforeach
    </div>
</x-border-form>
