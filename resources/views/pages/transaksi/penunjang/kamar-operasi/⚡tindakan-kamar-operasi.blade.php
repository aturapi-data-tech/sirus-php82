<?php
// resources/views/pages/transaksi/penunjang/kamar-operasi/tindakan-kamar-operasi.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Txn\Penunjang\KamarOperasiTrait;
use App\Support\KamarOperasiTarif;

/**
 * Tab Tindakan Operasi (`rstxn_okacts`).
 *
 * Total tindakan membentuk pos `oprdoc_fee` — ditagihkan ke pasien SEKALIGUS
 * tercatat sebagai pendapatan dokter operator. Karena itu tiap tambah/hapus
 * memanggil KamarOperasiTarif::hitungUlang() supaya pos persentase ikut
 * menyesuaikan, dan seluruhnya diaudit ke kunjungan induk.
 */
new class extends Component {
    use KamarOperasiTrait, WithRenderVersioningTrait;

    public string $okReg = '';
    public bool $isFormLocked = true;
    /** Dipakai LOV tarif per kelas kamar; disimpan sebagai properti supaya
     view tidak perlu memanggil method trait yang protected. */
    public string $sumber = 'RI';
    public int $refNo = 0;

    public array $rows = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['form-tindakan'];

    /* ── Form tambah ── */
    public ?string $formAccdocId = null;
    public string $formAccdocDesc = '';
    public ?int $formHarga = null;

    public function mount(string $okReg = ''): void
    {
        $this->registerAreas($this->renderAreas);
        $this->okReg = $okReg;
        $this->findData();
    }

    #[On('kamar-operasi.updated')]
    public function findData(): void
    {
        if ($this->okReg === '') {
            $this->rows = [];
            return;
        }

        $this->isFormLocked = $this->statusOk($this->okReg) !== 'A';
        ['sumber' => $this->sumber, 'refNo' => $this->refNo] = $this->sumberRefOk($this->okReg);

        $this->rows = DB::table('rstxn_okacts as t')->leftJoin('rsmst_accdocs as a', 'a.accdoc_id', '=', 't.accdoc_id')->select('t.okact_id', 't.accdoc_id', 'a.accdoc_desc', 't.okact_price')->where('t.ok_reg', $this->okReg)->orderBy('t.okact_id')->get()->map(fn($tindakan) => (array) $tindakan)->toArray();
    }

    public function resetForm(): void
    {
        $this->reset(['formAccdocId', 'formAccdocDesc', 'formHarga']);
        $this->incrementVersion('form-tindakan');
        $this->dispatch('kamar-operasi-fokus', ke: 'ok-lov-tindakan');
    }

    private function bolehUbah(): bool
    {
        if (!$this->isAllowedRoleOk()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses.');
            return false;
        }

        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi sudah selesai/dibatalkan — data tidak bisa diubah.');
            $this->findData();
            return false;
        }

        return true;
    }

    #[On('lov.selected.kamar-operasi-tindakan')]
    public function pilihTindakan($target = null, $payload = null): void
    {
        $this->formAccdocId = $payload['accdoc_id'] ?? null;
        $this->formAccdocDesc = $payload['accdoc_desc'] ?? '';
        // accdoc_price dari lov-jasa-dokter-ri sudah harga efektif per kelas kamar pasien.
        $this->formHarga = isset($payload['accdoc_price']) ? (int) $payload['accdoc_price'] : null;

        // Rantai Enter: begitu tindakan terpilih, kursor lompat ke kolom Tarif.
        $this->dispatch('kamar-operasi-fokus', ke: 'ok-tarif-tindakan');
    }

    public function tambah(): void
    {
        if (!$this->bolehUbah()) {
            return;
        }

        if (empty($this->formAccdocId)) {
            $this->dispatch('toast', type: 'error', message: 'Pilih jenis tindakan terlebih dahulu.');
            return;
        }

        $validator = Validator::make(['harga' => $this->formHarga], ['harga' => 'bail|required|integer|min:0|max:999999999'], ['harga.required' => 'Tarif tindakan wajib diisi.', 'harga.integer' => 'Tarif tindakan harus angka bulat.']);

        if ($validator->fails()) {
            $this->dispatch('toast', type: 'error', message: $validator->errors()->first('harga'));
            return;
        }

        $sumber = $this->sumber;
        $refNo = $this->refNo;
        $accdocId = $this->formAccdocId;
        $harga = (int) $this->formHarga;
        $desc = $this->formAccdocDesc;
        $totalBaru = 0;

        $berhasil = $this->jalankanDenganRetryOk(function () use ($sumber, $refNo, $accdocId, $harga, $desc, &$totalBaru) {
            $row = $this->kunciBarisOk($this->okReg);

            $nomor = (int) DB::scalar('SELECT NVL(MAX(okact_id),0) + 1 FROM rstxn_okacts');

            DB::table('rstxn_okacts')->insert(['okact_id' => $nomor, 'accdoc_id' => $accdocId, 'okact_price' => $harga, 'ok_reg' => $this->okReg]);

            // Tindakan bertambah → jasa operator dan pos persentasenya ikut naik.
            [, $totalBaru] = KamarOperasiTarif::hitungUlang($this->okReg, $row);

            $this->catatLogOk($sumber, $refNo, "Tambah tindakan OK No.{$this->okReg} — {$accdocId} {$desc} Rp " . number_format($harga) . '. Total Rp ' . number_format($totalBaru));
        }, 'Gagal menambah tindakan');

        if (!$berhasil) {
            return;
        }

        $this->resetForm();
        $this->findData();
        $this->dispatch('kamar-operasi.updated');
        $this->dispatch('toast', type: 'success', message: 'Tindakan ditambahkan — total Rp ' . number_format($totalBaru) . '.');
        $this->dispatch('kamar-operasi-fokus', ke: 'ok-lov-tindakan');
    }

    public function hapus(int $okactId): void
    {
        if (!$this->bolehUbah()) {
            return;
        }

        $sumber = $this->sumber;
        $refNo = $this->refNo;
        $totalBaru = 0;

        $berhasil = $this->jalankanDenganRetryOk(function () use ($sumber, $refNo, $okactId, &$totalBaru) {
            $row = $this->kunciBarisOk($this->okReg);

            $baris = DB::table('rstxn_okacts')->where('okact_id', $okactId)->where('ok_reg', $this->okReg)->first();

            if (!$baris) {
                throw new \RuntimeException('Baris tindakan tidak ditemukan.');
            }

            DB::table('rstxn_okacts')->where('okact_id', $okactId)->where('ok_reg', $this->okReg)->delete();

            [, $totalBaru] = KamarOperasiTarif::hitungUlang($this->okReg, $row);

            $this->catatLogOk($sumber, $refNo, "Hapus tindakan OK No.{$this->okReg} — {$baris->accdoc_id} Rp " . number_format((int) $baris->okact_price) . '. Total Rp ' . number_format($totalBaru));
        }, 'Gagal menghapus tindakan');

        if (!$berhasil) {
            return;
        }

        $this->findData();
        $this->dispatch('kamar-operasi.updated');
        $this->dispatch('toast', type: 'success', message: 'Tindakan dihapus — total Rp ' . number_format($totalBaru) . '.');
    }
};
?>

