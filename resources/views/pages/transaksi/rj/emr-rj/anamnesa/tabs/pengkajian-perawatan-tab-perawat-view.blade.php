<x-border-form :title="__('Pengkajian')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
    <div class="space-y-4">

        {{-- Perawat Penerima (Waktu Datang otomatis saat TTD) --}}
        <div>
            <x-signature.ttd-petugas :framed="false" :allowClear="false"
                :ttd="$dataDaftarPoliRJ['anamnesa']['pengkajianPerawatan']['perawatPenerima'] ?? ''"
                :date="$dataDaftarPoliRJ['anamnesa']['pengkajianPerawatan']['jamDatang'] ?? ''"
                :code="$dataDaftarPoliRJ['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'] ?? ''"
                :locked="$isFormLocked ?? false"
                sign="setPerawatPenerima" nameLabel="Perawat Penerima" dateLabel="Waktu Datang" signLabel="Ttd Perawat" />
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.perawatPenerima')" class="mt-1" />

            {{-- Buka Kunci TTD Perawat — muncul hanya bila SUDAH ter-TTD dan untuk role
                 berhak (Gate dokumen.bukaKunci, sama dengan Buka Kunci Screening &
                 modul dokumen). Tombol TTD sendiri hilang begitu stempel terisi. --}}
            @if (filled($dataDaftarPoliRJ['anamnesa']['pengkajianPerawatan']['perawatPenerima'] ?? ''))
                @can('dokumen.bukaKunci')
                    @if (filled($dataDaftarPoliRJ['perencanaan']['pengkajianMedis']['drPemeriksa'] ?? ''))
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

        {{-- Keluhan Utama --}}
        <div>
            <x-input-label for="dataDaftarPoliRJ.anamnesa.keluhanUtama.keluhanUtama" value="Keluhan Utama"
                :required="true" />

            <x-textarea id="dataDaftarPoliRJ.anamnesa.keluhanUtama.keluhanUtama"
                wire:model.live="dataDaftarPoliRJ.anamnesa.keluhanUtama.keluhanUtama" placeholder="Keluhan Utama"
                :error="$errors->has('dataDaftarPoliRJ.anamnesa.keluhanUtama.keluhanUtama')" :disabled="$isFormLocked" :rows="3" class="w-full mt-1" />

            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.keluhanUtama.keluhanUtama')" class="mt-1" />
        </div>

        {{-- SNOMED CT — Keluhan Utama (untuk Satu Sehat) --}}
        <div>
            <livewire:lov.snomed.lov-snomed
                target="keluhanUtamaSnomed"
                label="Kode SNOMED Keluhan Utama (Satu Sehat)"
                placeholder="Ketik keluhan dalam Bahasa Indonesia / Inggris..."
                valueSet="condition-code"
                :initialSnomedCode="$dataDaftarPoliRJ['anamnesa']['keluhanUtama']['snomedCode'] ?? null"
                :disabled="$isFormLocked"
                wire:key="lov-snomed-keluhan-{{ $rjNo ?? 'new' }}-{{ $renderVersions['modal-anamnesa-rj'] ?? 0 }}"
            />
        </div>

    </div>
</x-border-form>
