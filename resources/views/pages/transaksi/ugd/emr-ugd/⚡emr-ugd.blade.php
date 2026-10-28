<?php
// resources/views/pages/transaksi/ugd/emr-ugd/emr-ugd.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\BPJS\iCareTrait;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, WithRenderVersioningTrait, iCareTrait;

    // i-Care
    public string $icareUrlResponse = '';

    public bool $isFormLocked = false;
    public ?int $rjNo = null;

    /**
     * true = dipasang di tab "Rekam Medis UGD" modal gabungan emr-coding-ugd: tanpa x-modal,
     * dirty guard, header & identitas pasien (milik pembungkus); kunjungan dari prop rjNo di mount.
     */
    public bool $embedded = false;
    /**
     * RINGKASAN kunjungan, bukan dokumennya. Induk EMR UGD hanya perlu identitas pasien,
     * SEP, dan tahu apakah e-resep sudah terisi; anak-anaknya memuat datanya sendiri
     * dari prop rjNo.
     */
    public string $regNoPasien = '';
    public string $drId = '';
    public string $noSep = '';
    public bool $adaEresep = false;

    public array $renderVersions = [];

    /** Dokumen dibaca sebagai variabel LOKAL; hanya irisan di bawah ini yang disimpan. */
    private function muatDariDokumen(array $data): void
    {
        $this->regNoPasien = (string) ($data['regNo'] ?? '');
        $this->drId = (string) ($data['drId'] ?? '');
        $this->noSep = (string) ($data['sep']['noSep'] ?? '');
        $this->adaEresep = !empty($data['eresep']) || !empty($data['eresepRacikan']);
    }
    protected array $renderAreas = ['modal-emr-ugd'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?int $rjNo = null, bool $embedded = false): void
    {
        $this->registerAreas(['modal-emr-ugd']);
        $this->embedded = $embedded;
        if ($embedded && $rjNo) {
            $this->muatKunjungan($rjNo);
        }
    }

    /* ===============================
     | i-Care — Open/Close
     =============================== */
    public function openModalicare(): void
    {
        $this->dispatch('open-modal', name: 'icare-modal-ugd');
    }

    public function closeModalicare(): void
    {
        $this->icareUrlResponse = '';
        $this->dispatch('close-modal', name: 'icare-modal-ugd');
    }

    public function myiCare(string $noSep): void
    {
        if (!$noSep) {
            $this->dispatch('toast', type: 'error', message: 'Belum Terbit SEP.');
            return;
        }

        $regNo = $this->regNoPasien;
        $dataMasterPasien = $this->findDataMasterPasien($regNo);
        $nokartuBpjs = $dataMasterPasien['pasien']['identitas']['idbpjs'] ?? '';

        if (!$nokartuBpjs) {
            $this->dispatch('toast', type: 'error', message: 'Nomor kartu BPJS tidak ditemukan.');
            return;
        }

        $drId = $this->drId;
        if (!$drId) {
            $this->dispatch('toast', type: 'error', message: 'Data dokter tidak ditemukan.');
            return;
        }

        $kodeDokter = DB::table('rsmst_doctors')->select('kd_dr_bpjs')->where('dr_id', $drId)->first();

        if (!$kodeDokter || !$kodeDokter->kd_dr_bpjs) {
            $this->dispatch('toast', type: 'error', message: 'Dokter tidak memiliki hak akses untuk I-Care.');
            return;
        }

        try {
            $response = $this->icare($nokartuBpjs, $kodeDokter->kd_dr_bpjs)->getOriginalContent();

            if (($response['metadata']['code'] ?? 400) == 200) {
                $this->icareUrlResponse = $response['response']['url'] ?? '';
                $this->openModalicare();
            } else {
                $this->dispatch('toast', type: 'error', message: $response['metadata']['message'] ?? 'Gagal mengakses i-Care');
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengakses i-Care: ' . $e->getMessage());
        }
    }

    /* ===============================
     | OPEN REKAM MEDIS UGD
     =============================== */
    #[On('emr-ugd.rekam-medis.open')]
    public function openRekamMedis(int $rjNo): void
    {
        // Mode embedded dibuka oleh pembungkus (emr-coding-ugd), bukan event ini.
        if ($this->embedded) {
            return;
        }
        if (!$this->muatKunjungan($rjNo)) {
            return;
        }

        $this->dispatch('open-modal', name: 'rm-ugd-actions');
        // Seksi EMR memuat datanya sendiri di mount dari prop rjNo (isi modal dibungkus
        // @if($rjNo)), jadi tidak perlu lagi memancarkan open-rm-*-ugd: satu kali baca CLOB
        // per seksi, tidak ada race urutan event. Handler #[On] di seksi tetap ada.
    }

    /** Muat ringkasan kunjungan + status kunci. false = data tak ditemukan (toast sudah dikirim). */
    private function muatKunjungan(int $rjNo): bool
    {
        $this->resetForm();
        $this->rjNo = $rjNo;
        $this->resetValidation();

        $data = $this->findDataUGD($rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return false;
        }

        // Dokumen dibaca sebagai variabel LOKAL, diperas jadi ringkasan, lalu dilepas.
        $this->muatDariDokumen($data);
        $this->isFormLocked = $this->checkEmrUGDStatus($rjNo);

        return true;
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rm-ugd-actions');
    }

    /* ===============================
     | ADMINISTRASI
     =============================== */
    public function openAdministrasiPasien(int $rjNo): void
    {
        $this->dispatch('emr-ugd.administrasi.open', rjNo: $rjNo);
    }

    public function openLogAktivitas(int $rjNo): void
    {
        $this->dispatch('emr-ugd.log-aktivitas.open', rjNo: $rjNo);
    }

    public function openEresep(int $rjNo): void
    {
        if (!$rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor kunjungan tidak ditemukan.');
            return;
        }
        // Anak non-racikan/racikan memuat dari prop rjNo di mount (isi modal e-resep dibungkus
        // @if($rjNo) dan key-nya ikut versi 'modal'), jadi open-eresep-*-ugd tidak dipancarkan dari sini.
        $this->dispatch('emr-ugd.eresep.open', rjNo: $rjNo);
    }

    public function cetakEresep(string $rjNo): void
    {
        if (!$rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor kunjungan tidak ditemukan.');
            return;
        }
        $this->dispatch('cetak-eresep-ugd.open', rjNo: $rjNo);
    }

    public function hasEresep(): bool
    {
        return $this->adaEresep;
    }

    /* ===============================
     | SAVE — dispatch ke semua child
     =============================== */
    public function save(): void
    {
        $this->dispatch('save-rm-anamnesa-ugd');
        $this->dispatch('save-rm-pemeriksaan-ugd');
        $this->dispatch('save-rm-diagnosa-ugd');
        $this->dispatch('save-rm-perencanaan-ugd');
        // Observasi & Obat-Cairan: save per-item (add/remove), tidak perlu global save
    }

    /* ===============================
     | HELPERS
     =============================== */
    protected function resetForm(): void
    {
        $this->reset(['rjNo', 'regNoPasien', 'drId', 'noSep', 'adaEresep']);
        $this->resetVersion();
        $this->isFormLocked = false;
    }
};
?>

