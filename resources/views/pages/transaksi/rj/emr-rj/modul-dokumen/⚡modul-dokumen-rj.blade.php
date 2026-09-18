<?php
// resources/views/pages/transaksi/rj/emr-rj/modul-dokumen/modul-dokumen-rj.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;

    /**
     * RINGKASAN status dokumen, bukan isinya.
     *
     * Hub ini memang perlu tahu keadaan SEMUA dokumen untuk menyalakan badge — tapi cukup
     * benar/salah dan hitungannya, bukan isi dokumennya. Menyimpan `datadaftarpolirj_json`
     * utuh di properti publik berarti mengirim seluruh dokumen bolak-balik tiap request
     * hanya demi lima badge.
     */
    public bool $adaSuket = false;
    public bool $adaGeneralConsent = false;
    public int $jumlahInformConsent = 0;
    public int $jumlahPenundaan = 0;
    public bool $adaBedah = false;

    // renderVersions
    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    /* ===============================
     | OPEN MODUL DOKUMEN RJ
     =============================== */
    /**
     * Tab yang langsung terbuka. Default 'suket' (perilaku lama). Pemanggil dari
     * luar EMR boleh meminta tab tertentu — mis. worklist Kamar Operasi membuka
     * langsung ke 'pelayanan-bedah' supaya petugas OK tak perlu menyusuri tab.
     */
    public string $tabAwal = 'suket';

    /** Tab yang boleh diminta pemanggil lewat event open. */
    private const TAB_BOLEH = ['suket', 'general-consent', 'inform-consent', 'penundaan-pelayanan', 'pelayanan-bedah'];

    #[On('emr-rj.modul-dokumen.open')]
    public function openModulDokumen(int $rjNo, string $tab = 'suket'): void
    {
        $this->resetForm();
        $this->rjNo = $rjNo;

        // SESUDAH resetForm — fungsi itu mengembalikan tabAwal ke default,
        // jadi menaruhnya di atas membuat permintaan tab pemanggil terhapus.
        $this->tabAwal = in_array($tab, self::TAB_BOLEH, true) ? $tab : 'suket';
        $this->resetValidation();

        $data = $this->findDataRJ($rjNo);

        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return;
        }

        $this->hitungRingkasan($data);

        if ($this->checkEmrRJStatus($rjNo)) {
            $this->isFormLocked = true;
        }

        $this->dispatch('open-modal', name: 'modul-dokumen-rj');
        // Anak memuat datanya sendiri di mount dari prop rjNo (isi modal dibungkus @if($rjNo)),
        // jadi tidak perlu lagi memancarkan open-rm-suket-rj.
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'modul-dokumen-rj');
    }

    public function save(): void
    {
        // Suket / General Consent / Inform Consent punya tombol simpan sendiri
    }

    #[On('refresh-modul-dokumen-rj-data')]
    public function refreshDataDaftarRJ(int $rjNo): void
    {
        if ($this->rjNo !== $rjNo) {
            return;
        }

        $data = $this->findDataRJ($rjNo);
        if ($data) {
            $this->hitungRingkasan($data);
        }
    }

    /** Dokumen dibaca sebagai variabel LOKAL, diperas jadi lima penanda, lalu dilepas. */
    private function hitungRingkasan(array $data): void
    {
        $this->adaSuket = !empty($data['suket']['suketSehat']) || !empty($data['suket']['suketIstirahat']);
        $this->adaGeneralConsent = !empty($data['generalConsentPasienRJ']['signature']);
        $this->jumlahInformConsent = count($data['informConsentPasienRJ'] ?? []);
        $this->jumlahPenundaan = count($data['penundaanPelayananRJ'] ?? []);
        $this->adaBedah = collect([
            'pengkajianPreOpRJ', 'praAnestesiRJ', 'praInduksiRJ', 'surgicalSafetyChecklistRJ',
            'laporanOperasiRJ', 'laporanAnestesiRJ', 'pascaAnestesiRJ', 'instruksiPascaBedahRJ',
        ])->contains(fn($k) => !empty($data[$k]));
    }

    protected function resetForm(): void
    {
        $this->tabAwal = 'suket';
        $this->reset(['rjNo', 'adaSuket', 'adaGeneralConsent', 'jumlahInformConsent', 'jumlahPenundaan', 'adaBedah']);
        $this->resetVersion();
        $this->isFormLocked = false;
    }

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }
};

?>

