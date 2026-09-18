{{-- pages/transaksi/ugd/emr-ugd/anamnesa/tabs/pengkajian-perawatan-tab.blade.php --}}
<x-border-form :title="__('Pengkajian')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
    <div class="space-y-4">

        {{-- Perawat Penerima (Waktu Datang otomatis saat TTD) --}}
        <div>
            <x-signature.ttd-petugas :framed="false" :allowClear="false"
                :ttd="$anamnesa['pengkajianPerawatan']['perawatPenerima'] ?? ''"
                :date="$anamnesa['pengkajianPerawatan']['jamDatang'] ?? ''"
                :code="$anamnesa['pengkajianPerawatan']['perawatPenerimaCode'] ?? ''"
                :locked="$isFormLocked ?? false"
                sign="setPerawatPenerima" nameLabel="Perawat Penerima" dateLabel="Waktu Datang" signLabel="Ttd Perawat" />
            <x-input-error :messages="$errors->get('anamnesa.pengkajianPerawatan.perawatPenerima')" class="mt-1" />

            {{-- Buka Kunci TTD Perawat — muncul hanya bila SUDAH ter-TTD dan untuk role
                 berhak (Gate dokumen.bukaKunci, sama dengan Buka Kunci Screening &
                 modul dokumen). Tombol TTD sendiri hilang begitu stempel terisi. --}}
            @if (filled($anamnesa['pengkajianPerawatan']['perawatPenerima'] ?? ''))
                @can('dokumen.bukaKunci')
                    @if ($adaDrPemeriksa)
                        {{-- Dokter menandatangani TERAKHIR dan TTD-nya mengesahkan seluruh
                             rekaman. Selagi stempelnya berdiri, stempel perawat tak boleh
                             dicabut — jadi tombolnya diganti keterangan, bukan disembunyikan
                             diam-diam, supaya petugas tahu apa yang harus dilakukan dulu. --}}
                        <p class="text-xs text-right text-muted dark:text-gray-400">
                            Buka kunci <strong>TTD-E Dokter Pemeriksa</strong> lebih dulu sebelum
                            stempel perawat bisa dicabut.
                        </p>
                    @else
                    <div class="flex justify-end">
                        <x-confirm-button variant="warning-soft" action="bukaKunciTtdPerawatPenerima()"
                            title="Buka Kunci TTD Perawat"
                            message="Stempel TTD Perawat Penerima akan dicabut supaya bisa ditandatangani ulang. Waktu datang dan isian pengkajian tetap. Tindakan ini tercatat di log aktivitas."
                            confirmText="Buka Kunci" class="px-2.5 py-1.5 text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                            </svg>
                            Buka Kunci TTD Perawat
                        </x-confirm-button>
                    </div>
                    @endif
                @endcan
            @endif
        </div>

        {{-- Tingkat Kegawatan --}}
        <div>
            <x-input-label value="Tingkat Kegawatan (Triage)" :required="true" />
            <div class="flex flex-wrap gap-2 mt-1">
                @foreach ($anamnesa['pengkajianPerawatan']['tingkatKegawatanOption'] ?? [] as $opt)
                    <x-radio-button :label="$opt['tingkatKegawatan']" :value="$opt['tingkatKegawatan']" name="tingkatKegawatan"
                        wire:model.live="tingkatKegawatan" :disabled="$isFormLocked" />
                @endforeach
            </div>

            {{-- Indikator warna triage --}}
            <div class="grid grid-cols-4 gap-2 mt-2">
                @foreach ([
        'P1' => ['label' => 'Kritis', 'class' => 'bg-red-500'],
        'P2' => ['label' => 'Urgent', 'class' => 'bg-yellow-400'],
        'P3' => ['label' => 'Minor', 'class' => 'bg-green-500'],
        'P0' => ['label' => 'Meninggal', 'class' => 'bg-gray-700'],
    ] as $p => $info)
                    <div
                        class="flex items-center justify-center gap-1.5 px-2 py-1 rounded-full text-sm text-white font-medium {{ $info['class'] }}
            {{ $tingkatKegawatan === $p ? 'ring-2 ring-offset-1 ring-current opacity-100' : 'opacity-50' }}">
                        {{ $info['label'] }}
                    </div>
                @endforeach
            </div>

            <x-input-error :messages="$errors->get('anamnesa.pengkajianPerawatan.tingkatKegawatan')" class="mt-1" />
        </div>

        {{-- Status Medik --}}
        <div>
            <x-input-label value="Status Medik" />
            <div class="grid grid-cols-1 gap-2 mt-1 sm:grid-cols-2">
                @foreach ($anamnesa['pengkajianPerawatan']['statusMedik']['statusMedikOptions'] ?? [] as $opt)
                    <x-radio-button :label="$opt['statusMedik']" :value="$opt['statusMedik']" name="statusMedikUGD"
                        wire:model.live="anamnesa.pengkajianPerawatan.statusMedik.statusMedik" :disabled="$isFormLocked" />
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('anamnesa.pengkajianPerawatan.statusMedik.statusMedik')" class="mt-1" />
        </div>

        {{-- Cara Masuk IGD --}}
        <div>
            <x-input-label value="Cara Masuk IGD" :required="true" />
            <div class="grid grid-cols-3 gap-2 mt-1">
                @foreach ($anamnesa['pengkajianPerawatan']['caraMasukIgdOption'] ?? [] as $opt)
                    <x-radio-button :label="$opt['caraMasukIgd']" :value="$opt['caraMasukIgd']" name="caraMasukIgd"
                        wire:model.live="caraMasukIgd" :disabled="$isFormLocked" />
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('anamnesa.pengkajianPerawatan.caraMasukIgd')" class="mt-1" />
        </div>

        {{-- Sarana Transportasi --}}
        <div>
            <x-input-label value="Sarana Transportasi" />
            <div class="flex flex-wrap gap-2 mt-1">
                @foreach ($anamnesa['pengkajianPerawatan']['saranaTransportasiOptions'] ?? [] as $opt)
                    <x-radio-button :label="$opt['saranaTransportasiDesc']" :value="$opt['saranaTransportasiId']" name="saranaTransportasiId"
                        wire:model.live="saranaTransportasiId" :disabled="$isFormLocked" />
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('saranaTransportasiId')" class="mt-1" />
            <x-input-error :messages="$errors->get('anamnesa.pengkajianPerawatan.saranaTransportasiId')" class="mt-1" />
            @if ($saranaTransportasiId === '4')
                <x-text-input wire:model.live="anamnesa.pengkajianPerawatan.saranaTransportasiKet"
                    placeholder="Sebutkan sarana transportasi..." class="w-full mt-2"
                    :error="$errors->has('anamnesa.pengkajianPerawatan.saranaTransportasiKet')"
                    :disabled="$isFormLocked" />
                <x-input-error :messages="$errors->get('anamnesa.pengkajianPerawatan.saranaTransportasiKet')" class="mt-1" />
            @endif
        </div>

        {{-- Anamnesa Diperoleh --}}
        <div>
            <x-input-label value="Anamnesa Diperoleh Dari" />
            <x-select-input wire:model.live="anamnesa.anamnesaDiperoleh.anamnesaDiperolehDari"
                :error="$errors->has('anamnesa.anamnesaDiperoleh.anamnesaDiperolehDari')"
                class="w-full" :disabled="$isFormLocked">
                <option value="">-- Pilih Sumber Anamnesa --</option>
                <option value="Auto-anamnesa (Pasien)">Auto-anamnesa (Pasien)</option>
                <option value="Allo-anamnesa (Keluarga)">Allo-anamnesa (Keluarga)</option>
                <option value="Allo-anamnesa (Lain-lain)">Allo-anamnesa (Lain-lain)</option>
            </x-select-input>
            <x-input-error :messages="$errors->get('anamnesa.anamnesaDiperoleh.anamnesaDiperolehDari')" class="mt-1" />
        </div>

        {{-- Keluhan Utama --}}
        <div>
            <x-input-label value="Keluhan Utama" :required="true" />
            <x-textarea wire:model.live="anamnesa.keluhanUtama.keluhanUtama" placeholder="Keluhan Utama"
                :error="$errors->has('anamnesa.keluhanUtama.keluhanUtama')" :disabled="$isFormLocked" :rows="3" class="w-full mt-1" />
            <x-input-error :messages="$errors->get('anamnesa.keluhanUtama.keluhanUtama')" class="mt-1" />
        </div>

        {{-- SNOMED CT — Keluhan Utama (untuk Satu Sehat) --}}
        <div>
            <livewire:lov.snomed.lov-snomed
                target="keluhanUtamaSnomed"
                label="Kode SNOMED Keluhan Utama (Satu Sehat)"
                placeholder="Ketik keluhan dalam Bahasa Indonesia / Inggris..."
                valueSet="condition-code"
                :initialSnomedCode="$anamnesa['keluhanUtama']['snomedCode'] ?? null"
                :disabled="$isFormLocked"
                wire:key="lov-snomed-keluhan-ugd-{{ $rjNo ?? 'new' }}-{{ $renderVersions['modal-anamnesa-ugd'] ?? 0 }}"
            />
        </div>

    </div>
</x-border-form>
