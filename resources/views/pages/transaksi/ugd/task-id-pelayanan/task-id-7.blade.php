<?php
// resources/views/pages/transaksi/ugd/..../taskid7-ugd.blade.php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;

new class extends Component {
    use EmrUGDTrait;

    public ?int $rjNo = null;
    public bool $isLoading = false;

    public function prosesTaskId7(): void
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

            if (empty($data['taskIdPelayanan']['taskId6'] ?? null)) {
                $this->dispatch('toast', type: 'error', message: 'TaskId6 (Masuk Apotek) harus dilakukan terlebih dahulu.');
                return;
            }

            if (!empty($data['taskIdPelayanan']['taskId7'])) {
                $this->dispatch('toast', type: 'warning', message: "TaskId7 sudah tercatat: {$data['taskIdPelayanan']['taskId7']}.");
            }

            $waktuSekarang = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

            DB::table('rstxn_ugdhdrs')
                ->where('rj_no', $this->rjNo)
                ->update([
                    'waktu_selesai_pelayanan' => DB::raw("to_date('{$waktuSekarang}','dd/mm/yyyy hh24:mi:ss')"),
                ]);

            if (!isset($data['taskIdPelayanan'])) {
                $data['taskIdPelayanan'] = [];
                $needUpdate = true;
            }

            if (empty($data['taskIdPelayanan']['taskId7'])) {
                $data['taskIdPelayanan']['taskId7'] = $waktuSekarang;
                $needUpdate = true;
            }

            if ($needUpdate) {
                $existingData = $this->findDataUGD($this->rjNo);
                if (!empty($existingData)) {
                    $existingData['taskIdPelayanan'] = $data['taskIdPelayanan'];
                    $this->updateJsonUGD($this->rjNo, $existingData);
                }
            }

            $this->dispatch('toast', type: 'success', message: "Berhasil keluar apotek pada {$waktuSekarang}.");
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
    <x-primary-button wire:click="prosesTaskId7" wire:loading.attr="disabled" wire:target="prosesTaskId7"
        class="!px-2 !py-1 text-xs" title="Klik untuk mencatat TaskId7 (Keluar Apotek)">
        <span wire:loading.remove wire:target="prosesTaskId7">TaskId7</span>
        <span wire:loading wire:target="prosesTaskId7"><x-loading /></span>
    </x-primary-button>
</div>
