<?php
// resources/views/pages/transaksi/ugd/emr-ugd/pemeriksaan/penunjang/kamar-operasi/rm-kamar-operasi-ugd-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Support\KamarOperasiTarif;

/**
 * Order Kamar Operasi dari EMR Gawat Darurat — unit mengirim pasien ke OK.
 *
 * Sejajar dengan order Laboratorium/Radiologi: dokter ruangan mengirim, petugas
 * OK yang memproses, biayanya kembali ke tagihan gawat darurat. Bedanya, OK tidak
 * punya tabel order terpisah — order langsung membuat header `rstxn_oks` dengan
 * `ok_status='A'` (Proses Transaksi), yaitu status yang sama dengan transaksi
 * yang dibuat petugas OK sendiri lewat menu Penunjang. Jadi dari sisi petugas OK
 * keduanya tidak berbeda; yang membedakan hanya siapa yang membuat (tercatat di
 * audit log kunjungan).
 *
 * Rencana tindakan yang dipilih dokter langsung mengisi `rstxn_okacts`, lalu tarif
 * dihitung memakai rumus yang sama persis dengan modul OK
 * (`KamarOperasiTarif::hitungUlang`) — petugas OK tetap bisa mengubahnya nanti
 * selama status masih 'A'.
 */
new class extends Component {
    use WithRenderVersioningTrait, WithValidationToastTrait, EmrUGDTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['kamar-operasi-order-modal-ugd'];

    public ?string $rjNo = null;
    public bool $disabled = false;

    /* ── State Modal ── */
    public string $drId = '';        // dokter operator
    public string $drIdOk = '';      // dokter anestesi
    public ?string $diagId = null;   // diagnosa pra-operasi (opsional)
    public string $diagDesc = '';

    /** Rencana tindakan: [accdoc_id => ['accdoc_id','accdoc_desc','accdoc_price']] */
    public array $selectedTindakan = [];

    protected function rules(): array
    {
        return [
            'drId' => 'required',
            'drIdOk' => 'required',
        ];
    }

    protected function messages(): array
    {
        return [
            'drId.required' => 'Dokter operator harus dipilih.',
            'drIdOk.required' => 'Dokter anestesi harus dipilih.',
        ];
    }

    public function mount(?string $rjNo = null, bool $disabled = false): void
    {
        $this->rjNo = $rjNo;
        $this->disabled = $disabled;
        $this->registerAreas($this->renderAreas);
    }

    #[On('open-rm-kamar-operasi-ugd')]
    public function open(string $rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }
        $this->rjNo = $rjNo;
    }

    /* ═══════════════════════════════════════
    | OPEN / CLOSE ORDER MODAL
    ═══════════════════════════════════════ */
    public function openModal(): void
    {
        if ($this->disabled) {
            return;
        }

        if ($this->checkUGDStatus($this->rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'Kunjungan sudah ditutup di kasir, tidak dapat mengirim ke kamar operasi.');
            return;
        }

        $this->resetForm();

        // Dokter kunjungan dipakai sebagai usulan operator; dokter boleh menggantinya.
        $this->drId = (string) (DB::table('rstxn_ugdhdrs')->where('rj_no', $this->rjNo)->value('dr_id') ?? '');

        $this->resetValidation();
        $this->incrementVersion('kamar-operasi-order-modal-ugd');
        $this->dispatch('open-modal', name: 'kamar-operasi-order-ugd');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'kamar-operasi-order-ugd');
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['drId', 'drIdOk', 'diagId', 'diagDesc', 'selectedTindakan']);
    }

    /* ═══════════════════════════════════════
    | LOV listeners
    ═══════════════════════════════════════ */
    #[On('lov.selected.order-ok-ugd-operator')]
    public function pilihOperator($target = null, $payload = null): void
    {
        $this->drId = (string) ($payload['dr_id'] ?? '');
    }

    #[On('lov.selected.order-ok-ugd-anestesi')]
    public function pilihAnestesi($target = null, $payload = null): void
    {
        $this->drIdOk = (string) ($payload['dr_id'] ?? '');
    }

    #[On('lov.selected.order-ok-ugd-diagnosa')]
    public function pilihDiagnosa($target = null, $payload = null): void
    {
        // Simpan diag_id (PK), BUKAN icdx — icdx hanya untuk sistem eksternal.
        $this->diagId = $payload['diag_id'] ?? null;
        $this->diagDesc = $payload['diag_desc'] ?? '';
    }

    #[On('lov.selected.order-ok-ugd-tindakan')]
    public function pilihTindakan($target = null, $payload = null): void
    {
        $accdocId = $payload['accdoc_id'] ?? null;
        if (empty($accdocId)) {
            return;
        }

        $this->selectedTindakan[$accdocId] = [
            'accdoc_id' => (string) $accdocId,
            'accdoc_desc' => (string) ($payload['accdoc_desc'] ?? ''),
            // accdoc_price dari lov-jasa-dokter = tarif dasar (RJ/UGD tanpa kelas kamar).
            'accdoc_price' => (int) ($payload['accdoc_price'] ?? 0),
        ];
    }

    public function hapusTindakan(string $accdocId): void
    {
        unset($this->selectedTindakan[$accdocId]);
    }

    #[Computed]
    public function totalRencana(): int
    {
        return array_sum(array_map(fn($tindakan) => (int) ($tindakan['accdoc_price'] ?? 0), $this->selectedTindakan));
    }

    /* ═══════════════════════════════════════
    | KIRIM KE KAMAR OPERASI
    ═══════════════════════════════════════ */
    public function kirimKamarOperasi(): void
    {
        if ($this->disabled) {
            return;
        }

        $this->validateWithToast();

        if ($this->checkUGDStatus($this->rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'Kunjungan sudah ditutup di kasir, tidak dapat mengirim ke kamar operasi.');
            return;
        }

        $rjNo = (int) $this->rjNo;
        $drId = $this->drId;
        $drIdOk = $this->drIdOk;
        $diagId = $this->diagId;
        $tindakan = $this->selectedTindakan;
        $okRegBaru = null;

        // ok_reg & okact_id = PK tanpa sequence → tabrakan ditangani dengan mengulang.
        for ($percobaan = 1; ; $percobaan++) {
            try {
                DB::transaction(function () use ($rjNo, $drId, $drIdOk, $diagId, $tindakan, &$okRegBaru) {
                    $this->lockUGDRow($rjNo);

                    $statusInduk = DB::table('rstxn_ugdhdrs')->where('rj_no', $rjNo)->value('rj_status');
                    if (strtoupper((string) ($statusInduk ?? 'A')) !== 'A') {
                        throw new \RuntimeException('Kunjungan sudah ditutup di kasir, tidak dapat mengirim ke kamar operasi.');
                    }

                    $okRegBaru = (int) DB::scalar('SELECT NVL(MAX(TO_NUMBER(ok_reg)),0) + 1 FROM rstxn_oks');

                    DB::table('rstxn_oks')->insert([
                        'ok_reg' => $okRegBaru,
                        'ok_date' => DB::raw('SYSDATE'),
                        // status_rjri + ref_no = sumber kebenaran layanan (pola lab).
                        'status_rjri' => 'UGD',
                        'ref_no' => $rjNo,
                        // rihdr_no HANYA untuk RI — kolomnya FK ke rstxn_rihdrs.
                        'rihdr_no' => null,
                        'dr_id' => $drId,
                        'dr_id_ok' => $drIdOk,
                        'diag_id' => $diagId,
                        'ok_status' => 'A',
                        // Semua baris lama memakai '01'; dipertahankan supaya laporan
                        // lama yang memfilter kolom ini tetap melihat data baru.
                        'sl_codefrom' => '01',
                    ]);

                    if ($tindakan !== []) {
                        $nomor = (int) DB::scalar('SELECT NVL(MAX(okact_id),0) FROM rstxn_okacts');

                        foreach ($tindakan as $item) {
                            $nomor++;
                            DB::table('rstxn_okacts')->insert(['okact_id' => $nomor, 'accdoc_id' => $item['accdoc_id'], 'okact_price' => (int) $item['accdoc_price'], 'ok_reg' => $okRegBaru]);
                        }

                        // Rumus tarif identik dengan modul OK — jangan disalin ulang.
                        $row = DB::table('rstxn_oks')->where('ok_reg', $okRegBaru)->lockForUpdate()->first();
                        KamarOperasiTarif::hitungUlang((string) $okRegBaru, $row);
                    }

                    $ringkasTindakan = $tindakan === [] ? 'tanpa rencana tindakan' : collect($tindakan)->pluck('accdoc_desc')->implode(', ');
                    $this->appendAdminLogUGD($rjNo, "Order Kamar Operasi No.{$okRegBaru} — operator {$drId}, anestesi {$drIdOk}" . ($diagId ? ", diagnosa pra-op {$diagId}" : '') . " — {$ringkasTindakan}", 'MR');
                });

                break;
            } catch (\RuntimeException $e) {
                $this->dispatch('toast', type: 'error', message: $e->getMessage());
                return;
            } catch (\Illuminate\Database\QueryException $e) {
                if ($percobaan < 3 && str_contains($e->getMessage(), 'ORA-00001')) {
                    continue;
                }

                $this->dispatch('toast', type: 'error', message: 'Gagal mengirim ke kamar operasi: ' . $e->getMessage());
                return;
            } catch (\Exception $e) {
                $this->dispatch('toast', type: 'error', message: 'Gagal mengirim ke kamar operasi: ' . $e->getMessage());
                return;
            }
        }

        $this->closeModal();
        $this->dispatch('kamar-operasi-ugd.updated');
        $this->dispatch('refresh-after-kamar-operasi.saved');
        $this->dispatch('toast', type: 'success', message: "Pasien dikirim ke Kamar Operasi — No. Txn {$okRegBaru}.");
    }
};
?>

