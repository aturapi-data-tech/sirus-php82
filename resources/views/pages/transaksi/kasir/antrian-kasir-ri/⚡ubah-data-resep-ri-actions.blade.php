<?php
// resources/views/pages/transaksi/kasir/antrian-kasir-ri/ubah-data-resep-ri-actions.blade.php
//
// Komponen TERSENDIRI untuk kasir resep RI: mengoreksi TANGGAL & SHIFT resep (imtxn_slshdrs)
// selama resep masih di ANTRIAN (status 'A', belum diproses kasir). Padanan
// ubah-data-kunjungan-{rj,ugd}-actions, tetapi per RESEP, bukan per kunjungan:
//   - Klaim TIDAK di sini — klaim melekat ke pendaftaran RI (Ubah Data Kunjungan di Daftar Kasir RI).
//   - Setelah Lunas/Bon (status 'L') tanggal resep sudah tercetak di keterangan Bon Inap
//     (rstxn_ribonobats.ribon_desc/ribon_date) → koreksi wajib Batal Transaksi dulu.
//   - eresepHdr.resepDate di JSON RI (catatan resep dokter) SENGAJA tidak diubah.
// Dibuka dari menu titik-3 Resep Inap (antrian-kasir-ri): dispatch 'open-ubah-data-resep-ri'.
// Log dicatat di Log Aktivitas kunjungan RI (AdministrasiRI.userLogs), memuat No SLS.

