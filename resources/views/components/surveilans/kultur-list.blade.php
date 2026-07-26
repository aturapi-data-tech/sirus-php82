@props([
    // Key list di $newForm (mis. 'kulturDarahHasil') — dipakai sebagai argumen aksi server.
    'list',
    // Judul kecil di atas daftar.
    'title',
    // Baris yang sudah tersimpan di form.
    'rows' => [],
    // Baris staging yang sedang disusun (barisKultur[$list]).
    'baris' => ['tgl' => '', 'hasil' => ''],
    // Read-only (form terkunci / mode lihat).
    'formRO' => false,
    // Label & placeholder kolom hasil (mis. "Leukosit urin").
    'hasilLabel' => 'Hasil',
    'hasilPlaceholder' => 'Hasil pemeriksaan',
    'kosongTeks' => 'Belum ada hasil pada daftar.',
])

{{-- Daftar hasil kultur/pemeriksaan penunjang pada modul Surveilans HAIs.
     Pola sama dengan Leveling Dokter: form tambah → tabel + hapus (bukan baris tetap).
     Komponen induk wajib punya method tambahKultur($list) / hapusKultur($list, $index) /
     setNowKultur($list) dan properti $barisKultur[$list]. --}}
<div>
    <p class="mb-2 text-xs font-semibold tracking-wide uppercase text-muted-soft">{{ $title }}</p>

    @if (count($rows) > 0)
        <div class="mb-2 overflow-x-auto">
            <table class="w-full overflow-hidden text-sm border rounded-lg border-hairline dark:border-gray-700">
                <thead class="uppercase bg-surface-soft dark:bg-gray-800 text-muted dark:text-gray-400">
                    <tr>
                        <th class="px-3 py-2 text-left">Ke-</th>
                        <th class="px-3 py-2 text-left">Tanggal</th>
                        <th class="px-3 py-2 text-left">{{ $hasilLabel }}</th>
                        @unless ($formRO)
                            <th class="px-3 py-2 text-center">Aksi</th>
                        @endunless
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline-soft dark:divide-gray-700">
                    @foreach ($rows as $idx => $row)
                        <tr wire:key="kultur-{{ $list }}-{{ $idx }}" class="bg-canvas dark:bg-gray-900">
                            <td class="px-3 py-2 text-muted">{{ $idx + 1 }}</td>
                            <td class="px-3 py-2 font-mono text-muted">{{ $row['tgl'] ?: '-' }}</td>
                            <td class="px-3 py-2 text-body dark:text-gray-300">{{ $row['hasil'] ?: '-' }}</td>
                            @unless ($formRO)
                                <td class="px-3 py-2 text-center">
                                    <x-outline-button type="button" wire:click.prevent="hapusKultur('{{ $list }}', {{ $idx }})"
                                        wire:confirm="Hapus hasil ini dari daftar?" wire:loading.attr="disabled"
                                        class="!px-2 !py-1 !text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30"
                                        title="Hapus dari daftar">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </x-outline-button>
                                </td>
                            @endunless
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="mb-2 text-sm italic text-muted-soft">{{ $kosongTeks }}</p>
    @endif

    @unless ($formRO)
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-12">
            <div class="sm:col-span-5">
                <div class="flex gap-1">
                    <x-text-input wire:model="barisKultur.{{ $list }}.tgl" class="w-full" placeholder="dd/mm/yyyy HH:mm:ss" />
                    <x-now-button wire:click="setNowKultur('{{ $list }}')" />
                </div>
            </div>
            <div class="sm:col-span-5">
                <x-text-input wire:model="barisKultur.{{ $list }}.hasil" class="w-full" placeholder="{{ $hasilPlaceholder }}" />
            </div>
            <div class="sm:col-span-2">
                <x-primary-button type="button" wire:click="tambahKultur('{{ $list }}')" class="w-full gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah
                </x-primary-button>
            </div>
        </div>
    @endunless
</div>
