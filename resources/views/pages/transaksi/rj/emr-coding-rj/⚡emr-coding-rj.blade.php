<?php
// resources/views/pages/transaksi/rj/emr-coding-rj/emr-coding-rj.blade.php
//
// Modal GABUNGAN EMR RJ + Coding SATUSEHAT RJ dalam tab (pola halaman Kasir: <x-tabs> + @if per tab,
// komponen asli dipasang apa adanya) — keputusan user 2026-10-28, supaya tak buka-tutup dua modal.
//   tab rekamMedis → emr-rj          :embedded="true"  (body + footer aksi EMR utuh)
//   tab coding     → coding-fhir-rj  :embedded="true"  (hanya bila Gate emr.codingFhir)
// Modal, dirty guard, header & identitas pasien milik komponen ini. Dipasang di Pelayanan RJ menggantikan
// emr-rj & coding-fhir-rj berdiri sendiri; mendengar event lamanya:
//   'emr-rj.rekam-medis.open' (rjNo) → tab Rekam Medis   ·   'coding-fhir-rj.open' (rjNo) → tab Coding.
// Daftar RJ (tanpa EMR) tetap memakai modal coding-fhir-rj sendiri.
//
// Dirty guard: saveEvents mengikuti tab aktif; tanda "belum disimpan" hilang pada 'refresh-after-rj.saved'
// (dikirim seksi EMR & Simpan Coding). Pindah tab = simpan otomatis tab yang ditinggalkan; gagal (mis.
// validasi EMR) → tetap di tab itu. Tombol Tutup di footer anak memancarkan 'emr-rj-tutup' /
// 'coding-fhir-rj-tutup' → tryClose() dirty guard di sini.

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public const EVENT_SIMPAN_EMR = ['save-rm-anamnesa-rj', 'save-rm-pemeriksaan-rj', 'save-rm-diagnosa-rj', 'save-rm-perencanaan-rj'];

    public ?int $rjNo = null;

    /** 'rekamMedis' | 'coding' */
    public string $tabAktif = 'rekamMedis';

    public bool $bisaCoding = false;

    /** EMR terkunci → pindah tab tak perlu menyimpan EMR. */
    public bool $emrTerkunci = false;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-emr-coding-rj'];

    public function mount(): void
    {
        $this->registerAreas(['modal-emr-coding-rj']);
    }

    #[On('emr-rj.rekam-medis.open')]
    public function bukaRekamMedis(int $rjNo): void
    {
        $this->buka($rjNo, 'rekamMedis');
    }

    #[On('coding-fhir-rj.open')]
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
        // ulang; perilaku lama emr-rj (seksi Perencanaan dimuat ulang sendiri oleh E-Resep).
        if ($this->rjNo !== $rjNo || $this->tabAktif !== $tab) {
            $this->incrementVersion('modal-emr-coding-rj');
        }
        $this->rjNo = $rjNo;
        $this->tabAktif = $tab;
        $this->emrTerkunci = $this->checkEmrRJStatus($rjNo);
        $this->dispatch('open-modal', name: 'emr-coding-rj');
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
        $this->emrTerkunci = $this->checkEmrRJStatus($this->rjNo);
        $this->incrementVersion('modal-emr-coding-rj');
    }

    public function closeModal(): void
    {
        $this->rjNo = null;
        $this->tabAktif = 'rekamMedis';
        $this->dispatch('close-modal', name: 'emr-coding-rj');
    }
};
?>

<div>
    <x-modal name="emr-coding-rj" size="full" height="full" focusable>
        {{-- Isi hanya di-mount saat ada pasien: tertutup = nol komponen. --}}
        @if ($rjNo)
            @php
                $versi = $renderVersions['modal-emr-coding-rj'] ?? 0;
                $tabCoding = $tabAktif === 'coding';
            @endphp
            <x-dirty-modal-content name="emr-coding-rj" event="refresh-after-rj.saved"
                :label="$tabCoding ? 'Coding SATUSEHAT' : 'EMR Rawat Jalan'"
                :save-events="$tabCoding ? ['save-coding-fhir-rj'] : $this::EVENT_SIMPAN_EMR"
                :wireKey="'emr-coding-rj-' . $rjNo . '-' . $tabAktif . '-' . $versi">

                <x-modul-dokumen.header :judul="$tabCoding ? 'Coding SATUSEHAT' : 'Rekam Medis Rawat Jalan'" jalur="RJ"
                    :ikon="$tabCoding ? 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4' : 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'"
                    tutupAlpine="tryClose()" />

                {{-- DISPLAY PASIEN --}}
                <div class="px-6 pt-3">
                    <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                        wire:key="emr-coding-rj-display-pasien-{{ $rjNo }}-{{ $versi }}" />
                </div>

                {{-- TAB — pindah tab menyimpan otomatis tab yang ditinggalkan --}}
                @if ($bisaCoding)
                    <div class="flex items-center gap-2 px-6 mt-3" x-data="{
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
                                    const hasil = this.tunggu('refresh-after-rj.saved', eventSimpan.length);
                                    eventSimpan.forEach((namaEvent) => Livewire.dispatch(namaEvent, { silent: true }));
                                    aman = await hasil;
                                    if (!aman) Livewire.dispatch('toast', { type: 'error', message: 'Rekam Medis belum tersimpan — periksa isian yang ditandai merah, lalu pindah tab lagi.' });
                                } else if ($wire.tabAktif === 'coding') {
                                    const hasil = this.tunggu('coding-fhir-rj.tersimpan', 1);
                                    Livewire.dispatch('save-coding-fhir-rj', { rjNo: String($wire.rjNo), senyap: true });
                                    aman = await hasil;
                                }
                                if (aman) await $wire.pilihTab(tab);
                            } finally {
                                this.pindah = false;
                            }
                        },
                    }">
                        <x-tabs variant="underline">
                            <x-tab :active="!$tabCoding" color="emerald" x-on:click="pindahTab('rekamMedis')" x-bind:disabled="pindah">Rekam Medis Rawat Jalan</x-tab>
                            <x-tab :active="$tabCoding" color="emerald" x-on:click="pindahTab('coding')" x-bind:disabled="pindah">Coding SATUSEHAT</x-tab>
                        </x-tabs>
                        <span x-show="pindah" x-cloak class="flex items-center gap-1 text-sm text-muted dark:text-gray-400"><x-loading /> Menyimpan…</span>
                    </div>
                @endif

                {{-- Tombol Tutup di footer anak → dirty guard di sini --}}
                <div class="hidden" x-on:emr-rj-tutup.window="tryClose()" x-on:coding-fhir-rj-tutup.window="tryClose()"></div>

                @if ($tabCoding)
                    <livewire:pages::transaksi.rj.coding-fhir-rj.coding-fhir-rj :rjNo="(string) $rjNo" :embedded="true"
                        wire:key="emr-coding-rj-coding-{{ $rjNo }}-{{ $versi }}" />
                @else
                    <livewire:pages::transaksi.rj.emr-rj.emr-rj :rjNo="$rjNo" :embedded="true"
                        wire:key="emr-coding-rj-emr-{{ $rjNo }}-{{ $versi }}" />
                @endif
            </x-dirty-modal-content>
        @endif
    </x-modal>
</div>
