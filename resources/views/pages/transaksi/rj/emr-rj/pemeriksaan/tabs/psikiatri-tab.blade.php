@php
    // Partial tak mewarisi import komponen induk → FQCN.
    $opsiPsikiatri = \App\Support\Options\PsikiatriOptions::class;
    $psikiatri = $pemeriksaan['pemeriksaanPsikiatri'] ?? [];
    $isianLama = \App\Support\Options\PsikiatriOptions::ringkasanLama($psikiatri);
    $jalur = 'pemeriksaan.pemeriksaanPsikiatri';

    // Satu baris formulir: label kiri, pilihan kanan — meniru lembar kertas "Status Psikiatri".
    // tipe radio = pilih satu, toggle = boleh lebih dari satu (x-toggle per opsi, sama untuk
    // Proses Berpikir & jenis Halusinasi di bawah).
    $barisPilihan = [
        ['label' => 'Penampilan', 'field' => 'penampilan', 'opsi' => $opsiPsikiatri::PENAMPILAN, 'tipe' => 'radio'],
        ['label' => 'Mood', 'field' => 'mood', 'opsi' => $opsiPsikiatri::MOOD, 'tipe' => 'toggle'],
        ['label' => 'Afek', 'field' => 'afek', 'opsi' => $opsiPsikiatri::AFEK, 'tipe' => 'radio'],
    ];
    $barisProsesBerpikir = [
        ['label' => 'Bentuk', 'field' => 'bentukPikir', 'opsi' => $opsiPsikiatri::BENTUK_PIKIR],
        ['label' => 'Isi', 'field' => 'isiPikir', 'opsi' => $opsiPsikiatri::ISI_PIKIR],
        ['label' => 'Arus', 'field' => 'arusPikir', 'opsi' => $opsiPsikiatri::ARUS_PIKIR],
    ];
@endphp

