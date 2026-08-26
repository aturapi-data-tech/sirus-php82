{{-- Bagian D formulir DT-01. Partial markup murni.
     Import TIDAK diwarisi dari induk, jadi @use di bawah wajib ada. --}}
@use('App\Support\Options\PelaporanDowntimeOptions')

@php
    $jumlahManual = count(array_filter($dampak, fn ($baris) => ! empty($baris['manual'])));
    $saranLingkup = PelaporanDowntimeOptions::lingkupSaranDari($dampak);
@endphp

<x-border-form :title="__('D. Dampak terhadap Pelayanan')" :align="__('start')" :bgcolor="__('bg-canvas')">
    <div class="space-y-4">

        <p class="text-sm text-muted dark:text-gray-400">
            Nyalakan unit yang <strong>beralih ke manual</strong>. Semua unit tetap didaftar
            termasuk yang tidak terdampak — baris yang dihilangkan tak bisa dibedakan dari
            baris yang lupa diisi.
            @if ($jumlahManual > 0)
                <span class="font-semibold text-amber-700 dark:text-amber-400">
                    {{ $jumlahManual }} unit beralih manual.
                </span>
            @endif
        </p>

        {{-- Kolom jumlah & catatan hanya muncul untuk unit yang BERALIH MANUAL.
             Sebelumnya 9 baris × 3 isian = 27 kolom yang hampir semuanya dibiarkan
             kosong; padahal "berapa pasien dilayani manual" tak punya arti untuk
             unit yang sistemnya normal. --}}
        <div class="overflow-x-auto border border-hairline rounded-2xl dark:border-gray-700">
            <table class="ds-table">
                <thead>
                    <tr>
                        <th class="ds-c">No</th>
                        <th>Unit Pelayanan</th>
                        <th class="ds-c">Beralih ke Manual</th>
                        <th>Jml Pasien / Transaksi &amp; Catatan Dampak</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($dampak as $urut => $baris)
                        <tr wire:key="dampak-downtime-{{ $baris['unit'] }}"
                            class="{{ ! empty($baris['manual']) ? 'bg-amber-50 dark:bg-amber-900/10' : '' }}">
                            <td class="ds-c">{{ $urut + 1 }}</td>
                            <td class="ds-td-strong">{{ PelaporanDowntimeOptions::labelUnitDampak($baris['unit']) }}</td>
                            <td class="ds-c">
                                <div class="flex justify-center">
                                    <x-toggle wire:model.live="dampak.{{ $urut }}.manual"
                                        :trueValue="true" :falseValue="false" />
                                </div>
                            </td>
                            <td>
                                @if (! empty($baris['manual']))
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <div class="sm:w-40">
                                            <x-text-input wire:model.live.debounce.400ms="dampak.{{ $urut }}.jumlah"
                                                :error="$errors->has('dampak.' . $urut . '.jumlah')"
                                                inputmode="numeric" placeholder="jumlah, cth: 37" class="w-full" />
                                            <x-input-error :messages="$errors->get('dampak.' . $urut . '.jumlah')" class="mt-1" />
                                        </div>
                                        <div class="flex-1">
                                            <x-text-input wire:model.live.debounce.400ms="dampak.{{ $urut }}.catatan"
                                                :error="$errors->has('dampak.' . $urut . '.catatan')"
                                                placeholder="catatan dampak, cth: pakai formulir RJ-ADM-01" class="w-full" />
                                            <x-input-error :messages="$errors->get('dampak.' . $urut . '.catatan')" class="mt-1" />
                                        </div>
                                    </div>
                                @else
                                    <span class="text-sm text-muted dark:text-gray-400">tidak terdampak</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Saran, bukan paksaan: down time terencana tengah malam bisa saja tak
             membuat satu unit pun beralih manual padahal lingkupnya seluruh sistem. --}}
        @if ($saranLingkup !== null && $saranLingkup !== $kejadian['lingkup'])
            <div class="px-4 py-3 text-sm border rounded-2xl bg-blue-50 border-blue-200 text-blue-900 dark:bg-blue-900/20 dark:border-blue-700 dark:text-blue-100">
                Dari {{ $jumlahManual }} unit yang beralih manual, Lingkup Gangguan di Bagian A
                biasanya <strong>{{ PelaporanDowntimeOptions::labelLingkup($saranLingkup) }}</strong> —
                sekarang terisi {{ PelaporanDowntimeOptions::labelLingkup($kejadian['lingkup']) }}.
                Periksa lagi kalau memang bukan itu maksudnya.
            </div>
        @endif
    </div>
</x-border-form>
