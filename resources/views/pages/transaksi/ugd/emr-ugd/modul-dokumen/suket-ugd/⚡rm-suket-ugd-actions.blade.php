<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    /**
     * IRISAN dokumen: hanya cabang `suket`. Sekaligus MODEL FORM — partial tab
     * mengikat `wire:model.live="suket.suketIstirahat.*"` langsung ke sini.
     */
    public array $suket = [];

    /** Tanggal kunjungan — dipakai getDefaultSuket() menghitung opsi Hari Ini/Besok. */
    public ?string $rjDate = null;

    /** Penanda dokumen sudah dimuat lewat open(). rendering() mengisi $suket dengan
     *  default walau form belum pernah dibuka, jadi empty($suket) TIDAK sah jadi guard. */
    public bool $dokumenTermuat = false;

    // Tab aktif (Suket Sehat / Suket Istirahat) — di-entangle ke Alpine supaya
    // tidak balik ke default saat re-render (incrementVersion) sesudah Simpan.
    public string $suketActiveTab = 'Suket Sehat';

    public array $renderVersions = [];

    /** Dokumen dibaca sebagai variabel LOKAL; hanya irisan di bawah ini yang disimpan. */
    private function muatDariDokumen(array $data): void
    {
        $this->rjDate = $data['rjDate'] ?? null;
        $this->suket = $data['suket'] ?? $this->getDefaultSuket();
    }
    protected array $renderAreas = ['modal-suket-ugd'];

    /* ===============================
     | MOUNT
     =============================== */
    /**
     * rjNo datang lewat PROP dari induk modul dokumen (anak lahir di dalam @if($rjNo)),
     * bukan lagi lewat event open-rm-*: satu kali baca CLOB, tidak ada race urutan event.
     * Handler #[On] tetap dipertahankan untuk pemanggil dari luar modal.
     */
    public function mount(?int $rjNo = null): void
    {
        $this->registerAreas(['modal-suket-ugd']);

        if (filled($rjNo)) {
            $this->openSuket($rjNo);
        }
    }

    public function rendering(): void
    {
        $default = $this->getDefaultSuket();
        $this->suket = array_replace_recursive($default, $this->suket);
    }

    /* ===============================
     | OPEN
     =============================== */
    #[On('open-rm-suket-ugd')]
    public function openSuket($rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->rjNo = $rjNo;
        $this->resetForm();
        $this->resetValidation();

        $data = $this->findDataUGD($rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        // rjDate DULU: getDefaultSuket() membacanya untuk opsi Hari Ini/Besok.
        $this->muatDariDokumen($data);
        $this->dokumenTermuat = true;

        // Normalisasi data legacy:
        // - Regenerate mulaiIstirahatOptions ke struktur baru ([value, label])
        // - Strip suffix " (Hari Ini)"/" (Besok)" dari mulaiIstirahat agar Carbon parse aman
        $fresh = $this->getDefaultSuket();
        $this->suket['suketIstirahat']['mulaiIstirahatOptions']
            = $fresh['suketIstirahat']['mulaiIstirahatOptions'];
        $mulai = (string) ($this->suket['suketIstirahat']['mulaiIstirahat'] ?? '');
        $this->suket['suketIstirahat']['mulaiIstirahat']
            = trim(preg_replace('/\s*\(.+?\)\s*$/', '', $mulai)) ?: $fresh['suketIstirahat']['mulaiIstirahat'];

        $this->isFormLocked = $this->checkEmrUGDStatus($rjNo);
        $this->incrementVersion('modal-suket-ugd');
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'suket.suketIstirahat.suketIstirahatHari' => 'nullable|integer|min:1',
        ];
    }

    protected function messages(): array
    {
        return [
            'suket.suketIstirahat.suketIstirahatHari.integer' => ':attribute harus berupa angka.',
            'suket.suketIstirahat.suketIstirahatHari.min' => ':attribute minimal 1 hari.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'suket.suketIstirahat.suketIstirahatHari' => 'Jumlah Hari Istirahat',
        ];
    }

    /* ===============================
     | SAVE
     =============================== */
    #[On('save-rm-suket-ugd')]
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        $this->validate();

        try {
            DB::transaction(function () {
                // 1. Lock row dulu — cegah race condition update JSON bersamaan
                $this->lockUGDRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataUGD($this->rjNo) ?? [];

                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
                }

                // Tangkap status sebelum overwrite (untuk verb log Buat/Update)
                $isBaru = empty($data['suket']);

                // 3. Patch hanya key suket
                $data['suket'] = $this->suket;

                $this->updateJsonUGD($this->rjNo, $data);

                $this->appendAdminLogUGD((int) $this->rjNo, ($isBaru ? 'Buat' : 'Update') . ' Surat Keterangan UGD — mulai istirahat ' . ($data['suket']['suketIstirahat']['mulaiIstirahat'] ?? '-'), 'MR');
            });

            // 4. Notify + increment — di luar transaksi
            $this->afterSave('Surat Keterangan berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK
     =============================== */
    public function cetakSuketSehat(): void
    {
        // Event name UGD pakai suffix -ugd agar match listener di cetak-suket-sehat-ugd component
        // (listener cetak-suket-sehat.open tanpa suffix dipakai oleh RJ component).
        $this->dispatch('cetak-suket-sehat-ugd.open', rjNo: $this->rjNo);
    }

    public function cetakSuketSakit(): void
    {
        $this->dispatch('cetak-suket-sakit-ugd.open', rjNo: $this->rjNo);
    }

    /* ===============================
     | DEFAULT STRUCTURE
     =============================== */
    private function getDefaultSuket(): array
    {
        try {
            $rjDate = Carbon::createFromFormat('d/m/Y H:i:s', $this->rjDate ?? '');
        } catch (\Throwable) {
            $rjDate = Carbon::now(config('app.timezone'));
        }

        $hariIni = $rjDate->format('d/m/Y');
        $besok = $rjDate->copy()->addDay()->format('d/m/Y');

        return [
            'suketSehatTab' => 'Suket Sehat',
            'suketSehat' => ['suketSehat' => ''],

            'suketIstirahatTab' => 'Suket Istirahat',
            'suketIstirahat' => [
                'mulaiIstirahat' => $hariIni,
                // Options dipisah value (d/m/Y murni untuk Carbon::createFromFormat) dan label (tampilan)
                'mulaiIstirahatOptions' => [
                    ['value' => $hariIni, 'label' => "{$hariIni} (Hari Ini)"],
                    ['value' => $besok, 'label' => "{$besok} (Besok)"],
                ],
                'suketIstirahatHari' => '2',
                'suketIstirahat' => '',
            ],
        ];
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function afterSave(string $message): void
    {
        $this->incrementVersion('modal-suket-ugd');
        $this->dispatch('toast', type: 'success', message: $message);
        // Reset dirty state di EMR UGD parent (<x-dirty-modal-content>).
        $this->dispatch('refresh-after-ugd.saved');
    }

    public function openModal(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->resetValidation();
        $this->dispatch('open-modal', name: "rm-suket-ugd-{$this->rjNo}");
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: "rm-suket-ugd-{$this->rjNo}");
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->dokumenTermuat = false;
        $this->isFormLocked = false;
    }
};
?>

