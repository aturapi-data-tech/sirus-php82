<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait;

    public ?int $rjNo = null;

    #[On('cetak-rekam-medis-ugd-v1.open')]
    public function open(int $rjNo): mixed
    {
        $this->rjNo = $rjNo;

        $dataUGD = $this->findDataUGD($rjNo);
        if (empty($dataUGD)) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return null;
        }

        $pasienData = $this->findDataMasterPasien($dataUGD['regNo'] ?? '');
        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        $pasien = $pasienData['pasien'];

        if (!empty($pasien['tglLahir'])) {
            try {
                $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                    ->diff(Carbon::now(env('APP_TIMEZONE')))
                    ->format('%y Thn, %m Bln %d Hr');
            } catch (\Exception $e) {
                $pasien['thn'] = '-';
            }
        }

        $dokter = DB::table('rsmst_doctors')
            ->where('dr_id', $dataUGD['drId'] ?? '')
            ->select('dr_name')
            ->first();

        $pdf = Pdf::loadView('pages.components.rekam-medis.ugd.cetak-rekam-medis.cetak-rekam-medis-ugd-v1-print', [
            'dataDaftarTxn' => $dataUGD,
            'dataPasien' => ['pasien' => $pasien],
            'namaDokter' => $dokter->dr_name ?? null,
        ])->setPaper('A4');

        $filename = 'resume-ugd-v1-' . ($dataUGD['regNo'] ?? $rjNo) . '-' . ($dataUGD['rjNo'] ?? '') . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename);
    }
};
?>

<div></div>
