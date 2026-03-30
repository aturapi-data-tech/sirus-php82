<?php
// resources/views/pages/components/modul-dokumen/u-g-d/inform-consent/cetak-inform-consent.blade.php

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
    public ?string $signatureDate = null;

    #[On('cetak-inform-consent.open')]
    public function open(int $rjNo, ?string $signatureDate = null): mixed
    {
        $this->rjNo = $rjNo;
        $this->signatureDate = $signatureDate;

        $dataUGD = $this->findDataUGD($rjNo);
        if (empty($dataUGD)) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return null;
        }

        // Gunakan struktur yang sesuai: informConsentPasienUGD (bukan informConsentList)
        $consentList = $dataUGD['informConsentPasienUGD'] ?? [];
        if (empty($consentList)) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada Inform Consent yang tersimpan.');
            return null;
        }

        // Jika signatureDate tidak disediakan, ambil yang terbaru
        if (empty($this->signatureDate)) {
            $consent = collect($consentList)->sortByDesc('signatureDate')->first();
        } else {
            $consent = collect($consentList)->firstWhere('signatureDate', $this->signatureDate);
        }

        if (empty($consent)) {
            $this->dispatch('toast', type: 'error', message: 'Data Inform Consent yang dipilih tidak ditemukan.');
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

        // TTD dokter dari storage
        $ttdDokterPath = null;
        if (!empty($consent['dokterCode'])) {
            $ttdPath = DB::table('users')->where('myuser_code', $consent['dokterCode'])->value('myuser_ttd_image');
            if (!empty($ttdPath) && file_exists(public_path('storage/' . $ttdPath))) {
                $ttdDokterPath = public_path('storage/' . $ttdPath);
            }
        }

        $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_address', 'int_city')->first();

        $data = array_merge($pasien, [
            'dataUGD' => $dataUGD,
            'consent' => $consent, // ← sekarang berisi struktur lengkap
            'identitasRs' => $identitasRs,
            'ttdDokterPath' => $ttdDokterPath,
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);

        set_time_limit(300);

        $pdf = Pdf::loadView('pages.components.modul-dokumen.u-g-d.inform-consent.cetak-inform-consent-print', ['data' => $data])->setPaper('A4');

        return response()->streamDownload(fn() => print $pdf->output(), 'inform-consent-ugd-' . ($pasien['regNo'] ?? $rjNo) . '.pdf');
    }
};
?>
<div></div>
