{{-- pages/transaksi/ugd/emr-ugd/pemeriksaan/tabs/pelayanan-penunjang-tab.blade.php --}}
<div class="w-full mb-1">
    <div class="pt-0">

        {{-- Lab --}}
        <div class="mb-4">
            <div>
                <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.penunjang.laborat.rm-laborat-ugd-actions
                    :rjNo="$dataDaftarUGD['rjNo'] ?? ''" :disabled="$isFormLocked"
                    wire:key="laborat-ugd-actions-{{ $dataDaftarUGD['rjNo'] ?? 'new' }}" />
            </div>

            <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.penunjang.laborat.rm-daftar-laborat-ugd
                :rjNo="$dataDaftarUGD['rjNo'] ?? ''"
                wire:key="daftar-laborat-ugd-{{ $dataDaftarUGD['rjNo'] ?? 'new' }}" />
        </div>

        {{-- Lab Luar --}}
        <div class="mb-4">
            <div>
                <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.penunjang.laborat.rm-laborat-luar-ugd-actions
                    :rjNo="$dataDaftarUGD['rjNo'] ?? ''" :disabled="$isFormLocked"
                    wire:key="laborat-luar-ugd-actions-{{ $dataDaftarUGD['rjNo'] ?? 'new' }}" />
            </div>

            <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.penunjang.laborat.rm-daftar-laborat-luar-ugd
                :rjNo="$dataDaftarUGD['rjNo'] ?? ''"
                wire:key="daftar-laborat-luar-ugd-{{ $dataDaftarUGD['rjNo'] ?? 'new' }}" />
        </div>

        {{-- Radiologi --}}
        <div class="mb-4">
            <div>
                <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.penunjang.radiologi.rm-radiologi-ugd-actions
                    :rjNo="$dataDaftarUGD['rjNo'] ?? ''" :disabled="$isFormLocked"
                    wire:key="radiologi-ugd-actions-{{ $dataDaftarUGD['rjNo'] ?? 'new' }}" />
            </div>

            <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.penunjang.radiologi.rm-daftar-radiologi-ugd
                :rjNo="$dataDaftarUGD['rjNo'] ?? ''"
                wire:key="daftar-radiologi-ugd-{{ $dataDaftarUGD['rjNo'] ?? 'new' }}" />
        </div>

    </div>
</div>
