<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/surveilans-hais-ri/rm-surveilans-hais-ri-actions.blade.php
//
// Umbrella "Surveilans HAIs" — wadah sub-navigasi formulir surveilans infeksi RI
// (Formulir Surveilans HIPPII F/011/001/R/03), tiap jenis infeksi = modul sendiri.
// Tambahkan jenis surveilans berikutnya sebagai entri baru pada array $subForms.

use Livewire\Component;

new class extends Component {
    public ?string $riHdrNo = null;
    public bool $disabled = false;

    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
    }
};
?>

@php
    // Definisi sub-nav. icon = path SVG. pengisi/ket dipakai panduan & hint form aktif.
    $subForms = [
        ['key' => 'plebitis', 'label' => 'IADP & Plebitis', 'kelompok' => 'Infeksi aliran darah', 'pengisi' => 'IPCLN / Perawat ruangan', 'ket' => 'Kateter perifer / vena sentral / umbilikal — lokasi, lama pemasangan, tanda infeksi, kultur darah & pus', 'icon' => 'M12 3l5.5 6.5a5.5 5.5 0 11-11 0L12 3z'],
        ['key' => 'isk', 'label' => 'Infeksi Saluran Kemih', 'kelompok' => 'Infeksi saluran kemih', 'pengisi' => 'IPCLN / Perawat ruangan', 'ket' => 'Kateter SPP / douer / intermiten / kondom — tanda infeksi per kelompok usia, leukosit & biakan urin', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
        ['key' => 'vap', 'label' => 'Pneumonia Ventilator', 'kelompok' => 'Infeksi saluran napas', 'pengisi' => 'IPCLN / Perawat ICU', 'ket' => 'Lama pemakaian ventilator, demam, sekresi purulen, FiO2/PO2, foto toraks, kultur aspirat', 'icon' => 'M3 12h4l2 5 4-10 2 5h6'],
        ['key' => 'ilo', 'label' => 'Infeksi Luka Operasi', 'kelompok' => 'Infeksi daerah operasi', 'pengisi' => 'IPCLN / Perawat ruangan + tim OK', 'ket' => 'Data operasi (jenis, ASA, lama, implan), pemantauan luka hari ke-1 s/d 17, kultur', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
    ];

    $infoMap = collect($subForms)->keyBy('key')->map(fn($sub) => ['label' => $sub['label'], 'kelompok' => $sub['kelompok'], 'pengisi' => $sub['pengisi'], 'ket' => $sub['ket']]);
@endphp

<div x-data="{ subTab: 'plebitis', showGuide: false, info: @js($infoMap) }">

    {{-- ══ PANDUAN PENGISIAN (collapsible) ══ --}}
    <div class="mb-4 overflow-hidden border border-blue-200 rounded-2xl bg-blue-50 dark:bg-blue-900/20 dark:border-blue-700">
        <button type="button" x-on:click="showGuide = !showGuide"
            class="flex items-center justify-between w-full px-4 py-2.5 text-left transition-colors hover:bg-blue-100 dark:hover:bg-blue-900/30">
            <span class="flex items-center gap-2 text-base font-semibold text-blue-900 dark:text-blue-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Panduan Pengisian — Surveilans HAIs
            </span>
            <svg class="w-4 h-4 text-blue-600 transition-transform" :class="showGuide && 'rotate-180'" fill="none"
                stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div x-show="showGuide" x-collapse style="display:none" class="px-4 pb-4 space-y-3">
            <div>
                <p class="mb-1.5 text-sm font-semibold text-ink dark:text-gray-200">Jenis surveilans (satu modul per jenis infeksi):</p>
                <ol class="space-y-1 text-sm text-body dark:text-gray-300 list-decimal pl-5">
                    @foreach ($subForms as $sub)
                        <li>
                            <button type="button" x-on:click="subTab = '{{ $sub['key'] }}'; showGuide = false"
                                class="font-medium text-blue-700 underline-offset-2 hover:underline dark:text-blue-300">{{ $sub['label'] }}</button>
                            <span class="text-muted dark:text-gray-400"> — {{ $sub['pengisi'] }} · <em>{{ $sub['kelompok'] }}</em>: {{ $sub['ket'] }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="pt-2 border-t border-blue-200/60 dark:border-blue-700/60">
                <p class="mb-1.5 text-sm font-semibold text-ink dark:text-gray-200">Cara isi tiap formulir:</p>
                <ul class="space-y-1 text-sm text-body dark:text-gray-300 list-disc pl-5">
                    <li>Klik <b>Buka Formulir</b> pada jenis surveilans yang dipantau.</li>
                    <li>Isi <b>Data Dasar</b> (tanggal, cara masuk/keluar, tempat dirawat) dan <b>Faktor Risiko</b> pasien.</li>
                    <li>Lengkapi bagian khusus jenis infeksinya, lalu <b>Pemakaian Antibiotik</b>.</li>
                    <li>Klik <b>Simpan Draft</b> untuk mencicil; pantauan bisa dilanjutkan hari berikutnya lewat tombol <b>Lanjut Isi</b>.</li>
                    <li><b>Tanda Tangan Petugas</b> adalah aksi terakhir — sekaligus <b>mengunci</b> entri. Setelah terkunci hanya bisa Lihat / Cetak.</li>
                    <li>Satu pasien boleh punya lebih dari satu entri (mis. pemasangan ulang / operasi berulang).</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ══ SUB-NAV ══ --}}
    <x-tabs variant="chip" class="mb-3">
        @foreach ($subForms as $sub)
            <x-tab active-expr="subTab === '{{ $sub['key'] }}'" x-on:click="subTab = '{{ $sub['key'] }}'"
                class="inline-flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $sub['icon'] }}" />
                </svg>
                {{ $sub['label'] }}
            </x-tab>
        @endforeach
    </x-tabs>

    {{-- ══ HINT FORM AKTIF ══ --}}
    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 px-3 py-2 mb-4 text-sm rounded-lg bg-surface-soft border border-hairline text-muted dark:bg-gray-800/60 dark:border-gray-700 dark:text-gray-400">
        <svg class="w-4 h-4 shrink-0 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="font-semibold text-ink dark:text-gray-200" x-text="info[subTab]?.label"></span>
        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-brand-100 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300" x-text="info[subTab]?.kelompok"></span>
        <span>· Diisi oleh: <b class="text-body dark:text-gray-300" x-text="info[subTab]?.pengisi"></b></span>
        <span class="w-full sm:w-auto sm:before:content-['·'] sm:before:mr-2" x-text="info[subTab]?.ket"></span>
    </div>

    {{-- ① IADP & PLEBITIS --}}
    <div x-show="subTab === 'plebitis'" x-transition.opacity.duration.200ms>
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.surveilans-plebitis-ri.rm-surveilans-plebitis-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled"
            wire:key="surveilans-plebitis-ri-{{ $riHdrNo ?? 'init' }}" />
    </div>

    {{-- ② INFEKSI SALURAN KEMIH --}}
    <div x-show="subTab === 'isk'" x-transition.opacity.duration.200ms style="display:none">
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.surveilans-isk-ri.rm-surveilans-isk-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled"
            wire:key="surveilans-isk-ri-{{ $riHdrNo ?? 'init' }}" />
    </div>

    {{-- ③ PNEUMONIA VENTILATOR --}}
    <div x-show="subTab === 'vap'" x-transition.opacity.duration.200ms style="display:none">
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.surveilans-vap-ri.rm-surveilans-vap-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled"
            wire:key="surveilans-vap-ri-{{ $riHdrNo ?? 'init' }}" />
    </div>

    {{-- ④ INFEKSI LUKA OPERASI --}}
    <div x-show="subTab === 'ilo'" x-transition.opacity.duration.200ms style="display:none">
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.surveilans-ilo-ri.rm-surveilans-ilo-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled"
            wire:key="surveilans-ilo-ri-{{ $riHdrNo ?? 'init' }}" />
    </div>

</div>
