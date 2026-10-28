<x-border-form :title="__('Pengkajian')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
    <div>

        {{-- Perawat Penerima --}}
        <div class="py-2 grid grid-cols-3 gap-2 items-start border-b border-hairline dark:border-gray-700">
            <span class="text-sm font-medium text-muted dark:text-gray-400">Perawat Penerima</span>
            <span class="col-span-2 text-base font-medium text-ink dark:text-gray-200">
                {{ $anamnesa['pengkajianPerawatan']['perawatPenerima'] ?? '-' }}
                <x-input-error :messages="$errors->get('anamnesa.pengkajianPerawatan.perawatPenerima')" class="mt-1" />
            </span>
        </div>

        {{-- Waktu Datang --}}
        <div class="py-2 grid grid-cols-3 gap-2 items-start border-b border-hairline dark:border-gray-700">
            <span class="text-sm font-medium text-muted dark:text-gray-400">Waktu Datang</span>
            <span class="col-span-2 text-base font-medium text-ink dark:text-gray-200">
                {{ $anamnesa['pengkajianPerawatan']['jamDatang'] ?? '-' }}
                <x-input-error :messages="$errors->get('anamnesa.pengkajianPerawatan.jamDatang')" class="mt-1" />
            </span>
        </div>

        {{-- Keluhan Utama --}}
        <div class="py-2 grid grid-cols-3 gap-2 items-start border-b border-hairline dark:border-gray-700">
            <span class="text-sm font-medium text-muted dark:text-gray-400">Keluhan Utama</span>
            <span class="col-span-2 text-base text-ink dark:text-gray-200 whitespace-pre-line">
                {{ $anamnesa['keluhanUtama']['keluhanUtama'] ?? '-' }}
                <x-input-error :messages="$errors->get('anamnesa.keluhanUtama.keluhanUtama')" class="mt-1" />
            </span>
        </div>

        {{-- SNOMED CT (readonly) --}}
        @if (!empty($anamnesa['keluhanUtama']['snomedCode']))
            <div class="py-2 grid grid-cols-3 gap-2 items-start border-b border-hairline dark:border-gray-700">
                <span class="text-sm font-medium text-muted dark:text-gray-400">SNOMED CT</span>
                <span class="col-span-2 text-base text-ink dark:text-gray-200">
                    @php
                        $sId = $anamnesa['keluhanUtama']['snomedDisplayId'] ?? '';
                        $sEn = $anamnesa['keluhanUtama']['snomedDisplayEn'] ?? '';
                        $sCode = $anamnesa['keluhanUtama']['snomedCode'];
                    @endphp
                    @if (!empty($sId))
                        {{ $sId }} &mdash; {{ $sEn }} ({{ $sCode }})
                    @else
                        {{ $sEn }} ({{ $sCode }})
                    @endif
                </span>
            </div>
        @endif

        {{-- Keluhan Penyerta --}}
        @if (!empty($anamnesa['keluhanPenyerta']['keluhanPenyerta']))
            <div class="py-2 grid grid-cols-3 gap-2 items-start border-b border-hairline dark:border-gray-700">
                <span class="text-sm font-medium text-muted dark:text-gray-400">Keluhan Penyerta</span>
                <span class="col-span-2 text-base text-ink dark:text-gray-200 whitespace-pre-line">
                    {{ $anamnesa['keluhanPenyerta']['keluhanPenyerta'] }}
                </span>
            </div>
        @endif

        {{-- SNOMED CT Keluhan Penyerta (readonly, boleh lebih dari satu) --}}
        @if (!empty($anamnesa['keluhanPenyerta']['snomedList']))
            <div class="py-2 grid grid-cols-3 gap-2 items-start border-b border-hairline dark:border-gray-700">
                <span class="text-sm font-medium text-muted dark:text-gray-400">SNOMED CT Penyerta</span>
                <span class="col-span-2 text-base text-ink dark:text-gray-200">
                    @foreach ($anamnesa['keluhanPenyerta']['snomedList'] as $baris)
                        <span class="block">{{ ($baris['snomedDisplayId'] ?? '') ?: ($baris['snomedDisplayEn'] ?? '') }}
                            &mdash; {{ $baris['snomedDisplayEn'] ?? '' }} ({{ $baris['snomedCode'] ?? '' }})</span>
                    @endforeach
                </span>
            </div>
        @endif

    </div>
</x-border-form>
