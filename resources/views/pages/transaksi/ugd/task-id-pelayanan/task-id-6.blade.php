<?php
// resources/views/pages/transaksi/ugd/..../taskid6-ugd.blade.php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;

new class extends Component {
    use EmrUGDTrait;

    public ?int $rjNo = null;
    public bool $isLoading = false;

    public function prosesTaskId6(): void
    {
        if (empty($this->rjNo)) {
            $this->dispatch('toast', type: 'warning', message: 'Nomor UGD tidak boleh kosong.');
            return;
        }

        $this->isLoading = true;
        $needUpdate = false;

        try {
            $data = $this->findDataUGD($this->rjNo);

            if (empty($data)) {
                $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
                return;
            }

            if (empty($data['taskIdPelayanan']['taskId5'] ?? null)) {
                $this->dispatch('toast', type: 'error', message: 'TaskId5 (Panggil Antrian) harus dilakukan terlebih dahulu.');
                return;
            }

            $waktuSekarang = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

            DB::table('rstxn_ugdhdrs')
                ->where('rj_no', $this->rjNo)
                ->update([
                    'waktu_masuk_apt' => DB::raw("to_date('{$waktuSekarang}','dd/mm/yyyy hh24:mi:ss')"),
                ]);

            if (!isset($data['taskIdPelayanan'])) {
                $data['taskIdPelayanan'] = [];
                $needUpdate = true;
            }

            if (empty($data['taskIdPelayanan']['taskId6'])) {
                $data['taskIdPelayanan']['taskId6'] = $waktuSekarang;
                $needUpdate = true;
            }

            // Hitung noAntrianApotek jika belum ada
            if (empty($data['noAntrianApotek'])) {
                $eresepRacikanCount = collect($data['eresepRacikan'] ?? [])->count();
                $jenisResep = $eresepRacikanCount > 0 ? 'racikan' : 'non racikan';

                $refDate = Carbon::now(config('app.timezone'))->format('d/m/Y');
                $query = DB::table('rstxn_ugdhdrs')->select('datadaftarugd_json')->where('rj_status', '!=', 'F')->where('klaim_id', '!=', 'KR')->where(DB::raw("to_char(rj_date,'dd/mm/yyyy')"), '=', $refDate)->get();

                $nomerAntrian = $query
                    ->filter(function ($item) {
                        $dataJson = json_decode($item->datadaftarugd_json, true) ?: [];
                        return isset($dataJson['noAntrianApotek']);
                    })
                    ->count();

                $noAntrian = ($data['klaimId'] ?? '') !== 'KR' ? $nomerAntrian + 1 : 9999;

                $data['noAntrianApotek'] = [
                    'noAntrian' => $noAntrian,
                    'jenisResep' => $jenisResep,
                ];
                $needUpdate = true;
            }

            if ($needUpdate) {
                $existingData = $this->findDataUGD($this->rjNo);
                if (!empty($existingData)) {
                    $existingData['taskIdPelayanan'] = $data['taskIdPelayanan'];
                    $existingData['noAntrianApotek'] = $data['noAntrianApotek'];
                    $this->updateJsonUGD($this->rjNo, $existingData);
                }
            }

            $this->dispatch('toast', type: 'success', message: "Berhasil masuk apotek pada {$waktuSekarang}.");
            $this->dispatch('refresh-after-ugd.saved');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage());
        } finally {
            $this->isLoading = false;
        }
    }
};
?>

<div class="inline-block">
    <x-primary-button wire:click="prosesTaskId6" wire:loading.attr="disabled" wire:target="prosesTaskId6"
        class="!px-2 !py-1 text-xs" title="Klik untuk mencatat TaskId6 (Masuk Apotek)">
        <span wire:loading.remove wire:target="prosesTaskId6">TaskId6</span>
        <span wire:loading wire:target="prosesTaskId6"><x-loading /></span>
    </x-primary-button>
</div>