<div>
    <p class="mb-3 text-xs text-muted dark:text-gray-400">
        Total tindakan membentuk pos <span class="font-semibold">Jasa Dokter Operator</span> —
        ditagihkan ke pasien, sekaligus tercatat sebagai pendapatan dokter operator.
    </p>

    @unless ($isFormLocked)
        <div
            class="grid grid-cols-1 gap-3 items-end p-3 mb-4 border rounded-xl border-hairline dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40 lg:grid-cols-12">
            {{-- Enter saat kolom cari masih kosong = selesai di tab ini. --}}
            <div class="lg:col-span-7" id="ok-lov-tindakan"
                x-on:keydown.enter="if (!$event.target.value?.trim()) $dispatch('kamar-operasi-lanjut-tab', { ke: 'BahanAlat' })">
                {{-- Tarif rawat inap bertingkat per kelas kamar → LOV khusus RI.
                     RJ & UGD tidak punya kelas kamar, jadi memakai LOV tarif dasar
                     yang sama dengan Administrasi RJ/UGD. Payload keduanya identik
                     (accdoc_id / accdoc_desc / accdoc_price). --}}
                @if ($sumber === 'RI')
                    <livewire:lov.jasa-dokter.lov-jasa-dokter-ri target="kamar-operasi-tindakan" label="Jenis Tindakan"
                        :riHdrNo="$refNo" :initialAccdocId="$formAccdocId"
                        wire:key="lov-tindakan-ri-{{ $okReg }}-{{ $formAccdocId }}" />
                @else
                    <livewire:lov.jasa-dokter.lov-jasa-dokter target="kamar-operasi-tindakan" label="Jenis Tindakan"
                        :initialAccdocId="$formAccdocId"
                        wire:key="lov-tindakan-{{ $sumber }}-{{ $okReg }}-{{ $formAccdocId }}" />
                @endif
            </div>
            <div class="lg:col-span-3">
                <x-input-label value="Tarif" />
                {{-- Enter: ada isi -> simpan; kosong -> pindah tab berikutnya. --}}
                <x-text-input-number id="ok-tarif-tindakan" wire:model="formHarga" placeholder="0"
                    wire:key="{{ $this->renderKey('form-tindakan', 'tarif') }}"
                    x-on:keydown.enter.prevent="
                        const kosong = $el.value.replace(/\D/g, '') === '';
                        $el.blur();
                        kosong ? $dispatch('kamar-operasi-lanjut-tab', { ke: 'BahanAlat' }) : $wire.tambah()
                    " />
            </div>
            <div class="flex items-end lg:col-span-2">
                <x-icon-button color="gray" type="button" wire:click.prevent="resetForm"
                    title="Batal — kosongkan form entri">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </x-icon-button>
            </div>

            <div class=" lg:col-span-5">
                {{-- Petunjuk cara simpan — tombol Tambah ditiadakan --}}
                <p class="mt-3 text-xs text-muted dark:text-gray-400">
                    Tekan <span
                        class="px-1.5 py-0.5 font-semibold rounded border border-hairline bg-canvas text-body dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">Enter</span>
                    di kolom terakhir untuk menyimpan.
                </p>
            </div>

        </div>

    @endunless

    <div class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead
                    class="text-sm font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300 bg-surface-soft dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Jenis Tindakan</th>
                        <th class="px-4 py-3 text-right">Tarif</th>
                        @unless ($isFormLocked)
                            <th class="px-4 py-3 text-center">Aksi</th>
                        @endunless
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                    @forelse ($rows as $row)
                        <tr wire:key="tindakan-{{ $row['okact_id'] }}"
                            class="transition hover:bg-surface-soft dark:hover:bg-gray-800/40">
                            <td class="px-4 py-1.5 font-mono text-muted">{{ $row['accdoc_id'] ?? '-' }}</td>
                            <td class="px-4 py-1.5 text-ink dark:text-gray-200">{{ $row['accdoc_desc'] ?? '-' }}</td>
                            <td class="px-4 py-1.5 font-semibold text-right text-ink dark:text-gray-200 tabular-nums">
                                Rp {{ number_format($row['okact_price'] ?? 0) }}
                            </td>
                            @unless ($isFormLocked)
                                <td class="px-4 py-1.5 text-center">
                                    <x-outline-button type="button" wire:click.prevent="hapus({{ $row['okact_id'] }})"
                                        wire:confirm="Hapus tindakan ini? Jasa dokter operator akan dihitung ulang."
                                        wire:loading.attr="disabled"
                                        class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300 !px-2 !py-1"
                                        title="Hapus tindakan">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </x-outline-button>
                                </td>
                            @endunless
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-muted-soft">Belum ada tindakan operasi
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (!empty($rows))
                    <tfoot class="border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="2" class="px-4 py-3 text-sm font-semibold text-muted dark:text-gray-400">
                                Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-ink dark:text-white">
                                Rp {{ number_format(collect($rows)->sum('okact_price')) }}
                            </td>
                            @unless ($isFormLocked)
                                <td></td>
                            @endunless
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
