<?php
// Modal Rekonsiliasi Obat UGD — dibuka dari titik-3 Pelayanan UGD.
//
// PINTU KEDUA ke node yang sama dengan EMR UGD → Anamnesa → tab Rekonsiliasi Obat
// (anamnesa.rekonsiliasiObat). Ada supaya APOTEKER bisa mendata obat bawaan
// pasien tanpa membuka form Anamnesa yang bukan wewenangnya.
//
// Karena dua pintu menulis node yang sama, simpan di sini HANYA menambal
// sub-node rekonsiliasiObat pada data TERBARU di dalam lock — bukan menimpa
// seluruh node anamnesa — supaya isian perawat yang sedang berjalan tidak hilang.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
use App\Support\RekonsiliasiObat;

new class extends Component {
    use EmrUGDTrait, WithValidationToastTrait;

    public ?int $rjNo = null;
    public array $daftarObat = [];
    public bool $isFormLocked = false;

    // Entri form — bentuk $formEntry* seperti penilaian (formEntryNyeri, dst).
    public array $formEntryRekonsiliasi = [
        'namaObat' => '',
        'dosis' => '',
        'rute' => '',
        'dibawaRanap' => 'Tidak',
        'lanjutPulang' => 'Tidak',
    ];

    #[On('rekonsiliasi-obat-ugd.open')]
    public function open(int $rjNo): void
    {
        if (empty($rjNo) || !$this->bolehAkses()) {
            return;
        }

        $this->rjNo = $rjNo;
        $this->resetFormEntry();
        $this->resetValidation();

        $data = $this->findDataUGD($rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        $this->isFormLocked = $this->checkEmrUGDStatus($rjNo);
        $this->daftarObat = RekonsiliasiObat::normalkanDaftar(data_get($data, 'anamnesa.rekonsiliasiObat', []));

        $this->dispatch('open-modal', name: 'rekonsiliasi-obat-ugd');
    }

    public function addRekonsiliasiObat(): void
    {
        // validate() didahulukan supaya field yang kosong tetap ditandai merah
        // (guard/early-return sebelum validate bikin border error tak muncul).
        $this->validateWithToast(
            [
                'formEntryRekonsiliasi.namaObat' => ['required', 'string', 'max:200'],
                'formEntryRekonsiliasi.dosis' => ['required', 'string', 'max:100'],
                'formEntryRekonsiliasi.rute' => ['required', 'string'],
            ],
            [],
            [
                'formEntryRekonsiliasi.namaObat' => 'Nama Obat',
                'formEntryRekonsiliasi.dosis' => 'Dosis',
                'formEntryRekonsiliasi.rute' => 'Rute',
            ],
        );

        if (!$this->siapDiubah()) {
            return;
        }

        if (RekonsiliasiObat::sudahAda($this->daftarObat, $this->formEntryRekonsiliasi['namaObat'])) {
            $this->dispatch('toast', type: 'error', message: 'Obat sudah ada dalam daftar.');
            return;
        }

        $this->daftarObat[] = RekonsiliasiObat::barisBaru($this->formEntryRekonsiliasi['namaObat'], $this->formEntryRekonsiliasi['dosis'], $this->formEntryRekonsiliasi['rute'], $this->formEntryRekonsiliasi['dibawaRanap'], $this->formEntryRekonsiliasi['lanjutPulang']);

        $namaObat = $this->formEntryRekonsiliasi['namaObat'];
        $this->resetFormEntry();
        $this->simpan('Tambah Rekonsiliasi Obat UGD (Farmasi) — ' . $namaObat);
    }

    public function removeRekonsiliasiObat(int $index): void
    {
        if (!$this->siapDiubah() || !isset($this->daftarObat[$index])) {
            return;
        }

        $namaObat = $this->daftarObat[$index]['namaObat'] ?? '-';
        unset($this->daftarObat[$index]);
        $this->daftarObat = array_values($this->daftarObat);

        $this->simpan('Hapus Rekonsiliasi Obat UGD (Farmasi) — ' . $namaObat);
    }

    public function closeModal(): void
    {
        $this->rjNo = null;
        $this->daftarObat = [];
        $this->isFormLocked = false;
        $this->resetFormEntry();
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'rekonsiliasi-obat-ugd');
    }

    /**
     * Tambal HANYA sub-node rekonsiliasiObat pada data terbaru di dalam lock.
     * Perawat bisa sedang membuka Anamnesa untuk pasien yang sama; menimpa
     * seluruh node anamnesa dari sini akan menghapus isiannya.
     */
    private function simpan(string $logKeterangan): void
    {
        try {
            DB::transaction(function () use ($logKeterangan) {
                $this->lockUGDRow($this->rjNo);

                $fresh = $this->findDataUGD($this->rjNo);
                if (empty($fresh)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
                }

                data_set($fresh, 'anamnesa.rekonsiliasiObat', $this->daftarObat);
                $this->updateJsonUGD((int) $this->rjNo, $fresh);

                $this->appendAdminLogUGD((int) $this->rjNo, $logKeterangan, 'MR');
            });

            $this->dispatch('refresh-after-ugd.saved');
            $this->dispatch('toast', type: 'success', message: 'Rekonsiliasi obat tersimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /** Guard SERVER — guard blade saja bisa ditembus karena wire:click memanggil method publik. */
    private function bolehAkses(): bool
    {
        if (auth()->user()?->can('rekonsiliasi.obat')) {
            return true;
        }

        $this->dispatch('toast', type: 'error', message: 'Hanya Apoteker / Admin yang dapat mengisi Rekonsiliasi Obat dari layar ini.');

        return false;
    }

    private function siapDiubah(): bool
    {
        if (!$this->bolehAkses()) {
            return false;
        }

        if (empty($this->rjNo)) {
            return false;
        }

        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Kunjungan sudah selesai, form terkunci.');
            return false;
        }

        return true;
    }

    private function resetFormEntry(): void
    {
        $this->reset(['formEntryRekonsiliasi']);
    }
};
?>

<div>
    {{-- Ukuran & tema disamakan dengan modal EMR (rm-ugd-actions / rm-ri-actions):
         full/full, header bertitik + Display Pasien, body bertingkat abu, footer sendiri. --}}
    <x-modal name="rekonsiliasi-obat-ugd" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-4rem)]">

            {{-- ═══════════ HEADER ═══════════ --}}
            <div class="relative px-6 py-4 border-b border-hairline dark:border-gray-700 shrink-0">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10] pointer-events-none"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    {{-- Display Pasien — kartu identitas yang sama dgn header EMR.
                         Dirender hanya bila pasien sudah dipilih: mount()-nya membaca
                         CLOB, jangan dijalankan saat modal masih kosong. --}}
                    <div class="flex-1 min-w-0">
                        @if (filled($rjNo))
                            <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                                wire:key="rekonsiliasi-obat-ugd-display-pasien-{{ $rjNo }}" />
                        @endif
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeModal" class="shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- ═══════════ BODY ═══════════ --}}
            <div class="flex-1 px-4 py-4 text-base bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    @if ($isFormLocked)
                        <div
                            class="p-3 text-xs text-red-700 border rounded-xl bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-700 dark:text-red-300">
                            Kunjungan sudah selesai &mdash; daftar hanya bisa dilihat, tidak bisa diubah.
                        </div>
                    @endif

                    <x-border-form title="Rekonsiliasi Obat">
                        <div class="space-y-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300">UGD</span>
                                <span class="text-xs text-muted dark:text-gray-400">No. UGD: <span
                                        class="font-mono font-semibold">{{ $rjNo ?? '-' }}</span></span>
                                @if ($isFormLocked)
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                                                clip-rule="evenodd" />
                                        </svg>
                                        Terkunci
                                    </span>
                                @endif
                            </div>

                            <x-rekonsiliasi-obat.form :daftar-obat="$daftarObat" :is-form-locked="$isFormLocked"
                                :dibawa-ranap="$formEntryRekonsiliasi['dibawaRanap']"
                                :lanjut-pulang="$formEntryRekonsiliasi['lanjutPulang']"
                                :key-prefix="'rekonsiliasi-obat-ugd-' . ($rjNo ?? 'new')" />
                        </div>
                    </x-border-form>

                </div>
            </div>

            {{-- ═══════════ FOOTER ═══════════ --}}
            <div
                class="flex justify-end px-6 py-3 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700 shrink-0">
                <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
            </div>

        </div>
    </x-modal>
</div>
