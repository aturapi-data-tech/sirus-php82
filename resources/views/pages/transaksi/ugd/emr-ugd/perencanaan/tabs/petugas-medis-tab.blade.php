{{-- pages/transaksi/ugd/emr-ugd/perencanaan/tabs/petugas-medis-tab.blade.php --}}
<div class="space-y-4">

    {{-- Terapi --}}
    <x-border-form :title="__('Terapi')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
        <div class="">
            @include('pages.transaksi.ugd.emr-ugd.perencanaan.tabs.terapi-tab')
            <p class="mt-3 text-sm text-muted dark:text-gray-400">
                Waktu Pemeriksaan:
                <span class="font-medium text-body dark:text-gray-200">
                    {{ $perencanaan['pengkajianMedis']['waktuPemeriksaan'] ?? '-' }}
                </span>
            </p>
        </div>
    </x-border-form>

    {{-- Dokter Pemeriksa --}}
    <x-border-form :title="__('Dokter Pemeriksa')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
        <div class="space-y-3">
            <x-signature.ttd-petugas :framed="false" :allowClear="false"
                :ttd="$perencanaan['pengkajianMedis']['drPemeriksa'] ?? ''"
                :date="$perencanaan['pengkajianMedis']['selesaiPemeriksaan'] ?? ''"
                {{-- Kode = drId kunjungan: setDrPemeriksa() hanya mengizinkan TTD bila drId == myuser_code dokter login --}}
                :code="$drId"
                :locked="$isFormLocked"
                sign="setDrPemeriksa" nameLabel="Dokter Pemeriksa" dateLabel="Selesai Pemeriksaan" signLabel="TTD Dokter" />

            <x-input-error :messages="$errors->get('perencanaan.pengkajianMedis.drPemeriksa')" class="mt-1" />

            {{-- Buka Kunci TTD-E — hanya muncul bila SUDAH ter-TTD, dan hanya untuk role
                 berhak (Gate dokumen.bukaKunci, sama dengan Buka Kunci Screening & modul
                 dokumen). Tombol TTD sendiri hilang begitu stempel
                 terisi, jadi tanpa ini salah TTD tak punya jalan pulang. Gaya mengikuti
                 Buka Kunci modul dokumen: x-confirm-button variant kuning. --}}
            @if (filled($perencanaan['pengkajianMedis']['drPemeriksa'] ?? ''))
                @can('dokumen.bukaKunci')
                    <div class="flex justify-end">
                        <x-confirm-button variant="warning-soft" action="bukaKunciTtdPemeriksa()"
                            title="Buka Kunci TTD-E"
                            message="Stempel TTD Dokter Pemeriksa akan dicabut supaya bisa ditandatangani ulang. Waktu pemeriksaan tetap. Tindakan ini tercatat di log aktivitas."
                            confirmText="Buka Kunci" class="px-2.5 py-1.5 text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                            </svg>
                            Buka Kunci TTD-E
                        </x-confirm-button>
                    </div>
                @endcan
            @endif
        </div>
    </x-border-form>

</div>
