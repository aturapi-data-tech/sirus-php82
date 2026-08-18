@php
    $lock = strtoupper($lockStatus ?? '');
    $lockMap = [
        'RJ' => ['label' => 'Terkunci di Rawat Jalan', 'variant' => 'warning'],
        'UGD' => ['label' => 'Terkunci di UGD', 'variant' => 'danger'],
        'RI' => ['label' => 'Terkunci di Rawat Inap', 'variant' => 'warning'],
    ];
    $isLocked = array_key_exists($lock, $lockMap);
    $lockLabel = $isLocked ? $lockMap[$lock]['label'] : 'Bebas / Tidak Terkunci';
    $lockVariant = $isLocked ? $lockMap[$lock]['variant'] : 'success';
@endphp

<x-border-form :title="__('Status Kunci Pasien')" :align="__('start')" :bgcolor="__('bg-canvas')">
    <div class="space-y-5">

        {{-- Status saat ini --}}
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-sm font-medium text-muted dark:text-gray-400">Status saat ini:</span>
            <x-badge :variant="$lockVariant" class="text-sm">{{ $lockLabel }}</x-badge>
            @if ($isLocked)
                <span class="font-mono text-xs text-muted dark:text-gray-500">(lockstatus = "{{ $lock }}")</span>
            @endif
        </div>

        {{-- Penjelasan --}}
        <div class="rounded-lg bg-blue-50 border border-blue-200 dark:bg-blue-900/20 dark:border-blue-800 px-4 py-3">
            <p class="text-sm leading-relaxed text-blue-800 dark:text-blue-200">
                Kolom <span class="font-mono">lockstatus</span> menandai pasien sedang dikunci di satu jalur layanan
                (UGD / Rawat Jalan / Rawat Inap) agar tidak bisa didaftarkan ganda di jalur lain.
                Status akan otomatis dilepas saat pasien pulang. Gunakan tombol
                <span class="font-semibold">Reset</span> hanya bila pasien <span class="font-semibold">nyangkut</span>
                terkunci padahal sudah tidak ada kunjungan aktif.
            </p>
        </div>

        {{-- Tombol reset --}}
        <div class="flex items-center gap-3">
            <x-danger-button
                type="button"
                wire:click="resetLockStatus"
                wire:confirm="Yakin reset status kunci pasien ini menjadi bebas (null)?"
                wire:loading.attr="disabled"
                :disabled="!$isLocked">
                <span wire:loading.remove wire:target="resetLockStatus">Reset Status Kunci</span>
                <span wire:loading wire:target="resetLockStatus">
                    <x-loading />
                    Memproses...
                </span>
            </x-danger-button>

            @unless ($isLocked)
                <span class="text-xs text-muted dark:text-gray-500">Pasien sudah bebas — tidak perlu di-reset.</span>
            @endunless
        </div>

    </div>
</x-border-form>
