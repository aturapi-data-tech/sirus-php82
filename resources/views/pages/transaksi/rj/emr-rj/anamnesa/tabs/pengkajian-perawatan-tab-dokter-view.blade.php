{{-- Field Waktu Datang --}}
<div>
    <div class="mb-2">
        <x-input-label :value="__('Waktu Datang')" />
        <p class="mt-1 ml-2 text-sm text-gray-800 dark:text-gray-200">
            {{ $dataDaftarPoliRJ['anamnesa']['pengkajianPerawatan']['jamDatang'] ?? '-' }}
        </p>
    </div>

    {{-- Field Perawat Penerima --}}
    <div class="mb-2">
        <x-input-label :value="__('Perawat Penerima')" class="pt-2" />
        <p class="mt-1 ml-2 text-sm text-gray-800 dark:text-gray-200">
            {{ $dataDaftarPoliRJ['anamnesa']['pengkajianPerawatan']['perawatPenerima'] ?? '-' }}
        </p>
    </div>

    {{-- Field Keluhan Utama --}}
    <div class="mb-2">
        <x-input-label :value="__('Keluhan Utama')" class="pt-2" />
        <p class="mt-1 ml-2 text-sm text-gray-800 dark:text-gray-200 whitespace-pre-line">
            {{ $dataDaftarPoliRJ['anamnesa']['keluhanUtama']['keluhanUtama'] ?? '-' }}
        </p>
    </div>
</div>
