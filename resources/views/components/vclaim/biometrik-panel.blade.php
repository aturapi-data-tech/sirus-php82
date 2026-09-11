{{-- resources/views/components/vclaim/biometrik-panel.blade.php

    Blok "Validasi Biometrik BPJS" di modal VClaim RJ/UGD/RI — SATU bentuk
    untuk ketiganya. Host wajib memakai BiometrikSepTrait (state $biometrik +
    method cekBiometrik / bukaBiometrik / muatPertanyaanBiometrik /
    jawabBiometrik / lewatiBiometrik / tutupBiometrik).

    Alur: Cek Status → '1' selesai; '0' tampilkan pertanyaan acak (pilih FKTP
    terdaftar + tanggal lahir) → BPJS jawab True = lolos; atau petugas memilih
    lanjut tanpa validasi (BPJS yang menolak bila layanan mewajibkan finger).

    Prop:
      :biometrik  state dari trait
      :disabled   form terkunci (SEP sudah ada / mode edit)
--}}

@props([
    'biometrik' => [],
    'disabled' => false,
])

@php
    $status = $biometrik['status'] ?? null;
    $lolos = (bool) ($biometrik['lolos'] ?? false);
    $lewati = (bool) ($biometrik['lewati'] ?? false);
    $tampil = (bool) ($biometrik['tampil'] ?? false);
    $faskesList = $biometrik['faskesList'] ?? [];

    [$variant, $label] = match (true) {
        $lolos => ['success', 'Lolos pertanyaan acak'],
        $lewati => ['gray', 'Dilewati petugas'],
        $status === '1' => ['success', 'Sudah validasi'],
        $status === '0' => ['warning', 'Belum validasi'],
        default => ['gray', 'Belum dicek'],
    };
@endphp

@unless ($disabled)
    <div class="p-3 mb-4 border bg-canvas border-hairline rounded-xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-semibold text-ink dark:text-gray-100">Validasi Biometrik BPJS</span>
            <x-badge :variant="$variant">{{ $label }}</x-badge>
            @if (filled($biometrik['keterangan'] ?? ''))
                <span class="text-xs text-muted-soft">{{ $biometrik['keterangan'] }}</span>
            @endif

            <span class="flex flex-wrap items-center gap-2 ml-auto">
                <x-secondary-button type="button" wire:click="cekBiometrik" wire:loading.attr="disabled" class="gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3m0 0V11" />
                    </svg>
                    {{ $status === null ? 'Cek Status' : 'Cek Ulang' }}
                </x-secondary-button>
                @if ($status === '0' && !$lolos)
                    @if (!$tampil)
                        <x-warning-button type="button" wire:click="bukaBiometrik" wire:loading.attr="disabled">
                            Pertanyaan Acak
                        </x-warning-button>
                    @endif
                    @if (!$lewati)
                        <x-ghost-button type="button" wire:click="lewatiBiometrik">Lanjut tanpa validasi</x-ghost-button>
                    @endif
                @endif
            </span>
        </div>

        @if ($tampil && $status === '0' && !$lolos)
            <div class="pt-3 mt-3 space-y-3 border-t border-hairline dark:border-gray-700">
                <p class="text-xs text-muted dark:text-gray-400">
                    Jalur resmi BPJS untuk peserta yang <strong>gagal atau tidak bisa</strong> validasi sidik jari.
                    Tanyakan ke peserta: <strong>di FKTP mana terdaftar?</strong> lalu pilih jawabannya. Tanggal lahir
                    diambil dari master pasien — betulkan bila berbeda dengan jawaban peserta.
                </p>

                <div class="max-w-xs">
                    <x-input-label for="biometrik-tgl-lahir" value="Tanggal lahir peserta (jawaban)" />
                    <x-text-input id="biometrik-tgl-lahir" type="date" class="block w-full mt-1"
                        wire:model="biometrik.tglLahir" />
                </div>

                @if (empty($faskesList))
                    <div class="flex items-center gap-2">
                        <p class="text-sm text-muted-soft">Pilihan faskes belum dimuat.</p>
                        <x-secondary-button type="button" wire:click="muatPertanyaanBiometrik" wire:loading.attr="disabled">
                            Muat pilihan
                        </x-secondary-button>
                    </div>
                @else
                    <div class="overflow-x-auto border bg-canvas rounded-2xl border-hairline dark:border-gray-700">
                        <table class="ds-table ds-table-rapat">
                            <thead>
                                <tr>
                                    <th class="ds-c w-8">No</th>
                                    <th>Faskes (pilihan jawaban)</th>
                                    <th class="ds-c w-24">Jawab</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($faskesList as $indexFaskes => $faskes)
                                    <tr>
                                        <td class="ds-c ds-td-meta">{{ $indexFaskes + 1 }}</td>
                                        <td class="break-words">
                                            <span class="ds-td-strong">{{ $faskes['nama'] ?: '-' }}</span>
                                            <span class="block font-mono text-xs text-muted-soft">{{ $faskes['kode'] }}</span>
                                        </td>
                                        {{-- wireClick INDEKS, bukan nama (nama ber-& rusak oleh double-escape) --}}
                                        <td class="ds-c">
                                            <x-primary-button type="button" wire:click="jawabBiometrik({{ $indexFaskes }})"
                                                wire:loading.attr="disabled">
                                                Ini
                                            </x-primary-button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="flex justify-end">
                    <x-secondary-button type="button" wire:click="tutupBiometrik">Tutup</x-secondary-button>
                </div>
            </div>
        @endif
    </div>
@endunless
