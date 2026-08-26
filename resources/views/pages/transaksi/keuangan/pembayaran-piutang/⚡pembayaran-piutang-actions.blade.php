<?php

/**
 * Pembayaran Piutang Pasien — Modal Proses Pembayaran (banyak nota sekaligus).
 *
 * Dua konsep pemakaian, satu mesin tulis yang sama:
 *   - mode 'bundel' : klaim BPJS per bulan — semua nota tercentang DILUNASI penuh,
 *                     nominal = total sisa saat proses (tidak bisa diketik).
 *   - mode 'pasien' : pasien umum — nominal bebas, dialokasikan FIFO ke nota
 *                     tercentang (nota terlama dulu); nota terakhir boleh cicilan.
 *
 * Tulis per jalur:
 *   RJ  : rstxn_rjcashins       + rstxn_rjhdrs.txn_status
 *   UGD : rstxn_ugdcashins      + rstxn_ugdhdrs.txn_status
 *   RI  : rstxn_ripaymentpdtls  + rstxn_rihdrs.status_pulang & ri_titip
 *
 * Beda dengan kasir (disengaja, keputusan user):
 *   - Tanggal baris kas = TANGGAL BAYAR SEBENARNYA (kasir memakai tanggal kunjungan).
 *   - RI: ri_titip DIAKUMULASI; kolom ri_bayar sengaja tidak disentuh.
 *
 * Sisa tiap nota selalu dihitung ulang dari database SETELAH baris header dikunci —
 * angka di layar tidak pernah dipakai menulis.
 */

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\Manajemen\Rs\Tu\PiutangPasienTrait;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use PiutangPasienTrait, EmrRJTrait, EmrUGDTrait, EmrRITrait, WithRenderVersioningTrait;

    public array $renderVersions = [];

    public string $mode = 'pasien';          // 'bundel' | 'pasien'
    public string $judulKonteks = '';         // mis. "Klaim BPJS 07/2026" / nama pasien
    public array $items = [];                 // [['jalur' => 'RJ', 'no' => '123'], ...]
    public array $baris = [];                 // ringkasan nota untuk ditampilkan
    public int $totalSisa = 0;

    public ?string $tanggal = null;
    public ?string $accId = null;
    public ?string $keterangan = null;
    public ?int $bayar = null;

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    /* ── Buka modal ── */
    #[On('piutang.openBayar')]
    public function openBayar(array $items, string $mode = 'pasien', string $judulKonteks = ''): void
    {
        $this->resetFormFields();

        $baris = $this->ambilBarisPiutang($items);

        if ($baris->isEmpty()) {
            $this->dispatch('toast', type: 'info', message: 'Tidak ada nota dengan sisa piutang untuk diproses.');
            $this->dispatch('piutang.paid');
            return;
        }

        $this->mode = $mode === 'bundel' ? 'bundel' : 'pasien';
        $this->judulKonteks = $judulKonteks;
        $this->items = $baris->map(fn($row) => ['jalur' => $row->jalur, 'no' => (string) $row->no_transaksi])->all();
        $this->baris = $baris->map(fn($row) => [
            'jalur' => $row->jalur,
            'no' => (string) $row->no_transaksi,
            'nama' => (string) ($row->reg_name ?? ''),
            'regNo' => (string) ($row->reg_no ?? ''),
            'tgl' => (string) ($row->tgl ?? ''),
            'sisa' => (int) $row->sisa,
        ])->all();

        $this->totalSisa = (int) $baris->sum('sisa');
        $this->tanggal = Carbon::now()->format('d/m/Y H:i:s');
        $this->bayar = $this->totalSisa;
        $this->keterangan = '';

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'pembayaran-piutang-actions');
        $this->dispatch('focus-piutang-tanggal');
    }

    /* ── LOV akun kas ── */
    #[On('lov.selected.kas-piutang')]
    public function onKasSelected(string $target, ?array $payload): void
    {
        $this->accId = $payload['acc_id'] ?? null;
        $this->dispatch($this->mode === 'bundel' ? 'focus-btn-proses' : 'focus-piutang-nominal');
    }

    public function setTanggalSekarang(): void
    {
        $this->tanggal = Carbon::now()->format('d/m/Y H:i:s');
    }

    /* ── Proses pembayaran ── */
    public function prosesBayar(): void
    {
        $this->validate(
            [
                'tanggal' => 'required|date_format:d/m/Y H:i:s',
                'accId' => 'required|string|exists:acmst_accounts,acc_id',
                'bayar' => 'required|integer|min:1',
            ],
            [
                'tanggal.required' => 'Tanggal pembayaran wajib diisi.',
                'tanggal.date_format' => 'Format tanggal harus dd/mm/yyyy hh:mm:ss.',
                'accId.required' => 'Akun kas wajib dipilih.',
                'accId.exists' => 'Akun kas tidak valid.',
                'bayar.required' => 'Nominal bayar wajib diisi.',
                'bayar.min' => 'Nominal bayar minimal Rp 1.',
            ],
        );

        if (empty($this->items)) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada nota terpilih.');
            return;
        }

        if ($this->akunKasBelumTerdaftar()) {
            $this->dispatch('toast', type: 'error', message: 'Akun kas Anda belum terkonfigurasi. Hubungi administrator.');
            return;
        }

        $empId = auth()->user()->emp_id ?? null;
        if (!$empId) {
            $this->dispatch('toast', type: 'error', message: 'EMP ID belum diisi di profil user. Hubungi administrator.');
            return;
        }

        $tanggalDb = "to_date('{$this->tanggal}','dd/mm/yyyy hh24:mi:ss')";
        $shift = $this->shiftPadaJam($this->tanggal);

        $terbayar = 0;
        $jumlahLunas = 0;
        $jumlahCicilan = 0;

        try {
            DB::transaction(function () use ($empId, $tanggalDb, $shift, &$terbayar, &$jumlahLunas, &$jumlahCicilan) {
                // 1. Kunci semua baris header terpilih dulu.
                foreach ($this->items as $item) {
                    match ($item['jalur']) {
                        'RJ' => $this->lockRJRow((int) $item['no']),
                        'UGD' => $this->lockUGDRow((int) $item['no']),
                        'RI' => $this->lockRIRow((int) $item['no']),
                        default => null,
                    };
                }

                // 2. Hitung ulang sisa tiap nota dari DB (urut FIFO — terlama dulu).
                $barisKini = $this->ambilBarisPiutang($this->items);

                if ($barisKini->isEmpty()) {
                    throw new \RuntimeException('Semua nota terpilih sudah lunas / diproses user lain.');
                }

                $totalKini = (int) $barisKini->sum('sisa');

                // Mode bundel selalu melunasi seluruh sisa saat ini (klaim BPJS per bulan).
                $bayar = $this->mode === 'bundel' ? $totalKini : (int) $this->bayar;

                if ($bayar > $totalKini) {
                    throw new \RuntimeException(
                        'Nominal bayar (Rp ' . number_format($bayar) . ') melebihi total sisa piutang saat ini (Rp ' . number_format($totalKini) . ').',
                    );
                }

                // 3. Alokasi FIFO ke tiap nota.
                $sisaBayar = $bayar;

                foreach ($barisKini as $row) {
                    if ($sisaBayar <= 0) {
                        break;
                    }

                    $sisaNota = (int) $row->sisa;
                    if ($sisaNota <= 0) {
                        continue;
                    }

                    $nominal = min($sisaBayar, $sisaNota);
                    $lunas = $nominal >= $sisaNota;

                    $catatan = 'Pelunasan piutang: Rp ' . number_format($nominal, 0, ',', '.')
                        . ($lunas ? ' (LUNAS)' : ' (sisa Rp ' . number_format($sisaNota - $nominal, 0, ',', '.') . ')')
                        . (filled($this->keterangan) ? ' — ' . $this->keterangan : '');

                    match ($row->jalur) {
                        'RJ' => $this->simpanBayarRj((int) $row->no_transaksi, $nominal, $empId, $tanggalDb, $shift, $lunas, $catatan),
                        'UGD' => $this->simpanBayarUgd((int) $row->no_transaksi, $nominal, $empId, $tanggalDb, $shift, $lunas, $catatan),
                        'RI' => $this->simpanBayarRi((int) $row->no_transaksi, $nominal, $empId, $tanggalDb, $shift, $lunas, $catatan),
                        default => null,
                    };

                    $lunas ? $jumlahLunas++ : $jumlahCicilan++;
                    $terbayar += $nominal;
                    $sisaBayar -= $nominal;
                }
            });

            $pesan = 'Pembayaran Rp ' . number_format($terbayar) . ' berhasil — ' . $jumlahLunas . ' nota lunas';
            if ($jumlahCicilan > 0) {
                $pesan .= ', ' . $jumlahCicilan . ' nota cicilan';
            }

            $this->dispatch('toast', type: 'success', message: $pesan . '.');
            $this->closeModal();
            $this->dispatch('piutang.paid');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan pembayaran: ' . $e->getMessage());
        }
    }

    /**
     * Ambil baris piutang untuk daftar nota, buang yang sudah lunas, urut FIFO
     * (tanggal transaksi terlama dulu). Tanggal dari query berupa teks dd/mm/yyyy
     * hh24:mi:ss, jadi diurutkan di PHP — jumlah barisnya kecil.
     */
    protected function ambilBarisPiutang(array $items): \Illuminate\Support\Collection
    {
        return $this->piutangBanyakTransaksi($items)
            ->filter(fn($row) => (int) $row->sisa > 0)
            ->sortBy(fn($row) => $this->kunciUrutTanggal((string) ($row->tgl ?? '')))
            ->values();
    }

    /** dd/mm/yyyy hh24:mi:ss → yyyymmddhhmmss (aman untuk sortBy string). */
    protected function kunciUrutTanggal(string $tanggal): string
    {
        try {
            return Carbon::createFromFormat('d/m/Y H:i:s', $tanggal)->format('YmdHis');
        } catch (\Throwable) {
            return '99999999999999';
        }
    }

    /* ===============================
     | TULIS PER JALUR
     =============================== */
    protected function simpanBayarRj(int $rjNo, int $nominal, string $empId, string $tanggalDb, string $shift, bool $lunas, string $catatan): void
    {
        $hdr = DB::table('rstxn_rjhdrs')->select('reg_no')->where('rj_no', $rjNo)->first();

        DB::table('rstxn_rjcashins')->insert([
            'acc_id' => $this->accId,
            'rjc_dtl' => DB::raw('rjcdtl_seq.nextval'),
            'rjc_date' => DB::raw($tanggalDb),
            // Format sengaja mengikuti kasir (reg_no / no_transaksi) + penanda PIUT,
            // panjang kolom rjc_desc di Oracle belum diverifikasi — jangan diperpanjang.
            'rjc_desc' => 'PIUT ' . ($hdr->reg_no ?? '') . ' / ' . $rjNo,
            'emp_id' => $empId,
            'rj_no' => $rjNo,
            'shift' => $shift,
            'rjc_nominal' => $nominal,
        ]);

        if ($lunas) {
            DB::table('rstxn_rjhdrs')->where('rj_no', $rjNo)->update([
                'txn_status' => 'L',
                'pay_date' => DB::raw($tanggalDb),
            ]);

            DB::table('rsmst_pasiens')->where('reg_no', $hdr->reg_no)->update(['lockstatus' => null]);
        }

        $this->appendAdminLogRJ($rjNo, $catatan);
    }

    protected function simpanBayarUgd(int $rjNo, int $nominal, string $empId, string $tanggalDb, string $shift, bool $lunas, string $catatan): void
    {
        $hdr = DB::table('rstxn_ugdhdrs')->select('reg_no')->where('rj_no', $rjNo)->first();

        DB::table('rstxn_ugdcashins')->insert([
            'acc_id' => $this->accId,
            'rjc_dtl' => DB::raw('rjcdtl_seq.nextval'),
            'rjc_date' => DB::raw($tanggalDb),
            'rjc_desc' => 'PIUT ' . ($hdr->reg_no ?? '') . ' / ' . $rjNo,
            'emp_id' => $empId,
            'rj_no' => $rjNo,
            'shift' => $shift,
            'rjc_nominal' => $nominal,
        ]);

        if ($lunas) {
            DB::table('rstxn_ugdhdrs')->where('rj_no', $rjNo)->update([
                'txn_status' => 'L',
                'pay_date' => DB::raw($tanggalDb),
            ]);

            DB::table('rsmst_pasiens')->where('reg_no', $hdr->reg_no)->update(['lockstatus' => null]);
        }

        $this->appendAdminLogUGD($rjNo, $catatan);
    }

    protected function simpanBayarRi(int $riHdrNo, int $nominal, string $empId, string $tanggalDb, string $shift, bool $lunas, string $catatan): void
    {
        DB::table('rstxn_ripaymentpdtls')->insert([
            'ripay_no' => DB::raw('ripayp_seq.nextval'),
            'ripay_date' => DB::raw($tanggalDb),
            'ripay_bayar' => $nominal,
            'rihdr_no' => $riHdrNo,
            'emp_id' => $empId,
            'acc_id' => $this->accId,
            'shift' => $shift,
        ]);

        // ri_titip = akumulasi uang yang sudah diterima (keputusan user).
        $ubah = ['ri_titip' => DB::raw('NVL(ri_titip,0) + ' . $nominal)];

        if ($lunas) {
            $ubah['status_pulang'] = 'L';
            $ubah['payment_date'] = DB::raw($tanggalDb);
        }

        DB::table('rstxn_rihdrs')->where('rihdr_no', $riHdrNo)->update($ubah);

        $this->appendAdminLogRI($riHdrNo, $catatan);
    }

    /* ===============================
     | GUARD & UTIL
     =============================== */
    protected function akunKasBelumTerdaftar(): bool
    {
        return DB::table('user_kas')->where('user_id', auth()->id())->count() === 0;
    }

    /** Shift sesuai JAM pembayaran (bukan jam sekarang) — pola rstxn_shiftctls. */
    protected function shiftPadaJam(string $tanggal): string
    {
        $jam = Carbon::createFromFormat('d/m/Y H:i:s', $tanggal)->format('H:i:s');

        return (string) (DB::table('rstxn_shiftctls')
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->whereRaw('? BETWEEN shift_start AND shift_end', [$jam])
            ->value('shift') ?? '1');
    }

    public function closeModal(): void
    {
        $this->resetFormFields();
        $this->dispatch('close-modal', name: 'pembayaran-piutang-actions');
        $this->resetVersion();
    }

    protected function resetFormFields(): void
    {
        $this->reset([
            'mode', 'judulKonteks', 'items', 'baris', 'totalSisa',
            'tanggal', 'accId', 'keterangan', 'bayar',
        ]);
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="pembayaran-piutang-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$mode, count($items)]) }}"
            x-data
            x-on:focus-piutang-tanggal.window="$nextTick(() => setTimeout(() => $refs.inputTanggal?.focus(), 150))"
            x-on:focus-piutang-nominal.window="$nextTick(() => setTimeout(() => { $refs.inputBayar?.focus(); $refs.inputBayar?.select(); }, 150))"
            x-on:focus-btn-proses.window="$nextTick(() => setTimeout(() => $refs.btnProses?.focus(), 150))">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah" class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah" class="hidden w-6 h-6 dark:block" />
                            </div>

                            <div>
                                <h2 class="text-2xl font-semibold text-ink dark:text-gray-100">
                                    {{ $mode === 'bundel' ? 'Pelunasan Klaim (Bundel)' : 'Pembayaran Piutang Pasien' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    {{ $mode === 'bundel'
                                        ? 'Seluruh nota tercentang dilunasi penuh; nominal mengikuti sisa saat diproses.'
                                        : 'Nominal dialokasikan FIFO ke nota tercentang — nota terlama dilunasi lebih dulu.' }}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 mt-3">
                            @if (filled($judulKonteks))
                                <x-badge variant="info">{{ $judulKonteks }}</x-badge>
                            @endif
                            <x-badge variant="gray">{{ count($baris) }} nota</x-badge>
                            <x-badge variant="warning">Sisa Rp {{ number_format($totalSisa) }}</x-badge>
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="space-y-4 max-w-5xl">

                    <x-border-form title="Data Pembayaran">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-12 items-start">
                                {{-- Tanggal --}}
                                <div class="sm:col-span-5">
                                    <x-input-label value="Tanggal Pembayaran" :required="true" />
                                    <div class="flex items-center gap-2 mt-1">
                                        <x-text-input type="text" wire:model="tanggal"
                                            placeholder="dd/mm/yyyy hh:mm:ss" class="w-full text-sm"
                                            x-ref="inputTanggal" :error="$errors->has('tanggal')"
                                            x-on:keydown.enter.prevent="($refs.lovKasWrapper?.querySelector('input:not([disabled])') || $refs.inputBayar || $refs.btnProses)?.focus()" />
                                        <x-now-button wire:click.prevent="setTanggalSekarang" />
                                    </div>
                                    <x-input-error :messages="$errors->get('tanggal')" class="mt-1" />
                                </div>

                                {{-- Akun Kas --}}
                                <div class="sm:col-span-7" x-ref="lovKasWrapper">
                                    <livewire:lov.kas.lov-kas
                                        target="kas-piutang"
                                        tipe=""
                                        label="Akun Kas (penerimaan)"
                                        placeholder="Ketik kode/nama kas..."
                                        :initialAccId="$accId"
                                        :error="$errors->has('accId')"
                                        wire:key="lov-kas-piutang-{{ $renderVersions['modal'] ?? 0 }}" />
                                    <x-input-error :messages="$errors->get('accId')" class="mt-1" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-12 items-start">
                                {{-- Nominal --}}
                                <div class="sm:col-span-5">
                                    <x-input-label value="Nominal Bayar (Rp)" :required="true" />
                                    <div class="mt-1">
                                        <x-text-input-number wire:model="bayar" class="text-sm"
                                            x-ref="inputBayar" :disabled="$mode === 'bundel'"
                                            :error="$errors->has('bayar')"
                                            x-on:keydown.enter.prevent="$el.blur(); $refs.inputKet?.focus()" />
                                    </div>
                                    <x-input-error :messages="$errors->get('bayar')" class="mt-1" />
                                    <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                        {{ $mode === 'bundel'
                                            ? 'Mode bundel: seluruh nota tercentang dilunasi, nominal mengikuti sisa saat diproses.'
                                            : 'Default = total sisa nota tercentang. Boleh lebih kecil untuk angsuran.' }}
                                    </p>
                                </div>

                                {{-- Keterangan --}}
                                <div class="sm:col-span-7">
                                    <x-input-label value="Keterangan (opsional)" />
                                    <x-text-input type="text" wire:model="keterangan"
                                        placeholder="Mis. transfer klaim BPJS Juli, dibayar keluarga..."
                                        class="w-full mt-1 text-sm" x-ref="inputKet"
                                        x-on:keydown.enter.prevent="$refs.btnProses?.focus()" />
                                </div>
                            </div>
                        </div>
                    </x-border-form>

                    {{-- Daftar nota yang akan dibayar --}}
                    <div class="overflow-hidden border border-hairline rounded-2xl bg-canvas dark:bg-gray-900 dark:border-gray-700">
                        <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
                            <h3 class="text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-400">
                                Nota yang diproses ({{ count($baris) }}) — urut FIFO
                            </h3>
                        </div>
                        <div class="overflow-x-auto max-h-72 overflow-y-auto">
                            <table class="w-full text-sm">
                                <thead class="sticky top-0 z-10 text-left text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-300">
                                    <tr>
                                        <th class="px-4 py-2 font-semibold">Jalur</th>
                                        <th class="px-4 py-2 font-semibold">No. Transaksi</th>
                                        <th class="px-4 py-2 font-semibold">Pasien</th>
                                        <th class="px-4 py-2 font-semibold">Tanggal</th>
                                        <th class="px-4 py-2 font-semibold text-right">Sisa</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-hairline dark:divide-gray-700">
                                    @foreach ($baris as $item)
                                        <tr wire:key="nota-{{ $item['jalur'] }}-{{ $item['no'] }}">
                                            <td class="px-4 py-2">
                                                @php $variant = ['RJ' => 'info', 'UGD' => 'danger', 'RI' => 'purple'][$item['jalur']] ?? 'gray'; @endphp
                                                <x-badge :variant="$variant">{{ $item['jalur'] }}</x-badge>
                                            </td>
                                            <td class="px-4 py-2 font-mono">{{ $item['no'] }}</td>
                                            <td class="px-4 py-2">
                                                <div class="text-ink dark:text-gray-200">{{ $item['nama'] }}</div>
                                                <div class="font-mono text-xs text-muted">{{ $item['regNo'] }}</div>
                                            </td>
                                            <td class="px-4 py-2 whitespace-nowrap text-muted dark:text-gray-400">{{ $item['tgl'] }}</td>
                                            <td class="px-4 py-2 font-mono text-right text-rose-700 dark:text-rose-300">
                                                Rp {{ number_format($item['sisa']) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                                    <tr>
                                        <td colspan="4" class="px-4 py-2 font-semibold text-muted dark:text-gray-400">Total Sisa</td>
                                        <td class="px-4 py-2 font-mono font-bold text-right text-ink dark:text-white">
                                            Rp {{ number_format($totalSisa) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-muted dark:text-gray-400">
                        Baris kas dicatat dengan <strong>tanggal pembayaran ini</strong>; nota yang tertutup penuh berubah menjadi Lunas.
                    </div>

                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>
                        <x-primary-button type="button" wire:click="prosesBayar" x-ref="btnProses"
                            wire:loading.attr="disabled" wire:target="prosesBayar">
                            <span wire:loading.remove wire:target="prosesBayar">Proses Pembayaran</span>
                            <span wire:loading wire:target="prosesBayar"><x-loading /> Memproses...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>
        </div>
    </x-modal>
</div>
