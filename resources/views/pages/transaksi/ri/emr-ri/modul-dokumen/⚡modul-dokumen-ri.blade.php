<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/rm-modul-dokumen-ri-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    /**
     * RINGKASAN status dokumen, bukan isinya. Hub perlu tahu keadaan SEMUA dokumen untuk
     * menyalakan badge — cukup benar/salah dan hitungannya.
     */
    public bool $adaGeneralConsent = false;
    public bool $adaBedah = false;
    public bool $adaObstetriNeonatal = false;
    public bool $adaSurveilans = false;
    public bool $adaSuratKematianFinal = false;
    public string $statusPulang = '';
    public int $jumlahMpp = 0;
    public int $jumlahPenundaan = 0;
    public int $jumlahPenolakanObat = 0;
    public int $jumlahPenolakanResusitasi = 0;
    public int $jumlahPulangAps = 0;
    public int $jumlahSecondOpinion = 0;
    public int $jumlahKerohanian = 0;
    public int $jumlahAkhirHayat = 0;
    public int $jumlahEso = 0;
    public int $jumlahPermintaanDarah = 0;
    public int $jumlahInformConsent = 0;
    public int $jumlahEdukasi = 0;
    public int $jumlahPindahRuang = 0;

    /** Dokumen dibaca sebagai variabel LOKAL, diperas jadi penanda, lalu dilepas. */
    private function hitungRingkasan(array $data): void
    {
        $this->adaGeneralConsent = !empty($data['generalConsentPasienRI']['signature']);
        $this->adaSuratKematianFinal = !empty($data['suratKematianRI']['isFinal']);
        $this->statusPulang = (string) ($data['perencanaan']['tindakLanjut']['statusPulang'] ?? '');
        $this->jumlahMpp = count($data['formMPP']['formA'] ?? []) + count($data['formMPP']['formB'] ?? []);
        $this->jumlahPenundaan = count($data['penundaanPelayananRI'] ?? []);
        $this->jumlahPenolakanObat = count($data['penolakanObatRI'] ?? []);
        $this->jumlahPenolakanResusitasi = count($data['penolakanResusitasiRI'] ?? []);
        $this->jumlahPulangAps = count($data['pulangApsRI'] ?? []);
        $this->jumlahSecondOpinion = count($data['secondOpinionRI'] ?? []);
        $this->jumlahKerohanian = count($data['permintaanKerohanianRI'] ?? []);
        $this->jumlahAkhirHayat = count($data['pengkajianAkhirHayatRI'] ?? []);
        $this->jumlahEso = count($data['pelaporanEsoRI'] ?? []);
        $this->jumlahPermintaanDarah = count($data['permintaanDarahRI'] ?? []);
        $this->jumlahInformConsent = count($data['informConsentPasienRI'] ?? []);
        $this->jumlahEdukasi = count($data['edukasiPasienTerintegrasi'] ?? []);
        $this->jumlahPindahRuang = count($data['formPindahAntarRuangRI'] ?? []);
        $this->adaBedah = collect(['pengkajianPreOpRI', 'praAnestesiRI', 'praInduksiRI',
            'surgicalSafetyChecklistRI', 'laporanOperasiRI', 'laporanAnestesiRI',
            'pascaAnestesiRI', 'instruksiPascaBedahRI'])->contains(fn($k) => !empty($data[$k]));
        $this->adaObstetriNeonatal = collect(['pengkajianAwalObstetriRI', 'riwayatObstetriRI',
            'observasiPersalinanRI', 'laporanPersalinanRI', 'kriteriaRobsonRI', 'indikatorScRI', 'observasiNifasRI',
            'pengkajianAwalGinekologiRI', 'pengkajianAwalBayiRI', 'pengkajianNeonatalPerawatRI',
            'identifikasiBayiRI', 'catatanTerapiNeonatalRI'])->contains(fn($k) => !empty($data[$k]));
        $this->adaSurveilans = collect(['surveilansPlebitisRI', 'surveilansIskRI',
            'surveilansVapRI', 'surveilansHapRI', 'surveilansIloRI'])->contains(fn($k) => !empty($data[$k]));
    }

    /**
     * Tab yang langsung terbuka. Default General Consent (perilaku lama).
     * Pemanggil dari luar EMR boleh meminta tab tertentu — mis. modul Kamar
     * Operasi membuka langsung ke 'pelayananBedah' supaya petugas OK tidak
     * perlu menyusuri tab dulu.
     */
    public string $tabAwal = 'generalConsent';

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-modul-dokumen-ri'];

    /** Tab yang boleh diminta pemanggil lewat event open(). */
    private const TAB_BOLEH = ['generalConsent', 'informConsent', 'pelayananBedah', 'vkKebidanan', 'surveilansHais'];

    public function mount(): void
    {
        $this->registerAreas(['modal-modul-dokumen-ri']);
    }

    #[On('emr-ri.modul-dokumen.open')]
    public function open(string $riHdrNo, string $tab = 'generalConsent'): void
    {
        if (empty($riHdrNo)) {
            return;
        }

        $this->riHdrNo = $riHdrNo;
        $this->resetForm();
        $this->resetValidation();

        // SESUDAH resetForm — fungsi itu mengembalikan tabAwal ke default,
        // jadi menaruhnya di atas membuat permintaan tab pemanggil terhapus.
        // Whitelist: nilai tak dikenal jangan membuat modal terbuka tanpa tab aktif.
        $this->tabAwal = in_array($tab, self::TAB_BOLEH, true) ? $tab : 'generalConsent';

        $data = $this->findDataRI($riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->hitungRingkasan($data);
        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo);

        $this->incrementVersion('modal-modul-dokumen-ri');

        $this->dispatch('open-modal', name: 'modul-dokumen-ri'); // ← WAJIB ada ini
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        // Kosongkan penanda supaya guard if($riHdrNo) di template menghapus isi modal (anak ikut hilang).
        // Tidak di resetForm(): open() memanggil resetForm() SESUDAH mengisi riHdrNo.
        $this->riHdrNo = null;
        $this->dispatch('close-modal', name: 'modul-dokumen-ri');
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->tabAwal = 'generalConsent';
        $this->reset(['adaGeneralConsent', 'adaBedah', 'adaObstetriNeonatal', 'adaSurveilans', 'adaSuratKematianFinal', 'statusPulang', 'jumlahMpp', 'jumlahPenundaan', 'jumlahPenolakanObat', 'jumlahPenolakanResusitasi', 'jumlahPulangAps', 'jumlahSecondOpinion', 'jumlahKerohanian', 'jumlahAkhirHayat', 'jumlahEso', 'jumlahPermintaanDarah', 'jumlahInformConsent', 'jumlahEdukasi', 'jumlahPindahRuang']);
    }
};
?>

