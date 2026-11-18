<?php

/**
 * Topup Supplier PBF (medis) — setor uang muka (DP) ke supplier.
 *
 * Equivalent dgn Oracle Forms procedure post_transaksi_topup:
 *   - INSERT 1x IMTXN_CASHOUTHDRTOPUPS (saldo titipan, tidak link ke nota)
 *   - Jurnal "BAYAR DIMUKA PBF": kas berkurang, uang muka (RCV6) bertambah
 *
 * Saldo DP = App\Support\Keuangan\SaldoDpSupplier (satu rumus untuk semua layar):
 *   saldo awal tahun (tutup buku Forms) + setor − terpakai sejak tahun itu + Σ opname.
 * Pemakaian DP ada di menu Pembayaran Hutang PBF (pola Forms: DP dipakai sendirian, tak
 * dicampur tunai). Hapus salah setor ditolak bila saldo DP jadi minus (DP sudah terpakai).
 *
 * Opname saldo DP ada di menu Kartu DP Supplier (keuangan/kartu-dp-supplier, khusus Admin);
 * di sini barisnya hanya tampil di riwayat & rekap.
 */

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Support\Keuangan\SaldoDpSupplier;

new class extends Component {
    use WithRenderVersioningTrait;

    /** Hapus salah setor — sama dengan hapus salah bayar hutang/piutang. */
    private const ROLE_HAPUS_SETOR = ['Admin', 'Supervisor Tu'];
    private const JENIS = SaldoDpSupplier::JENIS_PBF;

    public ?string $suppId   = null;
    public ?array  $supplier = null;
    public ?string $accId    = null;

    public ?string $tanggal    = null;
    /** Shift kas keluar — otomatis dari jam Tanggal, tetap bisa dipilih manual. */
    public ?string $shift      = null;
    public ?string $keterangan = null;
    public ?int    $nominal    = null;
    /** Ringkasan untuk dialog konfirmasi — kosong = dialog tertutup. Hanya tampilan. */
    public array   $pratinjau  = [];

    /* ── Rekap saldo DP semua supplier (tampil selama belum ada supplier dipilih) ── */
    public string  $cariRekap        = '';
    /** false = hanya supplier yang saldonya masih ada; true = semua yang pernah setor. */
    public bool    $rekapSemua       = false;
    /** Tahun rekap (pola saldo kas): saldo awal 1 Jan → mutasi tahun itu → saldo akhir. */
    public int     $tahunRekap       = 0;
    /** Filter rekap = pilihan Semua/Medis/Non-Medis di LOV supplier ('all' | 'medis' | 'nonmedis'). */
    public string  $jenisRekap       = 'medis';

    public array $renderVersions = [];

    public function mount(): void
    {
        $this->registerAreas(['form']);
        $this->setTanggalSekarang();
        $this->tahunRekap = (int) now()->year;
    }

    /* ── LOV Listeners ── */
    #[On('lov.selected.topup-pbf-supplier')]
    public function onSupplierSelected(string $target, ?array $payload): void
    {
        $this->suppId   = $payload['supp_id'] ?? null;
        $this->supplier = $payload;
        $this->resetErrorBag('suppId');
        unset($this->riwayat, $this->ringkasan, $this->rekapSaldo);
    }

    #[On('lov.jenis.topup-pbf-supplier')]
    public function onJenisSupplier(string $target, string $jenis): void
    {
        $this->jenisRekap = in_array($jenis, ['all', 'medis', 'nonmedis'], true) ? $jenis : 'all';
        unset($this->rekapSaldo);
    }

    #[On('lov.selected.topup-pbf-kas')]
    public function onKasSelected(string $target, ?array $payload): void
    {
        $this->accId = $payload['acc_id'] ?? null;
        $this->resetErrorBag('accId');
    }

    public function clearSupplier(): void
    {
        $this->reset(['suppId', 'supplier', 'accId', 'nominal', 'keterangan', 'pratinjau']);
        $this->setTanggalSekarang();
        $this->resetValidation();
        $this->incrementVersion('form');
        unset($this->riwayat, $this->ringkasan, $this->rekapSaldo);
    }

    public function setTanggalSekarang(): void
    {
        $this->tanggal = Carbon::now()->format('d/m/Y H:i:s');
        $this->updatedTanggal($this->tanggal);
    }

    /** Tanggal diketik ulang → shift menyesuaikan jamnya (jam di luar master = biarkan, pilih manual). */
    public function updatedTanggal(?string $value): void
    {
        $shift = $this->shiftPadaJam((string) $value);
        if ($shift !== null) {
            $this->shift = $shift;
            $this->resetErrorBag('shift');
        }
    }

    /** Klik baris rekap → supplier itu dibuka (sama dengan memilih lewat LOV). */
    public function pilihDariRekap(string $suppId): void
    {
        $row = DB::table('immst_suppliers')->where('supp_id', $suppId)->first(['supp_id', 'supp_name', 'phone', 'address']);
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Supplier tidak ditemukan.');
            return;
        }
        $this->onSupplierSelected('rekap', [
            'supp_id' => (string) $row->supp_id,
            'supp_name' => (string) ($row->supp_name ?? ''),
            'phone' => (string) ($row->phone ?? ''),
            'address' => (string) ($row->address ?? ''),
        ]);
    }

    /* ── Data ── */

    /** Saldo DP semua supplier per tahun — SaldoDpSupplier::rekapTahun, transaksi terakhir di atas. */
    #[Computed]
    public function rekapSaldo(): array
    {
        if ($this->suppId) {
            return [];
        }

        $this->tahunRekap = max(2020, min((int) $this->tahunRekap, (int) now()->year));

        return SaldoDpSupplier::rekapTahun(self::JENIS, $this->tahunRekap, $this->cariRekap, $this->rekapSemua, $this->jenisRekap);
    }

    #[Computed]
    public function ringkasan(): array
    {
        if (!$this->suppId) {
            return ['tahunAwal' => null, 'awal' => 0, 'setor' => 0, 'pakai' => 0, 'opname' => 0, 'saldo' => 0, 'dari' => '1900-01-01'];
        }

        return $this->hitungSaldoDp($this->suppId);
    }

    /**
     * Setor, pemakaian, & opname DP supplier ini sejak tahun saldo awal (+ semua opname),
     * terbaru di atas, ditutup baris "Saldo awal" tahun itu.
     */
    #[Computed]
    public function riwayat(): array
    {
        if (!$this->suppId) {
            return [];
        }

        $ringkas = $this->ringkasan;
        $dari = $ringkas['dari'];

        $setor = DB::table('imtxn_cashouthdrtopups as t')
            ->leftJoin('acmst_accounts as a', 'a.acc_id', '=', 't.acc_id')
            ->where('t.supp_id', $this->suppId)
            ->whereRaw("t.cashout_date >= TO_DATE(?,'YYYY-MM-DD')", [$dari])
            ->orderByDesc('t.cashout_date')
            ->limit(60)
            ->get([
                't.cashout_no', 't.cashout_desc', 't.acc_id', 'a.acc_name', 't.shift',
                DB::raw('NVL(t.cashout_value,0) as nilai'),
                DB::raw("to_char(t.cashout_date,'dd/mm/yyyy hh24:mi:ss') as tgl"),
                DB::raw("to_char(t.cashout_date,'yyyymmddhh24miss') as urut"),
            ])
            ->map(fn ($row) => [
                'jenis' => 'setor',
                'no' => (int) $row->cashout_no,
                'tgl' => (string) $row->tgl,
                'urut' => (string) $row->urut,
                'ket' => (string) ($row->cashout_desc ?? ''),
                'akun' => trim(($row->acc_name ?? '') . ' (' . $row->acc_id . ')'),
                'shift' => (string) ($row->shift ?? ''),
                'nilai' => (int) round((float) $row->nilai),
                'bolehHapus' => $this->bolehHapusSetor((string) $row->tgl),
            ]);

        $pakai = DB::table('imtxn_cashouthdrs as c')
            ->where('c.supp_id', $this->suppId)
            ->whereRaw('NVL(c.cashout_value_topup,0) > 0')
            ->whereRaw("c.cashout_date >= TO_DATE(?,'YYYY-MM-DD')", [$dari])
            ->orderByDesc('c.cashout_date')
            ->limit(60)
            ->get([
                'c.cashout_no', 'c.cashout_desc', 'c.shift',
                DB::raw('NVL(c.cashout_value_topup,0) as nilai'),
                DB::raw("to_char(c.cashout_date,'dd/mm/yyyy hh24:mi:ss') as tgl"),
                DB::raw("to_char(c.cashout_date,'yyyymmddhh24miss') as urut"),
            ]);

        $notaPerKas = DB::table('imtxn_cashoutdtls')
            ->whereIn('cashout_no', $pakai->pluck('cashout_no')->all() ?: [-1])
            ->get(['cashout_no', 'rcv_no'])
            ->groupBy('cashout_no')
            ->map(fn ($baris) => $baris->pluck('rcv_no')->implode(', '));

        $pakai = $pakai->map(fn ($row) => [
            'jenis' => 'pakai',
            'no' => (int) $row->cashout_no,
            'tgl' => (string) $row->tgl,
            'urut' => (string) $row->urut,
            'ket' => 'Pelunasan nota ' . ($notaPerKas[$row->cashout_no] ?? '-'),
            'akun' => 'Saldo DP',
            'shift' => (string) ($row->shift ?? ''),
            'nilai' => (int) round((float) $row->nilai),
            'bolehHapus' => false, // dihapus dari menu Pembayaran Hutang PBF
        ]);

        // Opname dijumlah semua tahun (tutup buku Forms tak mengenalnya) → tampil semua.
        $opname = DB::table('tktxn_sodpsupps as o')
            ->leftJoin('immst_employers as e', 'e.emp_id', '=', 'o.emp_id')
            ->where('o.supp_id', $this->suppId)
            ->orderByDesc('o.so_date')
            ->get([
                'o.so_no', 'o.so_desc', 'o.shift', 'o.saldo_sistem', 'o.saldo_fisik', 'e.emp_name',
                DB::raw('NVL(o.so_d,0) - NVL(o.so_k,0) as nilai'),
                DB::raw("to_char(o.so_date,'dd/mm/yyyy hh24:mi:ss') as tgl"),
                DB::raw("to_char(o.so_date,'yyyymmddhh24miss') as urut"),
            ])
            ->map(fn ($row) => [
                'jenis' => 'opname',
                'no' => (int) $row->so_no,
                'tgl' => (string) $row->tgl,
                'urut' => (string) $row->urut,
                'ket' => 'Sistem Rp ' . number_format((float) $row->saldo_sistem, 0, ',', '.')
                    . ' → sebenarnya Rp ' . number_format((float) $row->saldo_fisik, 0, ',', '.')
                    . ' · ' . $row->so_desc,
                'akun' => 'Opname' . (filled($row->emp_name) ? ' · ' . $row->emp_name : ''),
                'shift' => (string) ($row->shift ?? ''),
                'nilai' => (int) round((float) $row->nilai),
                'bolehHapus' => false, // jejak koreksi — tidak dihapus (pola opname stok)
            ]);

        $baris = $setor->concat($pakai)->concat($opname)->sortByDesc('urut')->take(60)->values();

        if ($ringkas['tahunAwal'] !== null) {
            $baris->push([
                'jenis' => 'awal',
                'no' => (int) $ringkas['tahunAwal'],
                'tgl' => '01/01/' . $ringkas['tahunAwal'],
                'urut' => $ringkas['tahunAwal'] . '0100000000',
                'ket' => 'Saldo awal tahun ' . $ringkas['tahunAwal'] . ' (tutup buku)',
                'akun' => '-',
                'shift' => '',
                'nilai' => $ringkas['awal'],
                'bolehHapus' => false,
            ]);
        }

        return $baris->all();
    }

    /* ── Simpan: langkah 1 konfirmasi, langkah 2 tulis ── */

    protected function rules(): array
    {
        return [
            'suppId'  => 'required|string',
            'tanggal' => 'required|date_format:d/m/Y H:i:s',
            'shift'   => 'required|in:1,2,3',
            'accId'   => 'required|string|exists:acmst_accounts,acc_id',
            'nominal' => 'required|integer|min:1',
        ];
    }

    protected function messages(): array
    {
        return [
            'suppId.required'     => 'Supplier wajib dipilih.',
            'tanggal.required'    => 'Tanggal wajib diisi.',
            'tanggal.date_format' => 'Format tanggal harus dd/mm/yyyy hh:mm:ss.',
            'shift.required'      => 'Shift belum dipilih.',
            'shift.in'            => 'Shift tidak valid.',
            'accId.required'      => 'Akun kas wajib dipilih.',
            'accId.exists'        => 'Akun kas tidak valid.',
            'nominal.required'    => 'Nominal wajib diisi.',
            'nominal.min'         => 'Nominal minimal Rp 1.',
        ];
    }

    public function siapkanKonfirmasi(): void
    {
        $this->validate();

        $this->pratinjau = [
            'nominal' => (int) $this->nominal,
            'accName' => (string) (DB::table('acmst_accounts')->where('acc_id', $this->accId)->value('acc_name') ?? $this->accId),
            'saldoSesudah' => $this->ringkasan['saldo'] + (int) $this->nominal,
        ];
    }

    public function batalKonfirmasi(): void
    {
        $this->pratinjau = [];
    }

    public function simpan(): void
    {
        $this->validate();
        $this->pratinjau = [];

        $empId = auth()->user()->emp_id ?? null;
        if (!$empId) {
            $this->dispatch('toast', type: 'error',
                message: 'Profil pegawai Anda belum di-mapping (emp_id). Hubungi admin.');
            return;
        }

        $tanggalDb = "to_date('{$this->tanggal}','dd/mm/yyyy hh24:mi:ss')";
        // cashout_desc VARCHAR2(100)
        $desc = mb_substr('Topup Atas Nama "' . ($this->supplier['supp_name'] ?? '-') . '"'
            . (!empty($this->keterangan) ? ' - ' . $this->keterangan : ''), 0, 100);

        try {
            DB::transaction(function () use ($empId, $tanggalDb, $desc) {
                $cashoutNo = (int) DB::selectOne('SELECT cashout_seq.nextval AS val FROM dual')->val;

                DB::table('imtxn_cashouthdrtopups')->insert([
                    'cashout_no'    => $cashoutNo,
                    'cashout_date'  => DB::raw($tanggalDb),
                    'cashout_desc'  => $desc,
                    'cashout_value' => (int) $this->nominal,
                    'emp_id'        => $empId,
                    'supp_id'       => $this->suppId,
                    'acc_id'        => $this->accId,
                    'shift'         => (string) $this->shift,
                ]);
            });

            $this->dispatch('toast', type: 'success',
                message: 'Topup Rp ' . number_format($this->nominal) . ' tersimpan untuk ' . ($this->supplier['supp_name'] ?? '-') . '.');
            // supplier + kas tetap (user mungkin mau topup lagi)
            $this->reset(['nominal', 'keterangan']);
            $this->setTanggalSekarang();
            unset($this->riwayat, $this->ringkasan, $this->rekapSaldo);
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal simpan topup: ' . $e->getMessage());
        }
    }

    /* ── Hapus salah setor ── */

    protected function bolehHapusSetor(string $tanggal): bool
    {
        if (!auth()->user()?->hasAnyRole(self::ROLE_HAPUS_SETOR)) {
            return false;
        }
        try {
            return Carbon::createFromFormat('d/m/Y H:i:s', $tanggal)->year === (int) now()->year;
        } catch (\Throwable) {
            return false;
        }
    }

    public function hapusSetor(int $cashoutNo): void
    {
        try {
            $hasil = DB::transaction(function () use ($cashoutNo) {
                // Kunci supplier: cegah DP dipakai bersamaan di Pembayaran Hutang saat setoran dicabut.
                DB::table('immst_suppliers')->where('supp_id', $this->suppId)->lockForUpdate()->first(['supp_id']);

                $setor = DB::table('imtxn_cashouthdrtopups')->where('cashout_no', $cashoutNo)->lockForUpdate()->first([
                    'cashout_no', 'supp_id', 'acc_id', 'shift',
                    DB::raw('NVL(cashout_value,0) as nilai'),
                    DB::raw("to_char(cashout_date,'dd/mm/yyyy hh24:mi:ss') as tgl"),
                ]);
                if (!$setor || (string) $setor->supp_id !== (string) $this->suppId) {
                    throw new \RuntimeException('Data setoran tidak ditemukan.');
                }
                if (!$this->bolehHapusSetor((string) $setor->tgl)) {
                    throw new \RuntimeException('Setoran ini tidak boleh dihapus (beda tahun atau Anda tidak berwenang).');
                }

                $nilai = (int) round((float) $setor->nilai);
                $saldo = $this->hitungSaldoDp($this->suppId)['saldo'];
                if ($saldo - $nilai < 0) {
                    throw new \RuntimeException('Tidak bisa dihapus — saldo DP tinggal Rp ' . number_format($saldo)
                        . ', sebagian setoran ini sudah dipakai melunasi hutang. Hapus dulu pelunasannya di menu Pembayaran Hutang PBF.');
                }

                DB::table('imtxn_cashouthdrtopups')->where('cashout_no', $cashoutNo)->delete();

                return ['nilai' => $nilai, 'setor' => $setor];
            });
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
            return;
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus setoran: ' . $e->getMessage());
            return;
        }

        Log::info('Hapus setoran DP supplier PBF (salah setor)', [
            'cashout_no' => $cashoutNo,
            'supp_id' => $this->suppId,
            'nilai' => $hasil['nilai'],
            'tanggal' => $hasil['setor']->tgl,
            'shift' => $hasil['setor']->shift,
            'acc_id' => $hasil['setor']->acc_id,
            'user' => auth()->user()?->myuser_code,
        ]);

        unset($this->riwayat, $this->ringkasan, $this->rekapSaldo);
        $this->dispatch('toast', type: 'success', message: 'Setoran DP Rp ' . number_format($hasil['nilai'])
            . ' dihapus. Saldo kas pada tanggal setor tersebut ikut berubah.');
    }

    /* ── Helper ── */

    /** Saldo DP supplier = Σ setor − Σ pemakaian (cashout_value_topup). */
    /** Rincian saldo DP — satu rumus (SaldoDpSupplier). */
    protected function hitungSaldoDp(string $suppId): array
    {
        return SaldoDpSupplier::rincian(self::JENIS, $suppId);
    }


    protected function shiftPadaJam(string $tanggal): ?string
    {
        try {
            $jam = Carbon::createFromFormat('d/m/Y H:i:s', $tanggal)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }

        $shift = DB::table('rstxn_shiftctls')
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->whereRaw('? BETWEEN shift_start AND shift_end', [$jam])
            ->value('shift');

        return $shift !== null ? (string) $shift : null;
    }
};
?>

