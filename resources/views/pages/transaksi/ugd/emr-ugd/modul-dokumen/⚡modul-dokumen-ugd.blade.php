<?php
// resources/views/pages/transaksi/ugd/emr-ugd/modul-dokumen/modul-dokumen-ugd.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    /**
     * RINGKASAN status dokumen, bukan isinya.
     *
     * Hub ini memang perlu tahu keadaan SEMUA dokumen untuk menyalakan badge — tapi cukup
     * benar/salah dan hitungannya. Menyimpan `datadaftarugd_json` utuh berarti mengirim
     * seluruh dokumen bolak-balik tiap request hanya demi belasan badge.
     */
    public bool $adaSuket = false;
    public bool $adaTrfUgd = false;
    public bool $adaGeneralConsent = false;
    public bool $adaBedah = false;
    public bool $adaSuratKematianFinal = false;
    public string $triaseSaran = '';
    public int $jumlahInformConsent = 0;
    public int $jumlahPenjaminan = 0;
    public int $jumlahPenundaan = 0;
    public int $jumlahPenolakanObat = 0;
    public int $jumlahPenolakanResusitasi = 0;
    public int $jumlahSecondOpinion = 0;
    public int $jumlahEso = 0;
    public int $jumlahAkhirHayat = 0;
    public int $jumlahKriteriaRobson = 0;

    /** Dokumen dibaca sebagai variabel LOKAL, diperas jadi penanda, lalu dilepas. */
    private function hitungRingkasan(array $data): void
    {
        $this->adaSuket = !empty($data['suket']['suketSehat']) || !empty($data['suket']['suketIstirahat']);
        $this->adaTrfUgd = !empty($data['trfUgd']['petugasPengirim']);
        $this->adaGeneralConsent = !empty($data['generalConsentPasienUGD']['signature']);
        $this->adaSuratKematianFinal = !empty($data['suratKematianUGD']['isFinal']);
        $this->triaseSaran = (string) ($data['screening']['triaseSaran'] ?? '');
        $this->jumlahInformConsent = count($data['informConsentPasienUGD'] ?? []);
        $this->jumlahPenjaminan = count($data['formPenjaminanOrientasiKamar'] ?? []);
        $this->jumlahPenundaan = count($data['penundaanPelayananUGD'] ?? []);
        $this->jumlahPenolakanObat = count($data['penolakanObatUGD'] ?? []);
        $this->jumlahPenolakanResusitasi = count($data['penolakanResusitasiUGD'] ?? []);
        $this->jumlahSecondOpinion = count($data['secondOpinionUGD'] ?? []);
        $this->jumlahEso = count($data['pelaporanEsoUGD'] ?? []);
        $this->jumlahAkhirHayat = count($data['pengkajianAkhirHayatUGD'] ?? []);
        $this->jumlahKriteriaRobson = count($data['kriteriaRobsonUGD'] ?? []);
        $this->adaBedah = collect([
            'pengkajianPreOpUGD', 'praAnestesiUGD', 'praInduksiUGD', 'surgicalSafetyChecklistUGD',
            'laporanOperasiUGD', 'laporanAnestesiUGD', 'pascaAnestesiUGD', 'instruksiPascaBedahUGD',
        ])->contains(fn($k) => !empty($data[$k]));
    }

    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    /**
     * Tab yang langsung terbuka. Default 'suket' (perilaku lama). Pemanggil dari
     * luar EMR boleh meminta tab tertentu — mis. worklist Kamar Operasi membuka
     * langsung ke 'pelayanan-bedah' supaya petugas OK tak perlu menyusuri tab.
     */
    public string $tabAwal = 'suket';

    /** Tab yang boleh diminta pemanggil lewat event open. */
    private const TAB_BOLEH = ['suket', 'trf-ri', 'general-consent', 'inform-consent', 'form-penjaminan', 'penundaan-pelayanan', 'akhir-hayat', 'surat-kematian', 'pelayanan-bedah'];

    #[On('emr-ugd.modul-dokumen.open')]
    public function openModulDokumen(int $rjNo, string $tab = 'suket'): void
    {
        $this->resetForm();
        $this->rjNo = $rjNo;

        // SESUDAH resetForm — fungsi itu mengembalikan tabAwal ke default,
        // jadi menaruhnya di atas membuat permintaan tab pemanggil terhapus.
        $this->tabAwal = in_array($tab, self::TAB_BOLEH, true) ? $tab : 'suket';
        $this->resetValidation();

        $data = $this->findDataUGD($rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        $this->hitungRingkasan($data);

        if ($this->checkEmrUGDStatus($rjNo)) {
            $this->isFormLocked = true;
        }

        $this->dispatch('open-modal', name: 'modul-dokumen-ugd');
        // Anak memuat datanya sendiri di mount dari prop rjNo (isi modal dibungkus @if($rjNo)),
        // jadi tidak perlu lagi memancarkan open-rm-suket-ugd / form-trf-ugd-ri / form-penjaminan.
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'modul-dokumen-ugd');
    }

    public function save(): void
    {
        // General Consent, Inform Consent, dan Form Penjaminan punya tombol simpan sendiri
    }

    #[On('refresh-modul-dokumen-ugd-data')]
    public function refreshDataDaftarUGD(int $rjNo): void
    {
        if ($this->rjNo !== $rjNo) {
            return;
        }

        $data = $this->findDataUGD($rjNo);
        if ($data) {
            $this->hitungRingkasan($data);
        }
    }

    protected function resetForm(): void
    {
        $this->tabAwal = 'suket';
        $this->reset([
            'rjNo', 'adaSuket', 'adaTrfUgd', 'adaGeneralConsent', 'adaBedah',
            'adaSuratKematianFinal', 'triaseSaran', 'jumlahInformConsent', 'jumlahPenjaminan',
            'jumlahPenundaan', 'jumlahPenolakanObat', 'jumlahPenolakanResusitasi',
            'jumlahSecondOpinion', 'jumlahEso', 'jumlahAkhirHayat', 'jumlahKriteriaRobson',
        ]);
        $this->resetVersion();
        $this->isFormLocked = false;
    }
};
?>