use Livewire\Component;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Authorize;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    #[Locked] // kunci record — tak boleh diubah dari klien (celah ID)
    public ?int $slsNo = null;
    public ?int $riHdrNo = null;

    // ── Isian koreksi ──
    public ?string $slsDate = null;
    public ?string $shift = null;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-ubah-data-resep-ri'];

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
    }

    /* ===============================
     | OPEN — dipicu dari Resep Inap (kasir, antrian-kasir-ri)
     =============================== */
    #[On('open-ubah-data-resep-ri')]
    #[Authorize('pintu.open-ubah-data-resep-ri')]
    public function openUbahDataResep(int $slsNo): void
    {
        if (empty($slsNo)) {
            return;
        }

        if (!auth()->user()?->can('ri.ubahDataResep')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak mengubah data resep.');
            return;
        }

        $hdr = $this->bacaHeader($slsNo);
        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Data resep RI tidak ditemukan.');
            return;
        }

        $alasanTerkunci = $this->alasanTerkunci($hdr);
        if ($alasanTerkunci) {
            $this->dispatch('toast', type: 'error', message: $alasanTerkunci);
            return;
        }

        $this->resetValidation();
        $this->slsNo = $slsNo;
        $this->riHdrNo = (int) $hdr->rihdr_no;
        $this->slsDate = (string) $hdr->sls_date;
        $this->shift = (string) $hdr->shift;

        $this->incrementVersion('modal-ubah-data-resep-ri');
        $this->dispatch('open-modal', name: 'ubah-data-resep-ri');
    }

    /**
     * Tutup lewat server: kosongkan slsNo supaya isi modal (guard @if) benar-benar dihapus.
     */
    public function closeModal(): void
    {
        $this->slsNo = null;
        $this->riHdrNo = null;
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'ubah-data-resep-ri');
    }

    private function bacaHeader(int $slsNo): ?object
    {
        return DB::table('imtxn_slshdrs as s')
            ->join('rstxn_rihdrs as r', 'r.rihdr_no', '=', 's.rihdr_no')
            ->where('s.sls_no', $slsNo)
            ->select('s.rihdr_no', 's.status', 'r.ri_status', DB::raw("to_char(s.sls_date,'dd/mm/yyyy hh24:mi:ss') as sls_date"), 's.shift', DB::raw("to_char(r.entry_date,'dd/mm/yyyy hh24:mi:ss') as entry_date"))
            ->first();
    }

    /** Null = boleh diubah; selain itu pesan penolakan. */
    private function alasanTerkunci(object $hdr): ?string
    {
        if (strtoupper($hdr->status ?? 'A') === 'L') {
            return 'Resep sudah diproses kasir. Batalkan transaksinya dulu bila tanggal/shift perlu dikoreksi.';
        }
        if (in_array(strtoupper($hdr->ri_status ?? ''), ['P', 'B'], true)) {
            return 'Pasien sudah pulang / pendaftaran RI batal, data resep tidak bisa diubah.';
        }
        return null;
    }

    /* ===============================
     | TANGGAL & SHIFT
     =============================== */
    public function setTanggalSekarang(): void
    {
        $this->slsDate = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->updatedSlsDate($this->slsDate);
    }

    /** Shift OTOMATIS PENUH dari jam — tidak bisa dipilih manual (beda dengan RJ/UGD). Hanya tampilan; simpan() menghitung ulang. */
    public function updatedSlsDate(?string $value): void
    {
        $this->shift = $this->cariShift((string) $value);
    }

    /** Shift dari jam tanggal resep (rstxn_shiftctls). Null = format tanggal salah / jam di luar semua rentang shift. */
    private function cariShift(string $tanggal): ?string
    {
        try {
            $jam = Carbon::createFromFormat('d/m/Y H:i:s', $tanggal)->format('H:i:s');
        } catch (\Throwable $e) {
            return null;
        }

        // Oracle '' = NULL → whereNotNull cukup.
        $shift = DB::table('rstxn_shiftctls')
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->whereRaw('? BETWEEN shift_start AND shift_end', [$jam])
            ->value('shift');

        return $shift !== null ? (string) $shift : null;
    }

    /* ===============================
     | VALIDASI
     =============================== */
    protected function rules(): array
    {
        return [
            'slsDate' => 'required|date_format:d/m/Y H:i:s',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'date_format' => ':attribute harus berformat dd/mm/yyyy hh:mm:ss.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'slsDate' => 'Tanggal resep',
        ];
    }

    /* ===============================
     | SIMPAN
     =============================== */
    public function simpan(): void
    {
        if (!$this->slsNo) {
            return;
        }

        // Guard server — tombol @can saja bisa ditembus karena simpan() method publik.
        if (!auth()->user()?->can('ri.ubahDataResep')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak mengubah data resep.');
            return;
        }

        $this->validate();

        // Shift dihitung ulang di server dari jam yang disimpan — isian layar tidak dipercaya.
        $this->shift = $this->cariShift((string) $this->slsDate);
        if ($this->shift === null) {
            $this->addError('slsDate', 'Jam ini tidak masuk rentang shift mana pun di master shift. Hubungi administrator.');
            return;
        }

        try {
            $ringkasan = '';

            DB::transaction(function () use (&$ringkasan) {
                // Kunci baris resep (sama dengan postTransaksi kasir) lalu baris RI (untuk log JSON).
                DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)->lockForUpdate()->first();
                $this->lockRIRow($this->riHdrNo);

                // Status & nilai dibaca ULANG di dalam lock — form bisa lama terbuka.
                $hdr = $this->bacaHeader($this->slsNo);
                if (!$hdr) {
                    throw new \RuntimeException('Data resep RI tidak ditemukan.');
                }
                $alasanTerkunci = $this->alasanTerkunci($hdr);
                if ($alasanTerkunci) {
                    throw new \RuntimeException($alasanTerkunci);
                }

                $tanggalBerubah = (string) $hdr->sls_date !== (string) $this->slsDate;
                $shiftBerubah = (string) $hdr->shift !== (string) $this->shift;

                if (!$tanggalBerubah && !$shiftBerubah) {
                    return;
                }

                // Resep tak boleh lebih awal dari tanggal masuk RI.
                if ($tanggalBerubah && $hdr->entry_date) {
                    $tglResep = Carbon::createFromFormat('d/m/Y H:i:s', $this->slsDate);
                    $tglMasuk = Carbon::createFromFormat('d/m/Y H:i:s', $hdr->entry_date);
                    if ($tglResep->lt($tglMasuk)) {
                        throw new \RuntimeException("Tanggal resep tidak boleh sebelum tanggal masuk RI ({$hdr->entry_date}).");
                    }
                }

                DB::table('imtxn_slshdrs')
                    ->where('sls_no', $this->slsNo)
                    ->update([
                        'sls_date' => DB::raw("to_date('" . $this->slsDate . "','dd/mm/yyyy hh24:mi:ss')"),
                        'shift' => $this->shift,
                    ]);

                // ── Log admin RI: nilai lama → baru, hanya yang berubah ──
                $perubahan = [];
                if ($tanggalBerubah) {
                    $perubahan[] = "Tanggal: {$hdr->sls_date} → {$this->slsDate}";
                }
                if ($shiftBerubah) {
                    $perubahan[] = "Shift: {$hdr->shift} → {$this->shift}";
                }

                $ringkasan = implode('; ', $perubahan);
                $this->appendAdminLogRI($this->riHdrNo, "Ubah data resep SLS {$this->slsNo} (kasir) - " . $ringkasan);
            });

            if ($ringkasan === '') {
                $this->dispatch('toast', type: 'info', message: 'Tidak ada perubahan data resep.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Data resep diperbarui. ' . $ringkasan, duration: 8000);
            $this->dispatch('refresh-after-antrian-kasir-ri.saved');
            $this->closeModal();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage(), duration: 8000);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }
};
?>