<div>
    <x-modal name="modul-dokumen-rj" size="full" height="full" focusable>
        {{-- CONTAINER UTAMA --}}
        {{-- Anak hanya di-mount saat ada pasien: tertutup = nol komponen, buka = mount sekali (baca CLOB
             dari prop rjNo di mount masing-masing), tutup = dihapus. Tidak ada event open-rm-* lagi. --}}
        @if ($rjNo)
        <div class="flex flex-col min-h-full" wire:key="{{ $this->renderKey('modal', [$rjNo ?? 'new']) }}">

            <x-modul-dokumen.header judul="Modul Dokumen"
                ikon="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                jalur="RJ" :readOnly="$isFormLocked">
                Formulir &amp; dokumen bertanda tangan pasien — consent, surat keterangan, laporan, dan pengkajian.
            </x-modul-dokumen.header>

            {{-- DISPLAY PASIEN — di bawah header, sama dengan modal modul dokumen --}}
            <div class="px-4 pt-2">
                <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                    wire:key="modul-dokumen-display-pasien-rj-header-{{ $rjNo }}" />
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft dark:bg-gray-950/20">
                <div class="max-w-full mx-auto">
                    <div
                        class="p-4 space-y-6 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        {{-- TAB NAVIGATOR --}}
                        <div x-data="{ activeTab: @js($tabAwal) }">

                            <div class="border-b border-hairline dark:border-gray-700 mb-4">
                                <div class="flex flex-wrap gap-1 -mb-px">

                                    {{-- Surat Keterangan --}}
                                    <x-tab variant="underline" active-expr="activeTab === 'suket'"
                                        x-on:click="activeTab = 'suket'" class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Surat Keterangan
                                        @if ($adaSuket)
                                            <x-badge variant="success" class="text-[10px] px-1.5 py-0">&#10003;</x-badge>
                                        @endif
                                    </x-tab>

                                    {{-- General Consent --}}
                                    <x-tab variant="underline" active-expr="activeTab === 'general-consent'"
                                        x-on:click="activeTab = 'general-consent'" class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z" />
                                        </svg>
                                        General Consent
                                        @if ($adaGeneralConsent)
                                            <x-badge variant="success"
                                                class="text-[10px] px-1.5 py-0">&#10003;</x-badge>
                                        @endif
                                    </x-tab>

                                    {{-- Inform Consent --}}
                                    <x-tab variant="underline" active-expr="activeTab === 'inform-consent'"
                                        x-on:click="activeTab = 'inform-consent'" class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                        </svg>
                                        Inform Consent
                                        @if ($jumlahInformConsent > 0)
                                            <x-badge variant="success"
                                                class="text-[10px] px-1.5 py-0">{{ $jumlahInformConsent }}</x-badge>
                                        @endif
                                    </x-tab>

                                    {{-- Penundaan / Kelambatan Pelayanan --}}
                                    <x-tab variant="underline" active-expr="activeTab === 'penundaan-pelayanan'"
                                        x-on:click="activeTab = 'penundaan-pelayanan'"
                                        class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Penundaan Pelayanan
                                        @if ($jumlahPenundaan > 0)
                                            <x-badge variant="success"
                                                class="text-[10px] px-1.5 py-0">{{ $jumlahPenundaan }}</x-badge>
                                        @endif
                                    </x-tab>

                                    <x-tab variant="underline" active-expr="activeTab === 'pelayanan-bedah'"
                                        x-on:click="activeTab = 'pelayanan-bedah'"
                                        class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Pelayanan Bedah
                                        @if ($adaBedah)
                                            <x-badge variant="success" class="text-[10px] px-1.5 py-0">&#10003;</x-badge>
                                        @endif
                                    </x-tab>

                                </div>
                            </div>

                            {{-- Panel: Surat Keterangan --}}
                            <div x-show="activeTab === 'suket'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.rj.emr-rj.modul-dokumen.suket-rj.rm-suket-rj-actions
                                    :rjNo="$rjNo" wire:key="suket-rj-{{ $rjNo }}" />
                            </div>

                            {{-- Panel: General Consent --}}
                            <div x-show="activeTab === 'general-consent'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.rj.emr-rj.modul-dokumen.general-consent-rj.rm-general-consent-rj-actions
                                    :rjNo="$rjNo" :disabled="$isFormLocked"
                                    wire:key="general-consent-rj-{{ $rjNo ?? 'init' }}" />
                            </div>

                            {{-- Panel: Inform Consent --}}
                            <div x-show="activeTab === 'inform-consent'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.rj.emr-rj.modul-dokumen.inform-consent-rj.rm-inform-consent-rj-actions
                                    :rjNo="$rjNo" :disabled="$isFormLocked"
                                    wire:key="inform-consent-rj-{{ $rjNo ?? 'init' }}" />
                            </div>

                            {{-- Panel: Penundaan Pelayanan --}}
                            <div x-show="activeTab === 'penundaan-pelayanan'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.rj.emr-rj.modul-dokumen.penundaan-pelayanan-rj.rm-penundaan-pelayanan-rj-actions
                                    :rjNo="$rjNo" :disabled="$isFormLocked"
                                    wire:key="penundaan-pelayanan-rj-{{ $rjNo ?? 'init' }}" />
                            </div>

                            {{-- Panel: Pelayanan Bedah — 8 form operasi (pola sama dengan EMR RI). --}}
                            <div x-show="activeTab === 'pelayanan-bedah'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.rj.emr-rj.modul-dokumen.pelayanan-bedah-rj.rm-pelayanan-bedah-rj-actions
                                    :rjNo="$rjNo" :disabled="$isFormLocked"
                                    wire:key="pelayanan-bedah-rj-{{ $rjNo ?? 'init' }}" />
                            </div>

                        </div>

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">
                        Tutup
                    </x-secondary-button>
                </div>
            </div>

        </div>
        @endif
    </x-modal>
</div>
