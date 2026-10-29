<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\BPJS\iCareTrait;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait, WithRenderVersioningTrait, iCareTrait;

    // i-Care
    public string $icareUrlResponse = '';

    /* =======================
     | Open/Close i-Care
     * ======================= */
    public function openModalicare(): void
    {
        $this->dispatch('open-modal', name: 'icare-modal');
    }

    public function closeModalicare(): void
    {
        $this->icareUrlResponse = '';
        $this->dispatch('close-modal', name: 'icare-modal');
    }

    /* =======================
     | i-Care BPJS
     * ======================= */
    public function myiCare(string $noSep): void
    {
        if (!$noSep) {
            $this->dispatch('toast', type: 'error', message: 'Belum Terbit SEP.');
            return;
        }

        // Ambil nokartu BPJS dari master pasien
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
    // i-Care

    public bool $isFormLocked = false;
    public ?int $rjNo = null;

    /**
     * true = dipasang di tab "Rekam Medis Rawat Jalan" modal gabungan emr-coding-rj: tanpa x-modal,
     * dirty guard, header & identitas pasien (milik pembungkus); kunjungan dari prop rjNo di mount.
     */
    public bool $embedded = false;
    /**
     * RINGKASAN kunjungan, bukan dokumennya.
     *
     * Induk EMR RJ hanya perlu identitas pasien, SEP, dan tahu apakah e-resep sudah terisi —
     * bukan isi dokumen `datadaftarpolirj_json`. Properti publik ikut snapshot Livewire dan
     * dikirim bolak-balik tiap request; anak-anaknya memuat datanya sendiri dari prop rjNo.
     */
    public string $regNoPasien = '';
    public string $drId = '';
    public string $noSep = '';
    public bool $adaEresep = false;

    // renderVersions
    public array $renderVersions = [];

    /** Dokumen dibaca sebagai variabel LOKAL; hanya irisan di bawah ini yang disimpan. */
    private function muatDariDokumen(array $data): void
    {
        $this->regNoPasien = (string) ($data['regNo'] ?? '');
        $this->drId = (string) ($data['drId'] ?? '');
        $this->noSep = (string) ($data['sep']['noSep'] ?? '');
        $this->adaEresep = !empty($data['eresep']) || !empty($data['eresepRacikan']);
    }
    protected array $renderAreas = ['modal-emr-rj'];

    /* ===============================
     | OPEN REKAM MEDIS PERAWAT
     =============================== */
    #[On('emr-rj.rekam-medis.open')]
    public function openRekamMedisPerawat(int $rjNo): void
    {
        // Mode embedded dibuka oleh pembungkus (emr-coding-rj), bukan event ini. Event yang sama datang
        // lagi sesudah E-Resep disimpan: segarkan ringkasan (adaEresep → tombol Cetak E-Resep) tanpa
        // mount ulang seksi, karena pembungkus tidak menaikkan versi untuk kunjungan & tab yang sama.
        if ($this->embedded) {
            if ($rjNo === $this->rjNo && ($data = $this->findDataRJ($rjNo))) {
                $this->muatDariDokumen($data);
            }
            return;
        }
        if (!$this->muatKunjungan($rjNo)) {
            return;
        }

        $this->dispatch('open-modal', name: 'rm-perawat-actions');
        // Seksi S/O/A/P/N memuat datanya sendiri di mount dari prop rjNo (isi modal dibungkus
        // @if($rjNo)), jadi tidak perlu lagi memancarkan open-rm-*-rj — dulu tiap seksi membaca
        // CLOB yang sama sekali lagi sesudah di-mount ulang.
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rm-perawat-actions');
    }

    protected function resetForm(): void
    {
        $this->reset(['rjNo', 'regNoPasien', 'drId', 'noSep', 'adaEresep']);
        $this->resetVersion();
        $this->isFormLocked = false;
    }

    public function mount(?int $rjNo = null, bool $embedded = false)
    {
        $this->registerAreas(['modal-emr-rj']);
        $this->embedded = $embedded;
        if ($embedded && $rjNo) {
            $this->muatKunjungan($rjNo);
        }
    }

    /** Muat ringkasan kunjungan + status kunci. false = data tak ditemukan (toast sudah dikirim). */
    private function muatKunjungan(int $rjNo): bool
    {
        $this->resetForm();
        $this->rjNo = $rjNo;
        $this->resetValidation();

        $data = $this->findDataRJ($rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return false;
        }

        // Dokumen dibaca sebagai variabel LOKAL, diperas jadi ringkasan, lalu dilepas.
        $this->muatDariDokumen($data);
        $this->isFormLocked = $this->checkEmrRJStatus($rjNo);

        return true;
    }

    public function save()
    {
        $this->dispatch('save-rm-anamnesa-rj');
        $this->dispatch('save-rm-pemeriksaan-rj');
        $this->dispatch('save-rm-diagnosa-rj');
        $this->dispatch('save-rm-perencanaan-rj');
    }

    public function openAdministrasiPasien(string $rjNo): void
    {
        $this->dispatch('emr-rj.administrasi.open', rjNo: $rjNo);
    }

    public function openLogAktivitas(string $rjNo): void
    {
        $this->dispatch('emr-rj.log-aktivitas.open', rjNo: $rjNo);
    }

    public function openEresep(int $rjNo): void
    {
        if (!$rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor kunjungan tidak ditemukan.');
            return;
        }
        $this->dispatch('emr-rj.eresep.open', rjNo: $rjNo);
        // Anak non-racikan/racikan sudah findData() di mount saat eresep-rj di-mount ulang per rjNo;
        // open-eresep-*-rj di sini cuma bikin CLOB dibaca dua kali. Event itu tetap dipakai
        // rekam-medis-display / perencanaan untuk muat ulang sesudah copy resep.
    }

    public function cetakEresep(string $rjNo): void
    {
        if (!$rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor kunjungan tidak ditemukan.');
            return;
        }
        $this->dispatch('cetak-eresep-rj.open', rjNo: $rjNo);
    }

    public function hasEresep(): bool
    {
        return $this->adaEresep;
    }
};