<div>
    {{-- TOMBOL ORDER --}}
    <x-primary-button type="button" wire:click="openModal" :disabled="$disabled" wire:loading.attr="disabled"
        wire:target="openModal">
        <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Kirim ke Kamar Operasi
        </span>
        <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
            <x-loading /> Memuat...
        </span>
    </x-primary-button>

    {{-- MODAL ORDER --}}
    <x-modal name="kamar-operasi-order-ugd" size="full" height="full" focusable>
        <div class="flex flex-col h-full" wire:key="{{ $this->renderKey('kamar-operasi-order-modal-ugd', [$rjNo ?: 'kosong']) }}">

            {{-- HEADER --}}
            <div class="flex items-center justify-between px-6 py-4 border-b shrink-0 border-hairline dark:border-gray-700">
                <div>
                    <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Kirim Pasien ke Kamar Operasi</h2>
                    <p class="text-xs text-muted">Order dari ruangan — petugas OK yang akan melengkapi tarif dan bahan</p>
                </div>
                <x-icon-button color="gray" type="button" wire:click="closeModal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </x-icon-button>
            </div>

            {{-- BODY — satu kolom dari atas ke bawah, memakai lebar penuh modal,
                 tiap bagian dibingkai x-border-form (pola sama dengan Master Poli). --}}
            <div class="flex-1 min-h-0 px-6 py-5 overflow-y-auto bg-surface-soft dark:bg-gray-950/20">
                <div class="w-full space-y-5">

                    <x-border-form title="Dokter Operasi">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <livewire:lov.dokter.lov-dokter target="order-ok-ugd-operator" label="Dokter Operator"
                                    :initialDrId="$drId ?: null" wire:key="order-ok-ugd-operator-{{ $rjNo }}-{{ $drId }}" />
                                <x-input-error :messages="$errors->get('drId')" class="mt-1" />
                                <p class="mt-1 text-xs italic text-amber-700 dark:text-amber-400">Pendapatan pos Jasa Dokter Operator tercatat atas nama dokter ini.</p>
                            </div>
                            <div>
                                <livewire:lov.dokter.lov-dokter target="order-ok-ugd-anestesi" label="Dokter Anestesi"
                                    :initialDrId="$drIdOk ?: null" wire:key="order-ok-ugd-anestesi-{{ $rjNo }}-{{ $drIdOk }}" />
                                <x-input-error :messages="$errors->get('drIdOk')" class="mt-1" />
                                <p class="mt-1 text-xs italic text-amber-700 dark:text-amber-400">Pendapatan pos Jasa Dokter Anestesi tercatat atas nama dokter ini.</p>
                            </div>
                        </div>
                    </x-border-form>

                    {{-- Satu baris: Diagnosa · Rencana Tindakan · catatan status — rasio 1:1:1.
                         TANPA items-start — biar ketiga kotak sama tinggi (grid default
                         stretch), lalu tiap anaknya h-full supaya kartunya ikut memanjang. --}}
                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">

                        <x-border-form title="Diagnosa Pra-Operasi" class="lg:col-span-1 h-full">
                            {{-- Label tak mengulang judul bingkai. --}}
                            <livewire:lov.diagnosa.lov-diagnosa target="order-ok-ugd-diagnosa" label="Cari Diagnosa (opsional)"
                                :initialDiagnosaId="$diagId" wire:key="order-ok-ugd-diagnosa-{{ $rjNo }}-{{ $diagId }}" />
                        </x-border-form>

                        <div class="lg:col-span-1">
                            <x-border-form title="Rencana Tindakan" class="h-full">
                        {{-- RJ/UGD tak punya kelas kamar -> LOV tarif dasar, bukan varian -ri.
                             Payload-nya identik (accdoc_id / accdoc_desc / accdoc_price). --}}
                        <livewire:lov.jasa-dokter.lov-jasa-dokter target="order-ok-ugd-tindakan" label="Cari Tindakan Operasi"
                            wire:key="order-ok-ugd-tindakan-{{ $rjNo }}-{{ count($selectedTindakan) }}" />
                        @if (!empty($selectedTindakan))
                            <table class="w-full mt-3 text-sm text-left">
                                <thead class="text-xs font-semibold tracking-wide uppercase text-muted">
                                    <tr>
                                        <th class="px-2 py-1">Kode</th>
                                        <th class="px-2 py-1">Tindakan</th>
                                        <th class="px-2 py-1 text-right">Tarif</th>
                                        <th class="px-2 py-1 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                                    @foreach ($selectedTindakan as $item)
                                        <tr wire:key="rencana-{{ $item['accdoc_id'] }}">
                                            <td class="px-2 py-1 font-mono text-muted">{{ $item['accdoc_id'] }}</td>
                                            <td class="px-2 py-1 text-ink dark:text-gray-200">{{ $item['accdoc_desc'] }}</td>
                                            <td class="px-2 py-1 text-right text-ink dark:text-gray-200 tabular-nums">
                                                Rp {{ number_format($item['accdoc_price']) }}
                                            </td>
                                            <td class="px-2 py-1 text-center">
                                                <x-outline-button type="button" wire:click="hapusTindakan('{{ $item['accdoc_id'] }}')"
                                                    class="!px-2 !py-1 text-error border-error/40">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </x-outline-button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="border-t border-hairline dark:border-gray-700">
                                    <tr>
                                        <td colspan="2" class="px-2 py-2 text-sm font-semibold text-muted">Total rencana</td>
                                        <td class="px-2 py-2 text-sm font-bold text-right text-ink dark:text-white tabular-nums">
                                            Rp {{ number_format($this->totalRencana) }}
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        @else
                            <p class="mt-2 text-xs text-muted-soft">
                                Boleh dikosongkan — petugas OK bisa melengkapinya setelah operasi.
                            </p>
                        @endif
                            </x-border-form>
                        </div>

                        <div class="p-3 text-xs border rounded-xl lg:col-span-1 h-full border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200">
                        Order ini membuat transaksi berstatus <span class="font-semibold">Proses Transaksi</span> di modul Kamar Operasi.
                        Biaya <span class="font-semibold">belum</span> masuk tagihan pasien — baru masuk setelah petugas OK menekan
                        Trf Biaya-UGD.
                        </div>

                    </div>

                </div>
            </div>

            {{-- FOOTER --}}
            <div class="flex items-center justify-end gap-2 px-6 py-4 border-t shrink-0 border-hairline dark:border-gray-700">
                <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>
                <x-primary-button type="button" wire:click="kirimKamarOperasi" wire:loading.attr="disabled"
                    wire:target="kirimKamarOperasi">
                    <span wire:loading.remove wire:target="kirimKamarOperasi">Kirim ke Kamar Operasi</span>
                    <span wire:loading wire:target="kirimKamarOperasi" class="flex items-center gap-1.5">
                        <x-loading /> Mengirim...
                    </span>
                </x-primary-button>
            </div>

        </div>
    </x-modal>
</div>