<div>
    <x-modal name="ubah-data-resep-ri" size="full" height="full" focusable>
        {{-- Isi modal hanya di-mount saat ada resep: tertutup = nol komponen. --}}
        @if ($slsNo)
        <div class="flex flex-col min-h-full" wire:key="{{ $this->renderKey('modal-ubah-data-resep-ri', [$slsNo ?? 'new']) }}">

            {{-- ═══════════ HEADER ═══════════ --}}
            <x-modul-dokumen.header judul="Ubah Data Resep"
                ikon="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                jalur="RI" />

            {{-- Display Pasien --}}
            <div class="px-6 pt-3">
                <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                    wire:key="ubah-data-resep-ri-display-pasien-{{ $slsNo }}" />
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
                                Panduan Ubah Data Resep
                            </span>
                            <svg class="w-4 h-4 text-blue-600 transition-transform" :class="open ? 'rotate-180' : ''"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" x-collapse>
                            <ul class="px-4 pb-3 pl-9 space-y-1 text-sm list-disc text-blue-900 dark:text-blue-200">
                                <li>Shift ditentukan otomatis dari jam resep (master shift), tidak dipilih manual.</li>
                                <li>Hanya bisa selama resep masih di Antrian (belum diproses kasir) dan pasien belum pulang.</li>
                                <li>Resep yang sudah Lunas/Bon: Batal Transaksi dulu di Administrasi, baru dikoreksi.</li>
                                <li>Setiap perubahan dicatat di Log Aktivitas kunjungan RI, berisi No SLS dan nilai lama → baru.</li>
                                <li>Klaim tidak diubah di sini — klaim mengikuti pendaftaran RI (Ubah Data Kunjungan di Daftar Kasir RI).</li>
                            </ul>
                        </div>
                    </div>

                    {{-- ── WAKTU RESEP ── --}}
                    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="flex items-center gap-3 mb-4">
                            <span class="flex items-center justify-center w-7 h-7 text-sm font-bold rounded-full shrink-0 bg-brand-green/10 text-brand-green dark:bg-brand-lime/10 dark:text-brand-lime">1</span>
                            <div>
                                <h3 class="text-sm font-semibold text-ink dark:text-gray-200">Waktu Resep · SLS {{ $slsNo }}</h3>
                                <p class="text-xs text-muted dark:text-gray-400">Shift otomatis mengikuti jam, tidak bisa dipilih manual.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div class="sm:col-span-2">
                                <x-input-label value="Tanggal & Jam" />
                                <div class="flex items-center gap-2 mt-1">
                                    <x-text-input wire:model.live.debounce.500ms="slsDate" class="block w-full tabular-nums"
                                        placeholder="dd/mm/yyyy hh:mm:ss" :error="$errors->has('slsDate')" />
                                    <x-now-button wire:click="setTanggalSekarang" />
                                </div>
                                <x-input-error :messages="$errors->get('slsDate')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Shift" />
                                <div class="flex items-center px-3 py-2.5 mt-1 text-sm border rounded-lg tabular-nums bg-surface-soft border-hairline text-ink dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200">
                                    @if ($shift)
                                        <span class="font-semibold">Shift {{ $shift }}</span>
                                        <span class="ml-1.5 text-xs text-muted dark:text-gray-400">(otomatis)</span>
                                    @else
                                        <span class="text-xs text-muted dark:text-gray-400">— isi jam yang valid —</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- ═══════════ FOOTER (sticky) ═══════════ --}}
            <div class="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 px-6 py-4 border-t border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700">
                <p class="text-xs text-muted dark:text-gray-400">Perubahan dicatat di Log Aktivitas kunjungan RI.</p>
                <div class="flex items-center gap-3">
                    <x-secondary-button type="button" wire:click="closeModal">
                        <x-ikon nama="batal" />
                        Batal
                    </x-secondary-button>
                    <x-confirm-button variant="primary" :action="'simpan()'" title="Ubah Data Resep"
                        message="Simpan perubahan tanggal/shift resep ini? Perubahan dicatat di Log Aktivitas."
                        confirmText="Ya, simpan" cancelText="Batal">
                        <x-ikon nama="simpan" />
                        Simpan Perubahan
                    </x-confirm-button>
                </div>
            </div>
        </div>
        @endif
    </x-modal>
</div>
