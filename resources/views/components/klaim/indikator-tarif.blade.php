@props([
    'tarifRs' => 0,          // tarif berjalan RS (Rp)
    'tarifKlaim' => 0,       // tarif klaim INA-CBG (Rp); 0 = belum grouping
    'labelKlaim' => 'INA-CBG',
    'ringkas' => false,      // true = untuk tabel padat: tanpa bar, nominal sebaris
])

{{--
    Indikator merah/kuning/hijau tarif berjalan RS vs tarif klaim.
    Ambang & rumus di App\Support\IndikatorTarifKlaim (≤60% hijau, 60–70% kuning, ≥70% merah).
    Pakai: <x-klaim.indikator-tarif :tarifRs="$row->tarif_rs" :tarifKlaim="$row->tarif_inacbg" [:ringkas="true"] />
--}}
@php
    $tarifRs = (int) $tarifRs;
    $tarifKlaim = (int) $tarifKlaim;
    $persentase = \App\Support\IndikatorTarifKlaim::persentase($tarifRs, $tarifKlaim);
    $warna = \App\Support\IndikatorTarifKlaim::warna($persentase);
    $gaya = [
        'hijau' => ['badge' => 'success', 'bar' => 'bg-emerald-500', 'teks' => 'Aman'],
        'kuning' => ['badge' => 'warning', 'bar' => 'bg-amber-400', 'teks' => 'Waspada'],
        'merah' => ['badge' => 'danger', 'bar' => 'bg-rose-500', 'teks' => 'Bahaya'],
        'kosong' => ['badge' => 'gray', 'bar' => 'bg-surface-strong', 'teks' => $ringkas ? 'Belum grouping' : 'Belum grouping ' . $labelKlaim],
    ][$warna];
    $rupiah = fn(int $nilai) => 'Rp ' . number_format($nilai, 0, ',', '.');
@endphp

<div {{ $attributes->merge(['class' => $ringkas ? 'space-y-0.5' : 'space-y-1.5']) }}
    title="Tarif RS {{ $rupiah($tarifRs) }} / {{ $labelKlaim }} {{ $tarifKlaim > 0 ? $rupiah($tarifKlaim) : '-' }}">
    <div class="flex items-center gap-2">
        <x-badge :variant="$gaya['badge']">
            @if ($persentase !== null)
                {{ number_format($persentase, 1, ',', '.') }}%
            @else
                -
            @endif
        </x-badge>
        <span class="text-xs font-medium text-body dark:text-gray-300">{{ $gaya['teks'] }}</span>
    </div>

    @if ($ringkas)
        <div class="text-xs text-muted dark:text-gray-400 tabular-nums whitespace-nowrap">
            <span class="font-semibold text-body dark:text-gray-200">{{ number_format($tarifRs, 0, ',', '.') }}</span>
            / {{ $tarifKlaim > 0 ? number_format($tarifKlaim, 0, ',', '.') : '-' }}
        </div>
    @else
        <div class="w-full h-1.5 bg-surface-strong rounded-full dark:bg-gray-700">
            <div class="h-1.5 rounded-full {{ $gaya['bar'] }}" style="width: {{ min(100, $persentase ?? 0) }}%"></div>
        </div>

        <div class="text-xs leading-relaxed text-muted dark:text-gray-400 tabular-nums">
            <div>RS: <span class="font-semibold text-body dark:text-gray-200">{{ $rupiah($tarifRs) }}</span></div>
            <div>{{ $labelKlaim }}: <span class="font-semibold text-body dark:text-gray-200">{{ $tarifKlaim > 0 ? $rupiah($tarifKlaim) : '-' }}</span></div>
        </div>
    @endif
</div>
