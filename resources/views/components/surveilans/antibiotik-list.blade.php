@props([
    // Baris antibiotik yang sudah tersimpan di form.
    'barisList' => [],
    // Baris staging yang sedang disusun ($barisObat).
    'barisBaru' => [],
    // Read-only (form terkunci / mode lihat).
    'formRO' => false,
    // Peta opsi dari App\Support\SurveilansHaisOptions.
    'opsiRute' => [],
    'opsiIndikasi' => [],
])

{{-- Daftar pemakaian antibiotik pada modul Surveilans HAIs.
     Pola sama dengan Leveling Dokter: form tambah → tabel + hapus (bukan 5 baris tetap).
     Komponen induk wajib punya method tambahAntibiotik() / hapusAntibiotik($index) /
     setNowObat($field) dan properti $barisObat. --}}
<div>
    @if (count($barisList) > 0)
        <div class="mb-3 overflow-x-auto">
            <table class="w-full overflow-hidden text-sm border rounded-lg border-hairline dark:border-gray-700">
                <thead class="uppercase bg-surface-soft dark:bg-gray-800 text-muted dark:text-gray-400">
                    <tr>
                        <th class="px-3 py-2 text-left">Nama Obat</th>
                        <th class="px-3 py-2 text-left">Tgl Mulai</th>
                        <th class="px-3 py-2 text-left">s/d Tgl</th>
                        <th class="px-3 py-2 text-left">Dosis</th>
                        <th class="px-3 py-2 text-left">Rute</th>
                        <th class="px-3 py-2 text-left">Indikasi</th>
                        @unless ($formRO)
                            <th class="px-3 py-2 text-center">Aksi</th>
                        @endunless
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline-soft dark:divide-gray-700">
                    @foreach ($barisList as $indeks => $baris)
                        <tr wire:key="obat-{{ $indeks }}" class="bg-canvas dark:bg-gray-900">
                            <td class="px-3 py-2 font-medium text-ink dark:text-gray-100">{{ $baris['namaObat'] ?: '-' }}</td>
                            <td class="px-3 py-2 font-mono text-muted">{{ $baris['tglMulai'] ?: '-' }}</td>
                            <td class="px-3 py-2 font-mono text-muted">{{ $baris['tglSelesai'] ?: '-' }}</td>
                            <td class="px-3 py-2 text-muted">{{ $baris['dosis'] ?: '-' }}</td>
                            <td class="px-3 py-2 text-muted">{{ $opsiRute[$baris['rute'] ?? ''] ?? '-' }}</td>
                            <td class="px-3 py-2 text-body dark:text-gray-300">{{ $opsiIndikasi[$baris['indikasi'] ?? ''] ?? '-' }}</td>
                            @unless ($formRO)
                                <td class="px-3 py-2 text-center">
                                    <x-outline-button type="button" wire:click.prevent="hapusAntibiotik({{ $indeks }})"
                                        wire:confirm="Hapus antibiotik ini dari daftar?" wire:loading.attr="disabled"
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
        <p class="mb-3 text-sm italic text-muted-soft">Belum ada antibiotik pada daftar.</p>
    @endif

    @unless ($formRO)
        <div class="p-3 border border-dashed rounded-lg border-gray-300 dark:border-gray-600 bg-canvas dark:bg-gray-800/50">
            <p class="mb-3 text-sm font-semibold tracking-wide uppercase text-ink dark:text-white">Tambah Antibiotik</p>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-12">
                <div class="sm:col-span-3">
                    <x-input-label value="Nama Obat" class="text-xs" />
                    <x-text-input wire:model="barisObat.namaObat" class="w-full mt-1" placeholder="mis. Ceftriaxone" />
                </div>
                <div class="sm:col-span-3">
                    <x-input-label value="Tgl Mulai" class="text-xs" />
                    <div class="flex gap-1 mt-1">
                        <x-text-input wire:model="barisObat.tglMulai" class="w-full" placeholder="dd/mm/yyyy HH:mm:ss" />
                        <x-now-button wire:click="setNowObat('tglMulai')" />
                    </div>
                </div>
                <div class="sm:col-span-3">
                    <x-input-label value="s/d Tgl" class="text-xs" />
                    <div class="flex gap-1 mt-1">
                        <x-text-input wire:model="barisObat.tglSelesai" class="w-full" placeholder="dd/mm/yyyy HH:mm:ss" />
                        <x-now-button wire:click="setNowObat('tglSelesai')" />
                    </div>
                </div>
                <div class="sm:col-span-3">
                    <x-input-label value="Dosis" class="text-xs" />
                    <x-text-input wire:model="barisObat.dosis" class="w-full mt-1" placeholder="mis. 2 x 1 gr" />
                </div>
                <div class="sm:col-span-3">
                    <x-input-label value="Rute" class="text-xs" />
                    <x-select-input wire:model="barisObat.rute" class="w-full mt-1">
                        <option value="">—</option>
                        @foreach ($opsiRute as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div class="sm:col-span-3">
                    <x-input-label value="Indikasi" class="text-xs" />
                    <x-select-input wire:model="barisObat.indikasi" class="w-full mt-1">
                        <option value="">—</option>
                        @foreach ($opsiIndikasi as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div class="flex items-end sm:col-span-6">
                    <x-primary-button type="button" wire:click="tambahAntibiotik" class="gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Tambah
                    </x-primary-button>
                </div>
            </div>
        </div>
    @endunless
</div>
