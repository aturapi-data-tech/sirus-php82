<?php

/**
 * Kartu DP Supplier (PBF & Non-Medis) — pola Kartu Stock Apotek, KHUSUS ADMIN.
 *
 *   - Tahun + jenis + cari supplier → kiri: daftar supplier saldo awal / akhir tahun itu
 *   - Pilih supplier → kanan: saldo awal 1 Jan + mutasi bertanggal (Setor = masuk, Dipakai bayar nota
 *     = keluar, Opname = masuk/keluar) dengan saldo berjalan, ringkasan, dan form Opname.
 *   - Opname (pola stock opname TKTXN_SOAPTS): saldo sebenarnya + alasan → selisih dicatat di
 *     TKTXN_SODPSUPPS[NON] bertanggal SYSDATE; hanya tahun berjalan; TIDAK dijurnal ke keuangan;
 *     tidak bisa dihapus (salah input dibetulkan dengan opname berikutnya).
 *
 * Angka = App\Support\Keuangan\SaldoDpSupplier (rekapTahun / kartu / saldo) — satu rumus dengan
 * halaman DP & pilihan Saldo DP di Bayar Hutang.
 */

use Livewire\Component;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Support\Keuangan\SaldoDpSupplier;

new class extends Component {
    private const ROLE_KARTU = ['Admin'];

    public string $jenis = SaldoDpSupplier::JENIS_PBF;
    public int $tahun = 0;
    public string $cari = '';
    /** false = hanya supplier bersaldo / bertransaksi di tahun itu. */
    public bool $semua = false;

    public ?string $suppId = null;
    public ?string $suppName = null;

    /* ── Opname ── */
    public ?int $saldoSebenarnya = null;
    public ?string $alasan = null;
    /** Ringkasan dialog konfirmasi — kosong = tertutup. Hanya tampilan. */
    public array $pratinjau = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(self::ROLE_KARTU), 403, 'Kartu DP Supplier khusus Admin.');
        $this->tahun = (int) Carbon::now()->year;
    }

    public function updatedJenis(): void
    {
        $this->jenis = $this->jenis === SaldoDpSupplier::JENIS_NON_MEDIS ? SaldoDpSupplier::JENIS_NON_MEDIS : SaldoDpSupplier::JENIS_PBF;
        $this->tutupSupplier();
    }

    public function updatedTahun(): void
    {
        $this->tahun = max(2020, min((int) $this->tahun, (int) Carbon::now()->year));
        $this->reset(['saldoSebenarnya', 'alasan', 'pratinjau']);
        $this->resetValidation();
    }

    public function pilihSupplier(string $suppId): void
    {
        $this->suppId = $suppId;
        $this->suppName = (string) (DB::table('immst_suppliers')->where('supp_id', $suppId)->value('supp_name') ?? $suppId);
        $this->reset(['saldoSebenarnya', 'alasan', 'pratinjau']);
        $this->resetValidation();
    }

    public function tutupSupplier(): void
    {
        $this->reset(['suppId', 'suppName', 'saldoSebenarnya', 'alasan', 'pratinjau']);
        $this->resetValidation();
    }

    #[Computed]
    public function daftar(): array
    {
        return SaldoDpSupplier::rekapTahun($this->jenis, $this->tahun, $this->cari, $this->semua);
    }

    #[Computed]
    public function kartu(): ?array
    {
        return $this->suppId ? SaldoDpSupplier::kartu($this->jenis, $this->suppId, $this->tahun) : null;
    }

    public function bolehOpname(): bool
    {
        return $this->suppId !== null && $this->tahun === (int) Carbon::now()->year
            && (bool) auth()->user()?->hasAnyRole(self::ROLE_KARTU);
    }

    /* ── Opname: langkah 1 hitung selisih & konfirmasi ── */
    public function siapkanOpname(): void
    {
        if (!$this->bolehOpname()) {
            $this->dispatch('toast', type: 'error', message: 'Opname hanya untuk tahun berjalan (' . Carbon::now()->year . '), khusus Admin.');
            return;
        }
        $this->validate([
            'saldoSebenarnya' => 'required|integer',
            'alasan' => 'required|string|min:5|max:200',
        ], [
            'saldoSebenarnya.required' => 'Saldo DP sebenarnya wajib diisi (boleh 0).',
            'saldoSebenarnya.integer' => 'Saldo DP sebenarnya harus angka.',
            'alasan.required' => 'Alasan opname wajib diisi.',
            'alasan.min' => 'Alasan minimal 5 karakter.',
            'alasan.max' => 'Alasan maksimal 200 karakter.',
        ]);

        $sistem = SaldoDpSupplier::saldo($this->jenis, $this->suppId);
        $selisih = (int) $this->saldoSebenarnya - $sistem;
        if ($selisih === 0) {
            $this->dispatch('toast', type: 'info', message: 'Saldo sebenarnya sama dengan saldo sistem — tidak ada selisih untuk dicatat.');
            return;
        }
        $this->pratinjau = ['sistem' => $sistem, 'fisik' => (int) $this->saldoSebenarnya, 'selisih' => $selisih];
    }

    public function batalOpname(): void
    {
        $this->pratinjau = [];
    }

    /* ── Opname: langkah 2 tulis (saldo dihitung ulang setelah supplier dikunci) ── */
    public function simpanOpname(): void
    {
        $this->pratinjau = [];
        if (!$this->bolehOpname() || $this->saldoSebenarnya === null || blank($this->alasan)) {
            $this->dispatch('toast', type: 'error', message: 'Data opname belum lengkap / bukan tahun berjalan / Anda tidak berwenang.');
            return;
        }

        $user = auth()->user();
        $shift = DB::table('rstxn_shiftctls')->whereNotNull('shift_start')->whereNotNull('shift_end')
            ->whereRaw('? BETWEEN shift_start AND shift_end', [Carbon::now()->format('H:i:s')])->value('shift');
        $tabel = SaldoDpSupplier::tabel($this->jenis, 'opname');
        $seq = SaldoDpSupplier::tabel($this->jenis, 'seq');

        try {
            $hasil = DB::transaction(function () use ($user, $shift, $tabel, $seq) {
                DB::table('immst_suppliers')->where('supp_id', $this->suppId)->lockForUpdate()->first(['supp_id']);

                $sistem = SaldoDpSupplier::saldo($this->jenis, $this->suppId);
                $fisik = (int) $this->saldoSebenarnya;
                $selisih = $fisik - $sistem;
                if ($selisih === 0) {
                    throw new \RuntimeException('Saldo sebenarnya sama dengan saldo sistem — tidak ada selisih untuk dicatat.');
                }

                $soNo = (int) DB::selectOne("SELECT {$seq}.nextval AS val FROM dual")->val;
                DB::table($tabel)->insert([
                    'so_no' => $soNo,
                    'supp_id' => $this->suppId,
                    'so_date' => DB::raw('SYSDATE'),
                    'saldo_sistem' => $sistem,
                    'saldo_fisik' => $fisik,
                    'so_d' => max($selisih, 0),
                    'so_k' => max(-$selisih, 0),
                    'so_desc' => mb_substr(trim((string) $this->alasan), 0, 200),
                    'emp_id' => $user->emp_id ?? null,
                    'shift' => $shift !== null ? (string) $shift : null,
                    'user_code' => $user->myuser_code ?? null,
                ]);

                return ['no' => $soNo, 'sistem' => $sistem, 'fisik' => $fisik, 'selisih' => $selisih];
            });
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
            return;
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan opname: ' . $e->getMessage());
            return;
        }

        Log::info('Opname saldo DP supplier', $hasil + ['jenis' => $this->jenis, 'supp_id' => $this->suppId, 'user' => $user->myuser_code ?? null]);

        $this->reset(['saldoSebenarnya', 'alasan']);
        unset($this->daftar, $this->kartu);
        $this->dispatch('toast', type: 'success', message: 'Opname tercatat: saldo DP Rp ' . number_format($hasil['sistem'])
            . ' → Rp ' . number_format($hasil['fisik']) . ' (selisih ' . ($hasil['selisih'] > 0 ? '+' : '−') . 'Rp ' . number_format(abs($hasil['selisih'])) . ').');
    }
};
?>

