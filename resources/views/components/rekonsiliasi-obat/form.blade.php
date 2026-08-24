{{--
    Form Tambah + tabel daftar Rekonsiliasi Obat.

    Dipakai modal titik-3 Daftar RI & Pelayanan UGD. Komponen induknya WAJIB punya
    properti array bernama formEntryRekonsiliasi (keys: namaObat, dosis, rute,
    dibawaRanap, lanjutPulang) — nama itu sengaja disamakan di kedua modal supaya
    markup ini tidak perlu tahu sedang dipasang di jalur mana.

    Tab EMR (Anamnesa UGD & Pengkajian Dokter RI) TIDAK memakai komponen ini:
    keduanya menempel pada state induk yang beda bentuk ($dataDaftarUGD /
    $dataDaftarRi) dan punya panel panduan sendiri. Yang disatukan di antara
    keempatnya adalah bentuk barisnya, lewat App\Support\RekonsiliasiObat.
--}}
@use('App\Support\RekonsiliasiObat')

@props([
    'daftarObat' => [],
    'isFormLocked' => false,
    // Nilai toggle DIKIRIM sebagai prop: komponen anonim tidak melihat properti
    // Livewire induk, jadi $formEntryRekonsiliasi milik induk tidak terbaca
    // sebagai variabel di berkas ini.
    'dibawaRanap' => 'Tidak',
    'lanjutPulang' => 'Tidak',
    'keyPrefix' => 'rekon',
    'aksiTambah' => 'addRekonsiliasiObat',
    'aksiHapus' => 'removeRekonsiliasiObat',
])

<div class="space-y-4">

    @unless ($isFormLocked)
        <div class="space-y-3">
            <div class="grid grid-cols-12 gap-2">
                <div class="col-span-5">
                    <x-input-label value="Nama Obat" :required="true" class="truncate whitespace-nowrap" />
                    <x-text-input wire:model="formEntryRekonsiliasi.namaObat" wire:keydown.enter.prevent="{{ $aksiTambah }}"
                        placeholder="Amlodipin 10 mg" :error="$errors->has('formEntryRekonsiliasi.namaObat')" class="w-full px-2 mt-1" />
                    <x-input-error :messages="$errors->get('formEntryRekonsiliasi.namaObat')" class="mt-1" />
                </div>

                <div class="col-span-3">
                    <x-input-label value="Dosis" :required="true" class="truncate whitespace-nowrap" />
                    <x-text-input wire:model="formEntryRekonsiliasi.dosis" wire:keydown.enter.prevent="{{ $aksiTambah }}"
                        placeholder="1x1 tab" :error="$errors->has('formEntryRekonsiliasi.dosis')" class="w-full px-2 mt-1" />
                    <x-input-error :messages="$errors->get('formEntryRekonsiliasi.dosis')" class="mt-1" />
                </div>

                <div class="col-span-4">
                    <x-input-label value="Rute" :required="true" class="truncate whitespace-nowrap" />
                    <x-select-input wire:model="formEntryRekonsiliasi.rute" :error="$errors->has('formEntryRekonsiliasi.rute')" class="w-full px-2 mt-1">
                        <option value="">&mdash;</option>
                        @foreach (RekonsiliasiObat::RUTE as $rute)
                            <option value="{{ $rute }}">{{ $rute }}</option>
                        @endforeach
                    </x-select-input>
                    <x-input-error :messages="$errors->get('formEntryRekonsiliasi.rute')" class="mt-1" />
                </div>
            </div>

            <div class="pt-1 space-y-2 border-t border-hairline dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <x-input-label value="Dibawa Saat Ranap" :required="false" />
                    <x-toggle wire:model.live="formEntryRekonsiliasi.dibawaRanap" trueValue="Ya" falseValue="Tidak" :label="$dibawaRanap === 'Ya' ? 'Ya' : 'Tidak'" />
                </div>

                <div class="flex items-center justify-between gap-3">
                    <x-input-label value="Lanjut Saat Pulang" :required="false" />
                    <x-toggle wire:model.live="formEntryRekonsiliasi.lanjutPulang" trueValue="Ya" falseValue="Tidak" :label="$lanjutPulang === 'Ya' ? 'Ya' : 'Tidak'" />
                </div>
            </div>

            <x-primary-button type="button" wire:click="{{ $aksiTambah }}" wire:loading.attr="disabled"
                wire:target="{{ $aksiTambah }}" class="justify-center gap-1.5 w-full">
                <span wire:loading.remove wire:target="{{ $aksiTambah }}" class="flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah
                </span>
                <span wire:loading wire:target="{{ $aksiTambah }}" class="flex items-center gap-1.5">
                    <x-loading class="w-4 h-4" /> Menyimpan...
                </span>
            </x-primary-button>
        </div>
    @endunless

    <div class="overflow-x-auto border bg-canvas rounded-2xl border-hairline dark:border-gray-700">
        <table class="ds-table">
            <thead>
                <tr>
                    <th class="ds-c w-10">No</th>
                    <th>Obat (Dosis &middot; Rute)</th>
                    <th>Keterangan</th>
                    <th class="w-44">Petugas</th>
                    <th class="ds-c w-14">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftarObat as $index => $obat)
                    <tr wire:key="{{ $keyPrefix }}-{{ $index }}">
                        @php
                            $dosisRute = collect([$obat['dosis'] ?? null, $obat['rute'] ?? null])
                                ->filter(fn($isi) => filled($isi))
                                ->implode(' · ');
                        @endphp
                        <td class="ds-c ds-td-meta">{{ $index + 1 }}</td>
                        <td>
                            <div class="ds-td-strong">{{ $obat['namaObat'] ?? '-' }}</div>
                            @if ($dosisRute)
                                <div class="text-muted dark:text-gray-400">{{ $dosisRute }}</div>
                            @endif
                        </td>

                        <td>
                            <div class="space-y-1.5">
                                @foreach ([['dibawaRanap', 'Dibawa saat ranap'], ['lanjutPulang', 'Lanjut saat pulang']] as [$kolom, $judul])
                                    @php $nilai = ($obat[$kolom] ?? 'Tidak') === 'Ya' ? 'Ya' : 'Tidak'; @endphp
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-muted dark:text-gray-400">{{ $judul }}</span>
                                        <span
                                            class="font-medium {{ $nilai === 'Ya' ? 'text-success-deep dark:text-success' : 'text-muted-soft' }}">
                                            {{ $nilai }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </td>

                        {{-- Pencatat entri. Baris lama (sebelum field ini ada) tampil '-'. --}}
                        <td>
                            @if (filled($obat['petugasRekonsiliasi'] ?? null))
                                <div class="ds-td-strong">{{ $obat['petugasRekonsiliasi'] }}</div>
                                @if (filled($obat['tglRekonsiliasi'] ?? null))
                                    <div class="text-muted dark:text-gray-400">{{ $obat['tglRekonsiliasi'] }}</div>
                                @endif
                            @else
                                <span class="text-muted-soft">-</span>
                            @endif
                        </td>

                        <td class="ds-c">
                            @unless ($isFormLocked)
                                <x-confirm-button variant="danger-soft" :action="$aksiHapus . '(' . $index . ')'" title="Hapus Obat"
                                    :message="'Yakin hapus ' . ($obat['namaObat'] ?? 'obat ini') . ' dari daftar?'" confirmText="Ya, hapus" cancelText="Batal"
                                    class="px-2 py-1">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </x-confirm-button>
                            @else
                                <span class="text-muted-soft">&mdash;</span>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="ds-c italic text-muted-soft">
                            Belum ada riwayat pemakaian obat.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
