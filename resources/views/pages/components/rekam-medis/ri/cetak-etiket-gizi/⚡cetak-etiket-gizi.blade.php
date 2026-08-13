<?php
// Etiket Diet Gizi — label nampan makan pasien RI (worklist /ri/gizi).
//
// Pola sama dgn components/rekam-medis/etiket/cetak-etiket: event → dompdf
// 6cm × 4cm → streamDownload. Isi: identitas ringkas + PROGRAM DIET terakhir
// (penilaian.gizi[]) + alergi dari Pengkajian Dokter (penting utk dapur).

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Support\OracleLob;
use App\Support\GiziOptions;
use App\Support\AlergiSnomed;

new class extends Component {
    public ?string $riHdrNo = null;

    #[On('cetak-etiket-gizi.open')]
    public function open(string $riHdrNo): mixed
    {
        $this->riHdrNo = $riHdrNo;

        $row = DB::table('rsview_rihdrs')
            ->selectRaw("rihdr_no, reg_no, reg_name, sex, bangsal_name, room_name, to_char(birth_date,'dd/mm/yyyy') as birth_date, datadaftarri_json")
            ->where('rihdr_no', $riHdrNo)
            ->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return null;
        }

        $jsonRaw = OracleLob::read($row->datadaftarri_json ?? null, 'rstxn_rihdrs', 'rihdr_no', $row->rihdr_no, 'datadaftarri_json');
        $json = json_decode($jsonRaw ?: '{}', true) ?? [];

        $dietTerakhir = GiziOptions::entriTerakhirDengan($json['penilaian']['gizi'] ?? [], 'programDiet');
        if ($dietTerakhir === []) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada program diet — isi Penilaian Gizi dulu.');
            return null;
        }

        $anamnesa = (array) data_get($json, 'pengkajianDokter.anamnesa', []);
        $alergi = AlergiSnomed::untukCetakRi($anamnesa);
        $alergiAda = $alergi !== '' && $alergi !== AlergiSnomed::TIDAK_ADA['alergi'];

        // Umur (tahun) dari birth_date — identifikasi pasien seragam dgn etiket lain
        $umurTahun = null;
        if (!empty($row->birth_date)) {
            try {
                $umurTahun = Carbon::createFromFormat('d/m/Y', $row->birth_date)->diff(Carbon::now(config('app.timezone')))->y;
            } catch (\Throwable) {
                $umurTahun = null;
            }
        }

        $pdf = Pdf::loadView('pages.components.rekam-medis.ri.cetak-etiket-gizi.cetak-etiket-gizi-print', [
            'data' => [
                'regNo' => $row->reg_no,
                'regName' => $row->reg_name,
                'sex' => $row->sex,
                'birthDate' => $row->birth_date,
                'umurTahun' => $umurTahun,
                'bangsal' => $row->bangsal_name,
                'room' => $row->room_name,
                'programDiet' => (string) $dietTerakhir['nilai'],
                'programDietKet' => (string) data_get($dietTerakhir, 'entri.gizi.programDietKet', ''),
                'tglCetak' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i'),
                'alergi' => $alergiAda ? $alergi : '',
            ],
        ])->setPaper([0, 0, 170.08, 113.39]); // 6cm x 4cm dalam points

        return response()->streamDownload(fn() => print $pdf->output(), 'etiket-diet-' . ($row->reg_no ?? $riHdrNo) . '.pdf');
    }
};
?>

<div></div>
