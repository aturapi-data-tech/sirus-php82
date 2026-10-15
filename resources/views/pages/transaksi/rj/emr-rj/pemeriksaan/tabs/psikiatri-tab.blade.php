@php
    // Partial tak mewarisi import komponen induk → FQCN.
    $opsiPsikiatri = \App\Support\Options\PsikiatriOptions::class;
    $psikiatri = $pemeriksaan['pemeriksaanPsikiatri'] ?? [];
    $isianLama = \App\Support\Options\PsikiatriOptions::ringkasanLama($psikiatri);
    $jalur = 'pemeriksaan.pemeriksaanPsikiatri';

    // Satu baris formulir: label kiri, pilihan kanan — meniru lembar kertas "STATUS PSIKIATRI (O)".
    // tipe radio = pilih satu, checkbox = boleh lebih dari satu.
    $barisPilihan = [
        ['label' => 'Penampilan', 'field' => 'penampilan', 'opsi' => $opsiPsikiatri::PENAMPILAN, 'tipe' => 'radio'],
        ['label' => 'Mood', 'field' => 'mood', 'opsi' => $opsiPsikiatri::MOOD, 'tipe' => 'checkbox'],
        ['label' => 'Afek', 'field' => 'afek', 'opsi' => $opsiPsikiatri::AFEK, 'tipe' => 'radio'],
    ];
    $barisProsesBerpikir = [
        ['label' => 'Bentuk', 'field' => 'bentukPikir', 'opsi' => $opsiPsikiatri::BENTUK_PIKIR],
        ['label' => 'Isi', 'field' => 'isiPikir', 'opsi' => $opsiPsikiatri::ISI_PIKIR],
        ['label' => 'Arus', 'field' => 'arusPikir', 'opsi' => $opsiPsikiatri::ARUS_PIKIR],
    ];
@endphp

