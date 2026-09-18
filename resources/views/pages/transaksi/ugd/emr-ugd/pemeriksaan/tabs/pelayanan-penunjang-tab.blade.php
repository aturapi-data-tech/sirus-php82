{{-- pages/transaksi/ugd/emr-ugd/pemeriksaan/tabs/pelayanan-penunjang-tab.blade.php --}}
<div class="w-full mb-1 space-y-4">

    <div class="p-4 bg-canvas border border-hairline rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700">
        <h3 class="text-base font-semibold text-body dark:text-gray-300 mb-3">Laboratorium</h3>
        <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.penunjang.laborat.rm-laborat-ugd-actions
            :rjNo="$rjNo ?? ''" :disabled="$isFormLocked"
            wire:key="laborat-ugd-actions-{{ $rjNo ?? 'new' }}" />

        <div class="mt-3">
            <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.penunjang.laborat.rm-daftar-laborat-ugd
                :rjNo="$rjNo ?? ''"
                wire:key="daftar-laborat-ugd-{{ $rjNo ?? 'new' }}" />
        </div>
    </div>

    <div class="p-4 bg-canvas border border-hairline rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700">
        <h3 class="text-base font-semibold text-body dark:text-gray-300 mb-3">Laboratorium Luar</h3>
        <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.penunjang.laborat.rm-laborat-luar-ugd-actions
            :rjNo="$rjNo ?? ''" :disabled="$isFormLocked"
            wire:key="laborat-luar-ugd-actions-{{ $rjNo ?? 'new' }}" />

        <div class="mt-3">
            <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.penunjang.laborat.rm-daftar-laborat-luar-ugd
                :rjNo="$rjNo ?? ''"
                wire:key="daftar-laborat-luar-ugd-{{ $rjNo ?? 'new' }}" />
        </div>
    </div>

    <div class="p-4 bg-canvas border border-hairline rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700">
        <h3 class="text-base font-semibold text-body dark:text-gray-300 mb-3">Radiologi</h3>
        <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.penunjang.radiologi.rm-radiologi-ugd-actions
            :rjNo="$rjNo ?? ''" :disabled="$isFormLocked"
            wire:key="radiologi-ugd-actions-{{ $rjNo ?? 'new' }}" />

        <div class="mt-3">
            <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.penunjang.radiologi.rm-daftar-radiologi-ugd
                :rjNo="$rjNo ?? ''"
                wire:key="daftar-radiologi-ugd-{{ $rjNo ?? 'new' }}" />
        </div>
    </div>

    <div class="p-4 bg-canvas border border-hairline rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700">
        <h3 class="text-base font-semibold text-body dark:text-gray-300 mb-3">Kamar Operasi</h3>
        <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.penunjang.kamar-operasi.rm-kamar-operasi-ugd-actions
            :rjNo="$rjNo ?? ''" :disabled="$isFormLocked"
            wire:key="kamar-operasi-ugd-actions-{{ $rjNo ?? 'new' }}" />

        <div class="mt-3">
            <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.penunjang.kamar-operasi.rm-daftar-kamar-operasi-ugd
                :rjNo="$rjNo ?? ''"
                wire:key="daftar-kamar-operasi-ugd-{{ $rjNo ?? 'new' }}" />
        </div>
    </div>

</div>
