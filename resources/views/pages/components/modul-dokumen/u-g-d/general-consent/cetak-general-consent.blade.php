<?php
// resources/views/pages/components/modul-dokumen/u-g-d/general-consent/cetak-general-consent-ugd.blade.php

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

    #[On('cetak-general-consent-ugd.open')]
    public function open(int $rjNo): mixed
    {
        $this->rjNo = $rjNo;

        // ── 1. Data UGD ──
        $dataUGD = $this->findDataUGD($rjNo);
        if (empty($dataUGD)) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return null;
        }

        // Gunakan kunci yang sesuai: generalConsentPasienUGD
        $consent = $dataUGD['generalConsentPasienUGD'] ?? null;
        if (empty($consent)) {
            $this->dispatch('toast', type: 'error', message: 'Data General Consent belum tersedia.');
            return null;
        }

        // ── 2. Data Pasien ──
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

        // ── 3. Identitas RS ──
        $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();

        // ── 4. TTD Petugas — cek image dari storage ──
        $ttdPetugasPath = null;
        $petugasCode = $consent['petugasPemeriksaCode'] ?? null;
        if ($petugasCode) {
            $ttdPath = DB::table('users')->where('myuser_code', $petugasCode)->value('myuser_ttd_image');
            if (!empty($ttdPath) && file_exists(public_path('storage/' . $ttdPath))) {
                $ttdPetugasPath = public_path('storage/' . $ttdPath);
            }
        }

        $data = array_merge($pasien, [
            'dataUGD' => $dataUGD,
            'consent' => $consent,
            'identitasRs' => $identitasRs,
            'ttdPetugasPath' => $ttdPetugasPath,
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);

        set_time_limit(300);

        $pdf = Pdf::loadView('pages.components.modul-dokumen.u-g-d.general-consent.cetak-general-consent-print', ['data' => $data])->setPaper('A4');

        return response()->streamDownload(fn() => print $pdf->output(), 'general-consent-ugd-' . ($pasien['regNo'] ?? $rjNo) . '.pdf');
    }
};
?>
<div></div>