<x-border-form :title="__('Status Psikiatri (O)')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
    <div class="divide-y divide-hairline dark:divide-gray-700">

        {{-- Penampilan --}}
        @php $baris = $barisPilihan[0]; @endphp
        <div class="py-3 sm:flex">
            <x-input-label :value="$baris['label']" class="mb-1 sm:w-40 sm:pr-4 shrink-0" />
            <div class="flex flex-wrap gap-x-4 gap-y-2">
                @foreach ($baris['opsi'] as $opsi)
                    <x-radio-button :label="$opsi" :value="$opsi" name="psikiatri-{{ $baris['field'] }}"
                        wire:model.live="{{ $jalur }}.{{ $baris['field'] }}" :disabled="$isFormLocked" />
                @endforeach
            </div>
        </div>

        {{-- Kesadaran: GCS (+ nilai) / Berkabut / Confusional State --}}
        <div class="py-3 sm:flex">
            <x-input-label value="Kesadaran" class="mb-1 sm:w-40 sm:pr-4 shrink-0" />
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                @foreach ($opsiPsikiatri::KESADARAN as $opsi)
                    <x-radio-button :label="$opsi" :value="$opsi" name="psikiatri-kesadaranStatus"
                        wire:model.live="{{ $jalur }}.kesadaranStatus" :disabled="$isFormLocked" />
                @endforeach
                @if (($psikiatri['kesadaranStatus'] ?? '') === 'GCS')
                    <x-text-input wire:model.live="{{ $jalur }}.kesadaranGcs" placeholder="Nilai GCS, cth: E4V5M6"
                        :disabled="$isFormLocked" class="w-48" />
                @endif
            </div>
        </div>

        {{-- Kontak: Mata +/- · Verbal Relevan/Irelevan · Lancar/Kurang Lancar --}}
        <div class="py-3 sm:flex">
            <x-input-label value="Kontak" class="mb-1 sm:w-40 sm:pr-4 shrink-0" />
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <span class="w-14 text-sm text-muted dark:text-gray-400">Mata</span>
                    @foreach ($opsiPsikiatri::KONTAK_MATA as $opsi)
                        <x-radio-button :label="$opsi" :value="$opsi" name="psikiatri-kontakMata"
                            wire:model.live="{{ $jalur }}.kontakMata" :disabled="$isFormLocked" />
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <span class="w-14 text-sm text-muted dark:text-gray-400">Verbal</span>
                    @foreach ($opsiPsikiatri::KONTAK_VERBAL_RELEVANSI as $opsi)
                        <x-radio-button :label="$opsi" :value="$opsi" name="psikiatri-kontakVerbalRelevansi"
                            wire:model.live="{{ $jalur }}.kontakVerbalRelevansi" :disabled="$isFormLocked" />
                    @endforeach
                    <span class="text-muted-soft">|</span>
                    @foreach ($opsiPsikiatri::KONTAK_VERBAL_KELANCARAN as $opsi)
                        <x-radio-button :label="$opsi" :value="$opsi" name="psikiatri-kontakVerbalKelancaran"
                            wire:model.live="{{ $jalur }}.kontakVerbalKelancaran" :disabled="$isFormLocked" />
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Mood (boleh lebih dari satu) & Afek (pilih satu) --}}
        @foreach ([$barisPilihan[1], $barisPilihan[2]] as $baris)
            <div class="py-3 sm:flex">
                <x-input-label :value="$baris['label']" class="mb-1 sm:w-40 sm:pr-4 shrink-0" />
                <div class="flex flex-wrap gap-x-4 gap-y-2">
                    @foreach ($baris['opsi'] as $opsi)
                        @if ($baris['tipe'] === 'checkbox')
                            <x-check-box :label="$opsi" :value="$opsi"
                                wire:model.live="{{ $jalur }}.{{ $baris['field'] }}" :disabled="$isFormLocked" />
                        @else
                            <x-radio-button :label="$opsi" :value="$opsi" name="psikiatri-{{ $baris['field'] }}"
                                wire:model.live="{{ $jalur }}.{{ $baris['field'] }}" :disabled="$isFormLocked" />
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach

        {{-- Proses Berpikir: Bentuk / Isi / Arus — boleh lebih dari satu --}}
        <div class="py-3">
            <x-input-label value="Proses Berpikir" />
            <div class="mt-2 space-y-3">
                @foreach ($barisProsesBerpikir as $baris)
                    <div class="sm:flex">
                        <span class="block mb-1 text-sm sm:w-40 sm:pl-4 sm:pr-4 shrink-0 text-muted dark:text-gray-400">{{ $baris['label'] }}</span>
                        <div class="flex flex-wrap gap-x-4 gap-y-2">
                            @foreach ($baris['opsi'] as $opsi)
                                <x-check-box :label="$opsi" :value="$opsi"
                                    wire:model.live="{{ $jalur }}.{{ $baris['field'] }}" :disabled="$isFormLocked" />
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Persepsi: Halusinasi (+ jenis) / Depersonalisasi --}}
        <div class="py-3 sm:flex">
            <x-input-label value="Persepsi" class="mb-1 sm:w-40 sm:pr-4 shrink-0" />
            <div class="space-y-2">
                <x-check-box label="Halusinasi" wire:model.live="{{ $jalur }}.halusinasi" :disabled="$isFormLocked" />
                @if (!empty($psikiatri['halusinasi']))
                    <div class="flex flex-wrap pl-6 gap-x-4 gap-y-2">
                        @foreach ($opsiPsikiatri::HALUSINASI as $opsi)
                            <x-check-box :label="$opsi" :value="$opsi"
                                wire:model.live="{{ $jalur }}.halusinasiJenis" :disabled="$isFormLocked" />
                        @endforeach
                    </div>
                @endif
                <x-check-box label="Depersonalisasi" wire:model.live="{{ $jalur }}.depersonalisasi" :disabled="$isFormLocked" />
            </div>
        </div>

        {{-- Kemauan & Psikomotor: Meningkat / Normal / Menurun --}}
        @foreach (['kemauanStatus' => ['Kemauan', $opsiPsikiatri::KEMAUAN], 'psikomotorStatus' => ['Psikomotor', $opsiPsikiatri::PSIKOMOTOR]] as $field => [$label, $daftarOpsi])
            <div class="py-3 sm:flex">
                <x-input-label :value="$label" class="mb-1 sm:w-40 sm:pr-4 shrink-0" />
                <div class="flex flex-wrap gap-x-4 gap-y-2">
                    @foreach ($daftarOpsi as $opsi)
                        <x-radio-button :label="$opsi" :value="$opsi" name="psikiatri-{{ $field }}"
                            wire:model.live="{{ $jalur }}.{{ $field }}" :disabled="$isFormLocked" />
                    @endforeach
                </div>
            </div>
        @endforeach

        {{-- Isian format lama (teks bebas, sebelum formulir ini dirombak) — hanya tampil,
             tetap tersimpan di JSON dan ikut tercetak dengan akhiran "(lama)". --}}
        @if ($isianLama)
            <div class="py-3">
                <p class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Isian format lama</p>
                <dl class="mt-1 text-sm">
                    @foreach ($isianLama as $label => $nilai)
                        <div class="flex gap-2">
                            <dt class="w-56 shrink-0 text-muted dark:text-gray-400">{{ str_replace(' (lama)', '', $label) }}</dt>
                            <dd class="text-ink dark:text-gray-200">{{ $nilai }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif
    </div>
</x-border-form>
