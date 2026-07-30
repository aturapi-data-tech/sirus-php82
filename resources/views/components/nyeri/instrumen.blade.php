{{-- resources/views/components/nyeri/instrumen.blade.php

    Instrumen pengisian skala nyeri. Bentuknya mengikuti tipe skala:
      'angka' → skor diketik (NRS)
      'pilih' → satu nilai dipilih dari deret (VAS, Wong-Baker)
      'item'  → skor = jumlah aspek terpilih (FLACC, NIPS, BPS, CPOT, PAINAD)

    Komponen induk (RJ/UGD/RI) WAJIB menyediakan method dengan nama sama:
      updateSkorSkala(int $skor) · updateSkorItem(string $kategori, int $skor)
    dan properti formEntryNyeri.nyeri.nyeriMetode.nyeriMetodeScore utk tipe 'angka'.

    Atribut tambahan (mis. x-ref / x-on:keydown) diteruskan ke input tipe 'angka'.

    Pemakaian:
      <x-nyeri.instrumen :skala="$this->skalaTerpilih" :kode="..." :dataNyeri="..." />
--}}

@props(['skala', 'kode' => '', 'dataNyeri' => []])

<x-border-form :title="$kode . ' — ' . $skala['nama']" align="start" bgcolor="bg-canvas">

    {{-- Tipe 'angka': skor diketik langsung --}}
    @if ($skala['tipe'] === 'angka')
        <div class="mt-3">
            <x-input-label :value="'Skor (' . $skala['min'] . '–' . $skala['max'] . ') *'" />
            <x-text-input type="number" :min="$skala['min']" :max="$skala['max']"
                wire:model.live="formEntryNyeri.nyeri.nyeriMetode.nyeriMetodeScore"
                :error="$errors->has('formEntryNyeri.nyeri.nyeriMetode.nyeriMetodeScore')"
                {{ $attributes->merge(['class' => 'w-32 mt-1']) }} />
            <x-input-error :messages="$errors->get('formEntryNyeri.nyeri.nyeriMetode.nyeriMetodeScore')" class="mt-1" />
        </div>
    @endif

    {{-- Tipe 'pilih': satu nilai dipilih dari deret --}}
    @if ($skala['tipe'] === 'pilih')
        <div class="flex flex-wrap gap-2 mt-3">
            @foreach ($dataNyeri as $opsi)
                <button type="button" wire:click="updateSkorSkala({{ $opsi['score'] }})"
                    class="px-3 py-2 text-xs font-bold border-2 rounded-lg transition
                        {{ $opsi['active'] ? 'border-brand bg-brand text-white' : 'border-gray-300 bg-canvas text-body hover:border-brand hover:text-brand dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300' }}">
                    {{ $opsi['description'] }}
                </button>
            @endforeach
        </div>
    @endif

    {{-- Tipe 'item': skor = jumlah aspek terpilih --}}
    @if ($skala['tipe'] === 'item')
        <div class="mt-3 space-y-3">
            @foreach ($dataNyeri as $kategori => $aspek)
                <div>
                    <x-input-label :value="$aspek['label'] . ' *'" />
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach ($aspek['opsi'] as $opsi)
                            <button type="button" wire:click="updateSkorItem('{{ $kategori }}', {{ $opsi['score'] }})"
                                class="px-3 py-1.5 text-xs text-left border-2 rounded-lg transition
                                    {{ $opsi['active'] ? 'border-brand bg-brand text-white' : 'border-gray-300 bg-canvas text-body hover:border-brand hover:text-brand dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300' }}">
                                <span class="font-bold">{{ $opsi['score'] }}</span> — {{ $opsi['description'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Interpretasi resmi skala ini --}}
    <p class="mt-3 text-xs text-muted-soft">
        Interpretasi:
        @foreach ($skala['interpretasi'] as $baris)
            {{ $baris[0] === $baris[1] ? $baris[0] : $baris[0] . '–' . $baris[1] }}
            {{ $baris[2] }}@if (!$loop->last) &nbsp;|&nbsp; @endif
        @endforeach
    </p>
</x-border-form>
