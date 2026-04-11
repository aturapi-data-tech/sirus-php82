<?php
// resources/views/pages/components/modul-dokumen/r-i/riwayat-pengobatan/cetak-riwayat-pengobatan-ri.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait;

    public ?string $riHdrNo = null;

    #[On('cetak-riwayat-pengobatan-ri.open')]
    public function open(string $riHdrNo): mixed
    {
        $this->riHdrNo = $riHdrNo;

        $dataDaftarRi = $this->findDataRI($riHdrNo);
        if (empty($dataDaftarRi)) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return null;
        }

        $dataPasien = $this->findDataMasterPasien($dataDaftarRi['regNo'] ?? '');
        if (empty($dataPasien)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        $data = [
            'dataDaftarRi' => $dataDaftarRi,
            'dataPasien' => $dataPasien,
        ];

        $pdf = Pdf::loadView(
            'pages.components.modul-dokumen.r-i.riwayat-pengobatan.cetak-riwayat-pengobatan-ri-print',
            $data
        )->setPaper('A4');

        $regNo = $dataDaftarRi['regNo'] ?? $riHdrNo;
        return response()->streamDownload(
            fn () => print $pdf->output(),
            "Riwayat-Pengobatan-RI-{$regNo}.pdf"
        );
    }
};
?>
<div></div>