<div @class(['flex flex-col flex-1 min-h-0' => $embedded])>
    @if ($embedded)
        {{-- Mode embedded: modal, dirty guard, header & identitas pasien milik pembungkus emr-coding-ugd --}}
        @if ($rjNo)
            <div class="flex flex-col flex-1 min-h-0">
                @include('pages.transaksi.ugd.emr-ugd.emr-ugd-isi')
            </div>
        @endif
    @else
    <x-modal name="rm-ugd-actions" size="full" height="full" focusable>
        {{-- Anak hanya di-mount saat ada pasien: tertutup = nol komponen, buka = mount sekali (tiap seksi
             baca CLOB dari prop rjNo di mount-nya), tutup = dihapus. Tidak ada event open-rm-* lagi. --}}
        @if ($rjNo)
        <x-dirty-modal-content name="rm-ugd-actions" event="refresh-after-ugd.saved" label="EMR UGD" :save-events="[
            'save-rm-anamnesa-ugd',
            'save-rm-pemeriksaan-ugd',
            'save-rm-diagnosa-ugd',
            'save-rm-perencanaan-ugd',
        ]"
            :wireKey="$this->renderKey('modal-emr-ugd', [$rjNo ?? 'new'])">

            {{-- ═══════════ HEADER ═══════════ --}}
            <x-modul-dokumen.header judul="Rekam Medis UGD"
                ikon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                jalur="UGD" tutupAlpine="tryClose()" />

            {{-- DISPLAY PASIEN --}}
            <div class="px-6 pt-3">
                <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                    wire:key="emr-ugd-display-pasien-ugd-header-{{ $rjNo }}" />
            </div>

            @include('pages.transaksi.ugd.emr-ugd.emr-ugd-isi')
            <div class="hidden" x-on:emr-ugd-tutup.window="tryClose()"></div>
        </x-dirty-modal-content>
        @endif
    </x-modal>
    @endif

    {{-- Modal i-Care --}}
    <x-modal name="icare-modal-ugd" size="full" height="full" focusable padding="p-0">
        <div class="flex flex-col h-full">

            <div
                class="flex items-center justify-between px-6 py-4 border-b border-hairline dark:border-gray-700 shrink-0">
                <h3 class="text-lg font-semibold text-ink dark:text-gray-100">i-Care BPJS</h3>
                <x-icon-button color="gray" type="button" wire:click="closeModalicare">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </x-icon-button>
            </div>

            <div class="flex-1 min-h-0">
                @if ($icareUrlResponse)
                    <iframe src="{{ $icareUrlResponse }}" class="w-full h-full border-0"></iframe>
                @else
                    <p class="py-10 text-base text-center text-muted-soft">Memuat i-Care...</p>
                @endif
            </div>

            <div class="flex justify-end px-6 py-4 border-t border-hairline dark:border-gray-700 shrink-0">
                <x-secondary-button type="button" wire:click="closeModalicare">Tutup</x-secondary-button>
            </div>

        </div>
    </x-modal>

    {{-- Cetak E-Resep PDF (headless: listen event cetak-eresep-ugd.open) --}}
    <livewire:pages::components.rekam-medis.ugd.cetak-eresep.cetak-eresep wire:key="cetak-eresep-ugd-emr" />

    {{-- Rekam Medis Nasional SATUSEHAT (listen: rekam-medis-nasional.open { jalur, nomor }) --}}
    <livewire:pages::components.rekam-medis.rekam-medis-nasional.rekam-medis-nasional-actions
        wire:key="rekam-medis-nasional-actions" />
</div>