<div>
    <x-modal name="modul-dokumen-ugd" size="full" height="full" focusable>
        {{-- Anak hanya di-mount saat ada pasien: tertutup = nol komponen, buka = mount sekali (baca CLOB
             dari prop rjNo di mount masing-masing), tutup = dihapus. Tidak ada event open-rm-* lagi. --}}
        @if ($rjNo)
        <div class="flex flex-col min-h-full" wire:key="{{ $this->renderKey('modal', [$rjNo ?? 'new']) }}">

            <x-modul-dokumen.header judul="Modul Dokumen"
                ikon="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                jalur="UGD" :readOnly="$isFormLocked">
                Formulir &amp; dokumen bertanda tangan pasien — consent, surat keterangan, laporan, dan pengkajian.
            </x-modul-dokumen.header>

            {{-- DISPLAY PASIEN — di bawah header, sama dengan modal modul dokumen --}}
            <div class="px-4 pt-2">
                <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                    wire:key="modul-dokumen-display-pasien-ugd-header-{{ $rjNo }}" />
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

                                    {{-- Form Transfer UGD → RI --}}
                                    <x-tab variant="underline" active-expr="activeTab === 'trf-ri'"
                                        x-on:click="activeTab = 'trf-ri'" class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                        </svg>
                                        Form Transfer UGD &rarr; RI
                                        @if ($adaTrfUgd)
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

                                    {{-- Form Penjaminan & Orientasi Kamar --}}
                                    <x-tab variant="underline" active-expr="activeTab === 'form-penjaminan'"
                                        x-on:click="activeTab = 'form-penjaminan'" class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Form Penjaminan & Orientasi Kamar
                                        @if ($jumlahPenjaminan > 0)
                                            <x-badge variant="success"
                                                class="text-[10px] px-1.5 py-0">{{ $jumlahPenjaminan }}</x-badge>
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

                                    {{-- Penolakan Pengobatan / Obat Tertentu --}}
                                    <x-tab variant="underline" active-expr="activeTab === 'penolakan-obat'"
                                        x-on:click="activeTab = 'penolakan-obat'"
                                        class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                        </svg>
                                        Penolakan Obat
                                        @if ($jumlahPenolakanObat > 0)
                                            <x-badge variant="success"
                                                class="text-[10px] px-1.5 py-0">{{ $jumlahPenolakanObat }}</x-badge>
                                        @endif
                                    </x-tab>

                                    <x-tab variant="underline" active-expr="activeTab === 'penolakan-resusitasi'"
                                        x-on:click="activeTab = 'penolakan-resusitasi'"
                                        class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 3l18 18" />
                                        </svg>
                                        Penolakan Resusitasi (DNR)
                                        @if ($jumlahPenolakanResusitasi > 0)
                                            <x-badge variant="success"
                                                class="text-[10px] px-1.5 py-0">{{ $jumlahPenolakanResusitasi }}</x-badge>
                                        @endif
                                    </x-tab>

                                    {{-- Permintaan Second Opinion --}}
                                    <x-tab variant="underline" active-expr="activeTab === 'secondOpinion'"
                                        x-on:click="activeTab = 'secondOpinion'"
                                        class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                        </svg>
                                        Second Opinion
                                        @if ($jumlahSecondOpinion > 0)
                                            <x-badge variant="success"
                                                class="text-[10px] px-1.5 py-0">{{ $jumlahSecondOpinion }}</x-badge>
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

                                    {{-- Kriteria Robson (klasifikasi 10 kelompok, WHO) --}}
                                    <x-tab variant="underline" active-expr="activeTab === 'kriteria-robson'"
                                        x-on:click="activeTab = 'kriteria-robson'"
                                        class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                        </svg>
                                        Kriteria Robson
                                        @if ($jumlahKriteriaRobson > 0)
                                            <x-badge variant="success"
                                                class="text-[10px] px-1.5 py-0">{{ $jumlahKriteriaRobson }}</x-badge>
                                        @endif
                                    </x-tab>

                                    {{-- Pelaporan Efek Samping Obat (RM 37) --}}
                                    <x-tab variant="underline" active-expr="activeTab === 'pelaporan-eso'"
                                        x-on:click="activeTab = 'pelaporan-eso'"
                                        class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                                        </svg>
                                        Pelaporan ESO
                                        @if ($jumlahEso > 0)
                                            <x-badge variant="success"
                                                class="text-[10px] px-1.5 py-0">{{ $jumlahEso }}</x-badge>
                                        @endif
                                    </x-tab>

                                    {{-- Pengkajian Akhir Hayat --}}
                                    <x-tab variant="underline" active-expr="activeTab === 'akhir-hayat'"
                                        x-on:click="activeTab = 'akhir-hayat'"
                                        class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                        </svg>
                                        Akhir Hayat
                                        @if ($jumlahAkhirHayat > 0)
                                            <x-badge variant="success"
                                                class="text-[10px] px-1.5 py-0">{{ $jumlahAkhirHayat }}</x-badge>
                                        @endif
                                    </x-tab>

                                    {{-- Surat Keterangan Kematian — tab hanya muncul bila Screening UGD
                                         menyimpulkan P0, supaya tak jadi tab permanen di tiap pasien. --}}
                                    @if ($triaseSaran === 'P0')
                                        <x-tab variant="underline" active-expr="activeTab === 'surat-kematian'"
                                            x-on:click="activeTab = 'surat-kematian'"
                                            class="inline-flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            Surat Kematian
                                            @if ($adaSuratKematianFinal)
                                                <x-badge variant="success" class="text-[10px] px-1.5 py-0">TTD</x-badge>
                                            @else
                                                <x-badge variant="danger" class="text-[10px] px-1.5 py-0">P0</x-badge>
                                            @endif
                                        </x-tab>
                                    @endif

                                </div>
                            </div>

                            {{-- Panel: Surat Keterangan --}}
                            <div x-show="activeTab === 'suket'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.ugd.emr-ugd.modul-dokumen.suket-ugd.rm-suket-ugd-actions
                                    :rjNo="$rjNo" wire:key="suket-ugd-{{ $rjNo }}" />
                            </div>

                            {{-- Panel: Form Transfer UGD → RI --}}
                            <div x-show="activeTab === 'trf-ri'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.ugd.emr-ugd.modul-dokumen.form-trf-ugd-ri.rm-form-trf-ugd-ri-actions
                                    :rjNo="$rjNo" wire:key="form-trf-ugd-ri-{{ $rjNo }}" />
                            </div>

                            {{-- Panel: General Consent --}}
                            <div x-show="activeTab === 'general-consent'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.ugd.emr-ugd.modul-dokumen.general-consent-ugd.rm-general-consent-ugd-actions
                                    :rjNo="$rjNo" :disabled="$isFormLocked"
                                    wire:key="general-consent-ugd-{{ $rjNo ?? 'init' }}" />
                            </div>

                            {{-- Panel: Inform Consent --}}
                            <div x-show="activeTab === 'inform-consent'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.ugd.emr-ugd.modul-dokumen.inform-consent-ugd.rm-inform-consent-ugd-actions
                                    :rjNo="$rjNo" :disabled="$isFormLocked"
                                    wire:key="inform-consent-ugd-{{ $rjNo ?? 'init' }}" />
                            </div>

                            {{-- Panel: Form Penjaminan & Orientasi Kamar --}}
                            <div x-show="activeTab === 'form-penjaminan'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.ugd.emr-ugd.modul-dokumen.form-penjaminan-ugd.rm-form-penjaminan-ugd-actions
                                    :rjNo="$rjNo" wire:key="form-penjaminan-ugd-{{ $rjNo }}" />
                            </div>

                            {{-- Panel: Penundaan Pelayanan --}}
                            <div x-show="activeTab === 'penundaan-pelayanan'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.ugd.emr-ugd.modul-dokumen.penundaan-pelayanan-ugd.rm-penundaan-pelayanan-ugd-actions
                                    :rjNo="$rjNo" :disabled="$isFormLocked"
                                    wire:key="penundaan-pelayanan-ugd-{{ $rjNo ?? 'init' }}" />
                            </div>

                            {{-- Panel: Penolakan Pengobatan / Obat Tertentu --}}
                            <div x-show="activeTab === 'penolakan-obat'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.ugd.emr-ugd.modul-dokumen.penolakan-obat-ugd.rm-penolakan-obat-ugd-actions
                                    :rjNo="$rjNo" :disabled="$isFormLocked"
                                    wire:key="penolakan-obat-ugd-{{ $rjNo ?? 'init' }}" />
                            </div>

                            {{-- TAB: PENOLAKAN TINDAKAN RESUSITASI (DNR) --}}
                            <div x-show="activeTab === 'penolakan-resusitasi'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.ugd.emr-ugd.modul-dokumen.penolakan-resusitasi-ugd.rm-penolakan-resusitasi-ugd-actions
                                    :rjNo="$rjNo" :disabled="$isFormLocked"
                                    wire:key="penolakan-resusitasi-ugd-{{ $rjNo ?? 'init' }}" />
                            </div>

                            {{-- Panel: Permintaan Second Opinion --}}
                            <div x-show="activeTab === 'secondOpinion'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.ugd.emr-ugd.modul-dokumen.second-opinion-ugd.rm-second-opinion-ugd-actions
                                    :rjNo="$rjNo" :disabled="$isFormLocked"
                                    wire:key="second-opinion-ugd-{{ $rjNo ?? 'init' }}" />
                            </div>

                            {{-- Panel: Pelayanan Bedah — 8 form operasi (pola sama dengan EMR RI). --}}
                            <div x-show="activeTab === 'pelayanan-bedah'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.ugd.emr-ugd.modul-dokumen.pelayanan-bedah-ugd.rm-pelayanan-bedah-ugd-actions
                                    :rjNo="$rjNo" :disabled="$isFormLocked"
                                    wire:key="pelayanan-bedah-ugd-{{ $rjNo ?? 'init' }}" />
                            </div>

                            {{-- Panel: Kriteria Robson --}}
                            <div x-show="activeTab === 'kriteria-robson'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.ugd.emr-ugd.modul-dokumen.kriteria-robson-ugd.rm-kriteria-robson-ugd-actions
                                    :rjNo="$rjNo" :disabled="$isFormLocked"
                                    wire:key="kriteria-robson-ugd-{{ $rjNo ?? 'init' }}" />
                            </div>

                            {{-- Panel: Pengkajian Akhir Hayat --}}
                            <div x-show="activeTab === 'pelaporan-eso'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.ugd.emr-ugd.modul-dokumen.pelaporan-eso-ugd.rm-pelaporan-eso-ugd-actions
                                    :rjNo="$rjNo" :disabled="$isFormLocked"
                                    wire:key="pelaporan-eso-ugd-{{ $rjNo ?? 'init' }}" />
                            </div>

                            <div x-show="activeTab === 'akhir-hayat'" x-transition.opacity.duration.300ms>
                                <livewire:pages::transaksi.ugd.emr-ugd.modul-dokumen.akhir-hayat-ugd.rm-akhir-hayat-ugd-actions
                                    :rjNo="$rjNo" :disabled="$isFormLocked"
                                    wire:key="akhir-hayat-ugd-{{ $rjNo ?? 'init' }}" />
                            </div>

                            {{-- Panel: Surat Keterangan Kematian --}}
                            @if ($triaseSaran === 'P0')
                                <div x-show="activeTab === 'surat-kematian'" x-transition.opacity.duration.300ms>
                                    <livewire:pages::transaksi.ugd.emr-ugd.modul-dokumen.surat-kematian-ugd.rm-surat-kematian-ugd-actions
                                        :rjNo="$rjNo" :disabled="$isFormLocked"
                                        wire:key="surat-kematian-ugd-{{ $rjNo ?? 'init' }}" />
                                </div>
                            @endif

                        </div>

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>
                    {{-- @if (!$isFormLocked)
                        <x-primary-button wire:click.prevent="save()" class="min-w-[120px]"
                            wire:loading.attr="disabled">
                            <span wire:loading.remove>
                                <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                                </svg>
                                Simpan
                            </span>
                            <span wire:loading><x-loading /> Menyimpan...</span>
                        </x-primary-button>
                    @endif --}}
                </div>
            </div>

        </div>
        @endif
    </x-modal>
</div>