<div>
    {{-- RINGKASAN + TOMBOL (pola General Consent) --}}
    <x-modul-dokumen.kartu judul="Surat Keterangan"
        tombol="Buka Surat Keterangan"
        :nonaktif="!$rjNo">
        <x-slot:deskripsi>Surat Keterangan Sehat &amp; Surat Keterangan Istirahat (sakit) untuk pasien UGD.</x-slot:deskripsi>
    </x-modul-dokumen.kartu>

    {{-- MODAL FORM --}}
    <x-modal name="rm-suket-ugd-{{ $rjNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-full" wire:key="{{ $this->renderKey('modal-suket-ugd', [$rjNo ?? 'new']) }}">

            <x-modul-dokumen.header judul="Surat Keterangan"
                ikon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                Surat keterangan untuk keperluan pasien — keterangan sakit, sehat, atau keperluan lain.
            </x-modul-dokumen.header>

            {{-- DISPLAY PASIEN — paling atas, mengikuti pola EMR --}}
            <div class="px-4 pt-2">
                <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                    wire:key="suket-ugd-display-pasien-{{ $rjNo ?? 'init' }}" />
            </div>

            {{-- KONTEN (flex-1 → dorong footer sticky ke bawah, pola emr-ugd) --}}
            <div class="flex-1">

            {{-- Display Pasien (selaras General Consent) --}}
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-6 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                @if ($isFormLocked)
                    <x-modul-dokumen.banner jenis="terkunci" />
                @endif

                @if ($dokumenTermuat)
                    <div class="w-full">
                        <div x-data="{ activeTab: @entangle('suketActiveTab') }" class="w-full">

                            {{-- TAB NAVIGATION --}}
                            <x-scrollable-tabs class="w-full px-2 mb-2 border-b border-hairline dark:border-gray-700">
                                <div class="flex flex-nowrap w-full gap-2 -mb-px">

                                    <x-tab variant="underline"
                                        active-expr="activeTab === '{{ $suket['suketSehatTab'] ?? 'Suket Sehat' }}'"
                                        x-on:click="activeTab = '{{ $suket['suketSehatTab'] ?? 'Suket Sehat' }}'">
                                        {{ $suket['suketSehatTab'] ?? 'Suket Sehat' }}
                                    </x-tab>

                                    <x-tab variant="underline"
                                        active-expr="activeTab === '{{ $suket['suketIstirahatTab'] ?? 'Suket Istirahat' }}'"
                                        x-on:click="activeTab = '{{ $suket['suketIstirahatTab'] ?? 'Suket Istirahat' }}'">
                                        {{ $suket['suketIstirahatTab'] ?? 'Suket Istirahat' }}
                                    </x-tab>

                                </div>
                            </x-scrollable-tabs>

                            {{-- TAB CONTENTS --}}
                            <div class="w-full p-4">

                                @if (isset($suket['suketSehatTab']))
                                    <div class="w-full"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $suket['suketSehatTab'] ?? 'Suket Sehat' }}'">
                                        @include('pages.transaksi.ugd.emr-ugd.modul-dokumen.suket-ugd.tabs.suket-sehat-ugd-tab')
                                    </div>
                                @endif

                                @if (isset($suket['suketIstirahatTab']))
                                    <div class="w-full"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $suket['suketIstirahatTab'] ?? 'Suket Istirahat' }}'">
                                        @include('pages.transaksi.ugd.emr-ugd.modul-dokumen.suket-ugd.tabs.suket-istirahat-ugd-tab')
                                    </div>
                                @endif

                            </div>
                        </div>
                    </div>

                @endif

            </div>
        </div>
            </div>{{-- /konten flex-1 --}}

            {{-- ══ FOOTER STICKY (anak langsung modal-body → selalu terlihat) ══ --}}
            @if (!$isFormLocked)
                <div class="sticky bottom-0 z-10 px-6 py-3 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeModal" class="min-w-[120px] justify-center">
                            Tutup
                        </x-secondary-button>
                        <x-primary-button wire:click.prevent="save" wire:loading.attr="disabled"
                            wire:target="save" class="gap-2 min-w-[200px] justify-center">
                            <span wire:loading.remove wire:target="save">
                                <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                                </svg>
                                Simpan Surat Keterangan
                            </span>
                            <span wire:loading wire:target="save"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                        </x-primary-button>
                    </div>
                </div>
            @endif
    </div>
    </x-modal>

    {{-- Cetak components --}}
    <livewire:pages::components.modul-dokumen.ugd.suket-sakit.cetak-suket-sakit-ugd wire:key="cetak-suket-sakit-ugd" />
    <livewire:pages::components.modul-dokumen.ugd.suket-sehat.cetak-suket-sehat-ugd wire:key="cetak-suket-sehat-ugd" />
</div>