<div>
    <x-modal name="modul-dokumen-ri" size="full" height="full" focusable>
        {{-- Isi modal hanya di-mount saat ada pasien: tertutup = nol komponen, buka = mount sekali
             (anak memuat datanya dari prop), tutup = dihapus tanpa mount ulang. --}}
        @if ($riHdrNo)
        <div class="flex flex-col min-h-full"
            wire:key="{{ $this->renderKey('modal-modul-dokumen-ri', [$riHdrNo ?? 'new']) }}">

            <x-modul-dokumen.header judul="Modul Dokumen"
                ikon="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                jalur="RI" :readOnly="$isFormLocked">
                Formulir &amp; dokumen bertanda tangan pasien — consent, surat keterangan, laporan, dan pengkajian.
            </x-modul-dokumen.header>

            {{-- DISPLAY PASIEN — di bawah header, sama dengan modal modul dokumen --}}
            <div class="px-4 pt-2">
                <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                    wire:key="modul-dokumen-display-pasien-ri-header-{{ $riHdrNo }}" />
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft dark:bg-gray-950/20" x-data="{ activeTab: @js($tabAwal) }">

                {{-- TAB NAV --}}
                <div class="border-b border-hairline dark:border-gray-700 mb-4">
                    <div class="flex flex-wrap gap-2 -mb-px">

                        <x-tab variant="underline" active-expr="activeTab === 'generalConsent'"
                            x-on:click="activeTab = 'generalConsent'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                            General Consent
                            @if ($adaGeneralConsent)
                                <x-badge variant="success" class="text-[10px] px-1.5 py-0">&#10003;</x-badge>
                            @endif
                        </x-tab>

                        <x-tab variant="underline" active-expr="activeTab === 'penundaanPelayanan'"
                            x-on:click="activeTab = 'penundaanPelayanan'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Penundaan Pelayanan
                            @if ($jumlahPenundaan > 0)
                                <x-badge variant="success" class="text-[10px] px-1.5 py-0">{{ $jumlahPenundaan }}</x-badge>
                            @endif
                        </x-tab>

                        <x-tab variant="underline" active-expr="activeTab === 'penolakanObat'"
                            x-on:click="activeTab = 'penolakanObat'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                            Penolakan Obat
                            @if ($jumlahPenolakanObat > 0)
                                <x-badge variant="success" class="text-[10px] px-1.5 py-0">{{ $jumlahPenolakanObat }}</x-badge>
                            @endif
                        </x-tab>

                        <x-tab variant="underline" active-expr="activeTab === 'penolakanResusitasi'"
                            x-on:click="activeTab = 'penolakanResusitasi'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18" />
                            </svg>
                            Penolakan Resusitasi (DNR)
                            @if ($jumlahPenolakanResusitasi > 0)
                                <x-badge variant="success" class="text-[10px] px-1.5 py-0">{{ $jumlahPenolakanResusitasi }}</x-badge>
                            @endif
                        </x-tab>

                        <x-tab variant="underline" active-expr="activeTab === 'pulangAps'"
                            x-on:click="activeTab = 'pulangAps'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            Pulang APS
                            @if ($jumlahPulangAps > 0)
                                <x-badge variant="success" class="text-[10px] px-1.5 py-0">{{ $jumlahPulangAps }}</x-badge>
                            @endif
                        </x-tab>

                        <x-tab variant="underline" active-expr="activeTab === 'secondOpinion'"
                            x-on:click="activeTab = 'secondOpinion'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                            </svg>
                            Second Opinion
                            @if ($jumlahSecondOpinion > 0)
                                <x-badge variant="success" class="text-[10px] px-1.5 py-0">{{ $jumlahSecondOpinion }}</x-badge>
                            @endif
                        </x-tab>

                        {{-- Dikembalikan 2026-07-30: permintaan rohaniwan tidak selalu terkait
                             akhir hayat — pasien/keluarga bisa memintanya kapan saja selama
                             dirawat. Pengkajian Akhir Hayat tetap punya bagian spiritualnya
                             sendiri untuk konteks menjelang akhir hayat. --}}
                        <x-tab variant="underline" active-expr="activeTab === 'permintaanKerohanian'"
                            x-on:click="activeTab = 'permintaanKerohanian'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                            </svg>
                            Kerohanian
                            @if ($jumlahKerohanian > 0)
                                <x-badge variant="success" class="text-[10px] px-1.5 py-0">{{ $jumlahKerohanian }}</x-badge>
                            @endif
                        </x-tab>

                        <x-tab variant="underline" active-expr="activeTab === 'akhirHayat'"
                            x-on:click="activeTab = 'akhirHayat'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                            Akhir Hayat
                            @if ($jumlahAkhirHayat > 0)
                                <x-badge variant="success" class="text-[10px] px-1.5 py-0">{{ $jumlahAkhirHayat }}</x-badge>
                            @endif
                        </x-tab>

                        <x-tab variant="underline" active-expr="activeTab === 'pelaporanEso'"
                            x-on:click="activeTab = 'pelaporanEso'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                            </svg>
                            Pelaporan ESO
                            @if ($jumlahEso > 0)
                                <x-badge variant="success" class="text-[10px] px-1.5 py-0">{{ $jumlahEso }}</x-badge>
                            @endif
                        </x-tab>

                        <x-tab variant="underline" active-expr="activeTab === 'permintaanDarah'"
                            x-on:click="activeTab = 'permintaanDarah'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 3l5.5 6.5a5.5 5.5 0 11-11 0L12 3z" />
                            </svg>
                            Permintaan Darah
                            @if ($jumlahPermintaanDarah > 0)
                                <x-badge variant="success" class="text-[10px] px-1.5 py-0">{{ $jumlahPermintaanDarah }}</x-badge>
                            @endif
                        </x-tab>

                        <x-tab variant="underline" active-expr="activeTab === 'informConsent'"
                            x-on:click="activeTab = 'informConsent'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Inform Consent
                            @if ($jumlahInformConsent > 0)
                                <x-badge variant="success" class="text-[10px] px-1.5 py-0">{{ $jumlahInformConsent }}</x-badge>
                            @endif
                        </x-tab>

                        @can('ri.caseManager')
                            <x-tab variant="underline" active-expr="activeTab === 'caseManager'"
                                x-on:click="activeTab = 'caseManager'" class="inline-flex items-center gap-2">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Case Manager (MPP)
                                
                                @if ($jumlahMpp > 0)
                                    <x-badge variant="success" class="text-[10px] px-1.5 py-0">{{ $jumlahMpp }}</x-badge>
                                @endif
                            </x-tab>
                        @endcan

                        <x-tab variant="underline" active-expr="activeTab === 'edukasiTerintegrasi'"
                            x-on:click="activeTab = 'edukasiTerintegrasi'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            Edukasi Terintegrasi
                            @if ($jumlahEdukasi > 0)
                                <x-badge variant="success" class="text-[10px] px-1.5 py-0">{{ $jumlahEdukasi }}</x-badge>
                            @endif
                        </x-tab>

                        <x-tab variant="underline" active-expr="activeTab === 'pindahRuang'"
                            x-on:click="activeTab = 'pindahRuang'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                            Pindah Antar Ruang
                            @if ($jumlahPindahRuang > 0)
                                <x-badge variant="success" class="text-[10px] px-1.5 py-0">{{ $jumlahPindahRuang }}</x-badge>
                            @endif
                        </x-tab>

                        <x-tab variant="underline" active-expr="activeTab === 'pelayananBedah'"
                            x-on:click="activeTab = 'pelayananBedah'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Pelayanan Bedah
                            @if ($adaBedah)
                                <x-badge variant="success" class="text-[10px] px-1.5 py-0">&#10003;</x-badge>
                            @endif
                        </x-tab>

                        <x-tab variant="underline" active-expr="activeTab === 'vkKebidanan'"
                            x-on:click="activeTab = 'vkKebidanan'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                            VK / Kebidanan
                            @if ($adaObstetriNeonatal)
                                <x-badge variant="success" class="text-[10px] px-1.5 py-0">&#10003;</x-badge>
                            @endif
                        </x-tab>

                        <x-tab variant="underline" active-expr="activeTab === 'surveilansHais'"
                            x-on:click="activeTab = 'surveilansHais'" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                            </svg>
                            Surveilans HAIs
                            @if ($adaSurveilans)
                                <x-badge variant="success" class="text-[10px] px-1.5 py-0">&#10003;</x-badge>
                            @endif
                        </x-tab>

                        {{-- Surat Kematian — tab hanya muncul bila status pulang di Perencanaan
                             adalah Meninggal (statusPulang BPJS 4), supaya tak jadi tab permanen. --}}
                        @if ($statusPulang === '4')
                            <x-tab variant="underline" active-expr="activeTab === 'suratKematian'"
                                x-on:click="activeTab = 'suratKematian'" class="inline-flex items-center gap-2">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Surat Kematian
                                @if ($adaSuratKematianFinal)
                                    <x-badge variant="success" class="text-[10px] px-1.5 py-0">TTD</x-badge>
                                @else
                                    <x-badge variant="danger" class="text-[10px] px-1.5 py-0">!</x-badge>
                                @endif
                            </x-tab>
                        @endif

                    </div>
                </div>

                {{-- TAB: SURAT KEMATIAN --}}
                @if ($statusPulang === '4')
                    <div x-show="activeTab === 'suratKematian'" x-transition.opacity.duration.200ms
                        style="display:none">
                        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.surat-kematian-ri.rm-surat-kematian-ri-actions
                            :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                            wire:key="surat-kematian-ri-{{ $riHdrNo ?? 'init' }}" />
                    </div>
                @endif

                {{-- TAB: INFORM CONSENT --}}
                <div x-show="activeTab === 'informConsent'" x-transition.opacity.duration.200ms style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.inform-consent-ri.rm-inform-consent-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="inform-consent-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: GENERAL CONSENT --}}
                <div x-show="activeTab === 'generalConsent'" x-transition.opacity.duration.200ms>
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.general-consent-ri.rm-general-consent-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="general-consent-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: PENUNDAAN PELAYANAN --}}
                <div x-show="activeTab === 'penundaanPelayanan'" x-transition.opacity.duration.200ms
                    style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.penundaan-pelayanan-ri.rm-penundaan-pelayanan-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="penundaan-pelayanan-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: SECOND OPINION --}}
                <div x-show="activeTab === 'secondOpinion'" x-transition.opacity.duration.200ms
                    style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.second-opinion-ri.rm-second-opinion-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="second-opinion-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: PENGKAJIAN PASIEN MENJELANG AKHIR HAYAT (RM.RI.62) --}}
                <div x-show="activeTab === 'akhirHayat'" x-transition.opacity.duration.200ms style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.akhir-hayat-ri.rm-akhir-hayat-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="akhir-hayat-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: PELAPORAN EFEK SAMPING OBAT (RM 37) --}}
                <div x-show="activeTab === 'pelaporanEso'" x-transition.opacity.duration.200ms style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.pelaporan-eso-ri.rm-pelaporan-eso-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="pelaporan-eso-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: PERMINTAAN KEROHANIAN --}}
                <div x-show="activeTab === 'permintaanKerohanian'" x-transition.opacity.duration.200ms
                    style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.permintaan-kerohanian-ri.rm-permintaan-kerohanian-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="permintaan-kerohanian-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: PENOLAKAN PENGOBATAN / OBAT TERTENTU --}}
                <div x-show="activeTab === 'penolakanObat'" x-transition.opacity.duration.200ms
                    style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.penolakan-obat-ri.rm-penolakan-obat-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="penolakan-obat-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: PENOLAKAN TINDAKAN RESUSITASI (DNR) --}}
                <div x-show="activeTab === 'penolakanResusitasi'" x-transition.opacity.duration.200ms
                    style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.penolakan-resusitasi-ri.rm-penolakan-resusitasi-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="penolakan-resusitasi-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: PULANG ATAS PERMINTAAN SENDIRI (APS) --}}
                <div x-show="activeTab === 'pulangAps'" x-transition.opacity.duration.200ms
                    style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.pulang-aps-ri.rm-pulang-aps-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="pulang-aps-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: FORMULIR PERMINTAAN DARAH (transfusi) --}}
                <div x-show="activeTab === 'permintaanDarah'" x-transition.opacity.duration.200ms style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.permintaan-darah-ri.rm-permintaan-darah-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="permintaan-darah-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: CASE MANAGER --}}
                @can('ri.caseManager')
                    <div x-show="activeTab === 'caseManager'" x-transition.opacity.duration.200ms style="display:none">
                        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.case-manager-ri.rm-case-manager-ri-actions
                            :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                            wire:key="case-manager-ri-{{ $riHdrNo ?? 'init' }}" />
                    </div>
                @endcan

                {{-- TAB: EDUKASI TERINTEGRASI (gabungan — termasuk entri form Edukasi Pasien lama) --}}
                <div x-show="activeTab === 'edukasiTerintegrasi'" x-transition.opacity.duration.200ms style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.edukasi-terintegrasi-ri.rm-edukasi-terintegrasi-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="edukasi-terintegrasi-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: PINDAH ANTAR RUANG --}}
                <div x-show="activeTab === 'pindahRuang'" x-transition.opacity.duration.200ms
                    style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.form-pindah-antar-ruang-ri.rm-form-pindah-antar-ruang-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="form-pindah-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: PELAYANAN BEDAH (PAB) --}}
                <div x-show="activeTab === 'pelayananBedah'" x-transition.opacity.duration.200ms
                    style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.pelayanan-bedah-ri.rm-pelayanan-bedah-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="pelayanan-bedah-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: SURVEILANS HAIs (umbrella sub-tab per jenis infeksi) --}}
                <div x-show="activeTab === 'surveilansHais'" x-transition.opacity.duration.200ms style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.surveilans-hais-ri.rm-surveilans-hais-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="surveilans-hais-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

                {{-- TAB: VK / KEBIDANAN (umbrella sub-tab semua dokumen kebidanan) --}}
                <div x-show="activeTab === 'vkKebidanan'" x-transition.opacity.duration.200ms style="display:none">
                    <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.vk-kebidanan-ri.rm-vk-kebidanan-ri-actions
                        :riHdrNo="$riHdrNo" :disabled="$isFormLocked"
                        wire:key="vk-kebidanan-ri-{{ $riHdrNo ?? 'init' }}" />
                </div>

            </div>{{-- end body --}}

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>
                </div>
            </div>

        </div>
        @endif
    </x-modal>
</div>
