{{-- resources/views/components/nyeri/panduan-skala.blade.php

    Panel panduan "skala nyeri ini untuk siapa" — daftar semua skala beserta
    sasaran populasi, rentang skor, catatan, dan aturan tata laksana berjenjang.
    Default tertutup (gaya panel biru-info standar).

    Pemakaian:
      <x-nyeri.panduan-skala :daftarSkala="$this->daftarSkala" />
--}}

@props(['daftarSkala' => []])

<div x-data="{ buka: false }" class="border rounded-lg border-blue-200 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800">
    <button type="button" x-on:click="buka = !buka"
        class="flex items-center justify-between w-full px-4 py-2.5 text-sm font-semibold text-blue-900 dark:text-blue-100">
        <span class="flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Panduan: skala nyeri ini untuk siapa?
        </span>
        <svg class="w-4 h-4 transition-transform" x-bind:class="buka && 'rotate-180'" fill="none" stroke="currentColor"
            viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div x-show="buka" x-cloak class="px-4 pb-3 overflow-x-auto">
        <table class="w-full text-xs text-left text-blue-900 dark:text-blue-100">
            <thead class="border-b border-blue-200 dark:border-blue-800">
                <tr>
                    <th class="py-1.5 pr-3 font-semibold">Skala</th>
                    <th class="py-1.5 pr-3 font-semibold">Dipakai untuk</th>
                    <th class="py-1.5 pr-3 font-semibold whitespace-nowrap">Rentang</th>
                    <th class="py-1.5 font-semibold">Catatan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($daftarSkala as $kode => $skala)
                    <tr class="border-b border-blue-100 last:border-0 dark:border-blue-900">
                        <td class="py-1.5 pr-3 align-top whitespace-nowrap">
                            <span class="font-bold">{{ $kode }}</span>
                            <div class="text-[11px] opacity-80">{{ $skala['nama'] }}</div>
                        </td>
                        <td class="py-1.5 pr-3 align-top">{{ $skala['sasaran'] }}</td>
                        <td class="py-1.5 pr-3 align-top font-mono whitespace-nowrap">
                            {{ $skala['min'] }}–{{ $skala['max'] }}
                        </td>
                        <td class="py-1.5 align-top opacity-90">{{ $skala['catatan'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="mt-2 text-[11px] text-blue-900 dark:text-blue-100 opacity-90">
            Tata laksana menurut skor: <strong>0–3</strong> perawat ·
            <strong>4–6</strong> dokter umum/DPJP · <strong>7–10</strong> DPJP atau Tim Nyeri RS.
        </p>
    </div>
</div>
