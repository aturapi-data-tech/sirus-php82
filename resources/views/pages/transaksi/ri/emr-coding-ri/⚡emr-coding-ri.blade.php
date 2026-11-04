<?php
// resources/views/pages/transaksi/ri/emr-coding-ri/emr-coding-ri.blade.php
//
// Modal GABUNGAN EMR RI + Coding SATUSEHAT RI dalam tab (pola halaman Kasir: <x-tabs> + @if per tab,
// komponen asli dipasang apa adanya) — pola emr-coding-rj/ugd, keputusan user 2026-10-28.
//   tab rekamMedis → emr-ri          :embedded="true"
//   tab coding     → coding-fhir-ri  :embedded="true"  (hanya bila Gate emr.codingFhir)
// Dipasang di Daftar RI menggantikan emr-ri & coding-fhir-ri berdiri sendiri; mendengar event lamanya:
//   'emr-ri.rekam-medis.open' (riHdrNo) → tab Rekam Medis   ·   'coding-fhir-ri.open' (riHdrNo) → tab Coding.
//
// BEDA dengan RJ/UGD — dirty guard:
//   tab Rekam Medis: guard per-tab milik EMR RI sendiri (x-tabbed-dirty-modal-content di emr-ri-isi) tetap
//     dipakai; Tutup-nya berakhir ke 'emr-coding-ri.tutup' di sini. Sebelum pindah ke Coding, EMR diminta
//     menyimpan semua tab dirty ('emr-ri-pindah' → 'emr-ri-pindah-hasil').
//   tab Coding: x-dirty-modal-content di sini (event refresh-after-ri.saved, saveEvents save-coding-fhir-ri).
// Header Tutup: memancarkan 'emr-ri-tutup' / 'coding-fhir-ri-tutup' → tryClose() guard tab aktif.

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public ?string $riHdrNo = null;

    /** 'rekamMedis' | 'coding' */
    public string $tabAktif = 'rekamMedis';

    public bool $bisaCoding = false;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-emr-coding-ri'];

    public function mount(): void
    {
        $this->registerAreas(['modal-emr-coding-ri']);
    }

    #[On('emr-ri.rekam-medis.open')]
    public function bukaRekamMedis(string $riHdrNo): void
    {
        $this->buka($riHdrNo, 'rekamMedis');
    }

    #[On('coding-fhir-ri.open')]
    public function bukaCoding(string $riHdrNo): void
    {
        if (!auth()->user()?->can('emr.codingFhir')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak membuka Coding SATUSEHAT.');
            return;
        }
        $this->buka($riHdrNo, 'coding');
    }

    private function buka(string $riHdrNo, string $tab): void
    {
        $this->bisaCoding = (bool) auth()->user()?->can('emr.codingFhir');
        if ($this->riHdrNo !== $riHdrNo || $this->tabAktif !== $tab) {
            $this->incrementVersion('modal-emr-coding-ri');
        }
        $this->riHdrNo = $riHdrNo;
        $this->tabAktif = $tab;
        $this->dispatch('open-modal', name: 'emr-coding-ri');
    }

    /** Dipanggil Alpine SESUDAH tab yang ditinggalkan tersimpan. Versi naik → isi tab baru membaca data segar. */
    public function pilihTab(string $tab): void
    {
        if (empty($this->riHdrNo) || !in_array($tab, ['rekamMedis', 'coding'], true)) {
            return;
        }
        if ($tab === 'coding' && !$this->bisaCoding) {
            return;
        }
        $this->tabAktif = $tab;
        $this->incrementVersion('modal-emr-coding-ri');
    }

    #[On('emr-coding-ri.tutup')]
    public function closeModal(): void
    {
        $this->riHdrNo = null;
        $this->tabAktif = 'rekamMedis';
        $this->dispatch('close-modal', name: 'emr-coding-ri');
    }
};
?>

