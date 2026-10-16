<div class="space-y-4">

    {{-- Terapi --}}
    <x-border-form :title="__('Terapi')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
        <div class="">
            @include('pages.transaksi.rj.emr-rj.perencanaan.tabs.terapi-tab')

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
                {{-- Perwakilan tak diberi tombol TTD Dokter biasa (pasti ditolak) — ia memakai tombol di bawah. --}}
                :canSign="! $this->bolehTtdPerwakilan()"
                sign="setDrPemeriksa" nameLabel="Dokter Pemeriksa" dateLabel="Selesai Pemeriksaan" signLabel="TTD Dokter" />

            <x-input-error :messages="$errors->get('perencanaan.pengkajianMedis.drPemeriksa')" class="mt-1" />

            @if (!empty($perencanaan['pengkajianMedis']['diwakilkanOleh']['nama']))
                <p class="text-xs text-amber-700 dark:text-amber-400">
                    Diwakilkan oleh {{ $perencanaan['pengkajianMedis']['diwakilkanOleh']['nama'] }}
                    @if (!empty($perencanaan['pengkajianMedis']['diwakilkanOleh']['role']))
                        ({{ $perencanaan['pengkajianMedis']['diwakilkanOleh']['role'] }})
                    @endif
                </p>
            @endif

            {{-- TTD-E perwakilan (manager ke atas, Gate dokumen.ttdPerwakilan) — pola Resume Medis RI:
                 stempel tetap atas nama dokter kunjungan, penekan tombol tercatat di stempel & log. --}}
            @if (!$isFormLocked && blank($perencanaan['pengkajianMedis']['drPemeriksa'] ?? '') && $this->bolehTtdPerwakilan())
                <div>
                    <x-confirm-button variant="primary" action="setDrPemeriksaPerwakilan()"
                        title="TTD-E atas nama {{ $drDesc ?: 'Dokter Pemeriksa' }}?"
                        message="EMR akan ditandatangani ATAS NAMA dokter pemeriksa dan terkunci. Nama Anda tercatat sebagai perwakilan di stempel & log. Bila keliru: Buka Kunci TTD-E lalu TTD ulang."
                        confirmText="Ya, TTD Perwakilan" class="justify-center w-full"
                        wire:key="ttd-perwakilan-rj-{{ $rjNo }}">
                        TTD atas nama Dokter
                    </x-confirm-button>
                    <p class="mt-1 text-xs text-muted dark:text-gray-400">
                        Anda bukan dokter pemeriksa — TTD sebagai perwakilan atas nama
                        <strong>{{ $drDesc ?: '-' }}</strong>.
                    </p>
                </div>
            @endif

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
