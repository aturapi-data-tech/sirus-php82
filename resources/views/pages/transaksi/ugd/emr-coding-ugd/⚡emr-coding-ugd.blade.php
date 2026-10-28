<?php
// resources/views/pages/transaksi/ugd/emr-coding-ugd/emr-coding-ugd.blade.php
//
// Modal GABUNGAN EMR UGD + Coding SATUSEHAT UGD dalam tab (pola halaman Kasir: <x-tabs> + @if per tab,
// komponen asli dipasang apa adanya) — pola emr-coding-rj, keputusan user 2026-10-28, supaya tak buka-tutup dua modal.
//   tab rekamMedis → emr-ugd         :embedded="true"  (body + footer aksi EMR utuh)
//   tab coding     → coding-fhir-ugd :embedded="true"  (hanya bila Gate emr.codingFhir)
// Modal, dirty guard, header & identitas pasien milik komponen ini. Dipasang di Pelayanan UGD menggantikan
// emr-ugd & coding-fhir-ugd berdiri sendiri; mendengar event lamanya:
//   'emr-ugd.rekam-medis.open' (rjNo) → tab Rekam Medis   ·   'coding-fhir-ugd.open' (rjNo) → tab Coding.
// Daftar UGD (tanpa EMR) tetap memakai modal coding-fhir-ugd sendiri.
//
// Dirty guard: saveEvents mengikuti tab aktif; tanda "belum disimpan" hilang pada 'refresh-after-ugd.saved'
// (dikirim seksi EMR & Simpan Coding). Pindah tab = simpan otomatis tab yang ditinggalkan; gagal (mis.
// validasi EMR) → tetap di tab itu. Tombol Tutup di footer anak memancarkan 'emr-ugd-tutup' /
// 'coding-fhir-ugd-tutup' → tryClose() dirty guard di sini.

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait;

    public const EVENT_SIMPAN_EMR = ['save-rm-anamnesa-ugd', 'save-rm-pemeriksaan-ugd', 'save-rm-diagnosa-ugd', 'save-rm-perencanaan-ugd'];

    public ?int $rjNo = null;

    /** 'rekamMedis' | 'coding' */
    public string $tabAktif = 'rekamMedis';

    public bool $bisaCoding = false;

    /** EMR terkunci → pindah tab tak perlu menyimpan EMR. */
    public bool $emrTerkunci = false;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-emr-coding-ugd'];

    public function mount(): void
    {
        $this->registerAreas(['modal-emr-coding-ugd']);
    }

    #[On('emr-ugd.rekam-medis.open')]
    public function bukaRekamMedis(int $rjNo): void
    {
        $this->buka($rjNo, 'rekamMedis');
    }

    #[On('coding-fhir-ugd.open')]
    public function bukaCoding(string $rjNo): void
    {
        if (!auth()->user()?->can('emr.codingFhir')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak membuka Coding SATUSEHAT.');
            return;
        }
        $this->buka((int) $rjNo, 'coding');
    }

    private function buka(int $rjNo, string $tab): void
    {
        $this->bisaCoding = (bool) auth()->user()?->can('emr.codingFhir');
        // Kunjungan & tab sama saat modal masih terbuka (mis. kembali dari E-Resep) → isi TIDAK di-mount
        // ulang; perilaku lama emr-ugd (seksi Perencanaan dimuat ulang sendiri oleh E-Resep).
        if ($this->rjNo !== $rjNo || $this->tabAktif !== $tab) {
            $this->incrementVersion('modal-emr-coding-ugd');
        }
        $this->rjNo = $rjNo;
        $this->tabAktif = $tab;
        $this->emrTerkunci = $this->checkEmrUGDStatus($rjNo);
        $this->dispatch('open-modal', name: 'emr-coding-ugd');
    }

    /** Dipanggil Alpine SESUDAH tab yang ditinggalkan tersimpan. Versi naik → isi tab baru membaca data segar. */
    public function pilihTab(string $tab): void
    {
        if (empty($this->rjNo) || !in_array($tab, ['rekamMedis', 'coding'], true)) {
            return;
        }
        if ($tab === 'coding' && !$this->bisaCoding) {
            return;
        }
        $this->tabAktif = $tab;
        $this->emrTerkunci = $this->checkEmrUGDStatus($this->rjNo);
        $this->incrementVersion('modal-emr-coding-ugd');
    }

    public function closeModal(): void
    {
        $this->rjNo = null;
        $this->tabAktif = 'rekamMedis';
        $this->dispatch('close-modal', name: 'emr-coding-ugd');
    }
};
?>