?>

<div @class(['flex flex-col flex-1 min-h-0' => $embedded])>
    @if ($embedded)
        {{-- Mode embedded: modal, dirty guard, header & identitas pasien milik pembungkus emr-coding-rj --}}
        @if ($rjNo)
            <div class="flex flex-col flex-1 min-h-0">
                @include('pages.transaksi.rj.emr-rj.emr-rj-isi')
            </div>
        @endif
    @else
    <x-modal name="rm-perawat-actions" size="full" height="full" focusable>
        {{-- Anak hanya di-mount saat ada pasien: tertutup = nol komponen, buka = mount sekali (tiap seksi
             baca CLOB dari prop rjNo di mount-nya), tutup = dihapus. Tidak ada event open-rm-* lagi. --}}
        @if ($rjNo)
        <x-dirty-modal-content name="rm-perawat-actions" event="refresh-after-rj.saved" label="EMR Rawat Jalan"
            :save-events="[
                'save-rm-anamnesa-rj',
                'save-rm-pemeriksaan-rj',
                'save-rm-diagnosa-rj',
                'save-rm-perencanaan-rj',
            ]" :wireKey="$this->renderKey('modal-emr-rj', [$rjNo ?? 'new'])">

            {{-- HEADER --}}
            <x-modul-dokumen.header judul="Rekam Medis Rawat Jalan"
                ikon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                jalur="RJ" tutupAlpine="tryClose()" />

            {{-- DISPLAY PASIEN --}}
            <div class="px-6 pt-3">
                <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                    wire:key="emr-rj-display-pasien-rj-header-{{ $rjNo }}" />
            </div>

            @include('pages.transaksi.rj.emr-rj.emr-rj-isi')
            <div class="hidden" x-on:emr-rj-tutup.window="tryClose()"></div>
        </x-dirty-modal-content>
        @endif
    </x-modal>
    @endif

    {{-- Modal i-Care --}}
    <x-modal name="icare-modal" size="full" height="full" focusable padding="p-0">
        <div class="flex flex-col h-full">

            {{-- Header --}}
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

            {{-- Content --}}
            <div class="flex-1 min-h-0">
                @if ($icareUrlResponse)
                    <iframe src="{{ $icareUrlResponse }}" class="w-full h-full border-0"></iframe>
                @else
                    <p class="py-10 text-base text-center text-muted-soft">Memuat i-Care...</p>
                @endif
            </div>

            {{-- Footer --}}
            <div class="flex justify-end px-6 py-4 border-t border-hairline dark:border-gray-700 shrink-0">
                <x-secondary-button type="button" wire:click="closeModalicare">
                    Tutup
                </x-secondary-button>
            </div>

        </div>
    </x-modal>

    {{-- Cetak E-Resep PDF (headless: listen event cetak-eresep-rj.open) --}}
    <livewire:pages::components.rekam-medis.rj.cetak-eresep.cetak-eresep wire:key="cetak-eresep-rj-emr" />

    {{-- Screening RJ (x-modal, dibuka via tombol / dispatch open-rm-screening-rj) --}}
    <livewire:pages::transaksi.rj.emr-rj.screening.rm-screening-rj-actions :rjNo="$rjNo"
        wire:key="screening-rj-{{ $rjNo }}" />

    {{-- Rekam Medis Nasional SATUSEHAT (listen: rekam-medis-nasional.open { jalur, nomor }) --}}
    <livewire:pages::components.rekam-medis.rekam-medis-nasional.rekam-medis-nasional-actions
        wire:key="rekam-medis-nasional-actions" />
</div>
