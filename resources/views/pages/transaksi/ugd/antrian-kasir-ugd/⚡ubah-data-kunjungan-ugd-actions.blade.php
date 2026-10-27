<?php
// resources/views/pages/transaksi/ugd/antrian-kasir-ugd/ubah-data-kunjungan-ugd-actions.blade.php
//
// Komponen TERSENDIRI untuk kasir: mengoreksi TANGGAL, SHIFT, dan KLAIM kunjungan UGD selama
// pasien masih dalam pelayanan (rj_status 'A'). Di form Daftar UGD tanggal & shift terkunci di
// mode edit — koreksinya lewat sini supaya tercatat di log admin.
//
// PASIEN SENGAJA TIDAK BISA DIGANTI (di sini maupun di Daftar UGD): EMR, resep, penunjang, dan
// biaya yang sudah dientri melekat ke kunjungan — mengganti reg_no memindahkannya ke pasien
// lain. Salah pasien → batalkan kunjungan di kasir, daftar ulang.
// Dibuka dari tombol di Antrian Kasir UGD: dispatch 'open-ubah-data-kunjungan-ugd'.
// Pola sama dengan ubah-data-kunjungan-rj-actions; beda: guard ganda jendela 30 menit (bukan
// dokter+hari) dan tabel rstxn_ugdhdrs.
//
// Tarif admin (RS Admin / Admin OB / Uang Periksa) SENGAJA tidak dihitung ulang saat klaim
// diganti — kasir menyesuaikannya sendiri di modal Administrasi.

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait;

    public ?int $rjNo = null;

    // ── Isian koreksi ──
    public ?string $rjDate = null;
    public ?string $shift = null;
    public string $klaimId = 'UM';

    /** Klaim saat modal dibuka — hanya untuk memunculkan peringatan tarif admin bila klaim diganti. */
    public string $klaimTersimpan = 'UM';

    public array $klaimOptions = [['klaimId' => 'UM', 'klaimDesc' => 'UMUM'], ['klaimId' => 'JM', 'klaimDesc' => 'BPJS'], ['klaimId' => 'JR', 'klaimDesc' => 'JASA RAHARJA'], ['klaimId' => 'JML', 'klaimDesc' => 'Asuransi Lain'], ['klaimId' => 'KR', 'klaimDesc' => 'Kronis']];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-ubah-data-kunjungan-ugd'];

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
    }

    /* ===============================
     | OPEN — dipicu dari Antrian Kasir UGD
     =============================== */
    #[On('open-ubah-data-kunjungan-ugd')]
    public function openUbahDataKunjungan(int $rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        if (!auth()->user()?->can('ugd.ubahDataKunjungan')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak mengubah data kunjungan.');
            return;
        }

        if ($this->checkUGDStatus($rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'Kunjungan sudah tidak dalam pelayanan, data tidak bisa diubah.');
            return;
        }

        $hdr = $this->bacaHeader($rjNo);
        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        $this->resetValidation();
        $this->rjNo = $rjNo;
        // Nilai tersimpan sudah tampil di display pasien; simpan() membandingkan dengan header
        // yang dibaca ulang di dalam lock, jadi tidak perlu salinan "sebelum" di sini.
        $this->rjDate = (string) $hdr->rj_date;
        $this->shift = (string) $hdr->shift;
        $this->klaimId = (string) ($hdr->klaim_id ?: 'UM');
        $this->klaimTersimpan = $this->klaimId;

        $this->incrementVersion('modal-ubah-data-kunjungan-ugd');
        $this->dispatch('open-modal', name: 'ubah-data-kunjungan-ugd');
    }

    /**
     * Tutup lewat server: kosongkan rjNo supaya isi modal (guard @if) benar-benar dihapus.
     */
    public function closeModal(): void
    {
        $this->rjNo = null;
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'ubah-data-kunjungan-ugd');
    }

    private function bacaHeader(int $rjNo): ?object
    {
        return DB::table('rstxn_ugdhdrs as h')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->where('h.rj_no', $rjNo)
            ->select('h.reg_no', 'p.reg_name', DB::raw("to_char(h.rj_date,'dd/mm/yyyy hh24:mi:ss') as rj_date"), 'h.shift', 'h.klaim_id', 'h.vno_sep', 'h.dr_id')
            ->first();
    }

    /* ===============================
     | TANGGAL & SHIFT
     =============================== */
    public function setTanggalSekarang(): void
    {
        $this->rjDate = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->updatedRjDate($this->rjDate);
    }

    /** Shift mengikuti jam (rstxn_shiftctls), sama dengan form Daftar UGD — tetap bisa dipilih manual. */
    public function updatedRjDate(?string $value): void
    {
        try {
            $jam = Carbon::createFromFormat('d/m/Y H:i:s', (string) $value)->format('H:i:s');
        } catch (\Throwable $e) {
            return; // format belum lengkap — biarkan shift apa adanya
        }

        // Oracle '' = NULL → whereNotNull cukup (lihat resolveShiftByTime di daftar-rj-actions).
        $shiftRow = DB::table('rstxn_shiftctls')
            ->select('shift')
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->whereRaw('? BETWEEN shift_start AND shift_end', [$jam])
            ->first();

        if ($shiftRow) {
            $this->shift = (string) $shiftRow->shift;
        }
    }

    /* ===============================
     | VALIDASI
     =============================== */
    protected function rules(): array
    {
        return [
            'rjDate' => 'required|date_format:d/m/Y H:i:s',
            'shift' => 'required|in:1,2,3',
            'klaimId' => 'required|exists:rsmst_klaimtypes,klaim_id',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'exists' => ':attribute tidak ditemukan di master.',
            'date_format' => ':attribute harus berformat dd/mm/yyyy hh:mm:ss.',
            'in' => ':attribute harus 1, 2, atau 3.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'rjDate' => 'Tanggal kunjungan',
            'shift' => 'Shift',
            'klaimId' => 'Klaim',
        ];
    }

    /* ===============================
     | SIMPAN
     =============================== */
    public function simpan(): void
    {
        if (!$this->rjNo) {
            return;
        }

        // Guard server — tombol @can saja bisa ditembus karena simpan() method publik.
        if (!auth()->user()?->can('ugd.ubahDataKunjungan')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak mengubah data kunjungan.');
            return;
        }

        $this->validate();

        try {
            $ringkasan = '';

            DB::transaction(function () use (&$ringkasan) {
                $this->lockUGDRow($this->rjNo);

                // Status & nilai dibaca ULANG di dalam lock — form bisa lama terbuka.
                if ($this->checkUGDStatus($this->rjNo)) {
                    throw new \RuntimeException('Kunjungan sudah tidak dalam pelayanan, data tidak bisa diubah.');
                }

                $hdr = $this->bacaHeader($this->rjNo);
                if (!$hdr) {
                    throw new \RuntimeException('Data UGD tidak ditemukan.');
                }

                $klaimLama = (string) ($hdr->klaim_id ?: 'UM');
                $tanggalBerubah = (string) $hdr->rj_date !== (string) $this->rjDate;
                $shiftBerubah = (string) $hdr->shift !== (string) $this->shift;
                $klaimBerubah = $klaimLama !== $this->klaimId;

                if (!$tanggalBerubah && !$shiftBerubah && !$klaimBerubah) {
                    return;
                }

                // Guard pendaftaran ganda (semantik sama dgn cekDuplikatPendaftaran di daftar-ugd-actions):
                // pasien UGD sah datang 2x sehari, jadi bloknya jendela ±30 menit, kunjungan batal diabaikan.
                if ($tanggalBerubah) {
                    $rjNoGanda = DB::table('rstxn_ugdhdrs')
                        ->where('reg_no', $hdr->reg_no)
                        ->where('rj_no', '!=', $this->rjNo)
                        ->whereRaw("NVL(rj_status,'A') != 'F'")
                        ->whereRaw("ABS(rj_date - to_date(?,'dd/mm/yyyy hh24:mi:ss')) * 1440 < 30", [$this->rjDate])
                        ->value('rj_no');
                    if ($rjNoGanda) {
                        throw new \RuntimeException("Pasien sudah punya kunjungan UGD lain dalam 30 menit dari waktu tersebut (No. UGD {$rjNoGanda}).");
                    }
                }

                // ── Header ──
                // waktu_masuk_pelayanan ikut rj_date — sama dengan buildPayload() Daftar UGD.
                DB::table('rstxn_ugdhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update([
                        'rj_date' => DB::raw("to_date('" . $this->rjDate . "','dd/mm/yyyy hh24:mi:ss')"),
                        'waktu_masuk_pelayanan' => DB::raw("to_date('" . $this->rjDate . "','dd/mm/yyyy hh24:mi:ss')"),
                        'shift' => $this->shift,
                        'klaim_id' => $this->klaimId,
                    ]);

                // ── JSON ── findDataUGD mengisi rjDate/shift/klaimId/klaimStatus
                // dari header yang baru saja diperbarui; ditulis balik supaya JSON ikut.
                $this->updateJsonUGD($this->rjNo, $this->findDataUGD($this->rjNo));

                // ── Log admin: nilai lama → baru, hanya yang berubah ──
                $perubahan = [];
                if ($tanggalBerubah) {
                    $perubahan[] = "Tanggal: {$hdr->rj_date} → {$this->rjDate}";
                }
                if ($shiftBerubah) {
                    $perubahan[] = "Shift: {$hdr->shift} → {$this->shift}";
                }
                if ($klaimBerubah) {
                    $perubahan[] = 'Klaim: ' . $this->klaimDesc($klaimLama) . ' → ' . $this->klaimDesc($this->klaimId);
                }

                $ringkasan = implode('; ', $perubahan);
                $this->appendAdminLogUGD($this->rjNo, 'Ubah data kunjungan (kasir) - ' . $ringkasan);
            });

            if ($ringkasan === '') {
                $this->dispatch('toast', type: 'info', message: 'Tidak ada perubahan data kunjungan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Data kunjungan diperbarui. ' . $ringkasan, duration: 8000);
            $this->dispatch('refresh-after-kasir.saved');
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
    <x-modal name="ubah-data-kunjungan-ugd" size="full" height="full" focusable>
        {{-- Isi modal hanya di-mount saat ada kunjungan: tertutup = nol komponen. --}}
        @if ($rjNo)
        <div class="flex flex-col min-h-full" wire:key="{{ $this->renderKey('modal-ubah-data-kunjungan-ugd', [$rjNo ?? 'new']) }}">

            {{-- ═══════════ HEADER ═══════════ --}}
            <x-modul-dokumen.header judul="Ubah Data Kunjungan"
                ikon="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                jalur="UGD" />

            {{-- Display Pasien --}}
            <div class="px-6 pt-3">
                <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                    wire:key="ubah-data-kunjungan-ugd-display-pasien-{{ $rjNo }}" />
            </div>

            {{-- ═══════════ BODY ═══════════ --}}
            <div class="flex-1 px-4 py-6 bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="w-full max-w-6xl mx-auto space-y-4">

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
                                <li>Hanya bisa selama pasien masih dalam pelayanan (status Antrian).</li>
                                <li>Setiap perubahan dicatat di Log Aktivitas kunjungan, berisi nilai lama → baru.</li>
                                <li>Pasien <strong>tidak bisa</strong> diganti — salah pasien: batalkan kunjungan lalu daftar ulang.</li>
                            </ul>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:items-start">

                        {{-- ── ① WAKTU KUNJUNGAN ── --}}
                        <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                            <div class="flex items-center gap-3 mb-4">
                                <span class="flex items-center justify-center w-7 h-7 text-sm font-bold rounded-full shrink-0 bg-brand-green/10 text-brand-green dark:bg-brand-lime/10 dark:text-brand-lime">1</span>
                                <div>
                                    <h3 class="text-sm font-semibold text-ink dark:text-gray-200">Waktu Kunjungan</h3>
                                    <p class="text-xs text-muted dark:text-gray-400">Shift otomatis mengikuti jam, tetap bisa dipilih manual.</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div class="sm:col-span-2">
                                    <x-input-label value="Tanggal & Jam" />
                                    <div class="flex items-center gap-2 mt-1">
                                        <x-text-input wire:model.live.debounce.500ms="rjDate" class="block w-full tabular-nums"
                                            placeholder="dd/mm/yyyy hh:mm:ss" :error="$errors->has('rjDate')" />
                                        <x-now-button wire:click="setTanggalSekarang" />
                                    </div>
                                    <x-input-error :messages="$errors->get('rjDate')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Shift" />
                                    <x-select-input wire:model.live="shift" class="w-full mt-1" :error="$errors->has('shift')">
                                        <option value="">-- Shift --</option>
                                        <option value="1">Shift 1</option>
                                        <option value="2">Shift 2</option>
                                        <option value="3">Shift 3</option>
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('shift')" class="mt-1" />
                                </div>
                            </div>
                        </div>

                        {{-- ── ② JENIS KLAIM (x-radio-button standar, sama dgn form Daftar UGD) ── --}}
                        <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                            <div class="flex items-center gap-3 mb-4">
                                <span class="flex items-center justify-center w-7 h-7 text-sm font-bold rounded-full shrink-0 bg-brand-green/10 text-brand-green dark:bg-brand-lime/10 dark:text-brand-lime">2</span>
                                <div>
                                    <h3 class="text-sm font-semibold text-ink dark:text-gray-200">Jenis Klaim</h3>
                                    <p class="text-xs text-muted dark:text-gray-400">Penjamin biaya kunjungan ini.</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                @foreach ($klaimOptions as $klaim)
                                    <x-radio-button :label="$klaim['klaimDesc']" :value="(string) $klaim['klaimId']"
                                        name="klaimIdUbahDataKunjunganUgd" wire:model.live="klaimId" />
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
                                        <strong>{{ $this->klaimDesc($klaimId) }}</strong>. Tarif admin tidak ikut berubah —
                                        sesuaikan RS Admin / Admin OB / Uang Periksa di Administrasi.</span>
                                </div>
                            @endif
                        </div>

                    </div>{{-- end grid 2 kolom --}}
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
                        message="Simpan perubahan data kunjungan ini? Perubahan dicatat di Log Aktivitas."
                        confirmText="Ya, simpan" cancelText="Batal">
                        Simpan Perubahan
                    </x-confirm-button>
                </div>
            </div>
        </div>
        @endif
    </x-modal>
</div>
