<?php
// resources/views/pages/components/modul-dokumen/u-g-d/form-penjaminan/cetak-form-penjaminan.blade.php

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
    public ?string $signaturePembuatDate = null;

    #[On('cetak-form-penjaminan.open')]
    public function open(int $rjNo, string $signaturePembuatDate): mixed
    {
        $this->rjNo = $rjNo;
        $this->signaturePembuatDate = $signaturePembuatDate;

        $dataUGD = $this->findDataUGD($rjNo);
        if (empty($dataUGD)) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return null;
        }

        $listForm = $dataUGD['formPenjaminanOrientasiKamar'] ?? [];
        if (empty($listForm)) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada Form Pernyataan yang tersimpan.');
            return null;
        }

        $form = collect($listForm)->firstWhere('signaturePembuatDate', $signaturePembuatDate);
        if (empty($form)) {
            $this->dispatch('toast', type: 'error', message: 'Data Form Pernyataan yang dipilih tidak ditemukan.');
            return null;
        }

        $pasienData = $this->findDataMasterPasien($dataUGD['regNo'] ?? '');
        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        $pasien = $pasienData['pasien'];

        // Hitung umur
        if (!empty($pasien['tglLahir'])) {
            try {
                $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                    ->diff(Carbon::now(config('app.timezone')))
                    ->format('%y Thn, %m Bln %d Hr');
            } catch (\Throwable) {
                $pasien['thn'] = '-';
            }
        }

        // TTD Petugas RS
        $ttdPetugasPath = null;
        if (!empty($form['kodePetugas'])) {
            $ttdPath = DB::table('users')->where('myuser_code', $form['kodePetugas'])->value('myuser_ttd_image');
            if (!empty($ttdPath) && file_exists(public_path('storage/' . $ttdPath))) {
                $ttdPetugasPath = public_path('storage/' . $ttdPath);
            }
        }

        $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_address', 'int_city')->first();

        $data = array_merge($pasien, [
            'dataUGD' => $dataUGD,
            'form' => $form,
            'identitasRs' => $identitasRs,
            'ttdPetugasPath' => $ttdPetugasPath,
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);

        set_time_limit(300);

        $pdf = Pdf::loadView('pages.components.modul-dokumen.u-g-d.form-penjaminan.cetak-form-penjaminan-print', ['data' => $data])->setPaper('A4');

        return response()->streamDownload(fn() => print $pdf->output(), 'form-penjaminan-biaya-' . ($pasien['regNo'] ?? $rjNo) . '.pdf');
    }
};
?>
<div></div>
