<?php
// resources/views/pages/transaksi/ri/emr-ri/emr-ri.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;

    /**
     * true = dipasang di tab "Rekam Medis Rawat Inap" modal gabungan emr-coding-ri: tanpa x-modal,
     * header & identitas pasien (milik pembungkus). Dirty guard per-tab (x-tabbed-dirty-modal-content)
     * TETAP di sini; Tutup → event 'emr-coding-ri.tutup' ke pembungkus.
     */
    public bool $embedded = false;
    /**
     * RINGKASAN kunjungan, bukan dokumennya. Induk EMR RI hanya perlu identitas pasien
     * dan jenis klaim; anak-anaknya memuat datanya sendiri dari prop riHdrNo.
     */
    public string $regNoPasien = '';
    public string $klaimId = '';

    public array $renderVersions = [];

    /** Dokumen dibaca sebagai variabel LOKAL; hanya irisan di bawah ini yang disimpan. */
    private function muatDariDokumen(array $data): void
    {
        $this->regNoPasien = (string) ($data['regNo'] ?? '');
        $this->klaimId = (string) ($data['klaimId'] ?? '');
    }
    protected array $renderAreas = ['modal-emr-ri'];

    public function mount(?string $riHdrNo = null, bool $embedded = false): void
    {
        $this->registerAreas(['modal-emr-ri']);
        $this->embedded = $embedded;
        if ($embedded && $riHdrNo) {
            $this->muatKunjungan($riHdrNo);
        }
    }

    /** Muat ringkasan kunjungan + status kunci. false = data tak ditemukan (toast sudah dikirim). */
    private function muatKunjungan(string $riHdrNo): bool
    {
        $this->resetForm();
        $this->riHdrNo = $riHdrNo;
        $this->resetValidation();

        $data = $this->findDataRI($riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.');
            return false;
        }
        // Dokumen dibaca sebagai variabel LOKAL, diperas jadi ringkasan, lalu dilepas.
        $this->muatDariDokumen($data);

        // Kunci klinis mengikuti kebijakan trait (sengaja longgar — audit log saja),
        // BUKAN inline ri_status; kunci finansial tetap via checkRIStatus() di administrasi.
        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo);

        return true;
    }

    /* ── Open ── */
    #[On('emr-ri.rekam-medis.open')]
    public function openRekamMedis(string $riHdrNo): void
    {
        // Mode embedded dibuka oleh pembungkus (emr-coding-ri), bukan event ini.
        if ($this->embedded) {
            return;
        }
        if (!$this->muatKunjungan($riHdrNo)) {
            return;
        }

        $this->dispatch('open-modal', name: 'rm-ri-actions');

        // Seksi anak memuat datanya sendiri di mount dari prop riHdrNo (isi modal dibungkus
        // @if($riHdrNo)), jadi tidak ada lagi pancaran open-rm-*-ri dari sini. Handler
        // #[On('open-rm-*-ri')] di anak tetap ada: dipakai reloadEvent tab sesudah simpan
        // dan pemanggil lain. SKDP: syarat BPJS-nya sudah di template (@if $isBPJSRi).
    }

    /* ── Close ── */
    public function closeModal(): void
    {
        // Embedded: modalnya milik pembungkus — tryClose() dirty guard di sini berakhir ke sana.
        if ($this->embedded) {
            $this->dispatch('emr-coding-ri.tutup');
            return;
        }
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rm-ri-actions');
    }

    /* ── Shortcuts ── */
    public function openAdministrasiPasien(string $riHdrNo): void
    {
        $this->dispatch('emr-ri.administrasi.open', riHdrNo: $riHdrNo);
    }
    public function openPindahKamar(string $riHdrNo): void
    {
        $this->dispatch('emr-ri.pindah-kamar.open', riHdrNo: $riHdrNo);
    }
    public function openModulDokumen(string $riHdrNo): void
    {
        $this->dispatch('emr-ri.modul-dokumen.open', riHdrNo: $riHdrNo);
    }
    public function openLogAktivitas(string $riHdrNo): void
    {
        $this->dispatch('emr-ri.log-aktivitas.open', riHdrNo: $riHdrNo);
    }
    public function openEresep(string $riHdrNo): void
    {
        $this->dispatch('emr-ri.eresep.open', riHdrNo: (int) $riHdrNo);
    }

    protected function resetForm(): void
    {
        $this->reset(['riHdrNo', 'regNoPasien', 'klaimId']);
        $this->resetVersion();
        $this->isFormLocked = false;
    }
};
?>

<div @class(['flex flex-col flex-1 min-h-0' => $embedded])>
    @if ($embedded)
        {{-- Mode embedded: modal, header & identitas pasien milik pembungkus emr-coding-ri --}}
        @if ($riHdrNo)
            @include('pages.transaksi.ri.emr-ri.emr-ri-isi')
        @endif
    @else
    <x-modal name="rm-ri-actions" size="full" height="full" focusable>
        {{-- Anak hanya di-mount saat ada pasien: tertutup = nol komponen, buka = mount sekali (tiap
             seksi baca CLOB dari prop riHdrNo di mount-nya), tutup = dihapus. Tidak ada event open-rm-* lagi. --}}
        @if ($riHdrNo)
        @include('pages.transaksi.ri.emr-ri.emr-ri-isi')
        @endif
    </x-modal>
    @endif

    {{-- Modal E-Resep RI --}}
    <livewire:pages::transaksi.ri.eresep-ri.eresep-ri wire:key="eresep-ri-modal-{{ $riHdrNo ?? 'new' }}" />

    {{-- Modal Resume Medis — editor TinyMCE + generate PDF (listen: resume-medis-ri.open) --}}
    <livewire:pages::components.rekam-medis.ri.resume-medis-ri.resume-medis-ri-actions
        wire:key="resume-medis-ri-actions" />

    {{-- Modal Ringkasan Pemulangan Pasien — form terstruktur perawat (listen: ringkasan-pulang-ri.open) --}}
    <livewire:pages::components.rekam-medis.ri.ringkasan-pulang-ri.ringkasan-pulang-ri-actions
        wire:key="ringkasan-pulang-ri-actions" />

    {{-- Rekam Medis Nasional SATUSEHAT (listen: rekam-medis-nasional.open { jalur, nomor }) --}}
    <livewire:pages::components.rekam-medis.rekam-medis-nasional.rekam-medis-nasional-actions
        wire:key="rekam-medis-nasional-actions" />
</div>
