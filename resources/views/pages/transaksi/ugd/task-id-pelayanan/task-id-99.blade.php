<?php
// resources/views/pages/transaksi/ugd/..../taskid99-ugd.blade.php

use Livewire\Component;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;

new class extends Component {
    use EmrUGDTrait;

    public ?int $rjNo = null;
    public bool $isLoading = false;

    public function prosesTaskId99(): void
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

            if (!empty($data['taskIdPelayanan']['taskId4'] ?? null)) {
                $this->dispatch('toast', type: 'error', message: 'Tidak dapat membatalkan antrian karena TaskId4 (Selesai Pelayanan) sudah tercatat.');
                return;
            }

            if (!empty($data['taskIdPelayanan']['taskId5'] ?? null)) {
                $this->dispatch('toast', type: 'error', message: 'Tidak dapat membatalkan antrian karena TaskId5 (Panggil Antrian) sudah tercatat.');
                return;
            }

            if (!empty($data['taskIdPelayanan']['taskId99'])) {
                $this->dispatch('toast', type: 'warning', message: "TaskId99 sudah tercatat: {$data['taskIdPelayanan']['taskId99']}.");
            }

            if (!isset($data['taskIdPelayanan'])) {
                $data['taskIdPelayanan'] = [];
                $needUpdate = true;
            }

            if (empty($data['taskIdPelayanan']['taskId99'])) {
                $data['taskIdPelayanan']['taskId99'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
                $needUpdate = true;
            }

            if ($needUpdate) {
                $existingData = $this->findDataUGD($this->rjNo);
                if (!empty($existingData)) {
                    $existingData['taskIdPelayanan'] = $data['taskIdPelayanan'];
                    $this->updateJsonUGD($this->rjNo, $existingData);
                }
            }

            $this->dispatch('toast', type: 'success', message: 'Antrian berhasil dibatalkan.');
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
    <x-danger-button wire:click="prosesTaskId99" wire:loading.attr="disabled" wire:target="prosesTaskId99"
        class="!px-2 !py-1 text-xs" title="Klik untuk membatalkan antrian (hanya bisa sebelum TaskId4/5)">
        <span wire:loading.remove wire:target="prosesTaskId99">Batal</span>
        <span wire:loading wire:target="prosesTaskId99"><x-loading /></span>
    </x-danger-button>
</div>
