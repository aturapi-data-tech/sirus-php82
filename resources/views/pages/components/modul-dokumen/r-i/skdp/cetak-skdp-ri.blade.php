<?php
// resources/views/pages/components/modul-dokumen/r-i/skdp/cetak-skdp-ri.blade.php

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

    #[On('cetak-skdp-ri.open')]
    public function open(string $riHdrNo): mixed
    {
        $this->riHdrNo = $riHdrNo;

        $dataRI = $this->findDataRI($riHdrNo);
        if (empty($dataRI)) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return null;
        }

        $kontrol = $dataRI['kontrol'] ?? [];
        if (empty($kontrol['tglKontrol'])) {
            $this->dispatch('toast', type: 'error', message: 'Data surat kontrol belum tersedia.');
            return null;
        }

        $pasienData = $this->findDataMasterPasien($dataRI['regNo'] ?? '');
        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        $pasien = $pasienData['pasien'];

        if (!empty($pasien['tglLahir'])) {
            $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                ->diff(Carbon::now(config('app.timezone')))
                ->format('%y Thn, %m Bln %d Hr');
        }

        $dokter = DB::table('rsmst_doctors')
            ->where('dr_id', $dataRI['drId'] ?? '')
            ->select('dr_name')
            ->first();

        $data = [
            'pasien' => $pasien,
            'kontrol' => $kontrol,
            'drDesc' => $dokter->dr_name ?? ($dataRI['drDesc'] ?? '-'),
            'drStr' => $dokter->dr_str ?? null,
            'entryDate' => $dataRI['entryDate'] ?? '-',
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ];

        $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.skdp.cetak-skdp-ri-print', [
            'data' => $data,
        ])->setPaper([0, 0, 297.64, 481.89]); // 105mm x 170mm

        return response()->streamDownload(fn() => print $pdf->output(), 'SKDP-RI-' . ($kontrol['noKontrolRS'] ?? $riHdrNo) . '.pdf');
    }
};
?>
<div></div>