<div>
    <x-page-title title="Kartu DP Supplier"
        subtitle="Saldo uang muka per supplier — saldo awal, setor, pemakaian, opname, saldo berjalan (pola kartu stok)" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 gap-3 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="flex flex-wrap items-end gap-4 p-4 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                <div class="w-28 shrink-0">
                    <x-input-label value="Tahun" />
                    <x-select-input wire:model.live="tahun" class="mt-1">
                        @for ($th = (int) now()->year; $th >= 2020; $th--)
                            <option value="{{ $th }}">{{ $th }}</option>
                        @endfor
                    </x-select-input>
                </div>
                <div>
                    <x-input-label value="Jenis" />
                    <x-tabs variant="pill" class="mt-1">
                        <x-tab :active="$jenis === 'pbf'" wire:click="$set('jenis', 'pbf')">PBF (Obat)</x-tab>
                        <x-tab :active="$jenis === 'non-medis'" wire:click="$set('jenis', 'non-medis')">Non-Medis</x-tab>
                    </x-tabs>
                </div>
                <div class="w-56 shrink-0">
                    <x-input-label value="Cari supplier" />
                    <x-text-input type="text" wire:model.live.debounce.400ms="cari" placeholder="Nama / kode..." class="mt-1 text-sm" />
                </div>
                <div class="pb-1.5">
                    <x-toggle wire:model.live="semua" :trueValue="true" :falseValue="false" label="Tampilkan saldo 0" class="whitespace-nowrap" />
                </div>
            </div>

            <div class="grid flex-1 min-h-0 grid-cols-1 gap-4 lg:grid-cols-12">

                {{-- KIRI: daftar supplier --}}
                <div class="flex flex-col min-h-0 border shadow-sm lg:col-span-4 bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    @php $daftar = $this->daftar; @endphp
                    <div class="px-4 py-3 text-sm font-semibold tracking-wide uppercase border-b text-muted border-hairline dark:text-gray-300 dark:border-gray-700">
                        Supplier {{ $tahun }} <span class="font-normal normal-case">({{ count($daftar) }})</span>
                    </div>
                    <div class="flex-1 min-h-0 overflow-auto">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 z-10 text-left text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-300">
                                <tr class="text-xs font-semibold tracking-wide uppercase">
                                    <th class="px-3 py-2">Supplier</th>
                                    <th class="px-3 py-2 text-right">Awal</th>
                                    <th class="px-3 py-2 text-right">Akhir</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-hairline dark:divide-gray-700">
                                @forelse ($daftar as $row)
                                    <tr wire:key="kartu-dp-supp-{{ $row['suppId'] }}" wire:click="pilihSupplier(@js($row['suppId']))"
                                        class="cursor-pointer transition {{ $suppId === $row['suppId'] ? 'bg-brand-green/10 dark:bg-brand-lime/10' : 'hover:bg-surface-soft dark:hover:bg-gray-800/60' }}">
                                        <td class="px-3 py-2">
                                            <div class="text-ink dark:text-gray-100">{{ $row['nama'] }}</div>
                                            <div class="font-mono text-xs text-muted dark:text-gray-400">{{ $row['suppId'] }} · {{ $row['terakhir'] ?? '-' }}</div>
                                        </td>
                                        <td class="px-3 py-2 font-mono text-right whitespace-nowrap text-body dark:text-gray-300">{{ number_format($row['awal'], 0, ',', '.') }}</td>
                                        <td class="px-3 py-2 font-mono font-semibold text-right whitespace-nowrap {{ $row['akhir'] < 0 ? 'text-rose-700 dark:text-rose-300' : 'text-brand-green dark:text-brand-lime' }}">{{ number_format($row['akhir'], 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="px-4 py-12 text-center text-muted dark:text-gray-400">Tidak ada supplier dengan saldo / transaksi DP di {{ $tahun }}.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- KANAN: kartu --}}
                <div class="flex flex-col min-h-0 gap-3 lg:col-span-8">
                    @if (!$suppId)
                        <div class="flex items-center justify-center flex-1 px-6 py-16 text-sm text-center border border-dashed rounded-2xl text-muted border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400">
                            Pilih supplier di kiri untuk melihat kartu DP-nya.
                        </div>
                    @else
                        @php $k = $this->kartu; @endphp
                        <div class="grid flex-1 min-h-0 grid-cols-1 gap-3 xl:grid-cols-12">

                            {{-- Riwayat mutasi --}}
                            <div class="flex flex-col min-h-0 border shadow-sm xl:col-span-8 bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                                <div class="flex items-center justify-between gap-2 px-4 py-3 border-b border-hairline dark:border-gray-700">
                                    <div>
                                        <div class="font-semibold text-ink dark:text-gray-100">{{ $suppName }}</div>
                                        <div class="font-mono text-xs text-muted dark:text-gray-400">{{ $suppId }} · {{ $jenis === 'pbf' ? 'PBF' : 'Non-Medis' }} · {{ $tahun }}</div>
                                    </div>
                                    <x-secondary-button type="button" wire:click="tutupSupplier"><x-ikon nama="batal" /> Tutup</x-secondary-button>
                                </div>
                                <div class="flex-1 min-h-0 overflow-auto">
                                    <table class="w-full text-sm">
                                        <thead class="sticky top-0 z-10 text-left text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-300">
                                            <tr class="text-xs font-semibold tracking-wide uppercase">
                                                <th class="px-3 py-2">Tanggal</th>
                                                <th class="px-3 py-2">Status</th>
                                                <th class="px-3 py-2">Keterangan</th>
                                                <th class="px-3 py-2 text-right text-emerald-700 dark:text-emerald-400">Masuk</th>
                                                <th class="px-3 py-2 text-right text-rose-700 dark:text-rose-300">Keluar</th>
                                                <th class="px-3 py-2 text-right">Saldo</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-hairline dark:divide-gray-700 text-body dark:text-gray-200">
                                            <tr class="bg-surface-soft/60 dark:bg-gray-800/40">
                                                <td class="px-3 py-2 whitespace-nowrap">01/01/{{ $tahun }}</td>
                                                <td class="px-3 py-2"><x-badge variant="gray">Saldo Awal</x-badge></td>
                                                <td class="px-3 py-2 text-muted dark:text-gray-400">Saldo awal tahun {{ $tahun }}</td>
                                                <td class="px-3 py-2"></td><td class="px-3 py-2"></td>
                                                <td class="px-3 py-2 font-mono font-semibold text-right whitespace-nowrap">{{ number_format($k['awal'], 0, ',', '.') }}</td>
                                            </tr>
                                            @foreach ($k['mutasi'] as $m)
                                                <tr wire:key="kartu-dp-{{ $m['jenis'] }}-{{ $m['no'] }}" class="hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                                    <td class="px-3 py-2 whitespace-nowrap">{{ $m['tgl'] }}</td>
                                                    <td class="px-3 py-2">
                                                        @if ($m['jenis'] === 'setor')
                                                            <x-badge variant="success">Setor DP</x-badge>
                                                        @elseif ($m['jenis'] === 'pakai')
                                                            <x-badge variant="warning">Dipakai</x-badge>
                                                        @else
                                                            <x-badge variant="purple">Opname</x-badge>
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <div>{{ $m['ket'] }}</div>
                                                        <div class="text-xs text-muted dark:text-gray-400">No {{ $m['no'] }} · {{ $m['info'] }}</div>
                                                    </td>
                                                    <td class="px-3 py-2 font-mono text-right whitespace-nowrap text-emerald-700 dark:text-emerald-400">{{ $m['masuk'] ? number_format($m['masuk'], 0, ',', '.') : '' }}</td>
                                                    <td class="px-3 py-2 font-mono text-right whitespace-nowrap text-rose-700 dark:text-rose-300">{{ $m['keluar'] ? number_format($m['keluar'], 0, ',', '.') : '' }}</td>
                                                    <td class="px-3 py-2 font-mono text-right whitespace-nowrap {{ $m['saldo'] < 0 ? 'text-rose-700 dark:text-rose-300' : '' }}">{{ number_format($m['saldo'], 0, ',', '.') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- Ringkasan + Opname --}}
                            <div class="flex flex-col gap-3 overflow-y-auto xl:col-span-4">
                                <div class="p-4 space-y-1.5 text-sm border shadow-sm bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                                    <div class="flex justify-between gap-3"><span class="text-muted dark:text-gray-400">Saldo awal {{ $tahun }}</span><span class="font-mono">{{ number_format($k['awal'], 0, ',', '.') }}</span></div>
                                    <div class="flex justify-between gap-3"><span class="text-muted dark:text-gray-400">Masuk</span><span class="font-mono text-emerald-700 dark:text-emerald-400">{{ number_format($k['masuk'], 0, ',', '.') }}</span></div>
                                    <div class="flex justify-between gap-3"><span class="text-muted dark:text-gray-400">Keluar</span><span class="font-mono text-rose-700 dark:text-rose-300">{{ number_format($k['keluar'], 0, ',', '.') }}</span></div>
                                    <div class="flex justify-between gap-3 pt-1.5 border-t border-hairline dark:border-gray-700">
                                        <span class="font-semibold text-ink dark:text-gray-100">Saldo akhir{{ $tahun === (int) now()->year ? ' (s.d. hari ini)' : '' }}</span>
                                        <span class="font-mono font-bold {{ $k['akhir'] < 0 ? 'text-rose-700 dark:text-rose-300' : 'text-brand-green dark:text-brand-lime' }}">{{ number_format($k['akhir'], 0, ',', '.') }}</span>
                                    </div>
                                </div>

                                <div class="p-4 space-y-3 border shadow-sm bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                                    <div>
                                        <div class="text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-300">Opname Saldo DP</div>
                                        <p class="mt-0.5 text-xs text-muted dark:text-gray-400">Isi saldo DP sebenarnya (kartu / rekening koran supplier). Selisih dicatat sebagai baris Opname hari ini. Kas &amp; jurnal tidak berubah.</p>
                                    </div>
                                    @if ($this->bolehOpname())
                                        <div>
                                            <x-input-label value="Saldo DP Sebenarnya (Rp)" :required="true" />
                                            <div class="mt-1"><x-text-input-number wire:model="saldoSebenarnya" :error="$errors->has('saldoSebenarnya')" /></div>
                                            <x-input-error :messages="$errors->get('saldoSebenarnya')" class="mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="Alasan" :required="true" />
                                            <x-text-input type="text" wire:model="alasan" maxlength="200" class="w-full mt-1"
                                                placeholder="Mis. cocokkan kartu DP supplier per 30/11/2026" :error="$errors->has('alasan')" />
                                            <x-input-error :messages="$errors->get('alasan')" class="mt-1" />
                                        </div>
                                        <x-primary-button type="button" wire:click="siapkanOpname" class="justify-center w-full h-11"
                                            wire:loading.attr="disabled" wire:target="siapkanOpname">
                                            <span wire:loading.remove wire:target="siapkanOpname" class="inline-flex items-center gap-2"><x-ikon nama="proses" /> Hitung Selisih &amp; Opname</span>
                                            <span wire:loading wire:target="siapkanOpname"><x-loading /> Memeriksa...</span>
                                        </x-primary-button>
                                    @else
                                        <p class="px-3 py-2 text-xs border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:text-amber-200 dark:bg-amber-900/20 dark:border-amber-700">
                                            Data terkunci. Opname hanya untuk tahun berjalan ({{ now()->year }}).
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- KONFIRMASI OPNAME --}}
    @if (!empty($pratinjau))
        <div class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-gray-900/50" x-data x-init="$nextTick(() => $refs.btnYaOpname?.focus())">
            <div class="w-full max-w-md overflow-hidden border shadow-xl bg-canvas rounded-2xl border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="px-5 py-4 border-b border-hairline dark:border-gray-700">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-100">Konfirmasi Opname Saldo DP</h3>
                    <p class="mt-0.5 text-sm text-muted dark:text-gray-400">Selisih dicatat sebagai baris opname hari ini. Kas &amp; jurnal keuangan tidak berubah.</p>
                </div>
                <dl class="px-5 py-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-muted dark:text-gray-400">Supplier</dt><dd class="font-medium text-right text-ink dark:text-gray-100">{{ $suppName }} ({{ $jenis === 'pbf' ? 'PBF' : 'Non-Medis' }})</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted dark:text-gray-400">Saldo sistem</dt><dd class="font-mono text-ink dark:text-gray-100">Rp {{ number_format($pratinjau['sistem']) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted dark:text-gray-400">Saldo sebenarnya</dt><dd class="font-mono text-ink dark:text-gray-100">Rp {{ number_format($pratinjau['fisik']) }}</dd></div>
                    <div class="flex justify-between gap-3 pt-2 border-t border-hairline dark:border-gray-700">
                        <dt class="text-muted dark:text-gray-400">Selisih dicatat</dt>
                        <dd class="font-mono font-semibold {{ $pratinjau['selisih'] > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-300' }}">{{ $pratinjau['selisih'] > 0 ? '+' : '−' }}Rp {{ number_format(abs($pratinjau['selisih'])) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3"><dt class="text-muted dark:text-gray-400">Alasan</dt><dd class="text-right text-ink dark:text-gray-100">{{ $alasan }}</dd></div>
                </dl>
                <div class="flex justify-end gap-2 px-5 py-4 border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                    <x-secondary-button type="button" wire:click="batalOpname"><x-ikon nama="batal" /> Batal</x-secondary-button>
                    <x-primary-button type="button" wire:click="simpanOpname" x-ref="btnYaOpname" wire:loading.attr="disabled" wire:target="simpanOpname">
                        <span wire:loading.remove wire:target="simpanOpname" class="inline-flex items-center gap-2"><x-ikon nama="simpan" /> Ya, catat opname</span>
                        <span wire:loading wire:target="simpanOpname"><x-loading /> Menyimpan...</span>
                    </x-primary-button>
                </div>
            </div>
        </div>
    @endif
</div>