<x-border-form :title="__('Status Psikiatri')" :align="__('start')" :bgcolor="__('bg-surface-soft')" :collapsible="true" :open="$psikiatriTerbuka ?? false">
    <div class="divide-y divide-hairline dark:divide-gray-700">

        {{-- Semua baris seragam: label di atas, pilihan mulai dari kiri selebar panel
             (panel Pemeriksaan cuma separuh modal — kolom label di kiri membuatnya sesak). --}}

        {{-- Penampilan --}}
        @php $baris = $barisPilihan[0]; @endphp
        <div class="py-3">
            <x-input-label :value="$baris['label']" class="mb-2" />
            <div class="flex flex-wrap gap-x-4 gap-y-2">
                @foreach ($baris['opsi'] as $opsi)
                    <x-radio-button :label="$opsi" :value="$opsi" name="psikiatri-{{ $baris['field'] }}"
                        wire:model.live="{{ $jalur }}.{{ $baris['field'] }}" :disabled="$isFormLocked" />
                @endforeach
            </div>
        </div>

        {{-- Kesadaran: GCS (+ nilai) / Berkabut / Confusional State --}}
        <div class="py-3">
            <x-input-label value="Kesadaran" class="mb-2" />
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                @foreach ($opsiPsikiatri::KESADARAN as $opsi)
                    <x-radio-button :label="$opsi" :value="$opsi" name="psikiatri-kesadaranStatus"
                        wire:model.live="{{ $jalur }}.kesadaranStatus" :disabled="$isFormLocked" />
                @endforeach
                @if (($psikiatri['kesadaranStatus'] ?? '') === 'GCS')
                    <x-text-input wire:model.live="{{ $jalur }}.kesadaranGcs"
                        placeholder="Nilai GCS, cth: E4V5M6" :disabled="$isFormLocked" class="w-48" />
                @endif
            </div>
        </div>

        {{-- Kontak: Mata +/- · Verbal Relevan/Irelevan · Kelancaran Lancar/Kurang Lancar
             — sub-label di atas tiap kelompok, ketiganya sejajar. --}}
        <div class="py-3">
            <x-input-label value="Kontak" />
            {{-- Tiga kelompok sejajar, dipisah garis vertikal tipis. Mata selebar isinya; Verbal &
                 Kelancaran berbagi sisa lebar — pilihan di dalamnya boleh turun baris, kelompoknya tidak.
                 Layar sempit: bertumpuk tanpa garis. --}}
            <div class="flex flex-col gap-3 mt-2 sm:flex-row">
                @foreach ([['label' => 'Mata', 'field' => 'kontakMata', 'opsi' => $opsiPsikiatri::KONTAK_MATA], ['label' => 'Verbal', 'field' => 'kontakVerbalRelevansi', 'opsi' => $opsiPsikiatri::KONTAK_VERBAL_RELEVANSI], ['label' => 'Kelancaran Verbal', 'field' => 'kontakVerbalKelancaran', 'opsi' => $opsiPsikiatri::KONTAK_VERBAL_KELANCARAN]] as $kelompok)
                    <div
                        class="{{ $loop->first ? 'shrink-0' : 'flex-1 min-w-0 sm:border-l sm:pl-4 border-hairline dark:border-gray-700' }}">
                        <span class="block mb-2 text-sm text-muted dark:text-gray-400">{{ $kelompok['label'] }}</span>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($kelompok['opsi'] as $opsi)
                                <x-radio-button :label="$opsi" :value="$opsi"
                                    name="psikiatri-{{ $kelompok['field'] }}"
                                    wire:model.live="{{ $jalur }}.{{ $kelompok['field'] }}" :disabled="$isFormLocked" />
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Mood (toggle, boleh lebih dari satu) & Afek (pilih satu) --}}
        @foreach ([$barisPilihan[1], $barisPilihan[2]] as $baris)
            <div class="py-3">
                <x-input-label :value="$baris['label']" class="mb-2" />
                <div
                    class="{{ $baris['tipe'] === 'toggle' ? 'grid grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-2' : 'flex flex-wrap gap-x-4 gap-y-2' }}">
                    @foreach ($baris['opsi'] as $indeks => $opsi)
                        @if ($baris['tipe'] === 'toggle')
                            <x-toggle :label="$opsi" :current="in_array($opsi, (array) ($psikiatri[$baris['field']] ?? []), true) ? 'Y' : 'N'"
                                wireClick="togglePilihanPsikiatri('{{ $baris['field'] }}', {{ $indeks }})"
                                :disabled="$isFormLocked" />
                        @else
                            <x-radio-button :label="$opsi" :value="$opsi"
                                name="psikiatri-{{ $baris['field'] }}"
                                wire:model.live="{{ $jalur }}.{{ $baris['field'] }}" :disabled="$isFormLocked" />
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach

        {{-- Proses Berpikir: Bentuk / Isi / Arus — boleh lebih dari satu --}}
        <div class="py-3">
            <x-input-label value="Proses Berpikir" />
            {{-- Bentuk / Isi / Arus dipisah garis horizontal tipis. --}}
            <div class="mt-2 divide-y divide-hairline dark:divide-gray-700">
                @foreach ($barisProsesBerpikir as $baris)
                    <div class="{{ $loop->first ? 'pb-3' : ($loop->last ? 'pt-3' : 'py-3') }}">
                        <span class="block mb-2 text-sm text-muted dark:text-gray-400">{{ $baris['label'] }}</span>
                        <div class="grid grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-2">
                            @foreach ($baris['opsi'] as $indeks => $opsi)
                                <x-toggle :label="$opsi" :current="in_array($opsi, (array) ($psikiatri[$baris['field']] ?? []), true)
                                    ? 'Y'
                                    : 'N'"
                                    wireClick="togglePilihanPsikiatri('{{ $baris['field'] }}', {{ $indeks }})"
                                    :disabled="$isFormLocked" />
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Persepsi: Halusinasi (+ jenis) / Depersonalisasi --}}
        <div class="py-3">
            <x-input-label value="Persepsi" class="mb-2" />
            <div class="space-y-2">
                <x-toggle label="Halusinasi" wire:model.live="{{ $jalur }}.halusinasi" :trueValue="true"
                    :falseValue="false" :disabled="$isFormLocked" />
                @if (!empty($psikiatri['halusinasi']))
                    <div class="grid grid-cols-2 pl-6 lg:grid-cols-3 gap-x-6 gap-y-2">
                        @foreach ($opsiPsikiatri::HALUSINASI as $indeks => $opsi)
                            <x-toggle :label="$opsi" :current="in_array($opsi, (array) ($psikiatri['halusinasiJenis'] ?? []), true) ? 'Y' : 'N'"
                                wireClick="togglePilihanPsikiatri('halusinasiJenis', {{ $indeks }})"
                                :disabled="$isFormLocked" />
                        @endforeach
                    </div>
                @endif
                <x-toggle label="Depersonalisasi" wire:model.live="{{ $jalur }}.depersonalisasi"
                    :trueValue="true" :falseValue="false" :disabled="$isFormLocked" />
            </div>
        </div>

        {{-- Kemauan & Psikomotor: Meningkat / Normal / Menurun --}}
        @foreach (['kemauanStatus' => ['Kemauan', $opsiPsikiatri::KEMAUAN], 'psikomotorStatus' => ['Psikomotor', $opsiPsikiatri::PSIKOMOTOR]] as $field => [$label, $daftarOpsi])
            <div class="py-3">
                <x-input-label :value="$label" class="mb-2" />
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
                            <dt class="w-56 shrink-0 text-muted dark:text-gray-400">
                                {{ str_replace(' (lama)', '', $label) }}</dt>
                            <dd class="text-ink dark:text-gray-200">{{ $nilai }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif
    </div>
</x-border-form>
