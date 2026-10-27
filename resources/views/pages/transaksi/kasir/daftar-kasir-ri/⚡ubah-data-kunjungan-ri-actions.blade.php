<?php
// resources/views/pages/transaksi/kasir/daftar-kasir-ri/ubah-data-kunjungan-ri-actions.blade.php
//
// Komponen TERSENDIRI untuk kasir RI: mengoreksi KLAIM pendaftaran rawat inap selama pasien
// masih dirawat (ri_status 'I'). Padanan ubah-data-kunjungan-{rj,ugd}-actions, tetapi HANYA
// klaim — koreksi tanggal masuk/shift RI jarang, dan tanggal masuk terikat tanggal mulai kamar
// pertama (rsmst_trfrooms) yang mengalikan biaya kamar.
// Dibuka dari menu titik-3 Daftar Kasir RI (seperti RJ/UGD: hanya di kasir): dispatch 'open-ubah-data-kunjungan-ri'.
//
// Pasien SENGAJA tidak bisa diganti (EMR & biaya melekat ke pendaftaran). SEP tidak dibuat dari
// sini: bila klaim diganti ke BPJS, SEP dibuat lewat Pendaftaran Ubah (Mr/Admin).

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public ?int $riHdrNo = null;

    public string $klaimId = 'UM';

    /** Klaim saat modal dibuka — hanya untuk memunculkan peringatan bila klaim diganti. */
    public string $klaimTersimpan = 'UM';

    public array $klaimOptions = [['klaimId' => 'UM', 'klaimDesc' => 'UMUM'], ['klaimId' => 'JM', 'klaimDesc' => 'BPJS'], ['klaimId' => 'JR', 'klaimDesc' => 'JASA RAHARJA'], ['klaimId' => 'JML', 'klaimDesc' => 'Asuransi Lain'], ['klaimId' => 'KR', 'klaimDesc' => 'Kronis']];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-ubah-data-kunjungan-ri'];

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
    }

    /* ===============================
     | OPEN — dipicu dari menu titik-3 Daftar Kasir RI
     =============================== */
    #[On('open-ubah-data-kunjungan-ri')]
    public function openUbahDataKunjungan(int $riHdrNo): void
    {
        if (empty($riHdrNo)) {
            return;
        }

        if (!auth()->user()?->can('ri.ubahDataKunjungan')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak mengubah data kunjungan.');
            return;
        }

        if ($this->checkRIStatus($riHdrNo)) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah tidak dirawat, data tidak bisa diubah.');
            return;
        }

        $klaimId = DB::table('rstxn_rihdrs')->where('rihdr_no', $riHdrNo)->value('klaim_id');
        if ($klaimId === null && !DB::table('rstxn_rihdrs')->where('rihdr_no', $riHdrNo)->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.');
            return;
        }

        $this->resetValidation();
        $this->riHdrNo = $riHdrNo;
        $this->klaimId = (string) ($klaimId ?: 'UM');
        $this->klaimTersimpan = $this->klaimId;

        $this->incrementVersion('modal-ubah-data-kunjungan-ri');
        $this->dispatch('open-modal', name: 'ubah-data-kunjungan-ri');
    }

    /**
     * Tutup lewat server: kosongkan riHdrNo supaya isi modal (guard @if) benar-benar dihapus.
     */
    public function closeModal(): void
    {
        $this->riHdrNo = null;
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'ubah-data-kunjungan-ri');
    }

    /* ===============================
     | VALIDASI
     =============================== */
    protected function rules(): array
    {
        return [
            'klaimId' => 'required|exists:rsmst_klaimtypes,klaim_id',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'exists' => ':attribute tidak ditemukan di master.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'klaimId' => 'Klaim',
        ];
    }

    /* ===============================
     | SIMPAN
     =============================== */
    public function simpan(): void
    {
        if (!$this->riHdrNo) {
            return;
        }

        // Guard server — tombol @can saja bisa ditembus karena simpan() method publik.
        if (!auth()->user()?->can('ri.ubahDataKunjungan')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak mengubah data kunjungan.');
            return;
        }

        $this->validate();

        try {
            $ringkasan = '';

            DB::transaction(function () use (&$ringkasan) {
                $this->lockRIRow($this->riHdrNo);

                // Status & klaim dibaca ULANG di dalam lock — form bisa lama terbuka.
                if ($this->checkRIStatus($this->riHdrNo)) {
                    throw new \RuntimeException('Pasien sudah tidak dirawat, data tidak bisa diubah.');
                }

                $klaimLama = (string) (DB::table('rstxn_rihdrs')->where('rihdr_no', $this->riHdrNo)->value('klaim_id') ?: 'UM');
                if ($klaimLama === $this->klaimId) {
                    return;
                }

                DB::table('rstxn_rihdrs')
                    ->where('rihdr_no', $this->riHdrNo)
                    ->update(['klaim_id' => $this->klaimId]);

                // findDataRI mengisi klaimId/klaimDesc/klaimStatus dari header yang baru
                // diperbarui; ditulis balik supaya JSON ikut.
                $this->updateJsonRI($this->riHdrNo, $this->findDataRI($this->riHdrNo));

                $ringkasan = 'Klaim: ' . $this->klaimDesc($klaimLama) . ' → ' . $this->klaimDesc($this->klaimId);
                $this->appendAdminLogRI($this->riHdrNo, 'Ubah data kunjungan (kasir) - ' . $ringkasan);
            });

            if ($ringkasan === '') {
                $this->dispatch('toast', type: 'info', message: 'Tidak ada perubahan data kunjungan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Data kunjungan diperbarui. ' . $ringkasan, duration: 8000);
            $this->dispatch('refresh-after-ri.saved');
            $this->closeModal();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage(), duration: 8000);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    private function klaimDesc(string $klaimId): string
    {
        return collect($this->klaimOptions)->firstWhere('klaimId', $klaimId)['klaimDesc'] ?? $klaimId;
    }
};
?>

<div>
    <x-modal name="ubah-data-kunjungan-ri" size="full" height="full" focusable>
        {{-- Isi modal hanya di-mount saat ada pendaftaran: tertutup = nol komponen. --}}
        @if ($riHdrNo)
        <div class="flex flex-col min-h-full" wire:key="{{ $this->renderKey('modal-ubah-data-kunjungan-ri', [$riHdrNo ?? 'new']) }}">

            {{-- ═══════════ HEADER ═══════════ --}}
            <x-modul-dokumen.header judul="Ubah Data Kunjungan"
                ikon="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                jalur="RI" />

            {{-- Display Pasien --}}
            <div class="px-6 pt-3">
                <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                    wire:key="ubah-data-kunjungan-ri-display-pasien-{{ $riHdrNo }}" />
            </div>

            {{-- ═══════════ BODY ═══════════ --}}
            <div class="flex-1 px-4 py-6 bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="w-full max-w-3xl mx-auto space-y-4">

                    {{-- ── PANDUAN (biru-info standar, default tertutup) ── --}}
                    <div x-data="{ open: false }"
                        class="overflow-hidden border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
                        <button type="button" x-on:click="open = !open"
                            class="flex items-center justify-between w-full px-4 py-2.5 text-sm font-semibold transition-colors hover:bg-blue-100 dark:hover:bg-blue-900/30">
                            <span class="flex items-center gap-2 text-blue-900 dark:text-blue-200">
                                <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Panduan Ubah Data Kunjungan
                            </span>
                            <svg class="w-4 h-4 text-blue-600 transition-transform" :class="open ? 'rotate-180' : ''"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" x-collapse>
                            <ul class="px-4 pb-3 pl-9 space-y-1 text-sm list-disc text-blue-900 dark:text-blue-200">
                                <li>Hanya bisa selama pasien masih dirawat.</li>
                                <li>Setiap perubahan dicatat di Log Aktivitas, berisi nilai lama → baru.</li>
                                <li>Di Rawat Inap yang bisa dikoreksi di sini hanya klaim. Pasien <strong>tidak bisa</strong> diganti.</li>
                                <li>SEP tidak dibuat dari sini — bila klaim diganti ke BPJS, buat SEP lewat Pendaftaran Ubah.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- ── JENIS KLAIM (x-radio-button standar, sama dgn form Daftar RI) ── --}}
                    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="flex items-center gap-3 mb-4">
                            <span class="flex items-center justify-center w-7 h-7 text-sm font-bold rounded-full shrink-0 bg-brand-green/10 text-brand-green dark:bg-brand-lime/10 dark:text-brand-lime">1</span>
                            <div>
                                <h3 class="text-sm font-semibold text-ink dark:text-gray-200">Jenis Klaim</h3>
                                <p class="text-xs text-muted dark:text-gray-400">Penjamin biaya rawat inap ini.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach ($klaimOptions as $klaim)
                                <x-radio-button :label="$klaim['klaimDesc']" :value="(string) $klaim['klaimId']"
                                    name="klaimIdUbahDataKunjunganRi" wire:model.live="klaimId" />
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('klaimId')" class="mt-1" />

                        @if ($klaimId !== $klaimTersimpan)
                            <div class="flex items-start gap-2 px-3 py-2 mt-3 text-sm border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:text-amber-300 dark:bg-amber-900/20 dark:border-amber-700">
                                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                                </svg>
                                <span>Klaim diganti dari <strong>{{ $this->klaimDesc($klaimTersimpan) }}</strong> ke
                                    <strong>{{ $this->klaimDesc($klaimId) }}</strong>. Tarif tindakan yang sudah dientri
                                    tidak dihitung ulang.
                                    @if ($klaimId === 'JM')
                                        SEP belum dibuat otomatis — buat lewat Pendaftaran Ubah.
                                    @endif
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ═══════════ FOOTER (sticky) ═══════════ --}}
            <div class="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 px-6 py-4 border-t border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700">
                <p class="text-xs text-muted dark:text-gray-400">Perubahan dicatat di Log Aktivitas kunjungan.</p>
                <div class="flex items-center gap-3">
                    <x-secondary-button type="button" wire:click="closeModal">
                        Batal
                    </x-secondary-button>
                    <x-confirm-button variant="primary" :action="'simpan()'" title="Ubah Data Kunjungan"
                        message="Simpan perubahan klaim rawat inap ini? Perubahan dicatat di Log Aktivitas."
                        confirmText="Ya, simpan" cancelText="Batal">
                        Simpan Perubahan
                    </x-confirm-button>
                </div>
            </div>
        </div>
        @endif
    </x-modal>
</div>