<div>
    <x-page-title
        title="Topup Supplier PBF"
        subtitle="Setor DP/uang muka ke supplier obat — saldonya dipakai melunasi hutang di menu Pembayaran Hutang PBF" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">
            <div class="flex flex-col flex-1 min-h-0 gap-4 mt-2 lg:flex-row">

                {{-- KOLOM KIRI — supplier, saldo DP, form setor --}}
                <div class="flex flex-col gap-5 p-5 overflow-y-auto border shadow-sm shrink-0 lg:w-96 bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700"
                    wire:key="{{ $this->renderKey('form', [$suppId]) }}">

                    {{-- PANDUAN (biru-info, collapsible, default tertutup) --}}
                    <div x-data="{ open: false }"
                        class="overflow-hidden border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
                        <button type="button" @click="open = !open"
                            class="flex items-center justify-between w-full px-3 py-2 text-sm font-semibold text-blue-900 transition-colors hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Cara Pakai — Topup (Uang Muka) Supplier
                            </span>
                            <svg class="w-4 h-4 text-blue-600 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" x-collapse class="px-3 pb-3 text-xs text-blue-900 dark:text-blue-200">
                            <ul class="ml-4 space-y-1 list-disc">
                                <li>Setor DP = uang keluar dari kas sebagai uang muka ke supplier; <strong>Saldo DP</strong> bertambah.</li>
                                <li>Saldo DP dipakai melunasi nota hutang di menu <strong>Pembayaran Hutang PBF</strong> → Sumber Dana: Saldo DP.</li>
                                <li>Sebelum memilih supplier, kanan menampilkan <strong>Rekap Saldo DP per tahun</strong> (seperti saldo kas): saldo awal 1 Jan → disetor, terpakai, opname → saldo akhir; transaksi terakhir paling atas. Saldo akhir sebuah tahun = saldo awal tahun berikutnya. Klik baris untuk membuka supplier.</li>
                                <li>Saldo DP = saldo awal tahun (tutup buku) + disetor − terpakai ± opname. Riwayat di kanan menampilkan setor (+) dan pemakaian (−).</li>
                                <li>Shift terisi otomatis dari jam Tanggal; periksa dialog konfirmasi sebelum menyimpan.</li>
                                <li>Selisih saldo DP dengan kartu supplier dibetulkan lewat menu <strong>Kartu DP Supplier</strong> → Opname (khusus Admin); barisnya tampil di riwayat sebagai <em>Opname</em>.</li>
                                <li><strong>Hapus salah setor</strong> (Admin / Supervisor Tu, tahun berjalan) ditolak bila DP-nya sudah terpakai — hapus dulu pelunasannya di menu Pembayaran Hutang PBF.</li>
                            </ul>
                        </div>
                    </div>

                    <div>
                        @if ($suppId)
                            <div class="flex items-start justify-between gap-2">
                                <x-input-label value="Supplier" />
                                <x-secondary-button type="button" wire:click="clearSupplier"
                                    title="Ganti supplier / mulai transaksi baru"><x-ikon nama="ubah" /> Ganti</x-secondary-button>
                            </div>
                            <div class="px-4 py-3 mt-2 border rounded-xl border-hairline bg-surface-soft dark:bg-gray-800 dark:border-gray-700">
                                <div class="font-semibold text-ink dark:text-gray-100">{{ $supplier['supp_name'] ?? '-' }}</div>
                                <div class="font-mono text-xs text-muted dark:text-gray-400">Kode {{ $suppId }}</div>
                            </div>
                        @else
                            <livewire:lov.supplier.lov-supplier
                                target="topup-pbf-supplier"
                                label="Cari Supplier"
                                placeholder="Ketik kode / nama / telp supplier..."
                                :jenisSupplier="$jenisRekap"
                                wire:key="lov-supplier-topup-pbf" />
                        @endif
                    </div>

                    @if ($suppId)
                        <div class="pt-4 space-y-4 border-t border-hairline dark:border-gray-700">
                            <div class="text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-300">Setor DP</div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
                                <div class="sm:col-span-8">
                                    <x-input-label value="Tanggal" :required="true" />
                                    <div class="flex items-center gap-2 mt-1">
                                        <x-text-input type="text" wire:model.live.debounce.500ms="tanggal"
                                            placeholder="dd/mm/yyyy hh:mm:ss" class="w-full text-sm" :error="$errors->has('tanggal')" />
                                        <x-now-button wire:click.prevent="setTanggalSekarang" />
                                    </div>
                                    <x-input-error :messages="$errors->get('tanggal')" class="mt-1" />
                                </div>
                                <div class="sm:col-span-4">
                                    <x-input-label value="Shift" :required="true" />
                                    <x-select-input wire:model="shift" class="w-full mt-1" :error="$errors->has('shift')">
                                        <option value="">-- Shift --</option>
                                        <option value="1">Shift 1</option>
                                        <option value="2">Shift 2</option>
                                        <option value="3">Shift 3</option>
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('shift')" class="mt-1" />
                                </div>
                            </div>

                            {{-- Akun Kas & Nominal sebaris --}}
                            <div class="grid items-start grid-cols-1 gap-4 sm:grid-cols-12">
                            <div class="sm:col-span-7">
                                <livewire:lov.kas.lov-kas
                                    target="topup-pbf-kas"
                                    tipe=""
                                    label="Akun Kas (sumber dana)"
                                    placeholder="Ketik kode/nama kas..."
                                    :initialAccId="$accId"
                                    :error="$errors->has('accId')"
                                    wire:key="lov-kas-topup-pbf-{{ $suppId }}-{{ $renderVersions['form'] ?? 0 }}" />
                                <x-input-error :messages="$errors->get('accId')" class="mt-1" />
                            </div>

                            <div class="sm:col-span-5">
                                <x-input-label value="Nominal Setor (Rp)" :required="true" />
                                <div class="mt-1">
                                    <x-text-input-number wire:model="nominal" :error="$errors->has('nominal')" />
                                </div>
                                <x-input-error :messages="$errors->get('nominal')" class="mt-1" />
                            </div>
                            </div>

                            <div>
                                <x-input-label value="Keterangan (opsional)" />
                                <x-text-input type="text" wire:model="keterangan" maxlength="60"
                                    placeholder="Misal: DP order obat batch 2026-05" class="w-full mt-1" />
                            </div>

                            <x-primary-button type="button" wire:click="siapkanKonfirmasi" class="justify-center w-full h-11"
                                wire:loading.attr="disabled" wire:target="siapkanKonfirmasi">
                                <span wire:loading.remove wire:target="siapkanKonfirmasi" class="inline-flex items-center gap-2"><x-ikon nama="simpan" /> Simpan Setoran</span>
                                <span wire:loading wire:target="siapkanKonfirmasi"><x-loading /> Memeriksa...</span>
                            </x-primary-button>
                        </div>

                    @endif
                </div>

                {{-- KOLOM KANAN — rekap saldo DP (belum pilih supplier) / riwayat setor & pemakaian --}}
                @if (!$suppId)
                    @php
                        $rekap = $this->rekapSaldo;
                        $totalRekap = collect($rekap);
                    @endphp
                    <div class="flex flex-col flex-1 min-w-0 min-h-0 border shadow-sm bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 border-b border-hairline dark:border-gray-700">
                            <div class="text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-300">
                                Rekap Saldo DP Supplier {{ $tahunRekap }}{{ ['medis' => ' · Medis', 'nonmedis' => ' · Non-Medis'][$jenisRekap] ?? '' }}
                                <span class="font-normal normal-case">({{ count($rekap) }} supplier{{ $tahunRekap === (int) now()->year ? ', s.d. hari ini' : '' }})</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <x-select-input wire:model.live="tahunRekap" class="w-28 text-sm" title="Tahun rekap">
                                    @for ($th = (int) now()->year; $th >= 2020; $th--)
                                        <option value="{{ $th }}">{{ $th }}</option>
                                    @endfor
                                </x-select-input>
                                <x-text-input type="text" wire:model.live.debounce.400ms="cariRekap"
                                    placeholder="Cari nama / kode supplier..." class="w-56 text-sm" />
                                <x-toggle wire:model.live="rekapSemua" :trueValue="true" :falseValue="false" label="Tampilkan saldo 0"
                                    title="Nyala = semua supplier yang pernah punya DP, mati = hanya yang bersaldo / bertransaksi tahun ini" />
                            </div>
                        </div>

                        <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto">
                            <table class="w-full min-w-full text-sm">
                                <thead class="sticky top-0 z-10 text-left text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-300">
                                    <tr class="text-xs font-semibold tracking-wide uppercase">
                                        <th class="px-4 py-3">Supplier</th>
                                        <th class="px-4 py-3">Transaksi Terakhir</th>
                                        <th class="px-4 py-3 text-right">Saldo Awal {{ $tahunRekap }}</th>
                                        <th class="px-4 py-3 text-right">Disetor</th>
                                        <th class="px-4 py-3 text-right">Terpakai</th>
                                        <th class="px-4 py-3 text-right">Opname</th>
                                        <th class="px-4 py-3 text-right">Saldo Akhir</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y text-body divide-hairline dark:divide-gray-700 dark:text-gray-200">
                                    @forelse ($rekap as $row)
                                        <tr wire:key="rekap-dp-{{ $row['suppId'] }}"
                                            wire:click="pilihDariRekap(@js($row['suppId']))"
                                            title="Klik untuk membuka saldo & riwayat DP supplier ini"
                                            class="transition cursor-pointer hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                            <td class="px-4 py-2.5">
                                                <div class="font-medium text-ink dark:text-gray-100">{{ $row['nama'] }}</div>
                                                <div class="font-mono text-xs text-muted dark:text-gray-400">{{ $row['suppId'] }}</div>
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-muted dark:text-gray-400">{{ $row['terakhir'] ?: '-' }}</td>
                                            <td class="px-4 py-2.5 font-mono text-right whitespace-nowrap">{{ number_format($row['awal'], 0, ',', '.') }}</td>
                                            <td class="px-4 py-2.5 font-mono text-right whitespace-nowrap">{{ number_format($row['setor'], 0, ',', '.') }}</td>
                                            <td class="px-4 py-2.5 font-mono text-right whitespace-nowrap">{{ number_format($row['pakai'], 0, ',', '.') }}</td>
                                            <td class="px-4 py-2.5 font-mono text-right whitespace-nowrap {{ $row['opname'] !== 0 ? 'text-violet-700 dark:text-violet-300' : 'text-muted-soft' }}">{{ $row['opname'] === 0 ? '-' : ($row['opname'] > 0 ? '+' : '−') . number_format(abs($row['opname']), 0, ',', '.') }}</td>
                                            <td class="px-4 py-2.5 font-mono font-bold text-right whitespace-nowrap {{ $row['akhir'] < 0 ? 'text-rose-700 dark:text-rose-300' : 'text-brand-green dark:text-brand-lime' }}">
                                                {{ number_format($row['akhir'], 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-6 py-16 text-center text-muted dark:text-gray-400">
                                                {{ trim($cariRekap) !== '' ? 'Tidak ada supplier yang cocok.' : 'Tidak ada saldo / transaksi DP di tahun ' . $tahunRekap . '.' }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if (count($rekap) > 0)
                                    <tfoot class="sticky bottom-0 z-10 font-semibold border-t text-body bg-surface-soft border-hairline dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700">
                                        <tr>
                                            <td colspan="2" class="px-4 py-3 text-right">TOTAL</td>
                                            <td class="px-4 py-3 font-mono text-right whitespace-nowrap">{{ number_format($totalRekap->sum('awal'), 0, ',', '.') }}</td>
                                            <td class="px-4 py-3 font-mono text-right whitespace-nowrap">{{ number_format($totalRekap->sum('setor'), 0, ',', '.') }}</td>
                                            <td class="px-4 py-3 font-mono text-right whitespace-nowrap">{{ number_format($totalRekap->sum('pakai'), 0, ',', '.') }}</td>
                                            <td class="px-4 py-3 font-mono text-right whitespace-nowrap">{{ number_format($totalRekap->sum('opname'), 0, ',', '.') }}</td>
                                            <td class="px-4 py-3 font-mono text-right whitespace-nowrap text-brand-green dark:text-brand-lime">{{ number_format($totalRekap->sum('akhir'), 0, ',', '.') }}</td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                @else
                <div class="flex flex-col flex-1 min-w-0 min-h-0 border shadow-sm bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    {{-- Ringkasan saldo DP — satu baris ringkas di atas riwayat --}}
                    <div class="flex flex-wrap items-baseline gap-x-6 gap-y-1 px-5 py-2.5 text-sm border-b border-hairline bg-surface-soft dark:bg-gray-800/60 dark:border-gray-700">
                        @php $rk = $this->ringkasan; @endphp
                        <span class="text-muted dark:text-gray-400">Saldo awal {{ $rk['tahunAwal'] ?? '' }}
                            <span class="ml-1 font-mono font-medium text-body dark:text-gray-200">Rp {{ number_format($rk['awal'], 0, ',', '.') }}</span></span>
                        <span class="text-muted dark:text-gray-400">Disetor
                            <span class="ml-1 font-mono font-medium text-body dark:text-gray-200">Rp {{ number_format($rk['setor'], 0, ',', '.') }}</span></span>
                        <span class="text-muted dark:text-gray-400">Terpakai
                            <span class="ml-1 font-mono font-medium text-body dark:text-gray-200">Rp {{ number_format($rk['pakai'], 0, ',', '.') }}</span></span>
                        @if ($rk['opname'] !== 0)
                            <span class="text-muted dark:text-gray-400">Opname
                                <span class="ml-1 font-mono font-medium text-violet-700 dark:text-violet-300">{{ $rk['opname'] > 0 ? '+' : '−' }}Rp {{ number_format(abs($rk['opname']), 0, ',', '.') }}</span></span>
                        @endif
                        <span class="text-muted dark:text-gray-400">Saldo DP
                            <span class="ml-1 font-mono text-base font-bold {{ $this->ringkasan['saldo'] < 0 ? 'text-rose-700 dark:text-rose-300' : 'text-brand-green dark:text-brand-lime' }}">Rp {{ number_format($this->ringkasan['saldo'], 0, ',', '.') }}</span></span>
                    </div>
                    <div class="px-5 py-3 border-b border-hairline dark:border-gray-700">
                        <div class="text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-300">Riwayat Setor, Pemakaian &amp; Opname DP</div>
                    </div>

                    <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto">
                        <table class="w-full min-w-full text-sm">
                            <thead class="sticky top-0 z-10 text-left text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-300">
                                <tr class="text-xs font-semibold tracking-wide uppercase">
                                    <th class="px-4 py-3">Tanggal</th>
                                    <th class="px-4 py-3">Jenis</th>
                                    <th class="px-4 py-3">Keterangan</th>
                                    <th class="px-4 py-3">Akun</th>
                                    <th class="px-4 py-3 text-right">Nominal</th>
                                    <th class="w-16 px-4 py-3 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-body divide-hairline dark:divide-gray-700 dark:text-gray-200">
                                @forelse ($this->riwayat as $row)
                                    <tr wire:key="riwayat-dp-{{ $row['jenis'] }}-{{ $row['no'] }}" class="hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                        <td class="px-4 py-2.5 whitespace-nowrap">
                                            <div>{{ $row['tgl'] }}</div>
                                            @if ($row['jenis'] !== 'awal')
                                                <div class="text-xs text-muted dark:text-gray-400">Shift {{ $row['shift'] ?: '-' }} · No {{ $row['no'] }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5">
                                            @if ($row['jenis'] === 'setor')
                                                <x-badge variant="success">Setor</x-badge>
                                            @elseif ($row['jenis'] === 'opname')
                                                <x-badge variant="purple">Opname</x-badge>
                                            @elseif ($row['jenis'] === 'awal')
                                                <x-badge variant="gray">Saldo Awal</x-badge>
                                            @else
                                                <x-badge variant="warning">Dipakai</x-badge>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5">{{ $row['ket'] ?: '-' }}</td>
                                        <td class="px-4 py-2.5 text-muted dark:text-gray-400">{{ $row['akun'] }}</td>
                                        @php
                                            [$warnaNilai, $tanda, $angka] = match ($row['jenis']) {
                                                'setor' => ['text-emerald-700 dark:text-emerald-400', '+', $row['nilai']],
                                                'pakai' => ['text-amber-700 dark:text-amber-400', '−', $row['nilai']],
                                                'opname' => ['text-violet-700 dark:text-violet-300', $row['nilai'] >= 0 ? '+' : '−', abs($row['nilai'])],
                                                default => ['text-body dark:text-gray-200 font-semibold', '', $row['nilai']],
                                            };
                                        @endphp
                                        <td class="px-4 py-2.5 font-mono text-right whitespace-nowrap {{ $warnaNilai }}">
                                            {{ $tanda }} {{ number_format($angka, 0, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-2.5 text-center">
                                            @if ($row['bolehHapus'])
                                                <x-hapus-button action="hapusSetor({{ $row['no'] }})"
                                                    title="Hapus Setoran (salah setor)"
                                                    :message="'Hapus setoran DP Rp ' . number_format($row['nilai']) . ' tanggal ' . $row['tgl'] . ' (shift ' . ($row['shift'] ?: '-') . ', ' . $row['akun'] . ')? PERHATIAN: saldo kas pada tanggal & shift tersebut ikut BERUBAH MUNDUR, dan saldo DP supplier berkurang. Ditolak bila DP-nya sudah terpakai.'"
                                                    confirmText="Ya, hapus setoran" />
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-16 text-center text-muted dark:text-gray-400">
                                            Belum ada saldo awal, setoran, pemakaian, atau opname DP untuk supplier ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- KONFIRMASI SETOR --}}
    @if (!empty($pratinjau))
        <div class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-gray-900/50"
            x-data x-init="$nextTick(() => $refs.btnYaSetor?.focus())">
            <div class="w-full max-w-md overflow-hidden border shadow-xl bg-canvas rounded-2xl border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="px-5 py-4 border-b border-hairline dark:border-gray-700">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-100">Konfirmasi Setor DP</h3>
                    <p class="mt-0.5 text-sm text-muted dark:text-gray-400">Uang keluar dari kas sebagai uang muka ke supplier.</p>
                </div>
                <dl class="px-5 py-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted dark:text-gray-400">Supplier</dt>
                        <dd class="font-medium text-right text-ink dark:text-gray-100">{{ $supplier['supp_name'] ?? '-' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted dark:text-gray-400">Nominal</dt>
                        <dd class="font-mono font-semibold text-brand-green dark:text-brand-lime">Rp {{ number_format($pratinjau['nominal']) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted dark:text-gray-400">Akun kas · shift</dt>
                        <dd class="text-right text-ink dark:text-gray-100">{{ $pratinjau['accName'] }} · Shift {{ $shift }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted dark:text-gray-400">Tanggal</dt>
                        <dd class="text-ink dark:text-gray-100">{{ $tanggal }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 pt-2 border-t border-hairline dark:border-gray-700">
                        <dt class="text-muted dark:text-gray-400">Saldo DP sesudahnya</dt>
                        <dd class="font-mono font-semibold text-ink dark:text-gray-100">Rp {{ number_format($pratinjau['saldoSesudah']) }}</dd>
                    </div>
                </dl>
                <div class="flex justify-end gap-2 px-5 py-4 border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                    <x-secondary-button type="button" wire:click="batalKonfirmasi"><x-ikon nama="batal" /> Batal</x-secondary-button>
                    <x-primary-button type="button" wire:click="simpan" x-ref="btnYaSetor"
                        wire:loading.attr="disabled" wire:target="simpan">
                        <span wire:loading.remove wire:target="simpan" class="inline-flex items-center gap-2"><x-ikon nama="simpan" /> Ya, simpan</span>
                        <span wire:loading wire:target="simpan"><x-loading /> Menyimpan...</span>
                    </x-primary-button>
                </div>
            </div>
        </div>
    @endif

</div>
