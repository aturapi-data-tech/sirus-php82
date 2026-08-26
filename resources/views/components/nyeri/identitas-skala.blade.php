{{-- resources/views/components/nyeri/identitas-skala.blade.php

    Kartu identitas skala yang sedang dipakai + badge skor, interpretasi, dan
    tata laksana berjenjang. Dipakai di form Penilaian Nyeri RJ/UGD/RI.

    Pemakaian:
      <x-nyeri.identitas-skala :skala="$this->skalaTerpilih" :kode="..." :skor="..." :tafsir="$this->interpretasiBerjalan" />
--}}

@props(['skala', 'kode' => '', 'skor' => 0, 'tafsir' => []])

<div class="space-y-2">
    <div class="px-4 py-2.5 border rounded-lg border-hairline bg-surface-soft dark:bg-gray-800 dark:border-gray-700">
        <p class="text-sm font-semibold text-ink dark:text-gray-100">
            {{ $kode }} — {{ $skala['nama'] }}
        </p>
        <p class="mt-0.5 text-xs text-muted">
            Untuk: {{ $skala['sasaran'] }} · Rentang skor {{ $skala['min'] }}–{{ $skala['max'] }}
        </p>
        <p class="mt-0.5 text-xs text-muted-soft">{{ $skala['catatan'] }}</p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <span class="px-3 py-1 text-xs font-bold text-white rounded-lg bg-brand">
            Skor: {{ $skor }} / {{ $skala['max'] }}
        </span>
        @if (!empty($tafsir['tingkat']))
            <span class="px-2 py-0.5 text-xs font-bold rounded-full {{ $tafsir['badge'] }}">
                {{ $tafsir['label'] }}
            </span>
            <span
                class="px-2 py-0.5 text-xs font-medium border rounded-full bg-surface-soft text-body border-hairline dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                {{ $tafsir['tataLaksana'] }}
            </span>
        @endif
    </div>
</div>