<div>
    <x-modal name="emr-coding-ri" size="full" height="full" focusable>
        {{-- Isi hanya di-mount saat ada pasien: tertutup = nol komponen. --}}
        @if ($riHdrNo)
            @php
                $versi = $renderVersions['modal-emr-coding-ri'] ?? 0;
                $tabCoding = $tabAktif === 'coding';
                $tutupHeader = $tabCoding ? "\$dispatch('coding-fhir-ri-tutup')" : "\$dispatch('emr-ri-tutup')";
            @endphp
            <div class="flex flex-col min-h-[calc(100vh-8rem)]" wire:key="emr-coding-ri-{{ $riHdrNo }}-{{ $versi }}">
                <x-modul-dokumen.header :judul="$tabCoding ? 'Coding SATUSEHAT' : 'Rekam Medis Rawat Inap'" jalur="RI"
                    :ikon="$tabCoding ? 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4' : 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'"
                    :tutupAlpine="$tutupHeader">
                    <x-slot:tab>
                    {{-- TAB di sebelah judul — pindah tab menyimpan otomatis tab yang ditinggalkan --}}
                    @if ($bisaCoding)
                        <div class="flex items-center gap-2" x-data="{
                            pindah: false,
                            // Detail event pertama yang masuk sebelum batas waktu; null = tak ada balasan.
                            tunggu(namaEvent) {
                                return new Promise((resolve) => {
                                    let batas = null;
                                    const catat = (event) => { window.removeEventListener(namaEvent, catat); clearTimeout(batas); resolve(event.detail ?? {}); };
                                    window.addEventListener(namaEvent, catat);
                                    batas = setTimeout(() => { window.removeEventListener(namaEvent, catat); resolve(null); }, 6000);
                                });
                            },
                            async pindahTab(tab) {
                                if (this.pindah || tab === $wire.tabAktif) return;
                                this.pindah = true;
                                try {
                                    let aman = true;
                                    if ($wire.tabAktif === 'rekamMedis') {
                                        const hasil = this.tunggu('emr-ri-pindah-hasil');
                                        window.dispatchEvent(new CustomEvent('emr-ri-pindah'));
                                        const detail = await hasil;
                                        aman = !!(detail && detail.berhasil);
                                        if (!aman) {
                                            const daftar = (detail && detail.tabBelumTersimpan || []).join(', ');
                                            Livewire.dispatch('toast', { type: 'error', message: 'Rekam Medis belum tersimpan' + (daftar ? ' (' + daftar + ')' : '') + ' — periksa isian yang ditandai merah, lalu pindah tab lagi.' });
                                        }
                                    } else {
                                        const hasil = this.tunggu('coding-fhir-ri.tersimpan');
                                        Livewire.dispatch('save-coding-fhir-ri', { riHdrNo: String($wire.riHdrNo), senyap: true });
                                        const detail = await hasil;
                                        aman = !!(detail && detail.berhasil);
                                    }
                                    if (aman) await $wire.pilihTab(tab);
                                } finally {
                                    this.pindah = false;
                                }
                            },
                        }">
                            {{-- Gaya tab standar EMR (underline, sama dengan tab Pengkajian / Status Psikologis) --}}
                            <div class="flex items-center gap-2">
                                <x-tab variant="underline" :active="!$tabCoding" x-on:click="pindahTab('rekamMedis')" x-bind:disabled="pindah">Rekam Medis Rawat Inap</x-tab>
                                <x-tab variant="underline" :active="$tabCoding" x-on:click="pindahTab('coding')" x-bind:disabled="pindah">Coding SATUSEHAT</x-tab>
                            </div>
                            <span x-show="pindah" x-cloak class="flex items-center gap-1 text-sm text-muted dark:text-gray-400"><x-loading /> Menyimpan…</span>
                        </div>
                    @endif
                    </x-slot:tab>
                </x-modul-dokumen.header>

                {{-- DISPLAY PASIEN --}}
                <div class="px-6 pt-3">
                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="emr-coding-ri-display-pasien-{{ $riHdrNo }}-{{ $versi }}" />
                </div>


                @if ($tabCoding)
                    <x-dirty-modal-content name="emr-coding-ri" event="refresh-after-ri.saved" label="Coding SATUSEHAT"
                        :save-events="['save-coding-fhir-ri']" wrapperClass="flex flex-col flex-1 min-h-0"
                        :wireKey="'emr-coding-ri-guard-coding-' . $riHdrNo . '-' . $versi">
                        <div class="hidden" x-on:coding-fhir-ri-tutup.window="tryClose()"></div>
                        <livewire:pages::transaksi.ri.coding-fhir-ri.coding-fhir-ri :riHdrNo="$riHdrNo" :embedded="true"
                            wire:key="emr-coding-ri-coding-{{ $riHdrNo }}-{{ $versi }}" />
                    </x-dirty-modal-content>
                @else
                    <livewire:pages::transaksi.ri.emr-ri.emr-ri :riHdrNo="$riHdrNo" :embedded="true"
                        wire:key="emr-coding-ri-emr-{{ $riHdrNo }}-{{ $versi }}" />
                @endif
            </div>
        @endif
    </x-modal>
</div>
