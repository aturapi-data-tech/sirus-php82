<?php

/**
 * Opname Saldo DP Supplier (PBF & Non-Medis) — menu tersendiri, KHUSUS ADMIN.
 *
 * Pola opname stok (TKTXN_SOWHS): saldo DP sebenarnya (kartu / rekening koran supplier) + alasan →
 * selisih terhadap saldo sistem dicatat sebagai baris di TKTXN_SODPSUPPS[NON] bertanggal SYSDATE.
 * Saldo awal & transaksi lama tidak diubah. TIDAK dijurnal ke keuangan (keputusan user).
 * Saldo sistem = App\Support\Keuangan\SaldoDpSupplier (satu rumus dengan halaman DP & Hutang).
 * Baris opname tidak bisa dihapus (jejak koreksi) — salah input dibetulkan dengan opname berikutnya.
 */

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Support\Keuangan\SaldoDpSupplier;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    private const ROLE_OPNAME = ['Admin'];

    public string $jenis = SaldoDpSupplier::JENIS_PBF;
    public ?string $suppId = null;
    public ?string $suppName = null;

    /** Saldo DP sebenarnya menurut kartu / rekening koran supplier. */
    public ?int $saldoSebenarnya = null;
    public ?string $alasan = null;
    /** Ringkasan dialog konfirmasi — kosong = tertutup. Hanya tampilan. */
    public array $pratinjau = [];

    /** Filter riwayat opname. */
    public int $tahunRiwayat = 0;

    public array $renderVersions = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(self::ROLE_OPNAME), 403, 'Opname saldo DP khusus Admin.');
        $this->registerAreas(['form']);
        $this->tahunRiwayat = (int) now()->year;
    }

    public function updatedJenis(): void
    {
        $this->jenis = $this->jenis === SaldoDpSupplier::JENIS_NON_MEDIS ? SaldoDpSupplier::JENIS_NON_MEDIS : SaldoDpSupplier::JENIS_PBF;
        $this->gantiSupplier();
    }

    #[On('lov.selected.opname-dp-supplier')]
    public function onSupplierSelected(string $target, ?array $payload): void
    {
        $this->suppId = $payload['supp_id'] ?? null;
        $this->suppName = $payload['supp_name'] ?? null;
        $this->reset(['saldoSebenarnya', 'alasan', 'pratinjau']);
        $this->resetValidation();
        unset($this->rincian, $this->riwayat);
    }

    public function gantiSupplier(): void
    {
        $this->reset(['suppId', 'suppName', 'saldoSebenarnya', 'alasan', 'pratinjau']);
        $this->resetValidation();
        $this->incrementVersion('form');
        unset($this->rincian, $this->riwayat);
    }

    #[Computed]
    public function rincian(): ?array
    {
        return $this->suppId ? SaldoDpSupplier::rincian($this->jenis, $this->suppId) : null;
    }

    /** Riwayat opname (supplier terpilih, atau semua supplier) di tahun filter, terbaru di atas. */
    #[Computed]
    public function riwayat(): array
    {
        $tabel = SaldoDpSupplier::tabel($this->jenis, 'opname');
        $th = max(2020, min((int) $this->tahunRiwayat, (int) now()->year));

        return DB::table("{$tabel} as o")
            ->leftJoin('immst_suppliers as s', 's.supp_id', '=', 'o.supp_id')
            ->leftJoin('immst_employers as e', 'e.emp_id', '=', 'o.emp_id')
            ->when($this->suppId, fn ($q) => $q->where('o.supp_id', $this->suppId))
            ->whereRaw("o.so_date >= TO_DATE(?,'YYYY-MM-DD') AND o.so_date < TO_DATE(?,'YYYY-MM-DD')", ["{$th}-01-01", ($th + 1) . '-01-01'])
            ->orderByDesc('o.so_date')->orderByDesc('o.so_no')
            ->limit(200)
            ->get([
                'o.so_no', 'o.supp_id', 's.supp_name', 'o.saldo_sistem', 'o.saldo_fisik', 'o.so_desc', 'o.shift', 'e.emp_name', 'o.user_code',
                DB::raw('NVL(o.so_d,0) - NVL(o.so_k,0) as selisih'),
                DB::raw("to_char(o.so_date,'dd/mm/yyyy hh24:mi:ss') as tgl"),
            ])
            ->map(fn ($r) => [
                'no' => (int) $r->so_no,
                'tgl' => (string) $r->tgl,
                'suppId' => (string) $r->supp_id,
                'nama' => (string) ($r->supp_name ?? $r->supp_id),
                'sistem' => (int) round((float) $r->saldo_sistem),
                'fisik' => (int) round((float) $r->saldo_fisik),
                'selisih' => (int) round((float) $r->selisih),
                'alasan' => (string) ($r->so_desc ?? ''),
                'petugas' => (string) ($r->emp_name ?: $r->user_code ?: '-'),
                'shift' => (string) ($r->shift ?? ''),
            ])->all();
    }

    /** Langkah 1: hitung selisih, tampilkan konfirmasi (belum menulis). */
    public function siapkanOpname(): void
    {
        if (!auth()->user()?->hasAnyRole(self::ROLE_OPNAME)) {
            $this->dispatch('toast', type: 'error', message: 'Opname saldo DP khusus Admin.');
            return;
        }
        $this->validate([
            'suppId' => 'required|string',
            'saldoSebenarnya' => 'required|integer',
            'alasan' => 'required|string|min:5|max:200',
        ], [
            'suppId.required' => 'Pilih supplier dulu.',
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

    /** Langkah 2: tulis. Saldo sistem dihitung ulang setelah baris supplier dikunci. */
    public function simpanOpname(): void
    {
        $this->pratinjau = [];
        if (!auth()->user()?->hasAnyRole(self::ROLE_OPNAME) || !$this->suppId || $this->saldoSebenarnya === null || blank($this->alasan)) {
            $this->dispatch('toast', type: 'error', message: 'Data opname belum lengkap / Anda tidak berwenang.');
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
        unset($this->rincian, $this->riwayat);
        $this->dispatch('toast', type: 'success', message: 'Opname tercatat: saldo DP Rp ' . number_format($hasil['sistem'])
            . ' → Rp ' . number_format($hasil['fisik']) . ' (selisih ' . ($hasil['selisih'] > 0 ? '+' : '−') . 'Rp ' . number_format(abs($hasil['selisih'])) . ').');
    }
};
?>

<div>
    <x-page-title title="Opname Saldo DP Supplier"
        subtitle="Samakan saldo uang muka per supplier dengan kartu / rekening koran supplier — tidak mengubah kas & jurnal" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">
            <div class="flex flex-col flex-1 min-h-0 gap-4 mt-2 lg:flex-row">

                {{-- KIRI: jenis, supplier, saldo sistem, form opname --}}
                <div class="flex flex-col gap-5 p-5 overflow-y-auto border shadow-sm shrink-0 lg:w-96 bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700"
                    wire:key="{{ $this->renderKey('form', [$jenis, $suppId]) }}">

                    <div>
                        <x-input-label value="Jenis Supplier" />
                        <x-tabs variant="pill" class="mt-1">
                            <x-tab :active="$jenis === 'pbf'" wire:click="$set('jenis', 'pbf')">PBF (Obat)</x-tab>
                            <x-tab :active="$jenis === 'non-medis'" wire:click="$set('jenis', 'non-medis')">Non-Medis</x-tab>
                        </x-tabs>
                    </div>

                    <div>
                        @if ($suppId)
                            <div class="flex items-start justify-between gap-2">
                                <x-input-label value="Supplier" />
                                <x-secondary-button type="button" wire:click="gantiSupplier"><x-ikon nama="ubah" /> Ganti</x-secondary-button>
                            </div>
                            <div class="px-4 py-3 mt-2 border rounded-xl border-hairline bg-surface-soft dark:bg-gray-800 dark:border-gray-700">
                                <div class="font-semibold text-ink dark:text-gray-100">{{ $suppName }}</div>
                                <div class="font-mono text-xs text-muted dark:text-gray-400">Kode {{ $suppId }}</div>
                            </div>
                        @else
                            <livewire:lov.supplier.lov-supplier
                                target="opname-dp-supplier"
                                label="Cari Supplier"
                                placeholder="Ketik kode / nama / telp supplier..."
                                :jenisSupplier="$jenis === 'pbf' ? 'medis' : 'nonmedis'"
                                wire:key="lov-supplier-opname-dp-{{ $jenis }}-{{ $renderVersions['form'] ?? 0 }}" />
                        @endif
                    </div>

                    @if ($suppId && $this->rincian)
                        @php $rk = $this->rincian; @endphp
                        <div class="pt-4 space-y-1.5 text-sm border-t border-hairline dark:border-gray-700">
                            <div class="flex justify-between gap-3"><span class="text-muted dark:text-gray-400">Saldo awal {{ $rk['tahunAwal'] ?? '' }}</span><span class="font-mono text-body dark:text-gray-300">Rp {{ number_format($rk['awal'], 0, ',', '.') }}</span></div>
                            <div class="flex justify-between gap-3"><span class="text-muted dark:text-gray-400">Disetor</span><span class="font-mono text-body dark:text-gray-300">Rp {{ number_format($rk['setor'], 0, ',', '.') }}</span></div>
                            <div class="flex justify-between gap-3"><span class="text-muted dark:text-gray-400">Terpakai</span><span class="font-mono text-body dark:text-gray-300">Rp {{ number_format($rk['pakai'], 0, ',', '.') }}</span></div>
                            <div class="flex justify-between gap-3"><span class="text-muted dark:text-gray-400">Opname (semua)</span><span class="font-mono text-violet-700 dark:text-violet-300">{{ $rk['opname'] >= 0 ? '+' : '−' }}Rp {{ number_format(abs($rk['opname']), 0, ',', '.') }}</span></div>
                            <div class="flex justify-between gap-3 pt-1.5 border-t border-hairline dark:border-gray-700"><span class="font-semibold text-ink dark:text-gray-100">Saldo DP sistem</span><span class="font-mono font-bold text-brand-green dark:text-brand-lime">Rp {{ number_format($rk['saldo'], 0, ',', '.') }}</span></div>
                        </div>

                        <div class="pt-4 space-y-3 border-t border-hairline dark:border-gray-700">
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
                        </div>
                    @endif

                    {{-- Panduan --}}
                    <div x-data="{ open: false }" class="overflow-hidden border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
                        <button type="button" @click="open = !open"
                            class="flex items-center justify-between w-full px-3 py-2 text-sm font-semibold text-blue-900 transition-colors hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Cara Pakai — Opname Saldo DP
                            </span>
                            <svg class="w-4 h-4 text-blue-600 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                        <div x-show="open" x-collapse class="px-3 pb-3 text-xs text-blue-900 dark:text-blue-200">
                            <ul class="ml-4 space-y-1 list-disc">
                                <li>Pilih jenis &amp; supplier → saldo DP sistem tampil (saldo awal + disetor − terpakai ± opname).</li>
                                <li>Isi saldo DP <strong>sebenarnya</strong> menurut kartu / rekening koran supplier + alasan → selisihnya dicatat sebagai baris Opname bertanggal hari ini (seperti opname stok).</li>
                                <li>Saldo awal &amp; transaksi lama tidak diubah. Kas &amp; jurnal keuangan tidak terpengaruh.</li>
                                <li>Opname tidak bisa dihapus (jejak koreksi). Salah input → lakukan opname berikutnya dengan angka yang benar.</li>
                                <li>Khusus Admin. Hasilnya langsung terpakai di rekap DP, detail supplier, dan pilihan Saldo DP saat bayar hutang.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- KANAN: riwayat opname --}}
                <div class="flex flex-col flex-1 min-w-0 min-h-0 border shadow-sm bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 border-b border-hairline dark:border-gray-700">
                        <div class="text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-300">
                            Riwayat Opname {{ $jenis === 'pbf' ? 'PBF' : 'Non-Medis' }} {{ $tahunRiwayat }}{{ $suppId ? ' — ' . $suppName : ' — semua supplier' }}
                        </div>
                        <x-select-input wire:model.live="tahunRiwayat" class="w-28 text-sm">
                            @for ($th = (int) now()->year; $th >= 2020; $th--)
                                <option value="{{ $th }}">{{ $th }}</option>
                            @endfor
                        </x-select-input>
                    </div>
                    <div class="flex-1 min-h-0 overflow-auto">
                        <table class="w-full min-w-full text-sm">
                            <thead class="sticky top-0 z-10 text-left text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-300">
                                <tr class="text-xs font-semibold tracking-wide uppercase">
                                    <th class="px-4 py-3">Tanggal</th>
                                    <th class="px-4 py-3">Supplier</th>
                                    <th class="px-4 py-3 text-right">Saldo Sistem</th>
                                    <th class="px-4 py-3 text-right">Sebenarnya</th>
                                    <th class="px-4 py-3 text-right">Selisih</th>
                                    <th class="px-4 py-3">Alasan · Petugas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-body divide-hairline dark:divide-gray-700 dark:text-gray-200">
                                @forelse ($this->riwayat as $row)
                                    <tr wire:key="opname-dp-{{ $row['no'] }}" class="hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                        <td class="px-4 py-2.5 whitespace-nowrap">
                                            <div>{{ $row['tgl'] }}</div>
                                            <div class="text-xs text-muted dark:text-gray-400">Shift {{ $row['shift'] ?: '-' }} · No {{ $row['no'] }}</div>
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <div class="text-ink dark:text-gray-100">{{ $row['nama'] }}</div>
                                            <div class="font-mono text-xs text-muted dark:text-gray-400">{{ $row['suppId'] }}</div>
                                        </td>
                                        <td class="px-4 py-2.5 font-mono text-right whitespace-nowrap">{{ number_format($row['sistem'], 0, ',', '.') }}</td>
                                        <td class="px-4 py-2.5 font-mono text-right whitespace-nowrap">{{ number_format($row['fisik'], 0, ',', '.') }}</td>
                                        <td class="px-4 py-2.5 font-mono font-semibold text-right whitespace-nowrap {{ $row['selisih'] >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-300' }}">
                                            {{ $row['selisih'] >= 0 ? '+' : '−' }}{{ number_format(abs($row['selisih']), 0, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <div>{{ $row['alasan'] }}</div>
                                            <div class="text-xs text-muted dark:text-gray-400">{{ $row['petugas'] }}</div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-6 py-16 text-center text-muted dark:text-gray-400">Belum ada opname di tahun {{ $tahunRiwayat }}.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- KONFIRMASI --}}
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
