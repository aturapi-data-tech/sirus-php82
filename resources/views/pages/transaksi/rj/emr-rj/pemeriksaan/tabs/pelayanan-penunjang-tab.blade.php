<div class="w-full mb-1">
    <div class="pt-0">
        {{-- Lab --}}
        <div class="mb-4">
            <div>
                <livewire:pages::transaksi.rj.emr-rj.pemeriksaan.penunjang.laborat.rm-laborat-rj-actions
                    :rjNo="$dataDaftarPoliRJ['rjNo'] ?? ''" :disabled="$isFormLocked"
                    wire:key="laborat-actions-{{ $dataDaftarPoliRJ['rjNo'] ?? 'new' }}" />
            </div>

            <livewire:pages::transaksi.rj.emr-rj.pemeriksaan.penunjang.laborat.rm-daftar-laborat-rj
                :rjNo="$dataDaftarPoliRJ['rjNo'] ?? ''"
                wire:key="daftar-laborat-rj-{{ $dataDaftarPoliRJ['rjNo'] ?? 'new' }}" />
        </div>

        {{-- Lab Luar --}}
        <div class="mb-4">
            <div>
                <livewire:pages::transaksi.rj.emr-rj.pemeriksaan.penunjang.laborat.rm-laborat-luar-rj-actions
                    :rjNo="$dataDaftarPoliRJ['rjNo'] ?? ''" :disabled="$isFormLocked"
                    wire:key="laborat-luar-actions-{{ $dataDaftarPoliRJ['rjNo'] ?? 'new' }}" />
            </div>

            <livewire:pages::transaksi.rj.emr-rj.pemeriksaan.penunjang.laborat.rm-daftar-laborat-luar-rj
                :rjNo="$dataDaftarPoliRJ['rjNo'] ?? ''" wire:key="daftar-lab-luar-rj-{{ $dataDaftarPoliRJ['rjNo'] ?? 'new' }}" />
        </div>

        {{-- Rad --}}
        <div class="mb-4">
            <div>
                <livewire:pages::transaksi.rj.emr-rj.pemeriksaan.penunjang.radiologi.rm-radiologi-rj-actions
                    :rjNo="$dataDaftarPoliRJ['rjNo'] ?? ''" :disabled="$isFormLocked"
                    wire:key="radiologi-actions-{{ $dataDaftarPoliRJ['rjNo'] ?? 'new' }}" />
            </div>

            <livewire:pages::transaksi.rj.emr-rj.pemeriksaan.penunjang.radiologi.rm-daftar-radiologi-rj
                :rjNo="$dataDaftarPoliRJ['rjNo'] ?? ''"
                wire:key="daftar-radiologi-rj-{{ $dataDaftarPoliRJ['rjNo'] ?? 'new' }}" />
        </div>


    </div>
</div>