<div>
    <x-modal name="emr-coding-ugd" size="full" height="full" focusable>
        {{-- Isi hanya di-mount saat ada pasien: tertutup = nol komponen. --}}
        @if ($rjNo)
            @php
                $versi = $renderVersions['modal-emr-coding-ugd'] ?? 0;
                $tabCoding = $tabAktif === 'coding';
            @endphp
            <x-dirty-modal-content name="emr-coding-ugd" event="refresh-after-ugd.saved"
                :label="$tabCoding ? 'Coding SATUSEHAT' : 'EMR UGD'"
                :save-events="$tabCoding ? ['save-coding-fhir-ugd'] : $this::EVENT_SIMPAN_EMR"
                :wireKey="'emr-coding-ugd-' . $rjNo . '-' . $tabAktif . '-' . $versi">

                <x-modul-dokumen.header :judul="$tabCoding ? 'Coding SATUSEHAT' : 'Rekam Medis UGD'" jalur="UGD"
                    :ikon="$tabCoding ? 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4' : 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'"
                    tutupAlpine="tryClose()">
                    <x-slot:tab>
                    {{-- TAB di sebelah judul — pindah tab menyimpan otomatis tab yang ditinggalkan --}}
                    @if ($bisaCoding)
                        <div class="flex items-center gap-2" x-data="{
                            pindah: false,
                            // true bila `jumlah` event masuk sebelum batas waktu dan tak ada yang membalas berhasil:false.
                            tunggu(namaEvent, jumlah) {
                                return new Promise((resolve) => {
                                    let masuk = 0, berhasil = true, batas = null;
                                    const selesai = () => { window.removeEventListener(namaEvent, catat); clearTimeout(batas); resolve(berhasil && masuk >= jumlah); };
                                    const catat = (event) => {
                                        if (event.detail && event.detail.berhasil === false) berhasil = false;
                                        if (++masuk >= jumlah) selesai();
                                    };
                                    window.addEventListener(namaEvent, catat);
                                    batas = setTimeout(selesai, 4000);
                                });
                            },
                            async pindahTab(tab) {
                                if (this.pindah || tab === $wire.tabAktif) return;
                                this.pindah = true;
                                try {
                                    let aman = true;
                                    if ($wire.tabAktif === 'rekamMedis' && !$wire.emrTerkunci) {
                                        const eventSimpan = @js($this::EVENT_SIMPAN_EMR);
                                        const hasil = this.tunggu('refresh-after-ugd.saved', eventSimpan.length);
                                        eventSimpan.forEach((namaEvent) => Livewire.dispatch(namaEvent, { silent: true }));
                                        aman = await hasil;
                                        if (!aman) Livewire.dispatch('toast', { type: 'error', message: 'Rekam Medis belum tersimpan — periksa isian yang ditandai merah, lalu pindah tab lagi.' });
                                    } else if ($wire.tabAktif === 'coding') {
                                        const hasil = this.tunggu('coding-fhir-ugd.tersimpan', 1);
                                        Livewire.dispatch('save-coding-fhir-ugd', { rjNo: String($wire.rjNo), senyap: true });
                                        aman = await hasil;
                                    }
                                    if (aman) await $wire.pilihTab(tab);
                                } finally {
                                    this.pindah = false;
                                }
                            },
                        }">
                            {{-- Gaya tab standar EMR (underline, sama dengan tab Pengkajian / Status Psikologis) --}}
                            <div class="flex items-center gap-2">
                                <x-tab variant="underline" :active="!$tabCoding" x-on:click="pindahTab('rekamMedis')" x-bind:disabled="pindah">Rekam Medis UGD</x-tab>
                                <x-tab variant="underline" :active="$tabCoding" x-on:click="pindahTab('coding')" x-bind:disabled="pindah">Coding SATUSEHAT</x-tab>
                            </div>
                            <span x-show="pindah" x-cloak class="flex items-center gap-1 text-sm text-muted dark:text-gray-400"><x-loading /> Menyimpan…</span>
                        </div>
                    @endif
                    </x-slot:tab>
                </x-modul-dokumen.header>

                {{-- DISPLAY PASIEN --}}
                <div class="px-6 pt-3">
                    <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                        wire:key="emr-coding-ugd-display-pasien-{{ $rjNo }}-{{ $versi }}" />
                </div>


                {{-- Tombol Tutup di footer anak → dirty guard di sini --}}
                <div class="hidden" x-on:emr-ugd-tutup.window="tryClose()" x-on:coding-fhir-ugd-tutup.window="tryClose()"></div>

                @if ($tabCoding)
                    <livewire:pages::transaksi.ugd.coding-fhir-ugd.coding-fhir-ugd :rjNo="(string) $rjNo" :embedded="true"
                        wire:key="emr-coding-ugd-coding-{{ $rjNo }}-{{ $versi }}" />
                @else
                    <livewire:pages::transaksi.ugd.emr-ugd.emr-ugd :rjNo="$rjNo" :embedded="true"
                        wire:key="emr-coding-ugd-emr-{{ $rjNo }}-{{ $versi }}" />
                @endif
            </x-dirty-modal-content>
        @endif
    </x-modal>
</div>
